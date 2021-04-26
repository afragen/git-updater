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
     * @property bool   $is_network_activation
     * @property int    $network_install_blog_id
     * @property object $sync_cron
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
         * @param bool          $flush
         */
        function store( $key, $value, $network_level_or_blog_id = null, $flush = true ) {
            if ( $this->should_use_network_storage( $key, $network_level_or_blog_id ) ) {
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
         *
         * @return mixed
         */
        function get( $key, $default = false, $network_level_or_blog_id = null ) {
            if ( $this->should_use_network_storage( $key, $network_level_or_blog_id ) ) {
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

                    /**
                     * Remove the option from site level storage.
                     *
                     * IMPORTANT:
                     *      The line below is intentionally commented since we want to preserve the option
                     *      on the site storage level for "downgrade compatibility". Basically, if the user
                     *      will downgrade to an older version of the plugin with the prev storage structure,
                     *      it will continue working.
                     *
                     * @todo After a few releases we can remove this.
                     */
//                    $this->_storage->remove($option, false);

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
                'affiliate_application_data'   => 0,
                'beta_data'                    => 0,
                'connectivity_test'            => 0,
                'handle_gdpr_admin_notice'     => 0,
                'has_trial_plan'               => 0,
                'install_sync_timestamp'       => 0,
                'install_sync_cron'            => 0,
                'is_anonymous_ms'              => 0,
                'is_network_activated'         => 0,
                'is_on'                        => 0,
                'is_plugin_new_install'        => 0,
                'network_install_blog_id'      => 0,
                'pending_sites_info'           => 0,
                'plugin_last_version'          => 0,
                'plugin_main_file'             => 0,
                'plugin_version'               => 0,
                'sdk_downgrade_mode'           => 0,
                'sdk_last_version'             => 0,
                'sdk_upgrade_mode'             => 0,
                'sdk_version'                  => 0,
                'sticky_optin_added_ms'        => 0,
                'subscriptions'                => 0,
                'sync_timestamp'               => 0,
                'sync_cron'                    => 0,
                'was_plugin_loaded'            => 0,
                'network_user_id'              => 0,
                'plugin_upgrade_mode'          => 0,
                'plugin_downgrade_mode'        => 0,
                'is_network_connected'         => 0,
                /**
                 * Special flag that is used when a super-admin upgrades to the new version of the SDK that
                 * supports network level integration, when the connection decision wasn't made for all of the
                 * sites in the network.
                 */
                'is_network_activation'        => 0,
                'license_migration'            => 0,

                // When network activated, then network level.
                'install_timestamp'            => 1,
                'prev_is_premium'              => 1,
                'require_license_activation'   => 1,

                // If not network activated OR delegated, then site level.
                'activation_timestamp'           => 2,
                'expired_license_notice_shown'   => 2,
                'is_whitelabeled'                => 2,
                'last_license_key'               => 2,
                'last_license_user_id'           => 2,
                'prev_user_id'                   => 2,
                'sticky_optin_added'             => 2,
                'uninstall_reason'               => 2,
                'is_pending_activation'          => 2,
                'pending_license_key'            => 2,
                'is_extensions_tracking_allowed' => 2,

                // Site level options.
                'is_anonymous'                 => 3,
            );
        }

        /**
         * This method will and should only be executed when is_multisite() is true.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string $key
         *
         * @return bool|mixed
         */
        private function is_multisite_option( $key ) {
            if ( ! isset( self::$_NETWORK_OPTIONS_MAP ) ) {
                self::load_network_options_map();
            }

            if ( ! isset( self::$_NETWORK_OPTIONS_MAP[ $key ] ) ) {
                // Option not found -> use site level storage.
                return false;
            }

            if ( 0 === self::$_NETWORK_OPTIONS_MAP[ $key ] ) {
                // Option found and set to always use the network level storage on a multisite.
                return true;
            }

            if ( 3 === self::$_NETWORK_OPTIONS_MAP[ $key ] ) {
                // Option found and set to always use the site level storage on a multisite.
                return false;
            }

            if ( ! $this->_is_network_active ) {
                return false;
            }

            if ( 1 === self::$_NETWORK_OPTIONS_MAP[ $key ] ) {
                // Network activated.
                return true;
            }

            if ( 2 === self::$_NETWORK_OPTIONS_MAP[ $key ] && ! $this->_is_delegated_connection ) {
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
         *
         * @return bool
         */
        private function should_use_network_storage( $key, $network_level_or_blog_id = null ) {
            if ( ! $this->_is_multisite ) {
                // Not a multisite environment.
                return false;
            }

            if ( is_numeric( $network_level_or_blog_id ) ) {
                // Explicitly asked to use a specified blog storage.
                return false;
            }

            if ( is_bool( $network_level_or_blog_id ) ) {
                // Explicitly specified whether should use the network or blog level storage.
                return $network_level_or_blog_id;
            }

            // Determine which storage to use based on the option.
            return $this->is_multisite_option( $key );
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