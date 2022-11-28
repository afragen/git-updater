<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2015, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       1.2.3
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * Class FS_Storage
     *
     * A wrapper class for handling network level and single site level storage.
     *
     * @property bool        $is_network_activation
     * @property int         $network_install_blog_id
     * @property bool|null   $is_extensions_tracking_allowed
     * @property bool|null   $is_diagnostic_tracking_allowed
     * @property object      $sync_cron
     */
    class FS_Storage {
        /**
         * @var FS_Storage[]
         */
        private static $_instances = array();
        /**
         * @var FS_Key_Value_Storage Site level storage.
         */
        private $_storage;

        /**
         * @var FS_Key_Value_Storage Network level storage.
         */
        private $_network_storage;

        /**
         * @var string
         */
        private $_module_type;

        /**
         * @var string
         */
        private $_module_slug;

        /**
         * @var int The ID of the blog that is associated with the current site level options.
         */
        private $_blog_id = 0;

        /**
         * @var bool
         */
        private $_is_multisite;

        /**
         * @var bool
         */
        private $_is_network_active = false;

        /**
         * @var bool
         */
        private $_is_delegated_connection = false;

        /**
         * @var array {
         * @key   string Option name.
         * @value int If 0 store on the network level. If 1, store on the network level only if module was network level activated. If 2, store on the network level only if network activated and NOT delegated the connection.
         * }
         */
        private static $_NETWORK_OPTIONS_MAP;

        const OPTION_LEVEL_UNDEFINED                       = -1;
        // The option should be stored on the network level.
        const OPTION_LEVEL_NETWORK                         = 0;
        // The option should be stored on the network level when the plugin is network-activated.
        const OPTION_LEVEL_NETWORK_ACTIVATED               = 1;
        // The option should be stored on the network level when the plugin is network-activated and the opt-in connection was NOT delegated to the sub-site admin.
        const OPTION_LEVEL_NETWORK_ACTIVATED_NOT_DELEGATED = 2;
        // The option should be stored on the site level.
        const OPTION_LEVEL_SITE                            = 3;

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @param string $module_type
         * @param string $slug
         *
         * @return FS_Storage
         */
        static function instance( $module_type, $slug ) {
            $key = $module_type . ':' . $slug;

            if ( ! isset( self::$_instances[ $key ] ) ) {
                self::$_instances[ $key ] = new FS_Storage( $module_type, $slug );
            }

            return self::$_instances[ $key ];
        }

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @param string $module_type
         * @param string $slug
         */
        private function __construct( $module_type, $slug ) {
            $this->_module_type  = $module_type;
            $this->_module_slug  = $slug;
            $this->_is_multisite = is_multisite();

            if ( $this->_is_multisite ) {
                $this->_blog_id         = get_current_blog_id();
                $this->_network_storage = FS_Key_Value_Storage::instance( $module_type . '_data', $slug, true );
            }

            $this->_storage = FS_Key_Value_Storage::instance( $module_type . '_data', $slug, $this->_blog_id );
        }

        /**
         * Tells this storage wrapper class that the context plugin is network active. This flag will affect how values
         * are retrieved/stored from/into the storage.
         *
         * @author Leo Fajardo (@leorw)
         *
         * @param bool $is_network_active
         * @param bool $is_delegated_connection
         */
        function set_network_active( $is_network_active = true, $is_delegated_connection = false ) {
            $this->_is_network_active       = $is_network_active;
            $this->_is_delegated_connection = $is_delegated_connection;
        }

        /**
         * Switch the context of the site level storage manager.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int $blog_id
         */
        function set_site_blog_context( $blog_id ) {
            $this->_storage = $this->get_site_storage( $blog_id );
            $this->_blog_id = $blog_id;
        }

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @param string        $key
         * @param mixed         $value
         * @param null|bool|int $network_level_or_blog_id When an integer, use the given blog storage. When `true` use the multisite storage (if there's a network). When `false`, use the current context blog storage. When `null`, the decision which storage to use (MS vs. Current S) will be handled internally and determined based on the $option (based on self::$_BINARY_MAP).
         * @param int           $option_level Since 2.5.1
         * @param bool          $flush
         */
        function store(
            $key,
            $value,
            $network_level_or_blog_id = null,
            $option_level = self::OPTION_LEVEL_UNDEFINED,
            $flush = true
        ) {
            if ( $this->should_use_network_storage( $key, $network_level_or_blog_id, $option_level ) ) {
                $this->_network_storage->store( $key, $value, $flush );
            } else {
                $storage = $this->get_site_storage( $network_level_or_blog_id );
                $storage->store( $key, $value, $flush );
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @param bool          $store
         * @param string[]      $exceptions Set of keys to keep and not clear.
         * @param int|null|bool $network_level_or_blog_id
         */
        function clear_all( $store = true, $exceptions = array(), $network_level_or_blog_id = null ) {
            if ( ! $this->_is_multisite ||
                 false === $network_level_or_blog_id ||
                 is_null( $network_level_or_blog_id ) ||
                 is_numeric( $network_level_or_blog_id )
            ) {
                $storage = $this->get_site_storage( $network_level_or_blog_id );
                $storage->clear_all( $store, $exceptions );
            }

            if ( $this->_is_multisite &&
                 ( true === $network_level_or_blog_id || is_null( $network_level_or_blog_id ) )
            ) {
                $this->_network_storage->clear_all( $store, $exceptions );
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @param string        $key
         * @param bool          $store
         * @param null|bool|int $network_level_or_blog_id When an integer, use the given blog storage. When `true` use the multisite storage (if there's a network). When `false`, use the current context blog storage. When `null`, the decision which storage to use (MS vs. Current S) will be handled internally and determined based on the $option (based on self::$_BINARY_MAP).
         */
        function remove( $key, $store = true, $network_level_or_blog_id = null ) {
            if ( $this->should_use_network_storage( $key, $network_level_or_blog_id ) ) {
                $this->_network_storage->remove( $key, $store );
            } else {
                $storage = $this->get_site_storage( $network_level_or_blog_id );
                $storage->remove( $key, $store );
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @param string        $key
         * @param mixed         $default
         * @param null|bool|int $network_level_or_blog_id When an integer, use the given blog storage. When `true` use the multisite storage (if there's a network). When `false`, use the current context blog storage. When `null`, the decision which storage to use (MS vs. Current S) will be handled internally and determined based on the $option (based on self::$_BINARY_MAP).
         * @param int           $option_level Since 2.5.1
         *
         * @return mixed
         */
        function get(
            $key,
            $default = false,
            $network_level_or_blog_id = null,
            $option_level = self::OPTION_LEVEL_UNDEFINED
        ) {
            if ( $this->should_use_network_storage( $key, $network_level_or_blog_id, $option_level ) ) {
                return $this->_network_storage->get( $key, $default );
            } else {
                $storage = $this->get_site_storage( $network_level_or_blog_id );

                return $storage->get( $key, $default );
            }
        }

        /**
         * Multisite activated:
         *      true:    Save network storage.
         *      int:     Save site specific storage.
         *      false|0: Save current site storage.
         *      null:    Save network and current site storage.
         * Site level activated:
         *               Save site storage.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param bool|int|null $network_level_or_blog_id
         */
        function save( $network_level_or_blog_id = null ) {
            if ( $this->_is_network_active &&
                 ( true === $network_level_or_blog_id || is_null( $network_level_or_blog_id ) )
            ) {
                $this->_network_storage->save();
            }

            if ( ! $this->_is_network_active || true !== $network_level_or_blog_id ) {
                $storage = $this->get_site_storage( $network_level_or_blog_id );
                $storage->save();
            }
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return string
         */
        function get_module_slug() {
            return $this->_module_slug;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return string
         */
        function get_module_type() {
            return $this->_module_type;
        }

        /**
         * Migration script to the new storage data structure that is network compatible.
         *
         * IMPORTANT:
         *      This method should be executed only after it is determined if this is a network
         *      level compatible product activation.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         */
        function migrate_to_network() {
            if ( ! $this->_is_multisite ) {
                return;
            }

            $updated = false;

            if ( ! isset( self::$_NETWORK_OPTIONS_MAP ) ) {
                self::load_network_options_map();
            }

            foreach ( self::$_NETWORK_OPTIONS_MAP as $option => $storage_level ) {
                if ( ! $this->is_multisite_option( $option ) ) {
                    continue;
                }

                if ( isset( $this->_storage->{$option} ) && ! isset( $this->_network_storage->{$option} ) ) {
                    // Migrate option to the network storage.
                    $this->_network_storage->store( $option, $this->_storage->{$option}, false );

                    $updated = true;
                }
            }

            if ( ! $updated ) {
                return;
            }

            // Update network level storage.
            $this->_network_storage->save();
//            $this->_storage->save();
        }

        #--------------------------------------------------------------------------------
        #region Helper Methods
        #--------------------------------------------------------------------------------

        /**
         * We don't want to load the map right away since it's not even needed in a non-MS environment.
         *
         * Example:
         * array(
         *      'option1' => 0, // Means that the option should always be stored on the network level.
         *      'option2' => 1, // Means that the option should be stored on the network level only when the module was network level activated.
         *      'option2' => 2, // Means that the option should be stored on the network level only when the module was network level activated AND the connection was NOT delegated.
         *      'option3' => 3, // Means that the option should always be stored on the site level.
         * )
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         */
        private static function load_network_options_map() {
            self::$_NETWORK_OPTIONS_MAP = array(
                // Network level options.
                'affiliate_application_data'   => self::OPTION_LEVEL_NETWORK,
                'beta_data'                    => self::OPTION_LEVEL_NETWORK,
                'connectivity_test'            => self::OPTION_LEVEL_NETWORK,
                'handle_gdpr_admin_notice'     => self::OPTION_LEVEL_NETWORK,
                'has_trial_plan'               => self::OPTION_LEVEL_NETWORK,
                'install_sync_timestamp'       => self::OPTION_LEVEL_NETWORK,
                'install_sync_cron'            => self::OPTION_LEVEL_NETWORK,
                'is_anonymous_ms'              => self::OPTION_LEVEL_NETWORK,
                'is_network_activated'         => self::OPTION_LEVEL_NETWORK,
                'is_on'                        => self::OPTION_LEVEL_NETWORK,
                'is_plugin_new_install'        => self::OPTION_LEVEL_NETWORK,
                'network_install_blog_id'      => self::OPTION_LEVEL_NETWORK,
                'pending_sites_info'           => self::OPTION_LEVEL_NETWORK,
                'plugin_last_version'          => self::OPTION_LEVEL_NETWORK,
                'plugin_main_file'             => self::OPTION_LEVEL_NETWORK,
                'plugin_version'               => self::OPTION_LEVEL_NETWORK,
                'sdk_downgrade_mode'           => self::OPTION_LEVEL_NETWORK,
                'sdk_last_version'             => self::OPTION_LEVEL_NETWORK,
                'sdk_upgrade_mode'             => self::OPTION_LEVEL_NETWORK,
                'sdk_version'                  => self::OPTION_LEVEL_NETWORK,
                'sticky_optin_added_ms'        => self::OPTION_LEVEL_NETWORK,
                'subscriptions'                => self::OPTION_LEVEL_NETWORK,
                'sync_timestamp'               => self::OPTION_LEVEL_NETWORK,
                'sync_cron'                    => self::OPTION_LEVEL_NETWORK,
                'was_plugin_loaded'            => self::OPTION_LEVEL_NETWORK,
                'network_user_id'              => self::OPTION_LEVEL_NETWORK,
                'plugin_upgrade_mode'          => self::OPTION_LEVEL_NETWORK,
                'plugin_downgrade_mode'        => self::OPTION_LEVEL_NETWORK,
                'is_network_connected'         => self::OPTION_LEVEL_NETWORK,
                /**
                 * Special flag that is used when a super-admin upgrades to the new version of the SDK that supports network level integration, when the connection decision wasn't made for all the sites in the network.
                 */
                'is_network_activation'        => self::OPTION_LEVEL_NETWORK,
                'license_migration'            => self::OPTION_LEVEL_NETWORK,

                // When network activated, then network level.
                'install_timestamp'            => self::OPTION_LEVEL_NETWORK_ACTIVATED,
                'prev_is_premium'              => self::OPTION_LEVEL_NETWORK_ACTIVATED,
                'require_license_activation'   => self::OPTION_LEVEL_NETWORK_ACTIVATED,

                // If not network activated OR delegated, then site level.
                'activation_timestamp'         => self::OPTION_LEVEL_NETWORK_ACTIVATED_NOT_DELEGATED,
                'expired_license_notice_shown' => self::OPTION_LEVEL_NETWORK_ACTIVATED_NOT_DELEGATED,
                'is_whitelabeled'              => self::OPTION_LEVEL_NETWORK_ACTIVATED_NOT_DELEGATED,
                'last_license_key'             => self::OPTION_LEVEL_NETWORK_ACTIVATED_NOT_DELEGATED,
                'last_license_user_id'         => self::OPTION_LEVEL_NETWORK_ACTIVATED_NOT_DELEGATED,
                'prev_user_id'                 => self::OPTION_LEVEL_NETWORK_ACTIVATED_NOT_DELEGATED,
                'sticky_optin_added'           => self::OPTION_LEVEL_NETWORK_ACTIVATED_NOT_DELEGATED,
                'uninstall_reason'             => self::OPTION_LEVEL_NETWORK_ACTIVATED_NOT_DELEGATED,
                'is_pending_activation'        => self::OPTION_LEVEL_NETWORK_ACTIVATED_NOT_DELEGATED,
                'pending_license_key'          => self::OPTION_LEVEL_NETWORK_ACTIVATED_NOT_DELEGATED,

                // Site level options.
                'is_anonymous'                 => self::OPTION_LEVEL_SITE,
                'clone_id'                     => self::OPTION_LEVEL_SITE,
            );
        }

        /**
         * This method will and should only be executed when is_multisite() is true.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string $key
         * @param int    $option_level Since 2.5.1
         *
         * @return bool
         */
        private function is_multisite_option( $key, $option_level = self::OPTION_LEVEL_UNDEFINED ) {
            if ( ! isset( self::$_NETWORK_OPTIONS_MAP ) ) {
                self::load_network_options_map();
            }

            if (
                self::OPTION_LEVEL_UNDEFINED === $option_level &&
                isset( self::$_NETWORK_OPTIONS_MAP[ $key ] )
            ) {
                $option_level = self::$_NETWORK_OPTIONS_MAP[ $key ];
            }

            if ( self::OPTION_LEVEL_UNDEFINED === $option_level ) {
                // Option not found -> use site level storage.
                return false;
            }

            if ( self::OPTION_LEVEL_NETWORK === $option_level ) {
                // Option found and set to always use the network level storage on a multisite.
                return true;
            }

            if ( self::OPTION_LEVEL_SITE === $option_level ) {
                // Option found and set to always use the site level storage on a multisite.
                return false;
            }

            if ( ! $this->_is_network_active ) {
                return false;
            }

            if ( self::OPTION_LEVEL_NETWORK_ACTIVATED === $option_level ) {
                // Network activated.
                return true;
            }

            if (
                self::OPTION_LEVEL_NETWORK_ACTIVATED_NOT_DELEGATED === $option_level &&
                ! $this->_is_delegated_connection
            ) {
                // Network activated and not delegated.
                return true;
            }

            return false;
        }

        /**
         * @author Leo Fajardo
         *
         * @param string        $key
         * @param null|bool|int $network_level_or_blog_id When an integer, use the given blog storage. When `true` use the multisite storage (if there's a network). When `false`, use the current context blog storage. When `null`, the decision which storage to use (MS vs. Current S) will be handled internally and determined based on the $option (based on self::$_BINARY_MAP).
         * @param int           $option_level Since 2.5.1
         *
         * @return bool
         */
        private function should_use_network_storage(
            $key,
            $network_level_or_blog_id = null,
            $option_level = self::OPTION_LEVEL_UNDEFINED
        ) {
            if ( ! $this->_is_multisite ) {
                // Not a multisite environment.
                return false;
            }

            if ( is_numeric( $network_level_or_blog_id ) ) {
                // Explicitly asked to use a specified blog storage.
                return false;
            }

            if ( is_bool( $network_level_or_blog_id ) ) {
                // Explicitly specified whether it should use the network or blog level storage.
                return $network_level_or_blog_id;
            }

            // Determine which storage to use based on the option.
            return $this->is_multisite_option( $key, $option_level );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int $blog_id
         *
         * @return \FS_Key_Value_Storage
         */
        private function get_site_storage( $blog_id = 0 ) {
            if ( ! is_numeric( $blog_id ) ||
                 $blog_id == $this->_blog_id ||
                 0 == $blog_id
            ) {
                return $this->_storage;
            }

            return FS_Key_Value_Storage::instance(
                $this->_module_type . '_data',
                $this->_storage->get_secondary_id(),
                $blog_id
            );
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Magic methods
        #--------------------------------------------------------------------------------

        function __set( $k, $v ) {
            if ( $this->should_use_network_storage( $k ) ) {
                $this->_network_storage->{$k} = $v;
            } else {
                $this->_storage->{$k} = $v;
            }
        }

        function __isset( $k ) {
            return $this->should_use_network_storage( $k ) ?
                isset( $this->_network_storage->{$k} ) :
                isset( $this->_storage->{$k} );
        }

        function __unset( $k ) {
            if ( $this->should_use_network_storage( $k ) ) {
                unset( $this->_network_storage->{$k} );
            } else {
                unset( $this->_storage->{$k} );
            }
        }

        function __get( $k ) {
            return $this->should_use_network_storage( $k ) ?
                $this->_network_storage->{$k} :
                $this->_storage->{$k};
        }

        #endregion
    }
