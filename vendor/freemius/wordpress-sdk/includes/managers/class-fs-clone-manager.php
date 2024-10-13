<?php
    /**
     * @package   Freemius
     * @copyright Copyright (c) 2015, Freemius, Inc.
     * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @author    Leo Fajardo (@leorw)
     * @since     2.5.0
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * Manages the detection of clones and provides the logged-in WordPress user with options for manually resolving them.
     *
     * @since 2.5.0
     *
     * @property int    $clone_identification_timestamp
     * @property int    $temporary_duplicate_mode_selection_timestamp
     * @property int    $temporary_duplicate_notice_shown_timestamp
     * @property string $request_handler_id
     * @property int    $request_handler_timestamp
     * @property int    $request_handler_retries_count
     * @property bool   $hide_manual_resolution
     * @property array  $new_blog_install_map
     */
    class FS_Clone_Manager {
        /**
         * @var FS_Option_Manager
         */
        private $_storage;
        /**
         * @var FS_Option_Manager
         */
        private $_network_storage;
        /**
         * @var FS_Admin_Notices
         */
        private $_notices;
        /**
         * @var FS_Logger
         */
        protected $_logger;

        /**
         * @var int 3 minutes
         */
        const CLONE_RESOLUTION_MAX_EXECUTION_TIME = 180;
        /**
         * @var int
         */
        const CLONE_RESOLUTION_MAX_RETRIES = 3;
        /**
         * @var int
         */
        const TEMPORARY_DUPLICATE_PERIOD = WP_FS__TIME_WEEK_IN_SEC * 2;
        /**
         * @var string
         */
        const OPTION_NAME = 'clone_resolution';
        /**
         * @var string
         */
        const OPTION_MANAGER_NAME = 'clone_management';
        /**
         * @var string
         */
        const OPTION_TEMPORARY_DUPLICATE = 'temporary_duplicate';
        /**
         * @var string
         */
        const OPTION_LONG_TERM_DUPLICATE = 'long_term_duplicate';
        /**
         * @var string
         */
        const OPTION_NEW_HOME = 'new_home';

        #--------------------------------------------------------------------------------
        #region Singleton
        #--------------------------------------------------------------------------------

        /**
         * @var FS_Clone_Manager
         */
        private static $_instance;

        /**
         * @return FS_Clone_Manager
         */
        static function instance() {
            if ( ! isset( self::$_instance ) ) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        #endregion

        private function __construct() {
            $this->_storage         = FS_Option_Manager::get_manager( WP_FS___OPTION_PREFIX . self::OPTION_MANAGER_NAME, true );
            $this->_network_storage = FS_Option_Manager::get_manager( WP_FS___OPTION_PREFIX . self::OPTION_MANAGER_NAME, true, true );

            $this->maybe_migrate_options();

            $this->_notices = FS_Admin_Notices::instance( 'global_clone_resolution_notices', '', '', true );
            $this->_logger  = FS_Logger::get_logger( WP_FS__SLUG . '_' . '_clone_manager', WP_FS__DEBUG_SDK, WP_FS__ECHO_DEBUG_SDK );
        }

        /**
         * Migrate clone resolution options from 2.5.0 array-based structure, to a new flat structure.
         *
         * The reason this logic is not in a separate migration script is that we want to be 100% sure data is migrated before any execution of clone logic.
         *
         * @todo Delete this one in the future.
         */
        private function maybe_migrate_options() {
            $storages = array(
                $this->_storage,
                $this->_network_storage
            );

            foreach ( $storages as $storage ) {
                $clone_data = $storage->get_option( self::OPTION_NAME );
                if ( is_array( $clone_data ) && ! empty( $clone_data ) ) {
                    foreach ( $clone_data as $key => $val ) {
                        if ( ! is_null( $val ) ) {
                            $storage->set_option( $key, $val );
                        }
                    }

                    $storage->unset_option( self::OPTION_NAME, true );
                }
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         */
        function _init() {
            if ( is_admin() ) {
                if ( Freemius::is_admin_post() ) {
                    add_action( 'admin_post_fs_clone_resolution', array( $this, '_handle_clone_resolution' ) );
                }

                if ( Freemius::is_ajax() ) {
                    Freemius::add_ajax_action_static( 'handle_clone_resolution', array( $this, '_clone_resolution_action_ajax_handler' ) );
                } else {
                    if (
                        empty( $this->get_clone_identification_timestamp() ) &&
                        (
                            ! fs_is_network_admin() ||
                            ! ( $this->is_clone_resolution_options_notice_shown() || $this->is_temporary_duplicate_notice_shown() )
                        )
                    ) {
                        $this->hide_clone_admin_notices();
                    } else if ( ! Freemius::is_cron() && ! Freemius::is_admin_post() ) {
                        $this->try_resolve_clone_automatically();
                        $this->maybe_show_clone_admin_notice();

                        add_action( 'admin_footer', array( $this, '_add_clone_resolution_javascript' ) );
                    }
                }
            }
        }

        /**
         * Retrieves the timestamp that was stored when a clone was identified.
         *
         * @return int|null
         */
        function get_clone_identification_timestamp() {
            return $this->get_option( 'clone_identification_timestamp', true );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.1
         *
         * @param string $sdk_last_version
         */
        function maybe_update_clone_resolution_support_flag( $sdk_last_version ) {
            if ( null !== $this->hide_manual_resolution ) {
                return;
            }

            $this->hide_manual_resolution = (
                ! empty( $sdk_last_version ) &&
                version_compare( $sdk_last_version, '2.5.0', '<' )
            );
        }

        /**
         * Stores the time when a clone was identified.
         */
        function store_clone_identification_timestamp() {
            $this->clone_identification_timestamp = time();
        }

        /**
         * Retrieves the timestamp for the temporary duplicate mode's expiration.
         *
         * @return int
         */
        function get_temporary_duplicate_expiration_timestamp() {
            $temporary_duplicate_mode_start_timestamp = $this->was_temporary_duplicate_mode_selected() ?
                $this->temporary_duplicate_mode_selection_timestamp :
                $this->get_clone_identification_timestamp();

            return ( $temporary_duplicate_mode_start_timestamp + self::TEMPORARY_DUPLICATE_PERIOD );
        }

        /**
         * Determines if the SDK should handle clones. The SDK handles clones only up to 3 times with 3 min interval.
         *
         * @return bool
         */
        private function should_handle_clones() {
            if ( ! isset( $this->request_handler_timestamp ) ) {
                return true;
            }

            if ( $this->request_handler_retries_count >= self::CLONE_RESOLUTION_MAX_RETRIES ) {
                return false;
            }

            // Give the logic that handles clones enough time to finish (it is given 3 minutes for now).
            return ( time() > ( $this->request_handler_timestamp + self::CLONE_RESOLUTION_MAX_EXECUTION_TIME ) );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.1
         *
         * @return bool
         */
        function should_hide_manual_resolution() {
            return ( true === $this->hide_manual_resolution );
        }

        /**
         * Executes the clones handler logic if it should be executed, i.e., based on the return value of the should_handle_clones() method.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         */
        function maybe_run_clone_resolution() {
            if ( ! $this->should_handle_clones() ) {
                return;
            }

            $this->request_handler_retries_count = isset( $this->request_handler_retries_count ) ?
                ( $this->request_handler_retries_count + 1 ) :
                1;

            $this->request_handler_timestamp = time();

            $handler_id               = ( rand() . microtime() );
            $this->request_handler_id = $handler_id;

            // Add cookies to trigger request with the same user access permissions.
            $cookies = array();
            foreach ( $_COOKIE as $name => $value ) {
                $cookies[] = new WP_Http_Cookie( array(
                    'name'  => $name,
                    'value' => $value,
                ) );
            }

            wp_remote_post(
                admin_url( 'admin-post.php' ),
                array(
                    'method'    => 'POST',
                    'body'      => array(
                        'action'     => 'fs_clone_resolution',
                        'handler_id' => $handler_id,
                    ),
                    'timeout'   => 0.01,
                    'blocking'  => false,
                    'sslverify' => false,
                    'cookies'   => $cookies,
                )
            );
        }

        /**
         * Executes the clones handler logic.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         */
        function _handle_clone_resolution() {
            $handler_id = fs_request_get( 'handler_id' );

            if ( empty( $handler_id ) ) {
                return;
            }

            if (
                ! isset( $this->request_handler_id ) ||
                $this->request_handler_id !== $handler_id
            ) {
                return;
            }

            if ( ! $this->try_automatic_resolution() ) {
                $this->clear_temporary_duplicate_notice_shown_timestamp();
            }
        }

        #--------------------------------------------------------------------------------
        #region Automatic Clone Resolution
        #--------------------------------------------------------------------------------

        /**
         * @var array All installs cache.
         */
        private $all_installs;

        /**
         * Checks if a given instance's install is a clone of another subsite in the network.
         *
         * @author Vova Feldman (@svovaf)
         *
         * @return FS_Site
         */
        private function find_network_subsite_clone_install( Freemius $instance ) {
            if ( ! is_multisite() ) {
                // Not a multi-site network.
                return null;
            }

            if ( ! isset( $this->all_installs ) ) {
                $this->all_installs = FS_DebugManager::get_all_modules_sites();
            }

            // Check if there's another blog that has the same site.
            $module_type          = $instance->get_module_type();
            $sites_by_module_type = ! empty( $this->all_installs[ $module_type ] ) ?
                $this->all_installs[ $module_type ] :
                array();

            $slug          = $instance->get_slug();
            $sites_by_slug = ! empty( $sites_by_module_type[ $slug ] ) ?
                $sites_by_module_type[ $slug ] :
                array();

            $current_blog_id = get_current_blog_id();

            $current_install = $instance->get_site();

            foreach ( $sites_by_slug as $site ) {
                if (
                    $current_install->id == $site->id &&
                    $current_blog_id != $site->blog_id
                ) {
                    // Clone is identical to an install on another subsite in the network.
                    return $site;
                }
            }

            return null;
        }

        /**
         * Tries to find a different install of the context product that is associated with the current URL and loads it.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         *
         * @param Freemius $instance
         * @param string   $url
         *
         * @return object
         */
        private function find_other_install_by_url( Freemius $instance, $url ) {
            $result = $instance->get_api_user_scope()->get( "/plugins/{$instance->get_id()}/installs.json?url=" . urlencode( $url ) . "&all=true", true );

            $current_install = $instance->get_site();

            if ( $instance->is_api_result_object( $result, 'installs' ) ) {
                foreach ( $result->installs as $install ) {
                    if ( $install->id == $current_install->id ) {
                        continue;
                    }

                    if (
                        $instance->is_only_premium() &&
                        ! FS_Plugin_License::is_valid_id( $install->license_id )
                    ) {
                        continue;
                    }

                    // When searching for installs by a URL, the API will first strip any paths and search for any matching installs by the subdomain. Therefore, we need to test if there's a match between the current URL and the install's URL before continuing.
                    if ( $url !== fs_strip_url_protocol( untrailingslashit( $install->url ) ) ) {
                        continue;
                    }

                    // Found a different install that is associated with the current URL, load it and replace the current install with it if no updated install is found.
                    return $install;
                }
            }

            return null;
        }

        /**
         * Delete the current install associated with a given instance and opt-in/activate-license to create a fresh install.
         *
         * @author Vova Feldman (@svovaf)
         * @since 2.5.0
         *
         * @param Freemius    $instance
         * @param string|false $license_key
         *
         * @return bool TRUE if successfully connected. FALSE if failed and had to restore install from backup.
         */
        private function delete_install_and_connect( Freemius $instance, $license_key = false ) {
            $user = Freemius::_get_user_by_id( $instance->get_site()->user_id );

            $instance->delete_current_install( true );

            if ( ! is_object( $user ) ) {
                // Get logged-in WordPress user.
                $current_user = Freemius::_get_current_wp_user();

                // Find the relevant FS user by email address.
                $user = Freemius::_get_user_by_email( $current_user->user_email );
            }

            if ( is_object( $user ) ) {
                // When a clone is found, we prefer to use the same user of the original install for the opt-in.
                $instance->install_with_user( $user, $license_key, false, false );
            } else {
                // If no user is found, activate with the license.
                $instance->opt_in(
                    false,
                    false,
                    false,
                    $license_key
                );
            }

            if ( is_object( $instance->get_site() ) ) {
                // Install successfully created.
                return true;
            }

            // Restore from backup.
            $instance->restore_backup_site();

            return false;
        }

        /**
         * Try to resolve the clone situation automatically.
         *
         * @param Freemius  $instance
         * @param string    $current_url
         * @param bool      $is_localhost
         * @param bool|null $is_clone_of_network_subsite
         *
         * @return bool If managed to automatically resolve the clone.
         */
        private function try_resolve_clone_automatically_by_instance(
            Freemius $instance,
            $current_url,
            $is_localhost,
            $is_clone_of_network_subsite = null
        ) {
            // Try to find a different install of the context product that is associated with the current URL.
            $associated_install = $this->find_other_install_by_url( $instance, $current_url );

            if ( is_object( $associated_install ) ) {
                // Replace the current install with a different install that is associated with the current URL.
                $instance->store_site( new FS_Site( clone $associated_install ) );
                $instance->sync_install( array( 'is_new_site' => true ), true );

                return true;
            }

            if ( ! $instance->is_premium() ) {
                // For free products, opt-in with the context user to create new install.
                return $this->delete_install_and_connect( $instance );
            }

            $license              = $instance->_get_license();
            $can_activate_license = ( is_object( $license ) && ! $license->is_utilized( $is_localhost ) );

            if ( ! $can_activate_license ) {
                // License can't be activated, therefore, can't be automatically resolved.
                return false;
            }

            if ( ! WP_FS__IS_LOCALHOST_FOR_SERVER && ! $is_localhost ) {
                $is_clone_of_network_subsite = ( ! is_null( $is_clone_of_network_subsite ) ) ?
                    $is_clone_of_network_subsite :
                    is_object( $this->find_network_subsite_clone_install( $instance ) );

                if ( ! $is_clone_of_network_subsite ) {
                    return false;
                }
            }

            // If the site is a clone of another subsite in the network, or a localhost one, try to auto activate the license.
            return $this->delete_install_and_connect( $instance, $license->secret_key );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         */
        private function try_resolve_clone_automatically() {
            $clone_action = $this->get_clone_resolution_action_from_config();

            if ( ! empty( $clone_action ) ) {
                $this->try_resolve_clone_automatically_by_config( $clone_action );
                return;
            }

            $this->try_automatic_resolution();
        }

        /**
         * Tries to resolve the clone situation automatically based on the config in the wp-config.php file.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         *
         * @param string $clone_action
         */
        private function try_resolve_clone_automatically_by_config( $clone_action ) {
            $fs_instances = array();

            if ( self::OPTION_LONG_TERM_DUPLICATE === $clone_action ) {
                $instances = Freemius::_get_all_instances();

                foreach ( $instances as $instance ) {
                    if ( ! $instance->is_registered() ) {
                        continue;
                    }

                    if ( ! $instance->is_clone() ) {
                        continue;
                    }

                    $license = $instance->has_features_enabled_license() ?
                        $instance->_get_license() :
                        null;

                    if (
                        is_object( $license ) &&
                        ! $license->is_utilized(
                            ( WP_FS__IS_LOCALHOST_FOR_SERVER || FS_Site::is_localhost_by_address( Freemius::get_unfiltered_site_url() ) )
                        )
                    ) {
                        $fs_instances[] = $instance;
                    }
                }

                if ( empty( $fs_instances ) ) {
                    return;
                }
            }

            $this->resolve_cloned_sites( $clone_action, $fs_instances );
        }

        /**
         * @author Leo Fajard (@leorw)
         * @since 2.5.0
         *
         * @return string|null
         */
        private function get_clone_resolution_action_from_config() {
            if ( ! defined( 'FS__RESOLVE_CLONE_AS' ) ) {
                return null;
            }

            if ( ! in_array(
                FS__RESOLVE_CLONE_AS,
                array(
                    self::OPTION_NEW_HOME,
                    self::OPTION_TEMPORARY_DUPLICATE,
                    self::OPTION_LONG_TERM_DUPLICATE,
                )
            ) ) {
                return null;
            }

            return FS__RESOLVE_CLONE_AS;
        }

        /**
         * Tries to recover the install of a newly created subsite or resolve it if it's a clone.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         *
         * @param Freemius $instance
         */
        function maybe_resolve_new_subsite_install_automatically( Freemius $instance ) {
            if ( ! $instance->is_user_in_admin() ) {
                // Try to recover an install or resolve a clone only when there's a user in admin to prevent doing it prematurely (e.g., the install can get replaced with clone data again).
                return;
            }

            if ( ! is_multisite() ) {
                return;
            }

            $new_blog_install_map = $this->new_blog_install_map;

            if ( empty( $new_blog_install_map ) || ! is_array( $new_blog_install_map ) ) {
                return;
            }

            $is_network_admin = fs_is_network_admin();

            if ( ! $is_network_admin ) {
                // If not in network admin, handle the current site.
                $blog_id = get_current_blog_id();
            } else {
                // If in network admin, handle only the first site.
                $blog_ids = array_keys( $new_blog_install_map );
                $blog_id  = $blog_ids[0];
            }

            if ( ! isset( $new_blog_install_map[ $blog_id ] ) ) {
                // There's no site to handle.
                return;
            }

            $expected_install_id = $new_blog_install_map[ $blog_id ]['install_id'];

            $current_install    = $instance->get_install_by_blog_id( $blog_id );
            $current_install_id = is_object( $current_install ) ?
                $current_install->id :
                null;

            if ( $expected_install_id == $current_install_id ) {
                // Remove the current site's information from the map to prevent handling it again.
                $this->remove_new_blog_install_info_from_storage( $blog_id );

                return;
            }

            require_once WP_FS__DIR_INCLUDES . '/class-fs-lock.php';

            $lock = new FS_Lock( self::OPTION_NAME . '_subsite' );

            if ( ! $lock->try_lock(60) ) {
                return;
            }

            $instance->switch_to_blog( $blog_id );

            $current_url          = untrailingslashit( Freemius::get_unfiltered_site_url( null, true ) );
            $current_install_url  = is_object( $current_install ) ?
                fs_strip_url_protocol( untrailingslashit( $current_install->url ) ) :
                null;

            // This can be `false` even if the install is a clone as the URL can be updated as part of the cloning process.
            $is_clone = ( ! is_null( $current_install_url ) && $current_url !== $current_install_url );

            if ( ! FS_Site::is_valid_id( $expected_install_id ) ) {
                $expected_install = null;
            } else {
                $expected_install = $instance->fetch_install_by_id( $expected_install_id );
            }

            if ( FS_Api::is_api_result_entity( $expected_install ) ) {
                // Replace the current install with the expected install.
                $instance->store_site( new FS_Site( clone $expected_install ) );
                $instance->sync_install( array( 'is_new_site' => true ), true );
            } else {
                $network_subsite_clone_install = null;

                if ( ! $is_clone ) {
                    // It is possible that `$is_clone` is `false` but the install is actually a clone as the following call checks the install ID and not the URL.
                    $network_subsite_clone_install = $this->find_network_subsite_clone_install( $instance );
                }

                if ( $is_clone || is_object( $network_subsite_clone_install ) ) {
                    // If there's no expected install (or it couldn't be fetched) and the current install is a clone, try to resolve the clone automatically.
                    $is_localhost = FS_Site::is_localhost_by_address( $current_url );

                    $resolved = $this->try_resolve_clone_automatically_by_instance( $instance, $current_url, $is_localhost, is_object( $network_subsite_clone_install ) );

                    if ( ! $resolved && is_object( $network_subsite_clone_install ) ) {
                        if ( empty( $this->get_clone_identification_timestamp() ) ) {
                            $this->store_clone_identification_timestamp();
                        }

                        // Since the clone couldn't be identified based on the URL, replace the stored install with the cloned install so that the manual clone resolution notice will appear.
                        $instance->store_site( clone $network_subsite_clone_install );
                    }
                }
            }

            $instance->restore_current_blog();

            // Remove the current site's information from the map to prevent handling it again.
            $this->remove_new_blog_install_info_from_storage( $blog_id );

            $lock->unlock();
        }

        /**
         * If a new install was created after creating a new subsite, its ID is stored in the blog-install map so that it can be recovered in case it's replaced with a clone install (e.g., when the newly created subsite is a clone). The IDs of the clone subsites that were created while not running this version of the SDK or a higher version will also be stored in the said map so that the clone manager can also try to resolve them later on.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         *
         * @param int     $blog_id
         * @param FS_Site $site
         */
        function store_blog_install_info( $blog_id, $site = null ) {
            $new_blog_install_map = $this->new_blog_install_map;

            if (
                empty( $new_blog_install_map ) ||
                ! is_array( $new_blog_install_map )
            ) {
                $new_blog_install_map = array();
            }

            $install_id = null;

            if ( is_object( $site ) ) {
                $install_id = $site->id;
            }

            $new_blog_install_map[ $blog_id ] = array( 'install_id' => $install_id );

            $this->new_blog_install_map = $new_blog_install_map;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         *
         * @param int $blog_id
         */
        private function remove_new_blog_install_info_from_storage( $blog_id ) {
            $new_blog_install_map = $this->new_blog_install_map;

            unset( $new_blog_install_map[ $blog_id ] );
            $this->new_blog_install_map = $new_blog_install_map;
        }

        /**
         * Tries to resolve all clones automatically.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         *
         * @return bool If managed to automatically resolve all clones.
         */
        private function try_automatic_resolution() {
            $this->_logger->entrance();

            require_once WP_FS__DIR_INCLUDES . '/class-fs-lock.php';

            $lock = new FS_Lock( self::OPTION_NAME );

            /**
             * Try to acquire lock for the next 60 sec based on the thread ID.
             */
            if ( ! $lock->try_lock( 60 ) ) {
                return false;
            }

            $current_url  = untrailingslashit( Freemius::get_unfiltered_site_url( null, true ) );
            $is_localhost = FS_Site::is_localhost_by_address( $current_url );

            $require_manual_resolution = false;

            $instances = Freemius::_get_all_instances();

            foreach ( $instances as $instance ) {
                if ( ! $instance->is_registered() ) {
                    continue;
                }

                if ( ! $instance->is_clone() ) {
                    continue;
                }

                if ( ! $this->try_resolve_clone_automatically_by_instance( $instance, $current_url, $is_localhost ) ) {
                    $require_manual_resolution = true;
                }
            }

            // Create a 1-day lock.
            $lock->lock( WP_FS__TIME_24_HOURS_IN_SEC );

            return ( ! $require_manual_resolution );
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Manual Clone Resolution
        #--------------------------------------------------------------------------------

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         */
        function _add_clone_resolution_javascript() {
            $vars = array( 'ajax_action' => Freemius::get_ajax_action_static( 'handle_clone_resolution' ) );

            fs_require_once_template( 'clone-resolution-js.php', $vars );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         */
        function _clone_resolution_action_ajax_handler() {
            $this->_logger->entrance();

            check_ajax_referer( Freemius::get_ajax_action_static( 'handle_clone_resolution' ), 'security' );

            $clone_action = fs_request_get( 'clone_action' );
            $blog_id      = is_multisite() ?
                fs_request_get( 'blog_id' ) :
                0;

            if ( is_multisite() && $blog_id == get_current_blog_id() ) {
                $blog_id = 0;
            }

            if ( empty( $clone_action ) ) {
                Freemius::shoot_ajax_failure( array(
                    'message'      => fs_text_inline( 'Invalid clone resolution action.', 'invalid-clone-resolution-action-error' ),
                    'redirect_url' => '',
                ) );
            }

            $result = $this->resolve_cloned_sites( $clone_action, array(), $blog_id );

            Freemius::shoot_ajax_success( $result );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         *
         * @param string     $clone_action
         * @param Freemius[] $fs_instances
         * @param int        $blog_id
         *
         * @return array
         */
        private function resolve_cloned_sites( $clone_action, $fs_instances = array(), $blog_id = 0 ) {
            $this->_logger->entrance();

            $result = array();

            $instances_with_clone       = array();
            $instances_with_clone_count = 0;
            $install_by_instance_id     = array();

            $instances = ( ! empty( $fs_instances ) ) ?
                $fs_instances :
                Freemius::_get_all_instances();

            $should_switch_to_blog = ( $blog_id > 0 );

            foreach ( $instances as $instance ) {
                if ( $should_switch_to_blog ) {
                    $instance->switch_to_blog( $blog_id );
                }

                if ( $instance->is_registered() && $instance->is_clone() ) {
                    $instances_with_clone[] = $instance;

                    $instances_with_clone_count ++;

                    $install_by_instance_id[ $instance->get_id() ] = $instance->get_site();
                }
            }

            if ( self::OPTION_TEMPORARY_DUPLICATE === $clone_action ) {
                $this->store_temporary_duplicate_timestamp();
            } else {
                $redirect_url = '';

                foreach ( $instances_with_clone as $instance ) {
                    if ( $should_switch_to_blog ) {
                        $instance->switch_to_blog( $blog_id );
                    }

                    $has_error = false;

                    if ( self::OPTION_NEW_HOME === $clone_action ) {
                        $instance->sync_install( array( 'is_new_site' => true ), true );

                        if ( $instance->is_clone() ) {
                            $has_error = true;
                        }
                    } else {
                        $instance->_handle_long_term_duplicate();

                        if ( ! is_object( $instance->get_site() ) ) {
                            $has_error = true;
                        }
                    }
                    
                    if ( $has_error && 1 === $instances_with_clone_count ) {
                        $redirect_url = $instance->get_activation_url();
                    }
                }

                $result = ( array( 'redirect_url' => $redirect_url ) );
            }
            
            foreach ( $instances_with_clone as $instance ) {
                if ( $should_switch_to_blog ) {
                    $instance->switch_to_blog( $blog_id );
                }

                // No longer a clone, send an update.
                if ( ! $instance->is_clone() ) {
                    $instance->send_clone_resolution_update(
                        $clone_action,
                        $install_by_instance_id[ $instance->get_id() ]
                    );
                }
            }

            if ( 'temporary_duplicate_license_activation' !== $clone_action ) {
                $this->remove_clone_resolution_options_notice();
            } else {
                $this->remove_temporary_duplicate_notice();
            }

            if ( $should_switch_to_blog ) {
                foreach ( $instances as $instance ) {
                    $instance->restore_current_blog();
                }
            }

            return $result;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         */
        private function hide_clone_admin_notices() {
            $this->remove_clone_resolution_options_notice( false );
            $this->remove_temporary_duplicate_notice( false );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         */
        function maybe_show_clone_admin_notice() {
            $this->_logger->entrance();

            if ( fs_is_network_admin() ) {
                $existing_notice_ids = $this->maybe_remove_notices();

                if ( ! empty( $existing_notice_ids ) ) {
                    fs_enqueue_local_style( 'fs_clone_resolution_notice', '/admin/clone-resolution.css' );
                }

                return;
            }

            $first_instance_with_clone = null;

            $site_urls                        = array();
            $sites_with_license_urls          = array();
            $sites_with_premium_version_count = 0;
            $product_ids                      = array();
            $product_titles                   = array();

            $instances = Freemius::_get_all_instances();

            foreach ( $instances as $instance ) {
                if ( ! $instance->is_registered()  ) {
                    continue;
                }

                if ( ! $instance->is_clone( true ) ) {
                    continue;
                }

                $install = $instance->get_site();

                $site_urls[]      = $install->url;
                $product_ids[]    = $instance->get_id();
                $product_titles[] = $instance->get_plugin_title();

                if ( is_null( $first_instance_with_clone ) ) {
                    $first_instance_with_clone = $instance;
                }

                if ( is_object( $instance->_get_license() ) ) {
                    $sites_with_license_urls[] = $install->url;
                }

                if ( $instance->is_premium() ) {
                    $sites_with_premium_version_count ++;
                }
            }

            if ( empty( $site_urls ) && empty( $sites_with_license_urls ) ) {
                $this->hide_clone_admin_notices();

                return;
            }

            $site_urls               = array_unique( $site_urls );
            $sites_with_license_urls = array_unique( $sites_with_license_urls );

            $module_label              = fs_text_inline( 'products', 'products' );
            $admin_notice_module_title = null;

            $has_temporary_duplicate_mode_expired = $this->has_temporary_duplicate_mode_expired();

            if (
                ! $this->was_temporary_duplicate_mode_selected() ||
                $has_temporary_duplicate_mode_expired
            ) {
                if ( ! empty( $site_urls ) ) {
                    fs_enqueue_local_style( 'fs_clone_resolution_notice', '/admin/clone-resolution.css' );

                    $doc_url = 'https://freemius.com/help/documentation/wordpress-sdk/safe-mode-clone-resolution-duplicate-website/';

                    if ( 1 === count( $instances ) ) {
                        $doc_url = fs_apply_filter(
                            $first_instance_with_clone->get_unique_affix(),
                            'clone_resolution_documentation_url',
                            $doc_url
                        );
                    }

                    $this->add_manual_clone_resolution_admin_notice(
                        $product_ids,
                        $product_titles,
                        $site_urls,
                        Freemius::get_unfiltered_site_url(),
                        ( count( $site_urls ) === count( $sites_with_license_urls ) ),
                        ( count( $site_urls ) === $sites_with_premium_version_count ),
                        $doc_url
                    );
                }

                return;
            }

            if ( empty( $sites_with_license_urls ) ) {
                return;
            }

            if ( ! $this->is_temporary_duplicate_notice_shown() ) {
                $last_time_temporary_duplicate_notice_shown  = $this->temporary_duplicate_notice_shown_timestamp;
                $was_temporary_duplicate_notice_shown_before = is_numeric( $last_time_temporary_duplicate_notice_shown );

                if ( $was_temporary_duplicate_notice_shown_before ) {
                    $temporary_duplicate_mode_expiration_timestamp = $this->get_temporary_duplicate_expiration_timestamp();
                    $current_time                                  = time();

                    if (
                        $current_time > $temporary_duplicate_mode_expiration_timestamp ||
                        $current_time < ( $temporary_duplicate_mode_expiration_timestamp - ( 2 * WP_FS__TIME_24_HOURS_IN_SEC ) )
                    ) {
                        // Do not show the notice if the temporary duplicate mode has already expired or it will expire more than 2 days from now.
                        return;
                    }
                }
            }

            if ( 1 === count( $sites_with_license_urls ) ) {
                $module_label              = $first_instance_with_clone->get_module_label( true );
                $admin_notice_module_title = $first_instance_with_clone->get_plugin_title();
            }

            fs_enqueue_local_style( 'fs_clone_resolution_notice', '/admin/clone-resolution.css' );

            $this->add_temporary_duplicate_sticky_notice(
                $product_ids,
                $this->get_temporary_duplicate_admin_notice_string( $sites_with_license_urls, $product_titles, $module_label ),
                $admin_notice_module_title
            );
        }

        /**
         * Removes the notices from the storage if the context product is either no longer active on the context subsite or it's active but there's no longer any clone. This prevents the notices from being shown on the network-level admin page when they are no longer relevant.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.5.1
         *
         * @return string[]
         */
        private function maybe_remove_notices() {
            $notices = array(
                'clone_resolution_options_notice' => $this->_notices->get_sticky( 'clone_resolution_options_notice', true ),
                'temporary_duplicate_notice'      => $this->_notices->get_sticky( 'temporary_duplicate_notice', true ),
            );

            $instances = Freemius::_get_all_instances();

            foreach ( $notices as $id => $notice ) {
                if ( ! is_array( $notice ) ) {
                    unset( $notices[ $id ] );
                    continue;
                }

                if ( empty( $notice['data'] ) || ! is_array( $notice['data'] ) ) {
                    continue;
                }

                if ( empty( $notice['data']['product_ids'] ) || empty( $notice['data']['blog_id'] ) ) {
                    continue;
                }

                $product_ids = $notice['data']['product_ids'];
                $blog_id     = $notice['data']['blog_id'];
                $has_clone   = false;

                if ( ! is_null( get_site( $blog_id ) ) ) {
                    foreach ( $product_ids as $product_id ) {
                        if ( ! isset( $instances[ 'm_' . $product_id ] ) ) {
                            continue;
                        }

                        $instance = $instances[ 'm_' . $product_id ];

                        $plugin_basename = $instance->get_plugin_basename();

                        $is_plugin_active = is_plugin_active_for_network( $plugin_basename );

                        if ( ! $is_plugin_active ) {
                            switch_to_blog( $blog_id );

                            $is_plugin_active = is_plugin_active( $plugin_basename );

                            restore_current_blog();
                        }

                        if ( ! $is_plugin_active ) {
                            continue;
                        }

                        $install  = $instance->get_install_by_blog_id( $blog_id );

                        if ( ! is_object( $install ) ) {
                            continue;
                        }

                        $subsite_url = Freemius::get_unfiltered_site_url( $blog_id, true, true );

                        $has_clone = ( fs_strip_url_protocol( trailingslashit( $install->url ) ) !== $subsite_url );
                    }
                }

                if ( ! $has_clone ) {
                    $this->_notices->remove_sticky( $id, true, false );
                    unset( $notices[ $id ] );
                }
            }

            return array_keys( $notices );
        }

        /**
         * Adds a notice that provides the logged-in WordPress user with manual clone resolution options.
         *
         * @param number[] $product_ids
         * @param string[] $site_urls
         * @param string   $current_url
         * @param bool     $has_license
         * @param bool     $is_premium
         * @param string   $doc_url
         */
        private function add_manual_clone_resolution_admin_notice(
            $product_ids,
            $product_titles,
            $site_urls,
            $current_url,
            $has_license,
            $is_premium,
            $doc_url
        ) {
            $this->_logger->entrance();

            $total_sites = count( $site_urls );
            $sites_list  = '';

            $total_products = count( $product_titles );
            $products_list  = '';

            if ( 1 === $total_products ) {
                $notice_header = sprintf(
                    '<div class="fs-notice-header"><p>%s</p></div>',
                    fs_esc_html_inline( '%1$s has been placed into safe mode because we noticed that %2$s is an exact copy of %3$s.', 'single-cloned-site-safe-mode-message' )
                );
            } else {
                $notice_header = sprintf(
                    '<div class="fs-notice-header"><p>%s</p></div>',
                    ( 1 === $total_sites ) ?
                        fs_esc_html_inline( 'The products below have been placed into safe mode because we noticed that %2$s is an exact copy of %3$s:%1$s', 'multiple-products-cloned-site-safe-mode-message' ) :
                        fs_esc_html_inline( 'The products below have been placed into safe mode because we noticed that %2$s is an exact copy of these sites:%3$s%1$s', 'multiple-products-multiple-cloned-sites-safe-mode-message' )
                );

                foreach ( $product_titles as $product_title ) {
                    $products_list .= sprintf( '<li>%s</li>', $product_title );
                }

                $products_list = '<ol>' . $products_list . '</ol>';

                foreach ( $site_urls as $site_url ) {
                    $sites_list .= sprintf(
                        '<li><a href="%s" target="_blank">%s</a></li>',
                        $site_url,
                        fs_strip_url_protocol( $site_url )
                    );
                }

                $sites_list = '<ol>' . $sites_list . '</ol>';
            }

            $remote_site_link = '<b>' . (1 === $total_sites ?
                sprintf(
                    '<a href="%s" target="_blank">%s</a>',
                    $site_urls[0],
                    fs_strip_url_protocol( $site_urls[0] )
                ) :
                fs_text_inline( 'the above-mentioned sites', 'above-mentioned-sites' )) . '</b>';

            $current_site_link = sprintf(
                '<b><a href="%s" target="_blank">%s</a></b>',
                $current_url,
                fs_strip_url_protocol( $current_url )
            );

            $button_template = '<button class="button" data-clone-action="%s">%s</button>';
            $option_template = '<div class="fs-clone-resolution-option"><strong>%s</strong><p>%s</p><div>%s</div></div>';

            $duplicate_option = sprintf(
                $option_template,
                fs_esc_html_inline( 'Is %2$s a duplicate of %4$s?', 'duplicate-site-confirmation-message' ),
                fs_esc_html_inline( 'Yes, %2$s is a duplicate of %4$s for the purpose of testing, staging, or development.', 'duplicate-site-message' ),
                ( $this->has_temporary_duplicate_mode_expired() ?
                    sprintf(
                        $button_template,
                        'long_term_duplicate',
                        fs_text_inline( 'Long-Term Duplicate', 'long-term-duplicate' )
                    ) :
                    sprintf(
                        $button_template,
                        'temporary_duplicate',
                        fs_text_inline( 'Duplicate Website', 'duplicate-site' )
                    ) )
            );

            $migration_option = sprintf(
                $option_template,
                fs_esc_html_inline( 'Is %2$s the new home of %4$s?', 'migrate-site-confirmation-message' ),
                sprintf(
                    fs_esc_html_inline( 'Yes, %%2$s is replacing %%4$s. I would like to migrate my %s from %%4$s to %%2$s.', 'migrate-site-message' ),
                    ( $has_license ? fs_text_inline( 'license', 'license' ) : fs_text_inline( 'data', 'data' ) )
                ),
                sprintf(
                    $button_template,
                    'new_home',
                    $has_license ?
                        fs_text_inline( 'Migrate License', 'migrate-product-license' ) :
                        fs_text_inline( 'Migrate', 'migrate-product-data' )
                )
            );

            $new_website = sprintf(
                $option_template,
                fs_esc_html_inline( 'Is %2$s a new website?', 'new-site-confirmation-message' ),
                fs_esc_html_inline( 'Yes, %2$s is a new and different website that is separate from %4$s.', 'new-site-message' ) .
                ($is_premium ?
                    ' ' . fs_text_inline( 'It requires license activation.', 'new-site-requires-license-activation-message' ) :
                    ''
                ),
                sprintf(
                    $button_template,
                    'new_website',
                    ( ! $is_premium || ! $has_license ) ?
                        fs_text_inline( 'New Website', 'new-website' ) :
                        fs_text_inline( 'Activate License', 'activate-license' )
                )
            );

            $blog_id = get_current_blog_id();

            /**
             * %1$s - single product's title or product titles list.
             * %2$s - site's URL.
             * %3$s - single install's URL or install URLs list.
             * %4$s - Clone site's link or "the above-mentioned sites" if there are multiple clone sites.
             */
            $message = sprintf(
                $notice_header .
                '<div class="fs-clone-resolution-options-container" data-ajax-url="' . esc_attr( admin_url( 'admin-ajax.php?_fs_network_admin=false', 'relative' ) ) . '" data-blog-id="' . $blog_id . '">' .
                $duplicate_option .
                $migration_option .
                $new_website . '</div>' .
                sprintf( '<div class="fs-clone-documentation-container">Unsure what to do? <a href="%s" target="_blank">Read more here</a>.</div>', $doc_url ),
                // %1$s
                ( 1 === $total_products ?
                    sprintf( '<b>%s</b>', $product_titles[0] ) :
                    ( 1 === $total_sites ?
                        sprintf( '<div>%s</div>', $products_list ) :
                        sprintf( '<div><p><strong>%s</strong>:</p>%s</div>', fs_esc_html_x_inline( 'Products', 'Clone resolution admin notice products list label', 'products' ), $products_list ) )
                ),
                // %2$s
                $current_site_link,
                // %3$s
                ( 1 === $total_sites ?
                    $remote_site_link :
                    $sites_list ),
                // %4$s
                $remote_site_link
            );

            $this->_notices->add_sticky(
                $message,
                'clone_resolution_options_notice',
                '',
                'warn',
                true,
                null,
                null,
                true,
                // Intentionally not dismissible.
                false,
                array(
                    'product_ids' => $product_ids,
                    'blog_id'     => $blog_id
                )
            );
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Temporary Duplicate (Short Term)
        #--------------------------------------------------------------------------------

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         *
         * @return string
         */
        private function get_temporary_duplicate_admin_notice_string(
            $site_urls,
            $product_titles,
            $module_label
        ) {
            $this->_logger->entrance();

            $temporary_duplicate_end_date = $this->get_temporary_duplicate_expiration_timestamp();
            $temporary_duplicate_end_date = date( 'M j, Y', $temporary_duplicate_end_date );

            $current_url       = Freemius::get_unfiltered_site_url();
            $current_site_link = sprintf(
                '<b><a href="%s" target="_blank">%s</a></b>',
                $current_url,
                fs_strip_url_protocol( $current_url )
            );

            $total_sites = count( $site_urls );
            $sites_list  = '';

            $total_products = count( $product_titles );
            $products_list  = '';

            if ( $total_sites > 1 ) {
                foreach ( $site_urls as $site_url ) {
                    $sites_list .= sprintf(
                        '<li><a href="%s" target="_blank">%s</a></li>',
                        $site_url,
                        fs_strip_url_protocol( $site_url )
                    );
                }

                $sites_list = '<ol class="fs-sites-list">' . $sites_list . '</ol>';
            }

            if ( $total_products > 1 ) {
                foreach ( $product_titles as $product_title ) {
                    $products_list .= sprintf( '<li>%s</li>', $product_title );
                }

                $products_list = '<ol>' . $products_list . '</ol>';
            }

            return sprintf(
                sprintf(
                    '<div>%s</div>',
                    ( 1 === $total_sites ?
                        sprintf( '<p>%s</p>', fs_esc_html_inline( 'You marked this website, %s, as a temporary duplicate of %s.', 'temporary-duplicate-message' ) ) :
                        sprintf( '<p>%s:</p>', fs_esc_html_inline( 'You marked this website, %s, as a temporary duplicate of these sites', 'temporary-duplicate-of-sites-message' ) ) . '%s' )
                ) . '%s',
                $current_site_link,
                ( 1 === $total_sites ?
                    sprintf(
                        '<b><a href="%s" target="_blank">%s</a></b>',
                        $site_urls[0],
                        fs_strip_url_protocol( $site_urls[0] )
                    ) :
                    $sites_list ),
                sprintf(
                    '<div class="fs-clone-resolution-options-container fs-duplicate-site-options" data-ajax-url="%s" data-blog-id="' . get_current_blog_id() . '"><p>%s</p>%s<p>%s</p></div>',
                    esc_attr( admin_url( 'admin-ajax.php?_fs_network_admin=false', 'relative' ) ),
                    sprintf(
                        fs_esc_html_inline( "%s automatic security & feature updates and paid functionality will keep working without interruptions until %s (or when your license expires, whatever comes first).", 'duplicate-site-confirmation-message' ),
                        ( 1 === $total_products ?
                            sprintf(
                                fs_esc_html_x_inline( "The %s's", '"The <product_label>", e.g.: "The plugin"', 'the-product-x'),
                                "<strong>{$module_label}</strong>"
                            ) :
                            fs_esc_html_inline( "The following products'", 'the-following-products' ) ),
                        sprintf( '<strong>%s</strong>', $temporary_duplicate_end_date )
                    ),
                    ( 1 === $total_products ?
                        '' :
                        sprintf( '<div>%s</div>', $products_list )
                    ),
                    sprintf(
                        fs_esc_html_inline( 'If this is a long term duplicate, to keep automatic updates and paid functionality after %s, please %s.', 'duplicate-site-message' ),
                        sprintf( '<strong>%s</strong>', $temporary_duplicate_end_date),
                        sprintf( '<a href="#" id="fs_temporary_duplicate_license_activation_link" data-clone-action="temporary_duplicate_license_activation">%s</a>', fs_esc_html_inline( 'activate a license here', 'activate-license-here' ) )
                    )
                )
            );
        }

        /**
         * Determines if the temporary duplicate mode has already expired.
         *
         * @return bool
         */
        function has_temporary_duplicate_mode_expired() {
            $temporary_duplicate_mode_start_timestamp = $this->was_temporary_duplicate_mode_selected() ?
                $this->get_option( 'temporary_duplicate_mode_selection_timestamp', true ) :
                $this->get_clone_identification_timestamp();

            if ( ! is_numeric( $temporary_duplicate_mode_start_timestamp ) ) {
                return false;
            }

            return ( time() > ( $temporary_duplicate_mode_start_timestamp + self::TEMPORARY_DUPLICATE_PERIOD ) );
        }

        /**
         * Determines if the logged-in WordPress user manually selected the temporary duplicate mode for the site.
         *
         * @return bool
         */
        function was_temporary_duplicate_mode_selected() {
            return is_numeric( $this->temporary_duplicate_mode_selection_timestamp );
        }

        /**
         * Stores the time when the logged-in WordPress user selected the temporary duplicate mode for the site.
         */
        private function store_temporary_duplicate_timestamp() {
            $this->temporary_duplicate_mode_selection_timestamp = time();
        }

        /**
         * Removes the notice that is shown when the logged-in WordPress user has selected the temporary duplicate mode for the site.
         *
         * @param bool $store
         */
        function remove_clone_resolution_options_notice( $store = true ) {
            $this->_notices->remove_sticky( 'clone_resolution_options_notice', true, $store );
        }

        /**
         * Removes the notice that is shown when the logged-in WordPress user has selected the temporary duplicate mode for the site.
         *
         * @param bool $store
         */
        function remove_temporary_duplicate_notice( $store = true ) {
            $this->_notices->remove_sticky( 'temporary_duplicate_notice', true, $store );
        }

        /**
         * Determines if the manual clone resolution options notice is currently being shown.
         *
         * @return bool
         */
        function is_clone_resolution_options_notice_shown() {
            return $this->_notices->has_sticky( 'clone_resolution_options_notice', true );
        }

        /**
         * Determines if the temporary duplicate notice is currently being shown.
         *
         * @return bool
         */
        function is_temporary_duplicate_notice_shown() {
            return $this->_notices->has_sticky( 'temporary_duplicate_notice', true );
        }

        /**
         * Determines if a site was marked as a temporary duplicate and if it's still a temporary duplicate.
         *
         * @return bool
         */
        function is_temporary_duplicate_by_blog_id( $blog_id ) {
            $timestamp = $this->get_option( 'temporary_duplicate_mode_selection_timestamp', false, $blog_id );

            return (
                is_numeric( $timestamp ) &&
                time() < ( $timestamp + self::TEMPORARY_DUPLICATE_PERIOD )
            );
        }

        /**
         * Determines the last time the temporary duplicate notice was shown.
         *
         * @return int|null
         */
        function last_time_temporary_duplicate_notice_was_shown() {
            return $this->temporary_duplicate_notice_shown_timestamp;
        }

        /**
         * Clears the time that has been stored when the temporary duplicate notice was shown.
         */
        function clear_temporary_duplicate_notice_shown_timestamp() {
            unset( $this->temporary_duplicate_notice_shown_timestamp );
        }

        /**
         * Adds a temporary duplicate notice that provides the logged-in WordPress user with an option to activate a license for the site.
         *
         * @param number[]    $product_ids
         * @param string      $message
         * @param string|null $plugin_title
         */
        function add_temporary_duplicate_sticky_notice(
            $product_ids,
            $message,
            $plugin_title = null
        ) {
            $this->_logger->entrance();

            $this->_notices->add_sticky(
                $message,
                'temporary_duplicate_notice',
                '',
                'promotion',
                true,
                null,
                $plugin_title,
                true,
                true,
                array(
                    'product_ids' => $product_ids,
                    'blog_id'     => get_current_blog_id()
                )
            );

            $this->temporary_duplicate_notice_shown_timestamp = time();
        }

        #endregion

        /**
         * @author Leo Fajardo
         * @since 2.5.0
         *
         * @param string $key
         *
         * @return bool
         */
        private function should_use_network_storage( $key ) {
            return ( 'new_blog_install_map' === $key );
        }

        /**
         * @param string      $key
         * @param number|null $blog_id
         *
         * @return FS_Option_Manager
         */
        private function get_storage( $key, $blog_id = null ) {
            if ( is_numeric( $blog_id ) ){
                return FS_Option_Manager::get_manager( WP_FS___OPTION_PREFIX . self::OPTION_MANAGER_NAME, true, $blog_id );
            }

            return $this->should_use_network_storage( $key ) ?
                $this->_network_storage :
                $this->_storage;
        }

        /**
         * @param string      $name
         * @param bool        $flush
         * @param number|null $blog_id
         *
         * @return mixed
         */
        private function get_option( $name, $flush = false, $blog_id = null ) {
            return $this->get_storage( $name, $blog_id )->get_option( $name, null, $flush );
        }

        #--------------------------------------------------------------------------------
        #region Magic methods
        #--------------------------------------------------------------------------------

        /**
         * @param string     $name
         * @param int|string $value
         */
        function __set( $name, $value ) {
            $this->get_storage( $name )->set_option( $name, $value, true );
        }

        /**
         * @param string $name
         *
         * @return bool
         */
        function __isset( $name ) {
            return $this->get_storage( $name )->has_option( $name, true );
        }

        /**
         * @param string $name
         */
        function __unset( $name ) {
            $this->get_storage( $name )->unset_option( $name, true );
        }

        /**
         * @param string $name
         *
         * @return null|int|string
         */
        function __get( $name ) {
            return $this->get_option(
                $name,
                // Reload storage from DB when accessing request_handler_* options to avoid race conditions.
                fs_starts_with( $name, 'request_handler' )
            );
        }

        #endregion
    }
