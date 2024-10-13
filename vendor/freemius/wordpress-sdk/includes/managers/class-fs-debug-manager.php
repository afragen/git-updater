<?php
    /**
     * @author    Daniele Alessandra (@danielealessandra)
     * @copyright Copyright (c) 2024, Freemius, Inc.
     * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @package   Freemius
     * @since     2.6.2
     */

    class FS_DebugManager {

        /**
         * @author Vova Feldman (@svovaf)
         *  Moved from Freemius
         *
         * @since  1.0.8
         */
        static function _add_debug_section() {
            if ( ! is_super_admin() ) {
                // Add debug page only for super-admins.
                return;
            }

            Freemius::get_static_logger()->entrance();

            $title = sprintf( '%s [v.%s]', fs_text_inline( 'Freemius Debug' ), WP_FS__SDK_VERSION );

            if ( WP_FS__DEV_MODE ) {
                // Add top-level debug menu item.
                $hook = FS_Admin_Menu_Manager::add_page(
                    $title,
                    $title,
                    'manage_options',
                    'freemius',
                    array( self::class, '_debug_page_render' )
                );
            } else {
                // Add hidden debug page.
                $hook = FS_Admin_Menu_Manager::add_subpage(
                    '',
                    $title,
                    $title,
                    'manage_options',
                    'freemius',
                    array( self::class, '_debug_page_render' )
                );
            }

            if ( ! empty( $hook ) ) {
                add_action( "load-$hook", array( self::class, '_debug_page_actions' ) );
            }
        }

        /**
         * @author Vova Feldman (@svovaf)
         *  Moved from Freemius
         *
         * @since  1.0.8
         */
        static function _debug_page_actions() {
            Freemius::_clean_admin_content_section();

            if ( fs_request_is_action( 'restart_freemius' ) ) {
                check_admin_referer( 'restart_freemius' );

                if ( ! is_multisite() ) {
                    // Clear accounts data.
                    Freemius::get_accounts()->clear( null, true );
                } else {
                    $sites = Freemius::get_sites();
                    foreach ( $sites as $site ) {
                        $blog_id = Freemius::get_site_blog_id( $site );
                        Freemius::get_accounts()->clear( $blog_id, true );
                    }

                    // Clear network level storage.
                    Freemius::get_accounts()->clear( true, true );
                }

                // Clear SDK reference cache.
                delete_option( 'fs_active_plugins' );
            } else if ( fs_request_is_action( 'clear_updates_data' ) ) {
                check_admin_referer( 'clear_updates_data' );

                if ( ! is_multisite() ) {
                    set_site_transient( 'update_plugins', null );
                    set_site_transient( 'update_themes', null );
                } else {
                    $current_blog_id = get_current_blog_id();

                    $sites = Freemius::get_sites();
                    foreach ( $sites as $site ) {
                        switch_to_blog( Freemius::get_site_blog_id( $site ) );

                        set_site_transient( 'update_plugins', null );
                        set_site_transient( 'update_themes', null );
                    }

                    switch_to_blog( $current_blog_id );
                }
            } else if ( fs_request_is_action( 'reset_deactivation_snoozing' ) ) {
                check_admin_referer( 'reset_deactivation_snoozing' );

                Freemius::reset_deactivation_snoozing();
            } else if ( fs_request_is_action( 'simulate_trial' ) ) {
                check_admin_referer( 'simulate_trial' );

                $fs = freemius( fs_request_get( 'module_id' ) );

                // Update SDK install to at least 24 hours before.
                $fs->get_storage()->install_timestamp = ( time() - WP_FS__TIME_24_HOURS_IN_SEC );
                // Unset the trial shown timestamp.
                unset( $fs->get_storage()->trial_promotion_shown );
            } else if ( fs_request_is_action( 'simulate_network_upgrade' ) ) {
                check_admin_referer( 'simulate_network_upgrade' );

                $fs = freemius( fs_request_get( 'module_id' ) );

                Freemius::set_network_upgrade_mode( $fs->get_storage() );
            } else if ( fs_request_is_action( 'delete_install' ) ) {
                check_admin_referer( 'delete_install' );

                Freemius::_delete_site_by_slug(
                    fs_request_get( 'slug' ),
                    fs_request_get( 'module_type' ),
                    true,
                    fs_request_get( 'blog_id', null )
                );
            } else if ( fs_request_is_action( 'delete_user' ) ) {
                check_admin_referer( 'delete_user' );

                self::delete_user( fs_request_get( 'user_id' ) );
            } else if ( fs_request_is_action( 'download_logs' ) ) {
                check_admin_referer( 'download_logs' );

                $download_url = FS_Logger::download_db_logs(
                    fs_request_get( 'filters', false, 'post' )
                );

                if ( false === $download_url ) {
                    wp_die( 'Oops... there was an error while generating the logs download file. Please try again and if it doesn\'t work contact support@freemius.com.' );
                }

                fs_redirect( $download_url );
            } else if ( fs_request_is_action( 'migrate_options_to_network' ) ) {
                check_admin_referer( 'migrate_options_to_network' );

                Freemius::migrate_options_to_network();
            }
        }

        /**
         * @author Vova Feldman (@svovaf)
         *  Moved from Freemius
         *
         * @since  1.0.8
         */
        static function _debug_page_render() {
            Freemius::get_static_logger()->entrance();

            $all_modules_sites = self::get_all_modules_sites();

            $licenses_by_module_type = self::get_all_licenses_by_module_type();

            $vars = array(
                'plugin_sites'    => $all_modules_sites[ WP_FS__MODULE_TYPE_PLUGIN ],
                'theme_sites'     => $all_modules_sites[ WP_FS__MODULE_TYPE_THEME ],
                'users'           => Freemius::get_all_users(),
                'addons'          => Freemius::get_all_addons(),
                'account_addons'  => Freemius::get_all_account_addons(),
                'plugin_licenses' => $licenses_by_module_type[ WP_FS__MODULE_TYPE_PLUGIN ],
                'theme_licenses'  => $licenses_by_module_type[ WP_FS__MODULE_TYPE_THEME ],
            );

            fs_enqueue_local_style( 'fs_debug', '/admin/debug.css' );
            fs_require_once_template( 'debug.php', $vars );
        }

        /**
         * @author Vova Feldman (@svovaf)
         *  Moved from Freemius
         *
         * @since  1.2.1.6
         */
        static function _get_debug_log() {
            check_admin_referer( 'fs_get_debug_log' );

            if ( ! is_super_admin() ) {
                return;
            }

            if (!FS_Logger::is_storage_logging_on()) {
                return;
            }

            $limit  = min( ! empty( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 200, 200 );
            $offset = min( ! empty( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 200, 200 );

            $logs = FS_Logger::load_db_logs(
                fs_request_get( 'filters', false, 'post' ),
                $limit,
                $offset
            );

            Freemius::shoot_ajax_success( $logs );
        }

        /**
         * @author Vova Feldman (@svovaf)
         *  Moved from Freemius
         *
         * @since  1.2.1.7
         */
        static function _get_db_option() {
            check_admin_referer( 'fs_get_db_option' );

            $option_name = fs_request_get( 'option_name' );

            if ( ! is_super_admin() ||
                 ! fs_starts_with( $option_name, 'fs_' )
            ) {
                Freemius::shoot_ajax_failure();
            }

            $value = get_option( $option_name );

            $result = array(
                'name' => $option_name,
            );

            if ( false !== $value ) {
                if ( ! is_string( $value ) ) {
                    $value = json_encode( $value );
                }

                $result['value'] = $value;
            }

            Freemius::shoot_ajax_success( $result );
        }

        /**
         * @author Vova Feldman (@svovaf)
         *  Moved from Freemius
         *
         * @since  1.2.1.7
         */
        static function _set_db_option() {
            check_admin_referer( 'fs_set_db_option' );

            $option_name = fs_request_get( 'option_name' );

            if ( ! is_super_admin() ||
                 ! fs_starts_with( $option_name, 'fs_' )
            ) {
                Freemius::shoot_ajax_failure();
            }

            $option_value = fs_request_get_raw( 'option_value' );

            if ( ! empty( $option_value ) ) {
                update_option( $option_name, $option_value );
            }

            Freemius::shoot_ajax_success();
        }

        /**
         * @author Vova Feldman (@svovaf)
         *  Moved from Freemius
         *
         * @since  1.1.7.3
         */
        static function _toggle_debug_mode() {
            check_admin_referer( 'fs_toggle_debug_mode' );

            if ( ! is_super_admin() ) {
                return;
            }

            $is_on = fs_request_get( 'is_on', false, 'post' );

            if ( fs_request_is_post() && in_array( $is_on, array( 0, 1 ) ) ) {
                if ( $is_on ) {
                    self::_turn_on_debug_mode();
                } else {
                    self::_turn_off_debug_mode();
                }

                // Logic to turn debugging off automatically
                if ( 1 == $is_on ) {
                    // Plan a single event triggering after 24 hours to turn debugging off.
                    wp_schedule_single_event( time() + 24 * HOUR_IN_SECONDS, 'fs_debug_turn_off_logging_hook' );
                } else {
                    // Cancels any planned event when debugging is turned off manually.
                    $timestamp = wp_next_scheduled( 'fs_debug_turn_off_logging_hook' );
                    if ( $timestamp ) {
                        wp_unschedule_event( $timestamp, 'fs_debug_turn_off_logging_hook' );
                    }
                }
            }

            exit;
        }

        /**
         * @author Daniele Alessandra (@danielealessandra)
         * @since  2.6.2
         *
         */
        static function _turn_off_debug_mode() {
            self::update_debug_mode_option( 0 );
            FS_Logger::_set_storage_logging( false );
        }

        /**
         * @author Daniele Alessandra (@danielealessandra)
         * @since  2.6.2
         *
         */
        static function _turn_on_debug_mode() {
            self::update_debug_mode_option( 1 );
            FS_Logger::_set_storage_logging();
        }

        /**
         * @author Leo Fajardo (@leorw)
         *  Moved from Freemius
         *
         * @param string $url
         * @param array  $request
         *
         * @since  2.1.0
         *
         */
        public static function enrich_request_for_debug( &$url, &$request ) {
            if ( WP_FS__DEBUG_SDK || isset( $_COOKIE['XDEBUG_SESSION'] ) ) {
                $url = add_query_arg( 'XDEBUG_SESSION_START', rand( 0, 9999999 ), $url );
                $url = add_query_arg( 'XDEBUG_SESSION', 'PHPSTORM', $url );

                $request['cookies'] = array(
                    new WP_Http_Cookie( array(
                        'name'  => 'XDEBUG_SESSION',
                        'value' => 'PHPSTORM',
                    ) ),
                );
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         *  Moved from Freemius
         *
         * @return array
         *
         * @since  2.0.0
         *
         */
        private static function get_all_licenses_by_module_type() {
            $licenses = Freemius::get_account_option( 'all_licenses' );

            $licenses_by_module_type = array(
                WP_FS__MODULE_TYPE_PLUGIN => array(),
                WP_FS__MODULE_TYPE_THEME  => array(),
            );

            if ( ! is_array( $licenses ) ) {
                return $licenses_by_module_type;
            }

            foreach ( $licenses as $module_id => $module_licenses ) {
                $fs = Freemius::get_instance_by_id( $module_id );
                if ( false === $fs ) {
                    continue;
                }

                $licenses_by_module_type[ $fs->get_module_type() ] = array_merge( $licenses_by_module_type[ $fs->get_module_type() ],
                    $module_licenses );
            }

            return $licenses_by_module_type;
        }

        /**
         * Moved from the Freemius class.
         *
         * @author Leo Fajardo (@leorw)
         *
         * @return array
         *
         * @since  2.5.0
         */
        static function get_all_modules_sites() {
            Freemius::get_static_logger()->entrance();

            $sites_by_type = array(
                WP_FS__MODULE_TYPE_PLUGIN => array(),
                WP_FS__MODULE_TYPE_THEME  => array(),
            );

            $module_types = array_keys( $sites_by_type );

            if ( ! is_multisite() ) {
                foreach ( $module_types as $type ) {
                    $sites_by_type[ $type ] = Freemius::get_all_sites( $type );

                    foreach ( $sites_by_type[ $type ] as $slug => $install ) {
                        $sites_by_type[ $type ][ $slug ] = array( $install );
                    }
                }
            } else {
                $sites = Freemius::get_sites();

                foreach ( $sites as $site ) {
                    $blog_id = Freemius::get_site_blog_id( $site );

                    foreach ( $module_types as $type ) {
                        $installs = Freemius::get_all_sites( $type, $blog_id );

                        foreach ( $installs as $slug => $install ) {
                            if ( ! isset( $sites_by_type[ $type ][ $slug ] ) ) {
                                $sites_by_type[ $type ][ $slug ] = array();
                            }

                            $install->blog_id = $blog_id;

                            $sites_by_type[ $type ][ $slug ][] = $install;
                        }
                    }
                }
            }

            return $sites_by_type;
        }

        /**
         * Delete user.
         *
         * @author Vova Feldman (@svovaf)
         *
         * @param number $user_id
         * @param bool   $store
         *
         * @return false|int The user ID if deleted. Otherwise, FALSE (when install not exist).
         * @since  2.0.0
         *
         */
        public static function delete_user( $user_id, $store = true ) {
            $users = Freemius::get_all_users();

            if ( ! is_array( $users ) || ! isset( $users[ $user_id ] ) ) {
                return false;
            }

            unset( $users[ $user_id ] );

            self::$_accounts->set_option( 'users', $users, $store );

            return $user_id;
        }

        /**
         * @author Daniele Alessandra (@danielealessandra)
         *
         * @return void
         * @since  2.6.2
         *
         */
        public static function load_required_static() {
            if ( ! WP_FS__DEMO_MODE ) {
                add_action( ( fs_is_network_admin() ? 'network_' : '' ) . 'admin_menu', array(
                    self::class,
                    '_add_debug_section',
                ) );
            }

            add_action( "wp_ajax_fs_toggle_debug_mode", array( self::class, '_toggle_debug_mode' ) );

            Freemius::add_ajax_action_static( 'get_debug_log', array( self::class, '_get_debug_log' ) );
            Freemius::add_ajax_action_static( 'get_db_option', array( self::class, '_get_db_option' ) );
            Freemius::add_ajax_action_static( 'set_db_option', array( self::class, '_set_db_option' ) );
        }

        /**
         * @author Daniele Alessandra (@danielealessandra)
         *
         * @return void
         *
         * @since  2.6.2
         */
        public static function register_hooks() {
            add_action( 'fs_debug_turn_off_logging_hook', array( self::class, '_turn_off_debug_mode' ) );
        }

        /**
         * @author Daniele Alessandra (@danielealessandra)
         *
         * @param int $is_on
         *
         * @return void
         *
         * @since  2.6.2
         */
        private static function update_debug_mode_option( $is_on ) {
            update_option( 'fs_debug_mode', $is_on );
        }

    }
