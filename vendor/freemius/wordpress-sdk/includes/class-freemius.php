<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2015, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       1.0.3
     */
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    // "final class"
    class Freemius extends Freemius_Abstract {
        /**
         * SDK Version
         *
         * @var string
         */
        public $version = WP_FS__SDK_VERSION;

        #region Plugin Info

        /**
         * @since 1.0.1
         *
         * @var string
         */
        private $_slug;

        /**
         * @since 1.0.0
         *
         * @var string
         */
        private $_plugin_basename;
        /**
         * @since 2.2.1
         *
         * @var string
         */
        private $_premium_plugin_basename;
        /**
         * @since 1.0.0
         *
         * @var string
         */
        private $_free_plugin_basename;
        /**
         * @since 1.0.0
         *
         * @var string
         */
        private $_plugin_dir_path;
        /**
         * @since 1.0.0
         *
         * @var string
         */
        private $_plugin_dir_name;
        /**
         * @since 1.0.0
         *
         * @var string
         */
        private $_plugin_main_file_path;
        /**
         * @var string[]
         */
        private $_plugin_data;
        /**
         * @since 1.0.9
         *
         * @var string
         */
        private $_plugin_name;
        /**
         * @since 1.2.2
         *
         * @var string
         */
        private $_module_type;

        #endregion Plugin Info

        /**
         * @since 1.0.9
         *
         * @var bool If false, don't turn Freemius on.
         */
        private $_is_on;

        /**
         * @since 1.1.3
         *
         * @var bool If false, don't turn Freemius on.
         */
        private $_is_anonymous;

        /**
         * @since 1.0.9
         * @var bool If false, issues with connectivity to Freemius API.
         */
        private $_has_api_connection;

        /**
         * @since 1.0.9
         * @since 2.0.0 Default to true since we need the property during the instance construction, prior to the dynamic_init() execution.
         * @var bool Hints the SDK if plugin can support anonymous mode (if skip connect is visible).
         */
        private $_enable_anonymous = true;

        /**
         * @since 1.1.7.5
         * @var bool Hints the SDK if plugin should run in anonymous mode (only adds feedback form).
         */
        private $_anonymous_mode;

        /**
         * @since 1.1.9
         * @var bool Hints the SDK if plugin have any free plans.
         */
        private $_is_premium_only;

        /**
         * @since 1.2.1.6
         * @var bool Hints the SDK if plugin have premium code version at all.
         */
        private $_has_premium_version;

        /**
         * @since 1.2.1.6
         * @var bool Hints the SDK if plugin should ignore pending mode by simulating a skip.
         */
        private $_ignore_pending_mode;

        /**
         * @since 1.0.8
         * @var bool Hints the SDK if the plugin has any paid plans.
         */
        private $_has_paid_plans;

        /**
         * @since 1.2.1.5
         * @var int Hints the SDK if the plugin offers a trial period. If negative, no trial, if zero - has a trial but
         *      without a specified period, if positive - the number of trial days.
         */
        private $_trial_days = - 1;

        /**
         * @since 1.2.1.5
         * @var bool Hints the SDK if the trial requires a payment method or not.
         */
        private $_is_trial_require_payment = false;

        /**
         * @since 1.0.7
         * @var bool Hints the SDK if the plugin is WordPress.org compliant.
         */
        private $_is_org_compliant;

        /**
         * @since 1.0.7
         * @var bool Hints the SDK if the plugin is has add-ons.
         */
        private $_has_addons;

        /**
         * @since 2.4.5
         * @var string Navigation type: 'menu' or 'tabs'.
         */
        private $_navigation;

        const NAVIGATION_MENU = 'menu';
        const NAVIGATION_TABS = 'tabs';

        /**
         * @since 1.1.6
         * @var string[]bool.
         */
        private $_permissions;

        /**
         * @var FS_Storage
         */
        private $_storage;

        /**
         * @since 1.2.2.7
         * @var FS_Cache_Manager
         */
        private $_cache;

        /**
         * @since 1.0.0
         *
         * @var FS_Logger
         */
        private $_logger;
        /**
         * @since 1.0.4
         *
         * @var FS_Plugin
         */
        private $_plugin = false;
        /**
         * @since 1.0.4
         *
         * @var FS_Plugin|false
         */
        private $_parent_plugin = false;
        /**
         * @since 1.1.1
         *
         * @var Freemius
         */
        private $_parent = false;
        /**
         * @since 1.0.1
         *
         * @var FS_User
         */
        private $_user = false;
        /**
         * @since 1.0.1
         *
         * @var FS_Site
         */
        private $_site = false;
        /**
         * @since 1.0.1
         *
         * @var FS_Plugin_License
         */
        private $_license;
        /**
         * @since 1.0.2
         *
         * @var FS_Plugin_Plan[]
         */
        private $_plans = false;
        /**
         * @var FS_Plugin_License[]
         * @since 1.0.5
         */
        private $_licenses = false;

        /**
         * @since 1.0.1
         *
         * @var FS_Admin_Menu_Manager
         */
        private $_menu;

        /**
         * @var FS_Admin_Notices
         */
        private $_admin_notices;

        /**
         * @since 1.1.6
         *
         * @var FS_Admin_Notices
         */
        private static $_global_admin_notices;

        /**
         * @var FS_Logger
         * @since 1.0.0
         */
        private static $_static_logger;

        /**
         * @var FS_Options
         * @since 1.0.2
         */
        private static $_accounts;

        /**
         * @since 1.2.2
         *
         * @var number
         */
        private $_module_id;

        /**
         * @var Freemius[]
         */
        private static $_instances = array();

        /**
         * @since  1.2.3
         *
         * @var FS_Affiliate
         */
        private $affiliate = null;

        /**
         * @since  1.2.3
         *
         * @var FS_AffiliateTerms
         */
        private $plugin_affiliate_terms = null;

        /**
         * @since  1.2.3
         *
         * @var FS_AffiliateTerms
         */
        private $custom_affiliate_terms = null;

        /**
         * @since  2.0.0
         *
         * @var bool
         */
        private $_is_multisite_integrated;

        /**
         * @since  2.0.0
         *
         * @var bool True if the current request is for a network admin screen and the plugin is network active.
         */
        private $_is_network_active;

        /**
         * @since  2.0.0
         *
         * @var int|null The original blog ID the plugin was loaded with.
         */
        private $_blog_id = null;

        /**
         * @since  2.0.0
         *
         * @var int|null The current execution context. When true, run on network context. When int, run on the specified blog context.
         */
        private $_context_is_network_or_blog_id = null;

        /**
         * @since  2.0.0
         *
         * @var string
         */
        private $_dynamically_added_top_level_page_hook_name = '';

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.1
         *
         * @var bool
         */
        private $is_whitelabeled;

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.4.0
         *
         * @var bool
         */
        private $_is_bundle_license_auto_activation_enabled = false;

        #region Uninstall Reasons IDs

        const REASON_NO_LONGER_NEEDED = 1;
        const REASON_FOUND_A_BETTER_PLUGIN = 2;
        const REASON_NEEDED_FOR_A_SHORT_PERIOD = 3;
        const REASON_BROKE_MY_SITE = 4;
        const REASON_SUDDENLY_STOPPED_WORKING = 5;
        const REASON_CANT_PAY_ANYMORE = 6;
        const REASON_OTHER = 7;
        const REASON_DIDNT_WORK = 8;
        const REASON_DONT_LIKE_TO_SHARE_MY_INFORMATION = 9;
        const REASON_COULDNT_MAKE_IT_WORK = 10;
        const REASON_GREAT_BUT_NEED_SPECIFIC_FEATURE = 11;
        const REASON_NOT_WORKING = 12;
        const REASON_NOT_WHAT_I_WAS_LOOKING_FOR = 13;
        const REASON_DIDNT_WORK_AS_EXPECTED = 14;
        const REASON_TEMPORARY_DEACTIVATION = 15;

        #endregion

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.4.2
         *
         * @var string|null
         */
        private $_pricing_js_path = null;

        const VERSION_MAX_CHARS = 16;
        const LANGUAGE_MAX_CHARS = 8;

        /* Ctor
------------------------------------------------------------------------------------------------------------------*/

        /**
         * Main singleton instance.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.0
         *
         * @param number      $module_id
         * @param string|bool $slug
         * @param bool        $is_init Since 1.2.1 Is initiation sequence.
         */
        private function __construct( $module_id, $slug = false, $is_init = false ) {
            $main_file = false;

            if ( $is_init && is_numeric( $module_id ) && is_string( $slug ) ) {
                $main_file = $this->store_id_slug_type_path_map( $module_id, $slug );
            }

            $this->_module_id   = $module_id;
            $this->_slug        = $this->get_slug();
            $this->_module_type = $this->get_module_type();

            $this->_blog_id = is_multisite() ? get_current_blog_id() : null;

            $this->_storage = FS_Storage::instance( $this->_module_type, $this->_slug );

            // If not set or 24 hours have already passed from the last time it's set, set the last load timestamp to the current time.
            if (
                ! isset( $this->_storage->last_load_timestamp ) ||
                $this->_storage->last_load_timestamp < ( time() - ( WP_FS__TIME_24_HOURS_IN_SEC ) )
            ) {
                $this->_storage->last_load_timestamp = time();
            }

            $this->_cache = FS_Cache_Manager::get_manager( WP_FS___OPTION_PREFIX . "cache_{$module_id}" );

            $this->_logger = FS_Logger::get_logger( WP_FS__SLUG . '_' . $this->get_unique_affix(), WP_FS__DEBUG_SDK, WP_FS__ECHO_DEBUG_SDK );

            $this->_plugin_main_file_path = $this->_find_caller_plugin_file( $is_init, $main_file );
            $this->_plugin_dir_path       = plugin_dir_path( $this->_plugin_main_file_path );
            $this->_plugin_basename       = $this->get_plugin_basename();
            $this->_free_plugin_basename  = str_replace( '-premium/', '/', $this->_plugin_basename );

            $this->_is_multisite_integrated = (
                defined( "WP_FS__PRODUCT_{$module_id}_MULTISITE" ) &&
                ( true === constant( "WP_FS__PRODUCT_{$module_id}_MULTISITE" ) )
            );

            $this->_is_network_active = (
                is_multisite() &&
                $this->_is_multisite_integrated &&
                // Themes are always network activated, but the ACTUAL activation is per site.
                $this->is_plugin() &&
                (
                    is_plugin_active_for_network( $this->_plugin_basename ) ||
                    // Plugin network level activation or uninstall.
                    ( fs_is_network_admin() && is_plugin_inactive( $this->_plugin_basename ) )
                )
            );

            $this->_storage->set_network_active(
                $this->_is_network_active,
                $this->is_delegated_connection()
            );

            if ( ! isset( $this->_storage->is_network_activated ) ) {
                $this->_storage->is_network_activated = $this->_is_network_active;
            }

            if ( $this->_storage->is_network_activated != $this->_is_network_active ) {
                // Update last activation level.
                $this->_storage->is_network_activated = $this->_is_network_active;

                $this->maybe_adjust_storage();
            }

            #region Migration

            if ( is_multisite() ) {
                /**
                 * If the install_timestamp exists on the site level but doesn't exist on the
                 * network level storage, it means that we need to process the storage with migration.
                 *
                 * The code in this `if` scope will only be executed once and only for the first site that will execute it because once we migrate the storage data, install_timestamp will be already set in the network level storage.
                 *
                 * @author Vova Feldman (@svovaf)
                 * @since  2.0.0
                 */
                if ( false === $this->_storage->get( 'install_timestamp', false, true ) &&
                     false !== $this->_storage->get( 'install_timestamp', false, false )
                ) {
                    // Initiate storage migration.
                    $this->_storage->migrate_to_network();

                    // Migrate module cache to network level storage.
                    $this->_cache->migrate_to_network();
                }
            }

            #endregion

            $base_name_split        = explode( '/', $this->_plugin_basename );
            $this->_plugin_dir_name = $base_name_split[0];

            if ( $this->_logger->is_on() ) {
                $this->_logger->info( 'plugin_main_file_path = ' . $this->_plugin_main_file_path );
                $this->_logger->info( 'plugin_dir_path = ' . $this->_plugin_dir_path );
                $this->_logger->info( 'plugin_basename = ' . $this->_plugin_basename );
                $this->_logger->info( 'free_plugin_basename = ' . $this->_free_plugin_basename );
                $this->_logger->info( 'plugin_dir_name = ' . $this->_plugin_dir_name );
            }

            // Remember link between file to slug.
            $this->store_file_slug_map();

            // Store plugin's initial install timestamp.
            if ( ! isset( $this->_storage->install_timestamp ) ) {
                $this->_storage->install_timestamp = WP_FS__SCRIPT_START_TIME;
            }

            if ( ! is_object( $this->_plugin ) ) {
                $this->_plugin = FS_Plugin_Manager::instance( $this->_module_id )->get();
            }

            $this->_admin_notices = FS_Admin_Notices::instance(
                $this->_slug . ( $this->is_theme() ? ':theme' : '' ),
                /**
                 * Ensure that the admin notice will always have a title by using the stored plugin title if available and
                 * retrieving the title via the "get_plugin_name" method if there is no stored plugin title available.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since  1.2.2
                 */
                ( is_object( $this->_plugin ) && isset( $this->_plugin->title ) ?
                    $this->_plugin->title :
                    $this->get_plugin_name()
                ),
                $this->get_unique_affix()
            );

            if ( 'true' === fs_request_get( 'fs_clear_api_cache' ) ||
                 fs_request_is_action( 'restart_freemius' )
            ) {
                FS_Api::clear_cache();
                $this->_cache->clear();
            }

            $this->register_constructor_hooks();

            /**
             * Starting from version 2.0.0, `FS_Site` entities no longer have the `plan` property and have `plan_id`
             * instead. This should be called before calling `_load_account()`, otherwise, `$this->_site` will not be
             * loaded in `_load_account` for versions of SDK starting from 2.0.0.
             *
             * @author Leo Fajardo (@leorw)
             */
            self::migrate_install_plan_to_plan_id( $this->_storage );

            $this->_load_account();

            $this->_version_updates_handler();
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         */
        private function maybe_adjust_storage() {
            $install_timestamp = null;
            $prev_is_premium   = null;

            $options_to_update = array();

            $is_network_admin = fs_is_network_admin();

            $network_install_timestamp = $this->_storage->get( 'install_timestamp', null, true );

            if ( ! $is_network_admin ) {
                if ( is_null( $network_install_timestamp ) ) {
                    // Plugin was not network-activated before.
                    return;
                }

                if ( is_null( $this->_storage->get( 'install_timestamp', null, false ) ) ) {
                    // Set the `install_timestamp` only if it's not yet set.
                    $install_timestamp = $network_install_timestamp;
                }

                $prev_is_premium = $this->_storage->get( 'prev_is_premium', null, true );
            } else {
                $current_wp_user   = self::_get_current_wp_user();
                $current_fs_user   = self::_get_user_by_email( $current_wp_user->user_email );
                $network_user_info = array();

                $skips_count = 0;

                $sites       = self::get_sites();
                $sites_count = count( $sites );

                $blog_id_2_install_map = array();

                $is_first_non_ignored_blog = true;

                foreach ( $sites as $site ) {
                    $blog_id = self::get_site_blog_id( $site );

                    $blog_install_timestamp = $this->_storage->get( 'install_timestamp', null, $blog_id );

                    if ( is_null( $blog_install_timestamp ) ) {
                        // Plugin has not been installed on this blog.
                        continue;
                    }

                    $is_earlier_install = (
                        ! is_null( $install_timestamp ) &&
                        $blog_install_timestamp < $install_timestamp
                    );

                    $install = $this->get_install_by_blog_id( $blog_id );

                    $update_network_user_info = false;

                    if ( ! is_object( $install ) ) {
                        if ( ! $this->_storage->get( 'is_anonymous', false, $blog_id ) ) {
                            // The opt-in decision (whether to skip or opt in) is yet to be made.
                            continue;
                        }

                        $skips_count ++;
                    } else {
                        $blog_id_2_install_map[ $blog_id ] = $install;

                        if ( empty( $network_user_info ) ) {
                            // Set the network user info for the 1st time. Choose any user information whether or not it is for the current WP user.
                            $update_network_user_info = true;
                        }

                        if ( ! $update_network_user_info &&
                             is_object( $current_fs_user ) &&
                             $network_user_info['user_id'] != $current_fs_user->id &&
                             $install->user_id == $current_fs_user->id
                        ) {
                            // If an install that is owned by the current WP user is found, use its user information instead.
                            $update_network_user_info = true;
                        }

                        if ( ! $update_network_user_info &&
                             $is_earlier_install &&
                             ( ! is_object( $current_fs_user ) || $current_fs_user->id == $install->user_id )
                        ) {
                            // Update to the earliest install info if there's no install found so far that is owned by the current WP user; OR only if the found install is owned by the current WP user.
                            $update_network_user_info = true;
                        }
                    }

                    if ( $update_network_user_info ) {
                        $network_user_info = array(
                            'user_id' => $install->user_id,
                            'blog_id' => $blog_id
                        );
                    }

                    $site_prev_is_premium = $this->_storage->get( 'prev_is_premium', null, $blog_id );

                    if ( $is_first_non_ignored_blog ) {
                        $prev_is_premium = $site_prev_is_premium;

                        if ( is_null( $network_install_timestamp ) ) {
                            $install_timestamp = $blog_install_timestamp;
                        }

                        $is_first_non_ignored_blog = false;

                        continue;
                    }

                    if ( ! is_null( $prev_is_premium ) && $prev_is_premium !== $site_prev_is_premium ) {
                        // If a different `$site_prev_is_premium` value is found, do not include the option in the collection of options to update.
                        $prev_is_premium = null;
                    }

                    if ( $is_earlier_install ) {
                        // If an earlier install timestamp is found.
                        $install_timestamp = $blog_install_timestamp;
                    }
                }

                $installs_count = count( $blog_id_2_install_map );

                if ( $sites_count === ( $installs_count + $skips_count ) ) {
                    if ( ! empty( $network_user_info ) ) {
                        $options_to_update['network_user_id']         = $network_user_info['user_id'];
                        $options_to_update['network_install_blog_id'] = $network_user_info['blog_id'];

                        foreach ( $blog_id_2_install_map as $blog_id => $install ) {
                            if ( $install->user_id == $network_user_info['user_id'] ) {
                                continue;
                            }

                            $this->_storage->store( 'is_delegated_connection', true, $blog_id );
                        }
                    }

                    if ( $sites_count === $skips_count ) {
                        /**
                         * Assume network-level skipping as the intended action if all actions identified were only
                         * skipping of the connection (i.e., no opt-ins and delegated connections so far).
                         */
                        $options_to_update['is_anonymous_ms'] = true;
                    } else if ( $sites_count === $installs_count ) {
                        /**
                         * Assume network-level opt-in as the intended action if all actions identified were only opt-ins
                         * (i.e., no delegation and skipping of the connections so far).
                         */
                        $options_to_update['is_network_connected'] = true;
                    }
                }
            }

            if ( ! is_null( $install_timestamp ) ) {
                $options_to_update['install_timestamp'] = $install_timestamp;
            }

            if ( ! is_null( $prev_is_premium ) ) {
                $options_to_update['prev_is_premium'] = $prev_is_premium;
            }

            if ( ! empty( $options_to_update ) ) {
                $this->adjust_storage( $options_to_update, $is_network_admin );
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @param array $options
         * @param bool  $is_network_admin
         */
        private function adjust_storage( $options, $is_network_admin ) {
            foreach ( $options as $name => $value ) {
                $this->_storage->store( $name, $value, $is_network_admin ? true : null );
            }
        }

        /**
         * Checks whether this module has a settings menu.
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.2.2
         *
         * @return bool
         */
        function has_settings_menu() {
            return ( $this->_is_network_active && fs_is_network_admin() ) ?
                $this->_menu->has_network_menu() :
                $this->_menu->has_menu();
        }

        /**
         * If `true` the opt-in should be shown as a modal dialog box on the themes.php page. WordPress.org themes guidelines prohibit from redirecting the user from the themes.php page after activating a theme.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.4.5
         *
         * @return bool
         */
        function show_opt_in_on_themes_page() {
            if ( ! $this->is_free_wp_org_theme() ) {
                return false;
            }

            if ( ! $this->has_settings_menu() ) {
                return true;
            }

            return $this->show_settings_with_tabs();
        }

        /**
         * If `true` the opt-in should be shown on the product's main setting page.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.4.5
         *
         * @return bool
         *
         * @uses show_opt_in_on_themes_page();
         */
        function show_opt_in_on_setting_page() {
            return ! $this->show_opt_in_on_themes_page();
        }

        /**
         * If `true` the settings should be shown using tabs.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.4.5
         *
         * @return bool
         */
        function show_settings_with_tabs() {
            return ( self::NAVIGATION_TABS === $this->_navigation );
        }

        /**
         * Check if the context module is free wp.org theme.
         *
         * This method is helpful because:
         *      1. wp.org themes are limited to a single submenu item,
         *         and sub-submenu items are most likely not allowed (never verified).
         *      2. wp.org themes are not allowed to redirect the user
         *         after the theme activation, therefore, the agreed UX
         *         is showing the opt-in as a modal dialog box after
         *         activation (approved by @otto42, @emiluzelac, @greenshady, @grapplerulrich).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @return bool
         */
        function is_free_wp_org_theme() {
            return (
                $this->is_theme() &&
                $this->is_org_repo_compliant() &&
                ! $this->is_premium()
            );
        }

        /**
         * Checks whether this a submenu item is visible.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.6
         * @since  1.2.2.7 Even if the menu item was specified to be hidden, when it is the context page, then show the submenu item so the user will have the right context page.
         *
         * @param string $slug
         * @param bool   $is_tabs_visibility_check This is used to decide if the associated tab should be shown or hidden.
         *
         * @return bool
         */
        function is_submenu_item_visible( $slug, $is_tabs_visibility_check = false ) {
            if ( $this->is_admin_page( $slug ) ) {
                /**
                 * It is the current context page, so show the submenu item
                 * so the user will have the right context page, even if it
                 * was set to hidden.
                 */
                return true;
            }

            if ( ! $this->has_settings_menu() ) {
                // No menu settings at all.
                return false;
            }

            if (
                ! $is_tabs_visibility_check &&
                $this->is_org_repo_compliant() &&
                $this->show_settings_with_tabs()
            ) {
                /**
                 * wp.org themes are limited to a single submenu item, and
                 * sub-submenu items are most likely not allowed (never verified).
                 */
                return false;
            }

            return $this->_menu->is_submenu_item_visible( $slug );
        }

        /**
         * Check if a Freemius page should be accessible via the UI.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @param string $slug
         *
         * @return bool
         */
        function is_page_visible( $slug ) {
            if ( $this->is_admin_page( $slug ) ) {
                return true;
            }

            return $this->_menu->is_submenu_item_visible( $slug, true, true );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         */
        private function _version_updates_handler() {
            if ( ! isset( $this->_storage->sdk_version ) || $this->_storage->sdk_version != $this->version ) {
                // Freemius version upgrade mode.
                $this->_storage->sdk_last_version = $this->_storage->sdk_version;
                $this->_storage->sdk_version      = $this->version;

                if ( empty( $this->_storage->sdk_last_version ) ||
                     version_compare( $this->_storage->sdk_last_version, $this->version, '<' )
                ) {
                    $this->_storage->sdk_upgrade_mode   = true;
                    $this->_storage->sdk_downgrade_mode = false;
                } else {
                    $this->_storage->sdk_downgrade_mode = true;
                    $this->_storage->sdk_upgrade_mode   = false;

                }

                $this->do_action( 'sdk_version_update', $this->_storage->sdk_last_version, $this->version );
            }

            $plugin_version = $this->get_plugin_version();
            if ( ! isset( $this->_storage->plugin_version ) || $this->_storage->plugin_version != $plugin_version ) {
                // Plugin version upgrade mode.
                $this->_storage->plugin_last_version = $this->_storage->plugin_version;
                $this->_storage->plugin_version      = $plugin_version;

                if ( empty( $this->_storage->plugin_last_version ) ||
                     version_compare( $this->_storage->plugin_last_version, $plugin_version, '<' )
                ) {
                    $this->_storage->plugin_upgrade_mode   = true;
                    $this->_storage->plugin_downgrade_mode = false;
                } else {
                    $this->_storage->plugin_downgrade_mode = true;
                    $this->_storage->plugin_upgrade_mode   = false;
                }

                if ( ! empty( $this->_storage->plugin_last_version ) ) {
                    // Different version of the plugin was installed before, therefore it's an update.
                    $this->_storage->is_plugin_new_install = false;
                }

                $this->do_action( 'plugin_version_update', $this->_storage->plugin_last_version, $plugin_version );
            }
        }

        #--------------------------------------------------------------------------------
        #region Data Migration on SDK Update
        #--------------------------------------------------------------------------------

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.5
         *
         * @param string $sdk_prev_version
         * @param string $sdk_version
         */
        function _sdk_version_update( $sdk_prev_version, $sdk_version ) {
            if ( empty( $sdk_prev_version ) ) {
                return;
            }

            if (
                 version_compare( $sdk_prev_version, '2.5.1', '<' ) &&
                 version_compare( $sdk_version, '2.5.1', '>=' )
            ) {
                if ( $this->is_registered( true ) ) {
                    /**
                     * Migrate to new permissions layer.
                     */
                    require_once WP_FS__DIR_INCLUDES . '/supplements/fs-migration-2.5.1.php';

                    $install_by_blog_id = is_multisite() ?
                        $this->get_blog_install_map() :
                        array( 0 => $this->_site );

                    fs_migrate_251( $this, $install_by_blog_id );
                }
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.0.0
         *
         * @param \FS_Storage   $storage
         * @param bool|int|null $blog_id
         */
        private static function migrate_install_plan_to_plan_id( FS_Storage $storage, $blog_id = null ) {
            if ( empty( $storage->sdk_version ) ) {
                // New installation of the plugin, no need to upgrade.
                return;
            }

            if ( ! version_compare( $storage->sdk_version, '2.0.0', '<' ) ) {
                // Previous version is >= 2.0.0, so no need to migrate.
                return;
            }

            // Alias.
            $module_type = $storage->get_module_type();
            $module_slug = $storage->get_module_slug();

            $installs = self::get_all_sites( $module_type, $blog_id );
            $install  = isset( $installs[ $module_slug ] ) ? $installs[ $module_slug ] : null;

            if ( ! is_object( $install ) ) {
                return;
            }

            if ( isset( $install->plan ) && is_object( $install->plan ) ) {
                if ( isset( $install->plan->id ) && ! empty( $install->plan->id ) ) {
                    $install->plan_id = self::_decrypt( $install->plan->id );
                }

                unset( $install->plan );

                $installs[ $module_slug ] = clone $install;

                self::set_account_option_by_module(
                    $module_type,
                    'sites',
                    $installs,
                    true,
                    $blog_id
                );
            }
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @param string $plugin_prev_version
         * @param string $plugin_version
         */
        function _after_version_update( $plugin_prev_version, $plugin_version ) {
            if ( $this->is_theme() ) {
                // Expire the cache of the previous tabs since the theme may
                // have setting updates.
                $this->_cache->expire( 'tabs' );
                $this->_cache->expire( 'tabs_stylesheets' );
            }
        }

        /**
         * A special migration logic for the $_accounts, executed for all the plugins in the system:
         *  - Moves some data to the network level storage.
         *  - If the plugin's connection was skipped for all sites, set the plugin as if it was network skipped.
         *  - If the plugin's connection was ignored for all sites, don't do anything in terms of the network connection.
         *  - If the plugin was connected to all sites by the same super-admin, set the plugin as if was network opted-in for all sites.
         *  - If there's at least one site that was connected by a super-admin, find the "main super-admin" (the one that installed the majority of the plugin installs) and set the plugin as if was network activated with the main super-admin, set all the sites that were skipped or opted-in with a different user to delegated mode. Then, prompt the currently logged super-admin to choose what to do with the ignored sites.
         *  - If there are any sites in the network which the connection decision was not yet taken for, set this plugin into network activation mode so a super-admin can choose what to do with the rest of the sites.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         */
        private static function migrate_accounts_to_network() {
            $sites             = self::get_sites();
            $sites_count       = count( $sites );
            $connection_status = array();
            $plugin_slugs      = array();
            foreach ( $sites as $site ) {
                $blog_id = self::get_site_blog_id( $site );

                self::$_accounts->migrate_to_network( $blog_id );

                /**
                 * Build a list of all Freemius powered plugins slugs.
                 */
                $id_slug_type_path_map = self::$_accounts->get_option( 'id_slug_type_path_map', array(), $blog_id );
                foreach ( $id_slug_type_path_map as $module_id => $data ) {
                    if ( WP_FS__MODULE_TYPE_PLUGIN === $data['type'] ) {
                        $plugin_slugs[ $data['slug'] ] = true;
                    }
                }

                $installs = self::get_account_option( 'sites', WP_FS__MODULE_TYPE_PLUGIN, $blog_id );

                if ( is_array( $installs ) ) {
                    foreach ( $installs as $slug => $install ) {
                        if ( ! isset( $connection_status[ $slug ] ) ) {
                            $connection_status[ $slug ] = array();
                        }

                        if ( is_object( $install ) &&
                             FS_Site::is_valid_id( $install->id ) &&
                             FS_User::is_valid_id( $install->user_id )
                        ) {
                            $connection_status[ $slug ][ $blog_id ] = $install->user_id;
                        }
                    }
                }
            }

            foreach ( $plugin_slugs as $slug => $true ) {
                if ( ! isset( $connection_status[ $slug ] ) ) {
                    $connection_status[ $slug ] = array();
                }

                foreach ( $sites as $site ) {
                    $blog_id = self::get_site_blog_id( $site );

                    if ( isset( $connection_status[ $slug ][ $blog_id ] ) ) {
                        continue;
                    }

                    $storage = FS_Storage::instance( WP_FS__MODULE_TYPE_PLUGIN, $slug );

                    $is_anonymous = $storage->get( 'is_anonymous', null, $blog_id );

                    if ( ! is_null( $is_anonymous ) ) {
                        // Since 1.1.3 is_anonymous is an array.
                        if ( is_array( $is_anonymous ) && isset( $is_anonymous['is'] ) ) {
                            $is_anonymous = $is_anonymous['is'];
                        }

                        if ( is_bool( $is_anonymous ) && true === $is_anonymous ) {
                            $connection_status[ $slug ][ $blog_id ] = 'skipped';
                        }
                    }

                    if ( ! isset( $connection_status[ $slug ][ $blog_id ] ) ) {
                        $connection_status[ $slug ][ $blog_id ] = 'ignored';
                    }
                }
            }

            $super_admins = array();

            foreach ( $connection_status as $slug => $blogs_status ) {
                $skips                 = 0;
                $ignores               = 0;
                $connections           = 0;
                $opted_in_users        = array();
                $opted_in_super_admins = array();

                $storage = FS_Storage::instance( WP_FS__MODULE_TYPE_PLUGIN, $slug );

                foreach ( $blogs_status as $blog_id => $status_or_user_id ) {
                    if ( 'skipped' === $status_or_user_id ) {
                        $skips ++;
                    } else if ( 'ignored' === $status_or_user_id ) {
                        $ignores ++;
                    } else if ( FS_User::is_valid_id( $status_or_user_id ) ) {
                        $connections ++;

                        if ( ! isset( $opted_in_users[ $status_or_user_id ] ) ) {
                            $opted_in_users[ $status_or_user_id ] = array();
                        }

                        $opted_in_users[ $status_or_user_id ][] = $blog_id;

                        if ( isset( $super_admins[ $status_or_user_id ] ) ||
                             self::is_super_admin( $status_or_user_id )
                        ) {
                            // Cache super-admin data.
                            $super_admins[ $status_or_user_id ] = true;

                            // Remember opted-in super-admins for the plugin.
                            $opted_in_super_admins[ $status_or_user_id ] = true;
                        }
                    }
                }

                $main_super_admin_user_id = null;
                $all_migrated             = false;
                if ( $sites_count == $skips ) {
                    // All sites were skipped -> network skip by copying the anonymous mode from any of the sites.
                    $storage->is_anonymous_ms = $storage->is_anonymous;

                    $all_migrated = true;
                } else if ( $sites_count == $ignores ) {
                    // Don't do anything, still in activation mode.

                    $all_migrated = true;
                } else if ( 0 < count( $opted_in_super_admins ) ) {
                    // Find the super-admin with the majority of installs.
                    $max_installs_by_super_admin = 0;
                    foreach ( $opted_in_super_admins as $user_id => $true ) {
                        $installs_count = count( $opted_in_users[ $user_id ] );

                        if ( $installs_count > $max_installs_by_super_admin ) {
                            $max_installs_by_super_admin = $installs_count;
                            $main_super_admin_user_id    = $user_id;
                        }
                    }

                    if ( $sites_count == $connections && 1 == count( $opted_in_super_admins ) ) {
                        // Super-admin opted-in for all sites in the network.
                        $storage->is_network_connected = true;

                        $all_migrated = true;
                    }

                    // Store network user.
                    $storage->network_user_id = $main_super_admin_user_id;

                    $storage->network_install_blog_id = ( $sites_count == $connections ) ?
                        // Since all sites are opted-in, associating with the main site.
                        get_current_blog_id() :
                        // Associating with the 1st found opted-in site.
                        $opted_in_users[ $main_super_admin_user_id ][0];

                    /**
                     * Make sure we migrate the plan ID of the network install, otherwise, if after the migration
                     * the 1st page that will be loaded is the network level WP Admin and $storage->network_install_blog_id
                     * is different than the main site of the network, the $this->_site will not be set since the plan_id
                     * will be empty.
                     */
                    $storage->migrate_to_network();
                    self::migrate_install_plan_to_plan_id( $storage, $storage->network_install_blog_id );
                } else {
                    // At least one opt-in. All the opt-in were created by a non-super-admin.
                    if ( 0 == $ignores ) {
                        // All sites were opted-in or skipped, all by non-super-admin. So delegate all.
                        $storage->store( 'is_delegated_connection', true, true );

                        $all_migrated = true;
                    }
                }

                if ( ! $all_migrated ) {
                    /**
                     * Delegate all sites that were:
                     *  1) Opted-in by a user that is NOT the main-super-admin.
                     *  2) Skipped and non of the sites was opted-in by a super-admin. If any site was opted-in by a super-admin, there will be a main-super-admin, and we consider the skip as if it was done by that user.
                     */
                    foreach ( $blogs_status as $blog_id => $status_or_user_id ) {
                        if ( $status_or_user_id == $main_super_admin_user_id ) {
                            continue;
                        }

                        if ( FS_User::is_valid_id( $status_or_user_id ) ||
                             ( 'skipped' === $status_or_user_id && is_null( $main_super_admin_user_id ) )
                        ) {
                            $storage->store( 'is_delegated_connection', true, $blog_id );
                        }
                    }
                }


                if ( ( $connections + $skips > 0 ) ) {
                    if ( $ignores > 0 ) {
                        /**
                         * If admin already opted-in or skipped in any of the network sites, and also
                         * have sites which the connection decision was not yet taken, set this plugin
                         * into network activation mode so the super-admin can choose what to do with
                         * the rest of the sites.
                         */
                        self::set_network_upgrade_mode( $storage );
                    }
                }
            }
        }

        /**
         * Set a module into network upgrade mode.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param \FS_Storage $storage
         *
         * @return bool
         */
        public static function set_network_upgrade_mode( FS_Storage $storage ) {
            return $storage->is_network_activation = true;
        }

        /**
         * Will return true after upgrading to the SDK with the network level integration,
         * when the super-admin involvement is required regarding the rest of the sites.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return bool
         */
        function is_network_upgrade_mode() {
            return $this->_storage->get( 'is_network_activation' );
        }

        /**
         * Clear flag after the upgrade mode completion.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return bool True if network activation was on and now completed.
         */
        private function network_upgrade_mode_completed() {
            if ( fs_is_network_admin() && $this->is_network_upgrade_mode() ) {
                $this->_storage->remove( 'is_network_activation' );

                return true;
            }

            return false;
        }

        #endregion

        /**
         * This action is connected to the 'plugins_loaded' hook and helps to determine
         * if this is a new plugin installation or a plugin update.
         *
         * There are 3 different use-cases:
         *    1) New plugin installation right with Freemius:
         *       1.1 _activate_plugin_event_hook() will be executed first
         *       1.2 Since $this->_storage->is_plugin_new_install is not set,
         *           and $this->_storage->plugin_last_version is not set,
         *           $this->_storage->is_plugin_new_install will be set to TRUE.
         *       1.3 When _plugins_loaded() will be executed, $this->_storage->is_plugin_new_install will
         *           be already set to TRUE.
         *
         *    2) Plugin update, didn't have Freemius before, and now have the SDK:
         *       2.1 _activate_plugin_event_hook() will not be executed, because
         *           the activation hook do NOT fires on updates since WP 3.1.
         *       2.2 When _plugins_loaded() will be executed, $this->_storage->is_plugin_new_install will
         *           be empty, therefore, it will be set to FALSE.
         *
         *    3) Plugin update, had Freemius in prev version as well:
         *       3.1 _version_updates_handler() will be executed 1st, since FS was installed
         *           before, $this->_storage->plugin_last_version will NOT be empty,
         *           therefore, $this->_storage->is_plugin_new_install will be set to FALSE.
         *       3.2 When _plugins_loaded() will be executed, $this->_storage->is_plugin_new_install is
         *           already set, therefore, it will not be modified.
         *
         *    Use-case #3 is backward compatible, #3.1 will be executed since 1.0.9.
         *
         * NOTE:
         *    The only fallback of this mechanism is if an admin updates a plugin based on use-case #2,
         *    and then, the next immediate PageView is the plugin's main settings page, it will not
         *    show the opt-in right away. The reason it will happen is because Freemius execution
         *    will be turned off till the plugin is fully loaded at least once
         *    (till $this->_storage->was_plugin_loaded is TRUE).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.9
         *
         */
        function _plugins_loaded() {
            // Update flag that plugin was loaded with Freemius at least once.
            $this->_storage->was_plugin_loaded = true;

            /**
             * Bug fix - only set to false when it's a plugin, due to the
             * execution sequence of the theme hooks and our methods, if
             * this will be set for themes, Freemius will always assume
             * it's a theme update.
             *
             * @author Vova Feldman (@svovaf)
             * @since  1.2.2.2
             */
            if ( $this->is_plugin() &&
                 ! isset( $this->_storage->is_plugin_new_install )
            ) {
                $this->_storage->is_plugin_new_install = (
                    ! is_plugin_active( $this->_plugin_basename ) &&
                    empty( $this->_storage->plugin_last_version )
                );
            }
        }

        function _run_garbage_collector() {
            if ( true !== fs_get_optional_constant( 'WP_FS__ENABLE_GARBAGE_COLLECTOR', true ) ) {
                return;
            }

            if ( ! $this->is_user_in_admin() ) {
                return;
            }

            require_once WP_FS__DIR_INCLUDES . '/class-fs-lock.php';

            $lock = new FS_Lock( 'garbage_collection' );

            if ( $lock->is_locked() ) {
                return;
            }

            // Create a 1-day lock.
            $lock->lock( WP_FS__TIME_24_HOURS_IN_SEC );

            FS_Garbage_Collector::instance()->clean();
        }

        /**
         * Modifies all external links in the submenu by altering their href, and also opens them in new tab if needed.
         *
         * @author Vova Feldman (@svovaf)
         * @author Swashata Ghosh (@swashata)
         * @since  2.1.4
         */
        static function _handle_submenu_external_link() {
            ?>
            <script type="text/javascript">
                (function ( $ ) {
                    $( '.fs-submenu-item' ).each( function () {
                        var $this = $( this ),
                            $parent = $this.parent(),
                            externalLink = $this.data( 'fs-external-url' ),
                            isOpensInNewTab = $this.data( 'fs-new-tab' );

                        if ( externalLink ) {
                            $parent.attr( 'href', externalLink );
                        }

                        if ( isOpensInNewTab ) {
                            $parent.attr( { target: '_blank', rel: 'noopener noreferrer' } );
                        }
                    } );
                } )( jQuery );
            </script>
            <?php
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         */
        private function register_constructor_hooks() {
            $this->_logger->entrance();

            if ( is_admin() ) {
                add_action( 'admin_init', array( &$this, '_hook_action_links_and_register_account_hooks' ) );

                if ( $this->is_plugin() ) {
                    if ( self::is_plugin_install_page() && true !== fs_request_get_bool( 'fs_allow_updater_and_dialog' ) ) {
                        /**
                         * Unless the `fs_allow_updater_and_dialog` URL param exists and its value is `true`, make
                         * Freemius-related updates unavailable on the "Add Plugins" admin page (/plugin-install.php)
                         * so that they won't interfere with the .org plugins' functionalities on that page (e.g.
                         * updating of a .org plugin).
                         */
                        add_filter( 'site_transient_update_plugins', array( 'Freemius', '_remove_fs_updates_from_plugin_install_page' ), 10, 2 );
                    } else if ( self::is_plugins_page() || self::is_updates_page() ) {
                        /**
                         * On the "Plugins" and "Updates" admin pages, if there are premium or non–org-compliant plugins, modify their details dialog URLs (add a Freemius-specific param) so that the SDK can determine if the plugin information dialog should show information from Freemius.
                         *
                         * @author Leo Fajardo (@leorw)
                         * @since 2.2.3
                         */
                        add_action( 'admin_footer', array( 'Freemius', '_prepend_fs_allow_updater_and_dialog_flag_url_param' ) );
                    }

                    $plugin_dir = dirname( $this->_plugin_dir_path ) . '/';

                    /**
                     * @since 1.2.2
                     *
                     * Hook to both free and premium version activations to support
                     * auto deactivation on the other version activation.
                     */
                    register_activation_hook(
                        $plugin_dir . $this->_free_plugin_basename,
                        array( &$this, '_activate_plugin_event_hook' )
                    );

                    register_activation_hook(
                        $plugin_dir . $this->premium_plugin_basename(),
                        array( &$this, '_activate_plugin_event_hook' )
                    );
                } else {
                    add_action( 'after_switch_theme', array( &$this, '_activate_theme_event_hook' ), 10, 2 );

                    add_action( 'admin_footer', array( &$this, '_style_premium_theme' ) );
                }

                /**
                 * Part of the mechanism to identify new plugin install vs. plugin update.
                 *
                 * @author Vova Feldman (@svovaf)
                 * @since  1.1.9
                 */
                if ( empty( $this->_storage->was_plugin_loaded ) ) {
                    /**
                     * During the plugin activation (not theme), 'plugins_loaded' will be already executed
                     * when the logic gets here since the activation logic first add the activate plugins,
                     * then triggers 'plugins_loaded', and only then include the code of the plugin that
                     * is activated. Which means that _plugins_loaded() will NOT be executed during the
                     * plugin activation, and that IS intentional.
                     *
                     * @author Vova Feldman (@svovaf)
                     */
                    if ( $this->is_plugin() &&
                         $this->is_activation_mode( false ) &&
                         0 == did_action( 'plugins_loaded' )
                    ) {
                        add_action( 'plugins_loaded', array( &$this, '_plugins_loaded' ) );
                    } else {
                        // If was activated before, then it was already loaded before.
                        $this->_plugins_loaded();
                    }
                }

                add_action( 'plugins_loaded', array( &$this, '_run_garbage_collector' ) );

                if ( ! self::is_ajax() ) {
                    if ( ! $this->is_addon() ) {
                        add_action( 'init', array( &$this, '_add_default_submenu_items' ), WP_FS__LOWEST_PRIORITY );
                    }
                }

                if ( $this->_storage->handle_gdpr_admin_notice ) {
                    add_action( 'init', array( &$this, '_maybe_show_gdpr_admin_notice' ) );
                }

                add_action( 'init', array( &$this, '_maybe_add_gdpr_optin_ajax_handler') );
                add_action( 'init', array( &$this, '_add_pricing_ajax_handler' ) );
            }

            if ( $this->is_plugin() ) {
                if ( version_compare( $GLOBALS['wp_version'], '5.1', '<' ) ) {
                    add_action( 'wpmu_new_blog', array( $this, '_after_new_blog_callback' ), 10, 6 );
                } else {
                    add_action( 'wp_initialize_site', array( $this, '_after_wp_initialize_site_callback' ), 11, 2 );
                }

                register_deactivation_hook( $this->_plugin_main_file_path, array( &$this, '_deactivate_plugin_hook' ) );
            }

            if ( is_multisite() ) {
                add_action( 'deactivate_blog', array( &$this, '_after_site_deactivated_callback' ) );
                add_action( 'archive_blog', array( &$this, '_after_site_deactivated_callback' ) );
                add_action( 'make_spam_blog', array( &$this, '_after_site_deactivated_callback' ) );

                if ( version_compare( $GLOBALS['wp_version'], '5.1', '<' ) ) {
                    add_action( 'deleted_blog', array( $this, '_after_site_deleted_callback' ), 10, 2 );
                } else {
                    add_action( 'wp_delete_site', array( $this, '_after_wpsite_deleted_callback' ) );
                }

                add_action( 'activate_blog', array( &$this, '_after_site_reactivated_callback' ) );
                add_action( 'unarchive_blog', array( &$this, '_after_site_reactivated_callback' ) );
                add_action( 'make_ham_blog', array( &$this, '_after_site_reactivated_callback' ) );
            }

            if ( $this->is_theme() &&
                 self::is_customizer() &&
                 $this->apply_filters( 'show_customizer_upsell', true )
            ) {
                // Register customizer upsell.
                add_action( 'customize_register', array( &$this, '_customizer_register' ) );
            }

            add_action( 'admin_init', array( &$this, '_redirect_on_clicked_menu_link' ), WP_FS__LOWEST_PRIORITY );

            if ( $this->is_theme() && ! $this->is_migration() ) {
                add_action( 'admin_init', array( &$this, '_add_tracking_links' ) );
            }

            add_action( 'admin_init', array( &$this, '_add_license_activation' ) );
            add_action( 'admin_init', array( &$this, '_add_premium_version_upgrade_selection' ) );
            add_action( 'admin_init', array( &$this, '_add_beta_mode_update_handler' ) );
            add_action( 'admin_init', array( &$this, '_add_user_change_option' ) );
            add_action( 'admin_init', array( &$this, '_add_email_address_update_option' ) );

            $this->add_ajax_action( 'update_billing', array( &$this, '_update_billing_ajax_action' ) );
            $this->add_ajax_action( 'start_trial', array( &$this, '_start_trial_ajax_action' ) );
            $this->add_ajax_action( 'set_data_debug_mode', array( &$this, '_set_data_debug_mode' ) );
            $this->add_ajax_action( 'toggle_whitelabel_mode', array( &$this, '_toggle_whitelabel_mode_ajax_handler' ) );

            if ( $this->_is_network_active && fs_is_network_admin() ) {
                $this->add_ajax_action( 'network_activate', array( &$this, '_network_activate_ajax_action' ) );
            }

            $this->add_ajax_action( 'install_premium_version', array(
                &$this,
                '_install_premium_version_ajax_action'
            ) );

            $this->add_ajax_action( 'submit_affiliate_application', array( &$this, '_submit_affiliate_application' ) );

            $this->add_action( 'after_plans_sync', array( &$this, '_check_for_trial_plans' ) );

            $this->add_action( 'sdk_version_update', array( &$this, '_sdk_version_update' ), WP_FS__DEFAULT_PRIORITY, 2 );

            $this->add_action(
                'plugin_version_update',
                array( &$this, '_after_version_update' ),
                WP_FS__DEFAULT_PRIORITY,
                2
            );
            $this->add_filter( 'after_code_type_change', array( &$this, '_after_code_type_change' ) );

            add_action( 'admin_init', array( &$this, '_add_trial_notice' ) ); // @phpstan-ignore-line
            add_action( 'admin_init', array( &$this, '_add_affiliate_program_notice' ) ); // @phpstan-ignore-line
            add_action( 'admin_enqueue_scripts', array( &$this, '_enqueue_common_css' ) );

            /**
             * Handle request to reset anonymous mode for `get_reconnect_url()` or reset the pending activation mode.
             *
             * @author Vova Feldman (@svovaf)
             * @since  1.2.1.5
             */
            if (
                (
                    fs_request_is_action( 'reset_anonymous_mode' ) ||
                    fs_request_is_action( 'reset_pending_activation_mode' )
                ) &&
                $this->get_unique_affix() === fs_request_get_raw( 'fs_unique_affix' )
            ) {
                add_action( 'admin_init', array( &$this, 'connect_again' ) );
            }

            FS_DebugManager::register_hooks();
        }

        /**
         * Register the required hooks right after the settings parse is completed.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.3.1
         */
        private function register_after_settings_parse_hooks() {
            if ( is_admin() &&
                 $this->is_theme() &&
                 $this->is_premium() &&
                 ! $this->has_active_valid_license()
            ) {
                $this->add_ajax_action(
                    'delete_theme_update_data',
                    array( &$this, '_delete_theme_update_data_action' )
                );
            }

            if ( $this->show_settings_with_tabs() ) {
                /**
                 * Include the required hooks to capture the theme settings' page tabs
                 * and cache them.
                 *
                 * @author Vova Feldman (@svovaf)
                 * @since  1.2.2.7
                 */
                if ( ! $this->_cache->has_valid( 'tabs' ) ) {
                    add_action( 'admin_footer', array( &$this, '_tabs_capture' ) );
                    // Add license activation AJAX callback.
                    $this->add_ajax_action( 'store_tabs', array( &$this, '_store_tabs_ajax_action' ) );

                    add_action( 'admin_enqueue_scripts', array( &$this, '_store_tabs_styles' ), 9999999 );
                }

                add_action(
                    'admin_footer',
                    array( &$this, '_add_freemius_tabs' ),
                    /**
                     * The tabs JS code must be executed after the tabs capture logic (_tabs_capture()).
                     * That's why the priority is 11 while the tabs capture logic is added
                     * with priority 10.
                     *
                     * @author Vova Feldman (@svovaf)
                     */
                    11
                );
            }

            if ( ! self::is_ajax() ) {
                if ( ! $this->is_addon() || $this->is_only_premium() ) {
                    add_action(
                        ( $this->_is_network_active && fs_is_network_admin() ? 'network_' : '' ) . 'admin_menu',
                        array( &$this, '_prepare_admin_menu' ),
                        WP_FS__LOWEST_PRIORITY
                    );
                }
            }
        }

        /**
         * Makes Freemius-related updates unavailable on the "Add Plugins" admin page (/plugin-install.php) so that
         * they won't interfere with the .org plugins' functionalities on that page (e.g. updating of a .org plugin).
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.2.3
         *
         * @param object      $updates
         * @param string|null $transient
         *
         * @return object
         */
        static function _remove_fs_updates_from_plugin_install_page( $updates, $transient = null ) {
            if ( is_object( $updates ) && isset( $updates->response ) ) {
                foreach ( $updates->response as $file => $plugin ) {
                    if ( isset( $plugin->package ) && false !== strpos( $plugin->package, 'api.freemius' ) ) {
                        unset( $updates->response[ $file ] );
                    }
                }
            }

            return $updates;
        }

        /**
         * Prepends the `fs_allow_updater_and_dialog` param to the plugin information URLs to tell the SDK to handle
         * the information that is shown on the plugin details dialog that is shown when the relevant link is clicked.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.2.3
         *
         * @return void
         */
        static function _prepend_fs_allow_updater_and_dialog_flag_url_param() {
            $slug_basename_map = array();
            foreach ( self::$_instances as $instance ) {
                if ( ! $instance->is_plugin() ) {
                    continue;
                }

                $slug_basename_map[ $instance->get_slug() ] = $instance->premium_plugin_basename();
            }
            ?>
            <script type="text/javascript">
            (function( $ ) {
                var slugBasenameMap = <?php echo json_encode( $slug_basename_map ) ?>;
                for ( var slug in slugBasenameMap ) {
                    var basename = slugBasenameMap[ slug ];

                    // Try to get the plugin rows if on the "Plugins" page.
                    var $pluginRows = $( '.wp-list-table.plugins tr[data-plugin="' + basename + '"]');

                    if ( 0 === $pluginRows.length ) {
                        // Try to get the plugin rows if on the "Updates" page.
                        var $pluginCheckbox = $( '#update-plugins-table input[type="checkbox"][value="' + basename + '"]' );
                        if ( 0 !== $pluginCheckbox.length ) {
                            $pluginRows = $pluginCheckbox.parents( 'tr:first' );
                        }
                    }

                    if ( 0 === $pluginRows.length ) {
                        // No plugin rows found.
                        continue;
                    }

                    // Find the "View details" links and add the `fs_allow_updater_and_dialog` param to the URL.
                    $pluginRows.find( 'a[href*="plugin-install.php?tab=plugin-information"]' ).each(function() {
                        var $this = $( this ),
                            href  = $this.attr( 'href' ).replace( '?tab=', '?fs_allow_updater_and_dialog=true&tab=');

                        $this.attr( 'href', href );
                    });
                }
            })( jQuery );
            </script>
            <?php
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         */
        static function _maybe_add_beta_label_styles() {
            $has_any_beta_version = false;

            foreach ( self::$_instances as $instance ) {
                if ( $instance->is_beta() ) {
                    $has_any_beta_version = true;
                    break;
                }
            }

            if ( $has_any_beta_version ) {
                fs_enqueue_local_style( 'fs_plugins', '/admin/plugins.css' );
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         */
        static function _maybe_add_beta_label_to_plugins_and_handle_confirmation() {
            $beta_data = array();

            foreach ( self::$_instances as $instance ) {
                if ( ! $instance->is_premium() ) {
                    continue;
                }

                /**
                 * If there's an available beta version update, a confirmation message will be shown when the
                 * "Update now" link on the "Plugins" or "Themes" page is clicked.
                 */
                $has_beta_update = $instance->has_beta_update();

                $is_beta = (
                    // The "Beta" label is added separately for themes.
                    $instance->is_plugin() &&
                    $instance->is_beta()
                );

                if ( ! $is_beta && ! $has_beta_update ) {
                    continue;
                }

                $beta_data[ $instance->get_plugin_basename() ] = array( 'is_installed_version_beta' => $is_beta );

                if ( ! $has_beta_update ) {
                    continue;
                }

                $beta_data[ $instance->get_plugin_basename() ]['beta_version_update_confirmation_message'] = sprintf(
                    '%s %s',
                    sprintf(
                        fs_esc_attr_inline(
                            'An update to a Beta version will replace your installed version of %s with the latest Beta release - use with caution, and not on production sites. You have been warned.',
                            'beta-version-update-caution',
                            $instance->get_slug()
                        ),
                        $instance->get_plugin_title()
                    ),
                    fs_esc_attr_inline( 'Would you like to proceed with the update?', 'update-confirmation', $instance->get_slug() )
                );
            }

            if ( empty( $beta_data ) ) {
                return;
            }
            ?>
            <script type="text/javascript">
                ( function( $ ) {
                    var betaData = <?php echo json_encode( $beta_data ) ?>;

                    for ( var pluginBasename in betaData ) {
                        if ( ! betaData.hasOwnProperty( pluginBasename ) ) {
                            continue;
                        }

                        if ( ! betaData[ pluginBasename ].is_installed_version_beta ) {
                            continue;
                        }

                        var $parentContainer = $( '.wp-list-table.plugins tr[data-plugin="' + pluginBasename + '"]' );
                        if ( 0 === $parentContainer.length ) {
                            continue;
                        }

                        $parentContainer.find( '.plugin-title > strong:first-child').append(
                            '<span class="fs-tag fs-info"><?php fs_esc_js_echo_inline( 'Beta', 'beta' ) ?></span>'
                        );
                    }

                    setTimeout( function() {
                        // Wait a little bit before adding the event handler, otherwise, it will be overridden by the core WP logic.
                        $( '.plugins .update-message .update-link, .themes .theme .update-message' ).on( 'click', function() {
                            var $parentContainer = $( this ).parents( 'tr:first' );
                            pluginBasename   = ( 0 !== $parentContainer.length ) ?
                                $parentContainer.data( 'plugin' ) :
                                $( this ).parents( '.theme:first' ).data( 'slug' );

                            if (
                                betaData[ pluginBasename ] &&
                                betaData[ pluginBasename ].beta_version_update_confirmation_message &&
                                ! confirm( betaData[ pluginBasename ].beta_version_update_confirmation_message )
                            ) {
                                return false;
                            }
                        } );
                    }, 20 );
                } )( jQuery );
            </script>
            <?php
        }

        /**
         * Keeping the uninstall hook registered for free or premium plugin version may result to a fatal error that
         * could happen when a user tries to uninstall either version while one of them is still active. Uninstalling a
         * plugin will trigger inclusion of the free or premium version and if one of them is active during the
         * uninstallation, a fatal error may occur in case the plugin's class or functions are already defined.
         *
         * @author Leo Fajardo (@leorw)
         *
         * @since  1.2.0
         */
        private function unregister_uninstall_hook() {
            $uninstallable_plugins = (array) get_option( 'uninstall_plugins' );
            unset( $uninstallable_plugins[ $this->_free_plugin_basename ] );
            unset( $uninstallable_plugins[ $this->premium_plugin_basename() ] );

            update_option( 'uninstall_plugins', $uninstallable_plugins );
        }

        /**
         * @since 1.2.0 Invalidate module's main file cache, otherwise, FS_Plugin_Updater will not fetch updates.
         *
         * @param bool $store_prev_path
         */
        private function clear_module_main_file_cache( $store_prev_path = true ) {
            if ( ! isset( $this->_storage->plugin_main_file ) ||
                empty( $this->_storage->plugin_main_file->path )
            ) {
                return;
            }

            if ( ! $store_prev_path ) {
                /**
                 * Storing the previous path is not needed when clearing the cache after an SDK version update since
                 * the main purpose of the cache clearing in that event is to correct a wrong plugin main file path
                 * which causes data mix-up between plugins (e.g. titles and versions of an add-on and its parent plugin).
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.2.1
                 */
                unset( $this->_storage->plugin_main_file->path );
            } else {
                $plugin_main_file = clone $this->_storage->plugin_main_file;

                // Store cached path (2nd layer cache).
                $plugin_main_file->prev_path = $plugin_main_file->path;

                // Clear cached path.
                unset( $plugin_main_file->path );

                $this->_storage->plugin_main_file = $plugin_main_file;
            }

            /**
             * Clear global cached path.
             *
             * @author Leo Fajardo (@leorw)
             * @since  1.2.2
             */
            $id_slug_type_path_map = self::$_accounts->get_option( 'id_slug_type_path_map' );
            unset( $id_slug_type_path_map[ $this->_module_id ]['path'] );
            self::$_accounts->set_option( 'id_slug_type_path_map', $id_slug_type_path_map, true );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.0.0
         */
        function _hook_action_links_and_register_account_hooks() {
            if ( $this->is_migration() ) {
                return;
            }

            if (
                ( self::is_plugins_page() && $this->is_plugin() ) ||
                ( self::is_themes_page() && $this->is_theme() ) ||
                fs_request_is_action_secure( $this->get_unique_affix() . '_reconnect' )
            ) {
                $this->_add_tracking_links();
            }

            if ( self::is_plugins_page() && $this->is_plugin() ) {
                $this->hook_plugin_action_links();
            }

            $this->_register_account_hooks();
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         */
        private function _register_account_hooks() {
            if ( ! is_admin() ) {
                return;
            }

            /**
             * Always show the deactivation feedback form since we added
             * automatic free version deactivation upon premium code activation.
             *
             * @since 1.2.1.6
             */
            $this->add_ajax_action(
                'submit_uninstall_reason',
                array( &$this, '_submit_uninstall_reason_action' )
            );

            $this->add_ajax_action(
                'cancel_subscription_or_trial',
                array( &$this, 'cancel_subscription_or_trial_ajax_action' )
            );

            if ( ! $this->is_addon() || $this->is_parent_plugin_installed() ) {
                if ( ( $this->is_plugin() && self::is_plugins_page() ) ||
                     ( $this->is_theme() && self::is_themes_page() )
                ) {
                    add_action( 'admin_footer', array( &$this, '_add_deactivation_feedback_dialog_box' ) );
                }
            }
        }

        /**
         * Leverage backtrace to find caller plugin file path.
         *
         * @param bool   $is_init   Is initiation sequence.
         * @param string $main_file Since 2.5.0 expects the module's main file path to potentially purge the cached path.
         *
         * @return string
         * @since  1.0.6
         *
         * @author Vova Feldman (@svovaf)
         */
        private function _find_caller_plugin_file( $is_init = false, $main_file = '' ) {
            // Try to load the cached value of the file path.
            if ( isset( $this->_storage->plugin_main_file ) ) {
                $plugin_main_file = $this->_storage->plugin_main_file;
                if ( ! empty( $plugin_main_file->path ) ) {
                    $absolute_path = $this->get_absolute_path( $plugin_main_file->path );
                    if ( file_exists( $absolute_path ) ) {
                        if ( $is_init && $absolute_path !== $this->get_absolute_path( $main_file ) ) {
                            // Update cached path if not matching the actual path.
                            $plugin_main_file->path = $main_file;
                            $this->_storage->plugin_main_file = $plugin_main_file;
                        }

                        return $absolute_path;
                    }
                }
            }

            /**
             * @since 1.2.1
             *
             * `clear_module_main_file_cache()` is clearing the plugin's cached path on
             * deactivation. Therefore, if any plugin/theme was initiating `Freemius`
             * with that plugin's slug, it was overriding the empty plugin path with a wrong path.
             *
             * So, we've added a special mechanism with a 2nd layer of cache that uses `prev_path`
             * when the class instantiator isn't the module.
             */
            if ( ! $is_init ) {
                // Fetch prev path cache.
                if ( isset( $this->_storage->plugin_main_file ) &&
                     ! empty( $this->_storage->plugin_main_file->prev_path )
                ) {
                    $absolute_path = $this->get_absolute_path( $this->_storage->plugin_main_file->prev_path );
                    if ( file_exists( $absolute_path ) ) {
                        return $absolute_path;
                    }
                }

                wp_die(
                    $this->get_text_inline( 'Freemius SDK couldn\'t find the plugin\'s main file. Please contact sdk@freemius.com with the current error.', 'failed-finding-main-path' ) .
                    " Module: {$this->_slug}; SDK: " . WP_FS__SDK_VERSION . ";",
                    $this->get_text_inline( 'Error', 'error' ),
                    array( 'back_link' => true )
                );
            }

            /**
             * @since 1.2.1
             *
             * Only the original instantiator that calls dynamic_init can modify the module's path.
             */
            // Find caller module.
            $this->_storage->plugin_main_file = (object) array(
                'path' => $main_file,
            );

            return $this->get_absolute_path( $main_file );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.3
         *
         * @param string $path
         *
         * @return string
         */
        private function get_relative_path( $path ) {
            $module_root_dir = $this->get_module_root_dir_path();
            if ( 0 === strpos( $path, $module_root_dir ) ) {
                $path = substr( $path, strlen( $module_root_dir ) );
            }

            return $path;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.3
         *
         * @param string      $path
         * @param string|bool $module_type
         *
         * @return string
         */
        private function get_absolute_path( $path, $module_type = false ) {
            $module_root_dir = $this->get_module_root_dir_path( $module_type );
            if ( 0 !== strpos( $path, $module_root_dir ) ) {
                $path = fs_normalize_path( $module_root_dir . $path );
            }

            return $path;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.3
         *
         * @param string|bool $module_type
         *
         * @return string
         */
        private function get_module_root_dir_path( $module_type = false ) {
            $is_plugin = empty( $module_type ) ?
                $this->is_plugin() :
                ( WP_FS__MODULE_TYPE_PLUGIN === $module_type );

            return fs_normalize_path( trailingslashit( $is_plugin ?
                WP_PLUGIN_DIR :
                get_theme_root( get_stylesheet() ) ) );
        }

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @param number $module_id
         * @param string $slug
         *
         * @return string Since 2.5.0 return the module's main file path.
         *
         * @since  1.2.2
         */
        private function store_id_slug_type_path_map( $module_id, $slug ) {
            $id_slug_type_path_map = self::$_accounts->get_option( 'id_slug_type_path_map', array() );

            $store_option = false;

            if ( ! isset( $id_slug_type_path_map[ $module_id ] ) ) {
                $id_slug_type_path_map[ $module_id ] = array(
                    'slug' => $slug
                );

                $store_option = true;
            } else if (
                isset( $id_slug_type_path_map[ $module_id ]['slug'] ) &&
                $slug !== $id_slug_type_path_map[ $module_id ]['slug']
            ) {
                $id_slug_type_path_map[ $module_id ]['slug'] = $slug;
                $store_option                                = true;
            }

            $find_caller = empty( $id_slug_type_path_map[ $module_id ]['path'] );

            if ( ! $find_caller ) {
                /**
                 * This verification is for cases when suddenly the same module
                 * is installed but with a different folder name.
                 *
                 * @author Vova Feldman (@svovaf)
                 * @since  1.2.3
                 */
                $find_caller = ! file_exists( $this->get_absolute_path(
                    $id_slug_type_path_map[ $module_id ]['path'],
                    $id_slug_type_path_map[ $module_id ]['type']
                ) );
            }

            foreach ( $id_slug_type_path_map as $id => $data ) {
                if ( empty( $id ) ) {
                    // Remove maps with empty module ID.
                    unset( $id_slug_type_path_map[ $id ] );
                    $store_option = true;
                    continue;
                }

                /**
                 * If the module's main file path is identical to the main file path of another module then it means that the cached path of the current module or the other one with the same path is wrong, and therefore, we need to recalculate those paths.
                 *
                 * @author Vova Feldman (@svovaf)
                 * @since  2.5.0
                 */
                if ( ! $find_caller ) {
                    if ( $id == $module_id ) {
                        continue;
                    }

                    if (
                        isset( $data['path'] ) &&
                        $data['path'] === $id_slug_type_path_map[ $module_id ]['path']
                    ) {
                        $find_caller = true;
                    }
                }
            }

            if ( $find_caller ) {
                $caller_main_file_and_type = $this->get_caller_main_file_and_type( $module_id );

                $id_slug_type_path_map[ $module_id ]['type'] = $caller_main_file_and_type->module_type;
                $id_slug_type_path_map[ $module_id ]['path'] = $caller_main_file_and_type->path;

                $store_option = true;
            }

            if ( $store_option ) {
                self::$_accounts->set_option( 'id_slug_type_path_map', $id_slug_type_path_map, true );
            }

            return $id_slug_type_path_map[ $module_id ]['path'];
        }

        /**
         * Identifies the caller type: plugin or theme.
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.2.2
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.3 Find the earliest module in the call stack that calls to the SDK. This fix is for cases when
         *         add-ons are relying on loading the SDK from the parent module, and also allows themes including the
         *         SDK an internal file instead of directly from functions.php.
         * @since  1.2.1.7 Knows how to handle cases when an add-on includes the parent module logic.
         *
         * @param number $module_id @since 2.5.0
         */
        private function get_caller_main_file_and_type( $module_id ) {
            self::require_plugin_essentials();

            $all_plugins       = fs_get_plugins( true );
            $all_plugins_paths = array();

            // Get active plugin's main files real full names (might be symlinks).
            foreach ( $all_plugins as $relative_path => $data ) {
                if ( false === strpos( fs_normalize_path( $relative_path ), '/' ) ) {
                    /**
                     * Ignore plugins that don't have a folder (e.g. Hello Dolly) since they
                     * can't really include the SDK.
                     *
                     * @author Vova Feldman
                     * @since  1.2.1.7
                     */
                    continue;
                }

                $all_plugins_paths[] = fs_normalize_path( realpath( WP_PLUGIN_DIR . '/' . $relative_path ) );
            }

            $caller_file_candidate = false;
            $caller_map            = array();
            $module_type           = WP_FS__MODULE_TYPE_PLUGIN;
            $themes_dir            = fs_normalize_path( get_theme_root( get_stylesheet() ) );
            $plugin_dir_to_skip    = false;

            for ( $i = 1, $bt = debug_backtrace(), $len = count( $bt ); $i < $len; $i ++ ) {
                if ( empty( $bt[ $i ]['file'] ) ) {
                    continue;
                }

                if ( $i > 1 && ! empty( $bt[ $i - 1 ]['file'] ) && $bt[ $i ]['file'] === $bt[ $i - 1 ]['file'] ) {
                    // If file same as the prev file in the stack, skip it.
                    continue;
                }

                if ( ! empty( $bt[ $i ]['function'] ) && in_array( $bt[ $i ]['function'], array(
                        'do_action',
                        'apply_filter',
                        // The string split is stupid, but otherwise, theme check
                        // throws info notices.
                        'requir' . 'e_once',
                        'requir' . 'e',
                        'includ' . 'e_once',
                        'includ' . 'e',
                        'install_and_activate_plugin',
                        'try_activate_plugin',
                        'activate_plugin'
                    ) )
                ) {
                    if ( 'activate_plugin' === $bt[ $i ]['function'] ) {
                        /**
                         * Store the directory of the activator plugin so that any other file that starts with it
                         * cannot be mistakenly chosen as a candidate caller file.
                         *
                         * @author Leo Fajardo
                         *
                         * @since 2.3.0
                         */
                        $caller_file_path = fs_normalize_path( $bt[ $i ]['file'] );

                        foreach ( $all_plugins_paths as $plugin_path ) {
                            $plugin_dir = fs_normalize_path( dirname( $plugin_path ) . '/' );
                            if ( false !== strpos( $caller_file_path, $plugin_dir ) ) {
                                $plugin_dir_to_skip = $plugin_dir;

                                break;
                            }
                        }
                    }

                    // Ignore call stack hooks and files inclusion.
                    continue;
                }

                $caller_file_path = fs_normalize_path( $bt[ $i ]['file'] );

                if ( ! empty( $plugin_dir_to_skip ) ) {
                    /**
                     * Skip if it's an activator plugin file to avoid mistakenly choosing it as a candidate caller file.
                     *
                     * @author Leo Fajardo
                     *
                     * @since 2.3.0
                     */
                    if ( 0 === strpos( $caller_file_path, $plugin_dir_to_skip ) ) {
                        continue;
                    }
                }

                if ( 'functions.php' === basename( $caller_file_path ) ) {
                    /**
                     * 1. Assumes that theme's starting execution file is functions.php.
                     * 2. This complex logic fixes symlink issues (e.g. with Vargant).
                     *
                     * @author Vova Feldman (@svovaf)
                     * @since  1.2.2.5
                     */

                    if ( $caller_file_path == fs_normalize_path( realpath( trailingslashit( $themes_dir ) . basename( dirname( $caller_file_path ) ) . '/' . basename( $caller_file_path ) ) ) ) {
                        $module_type = WP_FS__MODULE_TYPE_THEME;

                        /**
                         * Relative path of the theme, e.g.:
                         * `my-theme/functions.php`
                         *
                         * @author Leo Fajardo (@leorw)
                         */
                        $caller_file_candidate = basename( dirname( $caller_file_path ) ) .
                                                 '/' .
                                                 basename( $caller_file_path );

                        continue;
                    }
                }

                $caller_file_hash = md5( $caller_file_path );

                if ( ! isset( $caller_map[ $caller_file_hash ] ) ) {
                    foreach ( $all_plugins_paths as $plugin_path ) {
                        if ( empty( $plugin_path ) ) {
                            continue;
                        }

                        if ( false !== strpos( $caller_file_path, fs_normalize_path( dirname( $plugin_path ) . '/' ) ) ) {
                            $caller_map[ $caller_file_hash ] = fs_normalize_path( $plugin_path );
                            break;
                        }
                    }
                }

                if ( isset( $caller_map[ $caller_file_hash ] ) ) {
                    $module_type           = WP_FS__MODULE_TYPE_PLUGIN;
                    $caller_file_candidate = plugin_basename( $caller_map[ $caller_file_hash ] );
                }
            }

            $caller_main_file_and_type = (object) array(
                'module_type' => $module_type,
                'path'        => $caller_file_candidate
            );

            return apply_filters( "fs_{$module_id}_caller_main_file_and_type", $caller_main_file_and_type );
        }

        #----------------------------------------------------------------------------------
        #region Deactivation Feedback Form
        #----------------------------------------------------------------------------------

        /**
         * Displays a confirmation and feedback dialog box when the user clicks on the "Deactivate" link on the plugins
         * page.
         *
         * @author Vova Feldman (@svovaf)
         * @author Leo Fajardo (@leorw)
         *
         * @since  1.1.2
         */
        function _add_deactivation_feedback_dialog_box() {
            if (
                $this->is_clone() ||
                ( is_object( $this->_site ) && ! $this->is_registered() )
            ) {
                return;
            }
            
            $subscription_cancellation_dialog_box_template_params = $this->apply_filters( 'show_deactivation_subscription_cancellation', true ) ?
                $this->_get_subscription_cancellation_dialog_box_template_params() :
                array();

            /**
             * @since 2.3.0 Developers can optionally hide the deactivation feedback form using the 'show_deactivation_feedback_form' filter.
             */
            $show_deactivation_feedback_form = ! self::is_deactivation_snoozed();
            if ( $this->has_filter( 'show_deactivation_feedback_form' ) ) {
                $show_deactivation_feedback_form = $this->apply_filters( 'show_deactivation_feedback_form', true );
            } else if ( $this->is_addon() ) {
                /**
                 * If the add-on's 'show_deactivation_feedback_form' is not set, try to inherit the value from the parent.
                 */
                $show_deactivation_feedback_form = $this->get_parent_instance()->apply_filters( 'show_deactivation_feedback_form', true );
            }

            $uninstall_confirmation_message = $this->apply_filters( 'uninstall_confirmation_message', '' );

            if (
                empty( $subscription_cancellation_dialog_box_template_params )  &&
                ! $show_deactivation_feedback_form &&
                empty( $uninstall_confirmation_message )
            ) {
                return;
            }

            $vars = array( 'id' => $this->_module_id );

            if ( $show_deactivation_feedback_form ) {
                /* Check the type of user:
                 * 1. Long-term (long-term)
                 * 2. Non-registered and non-anonymous short-term (non-registered-and-non-anonymous-short-term).
                 * 3. Short-term (short-term)
                 */
                $is_long_term_user = true;

                // Check if the site is at least 2 days old.
                $time_installed = $this->_storage->install_timestamp;

                // Difference in seconds.
                $date_diff = time() - $time_installed;

                // Convert seconds to days.
                $date_diff_days = floor( $date_diff / ( 60 * 60 * 24 ) );

                if ( $date_diff_days < 2 ) {
                    $is_long_term_user = false;
                }

                $is_long_term_user = $this->apply_filters( 'is_long_term_user', $is_long_term_user );

                if ( $is_long_term_user ) {
                    $user_type = 'long-term';
                } else {
                    if ( ! $this->is_registered() && ! $this->is_anonymous() ) {
                        $user_type = 'non-registered-and-non-anonymous-short-term';
                    } else {
                        $user_type = 'short-term';
                    }
                }

                $uninstall_reasons = $this->_get_uninstall_reasons( $user_type );

                $vars['reasons'] = $uninstall_reasons;
            }

            $vars['subscription_cancellation_dialog_box_template_params'] = &$subscription_cancellation_dialog_box_template_params;
            $vars['show_deactivation_feedback_form']                      = $show_deactivation_feedback_form;
            $vars['uninstall_confirmation_message']                       = $uninstall_confirmation_message;

            /**
             * Load the HTML template for the deactivation feedback dialog box.
             *
             * @todo Deactivation form core functions should be loaded only once! Otherwise, when there are multiple Freemius powered plugins the same code is loaded multiple times. The only thing that should be loaded differently is the various deactivation reasons object based on the state of the plugin.
             */
            fs_require_template( 'forms/deactivation/form.php', $vars );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.1.2
         *
         * @param string $user_type
         *
         * @return array The uninstall reasons for the specified user type.
         */
        function _get_uninstall_reasons( $user_type = 'long-term' ) {
            $module_type = $this->_module_type;

            $internal_message_template_var = array(
                'id' => $this->_module_id
            );

            $plan = $this->get_plan();

            if ( $this->is_registered() && is_object( $plan ) && $plan->has_technical_support() ) {
                $contact_support_template = fs_get_template( 'forms/deactivation/contact.php', $internal_message_template_var );
            } else {
                $contact_support_template = '';
            }

            $reason_found_better_plugin = array(
                'id'                => self::REASON_FOUND_A_BETTER_PLUGIN,
                'text'              => sprintf( $this->get_text_inline( 'I found a better %s', 'reason-found-a-better-plugin' ), $module_type ),
                'input_type'        => 'textfield',
                'input_placeholder' => sprintf( $this->get_text_inline( "What's the %s's name?", 'placeholder-plugin-name' ), $module_type ),
            );

            $reason_temporary_deactivation = array(
                'id'                => self::REASON_TEMPORARY_DEACTIVATION,
                'text'              => sprintf(
                    $this->get_text_inline( "It's a temporary %s - I'm troubleshooting an issue", 'reason-temporary-x' ),
                    strtolower( $this->is_plugin() ?
                        $this->get_text_inline( 'Deactivation', 'deactivation' ) :
                        $this->get_text_inline( 'Theme Switch', 'theme-switch' )
                    )
                ),
                'input_type'        => '',
                'input_placeholder' => ''
            );

            $reason_other = array(
                'id'                => self::REASON_OTHER,
                'text'              => $this->get_text_inline( 'Other', 'reason-other' ),
                'input_type'        => 'textfield',
                'input_placeholder' => ''
            );

            $long_term_user_reasons = array(
                array(
                    'id'                => self::REASON_NO_LONGER_NEEDED,
                    'text'              => sprintf( $this->get_text_inline( 'I no longer need the %s', 'reason-no-longer-needed' ), $module_type ),
                    'input_type'        => '',
                    'input_placeholder' => ''
                ),
                $reason_found_better_plugin,
                array(
                    'id'                => self::REASON_NEEDED_FOR_A_SHORT_PERIOD,
                    'text'              => sprintf( $this->get_text_inline( 'I only needed the %s for a short period', 'reason-needed-for-a-short-period' ), $module_type ),
                    'input_type'        => '',
                    'input_placeholder' => ''
                ),
                array(
                    'id'                => self::REASON_BROKE_MY_SITE,
                    'text'              => sprintf( $this->get_text_inline( 'The %s broke my site', 'reason-broke-my-site' ), $module_type ),
                    'input_type'        => '',
                    'input_placeholder' => '',
                    'internal_message'  => $contact_support_template
                ),
                array(
                    'id'                => self::REASON_SUDDENLY_STOPPED_WORKING,
                    'text'              => sprintf( $this->get_text_inline( 'The %s suddenly stopped working', 'reason-suddenly-stopped-working' ), $module_type ),
                    'input_type'        => '',
                    'input_placeholder' => '',
                    'internal_message'  => $contact_support_template
                )
            );

            if ( $this->is_paying() ) {
                $long_term_user_reasons[] = array(
                    'id'                => self::REASON_CANT_PAY_ANYMORE,
                    'text'              => $this->get_text_inline( "I can't pay for it anymore", 'reason-cant-pay-anymore' ),
                    'input_type'        => 'textfield',
                    'input_placeholder' => $this->get_text_inline( 'What price would you feel comfortable paying?', 'placeholder-comfortable-price' )
                );
            }

            $reason_dont_share_info = array(
                'id'                => self::REASON_DONT_LIKE_TO_SHARE_MY_INFORMATION,
                'text'              => $this->get_text_inline( "I don't like to share my information with you", 'reason-dont-like-to-share-my-information' ),
                'input_type'        => '',
                'input_placeholder' => ''
            );

            /**
             * If the current user has selected the "don't share data" reason in the deactivation feedback modal, inform the
             * user by showing additional message that he doesn't have to share data and can just choose to skip the opt-in
             * (the Skip button is included in the message to show). This message will only be shown if anonymous mode is
             * enabled and the user's account is currently not in pending activation state (similar to the way the Skip
             * button in the opt-in form is shown/hidden).
             */
            if ( $this->is_enable_anonymous() && ! $this->is_pending_activation() ) {
                $reason_dont_share_info['internal_message'] = fs_get_template( 'forms/deactivation/retry-skip.php', $internal_message_template_var );
            }

            $uninstall_reasons = array(
                'long-term'                                   => $long_term_user_reasons,
                'non-registered-and-non-anonymous-short-term' => array(
                    array(
                        'id'                => self::REASON_DIDNT_WORK,
                        'text'              => sprintf( $this->get_text_inline( "The %s didn't work", 'reason-didnt-work' ), $module_type ),
                        'input_type'        => '',
                        'input_placeholder' => ''
                    ),
                    $reason_dont_share_info,
                    $reason_found_better_plugin
                ),
                'short-term'                                  => array(
                    array(
                        'id'                => self::REASON_COULDNT_MAKE_IT_WORK,
                        'text'              => $this->get_text_inline( "I couldn't understand how to make it work", 'reason-couldnt-make-it-work' ),
                        'input_type'        => '',
                        'input_placeholder' => '',
                        'internal_message'  => $contact_support_template
                    ),
                    $reason_found_better_plugin,
                    array(
                        'id'                => self::REASON_GREAT_BUT_NEED_SPECIFIC_FEATURE,
                        'text'              => sprintf( $this->get_text_inline( "The %s is great, but I need specific feature that you don't support", 'reason-great-but-need-specific-feature' ), $module_type ),
                        'input_type'        => 'textarea',
                        'input_placeholder' => $this->get_text_inline( 'What feature?', 'placeholder-feature' )
                    ),
                    array(
                        'id'                => self::REASON_NOT_WORKING,
                        'text'              => sprintf( $this->get_text_inline( 'The %s is not working', 'reason-not-working' ), $module_type ),
                        'input_type'        => 'textarea',
                        'input_placeholder' => $this->get_text_inline( "Kindly share what didn't work so we can fix it for future users...", 'placeholder-share-what-didnt-work' )
                    ),
                    array(
                        'id'                => self::REASON_NOT_WHAT_I_WAS_LOOKING_FOR,
                        'text'              => $this->get_text_inline( "It's not what I was looking for", 'reason-not-what-i-was-looking-for' ),
                        'input_type'        => 'textarea',
                        'input_placeholder' => $this->get_text_inline( "What you've been looking for?", 'placeholder-what-youve-been-looking-for' )
                    ),
                    array(
                        'id'                => self::REASON_DIDNT_WORK_AS_EXPECTED,
                        'text'              => sprintf( $this->get_text_inline( "The %s didn't work as expected", 'reason-didnt-work-as-expected' ), $module_type ),
                        'input_type'        => 'textarea',
                        'input_placeholder' => $this->get_text_inline( 'What did you expect?', 'placeholder-what-did-you-expect' )
                    )
                )
            );

            // Randomize the reasons for the current user type.
            shuffle( $uninstall_reasons[ $user_type ] );

            // Keep the following reasons as the last items in the list.
            $uninstall_reasons[ $user_type ][] = $reason_temporary_deactivation;
            $uninstall_reasons[ $user_type ][] = $reason_other;

            $uninstall_reasons = $this->apply_filters( 'uninstall_reasons', $uninstall_reasons );

            return $uninstall_reasons[ $user_type ];
        }

        /**
         * Called after the user has submitted his reason for deactivating the plugin.
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.1.2
         */
        function _submit_uninstall_reason_action() {
            $this->_logger->entrance();

            $this->check_ajax_referer( 'submit_uninstall_reason' );

            $reason_id = fs_request_get( 'reason_id' );

            // Check if the given reason ID is an unsigned integer.
            if ( ! ctype_digit( $reason_id ) ) {
                exit;
            }

            $reason_info = trim( fs_request_get( 'reason_info', '' ) );
            if ( ! empty( $reason_info ) ) {
                $reason_info = substr( $reason_info, 0, 128 );
            }

            $reason = (object) array(
                'id'           => $reason_id,
                'info'         => $reason_info,
                'is_anonymous' => fs_request_get_bool( 'is_anonymous' )
            );

            $this->_storage->store( 'uninstall_reason', $reason );

            if ( self::REASON_TEMPORARY_DEACTIVATION == $reason->id ) {
                $snooze_period = fs_request_get( 'snooze_period' );

                if ( is_numeric( $snooze_period ) && 0 < $snooze_period ) {
                    self::snooze_deactivation_form( (int) $snooze_period );
                }
            }

            /**
             * If the module type is "theme", trigger the uninstall event here (on theme deactivation) since themes do
             * not support uninstall hook.
             *
             * @author Leo Fajardo (@leorw)
             * @since  1.2.2
             */
            if ( $this->is_theme() ) {
                if ( $this->is_premium() && ! $this->has_active_valid_license() ) {
                    FS_Plugin_Updater::instance( $this )->delete_update_data();
                }

                $this->_uninstall_plugin_event( false );
                $this->remove_sdk_reference();
            }

            // Print '1' for successful operation.
            echo 1;
            exit;
        }

        #--------------------------------------------------------------------------------
        #region Deactivation Feedback Snoozing
        #--------------------------------------------------------------------------------

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.4.3
         *
         * @param int $period
         *
         * @return bool True if the value was set, false otherwise.
         */
        private static function snooze_deactivation_form( $period ) {
            return ( 0 < $period && self::reset_deactivation_snoozing( $period ) );
        }

        /**
         * Check if deactivation feedback form is snoozed.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.4.3
         *
         * @return bool
         */
        static function is_deactivation_snoozed() {
            $is_snoozed = ( ! is_multisite() || fs_is_network_admin() ) ?
                get_transient( 'fs_snooze_period' ) :
                get_site_transient( 'fs_snooze_period' );


            return ( 'true' === $is_snoozed );
        }

        /**
         * Reset deactivation snoozing. When `$period` is `0` will stop deactivation snoozing by deleting the transients. Otherwise, will set the transients for the selected period.
         *
         * @param int $period Period in seconds.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.4.3
         */
        public static function reset_deactivation_snoozing( $period = 0 ) {
            $value = ( 0 === $period ) ? null : 'true';

            if ( ! is_multisite() || fs_is_network_admin() ) {
                return set_transient( 'fs_snooze_period', $value, $period );
            } else {
                return set_site_transient( 'fs_snooze_period', $value, $period );
            }
        }

        /**
         * The deactivation snooze expiration UNIX timestamp (in sec).
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.4.3
         *
         * @return int
         */
        static function deactivation_snooze_expires_at() {
            return ( ! is_multisite() || fs_is_network_admin() ) ?
                (int) get_option( '_transient_timeout_fs_snooze_period' ) :
                (int) get_site_option( '_site_transient_timeout_fs_snooze_period' );
        }

        #endregion

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.1.4
         */
        function cancel_subscription_or_trial_ajax_action() {
            $this->_logger->entrance();

            $this->check_ajax_referer( 'cancel_subscription_or_trial' );

            $result = $this->cancel_subscription_or_trial( fs_request_get( 'plugin_id', $this->get_id() ), false );

            if ( $this->is_api_error( $result ) ) {
                $this->shoot_ajax_failure( $result->error->message );
            }

            $this->shoot_ajax_success();
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.1.4
         *
         * @param number $plugin_id
         *
         * @return object
         */
        private function cancel_subscription_or_trial( $plugin_id ) {
            $fs = null;
            if ( $plugin_id == $this->get_id() ) {
                $fs = $this;
            } else if ( $this->is_addon_activated( $plugin_id ) ) {
                $fs = self::get_instance_by_id( $plugin_id );
            }

            $result = null;

            if ( ! is_null( $fs ) ) {
                $result = $fs->is_paid_trial() ?
                    $fs->_cancel_trial() :
                    $fs->_downgrade_site();
            }

            return $result;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.0.2
         */
        function _delete_theme_update_data_action() {
            FS_Plugin_Updater::instance( $this )->delete_update_data();
        }

        #endregion

        #----------------------------------------------------------------------------------
        #region Instance
        #----------------------------------------------------------------------------------

        /**
         * Main singleton instance.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.0
         *
         * @param  number      $module_id
         * @param  string|bool $slug
         * @param  bool        $is_init Is initiation sequence.
         *
         * @return Freemius|false
         */
        static function instance( $module_id, $slug = false, $is_init = false ) {
            if ( empty( $module_id ) ) {
                return false;
            }

            /**
             * Load the essential static data prior to initiating FS_Plugin_Manager since there's an essential MS network migration logic that needs to be executed prior to the initiation.
             */
            self::_load_required_static();

            if ( ! is_numeric( $module_id ) ) {
                if ( ! $is_init && true === $slug ) {
                    $is_init = true;
                }

                $slug = $module_id;

                $module = FS_Plugin_Manager::instance( $slug )->get();

                if ( is_object( $module ) ) {
                    $module_id = $module->id;
                }
            }

            $key = 'm_' . $module_id;

            if ( ! isset( self::$_instances[ $key ] ) ) {
                self::$_instances[ $key ] = new Freemius( $module_id, $slug, $is_init );
            }

            return self::$_instances[ $key ];
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param number $addon_id
         *
         * @return bool
         */
        private static function has_instance( $addon_id ) {
            return isset( self::$_instances[ 'm_' . $addon_id ] );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.2
         *
         * @param  string|number $id_or_slug
         * @param  string        $module_type
         *
         * @return number|false
         */
        private static function get_module_id( $id_or_slug, $module_type = WP_FS__MODULE_TYPE_PLUGIN ) {
            if ( is_numeric( $id_or_slug ) ) {
                return $id_or_slug;
            }

            foreach ( self::$_instances as $instance ) {
                // Also check the module type since there can be a plugin and a theme with the same slug.
                if ( ( $module_type === $instance->get_module_type() ) && ( $id_or_slug === $instance->get_slug() ) ) {
                    return $instance->get_id();
                }
            }

            return false;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param number $id
         *
         * @return false|Freemius
         */
        static function get_instance_by_id( $id ) {
            return isset ( self::$_instances[ 'm_' . $id ] ) ?
                self::$_instances[ 'm_' . $id ] :
                false;
        }

        /**
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         *
         * @param string $plugin_file
         * @param string $module_type
         *
         * @return false|Freemius
         */
        static function get_instance_by_file( $plugin_file, $module_type = WP_FS__MODULE_TYPE_PLUGIN ) {
            $slug = self::find_slug_by_basename( $plugin_file );

            return ( false !== $slug ) ?
                self::instance( self::get_module_id( $slug, $module_type ) ) :
                false;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @return false|Freemius
         */
        function get_parent_instance() {
            return self::get_instance_by_id( $this->_plugin->parent_plugin_id );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param  string|number $id_or_slug
         *
         * @return false|Freemius
         */
        function get_addon_instance( $id_or_slug ) {
            $addon_id = self::get_module_id( $id_or_slug );

            return self::instance( $addon_id );
        }

        /**
         * @return Freemius[]
         */
        static function _get_all_instances() {
            return self::$_instances;
        }

        #endregion ------------------------------------------------------------------

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @return bool
         */
        function is_parent_plugin_installed() {
            $is_active = self::has_instance( $this->_plugin->parent_plugin_id );

            if ( $is_active ) {
                return true;
            }

            /**
             * Parent module might be a theme. If that's the case, the add-on's FS
             * instance will be loaded prior to the theme's FS instance, therefore,
             * we need to check if it's active with a "look ahead".
             *
             * @author Vova Feldman
             * @since  1.2.2.3
             */
            global $fs_active_plugins;
            if ( is_object( $fs_active_plugins ) && is_array( $fs_active_plugins->plugins ) ) {
                $active_theme = wp_get_theme();

                foreach ( $fs_active_plugins->plugins as $sdk => $module ) {
                    if ( WP_FS__MODULE_TYPE_THEME === $module->type ) {
                        if ( $module->plugin_path == $active_theme->get_stylesheet() ) {
                            // Parent module is a theme and it's currently active.
                            return true;
                        }
                    }
                }
            }

            return false;
        }

        /**
         * Check if add-on parent plugin in activation mode.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.7
         *
         * @return bool
         */
        function is_parent_in_activation() {
            $parent_fs = $this->get_parent_instance();
            if ( ! is_object( $parent_fs ) ) {
                return false;
            }

            return ( $parent_fs->is_activation_mode() );
        }

        /**
         * Is plugin in activation mode.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.7
         *
         * @param bool $and_on
         *
         * @return bool
         */
        function is_activation_mode( $and_on = true ) {
            return fs_is_network_admin() ?
                $this->is_network_activation_mode( $and_on ) :
                $this->is_site_activation_mode( $and_on );
        }

        /**
         * Is plugin in activation mode.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.7
         *
         * @param bool $and_on
         *
         * @return bool
         */
        function is_site_activation_mode( $and_on = true ) {
            return (
                ( $this->is_on() || ! $and_on ) &&
                (
                    ( $this->is_premium() && true === $this->_storage->require_license_activation ) ||
                    (
                        ( ! $this->is_registered() ||
                            ( $this->is_only_premium() && ! $this->has_features_enabled_license() ) ) &&
                        ( ! $this->is_enable_anonymous() ||
                            ( ! $this->is_anonymous() && ! $this->is_pending_activation() ) )
                    )
                )
            );
        }

        /**
         * Checks if the SDK in network activation mode.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.0.0
         *
         * @param bool $and_on
         *
         * @return bool
         */
        private function is_network_activation_mode( $and_on = true ) {
            if ( ! $this->_is_network_active ) {
                // Not network activated.
                return false;
            }

            if ( $this->is_network_upgrade_mode() ) {
                // Special flag to enforce network activation mode to decide what to do with the sites that are not yet opted-in nor skipped.
                return true;
            }

            if ( ! $this->is_site_activation_mode( $and_on ) ) {
                // Whether the context is single site or the network, if the plugin is no longer in activation mode then it is not in network activation mode as well.
                return false;
            }

            if ( $this->is_network_delegated_connection() ) {
                // Super-admin delegated the connection to the site admins -> not activation mode.
                return false;
            }

            if ( $this->is_network_anonymous() && true !== $this->_storage->require_license_activation ) {
                // Super-admin skipped the connection network wide -> not activation mode.
                return false;
            }

            if ( $this->is_network_registered() ) {
                // Super-admin connected at least one site -> not activation mode.
                return false;
            }

            return true;
        }

        /**
         * Check if current page is the opt-in/pending-activation page.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.7
         *
         * @return bool
         */
        function is_activation_page() {
            if ( $this->_menu->is_activation_page( $this->show_opt_in_on_themes_page() ) ) {
                return true;
            }

            if ( ! $this->is_activation_mode() ) {
                return false;
            }

            // Check if current page is matching the activation page.
            return $this->is_matching_url( $this->get_activation_url() );
        }

        /**
         * Check if URL path's are matching and that all querystring
         * arguments of the $sub_url exist in the $url with the same values.
         *
         * WARNING:
         *  1. This method doesn't check if the sub/domain are matching.
         *  2. Ignore case sensitivity.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.7
         *
         * @param string $sub_url
         * @param string $url If argument is not set, check if the sub_url matching the current's page URL.
         *
         * @return bool
         */
        private function is_matching_url( $sub_url, $url = '' ) {
            if ( empty( $url ) ) {
                $url = $_SERVER['REQUEST_URI'];
            }

            $url     = strtolower( $url );
            $sub_url = strtolower( $sub_url );

            if ( parse_url( $sub_url, PHP_URL_PATH ) !== parse_url( $url, PHP_URL_PATH ) ) {
                // Different path - DO NOT OVERRIDE PAGE.
                return false;
            }

            $url_params     = fs_parse_url_params( $url );
            $sub_url_params = fs_parse_url_params( $sub_url );

            foreach ( $sub_url_params as $key => $val ) {
                if ( ! isset( $url_params[ $key ] ) || $val != $url_params[ $key ] ) {
                    // Not matching query string - DO NOT OVERRIDE PAGE.
                    return false;
                }
            }

            return true;
        }

        /**
         * Get the basenames of all active plugins for specific blog. Including network activated plugins.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int $blog_id
         *
         * @return string[]
         */
        private static function get_active_plugins_basenames( $blog_id = 0 ) {
            if ( is_multisite() && $blog_id > 0 ) {
                $active_basenames = get_blog_option( $blog_id, 'active_plugins' );
            } else {
                $active_basenames = get_option( 'active_plugins' );
            }

            if ( ! is_array( $active_basenames ) ) {
                $active_basenames = array();
            }

            if ( is_multisite() ) {
                $network_active_basenames = get_site_option( 'active_sitewide_plugins' );

                if ( is_array( $network_active_basenames ) && ! empty( $network_active_basenames ) ) {
                    $active_basenames = array_merge( $active_basenames, array_keys( $network_active_basenames ) );
                }
            }

            return $active_basenames;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @param int $blog_id
         *
         * @return array
         */
        static function get_active_plugins_directories_map( $blog_id = 0 ) {
            $active_basenames = self::get_active_plugins_basenames( $blog_id );

            $map = array();

            foreach ( $active_basenames as $active_basename ) {
                $active_basename = fs_normalize_path( $active_basename );

                if ( false === strpos( $active_basename, '/' ) ) {
                    continue;
                }

                $map[ dirname( $active_basename ) ] = true;
            }

            return $map;
        }

        /**
         * Get collection of all active plugins. Including network activated plugins.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @param int $blog_id Since 2.0.0
         *
         * @return array[string]array
         */
        private static function get_active_plugins( $blog_id = 0 ) {
            self::require_plugin_essentials();

            $active_plugin            = array();
            $all_plugins              = fs_get_plugins();
            $active_plugins_basenames = self::get_active_plugins_basenames( $blog_id );

            foreach ( $active_plugins_basenames as $plugin_basename ) {
                $active_plugin[ $plugin_basename ] = $all_plugins[ $plugin_basename ];
            }

            return $active_plugin;
        }

        /**
         * Get collection of all site active plugins for a specified blog.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int $blog_id
         *
         * @return array[string]array
         */
        private static function get_site_active_plugins( $blog_id = 0 ) {
            $active_basenames = ( is_multisite() && $blog_id > 0 ) ?
                get_blog_option( $blog_id, 'active_plugins' ) :
                get_option( 'active_plugins' );

            $active = array();

            if ( ! is_array( $active_basenames ) ) {
                return $active;
            }

            foreach ( $active_basenames as $basename ) {
                $active[ $basename ] = array(
                    'is_active' => true,
                    'Version'   => '1.0', // Dummy version.
                    'slug'      => self::get_plugin_slug( $basename ),
                );
            }

            return $active;
        }

        /**
         * Get collection of all plugins with their activation status for a specified blog.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.8
         *
         * @param int $blog_id Since 2.0.0
         *
         * @return array Key is the plugin file path and the value is an array of the plugin data.
         */
        private static function get_all_plugins( $blog_id = 0 ) {
            self::require_plugin_essentials();

            $all_plugins = fs_get_plugins();

            $active_plugins_basenames = self::get_active_plugins_basenames( $blog_id );

            foreach ( $all_plugins as $basename => &$data ) {
                // By default set to inactive (next foreach update the active plugins).
                $data['is_active'] = false;
                // Enrich with plugin slug.
                $data['slug'] = self::get_plugin_slug( $basename );
            }

            // Flag active plugins.
            foreach ( $active_plugins_basenames as $basename ) {
                if ( isset( $all_plugins[ $basename ] ) ) {
                    $all_plugins[ $basename ]['is_active'] = true;
                }
            }

            return $all_plugins;
        }

        /**
         * Get collection of all plugins and if they are network level activated.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return array Key is the plugin basename and the value is an array of the plugin data.
         */
        private static function get_network_plugins() {
            self::require_plugin_essentials();

            $all_plugins = fs_get_plugins();

            $network_active_basenames = is_multisite() ?
                get_site_option( 'active_sitewide_plugins' ) :
                array();

            foreach ( $all_plugins as $basename => &$data ) {
                // By default set to inactive (next foreach update the active plugins).
                $data['is_active'] = false;
                // Enrich with plugin slug.
                $data['slug'] = self::get_plugin_slug( $basename );
            }

            // Flag active plugins.
            foreach ( $network_active_basenames as $basename ) {
                if ( isset( $all_plugins[ $basename ] ) ) {
                    $all_plugins[ $basename ]['is_active'] = true;
                }
            }

            return $all_plugins;
        }

        /**
         * Cached result of get_site_transient( 'update_plugins' )
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.8
         *
         * @var object
         */
        private static $_plugins_info;

        /**
         * Helper function to get specified plugin's slug.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.8
         *
         * @param $basename
         *
         * @return string
         */
        private static function get_plugin_slug( $basename ) {
            if ( ! isset( self::$_plugins_info ) ) {
                self::$_plugins_info = get_site_transient( 'update_plugins' );
            }

            $slug = '';

            if ( is_object( self::$_plugins_info ) ) {
                if ( isset( self::$_plugins_info->no_update ) &&
                     isset( self::$_plugins_info->no_update[ $basename ] ) &&
                     ! empty( self::$_plugins_info->no_update[ $basename ]->slug )
                ) {
                    $slug = self::$_plugins_info->no_update[ $basename ]->slug;
                } else if ( isset( self::$_plugins_info->response ) &&
                            isset( self::$_plugins_info->response[ $basename ] ) &&
                            ! empty( self::$_plugins_info->response[ $basename ]->slug )
                ) {
                    $slug = self::$_plugins_info->response[ $basename ]->slug;
                }
            }

            if ( empty( $slug ) ) {
                // Try to find slug from FS data.
                $slug = self::find_slug_by_basename( $basename );
            }

            if ( empty( $slug ) ) {
                // Fallback to plugin's folder name.
                $slug = dirname( $basename );
            }

            return $slug;
        }

        private static $_statics_loaded = false;

        /**
         * Load static resources.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         */
        private static function _load_required_static() {
            if ( self::$_statics_loaded ) {
                return;
            }

            self::$_static_logger = FS_Logger::get_logger( WP_FS__SLUG, WP_FS__DEBUG_SDK, WP_FS__ECHO_DEBUG_SDK );

            self::$_static_logger->entrance();

            self::$_accounts = FS_Options::instance( WP_FS__ACCOUNTS_OPTION_NAME, true );

            if ( is_multisite() ) {
                $has_skipped_migration = (
                    // 'id_slug_type_path_map' - was never stored on older versions, therefore, not exists on the site level.
                    null === self::$_accounts->get_option( 'id_slug_type_path_map', null, false ) &&
                    // 'file_slug_map' stored on the site level, so it was running an SDK version before it was integrated with MS-network.
                    null !== self::$_accounts->get_option( 'file_slug_map', null, false )
                );

                /**
                 * If the file_slug_map exists on the site level but doesn't exist on the
                 * network level storage, it means that we need to process the storage with migration.
                 *
                 * The code in this `if` scope will only be executed once and only for the first site that will execute it because once we migrate the storage data, file_slug_map will be already set in the network level storage.
                 *
                 * @author Vova Feldman (@svovaf)
                 * @since  2.0.0
                 */
                if (
                    ( $has_skipped_migration && true !== self::$_accounts->get_option( 'ms_migration_complete', false, true ) ) ||
                    ( null === self::$_accounts->get_option( 'file_slug_map', null, true ) &&
                        null !== self::$_accounts->get_option( 'file_slug_map', null, false ) )
                ) {
                    self::migrate_options_to_network();
                }
            }

            self::$_global_admin_notices = FS_Admin_Notices::instance( 'global' );

            FS_DebugManager::load_required_static();

            if ( 0 == did_action( 'plugins_loaded' ) ) {
                add_action( 'plugins_loaded', array( 'Freemius', '_load_textdomain' ), 1 );
            }

            $clone_manager = FS_Clone_Manager::instance();
            add_action( 'init', array( $clone_manager, '_init' ) );

            add_action( 'admin_footer', array( 'Freemius', '_handle_submenu_external_link' ) );

            if ( self::is_plugins_page() || self::is_themes_page() ) {
                add_action( 'admin_print_footer_scripts', array( 'Freemius', '_maybe_add_beta_label_styles' ), 9 );

                /**
                 * Specifically use this hook so that the JS event handlers will work properly on the "Themes"
                 * page.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.3.0
                 */
                add_action( 'admin_footer-' . self::get_current_page(), array( 'Freemius', '_maybe_add_beta_label_to_plugins_and_handle_confirmation') );
            }

            self::$_statics_loaded = true;
        }

        public static function get_static_logger() {
            return self::$_static_logger;
        }

        public static function get_accounts() {
            return self::$_accounts;
        }

        #--------------------------------------------------------------------------------
        #region Clone
        #--------------------------------------------------------------------------------

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         *
         * @param bool $only_if_manual_resolution_is_not_hidden
         *
         * @return bool
         */
        private function is_unresolved_clone( $only_if_manual_resolution_is_not_hidden = false ) {
            if ( ! $this->is_clone( $only_if_manual_resolution_is_not_hidden ) ) {
                return false;
            }

            return FS_Clone_Manager::instance()->has_temporary_duplicate_mode_expired();
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         *
         * @param bool $only_if_manual_resolution_is_not_hidden
         */
        function is_clone( $only_if_manual_resolution_is_not_hidden = false ) {
            if ( ! is_object( $this->_site ) ) {
                return false;
            }

            $blog_id = null;

            if (
                fs_is_network_admin() &&
                FS_Site::is_valid_id( $this->_storage->network_install_blog_id )
            ) {
                // Ensure that we're comparing the network install's URL with the relevant subsite's URL.
                $blog_id = $this->_storage->network_install_blog_id;
            }

            $site_url = Freemius::get_unfiltered_site_url( $blog_id, true, true );

            if ( ! $this->_site->is_clone( $site_url ) ) {
                return false;
            }

            return (
                ! $only_if_manual_resolution_is_not_hidden ||
                ! FS_Clone_Manager::instance()->should_hide_manual_resolution()
            );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         *        
         * @param int|null $blog_id
         * @param bool     $strip_protocol
         * @param bool     $add_trailing_slash
         *
         * @return string
         */
        static function get_unfiltered_site_url( $blog_id = null, $strip_protocol = false, $add_trailing_slash = false ) {
            $url = ( ! is_multisite() && defined( 'WP_SITEURL' ) ) ? WP_SITEURL : self::get_site_url_from_wp_option( $blog_id );

            if ( $strip_protocol ) {
                $url = fs_strip_url_protocol( $url );
            }

            if ( $add_trailing_slash ) {
                $url = trailingslashit( $url );
            }

            return $url;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.6.0
         *
         * @param int|null $blog_id
         *
         * @return string
         */
        private static function get_site_url_from_wp_option( $blog_id = null ) {
            global $wp_filter;

            $site_url_filters = array(
                'site_url'               => null,
                'pre_option_siteurl'     => null,
                'default_option_siteurl' => null,
                'option_siteurl'         => null,
            );

            // Detach all URL-related filters to get the actual site's URL (stripped of potential manipulations by multilingual plugins).
            foreach ( $site_url_filters as $hook_name => $site_url_filter ) {
                if ( ! empty( $wp_filter[ $hook_name ] ) ) {
                    $site_url_filters[ $hook_name ] = $wp_filter[ $hook_name ];
                    unset( $wp_filter[ $hook_name ] );
                }
            }

            $url = get_site_url( $blog_id );

            // Re-attach the filters back.
            foreach ( $site_url_filters as $hook_name => $site_url_filter ) {
                if ( ! empty( $site_url_filter ) ) {
                    $wp_filter[ $hook_name ] = $site_url_filter;
                }
            }

            return $url;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         *
         * @param number $site_id
         */
        function fetch_install_by_id( $site_id ) {
            return $this->get_current_or_network_user_api_scope()->get( "/installs/{$site_id}.json" );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         *
         * @return string|object|bool
         */
        function _handle_long_term_duplicate() {
            $this->_logger->entrance();

            $this->delete_current_install( false );

            $license_key = false;

            if (
                is_object( $this->_license ) &&
                ! $this->_license->is_utilized(
                    ( WP_FS__IS_LOCALHOST_FOR_SERVER || FS_Site::is_localhost_by_address( self::get_unfiltered_site_url() ) )
                )
            ) {
                $license_key = $this->_license->secret_key;
            }

            return $this->opt_in(
                false,
                false,
                false,
                $license_key,
                false,
                false,
                false,
                null,
                array(),
                false
            );
        }

        #endregion

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @since 2.1.3
         */
        public static function migrate_options_to_network() {
            self::migrate_accounts_to_network();

            // Migrate API options from site level to network level.
            $api_network_options = FS_Option_Manager::get_manager( WP_FS__OPTIONS_OPTION_NAME, true, true );
            $api_network_options->migrate_to_network();

            // Migrate API cache to network level storage.
            FS_Cache_Manager::get_manager( WP_FS__API_CACHE_OPTION_NAME )->migrate_to_network();

            self::$_accounts->set_option( 'ms_migration_complete', true, true );
        }

        #----------------------------------------------------------------------------------
        #region Localization
        #----------------------------------------------------------------------------------

        /**
         * Load framework's text domain.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1
         */
        static function _load_textdomain() {
            if ( ! is_admin() ) {
                return;
            }

            global $fs_active_plugins;

            // Works both for plugins and themes.
            load_plugin_textdomain(
                'freemius',
                false,
                $fs_active_plugins->newest->sdk_path . '/languages/'
            );
        }

        #endregion

        #----------------------------------------------------------------------------------
        #region Connectivity Issues
        #----------------------------------------------------------------------------------

        /**
         * Check if Freemius should be turned on for the current plugin install.
         *
         * Note:
         *  $this->_is_on is updated in has_api_connectivity()
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @return bool
         */
        function is_on() {
            self::$_static_logger->entrance();

            if ( is_object( $this->_site ) && ! $this->is_registered() ) {
                return false;
            }

            if ( isset( $this->_is_on ) ) {
                return $this->_is_on;
            }

            // If already installed or pending then sure it's on :)
            if ( $this->is_registered() || $this->is_pending_activation() ) {
                $this->_is_on = true;

                return true;
            }

            return false;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         *
         * @param bool $flush_if_no_connectivity
         *
         * @return bool
         */
        private function should_run_connectivity_test( $flush_if_no_connectivity = false ) {
            if ( ! isset( $this->_storage->connectivity_test ) ) {
                // Connectivity test was never executed, or cache was cleared.
                return true;
            }

            if ( WP_FS__PING_API_ON_IP_OR_HOST_CHANGES ) {
                if ( WP_FS__IS_HTTP_REQUEST ) {
                    if ( $_SERVER['HTTP_HOST'] != $this->_storage->connectivity_test['host'] ) {
                        // Domain changed.
                        return true;
                    }

                    if ( WP_FS__REMOTE_ADDR != $this->_storage->connectivity_test['server_ip'] ) {
                        // Server IP changed.
                        return true;
                    }
                }
            }

            if ( $this->_storage->connectivity_test['is_connected'] &&
                 $this->_storage->connectivity_test['is_active']
            ) {
                // API connected and Freemius is active - no need to run connectivity check.
                return false;
            }

            if ( $flush_if_no_connectivity ) {
                /**
                 * If explicitly asked to flush when no connectivity - do it only
                 * if at least 10 sec passed from the last API connectivity test.
                 */
                return ( isset( $this->_storage->connectivity_test['timestamp'] ) &&
                         ( WP_FS__SCRIPT_START_TIME - $this->_storage->connectivity_test['timestamp'] ) > 10 );
            }

            /**
             * @since 1.1.7 Don't check for connectivity on plugin downgrade.
             */
            $version = $this->get_plugin_version();
            if ( version_compare( $version, $this->_storage->connectivity_test['version'], '>' ) ) {
                // If it's a plugin version upgrade and Freemius is off or no connectivity, run connectivity test.
                return true;
            }

            return false;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.5.4
         *
         * @param bool $is_update
         *
         * @return bool
         */
        private function should_turn_fs_on( $is_update = true ) {
            if (
                empty( $this->_plugin->opt_in_moderation ) ||
                ! is_array( $this->_plugin->opt_in_moderation )
            ) {
                return true;
            }

            $optin_config = $this->_plugin->opt_in_moderation;

            if (
                WP_FS__IS_LOCALHOST &&
                ( ! isset( $optin_config['localhost'] ) || false !== $optin_config['localhost'] )
            ) {
                return true;
            }

            $optin_config_key = $is_update ?
                'updates' :
                'new';

            if ( ! isset( $optin_config[ $optin_config_key ] ) ) {
                return true;
            }

            $visibility_percentage = $optin_config[ $optin_config_key ];

            if ( 0 == $visibility_percentage ) {
                return false;
            }

            if ( ! is_numeric( $visibility_percentage ) ) {
                return true;
            }

            $min = 1;
            $max = 100;

            if ( function_exists( 'random_int' ) ) {
                $random = random_int( $min, $max ); // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.random_intFound
            } else {
                $random = rand( $min, $max );
            }

            return ( $random <= $visibility_percentage );
        }

        /**
         * Check if there's any connectivity issue to Freemius API.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @param bool $flush_if_no_connectivity
         *
         * @return bool|null
         */
        function has_api_connectivity( $flush_if_no_connectivity = false ) {
            $this->_logger->entrance();

            if ( isset( $this->_has_api_connection ) && ( $this->_has_api_connection || ! $flush_if_no_connectivity ) ) {
                return $this->_has_api_connection;
            }

            if ( WP_FS__SIMULATE_NO_API_CONNECTIVITY &&
                 isset( $this->_storage->connectivity_test ) &&
                 true === $this->_storage->connectivity_test['is_connected']
            ) {
                $this->clear_connectivity_info();
            }

            if ( ! $this->should_run_connectivity_test( $flush_if_no_connectivity ) ) {
                $this->_has_api_connection = $this->_storage->connectivity_test['is_connected'];
                /**
                 * @since 1.1.6 During dev mode, if there's connectivity - turn Freemius on regardless the configuration.
                 *
                 * @since 1.2.1.5 If the user running the premium version then ignore the 'is_active' flag and turn Freemius on to enable license key activation.
                 */
                $this->_is_on = $this->_storage->connectivity_test['is_active'] ||
                                $this->is_premium() ||
                                ( WP_FS__DEV_MODE && $this->_has_api_connection && ! WP_FS__SIMULATE_FREEMIUS_OFF );

                return $this->_has_api_connection;
            }

            if (
                ! empty( $this->_storage->connectivity_test ) &&
                isset( $this->_storage->connectivity_test['is_active'] )
            ) {
                $is_connected = isset( $this->_storage->connectivity_test['is_connected'] ) ?
                    $this->_storage->connectivity_test['is_connected'] :
                    null;
                $is_active    = ( $this->_storage->connectivity_test['is_active'] || is_object( $this->_site ) );
            } else {
                $is_connected = null;
                $is_active    = $this->should_turn_fs_on( $this->apply_filters( 'is_plugin_update', $this->is_plugin_update() ) );
            }

            $this->store_connectivity_info( (object) array( 'is_active' => $is_active ), $is_connected );

            if ( $is_active ) {
                $this->_is_on = true;
            }

            return $this->_has_api_connection;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.4
         */
        private function clear_connectivity_info() {
            unset( $this->_storage->connectivity_test );

            FS_Api::clear_force_http_flag();
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.4
         *
         * @param object    $pong
         * @param bool|null $is_connected
         */
        private function store_connectivity_info( $pong, $is_connected ) {
            $this->_logger->entrance();

            $version = $this->get_plugin_version();

            if ( false === $is_connected || WP_FS__SIMULATE_FREEMIUS_OFF ) {
                $is_active = false;
            } else {
                $is_active = ( isset( $pong->is_active ) && true == $pong->is_active );
            }

            $is_active = $this->apply_filters(
                'is_on',
                $is_active,
                $this->is_plugin_update(),
                $version
            );

            $this->_storage->connectivity_test = array(
                'is_connected' => $is_connected,
                'host'         => $_SERVER['HTTP_HOST'],
                'server_ip'    => WP_FS__REMOTE_ADDR,
                'is_active'    => $is_active,
                'timestamp'    => WP_FS__SCRIPT_START_TIME,
                // Last version with connectivity attempt.
                'version'      => $version,
            );

            $this->_has_api_connection = $is_connected;
            $this->_is_on              = $is_active || ( WP_FS__DEV_MODE && $is_connected && ! WP_FS__SIMULATE_FREEMIUS_OFF );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.4
         *
         * @param bool $is_connected
         */
        private function update_connectivity_info( $is_connected ) {
            $this->store_connectivity_info(
                // This is true since we update the connection info only after a successful opt-in or license activation which means that Freemius has already been on even before the process.
                (object) array( 'is_active' => true ),
                $is_connected
            );
        }

        /**
         * Force turning Freemius on.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.8.1
         *
         * @return bool TRUE if successfully turned on.
         */
        private function turn_on() {
            $this->_logger->entrance();

            if ( $this->is_on() || ! isset( $this->_storage->connectivity_test['is_active'] ) ) {
                return false;
            }

            $updated_connectivity              = $this->_storage->connectivity_test;
            $updated_connectivity['is_active'] = true;
            $updated_connectivity['timestamp'] = WP_FS__SCRIPT_START_TIME;
            $this->_storage->connectivity_test = $updated_connectivity;

            $this->_is_on = true;

            return true;
        }

        /**
         * Anonymous and unique site identifier (Hash).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.0
         *
         * @param null|int $blog_id Since 2.0.0
         *
         * @return string
         */
        function get_anonymous_id( $blog_id = null ) {
            $unique_id = self::$_accounts->get_option( 'unique_id', null, $blog_id );

            if ( empty( $unique_id ) || ! is_string( $unique_id ) ) {
                $key = self::get_unfiltered_site_url( $blog_id, true );

                $secure_auth = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '';
                if ( empty( $secure_auth ) ||
                     false !== strpos( $secure_auth, ' ' ) ||
                     'put your unique phrase here' === $secure_auth
                ) {
                    // Protect against default auth key.
                    $secure_auth = md5( microtime() );
                }

                /**
                 * Base the unique identifier on the WP secure authentication key. Which
                 * turns the key into a secret anonymous identifier. This will help us
                 * to avoid duplicate installs generation on the backend upon opt-in.
                 *
                 * @author Vova Feldman (@svovaf)
                 * @since  1.2.3
                 */
                $unique_id = md5( $key . $secure_auth );

                self::$_accounts->set_option( 'unique_id', $unique_id, true, $blog_id );
            }

            $this->_logger->departure( $unique_id );

            return $unique_id;
        }

        /**
         * Returns anonymous network ID.
         *
         * @since  2.4.3
         *
         * @return string
         */
        function get_anonymous_network_id() {
           return $this->get_anonymous_id( get_network()->site_id );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.4
         *
         * @return \WP_User
         */
        static function _get_current_wp_user() {
            self::require_pluggable_essentials();
            self::wp_cookie_constants();

            return wp_get_current_user();
        }

        /**
         * Define cookie constants which are required by Freemius::_get_current_wp_user() since
         * it uses wp_get_current_user() which needs the cookie constants set. When a plugin
         * is network activated the cookie constants are only configured after the network
         * plugins activation, therefore, if we don't define those constants WP will throw
         * PHP warnings/notices.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    2.1.1
         */
        private static function wp_cookie_constants() {
            if ( defined( 'LOGGED_IN_COOKIE' ) &&
                 ( defined( 'AUTH_COOKIE' ) || defined( 'SECURE_AUTH_COOKIE' ) )
            ) {
                return;
            }

            /**
             * Used to guarantee unique hash cookies
             *
             * @since 1.5.0
             */
            if ( ! defined( 'COOKIEHASH' ) ) {
                $siteurl = get_site_option( 'siteurl' );
                if ( $siteurl ) {
                    define( 'COOKIEHASH', md5( $siteurl ) );
                } else {
                    define( 'COOKIEHASH', '' );
                }
            }

            if ( ! defined( 'LOGGED_IN_COOKIE' ) ) {
                define( 'LOGGED_IN_COOKIE', 'wordpress_logged_in_' . COOKIEHASH );
            }

            /**
             * @since 2.5.0
             */
            if ( ! defined( 'AUTH_COOKIE' ) ) {
                define( 'AUTH_COOKIE', 'wordpress_' . COOKIEHASH );
            }

            /**
             * @since 2.6.0
             */
            if ( ! defined( 'SECURE_AUTH_COOKIE' ) ) {
                define( 'SECURE_AUTH_COOKIE', 'wordpress_sec_' . COOKIEHASH );
            }
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.1.0
         *
         * @return int
         */
        static function get_current_wp_user_id() {
            $wp_user = self::_get_current_wp_user();

            return $wp_user->ID;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.7
         *
         * @param string $email
         *
         * @return bool
         */
        static function is_valid_email( $email ) {
            if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
                return false;
            }

            $parts = explode( '@', $email );

            if ( 2 !== count( $parts ) || empty( $parts[1] ) ) {
                return false;
            }

            $blacklist = array(
                'admin.',
                'webmaster.',
                'localhost.',
                'dev.',
                'development.',
                'test.',
                'stage.',
                'staging.',
            );

            // Make sure domain is not one of the blacklisted.
            foreach ( $blacklist as $invalid ) {
                if ( 0 === strpos( $parts[1], $invalid ) ) {
                    return false;
                }
            }

            // Get the UTF encoded domain name.
            /**
             * @note - The check of `defined('...')` is there to account for PHP servers compiled with some older version of ICU where the constants are not defined.
             * @author - @swashata
             */
            $is_new_idn_available = (
                version_compare( PHP_VERSION, '5.6.40') > 0 &&
                defined( 'IDNA_DEFAULT' ) &&
                defined( 'INTL_IDNA_VARIANT_UTS46' )
            );
            if ( $is_new_idn_available ) {
                $domain = idn_to_ascii( $parts[1], IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 );
            } else {
                $domain = idn_to_ascii( $parts[1] );  // phpcs:ignore PHPCompatibility.ParameterValues.NewIDNVariantDefault.NotSet
            }

            $domain = $domain . '.';

            return ( checkdnsrr( $domain, 'MX' ) || checkdnsrr( $domain, 'A' ) );
        }

        #endregion

        #----------------------------------------------------------------------------------
        #region Email
        #----------------------------------------------------------------------------------

        /**
         * Generates and sends an HTML email with customizable sections.
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.1.2
         *
         * @param string $to_address
         * @param string $subject
         * @param array  $sections
         * @param array  $headers
         *
         * @return bool Whether the email contents were sent successfully.
         */
        private function send_email(
            $to_address,
            $subject,
            $sections = array(),
            $headers = array()
        ) {
            $default_sections = $this->get_email_sections();

            // Insert new sections or replace the default email sections.
            if ( is_array( $sections ) && ! empty( $sections ) ) {
                foreach ( $sections as $section_id => $custom_section ) {
                    if ( ! isset( $default_sections[ $section_id ] ) ) {
                        // If the section does not exist, add it.
                        $default_sections[ $section_id ] = $custom_section;
                    } else {
                        // If the section already exists, override it.
                        $current_section = $default_sections[ $section_id ];

                        // Replace the current section's title if a custom section title exists.
                        if ( isset( $custom_section['title'] ) ) {
                            $current_section['title'] = $custom_section['title'];
                        }

                        // Insert new rows under the current section or replace the default rows.
                        if ( isset( $custom_section['rows'] ) && is_array( $custom_section['rows'] ) && ! empty( $custom_section['rows'] ) ) {
                            foreach ( $custom_section['rows'] as $row_id => $row ) {
                                $current_section['rows'][ $row_id ] = $row;
                            }
                        }

                        $default_sections[ $section_id ] = $current_section;
                    }
                }
            }

            $vars    = array( 'sections' => $default_sections );
            $message = fs_get_template( 'email.php', $vars );

            // Set the type of email to HTML.
            $headers[] = 'Content-type: text/html; charset=UTF-8';

            $header_string = implode( "\r\n", $headers );

            return wp_mail(
                $to_address,
                $subject,
                $message,
                $header_string
            );
        }

        /**
         * Generates the data for the sections of the email content.
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.1.2
         *
         * @return array
         */
        private function get_email_sections() {
            // Retrieve the current user's information so that we can get the user's email, first name, and last name below.
            $current_user = self::_get_current_wp_user();

            // Retrieve the cURL version information so that we can get the version number below.
            $curl_version_information = curl_version();

            $active_plugin = self::get_active_plugins();

            // Generate the list of active plugins separated by new line.
            $active_plugin_string = '';
            foreach ( $active_plugin as $plugin ) {
                $active_plugin_string .= sprintf(
                    '<a href="%s">%s</a> [v%s]<br>',
                    $plugin['PluginURI'],
                    $plugin['Name'],
                    $plugin['Version']
                );
            }

            $server_ip = WP_FS__REMOTE_ADDR;

            // Add PHP info for deeper investigation.
            ob_start();
            phpinfo();
            $php_info = ob_get_clean();

            $api_domain = substr( FS_API__ADDRESS, strpos( FS_API__ADDRESS, ':' ) + 3 );

            // Generate the default email sections.
            $sections = array(
                'sdk'      => array(
                    'title' => 'SDK',
                    'rows'  => array(
                        'fs_version'   => array( 'FS Version', $this->version ),
                        'curl_version' => array( 'cURL Version', $curl_version_information['version'] )
                    )
                ),
                'plugin'   => array(
                    'title' => ucfirst( $this->get_module_type() ),
                    'rows'  => array(
                        'name'    => array( 'Name', $this->get_plugin_name() ),
                        'version' => array( 'Version', $this->get_plugin_version() )
                    )
                ),
                'api'      => array(
                    'title' => 'API Subdomain',
                    'rows'  => array(
                        'dns' => array(
                            'DNS_CNAME',
                            function_exists( 'dns_get_record' ) ?
                                var_export( dns_get_record( $api_domain, DNS_CNAME ), true ) :
                                'dns_get_record() disabled/blocked'
                        ),
                        'ip'  => array(
                            'IP',
                            function_exists( 'gethostbyname' ) ?
                                gethostbyname( $api_domain ) :
                                'gethostbyname() disabled/blocked'
                        ),
                    ),
                ),
                'site'     => array(
                    'title' => 'Site',
                    'rows'  => array(
                        'unique_id'   => array( 'Unique ID', $this->get_anonymous_id() ),
                        'address'     => array( 'Address', site_url() ),
                        'host'        => array(
                            'HTTP_HOST',
                            ( ! empty( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '' )
                        ),
                        'hosting'     => array(
                            'Hosting Company' => fs_request_has( 'hosting_company' ) ?
                                fs_request_get( 'hosting_company' ) :
                                'Unknown',
                        ),
                        'server_addr' => array(
                            'SERVER_ADDR',
                            '<a href="http://www.projecthoneypot.org/ip_' . $server_ip . '">' . $server_ip . '</a>'
                        )
                    )
                ),
                'user'     => array(
                    'title' => 'User',
                    'rows'  => array(
                        'email' => array( 'Email', $current_user->user_email ),
                        'first' => array( 'First', $current_user->user_firstname ),
                        'last'  => array( 'Last', $current_user->user_lastname )
                    )
                ),
                'plugins'  => array(
                    'title' => 'Plugins',
                    'rows'  => array(
                        'active_plugins' => array( 'Active Plugins', $active_plugin_string )
                    )
                ),
                'php_info' => array(
                    'title' => 'PHP Info',
                    'rows'  => array(
                        'info' => array( $php_info )
                    ),
                )
            );

            // Allow the sections to be modified by other code.
            $sections = $this->apply_filters( 'email_template_sections', $sections );

            return $sections;
        }

        #endregion

        #----------------------------------------------------------------------------------
        #region Initialization
        #----------------------------------------------------------------------------------

        /**
         * Init plugin's Freemius instance.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         *
         * @param number $id
         * @param string $public_key
         * @param bool   $is_live
         * @param bool   $is_premium
         */
        function init( $id, $public_key, $is_live = true, $is_premium = true ) {
            $this->_logger->entrance();

            $this->dynamic_init( array(
                'id'         => $id,
                'public_key' => $public_key,
                'is_live'    => $is_live,
                'is_premium' => $is_premium,
            ) );
        }

        /**
         * Dynamic initiator, originally created to support initiation
         * with parent_id for add-ons.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param array $plugin_info
         *
         * @throws Freemius_Exception
         */
        function dynamic_init( array $plugin_info ) {
            $this->_logger->entrance();

            $this->parse_settings( $plugin_info );

            $this->register_after_settings_parse_hooks();

            /**
             * If anonymous but there's already a user entity and the user's site is associated with a valid license or trial period, update the anonymous mode accordingly.
             *
             * @todo Remove this entire `if` block after several releases as starting from this version, the anonymous mode will already be updated accordingly after a purchase.
             */
            if ( $this->is_anonymous() ) {
                $is_network_level = ( $this->_is_network_active && fs_is_network_admin() );

                if (
                    ! $is_network_level ||
                    FS_Site::is_valid_id( $this->_storage->network_install_blog_id )
                 ) {
                    if ( $this->is_paying_or_trial() ) {
                        $this->reset_anonymous_mode( $is_network_level );
                    }
                } else {
                    $network = get_network();

                    if ( is_object( $network ) ) {
                        $main_blog_id  = $network->site_id;
                        $first_install = $this->get_install_by_blog_id( $main_blog_id );

                        if ( is_object( $first_install ) ) {
                            $this->_storage->network_install_blog_id = $main_blog_id;
                            $this->_storage->network_user_id         = $first_install->user_id;
                        }
                    }
                }
            }

            if ( $this->should_stop_execution() ) {
                return;
            }

            if ( ! $this->is_registered() ) {
                if ( $this->is_anonymous() ) {
                    // If user skipped, no need to test connectivity.
                    $this->_has_api_connection = true;
                    $this->_is_on              = true;
                } else {
                    if ( false === $this->has_api_connectivity() ) {
                        return;
                    } else {
                        if ( $this->_anonymous_mode ) {
                            // Simulate anonymous mode.
                            $this->_is_anonymous = true;
                        }
                    }
                }
            }

            /**
             * This should be executed even if Freemius is off for the core module,
             * otherwise, the add-ons dialog box won't work properly. This is especially
             * relevant when the developer decided to turn FS off for existing users.
             *
             * @author Vova Feldman (@svovaf)
             */
            if ( $this->is_user_in_admin() &&
                'plugin-information' === fs_request_get( 'tab', false ) &&
                 $this->should_use_freemius_updater_and_dialog() &&
                 (
                     ( $this->is_addon() && $this->get_slug() == fs_request_get( 'plugin', false ) ) ||
                     ( $this->has_addons() && $this->get_id() == fs_request_get( 'parent_plugin_id', false ) )
                 )
            ) {
                require_once WP_FS__DIR_INCLUDES . '/fs-plugin-info-dialog.php';

                new FS_Plugin_Info_Dialog( $this->is_addon() ? $this->get_parent_instance() : $this );
            }

            // Check if Freemius is on for the current plugin.
            // This MUST be executed after all the plugin variables has been loaded.
            if ( ! $this->is_registered() && ! $this->is_on() ) {
                return;
            }

            if ( $this->has_api_connectivity() ) {
                if ( self::is_cron() ) {
                    $this->hook_callback_to_sync_cron();
                } else if ( $this->is_user_in_admin() ) {
                    /**
                     * Schedule daily data sync cron if:
                     *
                     *  1. User opted-in (for tracking).
                     *  2. If skipped, but later upgraded (opted-in via upgrade).
                     *
                     * @author Vova Feldman (@svovaf)
                     * @since  1.1.7.3
                     *
                     */
                    if ( $this->is_registered() && $this->is_tracking_allowed() ) {
                        $this->maybe_schedule_sync_cron();
                    }

                    /**
                     * Check if requested for manual blocking background sync.
                     */
                    if ( fs_request_has( 'background_sync' ) ) {
                        self::require_pluggable_essentials();
                        self::wp_cookie_constants();

                        $this->run_manual_sync();
                    }
                }
            }

            if ( $this->is_registered() ) {
                FS_Clone_Manager::instance()->maybe_resolve_new_subsite_install_automatically( $this );

                $this->hook_callback_to_install_sync();
            }

            if ( $this->is_addon() ) {
                if ( $this->is_parent_plugin_installed() ) {
                    // Link to parent FS.
                    $this->_parent = self::get_instance_by_id( $this->_plugin->parent_plugin_id );

                    // Get parent plugin reference.
                    $this->_parent_plugin = $this->_parent->get_plugin();
                }
            }

            if ( $this->is_user_in_admin() ) {
                if ( $this->is_registered() && fs_request_has( 'purchase_completed' ) ) {
                    $this->_admin_notices->add_sticky(
                        sprintf(
                        /* translators: %s: License type (e.g. you have a professional license) */
                            $this->get_text_inline( 'You have purchased a %s license.', 'you-have-x-license' ),
                            fs_request_get( 'purchased_plan' )
                        ) .
                        sprintf(
                            $this->get_text_inline(" The %s's %sdownload link%s, license key, and installation instructions have been sent to %s. If you can't find the email after 5 min, please check your spam box.", 'post-purchase-email-sent-message' ),
                            $this->get_module_label( true ),
                            ( FS_Plugin::is_valid_id( $this->get_bundle_id() ) ? "products' " : '' ),
                            ( FS_Plugin::is_valid_id( $this->get_bundle_id() ) ? 's' : '' ),
                            sprintf(
                                '<strong>%s</strong>',
                                fs_request_get( 'purchase_email' )
                            )
                        ),
                        'plan_purchased',
                        $this->get_text_x_inline( 'Yee-haw', 'interjection expressing joy or exuberance', 'yee-haw' ) . '!'
                    );
                }

                if ( $this->is_addon() ) {
                    if ( ! $this->is_parent_plugin_installed() ) {
                        $parent_name = $this->get_option( $plugin_info, 'parent_name', null );

                        if ( isset( $plugin_info['parent'] ) ) {
                            $parent_name = $this->get_option( $plugin_info['parent'], 'name', null );
                        }

                        $this->_admin_notices->add(
                            ( ! empty( $parent_name ) ?
                                sprintf( $this->get_text_x_inline( '%s cannot run without %s.', 'addonX cannot run without pluginY', 'addon-x-cannot-run-without-y' ), $this->get_plugin_name(), $parent_name ) :
                                sprintf( $this->get_text_x_inline( '%s cannot run without the plugin.', 'addonX cannot run...', 'addon-x-cannot-run-without-parent' ), $this->get_plugin_name() )
                            ),
                            $this->get_text_x_inline( 'Oops', 'exclamation', 'oops' ) . '...',
                            'error'
                        );

                        return;
                    } else {
                        $is_network_admin = fs_is_network_admin();

                        if ( ! $this->_parent->is_registered() && $this->is_registered() ) {
                            // If add-on activated and parent not, automatically install parent for the user.
                            $this->activate_parent_account( $this->_parent );
                        } else if (
                            $this->_parent->is_registered() &&
                            ! $this->is_registered() &&
                            /**
                             * If not registered for add-on and the following conditions for the add-on are met, activate add-on account.
                             * * Network active and in network admin         - network activate add-on account.
                             * * Network active and not in network admin     - activate add-on account for the current blog.
                             * * Not network active and not in network admin - activate add-on account for the current blog.
                             *
                             * If not registered for add-on, not network active, and in network admin, do not handle the add-on activation.
                             *
                             * @author Leo Fajardo (@leorw)
                             * @since 2.3.0
                             */
                            ( $this->is_network_active() || ! $is_network_admin )
                        ) {
                            $premium_license = null;

                            if (
                                ! $this->has_free_plan() &&
                                $this->is_bundle_license_auto_activation_enabled() &&
                                $this->_parent->is_activated_with_bundle_license()
                            ) {
                                /**
                                 * If the add-on has no free plan, try to activate the account only when there's a bundle license.
                                 *
                                 * @author Leo Fajardo (@leorw)
                                 * @since 2.4.0
                                 */
                                $bundle_license = $this->get_active_parent_license( $this->_parent->_get_license()->secret_key, false );

                                if (
                                    is_object( $bundle_license ) &&
                                    ! empty( $bundle_license->products ) &&
                                    in_array( $this->get_id(), $bundle_license->products )
                                ) {
                                    $premium_license = $bundle_license;
                                }
                            }

                            if ( $this->has_free_plan() || is_object( $premium_license) ) {
                                // If parent plugin activated, automatically install add-on for the user.
                                $this->_activate_addon_account(
                                    $this->_parent,
                                    ( $this->is_network_active() && $is_network_admin ) ?
                                        true :
                                        get_current_blog_id(),
                                    $premium_license
                                );
                            }
                        }

                        // @todo This should be only executed on activation. It should be migrated to register_activation_hook() together with other activation related logic.
                        if ( $this->is_premium() ) {
                            // Remove add-on download admin-notice.
                            $this->_parent->_admin_notices->remove_sticky( array(
                                'addon_plan_upgraded_' . $this->_slug,
                                'no_addon_license_' . $this->_slug,
                            ) );
                        }

//						$this->deactivate_premium_only_addon_without_license();
                    }
                }

                add_action( 'admin_init', array( &$this, '_admin_init_action' ) );

//				if ( $this->is_registered() ||
//				     $this->is_anonymous() ||
//				     $this->is_pending_activation()
//				) {
//					$this->_init_admin();
//				}
            }

            /**
             * Should be called outside `$this->is_user_in_admin()` scope
             * because the updater has some logic that needs to be executed
             * during AJAX calls.
             *
             * Currently, we need to hook to the `http_request_host_is_external` filter.
             * In the future, there might be additional logic added.
             *
             * @author Vova Feldman
             * @since  1.2.1.6
             */
            if (
                $this->should_use_freemius_updater_and_dialog() &&
                (
                    $this->is_premium() ||
                    /**
                     * If not premium but the premium version is installed, also instantiate the updater so that the
                     * plugin information dialog of the premium version will have the information from the server.
                     *
                     * @author Leo Fajardo (@leorw)
                     * @since 2.2.3
                     */
                    ( file_exists( fs_normalize_path( WP_PLUGIN_DIR . '/' . $this->premium_plugin_basename() ) ) )
                ) &&
                $this->has_release_on_freemius() &&
                ( ! $this->is_unresolved_clone( true ) )
            ) {
                FS_Plugin_Updater::instance( $this );
            }

            $this->do_action( 'initiated' );

            if ( $this->_storage->prev_is_premium !== $this->_plugin->is_premium ) {
                if ( isset( $this->_storage->prev_is_premium ) ) {
                    $this->apply_filters(
                        'after_code_type_change',
                        // New code type.
                        $this->_plugin->is_premium
                    );
                } else {
                    // Set for code type for the first time.
                    $this->_storage->prev_is_premium = $this->_plugin->is_premium;
                }
            }

            if ( ! $this->is_addon() ) {
                if ( $this->is_registered() ) {
                    // Fix for upgrade from versions < 1.0.9.
                    if ( ! isset( $this->_storage->activation_timestamp ) ) {
                        $this->_storage->activation_timestamp = WP_FS__SCRIPT_START_TIME;
                    }

                    $this->do_action( 'after_init_plugin_registered' );
                } else if ( $this->is_anonymous() ) {
                    $this->do_action( 'after_init_plugin_anonymous' );
                } else if ( $this->is_pending_activation() ) {
                    $this->do_action( 'after_init_plugin_pending_activations' );
                }
            } else {
                if ( $this->is_registered() ) {
                    $this->do_action( 'after_init_addon_registered' );
                } else if ( $this->is_anonymous() ) {
                    $this->do_action( 'after_init_addon_anonymous' );
                } else if ( $this->is_pending_activation() ) {
                    $this->do_action( 'after_init_addon_pending_activations' );
                }
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.2.3
         *
         * @return bool
         */
        private function should_use_freemius_updater_and_dialog() {
            return (
                /**
                 * Allow updater and dialog when the `fs_allow_updater_and_dialog` URL query param exists and has `true`
                 * value, or when the current page is not the "Add Plugins" page (/plugin-install.php) and the `action`
                 * URL query param doesn't exist or its value is not `install-plugin` so that there will be no conflicts
                 * with the .org plugins' functionalities (e.g. installation from the "Add Plugins" page and viewing
                 * plugin details from .org).
                 */
                ( true === fs_request_get_bool( 'fs_allow_updater_and_dialog' ) ) ||
                (
                    ! self::is_plugin_install_page() &&
                    // Disallow updater and dialog when installing a plugin, otherwise .org "add-on" plugins will be affected.
                    ( 'install-plugin' !== fs_request_get( 'action' ) )
                )
            );
        }

        /**
         * @param string[] $permissions
         * @param bool     $is_enabled
         * @param int|null $blog_id
         *
         * @return true|object `true` on success, API error object on failure.
         */
        private function update_site_permissions( array $permissions, $is_enabled, $blog_id = null ) {
            $this->_logger->entrance();

            $params = array(
                'permissions' => implode( ',', $permissions ),
                'is_enabled'  => $is_enabled,
            );

            $current_blog_id  = get_current_blog_id();
            $is_blog_switched = false;
            if ( is_numeric( $blog_id ) && $current_blog_id != $blog_id ) {
                $is_blog_switched = $this->switch_to_blog( $blog_id );
            }

            $result = $this->api_site_call( '/permissions.json', 'put', $params );

            if ( $is_blog_switched ) {
                $this->switch_to_blog( $current_blog_id );
            }

            if (
                ! $this->is_api_result_object( $result ) ||
                ! isset( $result->install_id )
            ) {
                $this->_logger->api_error( $result );

                return $result;
            }

            return true;
        }

        /**
         * @param string[] $permissions
         * @param bool     $is_enabled
         * @param bool     $has_site_delegated_connection
         *
         * @return true|object `true` on success, API error object on failure.
         */
        private function update_network_permissions(
            array $permissions,
            $is_enabled,
            &$has_site_delegated_connection
        ) {
            $this->_logger->entrance();

            $install_id_2_blog_id = array();
            $install_by_blog_id   = $this->get_blog_install_map();

            $has_site_delegated_connection = false;

            foreach ( $install_by_blog_id as $blog_id => $install ) {
                if ( $this->is_site_delegated_connection( $blog_id ) ) {
                    // Only update permissions of non-delegated installs.
                    $has_site_delegated_connection = true;
                    continue;
                }

                $install_id_2_blog_id[ $install->id ] = $blog_id;
            }

            if ( empty( $install_id_2_blog_id ) ) {
                return true;
            }

            $params = array(
                'permissions' => implode( ',', $permissions ),
                'is_enabled'  => $is_enabled,
                'install_ids' => implode( ',', array_keys( $install_id_2_blog_id ) ),
            );

            // Send update to FS.
            $result = $this->get_current_or_network_user_api_scope()->call(
                "/plugins/{$this->_module_id}/installs/permissions.json",
                'put',
                $params
            );

            if ( ! $this->is_api_result_object( $result, 'installs_metadata' ) ) {
                $this->_logger->api_error( $result );

                return $result;
            }

            return true;
        }

        /**
         * @param mixed $result
         *
         * @return string
         */
        private function get_api_error_message( $result ) {
            $error_message = sprintf( $this->get_text_inline( 'There was an unexpected API error while processing your request. Please try again in a few minutes and if it still doesn\'t work, contact the %s\'s author with the following:',
                    'unexpected-api-error' ), $this->_module_type ) . ' ';

            if (
                $this->is_api_error( $result ) &&
                isset( $result->error )
            ) {
                $code = empty( $result->error->code ) ? '' : " Code: {$result->error->code}";

                $error_message .= "<b>{$result->error->message}{$code}</b>";
            } else {
                $error_message .= var_export( $result, true );
            }

            return $error_message;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.5.1
         */
        function _toggle_permission_tracking_callback() {
            $this->_logger->entrance();

            $this->check_ajax_referer( 'toggle_permission_tracking' );

            if ( ! $this->is_registered( true ) ) {
                self::shoot_ajax_failure( 'User never opted-in.' );
            }

            $is_enabled  = fs_request_get_bool( 'is_enabled' );
            $permissions = fs_request_get( 'permissions' );

            if ( ! is_string( $permissions ) ) {
                self::shoot_ajax_failure( 'The permissions param must be a string.' );
            }

            $permissions = explode( ',', $permissions );

            $result = $this->toggle_permission_tracking( $permissions, $is_enabled );

            if ( true !== $result ) {
                self::shoot_ajax_failure( $this->get_api_error_message( $result ) );
            }

            self::shoot_ajax_success();
        }

        /**
         * @param string[] $permissions
         * @param bool     $is_enabled
         * @param int|null $blog_id
         *
         * @return bool|mixed `true` if updated successfully or no update is needed.
         */
        private function toggle_permission_tracking( $permissions, $is_enabled, $blog_id = null ) {
            if ( ! $this->is_registered( true ) ) {
                // User never opted-in.
                return false;
            }

            // Check if permissions are already set as needed.
            if ( FS_Permission_Manager::instance( $this )->are_permissions( $permissions, $is_enabled, $blog_id ) ) {
                /**
                 * Note:
                 *  When running on the network admin, there's no need to iterate through all the installs individually since network opt-in permissions are managed for ALL non-delegated installs through a single option (per permission) on the network-level storage.
                 */
                return true;
            }

            $api_managed_permissions = array_intersect(
                $permissions,
                FS_Permission_Manager::get_api_managed_permission_ids()
            );

            if (
                in_array( FS_Permission_Manager::PERMISSION_ESSENTIALS, $permissions ) &&
                ! in_array( FS_Permission_Manager::PERMISSION_SITE, $permissions )
            ) {
                $api_managed_permissions[] = FS_Permission_Manager::PERMISSION_SITE;
            }

            if ( ! empty( $api_managed_permissions ) ) {
                $has_site_delegated_connection = false;

                if (
                    ! $is_enabled &&
                    ! in_array( FS_Permission_Manager::PERMISSION_EXTENSIONS, $api_managed_permissions ) &&
                    false === FS_Permission_Manager::instance( $this )->is_extensions_tracking_allowed( $blog_id )
                ) {
                    /**
                     * If we are turning off a permission and the extensions permission is off too, enrich the permissions update request to also turn off extensions tracking, as currently when opting in with extensions tracking disabled the extensions tracking is off but the API isn't aware of it.
                     *
                     * @todo Remove this entire `if` after implementing granular opt-in that also sends the permissions to the API when opting in.
                     */
                    $api_managed_permissions[] = FS_Permission_Manager::PERMISSION_EXTENSIONS;
                }

                if ( is_null( $blog_id ) && fs_is_network_admin() ) {
                    $result = $this->update_network_permissions(
                        $api_managed_permissions,
                        $is_enabled,
                        $has_site_delegated_connection
                    );
                } else {
                    $result = $this->update_site_permissions(
                        $api_managed_permissions,
                        $is_enabled,
                        $blog_id
                    );
                }

                if ( true !== $result ) {
                    return $result;
                }

                if ( in_array( FS_Permission_Manager::PERMISSION_SITE, $api_managed_permissions ) ) {
                    if ( $is_enabled ) {
                        $this->schedule_sync_cron();
                    } else {
                        $this->clear_sync_cron( ! $has_site_delegated_connection );
                    }
                }

                if ( in_array( FS_Permission_Manager::PERMISSION_USER, $api_managed_permissions ) ) {
                    $this->toggle_user_permission( $is_enabled, $blog_id );
                }
            }

            $this->update_tracking_permissions(
                $permissions,
                $is_enabled,
                $blog_id
            );

            return true;
        }

        /**
         * @param bool     $is_enabled
         * @param int|null $blog_id
         */
        private function toggle_user_permission( $is_enabled, $blog_id = null ) {
            $network_or_blog_ids = is_numeric( $blog_id ) ?
                $blog_id :
                fs_is_network_admin();

            if ( $is_enabled ) {
                $this->reset_anonymous_mode( $network_or_blog_ids );
            } else {
                $this->skip_connection( $network_or_blog_ids );
            }
        }

        /**
         * Opt-in back into usage tracking.
         *
         * Note: This will only work if the user opted-in previously.
         *
         * Returns:
         *  1. FALSE  - If the user never opted-in.
         *  2. TRUE   - If successfully opted-in back to usage tracking.
         *  3. object - API result on failure.
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.2.1.5
         *
         * @bool $is_enabled
         *
         * @return bool|object
         */
        private function toggle_site_tracking( $is_enabled, $blog_id = null ) {
            $this->_logger->entrance();

            return $this->toggle_permission_tracking(
                FS_Permission_Manager::instance( $this )->get_site_tracking_permission_names(),
                $is_enabled,
                $blog_id
            );
        }

        /**
         * If user opted-in and later disabled usage-tracking,
         * re-allow tracking for licensing and updates.
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.2.1.5
         *
         * @param bool $is_context_single_site
         */
        private function reconnect_locally( $is_context_single_site = false ) {
            $this->_logger->entrance();

            if ( ! $this->is_registered() ) {
                return;
            }

            if ( ! fs_is_network_admin() || $is_context_single_site ) {
                if ( $this->is_tracking_prohibited() ) {
                    FS_Permission_Manager::instance( $this )->update_site_tracking( true );
                }
            } else {
                $installs_map = $this->get_blog_install_map();
                foreach ( $installs_map as $blog_id => $install ) {
                    /**
                     * @var FS_Site $install
                     */
                    if ( ! $this->is_tracking_allowed( $blog_id, $install ) ) {
                        FS_Permission_Manager::instance( $this )->update_site_tracking( true, $blog_id );
                    }
                }
            }
        }

        /**
         * Update permission tracking flags. When updating in a network context, in addition to updating the network-level flags, also update the permissions on the site-level for all non-delegated sites.
         *
         * @param string[] $permissions
         * @param bool     $is_enabled
         * @param int|null $blog_id
         *
         * @return array
         */
        private function update_tracking_permissions( $permissions, $is_enabled, $blog_id = null ) {
            // Alias.
            $permission_manager = FS_Permission_Manager::instance( $this );

            $network_or_blog_ids = is_numeric( $blog_id ) ?
                $blog_id :
                fs_is_network_admin();

            if ( true === $network_or_blog_ids ) {
                // Update the permission for all non-delegated sub-sites.
                $blog_ids = $this->get_non_delegated_blog_ids();

                // Add the network-level to the array, to update the permission on the network-level storage.
                array_unshift( $blog_ids, null );
            }
            else
            {
                if ( false === $network_or_blog_ids ) {
                    $network_or_blog_ids = null;
                }

                $blog_ids = is_array( $network_or_blog_ids ) ?
                    $network_or_blog_ids :
                    array( $network_or_blog_ids );
            }

            $result = array();
            foreach ( $permissions as $permission ) {
                $permission              = trim( $permission );
                $is_permission_supported = true;

                foreach ( $blog_ids as $id ) {
                    $is_permission_supported = $permission_manager->update_permission_tracking_flag(
                        $permission,
                        $is_enabled,
                        $id
                    );
                }

                if ( ! $is_permission_supported ) {
                    $permission = 'no_match';
                }

                $result[ $permission ] = $is_enabled;
            }

            return $result;
        }

        /**
         * Parse plugin's settings (as defined by the plugin dev).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         *
         * @param array $plugin_info
         *
         * @throws \Freemius_Exception
         */
        private function parse_settings( &$plugin_info ) {
            $this->_logger->entrance();

            $id          = $this->get_numeric_option( $plugin_info, 'id', false );
            $public_key  = $this->get_option( $plugin_info, 'public_key', false );
            $secret_key  = $this->get_option( $plugin_info, 'secret_key', null );
            $parent_id   = $this->get_numeric_option( $plugin_info, 'parent_id', null );
            $parent_name = $this->get_option( $plugin_info, 'parent_name', null );

            /**
             * @author Vova Feldman (@svovaf)
             * @since  1.1.9 Try to pull secret key from external config.
             */
            if ( is_null( $secret_key ) && defined( "WP_FS__{$this->_slug}_SECRET_KEY" ) ) {
                $secret_key = constant( "WP_FS__{$this->_slug}_SECRET_KEY" );
            }

            if ( isset( $plugin_info['parent'] ) ) {
                $parent_id = $this->get_numeric_option( $plugin_info['parent'], 'id', null );
//				$parent_slug       = $this->get_option( $plugin_info['parent'], 'slug', null );
//				$parent_public_key = $this->get_option( $plugin_info['parent'], 'public_key', null );
//				$parent_name = $this->get_option( $plugin_info['parent'], 'name', null );
            }

            if ( false === $id ) {
                throw new Freemius_Exception( array(
                    'error' => array(
                        'type'    => 'ParameterNotSet',
                        'message' => 'Plugin id parameter is not set.',
                        'code'    => 'plugin_id_not_set',
                        'http'    => 500,
                    )
                ) );
            }
            if ( false === $public_key ) {
                throw new Freemius_Exception( array(
                    'error' => array(
                        'type'    => 'ParameterNotSet',
                        'message' => 'Plugin public_key parameter is not set.',
                        'code'    => 'plugin_public_key_not_set',
                        'http'    => 500,
                    )
                ) );
            }

            $plugin = ( $this->_plugin instanceof FS_Plugin ) ?
                $this->_plugin :
                new FS_Plugin();

            $premium_suffix = $this->get_option( $plugin_info, 'premium_suffix', '(Premium)' );

            $plugin->update( array(
                'id'                   => $id,
                'type'                 => $this->get_option( $plugin_info, 'type', $this->_module_type ),
                'public_key'           => $public_key,
                'slug'                 => $this->_slug,
                'premium_slug'         => $this->get_option( $plugin_info, 'premium_slug', "{$this->_slug}-premium" ),
                'parent_plugin_id'     => $parent_id,
                'version'              => $this->get_plugin_version(),
                'title'                => $this->get_plugin_name( $premium_suffix ),
                'file'                 => $this->_plugin_basename,
                'is_premium'           => $this->get_bool_option( $plugin_info, 'is_premium', true ),
                'premium_suffix'       => $premium_suffix,
                'is_live'              => $this->get_bool_option( $plugin_info, 'is_live', true ),
                'affiliate_moderation' => $this->get_option( $plugin_info, 'has_affiliation' ),
                'bundle_id'            => $this->get_option( $plugin_info, 'bundle_id', null ),
                'bundle_public_key'    => $this->get_option( $plugin_info, 'bundle_public_key', null ),
                'opt_in_moderation'    => $this->get_option(
                    $plugin_info,
                    'opt_in',
                    // For backward compatibility, we support both parameter names: opt_in and opt_in_moderation.
                    $this->get_option( $plugin_info, 'opt_in_moderation', null )
                ),
            ) );

            if ( $plugin->is_updated() ) {
                // Update plugin details.
                $this->_plugin = FS_Plugin_Manager::instance( $this->_module_id )->store( $plugin );
            }
            // Set the secret key after storing the plugin, we don't want to store the key in the storage.
            $this->_plugin->secret_key = $secret_key;

            /**
             * If the product is network integrated and activated and the current view is in the network level Admin dashboard, if the product's network-level menu located differently from the sub-site level, then use the network menu details (when set).
             *
             * @author Vova Feldman
             * @since  2.4.5
             */
            if ( $this->is_network_active() && fs_is_network_admin() ) {
                if ( isset( $plugin_info['menu_network'] ) &&
                     is_array( $plugin_info['menu_network'] ) &&
                     ! empty( $plugin_info['menu_network'] )
                ) {
                    $plugin_info['menu'] = $plugin_info['menu_network'];
                }
            }

            if ( ! isset( $plugin_info['menu'] ) ) {
                $plugin_info['menu'] = array();

                if ( ! empty( $this->_storage->sdk_last_version ) &&
                     version_compare( $this->_storage->sdk_last_version, '1.1.2', '<=' )
                ) {
                    // Backward compatibility to 1.1.2
                    $plugin_info['menu']['slug'] = isset( $plugin_info['menu_slug'] ) ?
                        $plugin_info['menu_slug'] :
                        $this->_slug;
                }
            }

            $this->_menu = FS_Admin_Menu_Manager::instance(
                $this->_module_id,
                $this->_module_type,
                $this->get_unique_affix()
            );

            $this->_menu->init( $plugin_info['menu'], $this->is_addon() );

            $this->_has_addons          = $this->get_bool_option( $plugin_info, 'has_addons', false );
            $this->_has_paid_plans      = $this->get_bool_option( $plugin_info, 'has_paid_plans', true );
            $this->_has_premium_version = $this->get_bool_option( $plugin_info, 'has_premium_version', $this->_has_paid_plans );
            $this->_ignore_pending_mode = $this->get_bool_option( $plugin_info, 'ignore_pending_mode', false );
            $this->_is_org_compliant    = $this->get_bool_option( $plugin_info, 'is_org_compliant', true );
            $this->_is_premium_only     = $this->get_bool_option( $plugin_info, 'is_premium_only', false );
            if ( $this->_is_premium_only ) {
                // If premium only plugin, disable anonymous mode.
                $this->_enable_anonymous = false;
                $this->_anonymous_mode   = false;
            } else {
                $this->_enable_anonymous = $this->get_bool_option( $plugin_info, 'enable_anonymous', true );
                $this->_anonymous_mode   = $this->get_bool_option( $plugin_info, 'anonymous_mode', false );
            }
            $this->_permissions = $this->get_option( $plugin_info, 'permissions', array() );
            $this->_is_bundle_license_auto_activation_enabled = $this->get_option( $plugin_info, 'bundle_license_auto_activation', false );

            if ( ! empty( $plugin_info['trial'] ) ) {
                $this->_trial_days = $this->get_numeric_option(
                    $plugin_info['trial'],
                    'days',
                    // Default to 0 - trial without days specification.
                    0
                );

                $this->_is_trial_require_payment = $this->get_bool_option( $plugin_info['trial'], 'is_require_payment', false );
            }

            $this->_navigation = $this->get_option(
                $plugin_info,
                'navigation',
                $this->is_free_wp_org_theme() ?
                    self::NAVIGATION_TABS :
                    self::NAVIGATION_MENU
            );
        }

        /**
         * @param string[] $options
         * @param string   $key
         * @param mixed    $default
         *
         * @return bool
         */
        private function get_option( &$options, $key, $default = false ) {
            return ! empty( $options[ $key ] ) ? $options[ $key ] : $default;
        }

        private function get_bool_option( &$options, $key, $default = false ) {
            return isset( $options[ $key ] ) && is_bool( $options[ $key ] ) ? $options[ $key ] : $default;
        }

        private function get_numeric_option( &$options, $key, $default = false ) {
            return isset( $options[ $key ] ) && is_numeric( $options[ $key ] ) ? $options[ $key ] : $default;
        }

        /**
         * Gate keeper.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         *
         * @return bool
         */
        private function should_stop_execution() {
            if ( empty( $this->_storage->was_plugin_loaded ) ) {
                /**
                 * Don't execute Freemius until plugin was fully loaded at least once,
                 * to give the opportunity for the activation hook to run before pinging
                 * the API for connectivity test. This logic is relevant for the
                 * identification of new plugin install vs. plugin update.
                 *
                 * @author Vova Feldman (@svovaf)
                 * @since  1.1.9
                 */
                return true;
            }

            if ( $this->is_activation_mode() ) {
                if ( ! is_admin() ) {
                    /**
                     * If in activation mode, don't execute Freemius outside the admin dashboard.
                     *
                     * @author Vova Feldman (@svovaf)
                     * @since  1.1.7.3
                     */
                    return true;
                }

                if ( ! WP_FS__IS_HTTP_REQUEST ) {
                    /**
                     * If in activation and executed without HTTP context (e.g. CLI, Cronjob),
                     * then don't start Freemius.
                     *
                     * @author Vova Feldman (@svovaf)
                     * @since  1.1.6.3
                     *
                     * @link   https://wordpress.org/support/topic/errors-in-the-freemius-class-when-running-in-wordpress-in-cli
                     */
                    return true;
                }

                if ( self::is_cron() ) {
                    /**
                     * If in activation mode, don't execute Freemius during wp crons
                     * (wp crons have HTTP context - called as HTTP request).
                     *
                     * @author Vova Feldman (@svovaf)
                     * @since  1.1.7.3
                     */
                    return true;
                }

                if ( self::is_ajax() ) {
                    /**
                     * During activation, if running in AJAX mode, unless there's a sticky
                     * connectivity issue notice, don't run Freemius.
                     *
                     * @author Vova Feldman (@svovaf)
                     * @since  1.1.7.3
                     */
                    return true;
                }
            }

            return false;
        }

        /**
         * Triggered after code type has changed.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.9.1
         */
        function _after_code_type_change() {
            $this->_logger->entrance();

            if ( $this->is_theme() ) {
                // Expire the cache of the previous tabs since the theme may
                // have setting updates after code type has changed.
                $this->_cache->expire( 'tabs' );
                $this->_cache->expire( 'tabs_stylesheets' );
            }

            if ( ! $this->is_addon() ) {
                add_action(
                    is_admin() ? 'admin_init' : 'init',
                    array( &$this, '_plugin_code_type_changed' )
                );
            }

            if ( $this->is_registered() && $this->is_premium() ) {
                // Purge cached payments after switching to the premium version.
                // @todo This logic doesn't handle purging the cache for serviceware module upgrade.
                $this->get_api_user_scope()->purge_cache( "/plugins/{$this->_module_id}/payments.json?include_addons=true" );
            }
        }

        /**
         * Handles plugin's code type change (free <--> premium).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         */
        function _plugin_code_type_changed() {
            $this->_logger->entrance();

            if ( $this->is_premium() ) {
                $this->reconnect_locally();

                // Activated premium code.
                $this->do_action( 'after_premium_version_activation' );

                // Remove all sticky messages related to download of the premium version.
                $this->_admin_notices->remove_sticky( array(
                    'trial_started',
                    'plan_upgraded',
                    'plan_changed',
                    'license_activated',
                ) );

                $notice = '';
                if ( ! $this->is_only_premium() ) {
                    $notice = sprintf( $this->get_text_inline( 'Premium %s version was successfully activated.', 'premium-activated-message' ), $this->_module_type );
                }

                $license_notice = $this->get_license_network_activation_notice();
                if ( ! empty( $license_notice ) ) {
                    $notice .= ' ' . $license_notice;
                }

                if ( ! empty( $notice ) ) {
                    $this->_admin_notices->add_sticky(
                        trim( $notice ),
                        'premium_activated',
                        $this->get_text_x_inline( 'W00t',
                            'Used to express elation, enthusiasm, or triumph (especially in electronic communication).', 'woot' ) . '!'
                    );
                }
            } else {
                // Remove sticky message related to premium code activation.
                $this->_admin_notices->remove_sticky( 'premium_activated' );

                // Activated free code (after had the premium before).
                $this->do_action( 'after_free_version_reactivation' );

                if ( $this->is_paying() && ! $this->is_premium() ) {
                    $this->add_complete_upgrade_instructions_notice(
                        sprintf(
                        /* translators: %s: License type (e.g. you have a professional license) */
                            $this->get_text_inline( 'You have a %s license.', 'you-have-x-license' ),
                            $this->get_plan_title()
                        ),
                        'plan_upgraded'
                    );
                }
            }

            if ( $this->is_registered() ) {
                // Schedule code type changes event.
                $this->schedule_install_sync();
            }

            /**
             * Unregister the uninstall hook for the other version of the plugin (with different code type) to avoid
             * triggering a fatal error when uninstalling that plugin. For example, after deactivating the "free" version
             * of a specific plugin, its uninstall hook should be unregistered after the "premium" version has been
             * activated. If we don't do that, a fatal error will occur when we try to uninstall the "free" version since
             * the main file of the "free" version will be loaded first before calling the hooked callback. Since the
             * free and premium versions are almost identical (same class or have same functions), a fatal error like
             * "Cannot redeclare class MyClass" or "Cannot redeclare my_function()" will occur.
             */
            $this->unregister_uninstall_hook();

            $this->clear_module_main_file_cache();

            // Update is_premium of latest version.
            $this->_storage->prev_is_premium = $this->_plugin->is_premium;
        }

        #endregion

        #----------------------------------------------------------------------------------
        #region Add-ons
        #----------------------------------------------------------------------------------

        /**
         * Check if add-on installed and activated on site.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param string|number $id_or_slug
         * @param bool|null     $is_premium Since 1.2.1.7 can check for specified add-on version.
         *
         * @return bool
         */
        function is_addon_activated( $id_or_slug, $is_premium = null ) {
            $this->_logger->entrance();

            $addon_id     = self::get_module_id( $id_or_slug );
            $is_activated = self::has_instance( $addon_id );

            if ( ! $is_activated ) {
                return false;
            }

            if ( is_bool( $is_premium ) ) {
                // Check if the specified code version is activate.
                $addon        = $this->get_addon_instance( $addon_id );
                $is_activated = ( $is_premium === $addon->is_premium() );
            }

            return $is_activated;
        }

        /**
         * Check if add-on was connected to install
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7
         *
         * @param  string|number $id_or_slug
         *
         * @return bool
         */
        function is_addon_connected( $id_or_slug ) {
            $this->_logger->entrance();

            $sites = self::get_all_sites( WP_FS__MODULE_TYPE_PLUGIN );

            $addon_id = self::get_module_id( $id_or_slug );
            $addon    = $this->get_addon( $addon_id );
            $slug     = $addon->slug;
            if ( ! isset( $sites[ $slug ] ) ) {
                return false;
            }

            $site = $sites[ $slug ];

            $plugin = FS_Plugin_Manager::instance( $addon_id )->get();

            if ( $plugin->parent_plugin_id != $this->_plugin->id ) {
                // The given slug do NOT belong to any of the plugin's add-ons.
                return false;
            }

            return ( is_object( $site ) &&
                     is_numeric( $site->id ) &&
                     is_numeric( $site->user_id ) &&
                     FS_Plugin_Plan::is_valid_id( $site->plan_id )
            );
        }

        /**
         * Determines if add-on installed.
         *
         * NOTE: This is a heuristic and only works if the folder/file named as the slug.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param  string|number $id_or_slug
         *
         * @return bool
         */
        function is_addon_installed( $id_or_slug ) {
            $this->_logger->entrance();

            $addon_id = self::get_module_id( $id_or_slug );

            return file_exists( fs_normalize_path( WP_PLUGIN_DIR . '/' . $this->get_addon_basename( $addon_id ) ) );
        }

        /**
         * Get add-on basename.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param  string|number $id_or_slug
         *
         * @return string
         */
        function get_addon_basename( $id_or_slug ) {
            $addon_id = self::get_module_id( $id_or_slug );

            if ( $this->is_addon_activated( $addon_id ) ) {
                return self::instance( $addon_id )->get_plugin_basename();
            }

            $addon            = $this->get_addon( $addon_id );
            $premium_basename = "{$addon->premium_slug}/{$addon->slug}.php";

            if ( file_exists( fs_normalize_path( WP_PLUGIN_DIR . '/' . $premium_basename ) ) ) {
                return $premium_basename;
            }

            $all_plugins = $this->get_all_plugins();

            foreach ( $all_plugins as $basename => $data ) {
                if ( $addon->slug === $data['slug'] ||
                     $addon->premium_slug === $data['slug']
                ) {
                    return $basename;
                }
            }

            $free_basename = "{$addon->slug}/{$addon->slug}.php";

            return $free_basename;
        }

        /**
         * Get installed add-ons instances.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @return Freemius[]
         */
        function get_installed_addons() {
            if ( $this->is_addon() ) {
                // Add-on cannot have add-ons.
                return array();
            }

            $installed_addons = array();

            foreach ( self::$_instances as $instance ) {
                if ( $instance->is_addon_of( $this->_plugin->id ) ) {
                    $installed_addons[] = $instance;
                }
            }

            return $installed_addons;
        }

        /**
         * Check if any add-ons of the plugin are installed.
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.1.1
         *
         * @return bool
         */
        function has_installed_addons() {
            if ( ! $this->has_addons() ) {
                return false;
            }

            foreach ( self::$_instances as $instance ) {
                if ( $instance->is_addon() && is_object( $instance->_parent_plugin ) ) {
                    if ( $this->_plugin->id == $instance->_parent_plugin->id ) {
                        return true;
                    }
                }
            }

            return false;
        }

        /**
         * Tell Freemius that the current plugin is an add-on.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param number $parent_plugin_id The parent plugin ID
         */
        function init_addon( $parent_plugin_id ) {
            $this->_plugin->parent_plugin_id = $parent_plugin_id;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @return bool
         */
        function is_addon() {
            return (
                isset( $this->_plugin->parent_plugin_id ) &&
                is_numeric( $this->_plugin->parent_plugin_id )
            );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.3.2
         *
         * @param number $parent_product_id
         *
         * @return bool
         */
        function is_addon_of( $parent_product_id ) {
            return (
                $this->is_addon() &&
                $parent_product_id == $this->_plugin->parent_plugin_id
            );
        }

        /**
         * Deactivate add-on if it's premium only and the user does't have a valid license.
         *
         * @param bool $is_after_trial_cancel
         *
         * @return bool If add-on was deactivated.
         */
        private function deactivate_premium_only_addon_without_license( $is_after_trial_cancel = false ) {
            if ( ! $this->has_free_plan() &&
                 ! $this->has_features_enabled_license() &&
                 ! $this->_has_premium_license()
            ) {
                if ( $this->is_registered() ) {
                    // IF wrapper is turned off because activation_timestamp is currently only stored for plugins (not addons).
                    //                if (empty($this->_storage->activation_timestamp) ||
                    //                    (WP_FS__SCRIPT_START_TIME - $this->_storage->activation_timestamp) > 30
                    //                ) {
                    /**
                     * @todo When it's first fail, there's no reason to try and re-sync because the licenses were just synced after initial activation.
                     *
                     * Retry syncing the user add-on licenses.
                     */
                    // Sync licenses.
                    $this->_sync_licenses();
                    //                }

                    // Try to activate premium license.
                    $this->_activate_license( true );
                }

                if ( ! $this->has_free_plan() &&
                     ! $this->has_features_enabled_license() &&
                     ! $this->_has_premium_license()
                ) {
                    // @todo Check if deactivate plugins also call the deactivation hook.

                    $this->_parent->_admin_notices->add_sticky(
                        sprintf(
                            ( $is_after_trial_cancel ?
                                $this->_parent->get_text_inline(
                                    '%s free trial was successfully cancelled. Since the add-on is premium only it was automatically deactivated. If you like to use it in the future, you\'ll have to purchase a license.',
                                    'addon-trial-cancelled-message'
                                ) :
                                $this->_parent->get_text_inline(
                                    '%s is a premium only add-on. You have to purchase a license first before activating the plugin.',
                                    'addon-no-license-message'
                                )
                            ),
                            '<b>' . $this->_plugin->title . '</b>'
                        ) . ' ' . sprintf(
                            '<a href="%s" aria-label="%s" class="button button-primary" style="margin-left: 10px; vertical-align: middle;">%s &nbsp;&#10140;</a>',
                            $this->_parent->addon_url( $this->_slug ),
                            esc_attr( sprintf( $this->_parent->get_text_inline( 'More information about %s', 'more-information-about-x' ), $this->_plugin->title ) ),
                            $this->_parent->get_text_inline( 'Purchase License', 'purchase-license' )
                        ),
                        'no_addon_license_' . $this->_slug,
                        ( $is_after_trial_cancel ? '' : $this->_parent->get_text_x_inline( 'Oops', 'exclamation', 'oops' ) . '...' ),
                        ( $is_after_trial_cancel ? 'success' : 'error' )
                    );

                    deactivate_plugins( array( $this->_plugin_basename ), true );

                    return true;
                }
            }

            return false;
        }

        #endregion

        #----------------------------------------------------------------------------------
        #region Sandbox
        #----------------------------------------------------------------------------------

        /**
         * Set Freemius into sandbox mode for debugging.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @param string $secret_key
         */
        function init_sandbox( $secret_key ) {
            $this->_plugin->secret_key = $secret_key;

            // Update plugin details.
            FS_Plugin_Manager::instance( $this->_module_id )->update( $this->_plugin, true );
        }

        /**
         * Check if running payments in sandbox mode.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @return bool
         */
        function is_payments_sandbox() {
            return ( ! $this->is_live() ) || isset( $this->_plugin->secret_key );
        }

        #endregion

        /**
         * Check if running test vs. live plugin.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         *
         * @return bool
         */
        function is_live() {
            return $this->_plugin->is_live;
        }

        /**
         * Check if super-admin skipped connection for all sites in the network.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         */
        function is_network_anonymous() {
            if ( ! $this->_is_network_active ) {
                return false;
            }

            $is_anonymous_ms = $this->_storage->get( 'is_anonymous_ms' );

            if ( empty( $is_anonymous_ms ) ) {
                return false;
            }

            return $is_anonymous_ms['is'];
        }

        /**
         * Check if super-admin opted-in for all sites in the network.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         */
        function is_network_connected() {
            if ( ! $this->_is_network_active ) {
                return false;
            }

            return $this->_storage->get( 'is_network_connected' );
        }

        /**
         * Check if the user skipped connecting the account with Freemius.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.7
         *
         * @return bool
         */
        function is_anonymous() {
            if ( ! isset( $this->_is_anonymous ) ) {
                if ( $this->is_network_anonymous() ) {
                    $this->_is_anonymous = true;
                } else if ( fs_is_network_admin() ) {
                    /**
                     * When not-network-anonymous, yet, running in the network admin, consider as anonymous only when ALL non-delegated sites are set to anonymous.
                     */
                    $non_delegated_sites = $this->get_non_delegated_blog_ids();

                    foreach ( $non_delegated_sites as $blog_id ) {
                        $is_anonymous = $this->_storage->get( 'is_anonymous', false, $blog_id );

                        if ( empty( $is_anonymous ) || false === $is_anonymous[ 'is' ] ) {
                            $this->_is_anonymous = false;
                            break;
                        }
                    }

                    if ( false !== $this->_is_anonymous ) {
                        $this->_is_anonymous = true;
                    }
                } else {
                    if ( ! isset( $this->_storage->is_anonymous ) ) {
                        // Not skipped.
                        $this->_is_anonymous = false;
                    } else if ( is_bool( $this->_storage->is_anonymous ) ) {
                        // For back compatibility, since the variable was boolean before.
                        $this->_is_anonymous = $this->_storage->is_anonymous;

                        // Upgrade stored data format to 1.1.3 format.
                        $this->set_anonymous_mode( $this->_storage->is_anonymous );
                    } else {
                        // Version 1.1.3 and later.
                        $this->_is_anonymous = $this->_storage->is_anonymous['is'];
                    }
                }
            }

            return $this->_is_anonymous;
        }

        /**
         * Check if the user skipped the connection of a specified site.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int $blog_id
         *
         * @return bool
         */
        function is_anonymous_site( $blog_id = 0 ) {
            if ( $this->is_network_anonymous() ) {
                return true;
            }

            $is_anonymous = $this->_storage->get( 'is_anonymous', false, $blog_id );

            if ( empty( $is_anonymous ) ) {
                return false;
            }

            return $is_anonymous['is'];
        }

        /**
         * Check if user connected his account and install pending email activation.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.7
         *
         * @return bool
         */
        function is_pending_activation() {
            return $this->_storage->get( 'is_pending_activation', false );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         */
        private function clear_pending_activation_mode() {
            // Remove the pending activation sticky notice (if it still exists).
            $this->_admin_notices->remove_sticky( 'activation_pending' );

            // Clear the plugin's pending activation mode.
            unset( $this->_storage->is_pending_activation );
        }

        /**
         * Check if plugin must be WordPress.org compliant.
         *
         * @since 1.0.7
         *
         * @return bool
         */
        function is_org_repo_compliant() {
            return $this->_is_org_compliant;
        }

        #--------------------------------------------------------------------------------
        #region WP Cron Common
        #--------------------------------------------------------------------------------

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string $name Cron name.
         *
         * @return object
         */
        private function get_cron_data( $name ) {
            $this->_logger->entrance( $name );

            /**
             * @var object $cron_data
             */
            return $this->_storage->get( "{$name}_cron", null );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string $name Cron name.
         */
        private function clear_cron_data( $name ) {
            $this->_logger->entrance( $name );

            $this->_storage->remove( "{$name}_cron" );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string $name         Cron name.
         * @param int    $cron_blog_id The cron executing blog ID.
         */
        private function set_cron_data( $name, $cron_blog_id = 0 ) {
            $this->_logger->entrance( $name );

            $this->_storage->store( "{$name}_cron", (object) array(
                'version'     => $this->get_plugin_version(),
                'blog_id'     => $cron_blog_id,
                'sdk_version' => $this->version,
                'timestamp'   => WP_FS__SCRIPT_START_TIME,
                'on'          => true,
            ) );
        }

        /**
         * Get the cron's executing blog ID.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string $name Cron name.
         *
         * @return int
         */
        private function get_cron_blog_id( $name ) {
            $this->_logger->entrance( $name );

            if ( ! is_multisite() ) {
                // Not a multisite.
                return 0;
            }

            $cron_data = $this->get_cron_data( $name );

            return ( is_object( $cron_data ) && is_numeric( $cron_data->blog_id ) ) ?
                $cron_data->blog_id :
                0;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string $name Cron name.
         *
         * @return bool
         */
        private function is_cron_on( $name ) {
            $this->_logger->entrance( $name );

            /**
             * @var object $cron_data
             */
            $cron_data = $this->get_cron_data( $name );

            return ( ! is_null( $cron_data ) && true === $cron_data->on );
        }

        /**
         * Unix timestamp for previous cron execution or false if never executed.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string $name Cron name.
         *
         * @return int|false
         */
        private function cron_last_execution( $name ) {
            $this->_logger->entrance( $name );

            return $this->_storage->get( "{$name}_timestamp" );
        }

        /**
         * Set cron execution time to now.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string $name Cron name.
         */
        private function set_cron_execution_timestamp( $name ) {
            $this->_logger->entrance( $name );

            $this->_storage->store( "{$name}_timestamp", time() );
        }

        /**
         * Sets the keepalive time to now.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.2.3
         *
         * @param bool|null $use_network_level_storage
         */
        private function set_keepalive_timestamp( $use_network_level_storage = null ) {
            $this->_logger->entrance();

            $this->_storage->store( 'keepalive_timestamp', time(), $use_network_level_storage );
        }

        /**
         * Check if cron was executed in the last $period of seconds.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string $name   Cron name.
         * @param int    $period In seconds
         *
         * @return bool
         */
        private function is_cron_executed( $name, $period = WP_FS__TIME_24_HOURS_IN_SEC ) {
            $this->_logger->entrance( $name );

            $last_execution = $this->cron_last_execution( $name );

            if ( ! is_numeric( $last_execution ) ) {
                return false;
            }

            return ( $last_execution > ( WP_FS__SCRIPT_START_TIME - $period ) );
        }

        /**
         * WP Cron is executed on a site level. When running in a multisite network environment
         * with the network integration activated, for optimization reasons, we are consolidating
         * the installs data sync cron to be executed only from a single site.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int $except_blog_id Target any except the excluded blog ID.
         *
         * @return int
         */
        private function get_cron_target_blog_id( $except_blog_id = 0 ) {
            if ( ! is_multisite() ) {
                return 0;
            }

            if ( $this->_is_network_active ) {
                $network_install_blog_id = $this->_storage->network_install_blog_id;

                if (
                    is_numeric( $network_install_blog_id ) &&
                    $except_blog_id != $network_install_blog_id &&
                    self::is_site_active( $network_install_blog_id )
                ) {
                    // Try to run cron from the main network blog.
                    $install = $this->get_install_by_blog_id( $network_install_blog_id );

                    if (
                        is_object( $install ) &&
                        $this->is_tracking_allowed( $network_install_blog_id, $install )
                    ) {
                        return $network_install_blog_id;
                    }
                }
            }

            // Get first opted-in blog ID with active tracking.
            $installs = $this->get_blog_install_map();
            foreach ( $installs as $blog_id => $install ) {
                if ( $except_blog_id != $blog_id &&
                     self::is_site_active( $blog_id ) &&
                     $this->is_tracking_allowed( $blog_id, $install )
                ) {
                    return $blog_id;
                }
            }

            return 0;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string $name             Cron name.
         * @param string $action_tag       Callback action tag.
         * @param bool   $is_network_clear If set to TRUE, clear sync cron even if there are installs that are still connected.
         */
        private function clear_cron( $name, $action_tag = '', $is_network_clear = false ) {
            $this->_logger->entrance( $name );

            if ( ! $this->is_cron_on( $name ) ) {
                return;
            }

            $clear_cron = true;
            if ( ! $is_network_clear && $this->_is_network_active ) {
                $installs = $this->get_blog_install_map();

                foreach ( $installs as $blog_id => $install ) {
                    /**
                     * @var FS_Site $install
                     */
                    if ( $this->is_tracking_allowed( $blog_id, $install ) ) {
                        $clear_cron = false;
                        break;
                    }
                }
            }

            if ( ! $clear_cron ) {
                return;
            }

            $cron_blog_id = $this->get_cron_blog_id( $name );

            $this->clear_cron_data( $name );

            if ( 0 < $cron_blog_id ) {
                switch_to_blog( $cron_blog_id );
            }

            if ( empty( $action_tag ) ) {
                $action_tag = $name;
            }

            wp_clear_scheduled_hook( $this->get_action_tag( $action_tag ) );

            if ( 0 < $cron_blog_id ) {
                restore_current_blog();
            }
        }

        /**
         * Unix timestamp for next cron execution or false if not scheduled.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string $name       Cron name.
         * @param string $action_tag Callback action tag.
         *
         * @return int|false
         */
        private function get_next_scheduled_cron( $name, $action_tag = '' ) {
            $this->_logger->entrance( $name );

            if ( ! $this->is_cron_on( $name ) ) {
                return false;
            }

            $cron_blog_id = $this->get_cron_blog_id( $name );

            if ( 0 < $cron_blog_id ) {
                switch_to_blog( $cron_blog_id );
            }

            if ( empty( $action_tag ) ) {
                $action_tag = $name;
            }

            $next_scheduled = wp_next_scheduled( $this->get_action_tag( $action_tag ) );

            if ( 0 < $cron_blog_id ) {
                restore_current_blog();
            }

            return $next_scheduled;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string $name            Cron name.
         * @param string $action_tag      Callback action tag.
         * @param string $recurrence      'single' or 'daily'.
         * @param int    $start_at        Defaults to now.
         * @param bool   $randomize_start If true, schedule first job randomly during the next 12 hours. Otherwise, schedule job to start right away.
         * @param int    $except_blog_id  Target any except the excluded blog ID.
         */
        private function schedule_cron(
            $name,
            $action_tag = '',
            $recurrence = 'single',
            $start_at = WP_FS__SCRIPT_START_TIME,
            $randomize_start = true,
            $except_blog_id = 0
        ) {
            $this->_logger->entrance( $name );

            $this->clear_cron( $name, $action_tag, true );

            $cron_blog_id = $this->get_cron_target_blog_id( $except_blog_id );

            if ( is_multisite() && 0 == $cron_blog_id ) {
                // Don't schedule cron since couldn't find a target blog.
                return;
            }

            if ( 0 < $cron_blog_id ) {
                switch_to_blog( $cron_blog_id );
            }

            if ( 'daily' === $recurrence ) {
                if ( $randomize_start ) {
                    // Schedule first sync with a random 12 hour time range from now.
                    $start_at += rand( 0, ( WP_FS__TIME_24_HOURS_IN_SEC / 2 ) );
                }

                // Schedule daily WP cron.
                wp_schedule_event(
                    $start_at,
                    'daily',
                    $this->get_action_tag( $action_tag )
                );
            } else if ( 'single' === $recurrence ) {
                // Schedule single cron.
                wp_schedule_single_event(
                    $start_at,
                    $this->get_action_tag( $action_tag )
                );
            }

            $this->set_cron_data( $name, $cron_blog_id );

            if ( 0 < $cron_blog_id ) {
                restore_current_blog();
            }
        }

        /**
         * Consolidated cron execution for performance optimization. The max number of API requests is based on the number of unique opted-in users.
         * that doesn't halt page loading.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string   $name     Cron name.
         * @param callable $callable The function that should be executed.
         */
        private function execute_cron( $name, $callable ) {
            $this->_logger->entrance( $name );

            // Store the last time data sync was executed.
            $this->set_cron_execution_timestamp( $name );

            // Check if API is temporary down.
            if ( FS_Api::is_temporary_down() ) {
                return;
            }

            // @todo Add logic that identifies API latency, and reschedule the next background sync randomly between 8-16 hours.

            $users_2_blog_ids = array();

            if ( ! is_multisite() ) {
                // Add dummy blog.
                $users_2_blog_ids[0] = array( 0 );
            } else {
                $installs = $this->get_blog_install_map();
                foreach ( $installs as $blog_id => $install ) {
                    if ( $this->is_tracking_allowed( $blog_id, $install ) ) {
                        if ( ! isset( $users_2_blog_ids[ $install->user_id ] ) ) {
                            $users_2_blog_ids[ $install->user_id ] = array();
                        }

                        $users_2_blog_ids[ $install->user_id ][] = $blog_id;
                    }
                }
            }

            $current_blog_id = get_current_blog_id();

            foreach ( $users_2_blog_ids as $user_id => $blog_ids ) {
                if ( 0 < $blog_ids[0] ) {
                    $this->switch_to_blog( $blog_ids[0] );
                }

                call_user_func_array( $callable, array( $blog_ids, ( is_multisite() ? $current_blog_id : null ) ) );

                foreach ( $blog_ids as $blog_id ) {
                    $this->do_action( "after_{$name}_cron", $blog_id );
                }
            }

            if ( is_multisite() ) {
                $this->switch_to_blog( $current_blog_id, fs_is_network_admin() ? $this->get_network_install() : null );

                $this->do_action( "after_{$name}_cron_multisite" );
            }
        }

        #endregion

        #----------------------------------------------------------------------------------
        #region Daily Sync Cron
        #----------------------------------------------------------------------------------


        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return bool
         */
        private function is_sync_cron_scheduled() {
            return $this->is_cron_on( 'sync' );
        }

        /**
         * Get the sync cron's executing blog ID.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return int
         */
        private function get_sync_cron_blog_id() {
            return $this->get_cron_blog_id( 'sync' );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         */
        private function run_manual_sync() {
            if ( ! $this->is_user_admin() ) {
                return;
            }

            // Run manual sync.
            $this->_sync_cron();

            // Reschedule next cron to run 24 hours from now (performance optimization).
            $this->schedule_sync_cron( time() + WP_FS__TIME_24_HOURS_IN_SEC, false );
        }

        /**
         * Data sync cron job. Replaces the background sync non blocking HTTP request
         * that doesn't halt page loading.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         * @since  2.0.0   Consolidate all the data sync into the same cron for performance optimization. The max number of API requests is based on the number of unique opted-in users.
         */
        function _sync_cron() {
            $this->_logger->entrance();

            $this->execute_cron( 'sync', array( &$this, '_sync_cron_method' ) );
        }

        /**
         * The actual data sync cron logic.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int[]    $blog_ids
         * @param int|null $current_blog_id @since 2.2.3. This is passed from the `execute_cron` method and used by the
         *                                  `_sync_plugin_license` method in order to switch to the previous blog when sending
         *                                  updates for a single site in case `execute_cron` has switched to a different blog.
         */
        function _sync_cron_method( array $blog_ids, $current_blog_id = null ) {
            if ( $this->is_registered() ) {
                if ( $this->has_paid_plan() ) {
                    // Initiate background plan sync.
                    $this->_sync_license( true, false, $current_blog_id );

                    if ( $this->is_paying() ) {
                        // Check for premium plugin updates.
                        $this->check_updates( true );
                    }
                } else {
                    // Sync install(s) (only if something changed locally).
                    if ( 1 < count( $blog_ids ) ) {
                        $this->sync_installs();
                    } else {
                        $this->sync_install();
                    }

                    $this->maybe_sync_install_user();
                }
            }
        }

        /**
         * Check if sync was executed in the last $period of seconds.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         *
         * @param int $period In seconds
         *
         * @return bool
         */
        private function is_sync_executed( $period = WP_FS__TIME_24_HOURS_IN_SEC ) {
            return $this->is_cron_executed( 'sync', $period );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         *
         * @return bool
         */
        private function is_sync_cron_on() {
            return $this->is_cron_on( 'sync' );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.5.0
         */
        private function maybe_schedule_sync_cron() {
            $next_schedule = $this->next_sync_cron();

            // The event is properly scheduled, so no need to reschedule it.
            if (
                is_numeric( $next_schedule ) &&
                $next_schedule > time()
            ) {
                return;
            }

            $this->schedule_sync_cron();
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         *
         * @param int  $start_at        Defaults to now.
         * @param bool $randomize_start If true, schedule first job randomly during the next 12 hours. Otherwise, schedule job to start right away.
         * @param int  $except_blog_id  Since 2.0.0 when running in a multisite network environment, the cron execution is consolidated. This param allows excluding excluded specified blog ID from being the cron executor.
         */
        private function schedule_sync_cron(
            $start_at = WP_FS__SCRIPT_START_TIME,
            $randomize_start = true,
            $except_blog_id = 0
        ) {
            $this->schedule_cron(
                'sync',
                'data_sync',
                'daily',
                $start_at,
                $randomize_start,
                $except_blog_id
            );
        }

        /**
         * Add the actual sync function to the cron job hook.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         */
        private function hook_callback_to_sync_cron() {
            $this->add_action( 'data_sync', array( &$this, '_sync_cron' ) );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         *
         * @param bool $is_network_clear Since 2.0.0 If set to TRUE, clear sync cron even if there are installs that are still connected.
         */
        private function clear_sync_cron( $is_network_clear = false ) {
            $this->_logger->entrance();

            $this->clear_cron( 'sync', 'data_sync', $is_network_clear );
        }

        /**
         * Unix timestamp for next sync cron execution or false if not scheduled.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         *
         * @return int|false
         */
        function next_sync_cron() {
            return $this->get_next_scheduled_cron( 'sync', 'data_sync' );
        }

        /**
         * Unix timestamp for previous sync cron execution or false if never executed.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         *
         * @return int|false
         */
        function last_sync_cron() {
            return $this->cron_last_execution( 'sync' );
        }

        #endregion Daily Sync Cron ------------------------------------------------------------------

        #----------------------------------------------------------------------------------
        #region Async Install Sync
        #----------------------------------------------------------------------------------

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         *
         * @return bool
         */
        private function is_install_sync_scheduled() {
            return $this->is_cron_on( 'install_sync' );
        }

        /**
         * Get the sync cron's executing blog ID.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return int
         */
        private function get_install_sync_cron_blog_id() {
            return $this->get_cron_blog_id( 'install_sync' );
        }

        /**
         * Instead of running blocking install sync event, execute non blocking scheduled wp-cron.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         *
         * @param int $except_blog_id Since 2.0.0 when running in a multisite network environment, the cron execution is consolidated. This param allows excluding excluded specified blog ID from being the cron executor.
         */
        private function schedule_install_sync( $except_blog_id = 0 ) {
            if ( $this->is_clone() ) {
                return;
            }

            $this->schedule_cron( 'install_sync', 'install_sync', 'single', WP_FS__SCRIPT_START_TIME, false, $except_blog_id );
        }

        /**
         * Unix timestamp for previous install sync cron execution or false if never executed.
         *
         * @todo   There's some very strange bug that $this->_storage->install_sync_timestamp value is not being updated. But for sure the sync event is working.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         *
         * @return int|false
         */
        function last_install_sync() {
            return $this->cron_last_execution( 'install_sync' );
        }

        /**
         * Unix timestamp for next install sync cron execution or false if not scheduled.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         *
         * @return int|false
         */
        function next_install_sync() {
            return $this->get_next_scheduled_cron( 'install_sync', 'install_sync' );
        }

        /**
         * Add the actual install sync function to the cron job hook.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         */
        private function hook_callback_to_install_sync() {
            $this->add_action( 'install_sync', array( &$this, '_run_sync_install' ) );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         *
         * @param bool $is_network_clear Since 2.0.0 If set to TRUE, clear sync cron even if there are installs that are still connected.
         */
        private function clear_install_sync_cron( $is_network_clear = false ) {
            $this->_logger->entrance();

            $this->clear_cron( 'install_sync', 'install_sync', $is_network_clear );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         * @since  2.0.0   Consolidate all the data sync into the same cron for performance optimization. The max number of API requests is based on the number of unique opted-in users.
         */
        public function _run_sync_install() {
            $this->_logger->entrance();

            $this->execute_cron( 'sync', array( &$this, '_sync_install_cron_method' ) );
        }

        /**
         * The actual install(s) sync cron logic.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int[]    $blog_ids
         * @param int|null $current_blog_id
         */
        function _sync_install_cron_method( array $blog_ids, $current_blog_id = null ) {
            if ( $this->is_registered() ) {
                if ( 1 < count( $blog_ids ) ) {
                    $this->sync_installs( array(), true );
                } else {
                    $this->sync_install( array(), true );
                }

                $this->maybe_sync_install_user();
            }
        }

        #endregion Async Install Sync ------------------------------------------------------------------

        /**
         * Show a notice that activation is currently pending.
         *
         * @todo Add some sort of mechanism to allow users to update the email address they would like to opt-in with when $is_suspicious_email is true.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.7
         *
         * @param bool|string $email_address
         * @param bool        $is_pending_trial Since 1.2.1.5
         * @param bool        $is_suspicious_email Since 2.5.0 Set to true when there's an indication that email address the user opted in with is fake/dummy/placeholder.
         * @param bool        $has_upgrade_context Since 2.5.3
         * @param bool        $support_email_address Since 2.5.3
         */
        function _add_pending_activation_notice(
            $email_address = false,
            $is_pending_trial = false,
            $is_suspicious_email = false,
            $has_upgrade_context = false,
            $support_email_address = false
        ) {
            if ( ! is_string( $email_address ) ) {
                $current_user  = self::_get_current_wp_user();
                $email_address = $current_user->user_email;
            }

            $formatted_message_args = array(
                "<b>{$this->get_plugin_name()}</b>",
                "<b>{$email_address}</b>",
            );

            if ( ! $has_upgrade_context || ! fs_is_network_admin() ) {
                /* translators: %3$s: action (e.g.: "start the trial" or "complete the opt-in") */
                $formatted_message = $this->get_text_inline( 'You should receive a confirmation email for %1$s to your mailbox at %2$s. Please make sure you click the button in that email to %3$s.', 'pending-activation-message' );

                $formatted_message_args[] = $is_pending_trial ?
                    $this->get_text_inline( 'start the trial', 'start-the-trial' ) :
                    $this->get_text_inline( 'complete the opt-in', 'complete-the-opt-in' );

                $notice_title = $this->get_text_inline( 'Thanks!', 'thanks' );
            } else {
                /* translators: %3$s: What the user is expected to receive via email (e.g.: "the installation instructions" or "a license key") */
                $formatted_message = $this->get_text_inline( 'You should receive %3$s for %1$s to your mailbox at %2$s in the next 5 minutes.' );

                if ( $this->has_release_on_freemius() ) {
                    $formatted_message_args[] = $this->get_text_x_inline(
                        'the installation instructions',
                        'Part of the message telling the user what they should receive via email.',
                        'the-installation-instructions-phrase'
                    );
                } else {
                    $formatted_message_args[] = $this->get_text_x_inline(
                        'a license key',
                        'Part of the message telling the user what they should receive via email.',
                        'a-license-key-phrase'
                    );

                    $formatted_message .= ( ' ' . sprintf(
                        /* translators: %s: activation link (e.g.: <a>Click here</a>) */
                        $this->get_text_inline( '%s to activate the license once you get it.', 'license-activation-link-message' ),
                        sprintf(
                            '<b><a href="%s">%s</a></b>',
                            $this->get_activation_url( array(
                                'fs_action'       => 'reset_pending_activation_mode',
                                'require_license' => 'true',
                                'fs_unique_affix' => $this->get_unique_affix(),
                            ) ),
                            $this->get_text_x_inline( 'Click here', 'Part of an activation link message.', 'click-here' )
                        )
                    ) );
                }

                $formatted_message_args[] = ( ! empty( $support_email_address ) ) ?
                    ( "<b>{$support_email_address}</b>" ) :
                    $this->get_text_x_inline(
                        "the product's support email address",
                        'Part of the message that tells the user to check their spam folder for a specific email.',
                        'product-support-email-address-phrase'
                    );

                $formatted_message .= ( ' ' . $this->get_text_inline( 'If you didn\'t get the email, try checking your spam folder or search for emails from %4$s.', 'check-spam-folder-message' ) );

                $notice_title = $this->get_text_inline( 'Thanks for upgrading.', 'after-upgrade-thank-you-message' );
            }

            $this->_admin_notices->add_sticky(
                vsprintf( $formatted_message, $formatted_message_args ),
                'activation_pending',
                $notice_title
            );
        }

        /**
         * Check if currently in plugin activation.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.4
         *
         * @return bool
         */
        function is_plugin_activation() {
            $result = get_transient( "fs_{$this->_module_type}_{$this->_slug}_activated" );

            return !empty($result);
        }

        /**
         *
         * NOTE: admin_menu action executed before admin_init.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.7
         */
        function _admin_init_action() {
            $is_migration = $this->is_migration();

            /**
             * Automatically redirect to connect/activation page after plugin activation.
             *
             * @since 1.1.7 Do NOT redirect to opt-in when running in network admin mode.
             */
            if ( $this->is_plugin_activation() ) {
                delete_transient( "fs_{$this->_module_type}_{$this->_slug}_activated" );

                if ( isset( $_GET['activate-multi'] ) ) {
                    /**
                     * Don't redirect if activating multiple plugins at once (bulk activation).
                     */
                } else if (
                    self::is_deactivation_snoozed() &&
                    (
                        // Either running the free code base.
                        ! $this->is_premium() ||
                        // Or if has a free version.
                        ! $this->is_only_premium() ||
                        // If premium only, don't redirect if license is activated.
                        ( $this->is_registered() && ! $this->can_use_premium_code() )
                    )
                ) {
                    /**
                     * Don't redirect if activating during the deactivation snooze period (aka troubleshooting), unless activating a paid product version that the admin didn't enter its license key yet.
                     */
                } else if ( ! $is_migration ) {
                    $this->_redirect_on_activation_hook();
                    return;
                }
            }

            if ( $is_migration ) {
                return;
            }

            if ( fs_request_is_action( $this->get_unique_affix() . '_skip_activation' ) ) {
                check_admin_referer( $this->get_unique_affix() . '_skip_activation' );

                $this->skip_connection( fs_is_network_admin() );

                fs_redirect( $this->get_after_activation_url( 'after_skip_url' ) );
            }

            if ( $this->is_network_activation_mode() &&
                 fs_request_is_action( $this->get_unique_affix() . '_delegate_activation' )
            ) {
                check_admin_referer( $this->get_unique_affix() . '_delegate_activation' );

                $this->delegate_connection();

                fs_redirect( $this->get_after_activation_url( 'after_delegation_url' ) );
            }

            $this->_add_upgrade_action_link();

            if ( ! ( ! $this->_is_network_active && fs_is_network_admin() ) &&
                 (
                     ( true === $this->_storage->require_license_activation ) ||
                     // Not registered nor anonymous.
                     ( ! $this->is_registered() && ! $this->is_anonymous() ) ||
                     // OR, network level and in network upgrade mode.
                     ( fs_is_network_admin() && $this->_is_network_active && $this->is_network_upgrade_mode() )
                 )
            ) {
                if ( ! $this->is_pending_activation() ) {
                    if ( ! $this->is_activation_page() ) {
                        /**
                         * If a user visits any other admin page before activating the premium-only theme with a valid
                         * license, reactivate the previous theme.
                         *
                         * @author Leo Fajardo (@leorw)
                         * @since  1.2.2
                         */
                        if ( $this->is_theme() &&
                             ! $this->has_settings_menu()  &&
                             ! isset( $_REQUEST['fs_action'] )  &&
                             $this->can_activate_previous_theme()
                        ) {
                            if ( $this->is_only_premium() ) {
                                $this->activate_previous_theme();
                                return;
                            }

                            if ( true === $this->_storage->require_license_activation ) {
                                $this->_storage->require_license_activation = false;
                            }
                        }

                        if ( ! fs_is_network_admin() &&
                             $this->is_network_activation_mode() &&
                             ! $this->is_delegated_connection()
                        ) {
                            return;
                        }

                        if ( $this->is_plugin_new_install() || $this->is_only_premium() ) {
                            if ( ! $this->_anonymous_mode &&
                                 ( ! $this->is_addon() || ! $this->_parent->is_anonymous() ) ) {
                                // Show notice for new plugin installations.
                                $this->_admin_notices->add(
                                    sprintf(
                                        $this->get_text_inline( 'You are just one step away - %s', 'you-are-step-away' ),
                                        sprintf( '<b><a href="%s">%s</a></b>',
                                            $this->get_activation_url( array(), ! $this->is_delegated_connection() ),
                                            sprintf( $this->get_text_x_inline( 'Complete "%s" Activation Now',
                                                '%s - plugin name. As complete "PluginX" activation now', 'activate-x-now' ), $this->get_plugin_name() )
                                        )
                                    ),
                                    '',
                                    'update-nag'
                                );
                            }
                        } else {
                            if ( $this->should_add_sticky_optin_notice() ) {
                                $this->add_sticky_optin_admin_notice();
                            }

                            if ( $this->has_filter( 'optin_pointer_element' ) ) {
                                // Don't show admin nag if plugin update.
                                wp_enqueue_script( 'wp-pointer' );
                                wp_enqueue_style( 'wp-pointer' );

                                $this->_enqueue_connect_essentials();

                                add_action( 'admin_print_footer_scripts', array(
                                    $this,
                                    '_add_connect_pointer_script'
                                ) );
                            }
                        }
                    }
                }

                if ( $this->show_opt_in_on_themes_page() &&
                     $this->is_activation_page()
                ) {
                    $this->_show_theme_activation_optin_dialog();
                }
            }
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return bool
         */
        private function should_add_sticky_optin_notice() {
            if ( $this->is_addon() && $this->_parent->is_anonymous() ) {
                return false;
            }

            if ( fs_is_network_admin() ) {
                if ( ! $this->_is_network_active ) {
                    return false;
                }

                if ( ! $this->is_network_activation_mode() ) {
                    return false;
                }

                return ! isset( $this->_storage->sticky_optin_added_ms );
            }

            if ( ! $this->is_activation_mode() ) {
                return false;
            }

            // If running from a blog admin and delegated the connection.
            return ! isset( $this->_storage->sticky_optin_added );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.0.0
         */
        private function add_sticky_optin_admin_notice() {
            if ( ! $this->_is_network_active || ! fs_is_network_admin() ) {
                $this->_storage->sticky_optin_added = true;
            } else {
                $this->_storage->sticky_optin_added_ms = true;
            }

            // Show notice for new plugin installations.
            $this->_admin_notices->add_sticky(
                sprintf(
                    $this->get_text_inline( 'We made a few tweaks to the %s, %s', 'few-plugin-tweaks' ),
                    $this->_module_type,
                    sprintf( '<b><a href="%s">%s</a></b>',
                        $this->get_activation_url(),
                        sprintf( $this->get_text_inline( 'Opt in to make "%s" better!', 'optin-x-now' ), $this->get_plugin_name() )
                    )
                ),
                'connect_account',
                '',
                'update-nag'
            );
        }

        /**
         * Enqueue connect requires scripts and styles.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.4
         */
        function _enqueue_connect_essentials() {
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'json2' );

            fs_enqueue_local_script( 'postmessage', 'nojquery.ba-postmessage.js' );
            fs_enqueue_local_script( 'fs-postmessage', 'postmessage.js' );
        }

        /**
         * Add connect / opt-in pointer.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.4
         */
        function _add_connect_pointer_script() {
            $vars            = array( 'id' => $this->_module_id );
            $pointer_content = fs_get_template( 'connect.php', $vars );
            ?>
            <script type="text/javascript">// <![CDATA[
                jQuery(document).ready(function ($) {
                    if ('undefined' !== typeof(jQuery().pointer)) {

                        var element = <?php echo $this->apply_filters( 'optin_pointer_element', '$("#non_existing_element");' ) ?>;

                        if (element.length > 0) {
                            var optin = $(element).pointer($.extend(true, {}, {
                                content     : <?php echo json_encode( $pointer_content ) ?>,
                                position    : {
                                    edge : 'left',
                                    align: 'center'
                                },
                                buttons     : function () {
                                    // Don't show pointer buttons.
                                    return '';
                                },
                                pointerWidth: 482
                            }, <?php echo $this->apply_filters( 'optin_pointer_options_json', '{}' ) ?>));

                            <?php
                            echo $this->apply_filters( 'optin_pointer_execute', "

                            optin.pointer('open');

                            // Tag the opt-in pointer with custom class.
                            $('.wp-pointer #fs_connect')
                                .parents('.wp-pointer.wp-pointer-top')
                                .addClass('fs-opt-in-pointer');

                            ", 'element', 'optin' ) ?>
                        }
                    }
                });
                // ]]></script>
            <?php
        }

        /**
         * Return current page's URL.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.7
         *
         * @return string
         */
        static function current_page_url() {
            $url = 'http';

            if ( isset( $_SERVER["HTTPS"] ) ) {
                if ( $_SERVER["HTTPS"] == "on" ) {
                    $url .= "s";
                }
            }
            $url .= "://";
            if ( $_SERVER["SERVER_PORT"] != "80" ) {
                $url .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
            } else {
                $url .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
            }

            return esc_url( $url );
        }

        /**
         * Check if the current page is the plugin's main admin settings page.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.7
         *
         * @return bool
         */
        function _is_plugin_page() {
            return fs_is_plugin_page( $this->_menu->get_raw_slug() ) ||
                   fs_is_plugin_page( $this->_slug );
        }

        /* Events
        ------------------------------------------------------------------------------------------------------------------*/
        /**
         * Delete site install from Database.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         *
         * @param bool     $store
         * @param int|null $blog_id Since 2.0.0
         *
         * @return false|int The install ID if deleted. Otherwise, FALSE (when install not exist).
         */
        function _delete_site( $store = true, $blog_id = null ) {
            return self::_delete_site_by_slug( $this->_slug, $this->_module_type, $store, $blog_id );
        }

        /**
         * Delete site install from Database.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @param string   $slug
         * @param string   $module_type
         * @param bool     $store
         * @param int|null $blog_id Since 2.0.0
         *
         * @return false|int The install ID if deleted. Otherwise, FALSE (when install not exist).
         */
        static function _delete_site_by_slug( $slug, $module_type, $store = true, $blog_id = null ) {
            $sites = self::get_all_sites( $module_type, $blog_id );

            $install_id = false;

            if ( isset( $sites[ $slug ] ) ) {
                if ( is_object( $sites[ $slug ] ) ) {
                    $install_id = $sites[ $slug ]->id;
                }

                unset( $sites[ $slug ] );

                self::set_account_option_by_module( $module_type, 'sites', $sites, $store, $blog_id );
            }

            return $install_id;
        }

        /**
         * Delete plugin's plans information.
         *
         * @param bool $store                 Flush to Database if true.
         * @param bool $keep_associated_plans If set to false, delete all plans, even if a plan is associated with an install.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         */
        private function _delete_plans( $store = true, $keep_associated_plans = true ) {
            $this->_logger->entrance();

            $plans = self::get_all_plans( $this->_module_type );

            $plans_to_keep = array();

            if ( $keep_associated_plans ) {
                $plans_ids_to_keep = $this->get_plans_ids_associated_with_installs();
                foreach ( $plans_ids_to_keep as $plan_id ) {
                    $plan = self::_get_plan_by_id( $plan_id );
                    if ( is_object( $plan ) ) {
                        $plans_to_keep[] = self::_encrypt_entity( $plan );
                    }
                }
            }

            if ( ! empty( $plans_to_keep ) ) {
                $plans[ $this->_slug ] = $plans_to_keep;
            } else {
                unset( $plans[ $this->_slug ] );
            }

            $this->set_account_option( 'plans', $plans, $store );
        }

        /**
         * Delete all plugin licenses.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @param bool $store
         */
        private function _delete_licenses( $store = true ) {
            $this->_logger->entrance();

            $all_licenses = self::get_all_licenses();

            unset( $all_licenses[ $this->_module_id ] );

            self::$_accounts->set_option( 'all_licenses', $all_licenses, $store );
        }

        /**
         * Check if Freemius was added on new plugin installation.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.5
         *
         * @return bool
         */
        function is_plugin_new_install() {
            return isset( $this->_storage->is_plugin_new_install ) &&
                   $this->_storage->is_plugin_new_install;
        }

        /**
         * Check if it's the first plugin release that is running Freemius.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.5
         *
         * @return bool
         */
        function is_first_freemius_powered_version() {
            return empty( $this->_storage->plugin_last_version );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.2
         *
         * @return bool|string
         */
        private function get_previous_theme_slug() {
            return isset( $this->_storage->previous_theme ) ?
                $this->_storage->previous_theme :
                false;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.2
         *
         * @return bool
         */
        private function can_activate_previous_theme() {
            return $this->can_activate_theme( $this->get_previous_theme_slug() );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.5.0
         *
         * @return bool
         */
        private function can_activate_theme( $slug ) {
            if ( false !== $slug && current_user_can( 'switch_themes' ) ) {
                $theme_instance = wp_get_theme( $slug );

                return $theme_instance->exists();
            }

            return false;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.2
         */
        private function activate_previous_theme() {
            switch_theme( $this->get_previous_theme_slug() );
            unset( $this->_storage->previous_theme );

            global $pagenow;
            if ( 'themes.php' === $pagenow ) {
                /**
                 * Refresh the active theme information.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since  1.2.2
                 */
                fs_redirect( $this->admin_url( $pagenow ) );
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.2
         *
         * @return string
         */
        function get_previous_theme_activation_url() {
            if ( ! $this->can_activate_previous_theme() ) {
                return '';
            }

            /**
             * Activation URL
             *
             * @author Leo Fajardo (@leorw)
             * @since  1.2.2
             */
            return wp_nonce_url(
                $this->admin_url( 'themes.php?action=activate&stylesheet=' . urlencode( $this->get_previous_theme_slug() ) ),
                'switch-theme_' . $this->get_previous_theme_slug()
            );
        }

        /**
         * Saves the slug of the previous theme if it still exists so that it can be used by the logic in the opt-in
         * form that decides whether to add a close button to the opt-in dialog or not. So after a premium-only theme is
         * activated, the close button will appear and will reactivate the previous theme if clicked. If the previous
         * theme doesn't exist, then there will be no close button.
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.2.2
         *
         * @param  string        $slug_or_name Old theme's slug or name.
         * @param  bool|WP_Theme $old_theme    WP_Theme instance of the old theme if it still exists.
         */
        function _activate_theme_event_hook( $slug_or_name, $old_theme = false ) {
            $this->_storage->previous_theme = ( false !== $old_theme ) ?
                $old_theme->get_stylesheet() :
                $slug_or_name;

            $this->_activate_plugin_event_hook();
        }

        /**
         * Plugin activated hook.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         *
         * @uses   FS_Api
         */
        function _activate_plugin_event_hook() {
            $this->_logger->entrance( 'slug = ' . $this->_slug );

            if ( ! $this->is_user_admin() ) {
                return;
            }

            $this->unregister_uninstall_hook();

            // Clear API cache on activation.
            FS_Api::clear_cache();

            $is_premium_version_activation = $this->is_plugin() ?
                ( current_filter() !== ( 'activate_' . $this->_free_plugin_basename ) ) :
                $this->is_premium();

            if ( $is_premium_version_activation && $this->is_pending_activation() ) {
                $this->clear_pending_activation_mode();
            }

            $this->_logger->info( 'Activating ' . ( $is_premium_version_activation ? 'premium' : 'free' ) . ' plugin version.' );

            if ( $this->is_plugin() ) {
                // This logic is relevant only to plugins since both the free and premium versions of a plugin can be active at the same time.
                // 1. If running in the activation of the FREE module, get the basename of the PREMIUM.
                // 2. If running in the activation of the PREMIUM module, get the basename of the FREE.
                $other_version_basename = $is_premium_version_activation ?
                    $this->_free_plugin_basename :
                    $this->premium_plugin_basename();

                if ( ! $this->_is_network_active ) {
                    /**
                     * Themes are always network activated, but the ACTUAL activation is per site.
                     *
                     * During the activation, the plugin isn't yet active, therefore,
                     * _is_network_active will be set to false even if it's a network level
                     * activation. So we need to fix that by looking at the is_network_admin() value.
                     *
                     * @author Vova Feldman
                     */
                    $this->_is_network_active = (
                        $this->_is_multisite_integrated &&
                        fs_is_network_admin()
                    );
                }

                /**
                 * If the other module version is active, deactivate it.
                 *
                 * is_plugin_active() checks if the plugin is active on the site or the network level and
                 * deactivate_plugins() deactivates the plugin whether it's activated on the site or network level.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since  1.2.2
                 */
                if (
                    is_plugin_active( $other_version_basename ) &&
                    $this->apply_filters( 'deactivate_on_activation', true )
                ) {
                    deactivate_plugins( $other_version_basename );
                }
            }

            if ( $this->is_registered() ) {
                if ( $is_premium_version_activation ) {
                    $this->reconnect_locally();
                }


                // Schedule re-activation event and sync.
//				$this->sync_install( array(), true );
                $this->schedule_install_sync();

                // If activating the premium module version, add an admin notice to congratulate for an upgrade completion.
                if ( $is_premium_version_activation ) {
                    $this->_admin_notices->add(
                        sprintf( $this->get_text_inline( 'The upgrade of %s was successfully completed.', 'successful-version-upgrade-message' ), sprintf( '<b>%s</b>', $this->_plugin->title ) ),
                        $this->get_text_x_inline( 'W00t',
                            'Used to express elation, enthusiasm, or triumph (especially in electronic communication).', 'woot' ) . '!'
                    );
                }
            } else if ( $this->is_anonymous() ) {
                if ( isset( $this->_storage->is_anonymous_ms ) && $this->_storage->is_anonymous_ms['is'] ) {
                    $plugin_version = $this->_storage->is_anonymous_ms['version'];
                    $network        = true;
                } else {
                    $plugin_version = isset( $this->_storage->is_anonymous ) ?
                        $this->_storage->is_anonymous['version'] :
                        null;
                    $network        = false;
                }

                /**
                 * Reset "skipped" click cache on the following:
                 *  1. Freemius DEV mode.
                 *  2. WordPress DEBUG mode.
                 *  3. If a plugin and the user skipped the exact same version before.
                 *
                 * @since 1.2.2.7 Ulrich Pogson (@grapplerulrich) asked to not reset the SKIPPED flag if the exact same THEME version was activated before unless the developer is running with WP_DEBUG on, or Freemius debug mode on (WP_FS__DEV_MODE).
                 *
                 * @todo  4. If explicitly asked to retry after every activation.
                 */
                if ( WP_FS__DEV_MODE ||
                     (
                         ( $this->is_plugin() || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) &&
                         $this->get_plugin_version() == $plugin_version
                     )
                ) {
                    $this->reset_anonymous_mode( $network );
                }
            }

            $is_trial_or_has_features_enabled_license = ( $this->is_trial() || $this->has_features_enabled_license() );

            if ( $this->is_addon() && ! $is_trial_or_has_features_enabled_license ) {
                /**
                 * When activating an add-on, try to also activate a license.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.3.0
                 */
                if ( ! $this->_is_network_active ) {
                    $this->maybe_activate_addon_license();
                } else {
                    $this->maybe_network_activate_addon_license();
                }

                /**
                 * Avoid redirecting to the license activation screen after automatically activating an add-on license.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.3.0
                 */
                $is_trial_or_has_features_enabled_license = ( $this->is_trial() || $this->has_features_enabled_license() );

                if ( $is_trial_or_has_features_enabled_license && true === $this->_storage->require_license_activation ) {
                    $this->_storage->require_license_activation = false;
                }
            }

            if (
                $is_premium_version_activation &&
                (
                    ( ! $this->is_registered() && $this->is_anonymous() ) ||
                    (
                        $this->is_registered() &&
                        ! $is_trial_or_has_features_enabled_license
                    )
                )
            ) {
                $this->_storage->require_license_activation = true;
            }

            if ( ! isset( $this->_storage->is_plugin_new_install ) ) {
                /**
                 * If no previous version of plugin's version exist, it means that it's either
                 * the first time that the plugin installed on the site, or the plugin was installed
                 * before but didn't have Freemius integrated.
                 *
                 * Since register_activation_hook() do NOT fires on updates since 3.1, and only fires
                 * on manual activation via the dashboard, is_plugin_activation() is TRUE
                 * only after immediate activation.
                 *
                 * @since 1.1.4
                 * @link  https://make.wordpress.org/core/2010/10/27/plugin-activation-hooks-no-longer-fire-for-updates/
                 */
                $this->_storage->is_plugin_new_install = empty( $this->_storage->plugin_last_version );
            }

            /**
             * Also flush when activating the premium version so that even if Freemius was off before, the API
             * connectivity test can be run again.
             *
             * @author Leo Fajardo (@leorw)
             * @since 2.2.3.1
             */
            $has_api_connectivity = $this->has_api_connectivity( WP_FS__DEV_MODE || $is_premium_version_activation );

            if ( ! $this->_anonymous_mode &&
                ( false !== $has_api_connectivity ) &&
                 ! $this->_isAutoInstall
            ) {
                // Store hint that the plugin was just activated to enable auto-redirection to settings.
                set_transient( "fs_{$this->_module_type}_{$this->_slug}_activated", true, 60 );
            }

            /**
             * Activation hook is executed after the plugin's main file is loaded, therefore,
             * after the plugin was loaded. The logic is located at activate_plugin()
             * ./wp-admin/includes/plugin.php.
             *
             * @author Vova Feldman (@svovaf)
             * @since  1.1.9
             */
            $this->_storage->was_plugin_loaded = true;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         */
        private function maybe_activate_addon_license() {
            $parent_fs = $this->get_parent_instance();

            if (
                ! is_object( $parent_fs ) ||
                ( ! $parent_fs->is_registered() && ! $parent_fs->is_network_registered() )
            ) {
                // Try to activate a license only if the parent plugin is active and has a valid `install`.
                return;
            }

            $license = $this->get_active_parent_license();
            if ( ! is_object( $license ) ) {
                return;
            }

            if (
                $this->is_bundle_license_auto_activation_enabled() &&
                ! empty( $license->products )
            ) {
                $this->activate_bundle_license( $license );

                return;
            }

            if ( ! $this->is_registered() ) {
                // Opt in with a license key.
                $this->opt_in(
                    $parent_fs->get_current_or_network_user()->email,
                    false,
                    false,
                    $license->secret_key
                );
            } else {
                // Activate the license.
                $install = $this->api_site_call(
                    '/',
                    'put',
                    array( 'license_key' => $this->apply_filters( 'license_key', $license->secret_key ) )
                );

                if ( ! FS_Api::is_api_error( $install ) ) {
                    $this->_sync_addon_license( $this->get_id(), true );
                }
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @param FS_Plugin_License $license
         */
        private function maybe_network_activate_addon_license( $license = null ) {
            $parent_fs = $this->get_parent_instance();
            if ( ! is_object( $parent_fs ) || ( ! $parent_fs->is_registered() && ! $parent_fs->is_network_registered() ) ) {
                // Try to activate a license only if the parent plugin is active and has a valid `install`.
                return;
            }

            $license = ( ! is_null( $license ) ) ?
                $license :
                $this->get_active_parent_license();

            if ( ! is_object( $license ) ) {
                return;
            }

            if (
                $this->is_bundle_license_auto_activation_enabled() &&
                ! empty( $license->products )
            ) {
                $this->activate_bundle_license( $license );

                return;
            }

            if ( ! $this->is_network_registered() ) {
                $sites = $this->get_sites_for_network_level_optin();

                if ( count( $sites ) > $license->left() ) {
                    // If the add-on is network active, try to activate the license only if it can be activated on all sites.
                    return;
                }

                // Opt in with a license key.
                $this->opt_in(
                    $parent_fs->get_user()->email,
                    false,
                    false,
                    $license->secret_key,
                    false,
                    false,
                    false,
                    null,
                    $sites
                );
            } else {
                $blog_2_install_map = array();
                $site_ids           = array();

                $all_sites = Freemius::get_sites();

                foreach ( $all_sites as $site ) {
                    $blog_id = Freemius::get_site_blog_id( $site );
                    $install = $this->get_install_by_blog_id( $blog_id );

                    if ( is_object( $install ) && FS_Plugin_License::is_valid_id( $install->license_id ) ) {
                        // Skip license activation for installs that are already associated with a license.
                        continue;
                    }

                    if ( is_object( $install ) ) {
                        $blog_2_install_map[ $blog_id ] = $install;
                    } else {
                        $site_ids[] = $blog_id;
                    }
                }

                if ( ( count( $blog_2_install_map ) + count( $site_ids ) ) > $license->left() ) {
                    return;
                }

                $user = $this->get_current_or_network_user();

                if ( ! empty( $blog_2_install_map ) ) {
                    $result = $this->activate_license_on_many_installs( $user, $license->secret_key, $blog_2_install_map );

                    if ( true !== $result ) {
                        return;
                    }
                }

                if ( ! empty( $site_ids ) ) {
                    $this->activate_license_on_many_sites( $user, $license->secret_key, $site_ids );
                }
            }
        }

        /**
         * Tries to activate a bundle license for all supported products if the current product is activated with a bundle license. This is called after activating an available license (not via the license activation dialog but by clicking on a license activation button) for a product via its "Account" page.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.4.0
         *
         * @param FS_Plugin_License $license
         * @param array             $sites
         * @param int               $blog_id
         */
        private function maybe_activate_bundle_license( FS_Plugin_License $license = null, $sites = array(), $blog_id = 0 ) {
            if ( ! is_object( $license ) && $this->has_active_valid_license() ) {
                $license = $this->_license;
            }

            if ( ! is_object( $license ) ) {
                return;
            }

            $parent_license = ( ! empty( $license->products ) ) ?
                $license :
                $this->get_active_parent_license( $license->secret_key );

            if ( is_object( $parent_license ) ) {
                $this->activate_bundle_license( $parent_license, $sites, $blog_id );
            }
        }

        /**
         * Try to activate a bundle license for all the bundle products installed on the site.
         *  (1) If a child product install already has a license, the bundle license won't be activated.
         *  (2) On multi-site networks, if the attempt to activate the bundle license is triggered from the network admin, the bundle license activation will only work for non-delegated sites and only if none of them is associated with a license. Even if one of the sites has the product installed with a license key, skip the bundle license activation for the product.
         *  (3) On multi-site networks, if the attempt to activate the bundle license is triggered from a site-level admin, only activate the license if the product is site-level activated or delegated, and the product installation is not yet associated with a license.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.4.0
         *
         * @param FS_Plugin_License $license
         * @param array             $sites
         * @param int               $current_blog_id
         */
        private function activate_bundle_license( $license, $sites = array(), $current_blog_id = 0 ) {
            $is_network_admin = fs_is_network_admin();

            $installs_by_blog_map  = array();
            $site_info_by_blog_map = array();

            /**
             * Try to activate the license for all supported products.
             *
             * @author Leo Fajardo
             */
            foreach ( $license->products as $product_id ) {
                $fs = self::get_instance_by_id( $product_id );

                if ( ! is_object( $fs ) ) {
                    continue;
                }

                if ( ! $fs->has_paid_plan() ) {
                    continue;
                }

                if (
                    ! $fs->is_addon() &&
                    ! FS_Plan_Manager::instance()->has_paid_plan( $fs->_plans )
                ) {
                    /**
                     * The parent product can be free-only but can have its `has_paid_plan` flag set to `true` when
                     * there is a context bundle.
                     */
                    continue;
                }

                if ( $current_blog_id > 0 ) {
                    $fs->switch_to_blog( $current_blog_id );
                }

                if ( $fs->has_active_valid_license() ) {
                    continue;
                }

                if ( ! $is_network_admin || $current_blog_id > 0 ) {
                    if ( $fs->is_network_active() && ! $fs->is_delegated_connection( $current_blog_id ) ) {
                        // Do not try to activate the license in the site level if the product is network active and the connection was not delegated.
                        continue;
                    }
                } else {
                    if ( ! $fs->is_network_active() ) {
                        // Do not try to activate the license in the network level if the product is not network active.
                        continue;
                    }

                    if ( $fs->is_network_delegated_connection() ) {
                        // Do not try to activate the license in the network level if the activation has been delegated to site admins.
                        continue;
                    }

                    $has_install_with_license = false;

                    // Collection of sites that have an install entity that is not activated with a license or non-delegated sites that have no install entity, or both types of site.
                    $filtered_sites = array();

                    if ( empty( $sites ) ) {
                        $all_sites = self::get_sites();

                        foreach ( $all_sites as $site ) {
                            $sites[] = array( 'blog_id' => self::get_site_blog_id( $site ) );
                        }
                    } else {
                        // Populate the map here to avoid calling `$fs->get_site_info( $site );` in the other `for` loop below.
                        foreach ( $sites as $site ) {
                            if ( ! isset( $site['blog_id'] ) || ! is_numeric( $site['blog_id'] ) ) {
                                continue;
                            }

                            $site_info_by_blog_map[ $site['blog_id'] ] = $site;
                        }
                    }

                    foreach ( $sites as $site ) {
                        if ( ! isset( $site['blog_id'] ) || ! is_numeric( $site['blog_id'] ) ) {
                            continue;
                        }

                        $blog_id = $site['blog_id'];

                        if ( ! isset( $installs_by_blog_map[ $blog_id ] ) ) {
                            $installs_by_blog_map[ $blog_id ] = self::get_all_sites( $fs->get_module_type(), $blog_id );
                        }

                        $installs = $installs_by_blog_map[ $blog_id ];
                        $install  = null;

                        if ( isset( $installs[ $fs->get_slug() ] ) ) {
                            $install = $installs[ $fs->get_slug() ];

                            if (
                                is_object( $install ) &&
                                (
                                    ! FS_Site::is_valid_id( $install->id ) ||
                                    ! FS_User::is_valid_id( $install->user_id ) ||
                                    ! FS_Plugin_Plan::is_valid_id( $install->plan_id )
                                )
                            ) {
                                $install = null;
                            }
                        }

                        if (
                            is_object( $install ) &&
                            FS_Plugin_License::is_valid_id( $install->license_id )
                        ) {
                            $has_install_with_license = true;
                            break;
                        }

                        if ( $fs->is_site_delegated_connection( $blog_id ) ) {
                            // Site activation delegated, don't activate bundle license on the site in the network admin.
                            continue;
                        }

                        if ( ! isset( $site_info_by_blog_map[ $blog_id ] ) ) {
                            $site_info_by_blog_map[ $blog_id ] = $fs->get_site_info( $site );
                        }

                        $filtered_sites[] = $site_info_by_blog_map[ $blog_id ];
                    }

                    if ( $has_install_with_license || empty( $filtered_sites ) ) {
                        // Do not try to activate the license at the network level if there's any install with a license or there's no site to activate the license on.
                        continue;
                    }

                    $sites = $filtered_sites;
                }

                $fs->activate_migrated_license(
                    $license->secret_key,
                    null,
                    null,
                    $sites,
                    ( $current_blog_id > 0 ? $current_blog_id : null )
                );
            }
        }

        /**
         * Returns a parent license that can be activated for the context product.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @param string|null $license_key
         * @param bool        $flush
         *
         * @return FS_Plugin_License
         */
        function get_active_parent_license( $license_key = null, $flush = true ) {
            $parent_licenses_endpoint = "/plugins/{$this->get_id()}/parent_licenses.json?filter=activatable";

            $fs = $this;
            
            if ( $this->is_addon() ) {
                $parent_instance = $this->get_parent_instance();

                if ( is_object( $parent_instance ) && $parent_instance->is_registered() ) {
                    $fs = $parent_instance;
                }
            }

            $foreign_licenses = $fs->get_foreign_licenses_info(
                self::get_all_licenses( $this->get_parent_id() )
            );

            if ( ! empty ( $foreign_licenses ) ) {
                $foreign_licenses = array(
                    // Prefix with `+` to tell the server to include foreign licenses in the licenses collection.
                    'ids'          => ( urlencode( '+' ) . implode( ',', $foreign_licenses['ids'] ) ),
                    'license_keys' => implode( ',', array_map( 'urlencode', $foreign_licenses['license_keys'] ) )
                );

                $parent_licenses_endpoint = add_query_arg( $foreign_licenses, $parent_licenses_endpoint );
            }

            $result = $fs->get_current_or_network_user_api_scope()->get( $parent_licenses_endpoint, $flush );

            if (
                ! $this->is_api_result_object( $result, 'licenses' ) ||
                ! is_array( $result->licenses ) ||
                empty( $result->licenses )
            ) {
                return null;
            }

            $parent_license = null;

            if ( empty( $license_key ) ) {
                $parent_license = $result->licenses[0];
            } else {
                foreach ( $result->licenses as $license ) {
                    if ( $license_key === $license->secret_key ) {
                        $parent_license = $license;
                        break;
                    }
                }
            }

            if ( ! is_null( $parent_license ) ) {
                $parent_license = new FS_Plugin_License( $parent_license );
            }

            return $parent_license;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @return array
         */
        function get_sites_for_network_level_optin() {
            $sites     = array();
            $all_sites = self::get_sites();

            foreach ( $all_sites as $site ) {
                $blog_id = self::get_site_blog_id( $site );

                if ( ! $this->is_site_delegated_connection( $blog_id ) &&
                    ! $this->is_installed_on_site( $blog_id )
                ) {
                    $sites[] = $this->get_site_info( $site );
                }
            }

            return $sites;
        }

        /**
         * Delete account.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         *
         * @param bool $check_user Enforce checking if user have plugins activation privileges.
         */
        function delete_account_event( $check_user = true ) {
            $this->_logger->entrance( 'slug = ' . $this->_slug );

            if ( $check_user && ! $this->is_user_admin() ) {
                return;
            }

            $this->do_action( 'before_account_delete' );

            // Clear all admin notices.
            $this->_admin_notices->clear_all_sticky( false );

            $this->_delete_site( false );

            $delete_network_common_data = true;

            if ( $this->_is_network_active ) {
                $installs = $this->get_blog_install_map();

                // Don't delete common network data unless no other installs left.
                $delete_network_common_data = empty( $installs );
            }

            if ( $delete_network_common_data ) {
                $this->_delete_plans( false );

                $this->_delete_licenses( false );

                // Delete add-ons related to plugin's account.
                $this->_delete_account_addons( false );
            }

            // @todo Delete plans and licenses of add-ons.

            self::$_accounts->store();

            /**
             * IMPORTANT:
             *  Clear crons must be executed before clearing all storage.
             *  Otherwise, the cron will not be cleared.
             */
            if ( $delete_network_common_data ) {
                $this->clear_sync_cron();
            }

            $this->clear_install_sync_cron();

            // Clear all storage data.
            $this->_storage->clear_all( true, array(
                'is_delegated_connection',
                'connectivity_test',
                'is_on',
            ), false );

            // Send delete event.
            $this->get_api_site_scope()->call( '/', 'delete' );

            $this->do_action( 'after_account_delete' );
        }

        /**
         * Delete network level account.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param bool $check_user Enforce checking if user have plugins activation privileges.
         */
        function delete_network_account_event( $check_user = true ) {
            $this->_logger->entrance( 'slug = ' . $this->_slug );

            if ( $check_user && ! $this->is_user_admin() ) {
                return;
            }

            $this->do_action( 'before_network_account_delete' );

            // Clear all admin notices.
            $this->_admin_notices->clear_all_sticky();

            $this->_delete_plans( false, false );

            $this->_delete_licenses( false );

            // Delete add-ons related to plugin's account.
            $this->_delete_account_addons( false );

            // @todo Delete plans and licenses of add-ons.

            self::$_accounts->store( true );

            /**
             * IMPORTANT:
             *  Clear crons must be executed before clearing all storage.
             *  Otherwise, the cron will not be cleared.
             */
            $this->clear_sync_cron( true );
            $this->clear_install_sync_cron( true );

            $sites = self::get_sites();

            $install_ids = array();
            foreach ( $sites as $site ) {
                $blog_id = self::get_site_blog_id( $site );

                if ( $this->is_site_delegated_connection( $blog_id ) ) {
                    continue;
                }

                $install_id = $this->_delete_site( true, $blog_id );

                // Clear all storage data.
                $this->_storage->clear_all( true, array( 'connectivity_test' ), $blog_id );

                if ( FS_Site::is_valid_id( $install_id ) ) {
                    $install_ids[] = $install_id;
                }

                switch_to_blog( $blog_id );

                $this->do_action( 'after_account_delete' );

                restore_current_blog();
            }

            $this->_storage->clear_all( true, array(
                'connectivity_test',
                'is_on',
            ), true );

            // Send delete event.
            if ( ! empty( $install_ids ) ) {
                $result = $this->get_current_or_network_user_api_scope()->call( "/plugins/{$this->_module_id}/installs.json?ids=" . implode( ',', $install_ids ), 'delete' );
            }

            $this->do_action( 'after_network_account_delete' );
        }

        /**
         * Plugin deactivation hook.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         */
        function _deactivate_plugin_hook() {
            $this->_logger->entrance( 'slug = ' . $this->_slug );

            if ( ! $this->is_user_admin() ) {
                return;
            }

            $is_network_deactivation  = fs_is_network_admin();
            $storage_keys_for_removal = array();

            $this->_admin_notices->clear_all_sticky();

            $storage_keys_for_removal[] = 'sticky_optin_added';
            if ( isset( $this->_storage->sticky_optin_added ) ) {
                unset( $this->_storage->sticky_optin_added );
            }

            if ( ! isset( $this->_storage->is_plugin_new_install ) ) {
                // Remember that plugin was already installed.
                $this->_storage->is_plugin_new_install = false;
            }

            // Hook to plugin uninstall.
            register_uninstall_hook( $this->_plugin_main_file_path, array( 'Freemius', '_uninstall_plugin_hook' ) );

            $this->clear_module_main_file_cache();
            $this->clear_sync_cron( $this->_is_network_active );
            $this->clear_install_sync_cron();

            if ( $this->is_registered() ) {
                if ( $this->is_premium() && ! $this->has_active_valid_license() ) {
                    FS_Plugin_Updater::instance( $this )->delete_update_data();
                }

                if ( $is_network_deactivation ) {
                    // Send deactivation event.
                    $this->sync_installs( array(
                        'is_active' => false,
                    ) );
                } else {
                    // Send deactivation event.
                    $this->sync_install( array(
                        'is_active' => false,
                    ) );
                }
            } else {
                if ( false === $this->has_api_connectivity() && ! $this->is_premium() ) {
                    // Reset connectivity test cache.
                    $this->clear_connectivity_info();

                    $storage_keys_for_removal[] = 'connectivity_test';
                }
            }

            if ( $is_network_deactivation ) {
                if ( isset( $this->_storage->sticky_optin_added_ms ) ) {
                    unset( $this->_storage->sticky_optin_added_ms );
                }

                if ( ! empty( $storage_keys_for_removal ) ) {
                    $sites = self::get_sites();

                    foreach ( $sites as $site ) {
                        $blog_id = self::get_site_blog_id( $site );

                        foreach ( $storage_keys_for_removal as $key ) {
                            $this->_storage->remove( $key, false, $blog_id );
                        }

                        $this->_storage->save( $blog_id );
                    }
                }
            }

            // Clear API cache on deactivation.
            FS_Api::clear_cache();

            $this->remove_sdk_reference();
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.6
         */
        private function remove_sdk_reference() {
            global $fs_active_plugins;

            foreach ( $fs_active_plugins->plugins as $sdk_path => $data ) {
                if ( $this->_plugin_basename == $data->plugin_path ) {
                    unset( $fs_active_plugins->plugins[ $sdk_path ] );
                    break;
                }
            }

            fs_fallback_to_newest_active_sdk();
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.3
         *
         * @param bool     $is_anonymous
         * @param bool|int $network_or_blog_id Since 2.0.0
         */
        private function set_anonymous_mode( $is_anonymous = true, $network_or_blog_id = 0 ) {
            // Store information regarding skip to try and opt-in the user
            // again in the future.
            $skip_info = array(
                'is'        => $is_anonymous,
                'timestamp' => WP_FS__SCRIPT_START_TIME,
                'version'   => $this->get_plugin_version(),
            );

            if ( true === $network_or_blog_id ) {
                $this->_storage->is_anonymous_ms = $skip_info;
            } else {
                $this->_storage->store( 'is_anonymous', $skip_info, $network_or_blog_id );
            }

            $this->network_upgrade_mode_completed();

            // Update anonymous mode cache.
            $this->_is_anonymous = $is_anonymous;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.5.1
         *
         * @param bool|int $network_or_blog_id
         */
        private function unset_anonymous_mode( $network_or_blog_id = 0 ) {
            if ( true === $network_or_blog_id ) {
                unset( $this->_storage->is_anonymous_ms );
            } else {
                $this->_storage->remove( 'is_anonymous', true, $network_or_blog_id );
            }
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int    $blog_id    Site ID.
         * @param int    $user_id    User ID.
         * @param string $domain     Site domain.
         * @param string $path       Site path.
         * @param int    $network_id Network ID. Only relevant on multi-network installations.
         * @param array  $meta       Metadata. Used to set initial site options.
         *
         * @uses   Freemius::is_license_network_active() to check if the context license was network activated by the super-admin.
         * @uses   Freemius::is_network_connected() to check if the super-admin network opted-in.
         * @uses   Freemius::is_network_anonymous() to check if the super-admin network skipped.
         * @uses   Freemius::is_network_delegated_connection() to check if the super-admin network delegated the connection to the site admins.
         */
        public function _after_new_blog_callback( $blog_id, $user_id, $domain, $path, $network_id, $meta ) {
            $this->_logger->entrance();

            if ( ! $this->_is_network_active ) {
                FS_Clone_Manager::instance()->store_blog_install_info( $blog_id );
                return;
            }

            $site        = null;
            $new_blog_id = $blog_id;

            if ( $this->is_premium() &&
                 $this->is_network_connected() &&
                 is_object( $this->_license ) &&
                 $this->_license->can_activate( FS_Site::is_localhost_by_address( $domain ) ) &&
                 $this->is_license_network_active( $blog_id )
            ) {
                /**
                 * Running the premium version, the license was network activated, and the license can also be activated on the current site -> so try to opt-in with the license key.
                 */
                $current_blog_id = get_current_blog_id();
                $license         = clone $this->_license;

                $this->switch_to_blog( $blog_id );

                // Opt-in with network user.
                $this->install_with_user(
                    $this->get_network_user(),
                    $license->secret_key,
                    false,
                    false,
                    false
                );

                if ( is_object( $this->_site ) ) {
                    if ( $this->_site->license_id == $license->id ) {
                        /**
                         * If the license was activated successfully, sync the license data from the remote server.
                         */
                        $this->_license = $license;
                        $this->sync_site_license();
                    }
                }

                $site = $this->_site;

                $this->switch_to_blog( $current_blog_id );

                if ( is_object( $site ) ) {
                    FS_Clone_Manager::instance()->store_blog_install_info( $blog_id, $site );

                    // Already connected (with or without a license), so no need to continue.
                    return;
                }
            }

            if ( $this->is_network_anonymous() ) {
                /**
                 * Opt-in was network skipped so automatically skip the opt-in for the new site.
                 */
                $this->skip_site_connection( $blog_id );
            } else if ( $this->is_network_delegated_connection() ) {
                /**
                 * Opt-in was network delegated so automatically delegate the opt-in for the new site's admin.
                 */
                $this->delegate_site_connection( $blog_id );
            } else if ( $this->is_network_connected() ) {
                /**
                 * Opt-in was network activated so automatically opt-in with the network user and new site admin.
                 */
                $current_blog_id = get_current_blog_id();

                $this->switch_to_blog( $blog_id );

                // Opt-in with network user.
                $this->install_with_user(
                    $this->get_network_user(),
                    false,
                    false,
                    false,
                    false
                );

                $site = $this->_site;

                $this->switch_to_blog( $current_blog_id );
            } else {
                /**
                 * If the super-admin mixed different options (connect, skip, delegated):
                 *  a) If at least one site connection was delegated, then automatically delegate connection.
                 *  b) Otherwise, it means that at least one site was skipped and at least one site was connected. For a simplified UX in the initial release of the multisite network integration, skip the connection for the newly created site. If the super-admin will want to opt-in they can still do that from the network level Account page.
                 */
                $has_delegated_site = false;

                $sites = self::get_sites();
                foreach ( $sites as $wp_site ) {
                    $blog_id = self::get_site_blog_id( $wp_site );

                    if ( $this->is_site_delegated_connection( $blog_id ) ) {
                        $has_delegated_site = true;
                        break;
                    }
                }

                if ( $has_delegated_site ) {
                    $this->delegate_site_connection( $blog_id );
                } else {
                    $this->skip_site_connection( $blog_id );
                }
            }

            /**
             * Store the new blog's information even if there's no install so that when a clone install is stored in the new blog's storage, we can try to resolve it automatically.
             *
             * @author Leo Fajardo (@leorw)
             * @since 2.5.0
             */
            FS_Clone_Manager::instance()->store_blog_install_info( $new_blog_id, $site );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.5.0
         *
         * @param \WP_Site $new_site
         * @param array    $args
         */
        public function _after_wp_initialize_site_callback( WP_Site $new_site, $args ) {
            $this->_logger->entrance();

            $this->_after_new_blog_callback(
                $new_site->id,
                // Dummy user ID (not in use).
                0,
                $new_site->domain,
                $new_site->path,
                $new_site->network_id,
                // Dummy meta, not in use.
                array()
            );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.3
         *
         * @param bool|int|int[] $network_or_blog_ids Since 2.0.0.
         */
        private function reset_anonymous_mode( $network_or_blog_ids = false ) {
            if ( true === $network_or_blog_ids ) {
                $this->unset_anonymous_mode( true );

                if ( fs_is_network_admin() ) {
                    $this->_is_anonymous = null;
                }

                // Rest anonymous mode for all non-delegated sub-sites.
                $blog_ids = $this->get_non_delegated_blog_ids();
            }
            else
            {
                if ( false === $network_or_blog_ids ) {
                    $network_or_blog_ids = 0;
                }

                $blog_ids = is_array( $network_or_blog_ids ) ?
                    $network_or_blog_ids :
                    array( $network_or_blog_ids );

                foreach ( $blog_ids as $blog_id ) {
                    if ( 0 === $blog_id || get_current_blog_id() == $blog_id ) {
                        $this->_is_anonymous = null;
                    }
                }
            }

            foreach ( $blog_ids as $blog_id ) {
                $this->unset_anonymous_mode( $blog_id );
            }

            /**
             * Ensure that this field is also "false", otherwise, if the current module's type is "theme" and the module
             * has no menus, the opt-in popup will not be shown immediately (in this case, the user will have to click
             * on the admin notice that contains the opt-in link in order to trigger the opt-in popup).
             *
             * @author Leo Fajardo (@leorw)
             * @since  1.2.2
             */
            if ( ! $this->_is_network_active ) {
                $this->_is_anonymous = null;
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.3
         */
        private function update_license_required_permissions_if_anonymous() {
            if ( ! $this->is_anonymous() ) {
                return;
            }

            $this->reset_anonymous_mode( fs_is_network_admin() );

            FS_Permission_Manager::instance( $this )->update_permissions_tracking_flag( array(
                'essentials' => true,
                'events'     => true,
                'diagnostic' => false,
                'extensions' => false,
                'site'       => false,
            ) );
        }

        /**
         * This is used to ensure that before redirecting to the opt-in page after resetting the anonymous mode or
         * deleting the account in the network level, the URL of the page to redirect to is correct.
         *
         * @author Leo Fajardo (@leorw)
         *
         * @since 2.1.3
         */
        private function maybe_set_slug_and_network_menu_exists_flag() {
            if ( ! empty( $this->_dynamically_added_top_level_page_hook_name ) ) {
                $this->_menu->set_slug_and_network_menu_exists_flag( $this->_menu->has_menu() ?
                    $this->_menu->get_slug() :
                    $this->_slug
                );
            }
        }

        /**
         * Clears the anonymous mode and redirects to the opt-in screen.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7
         */
        function connect_again() {
            if ( ! $this->is_anonymous() && ! $this->is_pending_activation() ) {
                return;
            }

            if ( $this->is_anonymous() ) {
                $this->reset_anonymous_mode( fs_is_network_admin() );
            }

            $activation_url_params = array();

            if ( $this->is_pending_activation() ) {
                $this->clear_pending_activation_mode();

                if ( fs_request_get_bool( 'require_license' ) ) {
                    $activation_url_params['require_license'] = true;
                }
            }

            $this->maybe_set_slug_and_network_menu_exists_flag();

            fs_redirect( $this->get_activation_url( $activation_url_params ) );
        }

        /**
         * Skip account connect, and set anonymous mode.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.1
         *
         * @param bool|int|int[] $network_or_blog_ids Since 2.5.1
         */
        function skip_connection( $network_or_blog_ids = false ) {
            $this->_logger->entrance();

            $this->_admin_notices->remove_sticky( 'connect_account' );

            if ( true === $network_or_blog_ids ) {
                $this->set_anonymous_mode( true, true );

                if ( fs_is_network_admin() ) {
                    $this->_is_anonymous = null;
                }

                // Rest anonymous mode for all non-delegated sub-sites.
                $blog_ids = $this->get_non_delegated_blog_ids();
            }
            else
            {
                if ( false === $network_or_blog_ids ) {
                    $network_or_blog_ids = 0;
                }

                $blog_ids = is_array( $network_or_blog_ids ) ?
                    $network_or_blog_ids :
                    array( $network_or_blog_ids );

                foreach ( $blog_ids as $blog_id ) {
                    if ( 0 === $blog_id || get_current_blog_id() == $blog_id ) {
                        $this->_is_anonymous = null;
                    }
                }
            }

            foreach ( $blog_ids as $blog_id ) {
                $this->skip_site_connection( $blog_id );
            }

            $this->network_upgrade_mode_completed();
        }

        /**
         * Skip connection for specific site in the network.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int|null $blog_id
         * @param bool     $send_skip
         */
        private function skip_site_connection( $blog_id = null ) {
            $this->_logger->entrance();

            $this->_admin_notices->remove_sticky( 'connect_account', $blog_id );

            $this->set_anonymous_mode( true, $blog_id );
        }

        /**
         * Plugin version update hook.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         */
        private function update_plugin_version_event() {
            $this->_logger->entrance();

            if ( ! $this->is_registered() ) {
                return;
            }

            $this->schedule_install_sync();
//			$this->sync_install( array(), true );
        }

        /**
         * Generate an MD5 signature of a plugins collection.
         * This helper methods used to identify changes in a plugins collection.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param array [string]array $plugins
         *
         * @return string
         */
        private function get_plugins_thumbprint( $plugins ) {
            ksort( $plugins );

            $thumbprint = '';
            foreach ( $plugins as $basename => $data ) {
                $thumbprint .= $data['slug'] . ',' .
                               $data['Version'] . ',' .
                               ( $data['is_active'] ? '1' : '0' ) . ';';
            }

            return md5( $thumbprint );
        }

        /**
         * Return a list of modified plugins since the last sync.
         *
         * Note:
         *  There's no point to store a plugins counter since even if the number of
         *  plugins didn't change, we still need to check if the versions are all the
         *  same and the activity state is similar.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.8
         *
         * @return array|false
         */
        private function get_plugins_data_for_api() {
            // Alias.
            $site_active_plugins_option_name = 'active_plugins';
            $network_plugins_option_name     = 'all_plugins';

            /**
             * Collection of all site level active plugins.
             */
            $site_active_plugins_cache = self::$_accounts->get_option( $site_active_plugins_option_name );

            if ( ! is_object( $site_active_plugins_cache ) ) {
                $site_active_plugins_cache = (object) array(
                    'timestamp' => '',
                    'md5'       => '',
                    'plugins'   => array(),
                );
            }

            $time = time();

            if ( ! empty( $site_active_plugins_cache->timestamp ) &&
                 ( $time - $site_active_plugins_cache->timestamp ) < WP_FS__TIME_5_MIN_IN_SEC
            ) {
                // Don't send plugin updates if last update was in the past 5 min.
                return false;
            }

            // Write timestamp to lock the logic.
            $site_active_plugins_cache->timestamp = $time;
            self::$_accounts->set_option( $site_active_plugins_option_name, $site_active_plugins_cache, true );

            // Reload options from DB.
            self::$_accounts->load( true );
            $site_active_plugins_cache = self::$_accounts->get_option( $site_active_plugins_option_name );

            if ( $time != $site_active_plugins_cache->timestamp ) {
                // If timestamp is different, then another thread captured the lock.
                return false;
            }

            /**
             * Collection of all plugins (network level).
             */
            $network_plugins_cache = self::$_accounts->get_option( $network_plugins_option_name );

            if ( ! is_object( $network_plugins_cache ) ) {
                $network_plugins_cache = (object) array(
                    'timestamp' => '',
                    'md5'       => '',
                    'plugins'   => array(),
                );
            }

            // Check if there's a change in plugins.
            $network_plugins     = self::get_network_plugins();
            $site_active_plugins = self::get_site_active_plugins();

            $network_plugins_thumbprint     = $this->get_plugins_thumbprint( $network_plugins );
            $site_active_plugins_thumbprint = $this->get_plugins_thumbprint( $site_active_plugins );

            // Check if plugins status changed (version or active/inactive).
            $network_plugins_changed     = ( $network_plugins_cache->md5 !== $network_plugins_thumbprint );
            $site_active_plugins_changed = ( $site_active_plugins_cache->md5 !== $site_active_plugins_thumbprint );

            if ( ! $network_plugins_changed &&
                 ! $site_active_plugins_changed
            ) {
                // No changes.
                return array();
            }

            $plugins_update_data = array();

            foreach ( $network_plugins_cache->plugins as $basename => $data ) {
                if ( ! isset( $network_plugins[ $basename ] ) ) {
                    // Plugin uninstalled.
                    $uninstalled_plugin_data                   = $data;
                    $uninstalled_plugin_data['is_active']      = false;
                    $uninstalled_plugin_data['is_uninstalled'] = true;
                    $plugins_update_data[]                     = $uninstalled_plugin_data;

                    unset( $network_plugins[ $basename ] );

                    unset( $network_plugins_cache->plugins[ $basename ] );
                    unset( $site_active_plugins_cache->plugins[ $basename ] );

                    continue;
                }

                $was_active = $data['is_active'] ||
                              ( isset( $site_active_plugins_cache->plugins[ $basename ] ) &&
                                true === $site_active_plugins_cache->plugins[ $basename ]['is_active'] );
                $is_active  = $network_plugins[ $basename ]['is_active'] ||
                              ( isset( $site_active_plugins[ $basename ] ) &&
                                $site_active_plugins[ $basename ]['is_active'] );

                if ( ! isset( $site_active_plugins_cache->plugins[ $basename ] ) &&
                     isset( $site_active_plugins[ $basename ] )
                ) {
                    // Plugin was site level activated.
                    $site_active_plugins_cache->plugins[ $basename ]              = $network_plugins[ $basename ];
                    $site_active_plugins_cache->plugins[ $basename ]['is_active'] = true;
                } else if ( isset( $site_active_plugins_cache->plugins[ $basename ] ) &&
                            ! isset( $site_active_plugins[ $basename ] )
                ) {
                    // Plugin was site level deactivated.
                    unset( $site_active_plugins_cache->plugins[ $basename ] );
                }

                $prev_version    = $data['version'];
                $current_version = $network_plugins[ $basename ]['Version'];

                if ( $was_active !== $is_active || $prev_version !== $current_version ) {
                    // Plugin activated or deactivated, or version changed.

                    if ( $was_active !== $is_active ) {
                        if ( $data['is_active'] != $network_plugins[ $basename ]['is_active'] ) {
                            $network_plugins_cache->plugins[ $basename ]['is_active'] = $data['is_active'];
                        }
                    }

                    if ( $prev_version !== $current_version ) {
                        $network_plugins_cache->plugins[ $basename ]['Version'] = $current_version;
                    }

                    $updated_plugin_data              = $data;
                    $updated_plugin_data['is_active'] = $is_active;
                    $updated_plugin_data['version']   = $current_version;
                    $updated_plugin_data['title']     = $network_plugins[ $basename ]['Name'];
                    $plugins_update_data[]            = $updated_plugin_data;
                }
            }

            // Find new plugins that weren't yet seen before.
            foreach ( $network_plugins as $basename => $data ) {
                if ( ! isset( $network_plugins_cache->plugins[ $basename ] ) ) {
                    // New plugin.
                    $new_plugin = array(
                        'slug'           => $data['slug'],
                        'version'        => $data['Version'],
                        'title'          => $data['Name'],
                        'is_active'      => $data['is_active'],
                        'is_uninstalled' => false,
                    );

                    $network_plugins_cache->plugins[ $basename ] = $new_plugin;

                    $is_site_level_active = (
                        isset( $site_active_plugins[ $basename ] ) &&
                        $site_active_plugins[ $basename ]['is_active']
                    );

                    /**
                     * If not network active, set the activity status based on the site-level plugin status.
                     */
                    if ( ! $new_plugin['is_active'] ) {
                        $new_plugin['is_active'] = $is_site_level_active;
                    }

                    $plugins_update_data[] = $new_plugin;

                    if ( isset( $site_active_plugins[ $basename ] ) ) {
                        $site_active_plugins_cache->plugins[ $basename ]              = $new_plugin;
                        $site_active_plugins_cache->plugins[ $basename ]['is_active'] = $is_site_level_active;
                    }
                }
            }

            $site_active_plugins_cache->md5       = $site_active_plugins_thumbprint;
            $site_active_plugins_cache->timestamp = $time;
            self::$_accounts->set_option( $site_active_plugins_option_name, $site_active_plugins_cache, true );

            $network_plugins_cache->md5       = $network_plugins_thumbprint;
            $network_plugins_cache->timestamp = $time;
            self::$_accounts->set_option( $network_plugins_option_name, $network_plugins_cache, true );

            return $plugins_update_data;
        }

        /**
         * Return a list of modified themes since the last sync.
         *
         * Note:
         *  There's no point to store a themes counter since even if the number of
         *  themes didn't change, we still need to check if the versions are all the
         *  same and the activity state is similar.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.8
         *
         * @return array|false
         */
        private function get_themes_data_for_api() {
            // Alias.
            $option_name = 'all_themes';

            $all_cached_themes = self::$_accounts->get_option( $option_name );

            if ( ! is_object( $all_cached_themes ) ) {
                $all_cached_themes = (object) array(
                    'timestamp' => '',
                    'md5'       => '',
                    'themes'    => array(),
                );
            }

            $time = time();

            if ( ! empty( $all_cached_themes->timestamp ) &&
                 ( $time - $all_cached_themes->timestamp ) < WP_FS__TIME_5_MIN_IN_SEC
            ) {
                // Don't send theme updates if last update was in the past 5 min.
                return false;
            }

            // Write timestamp to lock the logic.
            $all_cached_themes->timestamp = $time;
            self::$_accounts->set_option( $option_name, $all_cached_themes, true );

            // Reload options from DB.
            self::$_accounts->load( true );
            $all_cached_themes = self::$_accounts->get_option( $option_name );

            if ( $time != $all_cached_themes->timestamp ) {
                // If timestamp is different, then another thread captured the lock.
                return false;
            }

            // Get active theme.
            $active_theme            = wp_get_theme();
            $active_theme_stylesheet = $active_theme->get_stylesheet();

            // Check if there's a change in themes.
            $all_themes = wp_get_themes();

            // Check if themes changed.
            ksort( $all_themes );

            $themes_signature = '';
            foreach ( $all_themes as $slug => $data ) {
                $is_active = ( $slug === $active_theme_stylesheet );
                $themes_signature .= $slug . ',' .
                                     $data->version . ',' .
                                     ( $is_active ? '1' : '0' ) . ';';
            }

            // Check if themes status changed (version or active/inactive).
            $themes_changed = ( $all_cached_themes->md5 !== md5( $themes_signature ) );

            $themes_update_data = array();

            if ( $themes_changed ) {
                // Change in themes, report changes.

                // Update existing themes info.
                foreach ( $all_cached_themes->themes as $slug => $data ) {
                    $is_active = ( $slug === $active_theme_stylesheet );

                    if ( ! isset( $all_themes[ $slug ] ) ) {
                        // Plugin uninstalled.
                        $uninstalled_theme_data                   = $data;
                        $uninstalled_theme_data['is_active']      = false;
                        $uninstalled_theme_data['is_uninstalled'] = true;
                        $themes_update_data[]                     = $uninstalled_theme_data;

                        unset( $all_themes[ $slug ] );
                        unset( $all_cached_themes->themes[ $slug ] );
                    } else if ( $data['is_active'] !== $is_active ||
                                $data['version'] !== $all_themes[ $slug ]->version
                    ) {
                        // Plugin activated or deactivated, or version changed.

                        $all_cached_themes->themes[ $slug ]['is_active'] = $is_active;
                        $all_cached_themes->themes[ $slug ]['version']   = $all_themes[ $slug ]->version;

                        $themes_update_data[] = $all_cached_themes->themes[ $slug ];
                    }
                }

                // Find new themes that weren't yet seen before.
                foreach ( $all_themes as $slug => $data ) {
                    if ( ! isset( $all_cached_themes->themes[ $slug ] ) ) {
                        $is_active = ( $slug === $active_theme_stylesheet );

                        // New plugin.
                        $new_plugin = array(
                            'slug'           => $slug,
                            'version'        => $data->version,
                            'title'          => $data->name,
                            'is_active'      => $is_active,
                            'is_uninstalled' => false,
                        );

                        $themes_update_data[]               = $new_plugin;
                        $all_cached_themes->themes[ $slug ] = $new_plugin;
                    }
                }

                $all_cached_themes->md5       = md5( $themes_signature );
                $all_cached_themes->timestamp = time();
                self::$_accounts->set_option( $option_name, $all_cached_themes, true );
            }

            return $themes_update_data;
        }

        /**
         * Get site data for API install request.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.2
         *
         * @param string[] $override
         * @param bool     $include_plugins   Since 1.1.8 by default include plugin changes.
         * @param bool     $include_themes    Since 1.1.8 by default include plugin changes.
         * @param bool     $include_blog_data Since 2.3.0 by default include the current blog's data (language, title, and URL).
         *
         * @return array
         */
        private function get_install_data_for_api(
            array $override,
            $include_plugins = true,
            $include_themes = true,
            $include_blog_data = true
        ) {
            // Alias.
            $permissions = FS_Permission_Manager::instance( $this );

            if ( $permissions->is_extensions_tracking_allowed() ) {
                if ( ! defined( 'WP_FS__TRACK_PLUGINS' ) || false !== WP_FS__TRACK_PLUGINS ) {
                    /**
                     * @since 1.1.8 Also send plugin updates.
                     */
                    if ( $include_plugins && ! isset( $override['plugins'] ) ) {
                        $plugins = $this->get_plugins_data_for_api();
                        if ( ! empty( $plugins ) ) {
                            $override['plugins'] = $plugins;
                        }
                    }
                }

                if ( ! defined( 'WP_FS__TRACK_THEMES' ) || false !== WP_FS__TRACK_THEMES ) {
                    /**
                     * @since 1.1.8 Also send themes updates.
                     */
                    if ( $include_themes && ! isset( $override['themes'] ) ) {
                        $themes = $this->get_themes_data_for_api();
                        if ( ! empty( $themes ) ) {
                            $override['themes'] = $themes;
                        }
                    }
                }
            }

            $versions = $this->get_versions();

            $blog_data = array();
            if ( $include_blog_data ) {
                $blog_data['url'] = self::get_unfiltered_site_url();

                if ( $permissions->is_diagnostic_tracking_allowed() ) {
                    $blog_data = array_merge( $blog_data, array(
                        'language' => self::get_sanitized_language(),
                        'title'    => get_bloginfo( 'name' ),
                    ) );
                }
            }

            return array_merge( $versions, $blog_data, array(
                'version'         => $this->get_plugin_version(),
                'is_premium'      => $this->is_premium(),
                // Special params.
                'is_active'       => true,
                'is_uninstalled'  => false,
            ), $override );
        }

        /**
         * Update installs details.
         *
         * @todo   V1 of multiste network support doesn't support plugin and theme data sending.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string[] string           $override
         * @param bool     $only_diff
         * @param bool     $is_keepalive
         * @param bool     $include_plugins Since 1.1.8 by default include plugin changes.
         * @param bool     $include_themes  Since 1.1.8 by default include plugin changes.
         *
         * @return array
         */
        private function get_installs_data_for_api(
            array $override,
            $only_diff = false,
            $is_keepalive = false,
            $include_plugins = true,
            $include_themes = true
        ) {
            /**
             * @since 1.1.8 Also send plugin updates.
             */
//            if ( $include_plugins && ! isset( $override['plugins'] ) ) {
//                $plugins = $this->get_plugins_data_for_api();
//                if ( ! empty( $plugins ) ) {
//                    $override['plugins'] = $plugins;
//                }
//            }
            /**
             * @since 1.1.8 Also send themes updates.
             */
//            if ( $include_themes && ! isset( $override['themes'] ) ) {
//                $themes = $this->get_themes_data_for_api();
//                if ( ! empty( $themes ) ) {
//                    $override['themes'] = $themes;
//                }
//            }

            // Common properties.
            $versions = $this->get_versions();
            $common   = array_merge( $versions, array(
                'version'    => $this->get_plugin_version(),
                'is_premium' => $this->is_premium(),
            ), $override );


            $is_common_diff_for_any_site = false;
            $common_diff_union           = array();

            $installs_data = array();

            $sites = self::get_sites();

            $subsite_data_for_api_by_install_id      = array();
            $install_url_by_install_id               = array();
            $subsite_registration_date_by_install_id = array();

            foreach ( $sites as $site ) {
                $blog_id = self::get_site_blog_id( $site );

                $install = $this->get_install_by_blog_id( $blog_id );

                if ( is_object( $install ) ) {
                    if ( $install->user_id != $this->_user->id ) {
                        // Install belongs to a different owner.
                        continue;
                    }

                    if ( ! $this->is_tracking_allowed( $blog_id, $install ) ) {
                        // Don't send updates regarding opted-out installs.
                        continue;
                    }

                    $install_data = $this->get_site_info( $site, true );

                    if ( FS_Clone_Manager::instance()->is_temporary_duplicate_by_blog_id( $install_data['blog_id'] ) ) {
                        continue;
                    }

                    $uid               = $install_data['uid'];
                    $url               = $install_data['url'];
                    $registration_date = $install_data['registration_date'];

                    if ( isset( $subsite_data_for_api_by_install_id[ $install->id ] ) ) {
                        $clone_subsite_data              = $subsite_data_for_api_by_install_id[ $install->id ];
                        $clone_install_url               = $install_url_by_install_id[ $install->id ];
                        $clone_subsite_registration_date = $subsite_registration_date_by_install_id[ $install->id ];

                        $skip = false;

                        if (
                            ! empty( $install_data['registration_date'] ) &&
                            ! empty( $clone_subsite_registration_date )
                        ) {
                            /**
                             * If the current subsite was created after the other subsite that is also linked to the same install ID, we assume that it's a clone (not the original), and therefore, would skip its processing.
                             *
                             * @author Leo Fajardo (@leorw)
                             * @since 2.5.1
                             */
                            $skip = ( strtotime( $install_data['registration_date'] ) > strtotime( $clone_subsite_registration_date ) );
                        } else if (
                            /**
                             * If we already have an install with the same URL as the subsite it's stored in, skip the current subsite. Otherwise, replace the existing install's data with the current subsite's install's data if the URLs match.
                             *
                             * @author Leo Fajardo (@leorw)
                             * @since 2.5.0
                             */
                            fs_strip_url_protocol( untrailingslashit( $clone_install_url ) ) === fs_strip_url_protocol( untrailingslashit( $clone_subsite_data['url'] ) ) ||
                            fs_strip_url_protocol( untrailingslashit( $install->url ) ) !== fs_strip_url_protocol( untrailingslashit( $url ) )
                        ) {
                            $skip = true;
                        }

                        if ( $skip ) {
                            // Store the skipped subsite's ID so that the clone resolution manager can try to resolve the clone install that is stored in that subsite later on.
                            FS_Clone_Manager::instance()->store_blog_install_info( $blog_id );
                            continue;
                        }
                    }

                    unset( $install_data['blog_id'] );
                    unset( $install_data['uid'] );
                    unset( $install_data['url'] );
                    unset( $install_data['registration_date'] );

                    $install_data['is_active']       = $this->is_active_for_site( $blog_id );
                    $install_data['is_uninstalled']  = $install->is_uninstalled;

                    $common_diff    = null;
                    $is_common_diff = false;
                    if ( $only_diff ) {
                        $install_data = $this->get_install_diff_for_api( $install_data, $install, $override );
                        $common_diff  = $this->get_install_diff_for_api( $common, $install, $override );

                        $is_common_diff = ! empty( $common_diff );

                        if ( $is_common_diff ) {
                            foreach ( $common_diff as $k => $v ) {
                                if ( ! isset( $common_diff_union[ $k ] ) ) {
                                    $common_diff_union[ $k ] = $v;
                                }
                            }
                        }

                        $is_common_diff_for_any_site = $is_common_diff_for_any_site || $is_common_diff;
                    }

                    if ( ! empty( $install_data ) || $is_common_diff || $is_keepalive ) {
                        // Add install ID and site unique ID.
                        $install_data['id']  = $install->id;
                        $install_data['uid'] = $uid;
                        $install_data['url'] = $url;

                        $subsite_data_for_api_by_install_id[ $install->id ]       = $install_data;
                        $install_url_by_install_id[ $install->id ]                = $install->url;
                        $subsite_registration_date_by_install_id[ $install->id ]  = $registration_date;
                    }
                }
            }

            restore_current_blog();

            $installs_data = array_merge(
                $installs_data,
                array_values( $subsite_data_for_api_by_install_id )
            );

            if ( 0 < count( $installs_data ) && ( $is_common_diff_for_any_site || ! $only_diff ) ) {
                if ( ! $only_diff ) {
                    $installs_data[] = $common;
                } else if ( ! empty( $common_diff_union ) ) {
                    $installs_data[] = $common_diff_union;
                }
            }

            foreach ( $installs_data as &$data ) {
                $data = (object) $data;
            }

            return $installs_data;
        }

        /**
         * Compare site actual data to the stored install data and return the differences for an API data sync.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param array    $site
         * @param FS_Site  $install
         * @param string[] string $override
         *
         * @return array
         */
        private function get_install_diff_for_api( $site, $install, $override = array() ) {
            $diff             = array();
            $special          = array();
            $special_override = false;

            foreach ( $site as $p => $v ) {
                if ( property_exists( $install, $p ) ) {
                    if ( ( is_bool( $install->{$p} ) || ! empty( $install->{$p} ) ) &&
                         $install->{$p} != $v
                    ) {
                        $val = self::get_api_sanitized_property( $p, $v );

                        if ( $install->{$p} != $val ) {
                            $install->{$p} = $val;
                            $diff[ $p ]    = $val;
                        }
                    }
                } else {
                    $special[ $p ] = $v;

                    if ( isset( $override[ $p ] ) ||
                         'plugins' === $p ||
                         'themes' === $p
                    ) {
                        $special_override = true;
                    }
                }
            }

            if ( $special_override || 0 < count( $diff ) ) {
                // Add special params only if has at least one
                // standard param, or if explicitly requested to
                // override a special param or a param which is not exist
                // in the install object.
                $diff = array_merge( $diff, $special );
            }

            return $diff;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.5.1
         */
        private function send_pending_clone_update_once() {
            $this->_logger->entrance();

            if ( ! empty( $this->_storage->clone_id ) ) {
                return;
            }

            $install_clone = $this->get_api_site_scope()->call(
                '/clones',
                'post',
                array( 'site_url' => self::get_unfiltered_site_url() )
            );

            if ( $this->is_api_result_entity( $install_clone ) ) {
                $this->_storage->clone_id = $install_clone->id;
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.5.1
         *
         * @param string  $resolution_type
         * @param FS_Site $clone_context_install
         */
        function send_clone_resolution_update( $resolution_type, $clone_context_install ) {
            $this->_logger->entrance();

            if ( empty( $this->_storage->clone_id ) ) {
                return;
            }

            $new_install_id = null;
            $current_site   = null;

            $flush = false;

            /**
             * If the current site is now different from the context install before the clone resolution, we need to override `$this->_site` so that the API call below will be made with the right install scope entity.
             */
            if ( $clone_context_install->id != $this->_site->id ) {
                $new_install_id = $this->_site->id;
                $current_site   = $this->_site;
                $this->_site    = $clone_context_install;

                $flush = true;
            }

            $this->get_api_site_scope( $flush )->call(
                "/clones/{$this->_storage->clone_id}",
                'put',
                array(
                    'resolution'     => $resolution_type,
                    'new_install_id' => $new_install_id,
                )
            );

            if ( is_object( $current_site ) ) {
                /**
                 * Ensure that the install scope entity is updated back to the previous install entity.
                 */
                $this->_site  = $current_site;

                // Restore the previous install scope entity of the API.
                $this->get_api_site_scope( true );
            }
        }

        /**
         * Update install only if changed.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @param string[] string $override
         * @param bool     $flush
         * @param bool     $is_two_way_sync @since 2.5.0 If true and there's a successful API request, the install sync cron will be cleared.
         *
         * @return false|object|string
         */
        private function send_install_update( $override = array(), $flush = false, $is_two_way_sync = false ) {
            $this->_logger->entrance();

            $check_properties = $this->get_install_data_for_api( $override );

            if ( $flush ) {
                $params = $check_properties;
            } else {
                $params = $this->get_install_diff_for_api( $check_properties, $this->_site, $override );
            }

            if ( empty( $params ) ) {
                $keepalive_only_update = $this->should_send_keepalive_update();

                if ( ! $keepalive_only_update ) {
                    /**
                     * There are no updates to send including keepalive.
                     *
                     * @author Leo Fajardo (@leorw)
                     * @since 2.2.3
                     */
                    return false;
                }
            }

            if ( $is_two_way_sync ) {
                /**
                 * Update last install sync timestamp during a two-way sync call as we expect that updates are sent during this call.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.2.3
                 */
                if ( ! is_multisite() ) {
                    // Update last install sync timestamp.
                    $this->set_cron_execution_timestamp( 'install_sync' );
                }

                $params['uid'] = $this->get_anonymous_id();
            }

            $this->set_keepalive_timestamp();

            // Send updated values to FS.
            $site = $this->api_site_call( '/', 'put', $params, true );

            if ( $is_two_way_sync && $this->is_api_result_entity( $site ) ) {
                /**
                 * Clear scheduled install sync after a two-way sync call.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.2.3
                 */
                if ( ! is_multisite() ) {
                    // I successfully sent install update, clear scheduled sync if exist.
                    $this->clear_install_sync_cron();
                }
            }

            return $site;
        }

        /**
         * Update installs only if changed.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string[] string $override
         * @param bool     $flush
         * @param bool     $is_two_way_sync @since 2.5.0 If true and there's a successful API request, the install sync cron will be cleared.
         *
         * @return false|object|string
         */
        private function send_installs_update( $override = array(), $flush = false, $is_two_way_sync = false ) {
            $this->_logger->entrance();

            /**
             * Pass `true` to use the network level storage since the update is for many installs.
             *
             * @author Leo Fajardo (@leorw)
             * @since 2.2.3
             */
            $should_send_keepalive = $this->should_send_keepalive_update( true );

            $installs_data = $this->get_installs_data_for_api( $override, ! $flush, $should_send_keepalive );

            if ( empty( $installs_data ) ) {
                return false;
            }

            if ( $is_two_way_sync ) {
                // Update last install sync timestamp during a two-way sync call as we expect that updates are sent during this call.
                $this->set_cron_execution_timestamp( 'install_sync' );
            }

            /**
             * Pass `true` to use the network level storage since the update is for many installs.
             *
             * @author Leo Fajardo (@leorw)
             * @since 2.2.3
             */
            $this->set_keepalive_timestamp( true );

            // Send updated values to FS.
            $result = $this->get_api_user_scope()->call( "/plugins/{$this->_plugin->id}/installs.json", 'put', $installs_data );

            if ( $is_two_way_sync && $this->is_api_result_object( $result, 'installs' ) ) {
                // I successfully sent a two-way installs update, clear the scheduled install sync if it exists.
                $this->clear_install_sync_cron();
            }

            return $result;
        }

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @param bool|null $use_network_level_storage
         *
         * @return bool
         */
        private function should_send_keepalive_update( $use_network_level_storage = null ) {
            $keepalive_timestamp = $this->_storage->get( 'keepalive_timestamp', 0, $use_network_level_storage );

            if ( $keepalive_timestamp < ( time() - WP_FS__TIME_WEEK_IN_SEC ) ) {
                // If updated more than 7 days ago, trigger a keepalive and update the time it was triggered.
                return true;
            } else {
                // If updated 7 days ago or less, "flip a coin", if the value is 7 trigger a keepalive and update the last time it was triggered.
                return ( 7 == rand( 1, 7 ) );
            }
        }

        /**
         * Syncs the install owner's data if needed (i.e., if the install owner is different from the loaded user).
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.3.2
         */
        private function maybe_sync_install_user() {
            if ( $this->_user->id == $this->_site->user_id ) {
                return;
            }

            // Fetch user data and store if found.
            $this->sync_user_by_current_install();
        }

        /**
         * Update install only if changed.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @param string[] string $override
         * @param bool     $flush
         */
        function sync_install( $override = array(), $flush = false ) {
            $this->_logger->entrance();

            $site = $this->send_install_update( $override, $flush, true );

            if ( false === $site ) {
                // No sync required.
                return;
            }

            if ( ! $this->is_api_result_entity( $site ) ) {
                // Failed to sync, don't update locally.
                return;
            }

            $this->_site = new FS_Site( $site );

            $this->_store_site( true );
        }

        /**
         * Update install only if changed.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @param string[] string $override
         * @param bool     $flush
         */
        private function sync_installs( $override = array(), $flush = false ) {
            $this->_logger->entrance();

            $result = $this->send_installs_update( $override, $flush, true );

            if ( false === $result ) {
                // No sync required.
                return;
            }

            if ( ! $this->is_api_result_object( $result, 'installs' ) ) {
                // Failed to sync, don't update locally.
                return;
            }

            $address_to_blog_map = $this->get_address_to_blog_map();

            foreach ( $result->installs as $install ) {
                $this->_site = new FS_Site( $install );

                $address = trailingslashit( fs_strip_url_protocol( $install->url ) );
                $blog_id = $address_to_blog_map[ $address ];

                $this->_store_site( true, $blog_id );
            }
        }

        /**
         * Track install's custom event.
         *
         * IMPORTANT:
         *      Custom event tracking is currently only supported for specific clients.
         *      If you are not one of them, please don't use this method. If you will,
         *      the API will simply ignore your request based on the plugin ID.
         *
         * Need custom tracking for your plugin or theme?
         *      If you are interested in custom event tracking please contact yo@freemius.com
         *      for further details.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1
         *
         * @param string $name       Event name.
         * @param array  $properties Associative key/value array with primitive values only
         * @param bool   $process_at A valid future date-time in the following format Y-m-d H:i:s.
         * @param bool   $once       If true, event will be tracked only once. IMPORTANT: Still trigger the API call.
         *
         * @return object|false Event data or FALSE on failure.
         *
         * @throws \Freemius_InvalidArgumentException
         */
        public function track_event( $name, $properties = array(), $process_at = false, $once = false ) {
            $this->_logger->entrance( http_build_query( array( 'name' => $name, 'once' => $once ) ) );

            if ( ! $this->is_registered() ) {
                return false;
            }

            $event = array( 'type' => $name );

            if ( is_numeric( $process_at ) && $process_at > time() ) {
                $event['process_at'] = $process_at;
            }

            if ( $once ) {
                $event['once'] = true;
            }

            if ( ! empty( $properties ) ) {
                // Verify associative array values are primitive.
                foreach ( $properties as $k => $v ) {
                    if ( ! is_scalar( $v ) ) {
                        throw new Freemius_InvalidArgumentException( 'The $properties argument must be an associative key/value array with primitive values only.' );
                    }
                }

                $event['properties'] = $properties;
            }

            $result = $this->get_api_site_scope()->call( 'events.json', 'post', $event );

            return $this->is_api_error( $result ) ?
                false :
                $result;
        }

        /**
         * Track install's custom event only once, but it still triggers the API call.
         *
         * IMPORTANT:
         *      Custom event tracking is currently only supported for specific clients.
         *      If you are not one of them, please don't use this method. If you will,
         *      the API will simply ignore your request based on the plugin ID.
         *
         * Need custom tracking for your plugin or theme?
         *      If you are interested in custom event tracking please contact yo@freemius.com
         *      for further details.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1
         *
         * @param string $name       Event name.
         * @param array  $properties Associative key/value array with primitive values only
         * @param bool   $process_at A valid future date-time in the following format Y-m-d H:i:s.
         *
         * @return object|false Event data or FALSE on failure.
         *
         * @throws \Freemius_InvalidArgumentException
         *
         * @user   Freemius::track_event()
         */
        public function track_event_once( $name, $properties = array(), $process_at = false ) {
            return $this->track_event( $name, $properties, $process_at, true );
        }

        /**
         * Plugin uninstall hook.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         *
         * @param bool $check_user Enforce checking if user have plugins activation privileges.
         */
        function _uninstall_plugin_event( $check_user = true ) {
            $this->_logger->entrance( 'slug = ' . $this->_slug );

            if ( $check_user && ! current_user_can( 'activate_plugins' ) ) {
                return;
            }

            $params           = array();
            $uninstall_reason = null;
            if ( isset( $this->_storage->uninstall_reason ) ) {
                $uninstall_reason      = $this->_storage->uninstall_reason;
                $params['reason_id']   = $uninstall_reason->id;
                $params['reason_info'] = $uninstall_reason->info;
            }

            if ( ! $this->is_registered() ) {
                // Send anonymous uninstall event only if user submitted a feedback.
                if ( isset( $uninstall_reason ) ) {
                    if ( isset( $uninstall_reason->is_anonymous ) && ! $uninstall_reason->is_anonymous ) {
                        $this->opt_in( false, false, false, false, true );
                    } else {
                        $params['uid'] = $this->get_anonymous_id();
                        $this->get_api_plugin_scope()->call( 'uninstall.json', 'put', $params );
                    }
                }
            } else {
                $params = array_merge( $params, array(
                    'is_active'      => false,
                    'is_uninstalled' => true,
                ) );

                if ( $this->_is_network_active ) {
                    // Send uninstall event.
                    $this->send_installs_update( $params );
                } else {
                    // Send uninstall event and handle the result.
                    $this->sync_install( $params );
                }
            }

            // @todo Decide if we want to delete plugin information from db.
        }

        /**
         * Set the basename of the current product and hook _activate_plugin_event_hook() to the activation action.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.2.1
         *
         * @param string $is_premium
         * @param string $caller
         *
         * @return void
         */
        function set_basename( $is_premium, $caller ) {
            $basename = plugin_basename( $caller );

            $current_basename = $is_premium ?
                $this->_premium_plugin_basename :
                $this->_free_plugin_basename;

            if ( $current_basename == $basename ) {
                // Basename value set correctly.
                return;
            }

            if ( $is_premium ) {
                $this->_premium_plugin_basename = $basename;
            } else {
                $this->_free_plugin_basename = $basename;
            }

            $plugin_dir = dirname( $this->_plugin_dir_path ) . '/';

            register_activation_hook(
                $plugin_dir . $basename,
                array( &$this, '_activate_plugin_event_hook' )
            );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.1
         * @since  2.2.1 If the context product is in its premium version, use the current module's basename, even if it was renamed.
         *
         * @return string
         */
        function premium_plugin_basename() {
            if ( ! isset( $this->_premium_plugin_basename ) ) {
                $this->_premium_plugin_basename = $this->is_premium() ?
                    // The product is premium, so use the current basename.
                    $this->_plugin_basename :
                    $this->get_premium_slug() . '/' . basename( $this->_free_plugin_basename );
            }

            return $this->_premium_plugin_basename;
        }

        /**
         * Uninstall plugin hook. Called only when connected his account with Freemius for active sites tracking.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.2
         */
        public static function _uninstall_plugin_hook() {
            self::_load_required_static();

            self::$_static_logger->entrance();

            if ( ! current_user_can( 'activate_plugins' ) ) {
                return;
            }

            $plugin_file = substr( current_filter(), strlen( 'uninstall_' ) );

            self::$_static_logger->info( 'plugin = ' . $plugin_file );

            define( 'WP_FS__UNINSTALL_MODE', true );

            $fs = self::get_instance_by_file( $plugin_file );

            if ( is_object( $fs ) ) {
                $fs->remove_sdk_reference();
                
                self::require_plugin_essentials();

                if ( is_plugin_active( $fs->_free_plugin_basename ) ||
                     is_plugin_active( $fs->premium_plugin_basename() )
                ) {
                    // Deleting Free or Premium plugin version while the other version still installed.
                    return;
                }

                if (
                    ! $fs->is_clone() &&
                    /**
                     * If there's a context install, run this method only when there's also a context user (e.g., when cloning a subsite of a multisite network into a single-site installation, it's possible for an install to be associated with a non-existing user entity; we want Freemius to be off in this case, while we are trying to recover the user).
                     *
                     * @author Leo Fajardo
                     */
                    ( ! is_object( $fs->_site ) || $fs->is_registered() )
                ) {
                    $fs->_uninstall_plugin_event();
                }

                $fs->do_action( 'after_uninstall' );
            }
        }

        #----------------------------------------------------------------------------------
        #region Plugin Information
        #----------------------------------------------------------------------------------

        /**
         * Load WordPress core plugin.php essential module.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.1
         */
        private static function require_plugin_essentials() {
            if ( ! function_exists( 'get_plugins' ) ) {
                self::$_static_logger->log( 'Including wp-admin/includes/plugin.php...' );

                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
        }

        /**
         * Load WordPress core pluggable.php module.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.2
         */
        private static function require_pluggable_essentials() {
            if ( ! function_exists( 'wp_get_current_user' ) ) {
                require_once ABSPATH . 'wp-includes/pluggable.php';
            }
        }

        /**
         * Return plugin data.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         *
         * @param bool $reparse_plugin_metadata
         *
         * @return array
         */
        function get_plugin_data( $reparse_plugin_metadata = false ) {
            if ( ! isset( $this->_plugin_data ) || $reparse_plugin_metadata ) {
                self::require_plugin_essentials();

                if ( $this->is_plugin() ) {
                    /**
                     * @author Vova Feldman (@svovaf)
                     * @since  1.2.0 When using get_plugin_data() do NOT translate plugin data.
                     *
                     * @link   https://github.com/Freemius/wordpress-sdk/issues/77
                     */
                    $plugin_data = get_plugin_data(
                        $this->_plugin_main_file_path,
                        false,
                        false
                    );
                } else {
                    $theme_data = wp_get_theme();

                    if ( $this->_plugin_basename !== $theme_data->get_stylesheet() && is_child_theme() ) {
                        $parent_theme = $theme_data->parent();

                        if ( ( $parent_theme instanceof WP_Theme ) && $this->_plugin_basename === $parent_theme->get_stylesheet() ) {
                            $theme_data = $parent_theme;
                        }
                    }

                    $plugin_data = array(
                        'Name'        => $theme_data->get( 'Name' ),
                        'Version'     => $theme_data->get( 'Version' ),
                        'Author'      => $theme_data->get( 'Author' ),
                        'Description' => $theme_data->get( 'Description' ),
                        'PluginURI'   => $theme_data->get( 'ThemeURI' ),
                    );
                }

                $this->_plugin_data = $plugin_data;
            }

            return $this->_plugin_data;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         * @since  1.2.2.5 If slug not set load slug by module ID.
         *
         * @return string Plugin slug.
         */
        function get_slug() {
            if ( ! isset( $this->_slug ) ) {
                $id_slug_type_path_map = self::$_accounts->get_option( 'id_slug_type_path_map', array() );
                $this->_slug           = $id_slug_type_path_map[ $this->_module_id ]['slug'];
            }

            return $this->_slug;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.2.1
         *
         * @return string
         */
        function get_premium_slug() {
            return ( is_object( $this->_plugin ) && ! empty( $this->_plugin->premium_slug ) ) ?
                $this->_plugin->premium_slug :
                "{$this->_slug}-premium";
        }

        /**
         * Retrieve the desired folder name for the product.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.7
         *
         * @return string Plugin slug.
         */
        function get_target_folder_name() {
            return $this->can_use_premium_code() ?
                $this->_plugin->premium_slug :
                $this->_slug;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         *
         * @return number Plugin ID.
         */
        function get_id() {
            return $this->_plugin->id;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.2.4
         *
         * @return number|null Bundle ID.
         */
        function get_bundle_id() {
            return ( isset( $this->_plugin->bundle_id ) && FS_Plugin::is_valid_id( $this->_plugin->bundle_id ) ) ?
                $this->_plugin->bundle_id :
                null;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.3.1
         *
         * @return string|null Bundle public key.
         */
        function get_bundle_public_key() {
            return isset( $this->_plugin->bundle_public_key ) ?
                $this->_plugin->bundle_public_key :
                null;
        }

        /**
         * Get whether the SDK has been initiated in the context of a Bundle.
         *
         * This will return true, if `bundle_id` is present in the SDK init parameters.
         *
         * ```php
         * $my_fs = fs_dynamic_init( array(
         *     // ...
         *     'bundle_id'         => 'XXXX', // Will return true since we have bundle id.
         *     'bundle_public_key' => 'pk_XXXX',
         * ) );
         * ```
         *
         * @author Swashata Ghosh (@swashata)
         * @since  2.5.0
         *
         * @return bool True if we are running in bundle context, false otherwise.
         */
        private function has_bundle_context() {
            return ! is_null( $this->get_bundle_id() );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.5
         *
         * @return string Freemius SDK version
         */
        function get_sdk_version() {
            return $this->version;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.5
         *
         * @return number Parent plugin ID (if parent exist).
         */
        function get_parent_id() {
            return $this->is_addon() ?
                $this->get_parent_instance()->get_id() :
                $this->_plugin->id;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.3.1
         *
         * @return string
         */
        function get_usage_tracking_terms_url() {
            return $this->apply_filters(
                'usage_tracking_terms_url',
                "https://freemius.com/product/opt-in/{$this->_plugin->id}/{$this->_slug}/"
            );
        }

        /**
         * @todo (For LiteSDK) We can refactor this and other related functions giving links to several landing pages on freemius.com to come from a separate class like `FS_Terms_Pages`. This would get a `FS_WP_Hook` (hypothetical) instance as a dependency and use it to hook into the `license_activation_terms_url` or related filters. The entry level instance from `ms_fs()` would hold a public read-only variable `my_fs()->terms_pages` which would be an instance of `FS_Terms_Pages` and would hold all the links to the terms pages.
         * @since 2.5.8
         *
         * @return string
         */
        function get_license_activation_terms_url() {
            return $this->apply_filters(
                'license_activation_terms_url',
                "https://freemius.com/product/license-activation/{$this->_plugin->id}/{$this->_slug}/"
            );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.3.1
         *
         * @return string
         */
        function get_eula_url() {
            return $this->apply_filters(
                'eula_url',
                "https://freemius.com/product/{$this->_plugin->id}/{$this->_slug}/legal/eula/"
            );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         *
         * @return string Plugin public key.
         */
        function get_public_key() {
            return $this->_plugin->public_key;
        }

        /**
         * Will be available only on sandbox mode.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @return mixed Plugin secret key.
         */
        function get_secret_key() {
            return $this->_plugin->secret_key;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.1
         *
         * @return bool
         */
        function has_secret_key() {
            return ! empty( $this->_plugin->secret_key );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @param string|bool $premium_suffix
         *
         * @return string
         */
        function get_plugin_name( $premium_suffix = false ) {
            $this->_logger->entrance();

            /**
             * This `if-else` can be squeezed into a single `if` but I intentionally split it for code readability.
             *
             * @author Vova Feldman
             */
            if ( ! isset( $this->_plugin_name ) ) {
                // Name is not yet set.
                $this->set_name( $premium_suffix );
            } else if (
                ! empty( $premium_suffix ) &&
                ( ! is_object( $this->_plugin ) || $this->_plugin->premium_suffix !== $premium_suffix )
            ) {
                // Name is already set, but there's a change in the premium suffix.
                $this->set_name( $premium_suffix );
            }

            return $this->_plugin_name;
        }

        /**
         * Calculates and stores the product's name. This helper function was created specifically for get_plugin_name() just to make the code clearer.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.2.1
         *
         * @param string $premium_suffix
         */
        private function set_name( $premium_suffix = '' ) {
            $plugin_data = $this->get_plugin_data();

            // Get name.
            $this->_plugin_name = $plugin_data['Name'];

            if ( is_string( $premium_suffix ) ) {
                $premium_suffix = trim( $premium_suffix );

                if ( ! empty( $premium_suffix ) ) {
                    // Check if plugin name contains " (premium)" or a custom suffix and remove it.
                    $suffix     = ( ' ' . strtolower( $premium_suffix ) );
                    $suffix_len = strlen( $suffix );

                    if ( strlen( $plugin_data['Name'] ) > $suffix_len &&
                         $suffix === substr( strtolower( $plugin_data['Name'] ), - $suffix_len )
                    ) {
                        $this->_plugin_name = substr( $plugin_data['Name'], 0, - $suffix_len );
                    }
                }
            }

            $this->_logger->departure( 'Name = ' . $this->_plugin_name );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.0
         *
         * @param bool $reparse_plugin_metadata
         *
         * @return string
         */
        function get_plugin_version( $reparse_plugin_metadata = false ) {
            $this->_logger->entrance();

            $plugin_data = $this->get_plugin_data( $reparse_plugin_metadata );

            $this->_logger->departure( 'Version = ' . $plugin_data['Version'] );

            return $this->apply_filters( 'plugin_version', $plugin_data['Version'] );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.7
         *
         * @return string
         */
        function get_plugin_title() {
            $this->_logger->entrance();

            $title = $this->_plugin->title;

            return $this->apply_filters( 'plugin_title', $title );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @param bool $lowercase
         *
         * @return string
         */
        function get_module_label( $lowercase = false ) {
            $label = $this->is_addon() ?
                $this->get_text_inline( 'Add-On', 'addon' ) :
                ( $this->is_plugin() ?
                    $this->get_text_inline( 'Plugin', 'plugin' ) :
                    $this->get_text_inline( 'Theme', 'theme' ) );

            if ( $lowercase ) {
                $label = strtolower( $label );
            }

            return $label;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @return string
         */
        function get_plugin_basename() {
            if ( ! isset( $this->_plugin_basename ) ) {
                if ( $this->is_plugin() ) {
                    $this->_plugin_basename = plugin_basename( $this->_plugin_main_file_path );
                } else {
                    $this->_plugin_basename = basename( dirname( $this->_plugin_main_file_path ) );
                }
            }

            return $this->_plugin_basename;
        }

        function get_plugin_folder_name() {
            $this->_logger->entrance();

            $plugin_folder = $this->_plugin_basename;

            while ( '.' !== dirname( $plugin_folder ) ) {
                $plugin_folder = dirname( $plugin_folder );
            }

            $this->_logger->departure( 'Folder Name = ' . $plugin_folder );

            return $plugin_folder;
        }

        #endregion ------------------------------------------------------------------

        /* Account
        ------------------------------------------------------------------------------------------------------------------*/

        /**
         * Find plugin's slug by plugin's basename.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @param string $plugin_base_name
         *
         * @return false|string
         */
        private static function find_slug_by_basename( $plugin_base_name ) {
            $file_slug_map = self::$_accounts->get_option( 'file_slug_map', array() );

            if ( ! array( $file_slug_map ) || ! isset( $file_slug_map[ $plugin_base_name ] ) ) {
                return false;
            }

            return $file_slug_map[ $plugin_base_name ];
        }

        /**
         * Store the map between the plugin's basename to the slug.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         */
        private function store_file_slug_map() {
            $file_slug_map = self::$_accounts->get_option( 'file_slug_map', array() );

            if ( ! array( $file_slug_map ) ) {
                $file_slug_map = array();
            }

            if ( ! isset( $file_slug_map[ $this->_plugin_basename ] ) ||
                 $file_slug_map[ $this->_plugin_basename ] !== $this->_slug
            ) {
                $file_slug_map[ $this->_plugin_basename ] = $this->_slug;
                self::$_accounts->set_option( 'file_slug_map', $file_slug_map, true );
            }
        }

        /**
         * @return array[number]FS_User
         */
        static function get_all_users() {
            $users = self::maybe_get_entities_account_option( 'users', array() );

            if ( ! is_array( $users ) ) {
                $users = array();
            }

            return $users;
        }

        /**
         * @param string   $module_type
         * @param null|int $blog_id Since 2.0.0
         *
         * @return array[string]FS_Site
         */
        public static function get_all_sites(
            $module_type = WP_FS__MODULE_TYPE_PLUGIN,
            $blog_id = null,
            $is_backup = false
        ) {
            $sites = self::get_account_option(
                ( $is_backup ? 'prev_' : '' ) . 'sites',
                $module_type,
                $blog_id
            );

            if ( ! is_array( $sites ) ) {
                $sites = array();
            }

            return $sites;
        }

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @since  1.2.2
         *
         * @param string   $option_name
         * @param string   $module_type
         * @param null|int $network_level_or_blog_id Since 2.0.0
         *
         * @return mixed
         */
        public static function get_account_option( $option_name, $module_type = null, $network_level_or_blog_id = null ) {
            if ( ! is_null( $module_type ) && WP_FS__MODULE_TYPE_PLUGIN !== $module_type ) {
                $option_name = $module_type . '_' . $option_name;
            }

            return self::maybe_get_entities_account_option( $option_name, array(), $network_level_or_blog_id );
        }

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @since  1.2.2
         *
         * @param string   $option_name
         * @param mixed    $option_value
         * @param bool     $store
         * @param null|int $network_level_or_blog_id Since 2.0.0
         */
        private function set_account_option( $option_name, $option_value, $store, $network_level_or_blog_id = null ) {
            self::set_account_option_by_module(
                $this->_module_type,
                $option_name,
                $option_value,
                $store,
                $network_level_or_blog_id
            );
        }

        /**
         * @author Vova Feldman (@svovaf)
         *
         * @since  1.2.2.7
         *
         * @param string   $module_type
         * @param string   $option_name
         * @param mixed    $option_value
         * @param bool     $store
         * @param null|int $network_level_or_blog_id Since 2.0.0
         */
        private static function set_account_option_by_module(
            $module_type,
            $option_name,
            $option_value,
            $store,
            $network_level_or_blog_id = null
        ) {
            if ( WP_FS__MODULE_TYPE_PLUGIN != $module_type ) {
                $option_name = $module_type . '_' . $option_name;
            }

            self::$_accounts->set_option( $option_name, $option_value, $store, $network_level_or_blog_id );
        }

        /**
         * This method can also return non-entity or non-entities collection option like the `user_id_license_ids_map` option.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.3.1
         *
         * @param string        $option_name
         * @param mixed         $default
         * @param null|bool|int $network_level_or_blog_id When an integer, use the given blog storage. When `true` use the multisite storage (if there's a network). When `false`, use the current context blog storage. When `null`, the decision which storage to use (MS vs. Current S) will be handled internally and determined based on the $option (based on self::$_SITE_LEVEL_PARAMS).
         *
         * @return mixed|FS_Plugin[]|FS_User[]|FS_Site[]|FS_Plugin_License[]|FS_Plugin_Plan[]|FS_Plugin_Tag[]
         */
        private static function maybe_get_entities_account_option( $option_name, $default = null, $network_level_or_blog_id = null ) {
            $option = self::$_accounts->get_option( $option_name, $default, $network_level_or_blog_id );

            $class_name = '';

            if ( fs_starts_with( $option_name, WP_FS__MODULE_TYPE_THEME . '_' ) ) {
                $option_name = str_replace( WP_FS__MODULE_TYPE_THEME . '_', '', $option_name );
            }
            
            switch ( $option_name ) {
                case 'plugins':
                case 'themes':
                case 'addons':
                $class_name = FS_Plugin::get_class_name();
                    break;
                case 'users':
                    $class_name = FS_User::get_class_name();
                    break;
                case 'sites':
                    $class_name = FS_Site::get_class_name();
                    break;
                case 'licenses':
                case 'all_licenses':
                    $class_name = FS_Plugin_License::get_class_name();
                    break;
                case 'plans':
                    $class_name = FS_Plugin_Plan::get_class_name();
                    break;
                case 'updates':
                    $class_name = FS_Plugin_Tag::get_class_name();
                    break;
            }

            if ( empty( $class_name ) ) {
                return $option;
            }

            return fs_get_entities( $option, $class_name );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param number|null $module_id
         *
         * @return FS_Plugin_License[]
         */
        private static function get_all_licenses( $module_id = null ) {
            $licenses = self::get_account_option( 'all_licenses' );

            if ( ! is_array( $licenses ) ) {
                $licenses = array();
            }

            if ( is_null( $module_id ) ) {
                return $licenses;
            }

            $licenses = isset( $licenses[ $module_id ] ) ?
                $licenses[ $module_id ] :
                array();

            return $licenses;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.0.0
         *
         * @param number      $module_id
         * @param number|null $user_id
         *
         * @return array
         */
        private static function get_user_id_license_ids_map( $module_id, $user_id = null ) {
            $all_modules_user_id_license_ids_map = self::get_account_option( 'user_id_license_ids_map' );

            if ( ! is_array( $all_modules_user_id_license_ids_map ) ) {
                $all_modules_user_id_license_ids_map = array();
            }

            $user_id_license_ids_map = isset( $all_modules_user_id_license_ids_map[ $module_id ] ) ?
                $all_modules_user_id_license_ids_map[ $module_id ] :
                array();

            if ( FS_User::is_valid_id( $user_id ) ) {
                $user_id_license_ids_map = isset( $user_id_license_ids_map[ $user_id ] ) ?
                    $user_id_license_ids_map[ $user_id ] :
                    array();
            }

            return $user_id_license_ids_map;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.0.0
         *
         * @param array       $new_user_id_license_ids_map
         * @param number      $module_id
         * @param number|null $user_id
         */
        private static function store_user_id_license_ids_map( $new_user_id_license_ids_map, $module_id, $user_id = null ) {
            $all_modules_user_id_license_ids_map = self::get_account_option( 'user_id_license_ids_map' );
            if ( ! is_array( $all_modules_user_id_license_ids_map ) ) {
                $all_modules_user_id_license_ids_map = array();
            }

            if ( ! isset( $all_modules_user_id_license_ids_map[ $module_id ] ) ) {
                $all_modules_user_id_license_ids_map[ $module_id ] = array();
            }

            if ( FS_User::is_valid_id( $user_id ) ) {
                $all_modules_user_id_license_ids_map[ $module_id ][ $user_id ] = $new_user_id_license_ids_map;
            } else {
                $all_modules_user_id_license_ids_map[ $module_id ] = $new_user_id_license_ids_map;
            }

            self::$_accounts->set_option( 'user_id_license_ids_map', $all_modules_user_id_license_ids_map, true );
        }

        /**
         * Get a collection of the user's linked license IDs.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param number $user_id
         *
         * @return number[]
         */
        private function get_user_linked_license_ids( $user_id ) {
            return self::get_user_id_license_ids_map( $this->_module_id, $user_id );
        }

        /**
         * Override the user's linked license IDs with a new IDs collection.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param number   $user_id
         * @param number[] $license_ids
         */
        private function set_user_linked_license_ids( $user_id, array $license_ids ) {
            self::store_user_id_license_ids_map( $license_ids, $this->_module_id, $user_id );
        }

        /**
         * Link a specified license ID to a given user.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param number $license_id
         * @param number $user_id
         */
        private function link_license_2_user( $license_id, $user_id ) {
            $license_ids = $this->get_user_linked_license_ids( $user_id );

            if ( in_array( $license_id, $license_ids ) ) {
                // License already linked.
                return;
            }

            $license_ids[] = $license_id;

            $this->set_user_linked_license_ids( $user_id, $license_ids );
        }

        /**
         * @param string|bool $module_type
         *
         * @return FS_Plugin_Plan[]
         */
        private static function get_all_plans( $module_type = false ) {
            $plans = self::get_account_option( 'plans', $module_type );

            if ( ! is_array( $plans ) ) {
                $plans = array();
            }

            return $plans;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @return FS_Plugin_Tag[]
         */
        private static function get_all_updates() {
            $updates = self::maybe_get_entities_account_option( 'updates', array() );

            if ( ! is_array( $updates ) ) {
                $updates = array();
            }

            return $updates;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @return array<number,FS_Plugin[]>|false
         */
        public static function get_all_addons() {
            $addons = self::maybe_get_entities_account_option( 'addons', array() );

            if ( ! is_array( $addons ) ) {
                $addons = array();
            }

            return $addons;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @return number[]|false
         */
        public static function get_all_account_addons() {
            $addons = self::$_accounts->get_option( 'account_addons', array() );

            if ( ! is_array( $addons ) ) {
                $addons = array();
            }

            return $addons;
        }

        /**
         * Check if user has connected his account (opted-in).
         *
         * Note:
         *      If the user opted-in and opted-out on a later stage,
         *      this will still return true. If you want to check if the
         *      user is currently opted-in, use:
         *          `$fs->is_registered() && $fs->is_tracking_allowed()`
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         *
         * @param bool $ignore_anonymous_state Since 2.5.1
         *
         * @return bool
         */
        function is_registered( $ignore_anonymous_state = false ) {
            return (
                is_object( $this->_user ) &&
                (
                    $this->is_premium() ||
                    $ignore_anonymous_state ||
                    ! $this->is_anonymous()
                )
            );
        }

        /**
         * Returns TRUE if the user opted-in and didn't disconnect (opt-out).
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.2.1.5
         *
         * @return bool
         */
        function is_tracking_allowed( $blog_id = null, $install = null ) {
            if ( is_null( $install ) ) {
                $install = is_null( $blog_id ) ?
                    $this->_site :
                    $this->get_install_by_blog_id( $blog_id );
            }

            return (
                is_object( $install ) &&
                FS_Permission_Manager::instance( $this )->is_homepage_url_tracking_allowed( $blog_id )
            );
        }

        /**
         * Returns TRUE if the user never opted-in or manually opted-out.
         *
         * @author Vova Feldman (@svovaf)
         * @since 1.2.1.5
         *
         * @param int|null $blog_id
         *
         * @return bool
         */
        function is_tracking_prohibited( $blog_id = null ) {
            return (
                ! $this->is_registered( true ) ||
                ! $this->is_tracking_allowed( $blog_id )
            );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.4.0
         *
         * @return bool
         */
        function is_bundle_license_auto_activation_enabled() {
            return $this->is_addon() ?
                ( is_object( $this->_parent ) && $this->_parent->is_bundle_license_auto_activation_enabled() ) :
                $this->_is_bundle_license_auto_activation_enabled;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @return FS_Plugin
         */
        function get_plugin() {
            return $this->_plugin;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         *
         * @return FS_User
         */
        function get_user() {
            return $this->_user;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         *
         * @return FS_Site
         */
        function get_site() {
            return $this->_site;
        }

        /**
         * @author Daniele Alessandra (@danielealessandra)
         * @return FS_Storage
         * @since  2.6.2
         *
         */
        public function get_storage() {
            return $this->_storage;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         */
        function store_site( $site ) {
            $this->_site = $site;
            $this->_store_site( true );
        }

        /**
         * Deletes the current install with an option to back it up in case restoration will be needed (e.g., if the automatic clone resolution attempt fails).
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         */
        function delete_current_install( $back_up ) {
            // Back up and delete the unique ID.
            if ( $back_up ) {
                self::$_accounts->set_option( 'prev_unique_id', $this->get_anonymous_id() );
            }

            self::$_accounts->set_option( 'unique_id', null );

            if ( $back_up ) {
                // Back up the install before deleting it so that it can be restored later on if necessary (e.g., if the automatic clone resolution attempt fails).
                $this->back_up_site();
            }

            $this->_delete_site();
            $this->_site = null;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         */
        function restore_backup_site() {
            self::$_accounts->set_option(
                'unique_id',
                self::$_accounts->get_option( 'prev_unique_id' )
            );

            $sites = self::get_all_sites( $this->_module_type, null, true );
            $this->store_site( clone $sites[ $this->_slug ] );
        }

        /**
         * Get plugin add-ons.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @since  1.1.7.3 If not yet loaded, fetch data from the API.
         *
         * @param bool $flush
         *
         * @return FS_Plugin[]|false
         */
        function get_addons( $flush = false ) {
            $this->_logger->entrance();

            if ( ! $this->_has_addons ) {
                return false;
            }

            $addons = $this->sync_addons( $flush );

            return ( ! is_array( $addons ) || empty( $addons ) ) ?
                false :
                $addons;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @return number[]|false
         */
        function get_account_addons() {
            $this->_logger->entrance();

            $addons = self::get_all_account_addons();

            if ( ! is_array( $addons ) ||
                 ! isset( $addons[ $this->_plugin->id ] ) ||
                 ! is_array( $addons[ $this->_plugin->id ] ) ||
                 0 === count( $addons[ $this->_plugin->id ] )
            ) {
                return false;
            }

            return $addons[ $this->_plugin->id ];
        }

        /**
         * Check if user has any
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.6
         *
         * @return bool
         */
        function has_account_addons() {
            $addons = $this->get_account_addons();

            return is_array( $addons ) && ( 0 < count( $addons ) );
        }


        /**
         * Get add-on by ID (from local data).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param number $id
         *
         * @return FS_Plugin|false
         */
        function get_addon( $id ) {
            $this->_logger->entrance();

            $addons = $this->get_addons();

            if ( is_array( $addons ) ) {
                foreach ( $addons as $addon ) {
                    if ( $id == $addon->id ) {
                        return $addon;
                    }
                }
            }

            return false;
        }

        /**
         * Get add-on by slug (from local data).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param string $slug
         *
         * @param bool   $flush
         *
         * @return FS_Plugin|false
         */
        function get_addon_by_slug( $slug, $flush = false ) {
            $this->_logger->entrance();

            $addons = $this->get_addons( $flush );

            if ( is_array( $addons ) ) {
                foreach ( $addons as $addon ) {
                    if ( $slug === $addon->slug ) {
                        return $addon;
                    }
                }
            }

            return false;
        }

        /**
         * @var array<number,object[]> {
         *      @key number   Add-on ID.
         *      @val object[] The add-on's plans and prices object.
         * }
         */
        private $plans_and_pricing_by_addon_id;

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @return array<number,object[]> {
         *      @key number   Add-on ID.
         *      @val object[] The add-on's plans and prices object.
         * }
         */
        function _get_addons_plans_and_pricing_map_by_id() {
            if ( ! isset( $this->plans_and_pricing_by_addon_id ) ) {
                $result = $this->get_api_plugin_scope()->get( $this->add_show_pending( "/addons/pricing.json?type=visible" ) );

                $plans_and_pricing_by_addon_id = array();
                if ( $this->is_api_result_object( $result, 'addons' ) ) {
                    foreach ( $result->addons as $addon ) {
                        $plans_and_pricing_by_addon_id[ $addon->id ] = $addon->plans;
                    }
                }

                $this->plans_and_pricing_by_addon_id = $plans_and_pricing_by_addon_id;
            }

            return $this->plans_and_pricing_by_addon_id;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @param number $addon_id
         * @param bool   $is_installed
         *
         * @return array
         */
        function _get_addon_info( $addon_id, $is_installed ) {
            $addon = $this->get_addon( $addon_id );

            if ( ! is_object( $addon ) ) {
                // Unexpected call.
                return array();
            }

            $slug = $addon->slug;

            $addon_storage = FS_Storage::instance( WP_FS__MODULE_TYPE_PLUGIN, $slug );

            if ( ! fs_is_network_admin() ) {
                // Get blog-level activated installations.
                $sites = self::maybe_get_entities_account_option( 'sites', array() );
            } else {
                $sites = null;

                if ( $this->is_addon_activated( $addon_id ) &&
                     $this->get_addon_instance( $addon_id )->is_network_active()
                ) {
                    if ( FS_Site::is_valid_id( $addon_storage->network_install_blog_id ) ) {
                        // Get network-level activated installations.
                        $sites = self::maybe_get_entities_account_option(
                            'sites',
                            array(),
                            $addon_storage->network_install_blog_id
                        );
                    }
                }
            }

            $addon_info = array(
                'is_connected'    => false,
                'slug'            => $slug,
                'title'           => $addon->title,
                'is_whitelabeled' => $addon_storage->is_whitelabeled
            );

            if ( ! $is_installed ) {
                $plans_and_pricing_by_addon_id = $this->_get_addons_plans_and_pricing_map_by_id();

                if ( isset( $plans_and_pricing_by_addon_id[ $addon_id ] ) ) {
                    $has_paid_plan = false;
                    $plans         = $plans_and_pricing_by_addon_id[ $addon_id ];

                    if ( is_array( $plans ) && count( $plans ) > 0 ) {
                        foreach ( $plans as $plan ) {
                            if ( isset( $plan->pricing ) &&
                                 is_array( $plan->pricing ) &&
                                 count( $plan->pricing ) > 0
                            ) {
                                $has_paid_plan = true;
                                break;
                            }
                        }
                    }

                    $addon_info['has_paid_plan'] = $has_paid_plan;
                }
            }

            if ( ! is_array( $sites ) || ! isset( $sites[ $slug ] ) ) {
                return $addon_info;
            }

            $site = $sites[ $slug ];

            $addon_info['is_connected'] = (
                ( $addon->parent_plugin_id == $this->get_id() ) &&
                is_object( $site ) &&
                FS_Site::is_valid_id( $site->id ) &&
                FS_User::is_valid_id( $site->user_id ) &&
                FS_Plugin_Plan::is_valid_id( $site->plan_id )
            );

            if ( $addon_info['is_connected'] && $is_installed ) {
                return $addon_info;
            }

            $addon_info['site'] = $site;

            $plugins_data = self::maybe_get_entities_account_option( WP_FS__MODULE_TYPE_PLUGIN . 's', array() );
            if ( isset( $plugins_data[ $slug ] ) ) {
                $plugin_data = $plugins_data[ $slug ];

                $addon_info['version'] = $plugin_data->version;
            }

            $all_plans = self::maybe_get_entities_account_option( 'plans', array() );
            if ( isset( $all_plans[ $slug ] ) ) {
                $plans = $all_plans[ $slug ];

                foreach ( $plans as $plan ) {
                    if ( $site->plan_id == Freemius::_decrypt( $plan->id ) ) {
                        $addon_info['plan_name']  = Freemius::_decrypt( $plan->name );
                        $addon_info['plan_title'] = Freemius::_decrypt( $plan->title );
                        break;
                    }
                }
            }

            $licenses = self::maybe_get_entities_account_option( 'all_licenses', array() );
            if ( is_array( $licenses ) && isset( $licenses[ $addon_id ] ) ) {
                foreach ( $licenses[ $addon_id ] as $license ) {
                    if ( $license->id == $site->license_id ) {
                        $addon_info['license'] = $license;
                        break;
                    }
                }
            }

            if ( isset( $addon_info['license'] ) ) {
                if ( isset( $addon_storage->subscriptions ) &&
                     ! empty( $addon_storage->subscriptions )
                ) {
                    $addon_subscriptions = fs_get_entities( $addon_storage->subscriptions, FS_Subscription::get_class_name() );

                    foreach ( $addon_subscriptions as $subscription ) {
                        if ( $subscription->license_id == $site->license_id ) {
                            $addon_info['subscription'] = $subscription;
                            break;
                        }
                    }
                }
            }

            return $addon_info;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param number $user_id
         *
         * @return FS_User
         */
        static function _get_user_by_id( $user_id ) {
            self::$_static_logger->entrance( "user_id = {$user_id}" );

            $users = self::get_all_users();

            if ( is_array( $users ) ) {
                if ( isset( $users[ $user_id ] ) &&
                     $users[ $user_id ] instanceof FS_User &&
                     $user_id == $users[ $user_id ]->id
                ) {
                    return $users[ $user_id ];
                }

                // If user wasn't found by the key, iterate over all the users collection.
                foreach ( $users as $user ) {
                    /**
                     * @var FS_User $user
                     */
                    if ( $user_id == $user->id ) {
                        return $user;
                    }
                }
            }

            return null;
        }

        /**
         * Checks if a Freemius user_id is associated with a super-admin.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param number $user_id
         *
         * @return bool
         */
        private static function is_super_admin( $user_id ) {
            $is_super_admin = false;

            $user = self::_get_user_by_id( $user_id );

            if ( $user instanceof FS_User && ! empty( $user->email ) ) {
                self::require_pluggable_essentials();

                $wp_user = get_user_by( 'email', $user->email );

                if ( $wp_user instanceof WP_User ) {
                    $super_admins   = get_super_admins();
                    $is_super_admin = ( is_array( $super_admins ) && in_array( $wp_user->user_login, $super_admins ) );
                }
            }

            return $is_super_admin;
        }

        #----------------------------------------------------------------------------------
        #region Plans & Licensing
        #----------------------------------------------------------------------------------

        /**
         * Check if running premium plugin code.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         *
         * @return bool
         */
        function is_premium() {
            /**
             * `$this->_plugin` will be `false` when `is_activation_mode` calls this method directly from the
             * `register_constructor_hooks` method.
             *
             * @author Leo Fajardo (@leorw)
             * @since 2.2.3
             */
            return is_object( $this->_plugin ) ?
                $this->_plugin->is_premium :
                false;
        }

        /**
         * Get site's plan ID.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.2
         *
         * @return number
         */
        function get_plan_id() {
            return $this->_site->plan_id;
        }

        /**
         * Get site's plan title.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.2
         *
         * @return string
         */
        function get_plan_title() {
            $plan = $this->get_plan();

            return is_object( $plan ) ? $plan->title : 'PLAN_TITLE';
        }

        /**
         * Get site's plan name.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return string
         */
        function get_plan_name() {
            $plan = $this->get_plan();

            return is_object( $plan ) ? $plan->name : 'PLAN_NAME';
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @return FS_Plugin_Plan|false
         */
        function get_plan() {
            if ( ! is_object( $this->_site ) ) {
                return false;
            }

            return FS_Plugin_Plan::is_valid_id( $this->_site->plan_id ) ?
                $this->_get_plan_by_id( $this->_site->plan_id ) :
                false;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         *
         * @return bool
         */
        function is_trial() {
            $this->_logger->entrance();

            if ( ! $this->is_registered( true ) || ! is_object( $this->_site ) ) {
                return false;
            }

            return $this->_site->is_trial();
        }

        /**
         * Check if currently in a trial with payment method (credit card or paypal).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7
         *
         * @return bool
         */
        function is_paid_trial() {
            $this->_logger->entrance();

            if ( ! $this->is_trial() ) {
                return false;
            }

            if ( ! $this->has_active_valid_license() ) {
                return false;
            }

            if ( $this->_site->trial_plan_id != $this->_license->plan_id ) {
                return false;
            }

            /**
             * @var FS_Subscription $subscription
             */
            $subscription = $this->_get_subscription( $this->_license->id );

            return ( is_object( $subscription ) && $subscription->is_active() );
        }

        /**
         * Check if trial already utilized.
         *
         * @since 1.0.9
         *
         * @return bool
         */
        function is_trial_utilized() {
            $this->_logger->entrance();

            if ( ! $this->is_registered() ) {
                return false;
            }

            return $this->_site->is_trial_utilized();
        }

        /**
         * Get trial plan information (if in trial).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @return bool|FS_Plugin_Plan
         */
        function get_trial_plan() {
            $this->_logger->entrance();

            if ( ! $this->is_trial() ) {
                return false;
            }

            // Try to load plan from local cache.
            $trial_plan = $this->_get_plan_by_id( $this->_site->trial_plan_id );

            if ( ! is_object( $trial_plan ) ) {
                $trial_plan = $this->_fetch_site_plan( $this->_site->trial_plan_id );

                /**
                 * If managed to fetch the plan, add it to the plans collection.
                 */
                if ( $trial_plan instanceof FS_Plugin_Plan ) {
                    if ( ! is_array( $this->_plans ) ) {
                        $this->_plans = array();
                    }

                    $this->_plans[] = $trial_plan;
                    $this->_store_plans();
                }
            }

            if ( $trial_plan instanceof FS_Plugin_Plan ) {
                return $trial_plan;
            }

            /**
             * If for some reason failed to get the trial plan, fallback to a dummy name and title.
             */
            $trial_plan        = new FS_Plugin_Plan();
            $trial_plan->id    = $this->_site->trial_plan_id;
            $trial_plan->name  = 'pro';
            $trial_plan->title = 'Pro';

            return $trial_plan;
        }

        /**
         * Check if the user has an activate, non-expired license on current plugin's install.
         *
         * @since 1.0.9
         *
         * @return bool
         */
        function is_paying() {
            $this->_logger->entrance();

            if ( ! $this->is_registered( true ) ) {
                return false;
            }

            if ( ! $this->has_paid_plan() ) {
                return false;
            }

            return (
                ! $this->is_trial() &&
                'free' !== $this->get_plan_name() &&
                $this->has_active_valid_license()
            );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @return bool
         */
        function is_free_plan() {
            if ( ! $this->is_registered() ) {
                return true;
            }

            if ( ! $this->has_paid_plan() ) {
                return true;
            }

            return (
                'free' === $this->get_plan_name() ||
                ! $this->has_features_enabled_license()
            );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         *
         * @return bool
         */
        function _has_premium_license() {
            $this->_logger->entrance();

            $premium_license = $this->_get_available_premium_license();

            return ( false !== $premium_license );
        }

        /**
         * Check if user has any licenses associated with the plugin (including expired or blocking).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.3
         *
         * @param bool $including_foreign
         *
         * @return bool
         */
        function has_any_license( $including_foreign = true ) {
            if ( ! is_array( $this->_licenses ) || 0 === count( $this->_licenses ) ) {
                return false;
            }

            if ( $including_foreign ) {
                return true;
            }

            foreach ( $this->_licenses as $license ) {
                if ( $this->_user->id == $license->user_id ) {
                    return true;
                }
            }

            return false;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         *
         * @param bool|null $is_localhost
         *
         * @return FS_Plugin_License|false
         */
        function _get_available_premium_license( $is_localhost = null ) {
            $this->_logger->entrance();

            $licenses = $this->get_available_premium_licenses( $is_localhost );
            if ( ! empty( $licenses ) ) {
                return $licenses[0];
            }

            return false;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         *
         * @param bool|null $is_localhost
         *
         * @return FS_Plugin_License[]
         */
        function get_available_premium_licenses( $is_localhost = null ) {
            $this->_logger->entrance();

            $licenses = array();
            if ( ! $this->has_paid_plan() ) {
                return $licenses;
            }

            if ( is_array( $this->_licenses ) ) {
                foreach ( $this->_licenses as $license ) {
                    if ( ! $license->can_activate( $is_localhost ) ) {
                        continue;
                    }

                    $licenses[] = $license;
                }
            }

            return $licenses;
        }

        /**
         * Sync local plugin plans with remote server.
         *
         * IMPORTANT: If for some reason a site is associated with deleted plan, we'll preserve the plan's information and append it as the last plan. This means that if plan is deleted, the is_plan() method will ALWAYS return true for any given argument (it becomes the most inclusive plan).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         *
         * @return FS_Plugin_Plan[]|object
         */
        function _sync_plans() {
            $plans = $this->_fetch_plugin_plans();

            if ( $this->is_array_instanceof( $plans, 'FS_Plugin_Plan' ) ) {
                $plans_map = array();
                foreach ( $plans as $plan ) {
                    $plans_map[ $plan->id ] = true;
                }

                $plans_ids_to_keep = $this->get_plans_ids_associated_with_installs();

                foreach ( $plans_ids_to_keep as $plan_id ) {
                    if ( isset( $plans_map[ $plan_id ] ) ) {
                        continue;
                    }

                    $missing_plan = self::_get_plan_by_id( $plan_id );

                    if ( is_object( $missing_plan ) ) {
                        $plans[] = $missing_plan;
                    }
                }

                $this->_plans = $plans;
                $this->_store_plans();
            }

            $this->do_action( 'after_plans_sync', $plans );

            return $this->_plans;
        }

        /**
         * Check if specified plan exists locally. If not, fetch it and store it.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param number $plan_id
         *
         * @return \FS_Plugin_Plan|object The plan entity or the API error object on failure.
         */
        private function sync_plan_if_not_exist( $plan_id ) {
            $plan = self::_get_plan_by_id( $plan_id );

            if ( is_object( $plan ) ) {
                // Plan already exists.
                return $plan;
            }

            $plan = $this->fetch_plan_by_id( $plan_id );

            if ( $plan instanceof FS_Plugin_Plan ) {
                $this->_plans[] = $plan;
                $this->_store_plans();

                return $plan;
            }

            return $plan;
        }

        /**
         * Check if specified license exists locally. If not, fetch it and store it.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param number $license_id
         * @param string $license_key
         *
         * @return \FS_Plugin_Plan|object The plan entity or the API error object on failure.
         */
        private function sync_license_if_not_exist( $license_id, $license_key ) {
            $license = $this->_get_license_by_id( $license_id );

            if ( is_object( $license ) ) {
                // License already exists.
                return $license;
            }

            $license = $this->fetch_license_by_key( $license_id, $license_key );

            if ( $license instanceof FS_Plugin_License ) {
                $this->_licenses[] = $license;

                $this->set_license( $license );

                $this->_store_licenses();

                return $license;
            }

            return $license;
        }

        /**
         * Get a collection of unique plan IDs that are associated with any installs in the network.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.0.0
         *
         * @return number[]
         */
        private function get_plans_ids_associated_with_installs() {
            if ( ! is_multisite() ) {
                if ( ! is_object( $this->_site ) ||
                     ! FS_Plugin_Plan::is_valid_id( $this->_site->plan_id )
                ) {
                    return array();
                }

                return array( $this->_site->plan_id );
            }

            $plan_ids = array();
            $sites    = self::get_sites();
            foreach ( $sites as $site ) {
                $blog_id = self::get_site_blog_id( $site );
                $install = $this->get_install_by_blog_id( $blog_id );

                if ( ! is_object( $install ) ||
                     ! FS_Plugin_Plan::is_valid_id( $install->plan_id )
                ) {
                    continue;
                }

                $plan_ids[ $install->plan_id ] = true;
            }

            return array_keys( $plan_ids );
        }

        /**
         * Get a collection of unique license IDs that are associated with any installs in the network.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.0.0
         *
         * @return number[]
         */
        private function get_license_ids_associated_with_installs() {
            if ( ! $this->_is_network_active ) {
                if ( ! is_object( $this->_site ) ||
                     ! FS_Plugin_License::is_valid_id( $this->_site->license_id )
                ) {
                    return array();
                }

                return array( $this->_site->license_id );
            }

            $license_ids = array();
            $sites       = self::get_sites();
            foreach ( $sites as $site ) {
                $blog_id = self::get_site_blog_id( $site );
                $install = $this->get_install_by_blog_id( $blog_id );

                if ( ! is_object( $install ) ||
                     ! FS_Plugin_License::is_valid_id( $install->license_id )
                ) {
                    continue;
                }

                $license_ids[ $install->license_id ] = true;
            }

            return array_keys( $license_ids );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         *
         * @param number $id
         *
         * @return FS_Plugin_Plan|false
         */
        function _get_plan_by_id( $id ) {
            $this->_logger->entrance();

            if ( ! is_array( $this->_plans ) || 0 === count( $this->_plans ) ) {
                $this->_sync_plans();
            }

            foreach ( $this->_plans as $plan ) {
                if ( $id == $plan->id ) {
                    return $plan;
                }
            }

            return false;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.8.1
         *
         * @param string $name
         *
         * @return FS_Plugin_Plan|false
         */
        private function get_plan_by_name( $name ) {
            $this->_logger->entrance();

            if ( ! is_array( $this->_plans ) || 0 === count( $this->_plans ) ) {
                $this->_sync_plans();
            }

            foreach ( $this->_plans as $plan ) {
                if ( $name == $plan->name ) {
                    return $plan;
                }
            }

            return false;
        }

        /**
         * Sync local licenses with remote server.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param number|bool $site_license_id
         * @param number|null $blog_id
         *
         * @return FS_Plugin_License[]|object
         */
        function _sync_licenses( $site_license_id = false, $blog_id = null ) {
            $this->_logger->entrance();

            $is_network_admin = fs_is_network_admin();

            if ( $is_network_admin && is_null( $blog_id ) ) {
                $all_licenses = self::get_all_licenses( $this->_module_id );
            } else {
                $all_licenses = $this->get_user_licenses( $this->_user->id );
            }

            $foreign_licenses = $this->get_foreign_licenses_info( $all_licenses, $site_license_id );

            $all_licenses_map = array();
            foreach ( $all_licenses as $license ) {
                $all_licenses_map[ $license->id ] = true;
            }

            $licenses = $this->_fetch_licenses( false, $site_license_id, $foreign_licenses, $blog_id );

            if ( $this->is_array_instanceof( $licenses, 'FS_Plugin_License' ) ) {
                $licenses_map = array();
                foreach ( $licenses as $license ) {
                    $licenses_map[ $license->id ] = true;
                }

//                $license_ids_to_keep = $this->get_license_ids_associated_with_installs();
//                foreach ( $license_ids_to_keep as $license_id ) {
//                    if ( isset( $licenses_map[ $license_id ] ) ) {
//                        continue;
//                    }
//
//                    $missing_license = self::_get_license_by_id( $license_id, false );
//                    if ( is_object( $missing_license ) ) {
//                        $licenses[]                           = $missing_license;
//                        $licenses_map[ $missing_license->id ] = true;
//                    }
//                }

                $user_license_ids = $this->get_user_linked_license_ids( $this->_user->id );

                foreach ( $user_license_ids as $key => $license_id ) {
                    if ( ! isset( $licenses_map[ $license_id ] ) ) {
                        // Remove access to licenses that no longer exist.
                        unset( $user_license_ids[ $key ] );
                    }
                }

                if ( ! empty( $user_license_ids ) ) {
                    foreach ( $licenses_map as $license_id => $value ) {
                        if ( ! isset( $all_licenses_map[ $license_id ] ) ) {
                            // Associate new licenses with the user who triggered the license syncing.
                            $user_license_ids[] = $license_id;
                        }
                    }

                    $user_license_ids = array_unique( $user_license_ids );
                } else {
                    $user_license_ids = array_keys( $licenses_map );
                }

                if ( ! $is_network_admin || ! is_null( $blog_id ) ) {
                    $user_licenses = array();
                    foreach ( $licenses as $license ) {
                        if ( ! in_array( $license->id, $user_license_ids ) ) {
                            continue;
                        }

                        $user_licenses[] = $license;
                    }

                    $this->_licenses = $user_licenses;
                } else {
                    $this->_licenses = $licenses;
                }

                $this->set_user_linked_license_ids( $this->_user->id, $user_license_ids );

                $this->_store_licenses( true, $this->_module_id, $licenses );
            }

            // Update current license.
            if ( is_object( $this->_license ) ) {
                $license = $this->_get_license_by_id( $this->_license->id );

                if ( is_object( $license ) ) {
                    /**
                     * `$license` can be `false` in case a user change action has just been completed and this method
                     * has synced the `$this->_licenses` collection for the new user. In this case, the
                     * `$this->_licenses` collection may have only the newly activated license that is associated with
                     * the new user. `set_license` will eventually be called in the same request by the logic that
                     * follows outside this method which will detect that the install's license has been updated, and
                     * then `_update_site_license` will be called which in turn will call `set_license`.
                     *
                     * @author Leo Fajardo (@leorw)
                     * @since 2.3.2
                     */
                    $this->set_license( $license );
                }
            }

            return $this->_licenses;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         *
         * @param number $id
         * @param bool   $sync_licenses
         *
         * @return FS_Plugin_License|false
         */
        function _get_license_by_id( $id, $sync_licenses = true ) {
            $this->_logger->entrance();

            if ( ! FS_Plugin_License::is_valid_id( $id ) ) {
                return false;
            }

            /**
             * When running from the network level admin and opted-in from the network,
             * check if the license exists in the network user licenses collection.
             *
             * @author Vova Feldman (@svovaf)
             * @since  2.0.0
             */
            if ( fs_is_network_admin() &&
                 $this->is_network_registered() &&
                 ( ! is_object( $this->_user ) || $this->_storage->network_user_id != $this->_user->id )
            ) {
                $licenses = $this->get_user_licenses( $this->_storage->network_user_id );

                foreach ( $licenses as $license ) {
                    if ( $id == $license->id ) {
                        return $license;
                    }
                }
            }

            if ( ! $this->has_any_license() && $sync_licenses ) {
                $this->_sync_licenses( $id );
            }

            if ( is_array( $this->_licenses ) ) {
                foreach ( $this->_licenses as $license ) {
                    if ( $id == $license->id ) {
                        return $license;
                    }
                }
            }

            return false;
        }

        /**
         * Get license by ID. Unlike _get_license_by_id(), this method only checks the local storage and return any license, whether it's associated with the current context user/install or not.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param number $id
         *
         * @return FS_Plugin_License
         */
        private function get_license_by_id( $id ) {
            $licenses = self::get_all_licenses( $this->_module_id );

            if ( is_array( $licenses ) && ! empty( $licenses ) ) {
                foreach ( $licenses as $license ) {
                    if ( $id == $license->id ) {
                        return $license;
                    }
                }
            }

            return null;
        }

        /**
         * Synchronize the site's context license by fetching the license form the API and updating the local data with it.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return \FS_Plugin_License|mixed
         */
        private function sync_site_license() {
            $api = $this->get_api_user_scope();

            $result = $api->get( "/licenses/{$this->_license->id}.json?license_key=" . urlencode( $this->_license->secret_key ), true );

            if ( ! $this->is_api_result_entity( $result ) ) {
                return $result;
            }

            $license = $this->_update_site_license( new FS_Plugin_License( $result ) );
            $this->_store_licenses();

            return $license;
        }

        /**
         * Get all user's available licenses for the current module.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param number $user_id
         *
         * @return FS_Plugin_License[]
         */
        private function get_user_licenses( $user_id ) {
            $all_licenses = self::get_all_licenses( $this->_module_id );
            if ( empty( $all_licenses ) ) {
                return array();
            }

            $user_license_ids = $this->get_user_linked_license_ids( $user_id );
            if ( empty( $user_license_ids ) ) {
                return array();
            }

            $licenses = array();
            foreach ( $all_licenses as $license ) {
                if ( in_array( $license->id, $user_license_ids ) ) {
                    $licenses[] = $license;
                }
            }

            return $licenses;
        }

        /**
         * Checks if the context license is network activated except on the given blog ID.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int $except_blog_id
         *
         * @return bool
         */
        private function is_license_network_active( $except_blog_id = 0 ) {
            $this->_logger->entrance();

            if ( ! is_object( $this->_license ) ) {
                return false;
            }

            $sites = self::get_sites();

            if ( $this->_license->total_activations() < ( count( $sites ) - 1 ) ) {
                // There are more sites than the number of activations, so license cannot be network activated.
                return false;
            }

            foreach ( $sites as $site ) {
                $blog_id = self::get_site_blog_id( $site );

                if ( $except_blog_id == $blog_id ) {
                    // Skip excluded blog.
                    continue;
                }

                $install = $this->get_install_by_blog_id( $blog_id );

                if ( is_object( $install ) && $install->license_id != $this->_license->id ) {
                    return false;
                }
            }

            return true;
        }

        /**
         * Checks if license can be activated on all the network sites (opted-in or skipped) that are not yet associated with a license. If possible, try to make the activation, if not return false.
         *
         * Notice: On success, this method will also update the license activations counters (without updating the license in the storage).
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param \FS_User           $user
         * @param \FS_Plugin_License $license
         *
         * @return bool
         */
        private function try_activate_license_on_network( FS_User $user, FS_Plugin_License $license ) {
            $this->_logger->entrance();

            $result = $this->can_activate_license_on_network( $license );

            if ( false === $result ) {
                return false;
            }

            $installs_without_license = $result['installs'];
            if ( ! empty( $installs_without_license ) ) {
                $this->activate_license_on_many_installs( $user, $license->secret_key, $installs_without_license );
            }

            $disconnected_site_ids = $result['sites'];
            if ( ! empty( $disconnected_site_ids ) ) {
                $this->activate_license_on_many_sites( $user, $license->secret_key, $disconnected_site_ids );
            }

            $this->link_license_2_user( $license->id, $user->id );

            // Sync license after activations.
            $license->activated += $result['production_count'];
            $license->activated_local += $result['localhost_count'];

//            $this->_store_licenses()

            return true;
        }

        /**
         * Checks if the given license can be activated on the whole network.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param \FS_Plugin_License $license
         *
         * @return false|array {
         * @type array[int]FS_Site $installs Blog ID to install map.
         * @type int[]               $sites            Non-connected blog IDs.
         * @type int                 $production_count Production sites count.
         * @type int                 $localhost_count  Production sites count.
         * }
         */
        private function can_activate_license_on_network( FS_Plugin_License $license ) {
            $sites = self::get_sites();

            $production_count = 0;
            $localhost_count  = 0;

            $installs_without_license = array();
            $disconnected_site_ids    = array();

            foreach ( $sites as $site ) {
                $blog_id = self::get_site_blog_id( $site );
                $install = $this->get_install_by_blog_id( $blog_id );

                if ( is_object( $install ) ) {
                    if ( FS_Plugin_License::is_valid_id( $install->license_id ) ) {
                        // License already activated on the install.
                        continue;
                    }

                    $url = $install->url;

                    $installs_without_license[ $blog_id ] = $install;
                } else {
                    $url = is_object( $site ) ?
                        $site->siteurl :
                        self::get_unfiltered_site_url( $blog_id );

                    $disconnected_site_ids[] = $blog_id;
                }

                if ( FS_Site::is_localhost_by_address( $url ) ) {
                    $localhost_count ++;
                } else {
                    $production_count ++;
                }
            }

            if ( ! $license->can_activate_bulk( $production_count, $localhost_count ) ) {
                return false;
            }

            return array(
                'installs'         => $installs_without_license,
                'sites'            => $disconnected_site_ids,
                'production_count' => $production_count,
                'localhost_count'  => $localhost_count,
            );
        }

        /**
         * Activate a given license on a collection of installs.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param \FS_User $user
         * @param string   $license_key
         * @param array    $blog_2_install_map {
         * @key    int Blog ID.
         * @value  FS_Site Blog's associated install.
         *                                     }
         *
         * @return mixed|true
         */
        private function activate_license_on_many_installs(
            FS_User $user,
            $license_key,
            array $blog_2_install_map
        ) {
            $params = array(
                array( 'license_key' => $this->apply_filters( 'license_key', $license_key ) )
            );

            $install_2_blog_map = array();
            foreach ( $blog_2_install_map as $blog_id => $install ) {
                $params[] = array( 'id' => $install->id, 'url' => $install->url );

                $install_2_blog_map[ $install->id ] = $blog_id;
            }

            $result = $this->get_api_user_scope_by_user( $user )->call(
                "plugins/{$this->_plugin->id}/installs.json",
                'PUT',
                $params
            );

            if ( ! $this->is_api_result_object( $result, 'installs' ) ) {
                return $result;
            }

            foreach ( $result->installs as $r_install ) {
                $install                  = new FS_Site( $r_install );
                $install->is_disconnected = false;

                // Update install.
                $this->_store_site(
                    true,
                    $install_2_blog_map[ $r_install->id ],
                    $install
                );
            }

            return true;
        }

        /**
         * Activate a given license on a collection of blogs/sites that are not yet opted-in.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.3.1
         *
         * @param \FS_User $user
         * @param string   $license_key
         *
         * @return true|mixed True if successful, otherwise, the API result.
         */
        private function activate_license_on_site( FS_User $user, $license_key ) {
            return $this->activate_license_on_many_sites( $user, $license_key );
        }

        /**
         * Activate a given license on a collection of blogs/sites that are not yet opted-in.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param \FS_User $user
         * @param string   $license_key
         * @param int[]    $site_ids
         *
         * @return true|mixed True if successful, otherwise, the API result.
         */
        private function activate_license_on_many_sites(
            FS_User $user,
            $license_key,
            array $site_ids = array()
        ) {
            $sites = array();
            foreach ( $site_ids as $site_id ) {
                $sites[] = $this->get_site_info( array( 'blog_id' => $site_id ) );
            }

            // Install the plugin.
            $result = $this->create_installs_with_user(
                $user,
                $license_key,
                false,
                $sites,
                false,
                true
            );

            if ( ! $this->is_api_result_entity( $result ) &&
                 ! $this->is_api_result_object( $result, 'installs' )
            ) {
                return $result;
            }

            $installs = array();

            if ( $this->is_api_result_entity( $result ) ) {
                $install = new FS_Site( $result );

                $this->_user = $user;

                $this->_store_site( true, null, $install );

                $this->_site = $install;

                $this->reset_anonymous_mode();
            } else {
                foreach ( $result->installs as $install ) {
                    $installs[] = new FS_Site( $install );
                }

                // Map site addresses to their blog IDs.
                $address_to_blog_map = $this->get_address_to_blog_map();

                $first_blog_id = null;

                foreach ( $installs as $install ) {
                    $address = trailingslashit( fs_strip_url_protocol( $install->url ) );
                    $blog_id = $address_to_blog_map[ $address ];

                    $this->_store_site( true, $blog_id, $install );

                    $this->reset_anonymous_mode( $blog_id );

                    if ( is_null( $first_blog_id ) ) {
                        $first_blog_id = $blog_id;
                    }
                }

                if ( ! FS_Site::is_valid_id( $this->_storage->network_install_blog_id ) ) {
                    $this->_storage->network_install_blog_id = $first_blog_id;
                }
            }

            return true;
        }

        /**
         * Sync site's license with user licenses.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param FS_Plugin_License|null $new_license
         *
         * @return FS_Plugin_License|null
         */
        function _update_site_license( $new_license ) {
            $this->_logger->entrance();

            /**
             * In case this call will be removed in the future, the `_sync_licenses()` method needs to be updated
             * accordingly so that it will also handle the case when an ownership change is done via license
             * activation.
             *
             * @author Leo Fajardo (@leorw)
             * @since 2.3.2
             */
            $this->set_license( $new_license );

            if ( ! is_object( $new_license ) ) {
                $this->_site->license_id = null;
                $this->_sync_site_subscription( null );

                return $this->_license;
            }

            $this->_site->license_id = $this->_license->id;

            if ( ! is_array( $this->_licenses ) ) {
                $this->_licenses = array();
            }

            $is_license_found = false;
            for ( $i = 0, $len = count( $this->_licenses ); $i < $len; $i ++ ) {
                if ( $new_license->id == $this->_licenses[ $i ]->id ) {
                    $this->_licenses[ $i ] = $new_license;

                    $is_license_found = true;
                    break;
                }
            }

            // If new license just append.
            if ( ! $is_license_found ) {
                $this->_licenses[] = $new_license;
            }

            $this->_sync_site_subscription( $new_license );

            return $this->_license;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.3.1
         *
         * @param \FS_Plugin_License $license
         */
        private function set_license( FS_Plugin_License $license = null ) {
            $this->_license = $license;

            $this->maybe_update_whitelabel_flag( $license );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.1
         *
         * @param FS_Plugin_License $license
         */
        private function maybe_update_whitelabel_flag( $license ) {
            $is_whitelabeled = isset( $this->_storage->is_whitelabeled ) ?
                $this->_storage->is_whitelabeled :
                false;

            if ( is_object( $license ) ) {
                $license_user = self::_get_user_by_id( $license->user_id );

                if ( ! is_object( $license_user ) ) {
                    // If foreign license, do not update the `is_whitelabeled` flag.
                    return;
                }

                if ( $this->is_addon() ) {
                    /**
                     * Store the last license data to the parent's storage since it's needed only when showing the
                     * "Start Debug" dialog which is triggered from the "Account" page. This way, there's no need to
                     * iterate over the add-ons just to get the last license data.
                     */
                    $this->get_parent_instance()->store_last_activated_license_data( $license, $license_user );
                } else {
                    $this->store_last_activated_license_data( $license );
                }

                if ( $license->is_whitelabeled ) {
                    // Activated a developer license, data should be hidden.
                    $is_whitelabeled = true;
                } else if ( $this->is_registered() && $this->_user->id == $license->user_id ) {
                    // The account owner activated a regular license key, no need to hide the  data.
                    $is_whitelabeled = false;
                }
            }

            $this->_storage->is_whitelabeled = $is_whitelabeled;

            // Reset the whitelabeled status after update.
            $this->is_whitelabeled = null;
            if ( $this->is_addon() ) {
                $parent_fs = $this->get_parent_instance();

                if ( is_object( $parent_fs ) ) {
                    $parent_fs->is_whitelabeled = null;
                }
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.1
         *
         * @param FS_Plugin_License $license
         * @param FS_User           $license_user
         */
        private function store_last_activated_license_data( FS_Plugin_License $license, $license_user = null ) {
            if ( ! is_object( $license_user ) ) {
                $this->_storage->last_license_key     = md5( $license->secret_key );
                $this->_storage->last_license_user_id = null;
            } else {
                $this->_storage->last_license_user_key = md5( $license_user->secret_key );
                $this->_storage->last_license_user_id  = $license_user->id;
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.1
         *
         * @param bool $ignore_data_debug_mode
         *
         * @return bool
         */
        function is_whitelabeled_by_flag( $ignore_data_debug_mode = false ) {
            if ( true !== $this->_storage->is_whitelabeled ) {
                return false;
            } else if ( $ignore_data_debug_mode ) {
                return true;
            }

            $fs = $this->is_addon() ?
                $this->get_parent_instance() :
                $this;

            return ! $fs->is_data_debug_mode();
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.1
         *
         * @return number
         */
        function get_last_license_user_id() {
            return ( FS_User::is_valid_id( $this->_storage->last_license_user_id ) ) ?
                $this->_storage->last_license_user_id :
                null;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.1
         *
         * @param int  $blog_id
         * @param bool $ignore_data_debug_mode
         *
         * @return bool
         */
        function is_whitelabeled( $ignore_data_debug_mode = false, $blog_id = null ) {
            if ( ! is_null( $blog_id ) ) {
                $this->switch_to_blog( $blog_id );
            }

            if ( ! is_null( $this->is_whitelabeled ) ) {
                $is_whitelabeled = $this->is_whitelabeled;
            } else {
                $is_whitelabeled = false;

                $is_whitelabeled_flag = $this->is_whitelabeled_by_flag( true );

                if ( ! $this->has_addons() ) {
                    $is_whitelabeled = $is_whitelabeled_flag;
                } else if ( $is_whitelabeled_flag ) {
                    $is_whitelabeled = true;
                } else {
                    if ( $this->is_registered() || $this->is_premium() ) {
                        $addon_ids = $this->get_updated_account_addons();
                    } else {
                        $addons = self::get_all_addons();

                        $plugin_addons = isset( $addons[ $this->_plugin->id ] ) ?
                            $addons[ $this->_plugin->id ] :
                            array();

                        $addon_ids = array();
                        foreach ( $plugin_addons as $addon ) {
                            $addon_ids[] = $addon->id;
                        }
                    }

                    $installed_addons = $this->get_installed_addons();
                    foreach ( $installed_addons as $fs_addon ) {
                        $addon_ids[] = $fs_addon->get_id();
                    }

                    if ( ! empty( $addon_ids ) ) {
                        $addon_ids = array_unique( $addon_ids );

                        $is_network_level = (
                            fs_is_network_admin() &&
                            $this->is_network_active()
                        );

                        foreach ( $addon_ids as $addon_id ) {
                            $addon = $this->get_addon( $addon_id );

                            if ( ! is_object( $addon ) ) {
                                continue;
                            }

                            $addon_storage = FS_Storage::instance( WP_FS__MODULE_TYPE_PLUGIN, $addon->slug );
                            $fs_addon      = $this->is_addon_activated( $addon_id ) ?
                                self::get_addon_instance( $addon_id ) :
                                null;

                            $was_addon_network_activated = false;

                            if ( is_object( $fs_addon ) ) {
                                $was_addon_network_activated = $fs_addon->is_network_active();
                            } else if ( $is_network_level ) {
                                $was_addon_network_activated = $addon_storage->get( 'was_plugin_loaded', false, true );
                            }

                            $network_delegated_connection = (
                                $was_addon_network_activated &&
                                $addon_storage->get( 'is_delegated_connection', false, true )
                            );

                            if (
                                $is_network_level &&
                                ( ! $was_addon_network_activated || $network_delegated_connection )
                            ) {
                                $sites = self::get_sites();

                                /**
                                 * If in network admin area and the add-on was not network-activated or network-activated
                                 * and network-delegated, find any add-on whose is_whitelabeled flag is true.
                                 */
                                foreach ( $sites as $site ) {
                                    $site_info = $this->get_site_info( $site );

                                    if ( $addon_storage->get( 'is_whitelabeled', false, $site_info['blog_id'] ) ) {
                                        $is_whitelabeled = true;
                                        break;
                                    }
                                }

                                if ( $is_whitelabeled ) {
                                    break;
                                }
                            } else {
                                /**
                                 * This will be executed when any of the following is met:
                                 * 1. Add-on was network-activated, not network-delegated, and in network admin area.
                                 * 2. Add-on was network-activated, network-delegated, and in site admin area.
                                 * 3. Add-on was not network-activated and in site admin area.
                                 */
                                if ( true === $addon_storage->is_whitelabeled ) {
                                    $is_whitelabeled = true;
                                    break;
                                }
                            }
                        }
                    }
                }

                $this->is_whitelabeled = $is_whitelabeled;

                if ( ! $is_whitelabeled || ! $this->is_data_debug_mode() ) {
                    $this->_admin_notices->remove_sticky( 'data_debug_mode_enabled' );
                }

                if ( ! is_null( $blog_id ) ) {
                    $this->restore_current_blog();
                }
            }

            return (
                $is_whitelabeled &&
                ( $ignore_data_debug_mode || ! $this->is_data_debug_mode() )
            );
        }

        /**
         * Sync site's subscription.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @param FS_Plugin_License|null $license
         *
         * @return bool|\FS_Subscription
         */
        private function _sync_site_subscription( $license ) {
            if ( ! is_object( $license ) ) {
                $this->delete_unused_subscriptions();

                return false;
            }

            // Load subscription details if not lifetime.
            $subscription = $license->is_lifetime() ?
                false :
                $this->_fetch_site_license_subscription();

            if ( is_object( $subscription ) && ! isset( $subscription->error ) ) {
                $this->store_subscription( $subscription );
            } else {
                $this->delete_unused_subscriptions();
            }

            return $subscription;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @return bool|\FS_Plugin_License
         */
        function _get_license() {
            if ( ! fs_is_network_admin() || is_object( $this->_license ) ) {
                return $this->_license;
            }

            return $this->_get_available_premium_license();
        }

        /**
         * @param number $license_id
         *
         * @return null|\FS_Subscription
         */
        function _get_subscription( $license_id ) {
            if ( ! isset( $this->_storage->subscriptions ) ||
                 empty( $this->_storage->subscriptions )
            ) {
                return null;
            }

            foreach ( fs_get_entities( $this->_storage->subscriptions, FS_Subscription::get_class_name() ) as $subscription ) {
                if ( $subscription->license_id == $license_id ) {
                    return $subscription;
                }
            }

            return null;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.0.0
         *
         * @param FS_Subscription $subscription
         */
        function store_subscription( FS_Subscription $subscription ) {
            if ( ! isset( $this->_storage->subscriptions ) ) {
                $this->_storage->subscriptions = array();
            }

            if ( empty( $this->_storage->subscriptions ) || ! is_multisite() ) {
                $this->_storage->subscriptions = array( $subscription );

                return;
            }

            $subscriptions = fs_get_entities( $this->_storage->subscriptions, FS_Subscription::get_class_name() );

            $updated_subscription = false;
            foreach ( $subscriptions as $key => $existing_subscription ) {
                if ( $existing_subscription->id == $subscription->id ) {
                    $subscriptions[ $key ] = $subscription;
                    $updated_subscription  = true;
                    break;
                }
            }

            if ( ! $updated_subscription ) {
                $subscriptions[] = $subscription;
            }

            $this->_storage->subscriptions = $subscriptions;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.0.0
         */
        function delete_unused_subscriptions() {
            if ( ! isset( $this->_storage->subscriptions ) ||
                 empty( $this->_storage->subscriptions ) ||
                 // Clean up only if there are already at least 3 subscriptions.
                 ( count( $this->_storage->subscriptions ) < 3 )
            ) {
                return;
            }

            if ( ! is_multisite() ) {
                // If not multisite, there should only be 1 subscription, so just clear the array.
                $this->_storage->subscriptions = array();

                return;
            }

            $subscriptions_to_keep_by_license_id_map = array();
            $sites                                   = self::get_sites();
            foreach ( $sites as $site ) {
                $blog_id = self::get_site_blog_id( $site );
                $install = $this->get_install_by_blog_id( $blog_id );

                if ( ! is_object( $install ) ||
                     ! FS_Plugin_License::is_valid_id( $install->license_id )
                ) {
                    continue;
                }

                $subscriptions_to_keep_by_license_id_map[ $install->license_id ] = true;
            }

            if ( empty( $subscriptions_to_keep_by_license_id_map ) ) {
                $this->_storage->subscriptions = array();

                return;
            }

            foreach ( $this->_storage->subscriptions as $key => $subscription ) {
                if ( ! isset( $subscriptions_to_keep_by_license_id_map[ $subscription->license_id ] ) ) {
                    unset( $this->_storage->subscriptions[ $key ] );
                }
            }
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.2
         *
         * @param string $plan  Plan name
         * @param bool   $exact If true, looks for exact plan. If false, also check "higher" plans.
         *
         * @return bool
         */
        function is_plan( $plan, $exact = false ) {
            $this->_logger->entrance();

            if ( ! $this->is_registered() ) {
                return false;
            }

            $plan = strtolower( $plan );

            $current_plan_name = $this->get_plan_name();

            if ( $current_plan_name === $plan ) {
                // Exact plan.
                return true;
            } else if ( $exact ) {
                // Required exact, but plans are different.
                return false;
            }

            $current_plan_order  = - 1;
            $required_plan_order = PHP_INT_MAX;
            for ( $i = 0, $len = count( $this->_plans ); $i < $len; $i ++ ) {
                if ( $plan === $this->_plans[ $i ]->name ) {
                    $required_plan_order = $i;
                } else if ( $current_plan_name === $this->_plans[ $i ]->name ) {
                    $current_plan_order = $i;
                }
            }

            return ( $current_plan_order > $required_plan_order );
        }

        /**
         * Check if module has only one plan.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.7
         *
         * @param bool $double_check In some cases developers prefer to release their paid offering as premium-only, even though there is a free version. For those cases, looking at the 'is_premium_only' value isn't enough because the result will return false even when the product has only signle paid plan.
         *
         * @return bool
         */
        function is_single_plan( $double_check = false ) {
            $this->_logger->entrance();

            if ( ! $this->is_registered() ||
                 ! is_array( $this->_plans ) ||
                 0 === count( $this->_plans )
            ) {
                return true;
            }

            $has_free_plan = $this->has_free_plan();

            if ( ! $has_free_plan && $double_check ) {
                foreach ( $this->_plans as $plan ) {
                    if ( $plan->is_free() ) {
                        $has_free_plan = true;
                        break;
                    }
                }
            }

            return ( 1 === ( count( $this->_plans ) - ( $has_free_plan ? 1 : 0 ) ) );
        }

        /**
         * Check if plan based on trial. If not in trial mode, should return false.
         *
         * @since  1.0.9
         *
         * @param string $plan  Plan name
         * @param bool   $exact If true, looks for exact plan. If false, also check "higher" plans.
         *
         * @return bool
         */
        function is_trial_plan( $plan, $exact = false ) {
            $this->_logger->entrance();

            if ( ! $this->is_registered() ) {
                return false;
            }

            if ( ! $this->is_trial() ) {
                return false;
            }

            $trial_plan = $this->get_trial_plan();

            if ( $trial_plan->name === $plan ) {
                // Exact plan.
                return true;
            } else if ( $exact ) {
                // Required exact, but plans are different.
                return false;
            }

            $current_plan_order  = - 1;
            $required_plan_order = - 1;
            for ( $i = 0, $len = count( $this->_plans ); $i < $len; $i ++ ) {
                if ( $plan === $this->_plans[ $i ]->name ) {
                    $required_plan_order = $i;
                } else if ( $trial_plan->name === $this->_plans[ $i ]->name ) {
                    $current_plan_order = $i;
                }
            }

            return ( $current_plan_order > $required_plan_order );
        }

        /**
         * Check if plugin has any paid plans.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.7
         *
         * @return bool
         */
        function has_paid_plan() {
            return $this->_has_paid_plans ||
                   FS_Plan_Manager::instance()->has_paid_plan( $this->_plans );
        }

        /**
         * Check if plugin has any plan with a trail.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @return bool
         */
        function has_trial_plan() {
            /**
             * @author Vova Feldman(@svovaf)
             * @since  1.2.1.5
             *
             * Allow setting a trial from the SDK without calling the API.
             * But, if the user did opt-in, continue using the real data from the API.
             */
            if ( $this->_trial_days >= 0 ) {
                return true;
            }

            return $this->_storage->get( 'has_trial_plan', false );
        }

        /**
         * Check if plugin has any free plan, or is it premium only.
         *
         * Note: If no plans configured, assume plugin is free.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.7
         *
         * @return bool
         */
        function has_free_plan() {
            return ! $this->is_only_premium();
        }

        /**
         * Displays a license activation dialog box when the user clicks on the "Activate License"
         * or "Change License" link on the plugins
         * page.
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.1.9
         */
        function _add_license_activation_dialog_box() {
            $vars = array(
                'id' => $this->_module_id,
            );

            fs_require_template( 'forms/license-activation.php', $vars );
            fs_require_template( 'forms/resend-key.php', $vars );
        }

        /**
         * Displays an email address update dialog box when the user clicks on the email address "Edit" button on the "Account" page.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.5.0
         */
        function _add_email_address_update_dialog_box() {
            $vars = array( 'id' => $this->_module_id );

            fs_require_template( 'forms/email-address-update.php', $vars );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         */
        function _add_email_address_update_option() {
            if ( ! $this->should_handle_user_change() ) {
                return;
            }

            // Add email address update AJAX handler.
            $this->add_ajax_action( 'update_email_address', array( &$this, '_email_address_update_ajax_handler' ) );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         */
        function _email_address_update_ajax_handler() {
            $this->check_ajax_referer( 'update_email_address' );

            $new_email_address = fs_request_get( 'email_address' );
            $transfer_type     = fs_request_get( 'transfer_type' );

            $result = $this->update_email( $new_email_address );

            if ( ! FS_Api::is_api_error( $result ) ) {
                self::shoot_ajax_success();
            }

            $error = '';

            if ( FS_Api::is_api_error_object( $result ) ) {
                switch ( $result->error->code ) {
                    case 'user_exist':
                    case 'account_verification_required':
                        $error = array(
                            'code' => 'change_ownership',
                            'url'  => $this->get_account_url( 'change_owner', array(
                                'state'           => 'init',
                                'candidate_email' => $new_email_address,
                                'transfer_type'   => $transfer_type,
                            ) ),
                        );

                        break;
                }
            }

            if ( empty( $error ) ) {
                $error = is_object( $result ) ?
                    var_export( $result->error, true ) :
                    $result;
            }

            self::shoot_ajax_failure( $error );
        }

        /**
         * Returns a collection of IDs of installs that are associated with the context product and its add-ons, and activated with foreign licenses.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.3.2
         *
         * @return number[]
         */
        function get_installs_ids_with_foreign_licenses() {
            $installs = array();

            if (
                is_object( $this->_license ) &&
                $this->_site->user_id != $this->_license->user_id
            ) {
                $installs[] = $this->_site->id;
            }

            /**
             * Also try to get foreign licenses for the context product's add-ons.
             */
            $installs_by_slug_map = $this->get_parent_and_addons_installs_info();

            foreach ( $installs_by_slug_map as $slug => $install_info ) {
                if ( $slug == $this->get_slug() ) {
                    continue;
                }

                $install = $install_info['install'];
                $license = $install_info['license'];

                if (
                    is_object( $license ) &&
                    $install->user_id != $license->user_id
                ) {
                    $installs[] = $install->id;
                }
            }

            return $installs;
        }

        /**
         * Displays the "Change User" dialog box when the user clicks on the "Change User" button on the "Account" page.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.3.2
         *
         * @param number[] $install_ids
         */
        function _add_user_change_dialog_box( $install_ids ) {
            $vars = array(
                'id'             => $this->_module_id,
                'license_owners' => $this->fetch_installs_licenses_owners_data( $install_ids )
            );

            fs_require_template( 'forms/user-change.php', $vars );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.3.1
         */
        function _add_data_debug_mode_dialog_box() {
            $vars = array(
                'id' => $this->_module_id,
            );

            fs_require_template( 'forms/data-debug-mode.php', $vars );
        }

        /**
         * Displays a subscription cancellation dialog box when the user clicks on the "Deactivate License"
         * link on the "Account" page or deactivates a plugin and there's an active subscription that is
         * either associated with a non-lifetime single-site license or non-lifetime multisite license that
         * is only activated on a single production site.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.2.1
         *
         * @param bool $is_license_deactivation
         *
         * @return array
         */
        function _get_subscription_cancellation_dialog_box_template_params( $is_license_deactivation = false ) {
            if ( fs_is_network_admin() ) {
                // Subscription cancellation dialog box is currently not supported for multisite networks.
                return array();
            }
            
            if ( $this->is_whitelabeled() ) {
                return array();
            }

            $license = $this->_get_license();

            /**
             * If the installation is associated with a non-lifetime license, which is either a single-site or only activated on a single production site (or zero), and connected to an active subscription, suggest the customer to cancel the subscription upon deactivation.
             *
             * @author Leo Fajardo (@leorw) (Comment added by Vova Feldman @svovaf)
             * @since 2.2.1
             */
            if ( ! is_object( $license ) ||
                $license->is_lifetime() ||
                ( ! $license->is_single_site() && $license->activated > 1 )
            ) {
                return array();
            }

            /**
             * @var FS_Subscription $subscription
             */
            $subscription = $this->_get_subscription( $license->id );
            if ( ! is_object( $subscription ) || ! $subscription->is_active() ) {
                return array();
            }

            return array(
                'id'                      => $this->_module_id,
                'license'                 => $license,
                'has_trial'               => $this->is_paid_trial(),
                'is_license_deactivation' => $is_license_deactivation,
            );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.0.2
         */
        function _add_premium_version_upgrade_selection_dialog_box() {
            $modules_update = get_site_transient( $this->is_theme() ? 'update_themes' : 'update_plugins' );
            if ( ! isset( $modules_update->response[ $this->_plugin_basename ] ) ) {
                return;
            }

            $vars = array(
                'id'          => $this->_module_id,
                'new_version' => is_object( $modules_update->response[ $this->_plugin_basename ] ) ?
                    $modules_update->response[ $this->_plugin_basename ]->new_version :
                    $modules_update->response[ $this->_plugin_basename ]['new_version']
            );

            fs_require_template( 'forms/premium-versions-upgrade-metadata.php', $vars );
            fs_require_once_template( 'forms/premium-versions-upgrade-handler.php', $vars );
        }

        /**
         * Displays the opt-out dialog box when the user clicks on the "Opt Out" link on the "Plugins"
         * page.
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.2.1.5
         */
        function _add_optout_dialog() {
            if ( $this->is_theme() ) {
                $vars = null;
                fs_require_once_template( '/js/jquery.content-change.php', $vars );
            }

            $vars = array( 'id' => $this->_module_id );
            fs_require_template( 'forms/optout.php', $vars );
        }

        /**
         * Prepare page to include all required UI and logic for the license activation dialog.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.0
         */
        function _add_license_activation() {
            if ( $this->is_migration() ) {
                return;
            }

            if ( ! $this->is_user_admin() ) {
                // Only admins can activate a license.
                return;
            }

            if ( ! $this->has_paid_plan() ) {
                // Module doesn't have any paid plans.
                return;
            }

            if (
                $this->has_premium_version() &&
                ! $this->is_premium() &&
                /**
                 * Also handle the case when an upgrade was made using the free version.
                 * 
                 * @author Leo Fajardo (@leorw)
                 * @since 2.3.2
                 */
                ! is_object( $this->_get_license() )
            ) {
                // Only add license activation logic to the premium version, or in case of a serviceware plugin, also in the free version.
                return;
            }

            // Add license activation link and AJAX request handler.
            if ( self::is_plugins_page() ) {
                $is_network_admin = fs_is_network_admin();

                if (
                    ( $is_network_admin && $this->is_network_active() && ! $this->is_network_delegated_connection() ) ||
                    ( ! $is_network_admin && ( ! $this->is_network_active() || $this->is_delegated_connection() ) )
                ) {
                    if (
                        $this->is_premium() ||
                        ( $this->has_paid_plan() && ! $this->has_premium_version() )
                    ) {
                        /**
                         * @since 1.2.0 Add license action link only on plugins page.
                         */
                        $this->_add_license_action_link();
                    }
                }
            }

            // Add license activation AJAX callback.
            $this->add_ajax_action( 'activate_license', array( &$this, '_activate_license_ajax_action' ) );

            // Add resend license AJAX callback.
            $this->add_ajax_action( 'resend_license_key', array( &$this, '_resend_license_key_ajax_action' ) );
        }

        /**
         * Prepares page to include all required UI and logic for the "Change User" dialog.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.3.2
         */
        function _add_user_change_option() {
            if ( ! $this->should_handle_user_change() ) {
                return;
            }

            $installs_ids_with_foreign_licenses = $this->get_installs_ids_with_foreign_licenses();

            if ( empty( $installs_ids_with_foreign_licenses ) ) {
                // Handle user change only when the parent product or one of its add-ons is activated with a foreign license.
                return;
            }

            // Add user change AJAX handler.
            $this->add_ajax_action( 'change_user', array( &$this, '_user_change_ajax_action' ) );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.3.2
         */
        function should_handle_user_change() {
            if ( ! $this->is_user_admin() ) {
                // Only admins can change user.
                return false;
            }

            if ( $this->is_addon() ) {
                return false;
            }

            if ( ! $this->is_registered() ) {
                return false;
            }

            if (
                $this->is_network_active() &&
                ( fs_is_network_admin() || ! $this->is_site_delegated_connection() )
            ) {
                // Handle only on site-level "Account" section for now.
                return false;
            }

            return true;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.0.2
         */
        function _add_premium_version_upgrade_selection() {
            if ( ! $this->is_user_admin() ) {
                return;
            }

            if ( ! $this->is_premium() || $this->has_any_active_valid_license() ) {
                // This is relevant only to the free versions and premium versions without an active license.
                return;
            }

            if ( self::is_updates_page() || ( $this->is_plugin() && self::is_plugins_page() ) ) {
                $this->_add_premium_version_upgrade_selection_action();
            }
        }

        /**
         * @author Edgar Melkonyan
         * @since 2.4.1
         *
         * @throws Freemius_Exception
         */
        function _toggle_whitelabel_mode_ajax_handler() {
            $this->_logger->entrance();

            $this->check_ajax_referer( 'toggle_whitelabel_mode' );

            if ( ! $this->is_user_admin() ) {
                // Only for admins.
                self::shoot_ajax_failure();
            }

            $license = $this->get_api_user_scope()->call(
                "/licenses/{$this->_site->license_id}.json",
                'put',
                array( 'is_whitelabeled' => ! $this->_license->is_whitelabeled )
            );

            if ( ! $this->is_api_result_entity( $license ) ) {
                self::shoot_ajax_failure(
                FS_Api::is_api_error_object( $license ) ?
                    $license->error->message :
                    fs_text_inline( "An unknown error has occurred while trying to toggle the license's white-label mode.", 'unknown-error-occurred', $this->get_slug() )
                );
            }

            $this->_license->is_whitelabeled = $license->is_whitelabeled;
            $this->_store_licenses();

            $this->_sync_license();

            if ( ! $license->is_whitelabeled ) {
                $this->_admin_notices->remove_sticky( 'license_whitelabeled' );
            } else {
                $this->_admin_notices->add_sticky(
                    sprintf(
                        $this->get_text_inline(
                            'Your %s license was flagged as white-labeled to hide sensitive information from the WP Admin (e.g. your email, license key, prices, billing address & invoices). If you ever wish to revert it back, you can easily do it through your %s. If this was a mistake you can also %s.',
                            'license_whitelabeled'
                        ),
                        "<strong>{$this->get_plugin_title()}</strong>",
                        sprintf( '<a href="https://users.freemius.com" target="_blank">%s</a>', $this->get_text_inline( 'User Dashboard', 'user-dashboard' ) ),
                        sprintf( '<a href="#" class="fs-toggle-whitelabel-mode">%s</a>', $this->get_text_inline( 'revert it now', 'revert-it-now' ) )
                    ),
                    'license_whitelabeled'
                );
            }

            self::shoot_ajax_response( array( 'success' => true ) );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.3.0
         */
        function _add_beta_mode_update_handler() {
            if ( ! $this->is_user_admin() ) {
                return;
            }

            if ( ! $this->is_premium() ) {
                return;
            }

            $this->add_ajax_action( 'set_beta_mode', array( &$this, '_set_beta_mode_ajax_handler' ) );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.3.0
         */
        function _set_beta_mode_ajax_handler() {
            $this->_logger->entrance();

            $this->check_ajax_referer( 'set_beta_mode' );

            if ( ! $this->is_user_admin() ) {
                // Only for admins.
                self::shoot_ajax_failure();
            }

            $is_beta = trim( fs_request_get( 'is_beta', '', 'post' ) );

            if ( empty( $is_beta ) || ! in_array( $is_beta, array( 'true', 'false' ) ) ) {
                self::shoot_ajax_failure();
            }

            $site = $this->api_site_call(
                '',
                'put',
                array(
                    'is_beta'   => ( 'true' == $is_beta ),
                    'fields'    => 'is_beta'
                )
            );

            if ( ! $this->is_api_result_entity( $site ) ) {
                self::shoot_ajax_failure(
                    FS_Api::is_api_error_object( $site ) ?
                        $site->error->message :
                        fs_text_inline( "An unknown error has occurred while trying to set the user's beta mode.", 'unknown-error-occurred', $this->get_slug() )
                );
            }

            $this->_site->is_beta = $site->is_beta;
            $this->_store_site();

            self::shoot_ajax_response( array( 'success' => true ) );
        }

        /**
         * License activation WP AJAX handler.
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.1.9
         *
         * @uses Freemius::activate_license()
         */
        function _activate_license_ajax_action() {
            $this->_logger->entrance();
            
            $this->check_ajax_referer( 'activate_license' );

            $license_key = trim( fs_request_get_raw( 'license_key' ) );

            if ( empty( $license_key ) ) {
                $license_id = trim( fs_request_get_raw( 'license_id' ) );

                if ( FS_Plugin_License::is_valid_id( $license_id ) ) {
                    $license = $this->_get_license_by_id( $license_id, false );

                    if ( is_object( $license ) ) {
                        $license_key = $license->secret_key;
                    }
                }
            }

            if ( empty( $license_key ) ) {
                exit;
            }

            $sites = fs_is_network_admin() ?
                fs_request_get( 'sites', array(), 'post' ) :
                array();

            $result = $this->activate_license(
                $license_key,
                $sites,
                fs_request_get_bool( 'is_marketing_allowed', null ),
                fs_request_get( 'blog_id', null ),
                fs_request_get( 'module_id', null, 'post' ),
                fs_request_get( 'user_id', null ),
                fs_request_get_bool( 'is_extensions_tracking_allowed', null ),
                fs_request_get_bool( 'is_diagnostic_tracking_allowed', null )
            );

            if (
                $result['success'] &&
                $this->is_bundle_license_auto_activation_enabled()
            ) {
                $license             = new FS_Plugin_License();
                $license->secret_key = $license_key;

                $this->maybe_activate_bundle_license( $license, $sites );
            }

            echo json_encode( $result );

            exit;
        }

        /**
         * User change WP AJAX handler.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.3.2
         */
        function _user_change_ajax_action() {
            $this->_logger->entrance();

            $this->check_ajax_referer( 'change_user' );

            $new_email_address = trim( fs_request_get( 'email_address', '' ) );
            $new_user_id       = fs_request_get( 'user_id' );

            if ( empty( $new_email_address ) && ! FS_User::is_valid_id( $new_user_id ) ) {
                self::shoot_ajax_failure( fs_text_inline( 'Invalid new user ID or email address.', 'invalid-new-user-id-or-email', $this->get_slug() ) );
            }

            $params = array();

            if ( ! empty( $new_email_address ) ) {
                $params['user_email'] = $new_email_address;
            } else {
                $params['user_id'] = $new_user_id;
            }

            $installs_info_by_slug_map = $this->get_parent_and_addons_installs_info();
            $install_ids               = array();

            foreach ( $installs_info_by_slug_map as $slug => $install_info ) {
                $install_ids[ $slug ] = $install_info['install']->id;
            }
            
            $params['install_ids'] = implode( ',', array_values( $install_ids ) );

            $install = $this->get_api_site_scope()->call( $this->add_show_pending( '/' ), 'put', $params );

            if ( FS_Api::is_api_error( $install ) ) {
                $error = '';

                if ( is_object( $install ) ) {
                    switch ( $install->error->code ) {
                        case 'user_exist':
                            $error = (
                                $this->get_text_x_inline( 'Oops', 'exclamation', 'oops' ) . '...' .
                                $this->get_text_inline( 'Sorry, we could not complete the email update. Another user with the same email is already registered.', 'user-exist-message' ) . ' ' .
                                sprintf( $this->get_text_inline( 'If you would like to give up the ownership of the %s\'s account to %s click the Change Ownership button.', 'user-exist-message_ownership' ), $this->_module_type, '<b>' . $new_email_address . '</b>' ) .
                                sprintf(
                                    '<a style="line-height: 40px;" href="%s"><button class="button button-primary">%s &nbsp;&#10140;</button></a>',
                                    $this->get_account_url( 'change_owner', array(
                                        'state'           => 'init',
                                        'candidate_email' => $new_email_address
                                    ) ),
                                    $this->get_text_inline( 'Change Ownership', 'change-ownership' )
                                )
                            );
                            break;
                    }
                }

                if ( empty( $error ) ) {
                    $error = FS_Api::is_api_error_object( $install ) ?
                        $install->error->message :
                        var_export( $install->error, true );
                }

                self::shoot_ajax_failure( $error );
            } else {
                if (
                    // If successful ownership change.
                    $this->get_user()->id != $install->user_id ||
                    ! empty( $new_email_address )
                ) {
                    $this->complete_ownership_change_by_license( $install->user_id, $install_ids );
                }
            }

            self::shoot_ajax_success();
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.3.2.14
         */
        function starting_migration() {
            if ( ! empty( $this->_storage->license_migration ) ) {
                // Do not overwrite the data if already set.
                return;
            }

            $this->_storage->license_migration = array(
                'is_migrating'    => true,
                'start_timestamp' => time()
            );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.3.2.14
         */
        function is_migration() {
            if ( $this->is_addon() ) {
                return $this->get_parent_instance()->is_migration();
            }

            if ( empty( $this->_storage->license_migration ) ) {
                return false;
            }

            if ( ! $this->_storage->license_migration['is_migrating'] ) {
                return false;
            }

            return (
                // Return `true` if the migration is within 5 minutes from the starting time.
                ( time() - $this->_storage->license_migration['start_timestamp'] ) <= WP_FS__TIME_5_MIN_IN_SEC
            );
        }

        /**
         *
         * A helper method to activate migrated licenses. If the product is network activated and integrated, the method will network activate the license.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.3.0
         *         
         * @param string      $license_key
         * @param null|bool   $is_marketing_allowed
         * @param null|number $plugin_id
         * @param array       $sites
         * @param int         $blog_id
         *
         * @return array {
         *      @var bool   $success
         *      @var string $error
         *      @var string $next_page
         * }
         *
         * @uses Freemius::activate_license()
         */
        function activate_migrated_license(
            $license_key,
            $is_marketing_allowed = null,
            $plugin_id = null,
            $sites = array(),
            $blog_id = null
        ) {
            $this->_logger->entrance();

            $result = $this->activate_license(
                $license_key,
                ( empty( $sites ) && is_null( $blog_id ) && $this->is_network_active() ) ?
                    $this->get_sites_for_network_level_optin() :
                    $sites,
                $is_marketing_allowed,
                $blog_id,
                $plugin_id
            );

            // No need to show the sticky after license activation notice after migrating a license.
            $this->_admin_notices->remove_sticky( 'plan_upgraded' );

            return $result;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.1
         *
         * @return string
         */
        function get_pricing_js_path() {
            if ( ! isset( $this->_pricing_js_path ) ) {
                $default_path = WP_FS__DIR_JS . '/pricing/freemius-pricing.js';

                $pricing_js_path = $this->apply_filters( 'freemius_pricing_js_path', $default_path );

                // Backward compatibility for people who placed the freemius-pricing inside `includes` directory. Let it take more preference than the default path.
                if ( empty( $pricing_js_path ) ) {
                    global $fs_active_plugins;

                    foreach ( $fs_active_plugins->plugins as $sdk_path => $data ) {
                        if ( $data->plugin_path == $this->get_plugin_basename() ) {
                            $plugin_or_theme_root_dir = ( $this->is_plugin() ? WP_PLUGIN_DIR : get_theme_root( get_stylesheet() ) );

                            $pricing_js_path = $plugin_or_theme_root_dir
                                . '/'
                                // The basename will be `plugins`, `themes`, or the basename of a custom plugins or themes directory.
                                . str_replace( '../' . basename( $plugin_or_theme_root_dir ) . '/', '', $sdk_path )
                                . '/includes/freemius-pricing/freemius-pricing.js';

                            break;
                        }
                    }
                }

                // If it is still empty, load the default pricing JS.
                if ( ! file_exists( $pricing_js_path ) ) {
                    $pricing_js_path = $default_path;
                }

                $this->_pricing_js_path = $pricing_js_path;
            }

            return $this->_pricing_js_path;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.1
         *
         * @deprecated Since v2.9.0 we have removed the iFrame based pricing. This will always return `false`.
         *
         * @return bool
         */
        function should_use_external_pricing() {
            return false;
        }

        /**
         * The implementation of this method was previously in `_activate_license_ajax_action()`.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.2.4
         * @since  2.0.0 When a super-admin that hasn't connected before is network activating a license and excluding some of the sites for the license activation, go over the unselected sites in the network and if a site is not connected, skipped, nor delegated, if it's a freemium product then just skip the connection for the site, if it's a premium only product, delegate the connection and license activation to the site admin (Vova Feldman @svovaf).
         * @param string      $license_key
         * @param array       $sites
         * @param null|bool   $is_marketing_allowed
         * @param null|int    $blog_id
         * @param null|number $plugin_id
         * @param null|number $license_owner_id
         * @param bool|null   $is_extensions_tracking_allowed
         * @param bool|null   $is_diagnostic_tracking_allowed Since 2.5.0.2 to allow license activation with minimal data footprint.
         *
         *
         * @return array {
         *      @var bool   $success
         *      @var string $error
         *      @var string $next_page
         * }
         */
        private function activate_license(
            $license_key,
            $sites = array(),
            $is_marketing_allowed = null,
            $blog_id = null,
            $plugin_id = null,
            $license_owner_id = null,
            $is_extensions_tracking_allowed = null,
            $is_diagnostic_tracking_allowed = null
        ) {
            $this->_logger->entrance();

            $license_key = trim( $license_key );

            $is_network_activation_or_migration = (
                fs_is_network_admin() ||
                ( ! empty( $sites ) && $this->is_migration() )
            );

            if ( ! $is_network_activation_or_migration ) {
                // If the license activation is executed outside the context of a network admin, ignore the sites collection.
                $sites = array();
            }

            $fs = ( empty($plugin_id) || $plugin_id == $this->_module_id ) ?
                $this :
                $this->get_addon_instance( $plugin_id );

            FS_Permission_Manager::instance( $this )->update_permissions_tracking_flag( array(
                FS_Permission_Manager::PERMISSION_DIAGNOSTIC => $is_diagnostic_tracking_allowed,
                FS_Permission_Manager::PERMISSION_EXTENSIONS => $is_extensions_tracking_allowed,
            ) );

            $error     = false;
            $next_page = false;

            $has_valid_blog_id = is_numeric( $blog_id );

            $user = null;

            if ( $fs->is_addon() && $fs->get_parent_instance()->is_registered() ) {
                /**
                 * When activating an add-on's license and the parent is opted-in, activate the license with the parent's opted-in user context.
                 *
                 * @author Vova Feldman (@svovaf)
                 */
                $user = $fs->get_parent_instance()->get_current_or_network_user();
            } else if ( $fs->is_registered() ) {
                $user = $fs->get_current_or_network_user();
            }

            if ( $has_valid_blog_id ) {
                /**
                 * If a specific blog ID was provided, activate the license only on the specific blog that is associated with the given blog ID.
                 *
                 * @author Leo Fajardo (@leorw)
                 */
                $fs->switch_to_blog( $blog_id );
            }

            if ( is_object( $user ) ) {
                $result = true;

                if ( $is_network_activation_or_migration && ! $has_valid_blog_id ) {
                    // If no specific blog ID was provided, activate the license for all sites in the network.
                    $blog_2_install_map = array();
                    $site_ids           = array();

                    foreach ( $sites as $site ) {
                        if ( ! isset( $site['blog_id'] ) || ! is_numeric( $site['blog_id'] ) ) {
                            continue;
                        }

                        $install = $fs->get_install_by_blog_id( $site['blog_id'] );

                        if ( is_object( $install ) ) {
                            $blog_2_install_map[ $site['blog_id'] ] = $install;
                        } else {
                            $site_ids[] = $site['blog_id'];
                        }
                    }

                    if ( ! empty( $blog_2_install_map ) ) {
                        $result = $fs->activate_license_on_many_installs( $user, $license_key, $blog_2_install_map );
                    }

                    if ( true === $result && ! empty( $site_ids ) ) {
                        $result = $fs->activate_license_on_many_sites( $user, $license_key, $site_ids );
                    }
                } else {
                    if ( $fs->is_registered() ) {
                        $params = array(
                            'license_key' => $fs->apply_filters( 'license_key', $license_key )
                        );

                        $install_ids = array();

                        $change_owner = FS_User::is_valid_id( $license_owner_id );

                        if ( $change_owner ) {
                            $params['user_id'] = $license_owner_id;

                            $installs_info_by_slug_map = $fs->get_parent_and_addons_installs_info();

                            foreach ( $installs_info_by_slug_map as $slug => $install_info ) {
                                $install_ids[ $slug ] = $install_info['install']->id;
                            }

                            $params['install_ids'] = implode( ',', array_values( $install_ids ) );
                        }

                        $api = $fs->get_api_site_scope();

                        $result = $api->call( $fs->add_show_pending( '/' ), 'put', $params );

                        if ( ! FS_Api::is_api_error( $result ) ) {
                            $install = $result;

                            $fs->reconnect_locally( $has_valid_blog_id );

                            if (
                                $change_owner &&
                                // If successful ownership change.
                                $fs->get_user()->id != $install->user_id
                            ) {
                                $fs->complete_ownership_change_by_license( $install->user_id, $install_ids );
                            }
                        }
                    } else /* ( $fs->is_addon() && $fs->get_parent_instance()->is_registered() ) */ {
                        $result = $fs->activate_license_on_site( $user, $license_key );
                    }
                }

                $is_connected = null;

                if ( true !== $result && ! FS_Api::is_api_result_entity( $result ) ) {
                    if ( FS_Api::is_blocked( $result ) ) {
                        $result->error->message = $this->generate_api_blocked_notice_message_from_result( $result );

                        $is_connected = false;
                    }

                    $error = FS_Api::is_api_error_object( $result ) ?
                        $result->error->message :
                        var_export( $result, true );
                } else {
                    $is_connected = true;

                    $fs->network_upgrade_mode_completed();

                    $fs->_user = $user;

                    if ( fs_is_network_admin() && ! $has_valid_blog_id ) {
                        $fs->_site = $fs->get_network_install();
                    }

                    $fs->_sync_license( true, $has_valid_blog_id );

                    $this->maybe_sync_install_user();

                    $next_page = $fs->is_addon() ?
                        $fs->get_parent_instance()->get_account_url() :
                        $fs->get_after_activation_url( 'after_connect_url' );
                }

                $fs->update_connectivity_info( $is_connected );
            } else {
                $next_page = $fs->opt_in(
                    false,
                    false,
                    false,
                    $license_key,
                    false,
                    false,
                    false,
                    $is_marketing_allowed,
                    $sites
                );

                if ( isset( $next_page->error ) ) {
                    $error = $next_page->error;
                } else {
                    if ( $is_network_activation_or_migration ) {
                        /**
                         * Get the list of sites that were just opted-in (and license activated).
                         * This is an optimization for the next part below saving some DB queries.
                         */
                        $connected_sites = array();
                        foreach ( $sites as $site ) {
                            if ( isset( $site['blog_id'] ) && is_numeric( $site['blog_id'] ) ) {
                                $connected_sites[ $site['blog_id'] ] = true;
                            }
                        }

                        $all_sites        = self::get_sites();
                        $pending_blog_ids = array();

                        /**
                         * Check if there are any sites that are not connected, skipped, nor delegated. For every site that falls into that category, if the product is freemium, skip the connection. If the product is premium only, delegate the connection to the site administrator.
                         *
                         * @author Vova Feldman (@svovaf)
                         */
                        foreach ( $all_sites as $site ) {
                            $blog_id = self::get_site_blog_id( $site );

                            if ( isset( $connected_sites[ $blog_id ] ) ) {
                                // Site was just connected.
                                continue;
                            }

                            if ( $fs->is_installed_on_site( $blog_id ) ) {
                                // Site was already connected before.
                                continue;
                            }

                            if ( $fs->is_site_delegated_connection( $blog_id ) ) {
                                // Site's connection was delegated.
                                continue;
                            }

                            if ( $fs->is_anonymous_site( $blog_id ) ) {
                                // Site connection was already skipped.
                                continue;
                            }

                            $pending_blog_ids[] = $blog_id;
                        }

                        if ( ! empty( $pending_blog_ids ) ) {
                            if ( $fs->is_freemium() && $fs->is_enable_anonymous() ) {
                                $fs->skip_connection( $pending_blog_ids );
                            } else {
                                $fs->delegate_connection( $pending_blog_ids );
                            }
                        }
                    }
                }
            }

            if ( false === $error && true === $fs->_storage->require_license_activation ) {
                $fs->_storage->require_license_activation = false;
            }

            $result = array(
                'success' => ( false === $error )
            );

            if ( false !== $error ) {
                $result['error'] = $fs->apply_filters( 'opt_in_error_message', $error );
            } else {
                if ( $fs->is_addon() || $fs->has_addons() ) {
                    /**
                     * Purge the valid user licenses cache so that when the "Account" or the "Add-Ons" page is loaded,
                     * an updated valid user licenses collection will be fetched from the server which is used to also
                     * update the account add-ons (add-ons the user has licenses for).
                     *
                     * @author Leo Fajardo (@leorw)
                     * @since  2.2.4
                     */
                    $fs->purge_valid_user_licenses_cache();
                }

                $result['next_page'] = $next_page;
            }

            return $result;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.2
         *
         * @return array {
         *      @key   string Product slug.
         *      @value array {
         *          @property FS_Site           $site
         *          @property FS_Plugin_License $license
         *      }
         * }
         */
        private function get_parent_and_addons_installs_info() {
            $fs = $this->is_addon() ?
                $this->get_parent_instance() :
                $this;

            $installed_addons_ids = array();

            $installed_addons_instances = $fs->get_installed_addons();
            foreach ( $installed_addons_instances as $instance ) {
                $installed_addons_ids[] = $instance->get_id();
            }

            $addons_ids = array_unique( array_merge(
                $installed_addons_ids,
                $fs->get_updated_account_addons()
            ) );

            // Add parent product info.
            $installs_info_by_slug_map = array(
                $fs->get_slug() => array(
                    'install' => $fs->get_site(),
                    'license' => $fs->_get_license()
                )
            );

            foreach ( $addons_ids as $addon_id ) {
                $is_installed = isset( $installed_addons_ids_map[ $addon_id ] );

                $addon_info = $fs->_get_addon_info( $addon_id, $is_installed );

                if ( ! isset( $addon_info['is_connected'] ) || ! $addon_info['is_connected'] ) {
                    // Add-on is not associated with an install entity.
                    continue;
                }

                $installs_info_by_slug_map[ $addon_info['slug'] ] = array(
                    'install' => $addon_info['site'],
                    'license' => isset( $addon_info['license'] ) ?
                        $addon_info['license'] :
                        null
                );
            }

            return $installs_info_by_slug_map;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.3.1
         */
        function _network_activate_ajax_action() {
            $this->_logger->entrance();

            $this->check_ajax_referer( 'network_activate' );

            $plugin_id = fs_request_get( 'module_id', '', 'post' );
            $fs        = ( $plugin_id == $this->_module_id ) ?
                $this :
                $this->get_addon_instance( $plugin_id );

            $error = false;

            $sites = fs_request_get( 'sites', array(), 'post' );
            if ( is_array( $sites ) && ! empty( $sites ) ) {
                $sites_by_action = array(
                    'allow'    => array(),
                    'delegate' => array(),
                    'skip'     => array()
                );

                foreach ( $sites as $site ) {
                    $sites_by_action[ $site['action'] ][] = $site;
                }

                $total_sites             = count( $sites );
                $total_sites_to_delegate = count( $sites_by_action['delegate'] );

                $next_page = '';

                $has_any_install = fs_request_get_bool( 'has_any_install' );

                if ( $total_sites === $total_sites_to_delegate &&
                    ! $this->is_network_upgrade_mode() &&
                    ! $has_any_install
                ) {
                    $this->delegate_connection();
                } else {
                    if ( ! empty( $sites_by_action['delegate'] ) ) {
                        $this->delegate_connection( self::get_sites_blog_ids( $sites_by_action['delegate'] ) );
                    }

                    if ( ! empty( $sites_by_action['skip'] ) ) {
                        $this->skip_connection( self::get_sites_blog_ids( $sites_by_action['skip'] ) );
                    }

                    if ( empty( $sites_by_action['allow'] ) ) {
                        if ( $has_any_install ) {
                            $first_install = $fs->find_first_install();

                            if ( ! is_null( $first_install ) ) {
                                $fs->_site                             = $first_install['install'];
                                $fs->_storage->network_install_blog_id = $first_install['blog_id'];

                                $fs->_user                     = self::_get_user_by_id( $fs->_site->user_id );
                                $fs->_storage->network_user_id = $fs->_user->id;
                            }
                        }
                    } else {
                        if ( ! $fs->is_registered() || ! $this->_is_network_active ) {
                            $next_page = $fs->opt_in(
                                false,
                                false,
                                false,
                                false,
                                false,
                                false,
                                false,
                                fs_request_get_bool( 'is_marketing_allowed', null ),
                                $sites_by_action['allow']
                            );
                        } else {
                            $next_page = $fs->install_with_user(
                                $this->get_network_user(),
                                false,
                                false,
                                false,
                                true,
                                $sites_by_action['allow']
                            );
                        }

                        if ( is_object( $next_page ) && isset( $next_page->error ) ) {
                            $error = $next_page->error;
                        }
                    }
                }

                if ( empty( $next_page ) ) {
                    $next_page = $this->get_after_activation_url( 'after_network_activation_url' );
                }
            } else {
                $error = $this->get_text_inline( 'Invalid site details collection.', 'invalid_site_details_collection' );
            }

            $result = array(
                'success' => ( false === $error )
            );

            if ( false !== $error ) {
                $result['error'] = $error;
            } else {
                $result['next_page'] = $next_page;
            }

            echo json_encode( $result );

            exit;
        }

        /**
         * Billing update AJAX callback.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.5
         */
        function _update_billing_ajax_action() {
            $this->_logger->entrance();

            $this->check_ajax_referer( 'update_billing' );

            if ( ! $this->is_user_admin() ) {
                // Only for admins.
                self::shoot_ajax_failure();
            }

            $billing = fs_request_get( 'billing' );

            $api    = $this->get_api_user_scope();
            $result = $api->call( '/billing.json', 'put', array_merge( $billing, array(
                'plugin_id' => $this->get_parent_id(),
            ) ) );

            if ( ! $this->is_api_result_entity( $result ) ) {
                self::shoot_ajax_failure();
            }

            // Purge cached billing.
            $this->get_api_user_scope()->purge_cache( 'billing.json' );

            self::shoot_ajax_success();
        }

        /**
         * Trial start for anonymous users (AJAX callback).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.5
         */
        function _start_trial_ajax_action() {
            $this->_logger->entrance();

            $this->check_ajax_referer( 'start_trial' );

            if ( ! $this->is_user_admin() ) {
                // Only for admins.
                self::shoot_ajax_failure();
            }

            $trial_data = fs_request_get( 'trial' );

            $next_page = $this->opt_in(
                false,
                false,
                false,
                false,
                false,
                $trial_data['plan_id']
            );

            if ( is_object( $next_page ) && $this->is_api_error( $next_page ) ) {
                self::shoot_ajax_failure(
                    isset( $next_page->error ) ?
                        $next_page->error->message :
                        var_export( $next_page, true )
                );
            }

            $this->shoot_ajax_success( array(
                'next_page' => $next_page,
            ) );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.0
         */
        function _resend_license_key_ajax_action() {
            $this->_logger->entrance();

            $this->check_ajax_referer( 'resend_license_key' );

            $email_address = sanitize_email( trim( fs_request_get( 'email', '', 'post' ) ) );

            if ( empty( $email_address ) ) {
                exit;
            }

            $error = false;

            $api    = $this->get_api_plugin_scope();
            $result = $api->call( '/licenses/resend.json', 'post',
                array(
                    'email' => $email_address,
                    'url'   => home_url(),
                )
            );

            if ( is_object( $result ) && isset( $result->error ) ) {
                $error = $result->error;

                if ( in_array( $error->code, array( 'invalid_email', 'no_user' ) ) ) {
                    $error = $this->get_text_inline( "We couldn't find your email address in the system, are you sure it's the right address?", 'email-not-found' );
                } else if ( 'no_license' === $error->code ) {
                    $error = $this->get_text_inline( "We can't see any active licenses associated with that email address, are you sure it's the right address?", 'no-active-licenses' );
                } else {
                    $error = $error->message;
                }
            }

            $licenses = array(
                'success' => ( false === $error )
            );

            if ( false !== $error ) {
                $licenses['error'] = sprintf( '%s... %s', $this->get_text_x_inline( 'Oops', 'exclamation', 'oops' ), strtolower( $error ) );
            }

            echo json_encode( $licenses );

            exit;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.8
         *
         * @var string
         */
        private static $_pagenow;

        /**
         * Get current page or the referer if executing a WP AJAX request.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.8
         *
         * @return string
         */
        static function get_current_page() {
            if ( ! isset( self::$_pagenow ) ) {
                global $pagenow;
                if ( empty( $pagenow ) && is_admin() && is_multisite() ) {
                    /**
                     * It appears that `$pagenow` is not yet initialized in some network admin pages when this method
                     * is called, so initialize it here using some pieces of code from `wp-includes/vars.php`.
                     *
                     * @author Leo Fajardo (@leorw)
                     * @since 2.2.3
                     */
                    if ( is_network_admin() ) {
                        preg_match( '#/wp-admin/network/?(.*?)$#i', $_SERVER['PHP_SELF'], $self_matches );
                    } else if ( is_user_admin() ) {
                        preg_match( '#/wp-admin/user/?(.*?)$#i', $_SERVER['PHP_SELF'], $self_matches );
                    } else {
                        preg_match( '#/wp-admin/?(.*?)$#i', $_SERVER['PHP_SELF'], $self_matches );
                    }

                    $pagenow = $self_matches[1];
                    $pagenow = trim( $pagenow, '/' );
                    $pagenow = preg_replace( '#\?.*?$#', '', $pagenow );
                    if ( '' === $pagenow || 'index' === $pagenow || 'index.php' === $pagenow ) {
                        $pagenow = 'index.php';
                    } else {
                        preg_match( '#(.*?)(/|$)#', $pagenow, $self_matches );
                        $pagenow = strtolower( $self_matches[1] );
                        if ( '.php' !== substr($pagenow, -4, 4) )
                            $pagenow .= '.php'; // for Options +Multiviews: /wp-admin/themes/index.php (themes.php is queried)
                    }
                }

                self::$_pagenow = $pagenow;

                if ( self::is_ajax() &&
                     'admin-ajax.php' === $pagenow
                ) {
                    $referer = fs_get_raw_referer();

                    if ( is_string( $referer ) ) {
                        $parts = explode( '?', $referer );

                        self::$_pagenow = basename( $parts[0] );
                    }
                }
            }

            return self::$_pagenow;
        }

        /**
         * Helper method to check if user in the plugins page.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.5
         *
         * @return bool
         */
        static function is_plugins_page() {
            return ( 'plugins.php' === self::get_current_page() );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.2.3
         *
         * @return bool
         */
        static function is_plugin_install_page() {
            return ( 'plugin-install.php' === self::get_current_page() );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.0.2
         *
         * @return bool
         */
        static function is_updates_page() {
            return ( 'update-core.php' === self::get_current_page() );
        }

        /**
         * Helper method to check if user in the themes page.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.6
         *
         * @return bool
         */
        static function is_themes_page() {
            return ( 'themes.php' === self::get_current_page() );
        }

        #----------------------------------------------------------------------------------
        #region Affiliation
        #----------------------------------------------------------------------------------

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.3
         *
         * @return bool
         */
        function has_affiliate_program() {
            if ( ! is_object( $this->_plugin ) ) {
                return false;
            }

            return $this->_plugin->has_affiliate_program();
        }

        /**
         * Get Plugin ID under which we will track affiliate application.
         *
         * This could either be the Bundle ID or the main plugin ID.
         *
         * @return number Bundle ID if developer has provided one, else the main plugin ID.
         */
        private function get_plugin_id_for_affiliate_terms() {
            return $this->has_bundle_context() ?
                $this->get_bundle_id() :
                $this->_plugin->id;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.4
         */
        private function fetch_affiliate_terms() {
            if ( ! is_object( $this->plugin_affiliate_terms ) ) {
                /**
                 * In case we have a bundle set in SDK configuration, we would like to use that for affiliates, not the main plugin.
                 */
                $plugins_api = $this->has_bundle_context() ?
                    $this->get_api_bundle_scope() :
                    $this->get_api_plugin_scope();

                $affiliate_terms = $plugins_api->get( '/aff.json?type=affiliation', false );

                /**
                 * At this point, we intentionally don't fallback to the main plugin, because the developer has chosen to use bundle. So it makes sense the affiliate program should be in context to the bundle too.
                 */
                if ( ! $this->is_api_result_entity( $affiliate_terms ) ) {
                    return;
                }

                $this->plugin_affiliate_terms = new FS_AffiliateTerms( $affiliate_terms );
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.4
         */
        private function fetch_affiliate_and_custom_terms() {
            if ( ! empty( $this->_storage->affiliate_application_data ) ) {
                $application_data = $this->_storage->affiliate_application_data;
                $flush            = ( ! isset( $application_data['status'] ) || 'pending' === $application_data['status'] );

                $plugin_id_for_affiliate = $this->get_plugin_id_for_affiliate_terms();

                $users_api = $this->get_api_user_scope();
                $result    = $users_api->get( "/plugins/{$plugin_id_for_affiliate}/aff/{$this->plugin_affiliate_terms->id}/affiliates.json", $flush );
                if ( $this->is_api_result_object( $result, 'affiliates' ) ) {
                    if ( ! empty( $result->affiliates ) ) {
                        $affiliate = new FS_Affiliate( $result->affiliates[0] );

                        if ( ! isset( $application_data['status'] ) || $application_data['status'] !== $affiliate->status ) {
                            $application_data['status']                 = $affiliate->status;
                            $this->_storage->affiliate_application_data = $application_data;
                        }

                        if ( $affiliate->is_using_custom_terms ) {
                            $affiliate_terms = $users_api->get( "/plugins/{$this->_plugin->id}/affiliates/{$affiliate->id}/aff/{$affiliate->custom_affiliate_terms_id}.json", $flush );
                            if ( $this->is_api_result_entity( $affiliate_terms ) ) {
                                $this->custom_affiliate_terms = new FS_AffiliateTerms( $affiliate_terms );
                            }
                        }

                        $this->affiliate = $affiliate;
                    }
                }
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.3
         */
        private function fetch_affiliate_and_terms() {
            $this->_logger->entrance();

            $this->fetch_affiliate_terms();
            $this->fetch_affiliate_and_custom_terms();
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.3
         *
         * @return FS_Affiliate
         */
        function get_affiliate() {
            return $this->affiliate;
        }


        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.3
         *
         * @return FS_AffiliateTerms
         */
        function get_affiliate_terms() {
            return is_object( $this->custom_affiliate_terms ) ?
                $this->custom_affiliate_terms :
                $this->plugin_affiliate_terms;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.3
         */
        function _submit_affiliate_application() {
            $this->_logger->entrance();

            $this->check_ajax_referer( 'submit_affiliate_application' );

            if ( ! $this->is_user_admin() ) {
                // Only for admins.
                self::shoot_ajax_failure();
            }

            $affiliate = fs_request_get( 'affiliate' );

            if ( empty( $affiliate['promotion_methods'] ) ) {
                unset( $affiliate['promotion_methods'] );
            }

            if ( ! empty( $affiliate['additional_domains'] ) ) {
                $affiliate['additional_domains'] = array_unique( $affiliate['additional_domains'] );
            }

            if ( ! $this->is_registered() ) {
                $email_address = isset( $affiliate['email'] ) ? $affiliate['email'] : '';

                if ( ! is_email( $email_address ) ) {
                    self::shoot_ajax_failure('Invalid email address.');
                }

                // Opt in but don't track usage.
                $next_page = $this->opt_in(
                    $email_address,
                    false,
                    false,
                    false,
                    false,
                    false,
                    true
                );

                if ( is_object( $next_page ) && $this->is_api_error( $next_page ) ) {
                    self::shoot_ajax_failure(
                        isset( $next_page->error ) ?
                            $next_page->error->message :
                            var_export( $next_page, true )
                    );
                } else if ( $this->is_pending_activation() ) {
                    self::shoot_ajax_failure( $this->get_text_inline( 'Account is pending activation. Please check your email and click the link to activate your account and then submit the affiliate form again.', 'account-is-pending-activation' ) );
                }
            }

            $this->fetch_affiliate_terms();

            $plugin_id_for_affiliate = $this->get_plugin_id_for_affiliate_terms();

            $api    = $this->get_api_user_scope();
            $result = $api->call(
                ( "/plugins/{$plugin_id_for_affiliate}/aff/{$this->plugin_affiliate_terms->id}/affiliates.json" ),
                'post',
                $affiliate
            );

            if ( $this->is_api_error( $result ) ) {
                self::shoot_ajax_failure(
                    isset( $result->error ) ?
                        $result->error->message :
                        var_export( $result, true )
                );
            } else {
                if ( $this->_admin_notices->has_sticky( 'affiliate_program' ) ) {
                    $this->_admin_notices->remove_sticky( 'affiliate_program' );
                }

                $affiliate_application_data = array(
                    'status'                       => 'pending',
                    'stats_description'            => $affiliate['stats_description'],
                    'promotion_method_description' => $affiliate['promotion_method_description'],
                );

                if ( ! empty( $affiliate['promotion_methods'] ) ) {
                    $affiliate_application_data['promotion_methods'] = $affiliate['promotion_methods'];
                }

                if ( ! empty( $affiliate['domain'] ) ) {
                    $affiliate_application_data['domain'] = $affiliate['domain'];
                }

                if ( ! empty( $affiliate['additional_domains'] ) ) {
                    $affiliate_application_data['additional_domains'] = $affiliate['additional_domains'];
                }

                $this->_storage->affiliate_application_data = $affiliate_application_data;
            }

            // Purge cached affiliate.
            $api->purge_cache( 'affiliate.json' );

            self::shoot_ajax_success( $result );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.3
         *
         * @return array|null
         */
        function get_affiliate_application_data() {
            if ( empty( $this->_storage->affiliate_application_data ) ) {
                return null;
            }

            return $this->_storage->affiliate_application_data;
        }

        #endregion Affiliation ------------------------------------------------------------

        #----------------------------------------------------------------------------------
        #region URL Generators
        #----------------------------------------------------------------------------------

        /**
         * Alias to pricing_url().
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.2
         *
         * @uses   pricing_url()
         *
         * @param string $period Billing cycle
         * @param bool   $is_trial
         *
         * @return string
         */
        function get_upgrade_url( $period = WP_FS__PERIOD_ANNUALLY, $is_trial = false ) {
            return $this->pricing_url( $period, $is_trial );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @uses   get_upgrade_url()
         *
         * @return string
         */
        function get_trial_url() {
            return $this->get_upgrade_url( WP_FS__PERIOD_ANNUALLY, true );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.1.4
         *
         * @param string $new_version
         *
         * @return string
         */
        function version_upgrade_checkout_link( $new_version ) {
            if ( ! is_object( $this->_license ) ) {
                $url = $this->pricing_url();

                $purchase_license_text = $this->get_text_inline( 'Buy a license now', 'buy-license-now' );
            } else {
                $subscription = $this->_get_subscription( $this->_license->id );

                $url = $this->checkout_url(
                    is_object( $subscription ) ?
                        ( 1 == $subscription->billing_cycle ? WP_FS__PERIOD_MONTHLY : WP_FS__PERIOD_ANNUALLY ) :
                        WP_FS__PERIOD_LIFETIME,
                    false,
                    array( 'licenses' => $this->_license->quota )
                );

                $purchase_license_text = $this->get_text_inline( 'Renew your license now', 'renew-license-now' );
            }

            return sprintf(
                $this->get_text_inline( '%s to access version %s security & feature updates, and support.', 'x-for-updates-and-support' ),
                sprintf(
                    '<a href="%s">%s</a>',
                    $this->apply_filters( 'update_notice_checkout_url', $url ),
                    $purchase_license_text
                ),
                $new_version
            );
        }

        /**
         * Plugin's pricing URL.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @param string $billing_cycle Billing cycle
         *
         * @param bool   $is_trial
         *
         * @return string
         */
        function pricing_url( $billing_cycle = WP_FS__PERIOD_ANNUALLY, $is_trial = false ) {
            $this->_logger->entrance();

            $params = array(
                'billing_cycle' => $billing_cycle
            );

            if ( $is_trial ) {
                $params['trial'] = 'true';
            }

            $url = $this->is_addon() ?
                $this->_parent->addon_url( $this->_slug ) :
                $this->_get_admin_page_url( 'pricing', $params );

            return $this->get_pricing_url_with_filter( $url );
        }

        /**
         * Retrieves the filtered pricing URL.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.7.4
         *
         * @param string $url
         *
         * @return string
         */
        private function get_pricing_url_with_filter( $url ) {
            return $this->apply_filters( 'pricing_url', $url );
        }

        /**
         * Checkout page URL.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.6
         *
         * @param string    $billing_cycle Billing cycle
         * @param bool      $is_trial
         * @param array     $extra         (optional) Extra parameters, override other query params.
         * @param bool|null $network
         *
         * @return string
         */
        function checkout_url(
            $billing_cycle = WP_FS__PERIOD_ANNUALLY,
            $is_trial = false,
            $extra = array(),
            $network = null
        ) {
            $this->_logger->entrance();

            $params = array(
                'checkout'      => 'true',
                'billing_cycle' => $billing_cycle,
            );

            if ( $is_trial ) {
                $params['trial'] = 'true';
            }

            /**
             * Params in extra override other params.
             */
            $params = array_merge( $params, $extra );

            return $this->apply_filters( 'checkout_url', $this->_get_admin_page_url( 'pricing', $params, $network ) );
        }

        /**
         * Add-on checkout URL.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.1.7
         *
         * @param number    $addon_id
         * @param number    $pricing_id
         * @param string    $billing_cycle
         * @param bool      $is_trial
         * @param bool|null $network
         *
         * @return string
         */
        function addon_checkout_url(
            $addon_id,
            $pricing_id,
            $billing_cycle = WP_FS__PERIOD_ANNUALLY,
            $is_trial = false,
            $network = null
        ) {
            return $this->checkout_url( $billing_cycle, $is_trial, array(
                'plugin_id'  => $addon_id,
                'pricing_id' => $pricing_id,
            ), $network );
        }

        #endregion

        #endregion ------------------------------------------------------------------

        /**
         * Check if plugin has any add-ons.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         *
         * @since  1.1.7.3 Base logic only on the parameter provided by the developer in the init function.
         *
         * @return bool
         */
        function has_addons() {
            $this->_logger->entrance();

            return $this->_has_addons;
        }

        /**
         * Check if plugin can work in anonymous mode.
         *
         * @author     Vova Feldman (@svovaf)
         * @since      1.0.9
         *
         * @return bool
         *
         * @deprecated Please use is_enable_anonymous() instead.
         */
        function enable_anonymous() {
            return $this->_enable_anonymous;
        }

        /**
         * Check if plugin can work in anonymous mode.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.9
         *
         * @return bool
         */
        function is_enable_anonymous() {
            return $this->_enable_anonymous;
        }

        /**
         * Check if plugin is premium only (no free plans).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.9
         *
         * @return bool
         */
        function is_only_premium() {
            return $this->_is_premium_only;
        }

        /**
         * Checks if the plugin's type is "plugin". The other type is "theme".
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.2.2
         *
         * @return bool
         */
        function is_plugin() {
            return ( WP_FS__MODULE_TYPE_PLUGIN === $this->_module_type );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.2
         *
         * @return string
         */
        function get_module_type() {
            if ( ! isset( $this->_module_type ) ) {
                $id_slug_type_path_map = self::$_accounts->get_option( 'id_slug_type_path_map', array() );
                $this->_module_type    = $id_slug_type_path_map[ $this->_module_id ]['type'];
            }

            return $this->_module_type;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.2
         *
         * @return string
         */
        function get_plugin_main_file_path() {
            return $this->_plugin_main_file_path;
        }

        /**
         * Check if module has a premium code version.
         *
         * Serviceware module might be freemium without any
         * premium code version, where the paid features
         * are all part of the service.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.6
         *
         * @return bool
         */
        function has_premium_version() {
            return $this->_has_premium_version;
        }

        /**
         * Check if feature supported with current site's plan.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         *
         * @todo   IMPLEMENT
         *
         * @param number $feature_id
         *
         * @throws Exception
         */
        function is_feature_supported( $feature_id ) {
            throw new Exception( 'not implemented' );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         *
         * @return bool Is running in SSL/HTTPS
         */
        function is_ssl() {
            return WP_FS__IS_HTTPS;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @return bool Is running in AJAX call.
         *
         * @link   http://wordpress.stackexchange.com/questions/70676/how-to-check-if-i-am-in-admin-ajax
         */
        static function is_ajax() {
            return ( defined( 'DOING_AJAX' ) && DOING_AJAX );
        }

        /**
         * Check if it's an AJAX call targeted for the current module.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.0
         *
         * @param array|string $actions Collection of AJAX actions.
         *
         * @return bool
         */
        function is_ajax_action( $actions ) {
            // Verify it's an ajax call.
            if ( ! self::is_ajax() ) {
                return false;
            }

            // Verify the call is relevant for the plugin.
            if ( $this->_module_id != fs_request_get( 'module_id' ) ) {
                return false;
            }

            // Verify it's one of the specified actions.
            if ( is_string( $actions ) ) {
                $actions = explode( ',', $actions );
            }

            if ( is_array( $actions ) && 0 < count( $actions ) ) {
                $ajax_action = fs_request_get( 'action' );

                foreach ( $actions as $action ) {
                    if ( $ajax_action === $this->get_action_tag( $action ) ) {
                        return true;
                    }
                }
            }

            return false;
        }

        /**
         * Check if it's an AJAX call targeted for current request.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.0
         *
         * @param array|string $actions Collection of AJAX actions.
         * @param number|null  $module_id
         *
         * @return bool
         */
        static function is_ajax_action_static( $actions, $module_id = null ) {
            // Verify it's an ajax call.
            if ( ! self::is_ajax() ) {
                return false;
            }


            if ( ! empty( $module_id ) ) {
                // Verify the call is relevant for the plugin.
                if ( $module_id != fs_request_get( 'module_id' ) ) {
                    return false;
                }
            }

            // Verify it's one of the specified actions.
            if ( is_string( $actions ) ) {
                $actions = explode( ',', $actions );
            }

            if ( is_array( $actions ) && 0 < count( $actions ) ) {
                $ajax_action = fs_request_get( 'action' );

                foreach ( $actions as $action ) {
                    if ( $ajax_action === self::get_ajax_action_static( $action, $module_id ) ) {
                        return true;
                    }
                }
            }

            return false;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7
         *
         * @return bool
         */
        static function is_cron() {
            return ( defined( 'DOING_CRON' ) && DOING_CRON );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.5.0
         *
         * @return bool
         */
        static function is_admin_post() {
            return ( 'admin-post.php' === self::get_current_page() );
        }

        /**
         * Check if a real user is visiting the admin dashboard.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7
         *
         * @return bool
         */
        function is_user_in_admin() {
            return (
                is_admin() &&
                ! self::is_ajax() &&
                ! self::is_cron() &&
                ! self::is_admin_post()
            );
        }

        /**
         * Check if a real user is in the customizer view.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @return bool
         */
        static function is_customizer() {
            return is_customize_preview();
        }

        /**
         * Check if running in HTTPS and if site's plan matching the specified plan.
         *
         * @param string $plan
         * @param bool   $exact
         *
         * @return bool
         */
        function is_ssl_and_plan( $plan, $exact = false ) {
            return ( $this->is_ssl() && $this->is_plan( $plan, $exact ) );
        }

        /**
         * Construct plugin's settings page URL.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @param string    $page
         * @param array     $params
         * @param bool|null $network
         *
         * @return string
         */
        function _get_admin_page_url( $page = '', $params = array(), $network = null ) {
            if ( is_null( $network ) ) {
                $network = (
                    $this->_is_network_active &&
                    ( fs_is_network_admin() || ! $this->is_delegated_connection() )
                );
            }

            if ( 0 < count( $params ) ) {
                foreach ( $params as $k => $v ) {
                    $params[ $k ] = urlencode( $v );
                }
            }

            $page_param = $this->_menu->get_slug( $page );

            if ( empty( $page ) &&
                 // Show the opt-in as an overlay for free wp.org themes or themes without any settings page.
                 $this->show_opt_in_on_themes_page()
            ) {
                $params[ $this->get_unique_affix() . '_show_optin' ] = 'true';

                return add_query_arg(
                    $params,
                    $this->admin_url( 'themes.php', 'admin', $network )
                );
            }

            if ( ! $this->has_settings_menu() ) {
                if ( ! empty( $page ) ) {
                    // Module doesn't have a setting page, but since the request is for
                    // a specific Freemius page, use the admin.php path.
                    return add_query_arg( array_merge( $params, array(
                        'page' => $page_param,
                    ) ), $this->admin_url( 'admin.php', 'admin', $network ) );
                } else {
                    if ( $this->is_activation_mode() ) {
                        /**
                         * @author Vova Feldman
                         * @since  1.2.1.6
                         *
                         * If plugin doesn't have a settings page, create one for the opt-in screen.
                         */
                        return add_query_arg( array_merge( $params, array(
                            'page' => $this->_slug,
                        ) ), $this->admin_url( 'admin.php', 'admin', $network ) );
                    } else {
                        // Plugin without a settings page.
                        return add_query_arg(
                            $params,
                            $this->admin_url( 'plugins.php', 'admin', $network )
                        );
                    }
                }
            }

            // Module has a submenu settings page.
            if ( ! $this->_menu->is_top_level() ) {
                $parent_slug = $this->_menu->get_parent_slug();
                $menu_file   = ( false !== strpos( $parent_slug, '.php' ) ) ?
                    $parent_slug :
                    'admin.php';

                return add_query_arg( array_merge( $params, array(
                    'page' => $page_param,
                ) ), $this->admin_url( $menu_file, 'admin', $network ) );
            }

            // Module has a top level CPT settings page.
            if ( $this->_menu->is_cpt() ) {
                if ( empty( $page ) && $this->is_activation_mode() ) {
                    return add_query_arg( array_merge( $params, array(
                        'page' => $page_param
                    ) ), $this->admin_url( 'admin.php', 'admin', $network ) );
                } else {
                    if ( ! empty( $page ) ) {
                        $params['page'] = $page_param;
                    }

                    return add_query_arg(
                        $params,
                        $this->admin_url( $this->_menu->get_raw_slug(), 'admin', $network )
                    );
                }
            }

            // Module has a custom top level settings page.
            return add_query_arg( array_merge( $params, array(
                'page' => $page_param,
            ) ), $this->admin_url( 'admin.php', 'admin', $network ) );
        }

        #--------------------------------------------------------------------------------
        #region Multisite
        #--------------------------------------------------------------------------------

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.0.0
         *
         * @return bool
         */
        function is_network_active() {
            return $this->_is_network_active;
        }

        /**
         * Delegate activation for the given sites in the network (or all sites if `null`) to site admins.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.0.0
         *
         * @param bool|int[] $all_or_blog_ids
         */
        private function delegate_connection( $all_or_blog_ids = true ) {
            $this->_logger->entrance();

            $this->_admin_notices->remove_sticky( 'connect_account' );

            if ( true === $all_or_blog_ids ) {
                // All sites delegation.
                $this->_storage->store( 'is_delegated_connection', true, true );
            } else {
                // Specified sites delegation.
                foreach ( $all_or_blog_ids as $blog_id ) {
                    $this->delegate_site_connection( $blog_id );
                }
            }

            $this->network_upgrade_mode_completed();
        }

        /**
         * Delegate specific network site conncetion to the site admin.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int $blog_id
         */
        private function delegate_site_connection( $blog_id ) {
            $this->_storage->store( 'is_delegated_connection', true, $blog_id );
        }

        /**
         * Check if super-admin delegated the connection of ALL sites to the site admins.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return bool
         */
        function is_network_delegated_connection() {
            if ( ! $this->_is_network_active ) {
                return false;
            }

            return $this->_storage->get( 'is_delegated_connection', false, true );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.0.0
         *
         * @param int $blog_id
         *
         * @return bool
         */
        function is_site_delegated_connection( $blog_id = 0 ) {
            if ( ! $this->_is_network_active ) {
                return false;
            }

            if ( 0 == $blog_id ) {
                $blog_id = get_current_blog_id();
            }

            return $this->_storage->get( 'is_delegated_connection', false, $blog_id );
        }

        /**
         * Check if delegated the connection. When running within the network admin,
         * and haven't specified the blog ID, checks if network level delegated. If running
         * within a site admin or specified a blog ID, check if delegated the connection for
         * the current context site.
         *
         * If executed outside the the admin, check if delegated the connection
         * for the current context site OR the whole network.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int $blog_id If set, checks if network delegated or blog specific delegated.
         *
         * @return bool
         */
        function is_delegated_connection( $blog_id = 0 ) {
            if ( ! $this->_is_network_active ) {
                return false;
            }

            if ( fs_is_network_admin() && 0 == $blog_id ) {
                return $this->is_network_delegated_connection();
            }

            return (
                $this->is_network_delegated_connection() ||
                $this->is_site_delegated_connection( $blog_id )
            );
        }

        /**
         * Check if the current module is active for the site.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int $blog_id
         *
         * @return bool
         */
        function is_active_for_site( $blog_id ) {
            if ( ! is_multisite() ) {
                // Not a multisite and this code is executed, means that the plugin is active.
                return true;
            }

            if ( $this->is_theme() ) {
                // All themes are site level activated.
                return true;
            }

            if ( $this->_is_network_active ) {
                // Plugin was network activated so it's active.
                return true;
            }

            return in_array( $this->_plugin_basename, (array) get_blog_option( $blog_id, 'active_plugins', array() ) );
        }

        /**
         * @todo Implement pagination when accessing the subsites collection.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.0.0
         *
         * @param int $limit  Default to 1,000
         * @param int $offset Default to 0
         *
         * @return array Active & public sites collection.
         */
        static function get_sites( $limit = 1000, $offset = 0 ) {
            if ( ! is_multisite() ) {
                return array();
            }

            /**
             * For consistency with get_blog_list() which only return active public sites.
             *
             * @author Vova Feldman (@svovaf)
             */
            $args = array(
                /**
                 * Commented out in order to handle the migration of site options whether the site is public or not.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.2.1
                 */
                // 'public'   => 1,
                'archived' => 0,
                'mature'   => 0,
                'spam'     => 0,
                'deleted'  => 0,
                'number'   => $limit,
                'offset'   => $offset,
            );

            return get_sites( $args );
        }

        /**
         * Checks if a given blog is active.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param $blog_id
         *
         * @return bool
         */
        private static function is_site_active( $blog_id ) {
            global $wpdb;

            $blog_info = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->blogs} WHERE blog_id = %d", $blog_id ) );

            if ( ! is_object( $blog_info ) ) {
                return false;
            }

            return (
                true == $blog_info->public &&
                false == $blog_info->archived &&
                false == $blog_info->mature &&
                false == $blog_info->spam &&
                false == $blog_info->deleted
            );
        }

        /**
         * Get a mapping between the site addresses to their blog IDs.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return array {
         * @key    string Site address without protocol with a trailing slash.
         * @value  int Site's blog ID.
         * }
         */
        private function get_address_to_blog_map() {
            $sites = self::get_sites();

            // Map site addresses to their blog IDs.
            $address_to_blog_map = array();
            foreach ( $sites as $site ) {
                $blog_id                         = self::get_site_blog_id( $site );
                $address                         = self::get_unfiltered_site_url( $blog_id, true, true );
                $address_to_blog_map[ $address ] = $blog_id;
            }

            return $address_to_blog_map;
        }

        /**
         * Get a mapping between the site addresses to their blog IDs.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return array {
         * @key    int     Site's blog ID.
         * @value  FS_Site Associated install.
         * }
         */
        function get_blog_install_map() {
            $sites = self::get_sites();

            // Map site blog ID to its install.
            $install_map = array();

            foreach ( $sites as $site ) {
                $blog_id = self::get_site_blog_id( $site );
                $install = $this->get_install_by_blog_id( $blog_id );

                if ( is_object( $install ) ) {
                    $install_map[ $blog_id ] = $install;
                }
            }

            return $install_map;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.5.1
         *
         * @param bool|null $is_delegated When `true`, returns only connection delegated blog IDs. When `false`, only non-delegated blog IDs.
         *
         * @return int[]
         */
        private function get_blog_ids( $is_delegated = null ) {
            $blog_ids = array();

            $sites = self::get_sites();
            foreach ( $sites as $site ) {
                $blog_id = self::get_site_blog_id( $site );

                if (
                    is_null( $is_delegated ) ||
                    $is_delegated === $this->is_site_delegated_connection( $blog_id )
                ) {
                    $blog_ids[] = $blog_id;
                }
            }

            return $blog_ids;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.5.1
         *
         * @return int[]
         */
        private function get_non_delegated_blog_ids() {
            return $this->get_blog_ids( false );
        }

        /**
         * Gets a map of module IDs that the given user has opted-in to.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.1.0
         *
         * @param number $fs_user_id
         *
         * @return array {
         * @key number $plugin_id
         * @value bool Always true.
         * }
         */
        private static function get_user_opted_in_module_ids_map( $fs_user_id ) {
            self::$_static_logger->entrance();

            if ( ! is_multisite() ) {
                $installs = array_merge(
                    self::get_all_sites( WP_FS__MODULE_TYPE_PLUGIN ),
                    self::get_all_sites( WP_FS__MODULE_TYPE_THEME )
                );
            } else {
                $sites = self::get_sites();

                $installs = array();
                foreach ( $sites as $site ) {
                    $blog_id = self::get_site_blog_id( $site );

                    $installs = array_merge(
                        $installs,
                        self::get_all_sites( WP_FS__MODULE_TYPE_PLUGIN, $blog_id ),
                        self::get_all_sites( WP_FS__MODULE_TYPE_THEME, $blog_id )
                    );
                }
            }

            $module_ids_map = array();
            foreach ( $installs as $install ) {
                if ( is_object( $install ) &&
                     FS_Site::is_valid_id( $install->id ) &&
                     FS_User::is_valid_id( $install->user_id ) &&
                     ( $install->user_id == $fs_user_id )
                ) {
                    $module_ids_map[ $install->plugin_id ] = true;
                }
            }

            return $module_ids_map;
        }

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @return null|array {
         *      'install' => FS_Site Module's install,
         *      'blog_id' => string The associated blog ID.
         * }
         */
        function find_first_install() {
            $sites = self::get_sites();

            foreach ( $sites as $site ) {
                $blog_id = self::get_site_blog_id( $site );
                $install = $this->get_install_by_blog_id( $blog_id );

                if ( is_object( $install ) ) {
                    return array(
                        'install' => $install,
                        'blog_id' => $blog_id
                    );
                }
            }

            return null;
        }

        /**
         * Switches the Freemius site level context to a specified blog.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int     $blog_id
         * @param FS_Site $install
         * @param bool    $flush
         *
         * @return bool Since 2.3.1 returns if a switch was made.
         */
        function switch_to_blog( $blog_id, FS_Site $install = null, $flush = false ) {
            if ( ! is_numeric( $blog_id ) ) {
                return false;
            }

            if ( ! $flush && $blog_id == $this->_context_is_network_or_blog_id ) {
                return false;
            }

            switch_to_blog( $blog_id );
            $this->_context_is_network_or_blog_id = $blog_id;

            self::$_accounts->set_site_blog_context( $blog_id );
            $this->_storage->set_site_blog_context( $blog_id );
            $this->_storage->set_network_active( $this->_is_network_active, $this->is_delegated_connection( $blog_id ) );

            $this->_site = is_object( $install ) ?
                $install :
                $this->get_install_by_blog_id( $blog_id );

            $this->_user           = false;
            $this->_licenses       = false;
            $this->_license        = null;
            $this->is_whitelabeled = null;

            if ( is_object( $this->_site ) ) {
                // Try to fetch user from install.
                $this->_user = self::_get_user_by_id( $this->_site->user_id );

                if ( ! is_object( $this->_user ) &&
                     FS_User::is_valid_id( $this->_storage->prev_user_id )
                ) {
                    // Try to fetch previously saved user.
                    $this->_user = self::_get_user_by_id( $this->_storage->prev_user_id );

                    if ( ! is_object( $this->_user ) ) {
                        // Fallback to network's user.
                        $this->_user = $this->get_network_user();
                    }
                }

                $all_plugin_licenses = self::get_all_licenses( $this->_module_id );

                if ( ! empty( $all_plugin_licenses ) ) {
                    if ( ! FS_Plugin_License::is_valid_id( $this->_site->license_id ) ) {
                        $this->_license = null;
                    } else {
                        $license_found = false;
                        foreach ( $all_plugin_licenses as $license ) {
                            if ( $license->id == $this->_site->license_id ) {
                                // License found.
                                $this->_license = $license;
                                $license_found  = true;
                                break;
                            }
                        }

                        if ( $license_found ) {
                            $this->link_license_2_user( $this->_license->id, $this->_user->id );
                        }
                    }

                    $this->_licenses = $this->get_user_licenses( $this->_user->id );
                }
            }

            unset( $this->_site_api );
            unset( $this->_user_api );

            return true;
        }

        /**
         * Restore the blog context to the blog that originally loaded the module.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         */
        function restore_current_blog() {
            $this->switch_to_blog( $this->_blog_id );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param array|WP_Site $site
         *
         * @return int
         */
        static function get_site_blog_id( &$site ) {
            return ( $site instanceof WP_Site ) ?
                $site->blog_id :
                ( is_object( $site ) && isset( $site->userblog_id ) ?
                    $site->userblog_id :
                    $site['blog_id'] );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.5.1
         *
         * @param WP_Site[]|array[] $sites
         *
         * @return int[]
         */
        static function get_sites_blog_ids( $sites ) {
            $blog_ids = array();
            foreach ( $sites as $site ) {
                $blog_ids[] = self::get_site_blog_id( $site );
            }

            return $blog_ids;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.0.0
         *
         * @param array|WP_Site|null $site
         * @param bool               $load_registration Since 2.5.1 When set to `true` the method will attempt to return the subsite's registration date, regardless of the `$site` type and value. In most calls, the registration date will be returned anyway, even when the value is `false`. This param is purely for performance optimization.
         *
         * @return array
         */
        function get_site_info( $site = null, $load_registration = false ) {
            $this->_logger->entrance();

            $switched = false;

            $registration_date = null;

            if ( is_null( $site ) ) {
                $url     = self::get_unfiltered_site_url();
                $name    = get_bloginfo( 'name' );
                $blog_id = null;
            } else {
                $blog_id = self::get_site_blog_id( $site );

                if ( get_current_blog_id() != $blog_id ) {
                    switch_to_blog( $blog_id );
                    $switched = true;
                }

                if ( $site instanceof WP_Site ) {
                    $url               = $site->siteurl;
                    $name              = $site->blogname;
                    $registration_date = $site->registered;
                } else {
                    $url  = self::get_unfiltered_site_url( $blog_id );
                    $name = get_bloginfo( 'name' );
                }
            }

            if ( empty( $registration_date ) && $load_registration ) {
                $blog_details = get_blog_details( $blog_id, false );

                if ( is_object( $blog_details ) && isset( $blog_details->registered ) ) {
                    $registration_date = $blog_details->registered;
                }
            }

            $info = array(
                'uid' => $this->get_anonymous_id( $blog_id ),
                'url' => $url,
            );

            // Add these diagnostic information only if user allowed to track.
            if ( FS_Permission_Manager::instance( $this )->is_diagnostic_tracking_allowed() ) {
                $info = array_merge( $info, array(
                    'title'    => $name,
                    'language' => self::get_sanitized_language(),
                ) );
            }

            if ( is_numeric( $blog_id ) ) {
                $info['blog_id'] = $blog_id;
            }

            if ( ! empty( $registration_date ) ) {
                $info[ 'registration_date' ] = $registration_date;
            }

            if ( $switched ) {
                restore_current_blog();
            }

            return $info;
        }

        /**
         * Load the module's install based on the blog ID.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int|null $blog_id
         *
         * @return FS_Site
         */
        function get_install_by_blog_id( $blog_id = null ) {
            $installs = self::get_all_sites( $this->_module_type, $blog_id );
            $install  = isset( $installs[ $this->_slug ] ) ? $installs[ $this->_slug ] : null;

            if ( is_object( $install ) &&
                 is_numeric( $install->id ) &&
                 is_numeric( $install->user_id ) &&
                 FS_Plugin_Plan::is_valid_id( $install->plan_id )
            ) {
                // Load site.
                $install = clone $install;
            }

            return $install;
        }

        /**
         * Check if module is installed on a specified site.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int|null $blog_id
         *
         * @return bool
         */
        function is_installed_on_site( $blog_id = null ) {
            $installs = self::get_all_sites( $this->_module_type, $blog_id );
            $install  = isset( $installs[ $this->_slug ] ) ? $installs[ $this->_slug ] : null;

            return (
                is_object( $install ) &&
                is_numeric( $install->id ) &&
                is_numeric( $install->user_id ) &&
                FS_Plugin_Plan::is_valid_id( $install->plan_id )
            );
        }

        /**
         * Check if super-admin connected at least one site via the network opt-in.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return bool
         */
        function is_network_registered() {
            if ( ! $this->_is_network_active ) {
                return false;
            }

            return FS_User::is_valid_id( $this->_storage->network_user_id );
        }

        /**
         * Returns the main user associated with the network.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return FS_User
         */
        function get_network_user() {
            if ( ! $this->_is_network_active ) {
                return null;
            }

            return FS_User::is_valid_id( $this->_storage->network_user_id ) ?
                self::_get_user_by_id( $this->_storage->network_user_id ) :
                null;
        }

        /**
         * Returns the current context user or the network's main user.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return FS_User
         */
        function get_current_or_network_user() {
            return ( $this->_user instanceof FS_User ) ?
                $this->_user :
                $this->get_network_user();
        }

        /**
         * Returns the main install associated with the network.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return FS_Site
         */
        function get_network_install() {
            if ( ! $this->_is_network_active ) {
                return null;
            }

            return FS_Site::is_valid_id( $this->_storage->network_install_blog_id ) ?
                $this->get_install_by_blog_id( $this->_storage->network_install_blog_id ) :
                null;
        }

        /**
         * Returns the blog ID that is associated with the main install.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.0.0
         *
         * @return int|null
         */
        function get_network_install_blog_id() {
            if ( ! $this->_is_network_active ) {
                return null;
            }

            return FS_Site::is_valid_id( $this->_storage->network_install_blog_id ) ?
                $this->_storage->network_install_blog_id :
                null;
        }

        /**
         * Returns the current context install or the network's main install.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return FS_Site
         */
        function get_current_or_network_install() {
            return ( $this->_site instanceof FS_Site ) ?
                $this->_site :
                $this->get_network_install();
        }

        /**
         * Check if executing a site level action from the network level admin.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return false|int If yes, return the requested blog ID.
         */
        private function is_network_level_site_specific_action() {
            if ( ! $this->_is_network_active ) {
                return false;
            }

            if ( ! fs_is_network_admin() ) {
                return false;
            }

            $blog_id = fs_request_get( 'blog_id', '' );

            return is_numeric( $blog_id ) ? $blog_id : false;
        }

        /**
         * Check if executing an action from the network level admin.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return bool
         */
        private function is_network_level_action() {
            return ( $this->_is_network_active && fs_is_network_admin() );
        }

        /**
         * Needs to be executed after site deactivation, archive, deletion, or flag as spam.
         * The logic updates the network level user and blog, and reschedule the crons if the cron executing site matching the site that is no longer publicly active.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int $context_blog_id
         */
        private function update_multisite_data_after_site_deactivation( $context_blog_id = 0 ) {
            $this->_logger->entrance();

            if ( $this->_is_network_active ) {
                if ( $context_blog_id == $this->_storage->network_install_blog_id ) {
                    $installs_map = $this->get_blog_install_map();

                    foreach ( $installs_map as $blog_id => $install ) {
                        /**
                         * @var FS_Site $install
                         */
                        if ( $context_blog_id == $blog_id ) {
                            continue;
                        }

                        if ( $install->user_id != $this->_storage->network_user_id ) {
                            continue;
                        }

                        // Switch reference to a blog that is opted-in and belong to the same super-admin.
                        $this->_storage->network_install_blog_id = $blog_id;
                        break;
                    }
                }
            }

            if ( ! $this->is_registered() ) {
                return;
            }

            if ( $this->is_sync_cron_scheduled() &&
                 $context_blog_id == $this->get_sync_cron_blog_id()
            ) {
                $this->schedule_sync_cron( WP_FS__SCRIPT_START_TIME, true, $context_blog_id );
            }

            if ( $this->is_install_sync_scheduled() &&
                 $context_blog_id == $this->get_install_sync_cron_blog_id()
            ) {
                $this->schedule_install_sync( $context_blog_id );
            }
        }

        /**
         * Executed after site deactivation, archive, or flag as spam.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int $context_blog_id
         */
        public function _after_site_deactivated_callback( $context_blog_id = 0 ) {
            $this->_logger->entrance();

            $install = $this->get_install_by_blog_id( $context_blog_id );

            if ( ! is_object( $install ) ) {
                // Site not connected.
                return;
            }

            $this->update_multisite_data_after_site_deactivation( $context_blog_id );

            if ( ! $this->is_registered() ) {
                return;
            }

            $current_blog_id = get_current_blog_id();

            $this->switch_to_blog( $context_blog_id );

            // Send deactivation event.
            $this->sync_install( array(
                'is_active' => false,
            ) );

            $this->switch_to_blog( $current_blog_id );
        }

        /**
         * Executed after site deletion.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int  $context_blog_id
         * @param bool $drop True if site's database tables should be dropped. Default is false.
         */
        public function _after_site_deleted_callback( $context_blog_id = 0, $drop = false ) {
            $this->_logger->entrance();

            $install = $this->get_install_by_blog_id( $context_blog_id );

            if ( ! is_object( $install ) ) {
                // Site not connected.
                return;
            }

            $this->update_multisite_data_after_site_deactivation( $context_blog_id );

            if ( ! $this->is_registered() ) {
                return;
            }

            $current_blog_id = get_current_blog_id();

            $this->switch_to_blog( $context_blog_id );

            if ( $drop ) {
                // Delete install if dropping site DB.
                $this->delete_account_event();
            } else {
                // Send deactivation event.
                $this->sync_install( array(
                    'is_active' => false,
                ) );
            }

            $this->switch_to_blog( $current_blog_id );
        }

        /**
         * Executed after site deletion, called from wp_delete_site
         *
         * @author Dario Curvino (@dudo)
         * @since  2.5.0
         *
         * @param WP_Site $old_site
         */
        public function _after_wpsite_deleted_callback( WP_Site $old_site ) {
            $this->_logger->entrance();

            $this->_after_site_deleted_callback( $old_site->blog_id, true );
        }

        /**
         * Executed after site re-activation.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param int $context_blog_id
         */
        public function _after_site_reactivated_callback( $context_blog_id = 0 ) {
            $this->_logger->entrance();

            $install = $this->get_install_by_blog_id( $context_blog_id );

            if ( ! is_object( $install ) ) {
                // Site not connected.
                return;
            }

            if ( ! self::is_site_active( $context_blog_id ) ) {
                // Site not yet active (can be in spam mode, archived, deleted...).
                return;
            }

            $current_blog_id = get_current_blog_id();

            $this->switch_to_blog( $context_blog_id );

            // Send re-activation event.
            $this->sync_install( array(
                'is_active' => true,
            ) );

            $this->switch_to_blog( $current_blog_id );
        }

        #endregion Multisite

        /**
         * @author Leo Fajardo (@leorw)
         *
         * @param string $path
         * @param string $scheme
         * @param bool   $network
         *
         * @return string
         */
        private function admin_url( $path = '', $scheme = 'admin', $network = true ) {
            return ( $this->_is_network_active && $network ) ?
                network_admin_url( $path, $scheme ) :
                admin_url( $path, $scheme );
        }

        /**
         * Check if currently in a specified admin page.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @param string $page
         *
         * @return bool
         */
        function is_admin_page( $page ) {
            return ( $this->_menu->get_slug( $page ) === fs_request_get( 'page', '', 'get' ) );
        }

        /**
         * Check if currently in the product's main admin page.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.3.1
         *
         * @return bool
         */
        function is_main_admin_page() {
            return $this->is_admin_page( '' );
        }

        /**
         * Get module's main admin setting page URL.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @return string
         */
        function main_menu_url() {
            return $this->_menu->main_menu_url();
        }

        /**
         * Check if currently on the theme's setting page or
         * on any of the Freemius added pages (via tabs).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @return bool
         *
         * @deprecated Please use is_product_settings_page() instead;
         */
        function is_theme_settings_page() {
            return $this->is_product_settings_page();
        }

        /**
         * Check if currently on the product's main setting page or on any of the Freemius added pages (via tabs).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @return bool
         */
        function is_product_settings_page() {
            $page      = fs_request_get( 'page', '', 'get' );
            $menu_slug = $this->_menu->get_slug();

            if ( $page === $menu_slug ) {
                return true;
            }

            return fs_starts_with(
                // e.g., {$menu_slug}-account, {$menu_slug}-affiliation, etc.
                $page,
                ( $menu_slug . '-' )
            );
        }

        /**
         * Plugin's account page + sync license URL.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.9.1
         *
         * @param bool|number $plugin_id
         * @param bool        $add_action_nonce
         * @param array       $params
         *
         * @return string
         */
        function _get_sync_license_url( $plugin_id = false, $add_action_nonce = true, $params = array() ) {
            if ( is_numeric( $plugin_id ) ) {
                $params['plugin_id'] = $plugin_id;
            }

            return $this->get_account_url(
                $this->get_unique_affix() . '_sync_license',
                $params,
                $add_action_nonce
            );
        }

        /**
         * Plugin's account URL.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @param bool|string $action
         * @param array       $params
         *
         * @param bool        $add_action_nonce
         *
         * @return string
         */
        function get_account_url( $action = false, $params = array(), $add_action_nonce = true ) {
            if ( is_string( $action ) ) {
                $params['fs_action'] = $action;
            }

            self::require_pluggable_essentials();

            return ( $add_action_nonce && is_string( $action ) ) ?
                fs_nonce_url( $this->_get_admin_page_url( 'account', $params ), $action ) :
                $this->_get_admin_page_url( 'account', $params );
        }

        /**
         * @author  Vova Feldman (@svovaf)
         * @since   1.2.0
         *
         * @param string $tab
         * @param bool   $action
         * @param array  $params
         * @param bool   $add_action_nonce
         *
         * @return string
         *
         * @uses    get_account_url()
         */
        function get_account_tab_url( $tab, $action = false, $params = array(), $add_action_nonce = true ) {
            $params['tab'] = $tab;

            return $this->get_account_url( $action, $params, $add_action_nonce );
        }

        /**
         * Plugin's account URL.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @param bool|string $topic
         * @param bool|string $message
         * @param bool|string $summary Since 2.5.1.
         *
         * @return string
         */
        function contact_url( $topic = false, $message = false, $summary = false ) {
            $params = array();
            if ( is_string( $topic ) ) {
                $params['topic'] = $topic;
            }
            if ( is_string( $message ) ) {
                $params['message'] = $message;
            }

            if ( is_string( $summary ) ) {
                $params['summary'] = $summary;
            }

            if ( $this->is_addon() ) {
                $params['addon_id'] = $this->get_id();

                return $this->get_parent_instance()->_get_admin_page_url( 'contact', $params );
            } else {
                return $this->_get_admin_page_url( 'contact', $params );
            }
        }

        /**
         * Add-on direct info URL.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.0
         *
         * @param string $slug
         *
         * @return string
         */
        function addon_url( $slug ) {
            return $this->_get_admin_page_url( 'addons', array(
                'slug' => $slug
            ) );
        }

        /**
         * Add-ons URL.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.4.5
         *
         * @return string
         */
        function get_addons_url() {
            return $this->_get_admin_page_url( 'addons' );
        }

        /* Logger
        ------------------------------------------------------------------------------------------------------------------*/
        /**
         * @param string $id
         * @param bool   $prefix_slug
         *
         * @return FS_Logger
         */
        function get_logger( $id = '', $prefix_slug = true ) {
            return FS_Logger::get_logger( ( $prefix_slug ? $this->_slug : '' ) . ( ( ! $prefix_slug || empty( $id ) ) ? '' : '_' ) . $id );
        }

        /**
         * Note: This method is used externally so don't delete it.
         *
         * @param      $id
         * @param bool $load_options
         * @param bool $prefix_slug
         *
         * @return FS_Option_Manager
         */
        function get_options_manager( $id, $load_options = false, $prefix_slug = true ) {
            return FS_Option_Manager::get_manager( ( $prefix_slug ? $this->_slug : '' ) . ( ( ! $prefix_slug || empty( $id ) ) ? '' : '_' ) . $id, $load_options );
        }

        /* Security
        ------------------------------------------------------------------------------------------------------------------*/
        private static function _encrypt( $str ) {
            if ( is_null( $str ) ) {
                return null;
            }

            /**
             * The encrypt/decrypt functions are used to protect
             * the user from messing up with some of the sensitive
             * data stored for the module as a JSON in the database.
             *
             * I used the same suggested hack by the theme review team.
             * For more details, look at the function `Base64UrlDecode()`
             * in `./sdk/FreemiusBase.php`.
             *
             * @todo   Remove this hack once the base64 error is removed from the Theme Check.
             *
             * @author Vova Feldman (@svovaf)
             * @since  1.2.2
             */
            $fn = 'base64' . '_encode';

            return $fn( $str );
        }

        static function _decrypt( $str ) {
            if ( is_null( $str ) ) {
                return null;
            }

            /**
             * The encrypt/decrypt functions are used to protect
             * the user from messing up with some of the sensitive
             * data stored for the module as a JSON in the database.
             *
             * I used the same suggested hack by the theme review team.
             * For more details, look at the function `Base64UrlDecode()`
             * in `./sdk/FreemiusBase.php`.
             *
             * @todo   Remove this hack once the base64 error is removed from the Theme Check.
             *
             * @author Vova Feldman (@svovaf)
             * @since  1.2.2
             */
            $fn = 'base64' . '_decode';

            return $fn( $str );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         *
         * @param FS_Entity $entity
         *
         * @return FS_Entity Return an encrypted clone entity.
         */
        private static function _encrypt_entity( FS_Entity $entity ) {
            $clone = clone $entity;
            $props = get_object_vars( $entity );

            foreach ( $props as $key => $val ) {
                $clone->{$key} = self::_encrypt( $val );
            }

            return $clone;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         *
         * @param FS_Entity $entity
         *
         * @return FS_Entity Return an decrypted clone entity.
         */
        private static function decrypt_entity( FS_Entity $entity ) {
            $clone = clone $entity;
            $props = get_object_vars( $entity );

            foreach ( $props as $key => $val ) {
                $clone->{$key} = self::_decrypt( $val );
            }

            return $clone;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.7
         *
         * @param string $email
         *
         * @return FS_User|false
         */
        public static function _get_user_by_email( $email ) {
            self::$_static_logger->entrance();

            $email = trim( strtolower( $email ) );

            $users = self::get_all_users();

            if ( is_array( $users ) ) {
                foreach ( $users as $user ) {
                    if ( $email === trim( strtolower( $user->email ) ) ) {
                        return $user;
                    }
                }
            }

            return false;
        }

        #----------------------------------------------------------------------------------
        #region Account (Loading, Updates & Activation)
        #----------------------------------------------------------------------------------

        /***
         * Load account information (user + site).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         */
        private function _load_account() {
            $this->_logger->entrance();

            $this->do_action( 'before_account_load' );

            $users = self::get_all_users();
            $plans = self::get_all_plans( $this->_module_type );

            if ( $this->_logger->is_on() && is_admin() ) {
                $this->_logger->log( 'users = ' . var_export( $users, true ) );
                $this->_logger->log( 'plans = ' . var_export( $plans, true ) );
            }

            $site = fs_is_network_admin() ?
                $this->get_network_install() :
                $this->get_install_by_blog_id();

            if ( fs_is_network_admin() &&
                $this->is_network_active() &&
                ! is_object( $site ) &&
                FS_Site::is_valid_id( $this->_storage->network_install_blog_id )
            ) {
                $first_install = $this->find_first_install();

                if ( is_null( $first_install ) ) {
                    unset( $this->_storage->network_install_blog_id );
                } else {
                    $site                                    = $first_install['install'];
                    $this->_storage->network_install_blog_id = $first_install['blog_id'];
                }
            }

            if ( is_object( $site ) &&
                 is_numeric( $site->id ) &&
                 is_numeric( $site->user_id ) &&
                 FS_Plugin_Plan::is_valid_id( $site->plan_id )
            ) {
                // Load site.
                $this->_site = $site;
            }

            $user = null;
            if ( fs_is_network_admin() && $this->_is_network_active ) {
                $user = $this->get_network_user();
            }

            if ( is_object( $user ) ) {
                $this->_user = clone $user;
            } else if ( $this->_site ) {
                $user = self::_get_user_by_id( $this->_site->user_id );

                if ( ! is_object( $user ) && FS_User::is_valid_id( $this->_storage->prev_user_id ) ) {
                    /**
                     * Try to load the previous owner. This recovery is used for the following use-case:
                     *      1. Opt-in
                     *      2. Cloning site1 to site2
                     *      3. Ownership switch in site1 (same applies for site2)
                     *      4. Install data sync on site2
                     *      5. Now site2's install is associated with the new owner which does not exists locally.
                     */
                    $user = self::_get_user_by_id( $this->_storage->prev_user_id );
                }

                if ( ! is_object( $user ) ) {
                    /**
                     * This is a special fault tolerance mechanism to handle a scenario that the user data is missing.
                     */
                    if (
                        ! isset( $this->_storage->user_recovery_from_install_last_attempt_timestamp ) ||
                        time() > ( $this->_storage->user_recovery_from_install_last_attempt_timestamp + FS_Clone_Manager::CLONE_RESOLUTION_MAX_EXECUTION_TIME )
                    ) {
                        $user = $this->sync_user_by_current_install();
                    } else {
                        return;
                    }

                    if ( is_object( $user ) ) {
                        $this->_storage->user_was_recovered_from_install = true;
                    } else {
                        $this->_storage->user_recovery_from_install_attempts = isset( $this->_storage->user_recovery_from_install_attempts ) ?
                            ( $this->_storage->user_recovery_from_install_attempts + 1 ) :
                            1;

                        if ( $this->_storage->user_recovery_from_install_attempts >= 3 ) {
                            $this->delete_current_install( false );
                        } else {
                            $this->_storage->user_recovery_from_install_last_attempt_timestamp = time();

                            return;
                        }
                    }
                }

                $this->_user = ( $user instanceof FS_User ) ?
                    clone $user :
                    null;
            }

            if ( is_object( $this->_user ) ) {
                // Load licenses.
                $this->_licenses = $this->get_user_licenses( $this->_user->id );
            }

            if ( is_object( $this->_site ) ) {
                // Load plans.
                $this->_plans = isset( $plans[ $this->_slug ] ) ?
                    $plans[ $this->_slug ] :
                    array();

                if ( ! is_array( $this->_plans ) || empty( $this->_plans ) ) {
                    $this->_sync_plans();
                } else {
                    for ( $i = 0, $len = count( $this->_plans ); $i < $len; $i ++ ) {
                        if ( $this->_plans[ $i ] instanceof FS_Plugin_Plan ) {
                            $this->_plans[ $i ] = self::decrypt_entity( $this->_plans[ $i ] );
                        } else {
                            unset( $this->_plans[ $i ] );
                        }
                    }
                }

                $this->_license = $this->_get_license_by_id( $this->_site->license_id );

                if ( $this->_site->version != $this->get_plugin_version() ) {
                    // If stored install version is different than current installed plugin version,
                    // then update plugin version event.
                    $this->update_plugin_version_event();
                }
            }

            if ( true === $this->_storage->require_license_activation &&
                ! fs_request_get_bool( 'require_license', true )
            ) {
                $this->_storage->require_license_activation = false;
            }

            if ( $this->is_theme() ) {
                $this->_register_account_hooks();
            }

            if ( $this->is_user_in_admin() && $this->is_clone() ) {
                if ( empty( FS_Clone_Manager::instance()->get_clone_identification_timestamp() ) ) {
                    FS_Clone_Manager::instance()->store_clone_identification_timestamp();
                }

                FS_Clone_Manager::instance()->maybe_update_clone_resolution_support_flag( $this->_storage->sdk_last_version );
                $this->send_pending_clone_update_once();
            }
        }

        /**
         * Special user recovery mechanism.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param number|null $site_user_id
         *
         * @return \FS_User|mixed
         */
        private function sync_user_by_current_install( $site_user_id = null ) {
            $site_user_id = FS_Site::is_valid_id( $site_user_id ) ?
                $site_user_id :
                $this->_site->user_id;

            $api = $this->get_api_site_scope();

            $uid          = $this->get_anonymous_id();
            $request_path = "/users/{$site_user_id}.json?uid={$uid}";

            $result = $api->get( $request_path, false, WP_FS__TIME_10_MIN_IN_SEC );

            if ( $this->is_api_result_entity( $result ) ) {
                $user        = new FS_User( $result );
                $this->_user = $user;
                $this->_store_user();

                return $user;
            }

            $error_code = FS_Api::get_error_code( $result );

            if ( in_array( $error_code, array( 'invalid_unique_id', 'user_cannot_be_recovered' ) ) ) {
                /**
                 * Those API errors will continue coming and are not recoverable with the
                 * current site's data. Therefore, extend the API call's cached result to 7 days.
                 */
                $api->update_cache_expiration( $request_path, WP_FS__TIME_WEEK_IN_SEC );
            }

            return $result;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         *
         * @param FS_User    $user
         * @param FS_Site    $site
         * @param bool|array $plans
         */
        private function _set_account( FS_User $user, FS_Site $site, $plans = false ) {
            $site->user_id = $user->id;

            $this->_site = $site;
            $this->_user = $user;
            if ( false !== $plans ) {
                $this->_plans = $plans;
            }

            $this->send_install_update();

            $this->_store_account();

        }

        /**
         * Get a sanitized array with the WordPress version, SDK version, and PHP version.
         * Each version is trimmed after the 16th char.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.2.1
         *
         * @return array
         */
        private function get_versions() {
            $versions = array();
            $versions['sdk_version'] = $this->version;

            // Collect these diagnostic information only if it's allowed.
            if ( FS_Permission_Manager::instance( $this )->is_diagnostic_tracking_allowed() ) {
                $versions['platform_version']             = get_bloginfo( 'version' );
                $versions['programming_language_version'] = phpversion();
            }

            foreach ( $versions as $k => $version ) {
                $versions[ $k ] = self::get_api_sanitized_property( $k, $version );
            }

            return $versions;
        }

        /**
         * Get sanitized site language.
         *
         * @param string $language
         * @param int    $max_len
         *
         * @since  2.5.1
         * @author Vova Feldman (@svovaf)
         *
         * @return string
         */
        private static function get_sanitized_language( $language = '', $max_len = self::LANGUAGE_MAX_CHARS ) {
            if ( empty( $language ) ) {
                $language = get_bloginfo( 'language' );
            }

            return substr( $language, 0, $max_len );
        }

        /**
         * Get core version stripped from pre-release and build.
         *
         * @since  2.5.1
         * @author Vova Feldman (@svovaf)
         *
         * @param string $version
         * @param int    $parts
         * @param int    $max_len
         * @param bool   $include_pre_release
         *
         * @return string
         */
        private static function get_core_version(
            $version,
            $parts = 3,
            $max_len = self::VERSION_MAX_CHARS,
            $include_pre_release = false
        ) {
            if ( empty( $version ) ) {
                // Version is empty.
                return '';
            }

            if ( is_numeric( $version ) ) {
                $is_float_version = is_float( $version );

                $version = (string) $version;

                /**
                 * Casting a whole float number to a string cuts the decimal point. This part make sure to add the missing decimal part to the version.
                 */
                if ( $is_float_version && false === strpos( $version, '.' ) ) {
                    $version .= '.0';
                }
            }

            if ( ! is_string( $version ) ) {
                return '';
            }

            if ( $parts < 1 ) {
                return '';
            }

            $pre_release_regex = $include_pre_release ?
                '(\-(alpha|beta|RC)([0-9]+)?)?' :
                '';

            if ( 0 === preg_match( '/^([0-9]+(\.[0-9]+){0,' . ( $parts - 1 ) . '}' . $pre_release_regex . ')/i', $version, $matches ) ) {
                // Version is not starting with a digit.
                return '';
            }

            return substr( $matches[1], 0, $max_len );
        }

        /**
         * @param string $prop
         * @param mixed  $val
         *
         * @return mixed
         *@author Vova Feldman (@svovaf)
         *
         * @since  2.5.1
         */
        private static function get_api_sanitized_property( $prop, $val ) {
            if ( ! is_string( $val ) || empty( $val ) ) {
                return $val;
            }

            switch ( $prop ) {
                case 'programming_language_version':
                    // Get core PHP version, which can have up to 3 parts (ignore pre-releases).
                    return self::get_core_version( $val );
                case 'platform_version':
                    // Get the exact WordPress version, which can have up to 3 parts (including pre-releases).
                    return self::get_core_version( $val, 3, self::VERSION_MAX_CHARS, true );
                case 'sdk_version':
                    // Get the exact SDK version, which can have up to 4 parts.
                    return self::get_core_version( $val, 4 );
                case 'version':
                    // Get the entire version but just limited in length.
                    return substr( $val, 0, self::VERSION_MAX_CHARS );
                case 'language':
                    return self::get_sanitized_language( $val );
                default:
                    return $val;
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @return bool
         */
        function has_beta_update() {
            return (
                ! empty( $this->_storage->beta_data ) &&
                ( true === $this->_storage->beta_data['is_beta'] ) &&
                version_compare( $this->_storage->beta_data['version'], $this->get_plugin_version(), '>' )
            );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @return bool
         */
        function is_beta() {
            return (
                ! empty( $this->_storage->beta_data ) &&
                ( true === $this->_storage->beta_data['is_beta'] ) &&
                ( $this->get_plugin_version() === $this->_storage->beta_data['version'] )
            );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.4
         *
         * @param array         $override_with
         * @param bool|int|null $network_level_or_blog_id If true, return params for network level opt-in. If integer, get params for specified blog in the network.
         *
         * @return array
         */
        function get_opt_in_params( $override_with = array(), $network_level_or_blog_id = null ) {
            $this->_logger->entrance();

            $current_user = self::_get_current_wp_user();

            $activation_action = $this->get_unique_affix() . '_activate_new';
            $return_url        = $this->is_anonymous() ?
                // If skipped already, then return to the account page.
                $this->get_account_url( $activation_action, array(), false ) :
                // Return to the module's main page.
                $this->get_after_activation_url( 'after_connect_url', array( 'fs_action' => $activation_action ) );

            $versions = $this->get_versions();

            $params = array_merge( $versions, array(
                'user_firstname'    => $current_user->user_firstname,
                'user_lastname'     => $current_user->user_lastname,
                'user_email'        => $current_user->user_email,
                'plugin_slug'       => $this->_slug,
                'plugin_id'         => $this->get_id(),
                'plugin_public_key' => $this->get_public_key(),
                'plugin_version'    => $this->get_plugin_version(),
                'return_url'        => fs_nonce_url( $return_url, $activation_action ),
                'account_url'       => fs_nonce_url( $this->_get_admin_page_url(
                    'account',
                    array( 'fs_action' => 'sync_user' )
                ), 'sync_user' ),
                'is_premium'        => $this->is_premium(),
                'is_active'         => true,
                'is_uninstalled'    => false,
                'is_localhost'      => WP_FS__IS_LOCALHOST,
            ) );

            if ( $this->is_addon() ) {
                $parent_fs = $this->get_parent_instance();

                $params['parent_plugin_slug'] = $parent_fs->_slug;
                $params['parent_plugin_id']   = $parent_fs->get_id();
            }

            if ( true === $network_level_or_blog_id ) {
                if ( ! isset( $override_with['sites'] ) ) {
                    $params['sites'] = $this->get_sites_for_network_level_optin();
                }
            } else {
                $site = is_numeric( $network_level_or_blog_id ) ?
                    array( 'blog_id' => $network_level_or_blog_id ) :
                    null;

                $site = $this->get_site_info( $site );

                $diagnostic_info = array();
                if ( FS_Permission_Manager::instance( $this )->is_diagnostic_tracking_allowed() ) {
                    $diagnostic_info = array(
                        'site_name' => $site['title'],
                        'language'  => self::get_sanitized_language( $site['language'] ),
                    );
                }

                $params = array_merge( $params, $diagnostic_info, array(
                    'site_uid'  => $site['uid'],
                    'site_url'  => $site['url'],
                ) );
            }

            if ( $this->is_pending_activation() &&
                 ! empty( $this->_storage->pending_license_key )
            ) {
                $params['license_key'] = $this->_storage->pending_license_key;
            }

            if ( WP_FS__SKIP_EMAIL_ACTIVATION && $this->has_secret_key() ) {
                // Even though rand() is known for its security issues,
                // the timestamp adds another layer of protection.
                // It would be very hard for an attacker to get the secret key form here.
                // Plus, this should never run in production since the secret should never
                // be included in the production version.
                $params['ts']     = WP_FS__SCRIPT_START_TIME;
                $params['salt']   = md5( uniqid( rand() ) );
                $params['secure'] = md5(
                    $params['ts'] .
                    $params['salt'] .
                    $this->get_secret_key()
                );
            }

            if ( is_multisite() && function_exists( 'get_network' ) ) {
                $params['network_uid'] = $this->get_anonymous_network_id();
            }

            return array_merge( $params, $override_with );
        }

        /**
         * 1. If successful opt-in or pending activation returns the next page that the user should be redirected to.
         * 2. If there was an API error, return the API result.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.4
         *
         * @param string|bool $email
         * @param string|bool $first
         * @param string|bool $last
         * @param string|bool $license_key
         * @param bool        $is_uninstall         If "true", this means that the module is currently being uninstalled.
         *                                          In this case, the user and site info will be sent to the server but no
         *                                          data will be saved to the WP installation's database.
         * @param number|bool $trial_plan_id
         * @param bool        $is_disconnected      Whether to opt in without tracking.
         * @param null|bool   $is_marketing_allowed
         * @param array       $sites                If network-level opt-in, an array of containing details of sites.
         * @param bool        $redirect
         *
         * @return string|object
         * @use    WP_Error
         */
        function opt_in(
            $email = false,
            $first = false,
            $last = false,
            $license_key = false,
            $is_uninstall = false,
            $trial_plan_id = false,
            $is_disconnected = false,
            $is_marketing_allowed = null,
            $sites = array(),
            $redirect = true
        ) {
            $this->_logger->entrance();

            if ( false === $email ) {
                $current_user = self::_get_current_wp_user();
                $email        = $current_user->user_email;
            }

            /**
             * @since 1.2.1 If activating with license key, ignore the context-user
             *              since the user will be automatically loaded from the license.
             */
            if ( empty( $license_key ) ) {
                // Clean up pending license if opt-ing in again.
                $this->_storage->remove( 'pending_license_key' );

                if ( ! $is_uninstall ) {
                    $fs_user = Freemius::_get_user_by_email( $email );
                    if ( is_object( $fs_user ) && ! $this->is_pending_activation() ) {
                        return $this->install_with_user(
                            $fs_user,
                            false,
                            $trial_plan_id,
                            $redirect,
                            true,
                            $sites
                        );
                    }
                }
            }

            $user_info = array();
            if ( ! empty( $email ) ) {
                $user_info['user_email'] = $email;
            }
            if ( ! empty( $first ) ) {
                $user_info['user_firstname'] = $first;
            }
            if ( ! empty( $last ) ) {
                $user_info['user_lastname'] = $last;
            }

            if ( ! empty( $sites ) ) {
                $is_network = true;

                $user_info['sites'] = $sites;
            } else {
                $is_network = false;
            }

            $params = $this->get_opt_in_params( $user_info, $is_network );

            $filtered_license_key = false;
            if ( is_string( $license_key ) ) {
                $filtered_license_key  = $this->apply_filters( 'license_key', $license_key );
                $params['license_key'] = $filtered_license_key;
            } else if ( FS_Plugin_Plan::is_valid_id( $trial_plan_id ) ) {
                $params['trial_plan_id'] = $trial_plan_id;
            }

            if ( $is_uninstall ) {
                $params['uninstall_params'] = array(
                    'reason_id'   => $this->_storage->uninstall_reason->id,
                    'reason_info' => $this->_storage->uninstall_reason->info
                );
            }

            if ( isset( $params['license_key'] ) ) {
                $fs_user = Freemius::_get_user_by_email( $email );

                if ( is_object( $fs_user ) ) {
                    /**
                     * If opting in with a context license and the context WP Admin user already opted in
                     * before from the current site, add the user context security params to avoid the
                     * unnecessary email activation when the context license is owned by the same context user.
                     *
                     * @author Leo Fajardo (@leorw)
                     * @since  1.2.3
                     */
                    $params = array_merge( $params, FS_Security::instance()->get_context_params(
                        $fs_user,
                        false,
                        'install_with_existing_user'
                    ) );
                }
            }

            if ( is_bool( $is_marketing_allowed ) ) {
                $params['is_marketing_allowed'] = $is_marketing_allowed;
            }

            $params['is_disconnected']                = $is_disconnected;
            $params['format']                         = 'json';
            $params['is_extensions_tracking_allowed'] = FS_Permission_Manager::instance( $this )->is_extensions_tracking_allowed();
            $params['is_diagnostic_tracking_allowed'] = FS_Permission_Manager::instance( $this )->is_diagnostic_tracking_allowed();

            $request = array(
                'method'  => 'POST',
                'body'    => $params,
                'timeout' => 60,
            );

            $url = $this->add_show_pending( WP_FS__ADDRESS . '/action/service/user/install/' );
            $response = self::safe_remote_post( $url, $request );

            if ( is_wp_error( $response ) ) {
                /**
                 * @var WP_Error $response
                 */
                $result = new stdClass();

                $error_code = $response->get_error_code();
                $error_type = str_replace( ' ', '', ucwords( str_replace( '_', ' ', $error_code ) ) );

                $result->error = (object) array(
                    'type'    => $error_type,
                    'message' => $response->get_error_message(),
                    'code'    => $error_code,
                    'http'    => 402
                );

                $this->maybe_modify_api_curl_error_message( $result );

                if ( FS_Api::is_blocked( $result ) ) {
                    $result->error->message = $this->generate_api_blocked_notice_message_from_result( $result );
                }

                $is_connected = null;

                if ( empty( $license_key ) && $this->is_enable_anonymous() ) {
                    $this->skip_connection( fs_is_network_admin() );

                    $is_connected = ( ! FS_Api::is_blocked( $result ) );
                }

                $this->update_connectivity_info( $is_connected );

                return $result;
            }

            $this->update_connectivity_info( true );

            // Module is being uninstalled, don't handle the returned data.
            if ( $is_uninstall ) {
                return true;
            }

            /**
             * When json_decode() executed on PHP 5.2 with an invalid JSON, it will throw a PHP warning. Unfortunately, the new Theme Check doesn't allow PHP silencing and the theme review team isn't open to change that, therefore, instead of using `@json_decode()` we had to use the method without the `@` directive.
             *
             * @author Vova Feldman (@svovaf)
             * @since  1.2.3
             * @link   https://themes.trac.wordpress.org/ticket/46134#comment:5
             * @link   https://themes.trac.wordpress.org/ticket/46134#comment:9
             * @link   https://themes.trac.wordpress.org/ticket/46134#comment:12
             * @link   https://themes.trac.wordpress.org/ticket/46134#comment:14
             */
            $decoded = is_string( $response['body'] ) ?
                json_decode( $response['body'] ) :
                null;

            if ( empty( $decoded ) ) {
                return false;
            }

            if ( ! $this->is_api_result_object( $decoded ) ) {
                if ( ! empty( $params['license_key'] ) ) {
                    // Pass the fully entered license key to the failure handler.
                    $params['license_key'] = $license_key;
                }

                return $is_uninstall ?
                    $decoded :
                    $this->apply_filters( 'after_install_failure', $decoded, $params );
            } else if ( isset( $decoded->pending_activation ) && $decoded->pending_activation ) {
                if ( $is_network ) {
                    $site_ids = array();
                    foreach ( $sites as $site ) {
                        $site_ids[] = $site['blog_id'];
                    }

                    /**
                     * Store the sites so that they can be installed once the user has clicked on the activation link
                     * in the email.
                     *
                     * @author Leo Fajardo (@leorw)
                     */
                    $this->_storage->pending_sites_info = array(
                        'blog_ids'      => $site_ids,
                        'license_key'   => $license_key,
                        'trial_plan_id' => $trial_plan_id
                    );
                }

                // Pending activation, add message.
                return $this->set_pending_confirmation(
                    ( isset( $decoded->email ) ?
                        $decoded->email :
                        true ),
                    false,
                    $filtered_license_key,
                    ! empty( $params['trial_plan_id'] ),
                    isset( $decoded->is_suspicious_email ) && $decoded->is_suspicious_email
                );
            } else if ( isset( $decoded->install_secret_key ) ) {
                return $this->install_with_new_user(
                    $decoded->user_id,
                    $decoded->user_public_key,
                    $decoded->user_secret_key,
                    ( isset( $decoded->is_marketing_allowed ) && ! is_null( $decoded->is_marketing_allowed ) ?
                        $decoded->is_marketing_allowed :
                        null ),
                    ( isset( $decoded->is_extensions_tracking_allowed ) && ! is_null( $decoded->is_extensions_tracking_allowed ) ?
                        $decoded->is_extensions_tracking_allowed :
                        null ),
                    ( isset( $decoded->is_diagnostic_tracking_allowed ) && ! is_null( $decoded->is_diagnostic_tracking_allowed ) ?
                        $decoded->is_diagnostic_tracking_allowed :
                        null ),
                    $decoded->install_id,
                    $decoded->install_public_key,
                    $decoded->install_secret_key,
                    false
                );
            } else if ( is_array( $decoded->installs ) ) {
                return $this->install_many_with_new_user(
                    $decoded->user_id,
                    $decoded->user_public_key,
                    $decoded->user_secret_key,
                    ( isset( $decoded->is_marketing_allowed ) && ! is_null( $decoded->is_marketing_allowed ) ?
                        $decoded->is_marketing_allowed :
                        null ),
                    ( isset( $decoded->is_extensions_tracking_allowed ) && ! is_null( $decoded->is_extensions_tracking_allowed ) ?
                        $decoded->is_extensions_tracking_allowed :
                        null ),
                    ( isset( $decoded->is_diagnostic_tracking_allowed ) && ! is_null( $decoded->is_diagnostic_tracking_allowed ) ?
                        $decoded->is_diagnostic_tracking_allowed :
                        null ),
                    $decoded->installs,
                    false
                );
            }

            return $decoded;
        }

        /**
         * Set user and site identities.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @param FS_User $user
         * @param FS_Site $site
         * @param bool    $redirect
         * @param bool    $auto_install Since 1.2.1.7 If `true` and setting up an account with a valid license, will
         *                              redirect (or return a URL) to the account page with a special parameter to
         *                              trigger the auto installation processes.
         *
         * @return string If redirect is `false`, returns the next page the user should be redirected to.
         */
        function setup_account(
            FS_User $user,
            FS_Site $site,
            $redirect = true,
            $auto_install = false
        ) {
            return $this->setup_network_account(
                $user,
                array( $site ),
                $redirect,
                $auto_install,
                false
            );
        }

        /**
         * Set user and site identities.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param FS_User   $user
         * @param FS_Site[] $installs
         * @param bool      $redirect
         * @param bool      $auto_install Since 1.2.1.7 If `true` and setting up an account with a valid license, will redirect (or return a URL) to the account page with a special parameter to trigger the auto installation processes.
         * @param bool      $is_network_level_opt_in
         *
         * @return string If redirect is `false`, returns the next page the user should be redirected to.
         */
        function setup_network_account(
            FS_User $user,
            array $installs,
            $redirect = true,
            $auto_install = false,
            $is_network_level_opt_in = true
        ) {
            $first_install = $installs[0];

            $this->_user = $user;
            $this->_site = $first_install;

            $this->_sync_plans();

            if ( $this->_storage->handle_gdpr_admin_notice &&
                 $this->should_handle_gdpr_admin_notice() &&
                 FS_GDPR_Manager::instance()->should_show_opt_in_notice()
            ) {
                /**
                 * Clear user lock after an opt-in.
                 */
                require_once WP_FS__DIR_INCLUDES . '/class-fs-user-lock.php';
                FS_User_Lock::instance()->unlock();
            }

            if ( 1 < count( $installs ) ) {
                // Only network level opt-in can have more than one install.
                $is_network_level_opt_in = true;
            }

            $this->update_connectivity_info( true );

//            $is_network_level_opt_in = self::is_ajax_action_static( 'network_activate', $this->_module_id );
            // If Freemius was OFF before, turn it on.
            $this->turn_on();

            $this->handle_account_connection(
                $installs,
                ( ! $this->_is_network_active || ! $is_network_level_opt_in )
            );

            if ( is_numeric( $first_install->license_id ) ) {
                $this->set_license( $this->_get_license_by_id( $first_install->license_id ) );
            }

            $this->_admin_notices->remove_sticky( 'connect_account' );

            if ( $this->is_pending_activation() || ! $this->has_settings_menu() ) {
                $this->clear_pending_activation_mode();

                if ( ! $this->is_paying_or_trial() ) {
                    $this->_admin_notices->add_sticky(
                        sprintf( $this->get_text_inline( '%s opt-in was successfully completed.', 'plugin-x-activation-message' ), '<b>' . $this->get_plugin_name() . '</b>' ),
                        'activation_complete'
                    );
                }
            }

            if ( $this->is_paying_or_trial() ) {
                if ( ! $this->is_premium() ||
                     ! $this->has_premium_version() ||
                     ! $this->has_settings_menu()
                ) {
                    if ( $this->is_paying() ) {
                        $this->add_complete_upgrade_instructions_notice(
                            sprintf(
                                $this->get_text_inline( 'Your account was successfully activated with the %s plan.', 'activation-with-plan-x-message' ),
                                $this->get_plan_title()
                            ),
                            'plan_upgraded'
                        );
                    } else {
                        $trial_plan = $this->get_trial_plan();

                        $this->add_complete_upgrade_instructions_notice(
                            sprintf(
                                $this->get_text_inline( 'Your trial has been successfully started.', 'trial-started-message' ),
                                '<i>' . $this->get_plugin_name() . '</i>'
                            ),
                            'trial_started',
                            $trial_plan->title
                        );
                    }
                }

                $this->_admin_notices->remove_sticky( array(
                    'trial_promotion',
                ) );
            }

            $plugin_id = fs_request_get( 'plugin_id', false );

            // Store activation time ONLY for plugins & themes (not add-ons).
            if ( ! is_numeric( $plugin_id ) || ( $plugin_id == $this->_plugin->id ) ) {
                if ( empty( $this->_storage->activation_timestamp ) ) {
                    $this->_storage->activation_timestamp = WP_FS__SCRIPT_START_TIME;
                }
            }

            $next_page = '';

            $extra = array();
            if ( $auto_install ) {
                $extra['auto_install'] = 'true';
            }

            if ( is_numeric( $plugin_id ) ) {
                /**
                 * @author Leo Fajardo (@leorw)
                 * @since  1.2.1.6
                 *
                 * Also sync the license after an anonymous user subscribes.
                 */
                if ( $this->is_anonymous() || $plugin_id != $this->_plugin->id ) {
                    // Add-on was installed - sync license right after install.
                    $next_page = $this->_get_sync_license_url( $plugin_id, true, $extra );
                }
            } else {
                /**
                 * @author Vova Feldman (@svovaf)
                 * @since  1.1.9 If site installed with a valid license, sync license.
                 */
                if ( $this->is_paying() ) {
                    $this->_sync_plugin_license(
                        true,
                        // Installs data is already synced in the beginning of this method directly or via _set_account().
                        false
                    );
                }

                // Reload the page with the keys.
                $next_page = $this->is_anonymous() ?
                    // If user previously skipped, redirect to account page.
                    $this->get_account_url( false, $extra ) :
                    $this->get_after_activation_url( 'after_connect_url', array(), $is_network_level_opt_in );
            }

            if ( ! empty( $next_page ) && $redirect ) {
                fs_redirect( $next_page );
            }

            return $next_page;
        }

        /**
         * Install plugin with new user information after approval.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.7
         */
        function _install_with_new_user() {
            $this->_logger->entrance();

            if ( $this->is_registered() ) {
                return;
            }

            $has_pending_activation_confirmation_param = fs_request_has( 'pending_activation' );

            $this->update_license_required_permissions_if_anonymous();

            if ( ( $this->is_plugin() && fs_request_is_action( $this->get_unique_affix() . '_activate_new' ) ) ||
                 // @todo This logic should be improved because it's executed on every load of a theme.
                 $this->is_theme()
            ) {
//				check_admin_referer( $this->_slug . '_activate_new' );

                if ( fs_request_has( 'user_secret_key' ) ) {
                    if ( fs_is_network_admin() && isset( $this->_storage->pending_sites_info ) ) {
                        $pending_sites_info = $this->_storage->pending_sites_info;

                        $this->install_many_pending_with_user(
                            fs_request_get( 'user_id' ),
                            fs_request_get_raw( 'user_public_key' ),
                            fs_request_get_raw( 'user_secret_key' ),
                            fs_request_get_bool( 'is_marketing_allowed', null ),
                            fs_request_get_bool( 'is_extensions_tracking_allowed', null ),
                            fs_request_get_bool( 'is_diagnostic_tracking_allowed', null ),
                            $pending_sites_info['blog_ids'],
                            $pending_sites_info['license_key'],
                            $pending_sites_info['trial_plan_id']
                        );
                    } else {
                        $this->install_with_new_user(
                            fs_request_get( 'user_id' ),
                            fs_request_get_raw( 'user_public_key' ),
                            fs_request_get_raw( 'user_secret_key' ),
                            fs_request_get_bool( 'is_marketing_allowed', null ),
                            fs_request_get_bool( 'is_extensions_tracking_allowed', null ),
                            fs_request_get_bool( 'is_diagnostic_tracking_allowed', null ),
                            fs_request_get( 'install_id' ),
                            fs_request_get_raw( 'install_public_key' ),
                            fs_request_get_raw( 'install_secret_key' ),
                            true,
                            fs_request_get_bool( 'auto_install' )
                        );
                    }
                } else if ( $has_pending_activation_confirmation_param ) {
                    $this->set_pending_confirmation(
                        fs_request_get( 'user_email' ),
                        true,
                        false,
                        false,
                        fs_request_get_bool( 'is_suspicious_email' ),
                        fs_request_get_bool( 'has_upgrade_context' ),
                        fs_request_get( 'support_email_address' )
                    );
                }
            }
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param number $id
         * @param string $public_key
         * @param string $secret_key
         *
         * @return \FS_User
         */
        private function setup_user( $id, $public_key, $secret_key ) {
            $user = self::_get_user_by_id( $id );

            if ( is_object( $user ) ) {
                $this->_user = $user;
            } else {
                $user             = new FS_User();
                $user->id         = $id;
                $user->public_key = $public_key;
                $user->secret_key = $secret_key;

                $this->_user = $user;
                $user_result = $this->get_api_user_scope()->get();
                $user        = new FS_User( $user_result );

                $this->_user = $user;
                $this->_store_user();
            }

            return $user;
        }

        /**
         * Install plugin with new user.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.4
         *
         * @param number    $user_id
         * @param string    $user_public_key
         * @param string    $user_secret_key
         * @param bool|null $is_marketing_allowed
         * @param bool|null $is_extensions_tracking_allowed Since 2.3.2
         * @param bool|null $is_diagnostic_tracking_allowed Since 2.5.0.2
         * @param number    $install_id
         * @param string    $install_public_key
         * @param string    $install_secret_key
         * @param bool      $redirect
         * @param bool      $auto_install                   Since 1.2.1.7 If `true` and setting up an account with a valid license, will redirect (or return a URL) to the account page with a special parameter to trigger the auto installation processes.
         *
         * @return string If redirect is `false`, returns the next page the user should be redirected to.
         */
        private function install_with_new_user(
            $user_id,
            $user_public_key,
            $user_secret_key,
            $is_marketing_allowed,
            $is_extensions_tracking_allowed,
            $is_diagnostic_tracking_allowed,
            $install_id,
            $install_public_key,
            $install_secret_key,
            $redirect = true,
            $auto_install = false
        ) {
            /**
             * This method is also executed after opting in with a license key since the
             * license can be potentially associated with a different owner.
             *
             * @since 2.0.0
             */
            $user = self::_get_user_by_id( $user_id );

            if ( ! is_object( $user ) ) {
                $user             = new FS_User();
                $user->id         = $user_id;
                $user->public_key = $user_public_key;
                $user->secret_key = $user_secret_key;

                $this->_user = $user;
                $user_result = $this->get_api_user_scope()->get();
                $user        = new FS_User( $user_result );
            }

            $this->_user = $user;

            $site             = new FS_Site();
            $site->id         = $install_id;
            $site->public_key = $install_public_key;
            $site->secret_key = $install_secret_key;

            $this->_site = $site;
            $site_result = $this->get_api_site_scope( true )->get();
            $site        = new FS_Site( $site_result );
            $this->_site = $site;

            if ( ! is_null( $is_marketing_allowed ) ) {
                $this->disable_opt_in_notice_and_lock_user();
            }

            FS_Permission_Manager::instance( $this )->update_permissions_tracking_flag( array(
                FS_Permission_Manager::PERMISSION_DIAGNOSTIC => $is_diagnostic_tracking_allowed,
                FS_Permission_Manager::PERMISSION_EXTENSIONS => $is_extensions_tracking_allowed,
            ) );

            return $this->setup_account(
                $this->_user,
                $this->_site,
                $redirect,
                $auto_install
            );
        }

        /**
         * Install plugin with user.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.0.0
         *
         * @param number    $user_id
         * @param string    $user_public_key
         * @param string    $user_secret_key
         * @param bool|null $is_marketing_allowed
         * @param bool|null $is_extensions_tracking_allowed Since 2.3.2
         * @param bool|null $is_diagnostic_tracking_allowed Since 2.5.0.2
         * @param array     $site_ids
         * @param bool      $license_key
         * @param bool      $trial_plan_id
         * @param bool      $redirect
         *
         * @return void
         */
        private function install_many_pending_with_user(
            $user_id,
            $user_public_key,
            $user_secret_key,
            $is_marketing_allowed,
            $is_extensions_tracking_allowed,
            $is_diagnostic_tracking_allowed,
            $site_ids,
            $license_key = false,
            $trial_plan_id = false,
            $redirect = true
        ) {
            $user = $this->setup_user( $user_id, $user_public_key, $user_secret_key );

            if ( ! is_null( $is_marketing_allowed ) ) {
                $this->disable_opt_in_notice_and_lock_user();
            }

            FS_Permission_Manager::instance( $this )->update_permissions_tracking_flag( array(
                FS_Permission_Manager::PERMISSION_DIAGNOSTIC => $is_diagnostic_tracking_allowed,
                FS_Permission_Manager::PERMISSION_EXTENSIONS => $is_extensions_tracking_allowed,
            ) );

            $sites = array();
            foreach ( $site_ids as $site_id ) {
                $sites[] = $this->get_site_info( array( 'blog_id' => $site_id ) );
            }

            $this->install_with_user( $user, $license_key, $trial_plan_id, $redirect, true, $sites );
        }

        /**
         * Multi-site install with a new user.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param number    $user_id
         * @param string    $user_public_key
         * @param string    $user_secret_key
         * @param bool|null $is_marketing_allowed
         * @param bool|null $is_extensions_tracking_allowed Since 2.3.2
         * @param bool|null $is_diagnostic_tracking_allowed Since 2.5.0.2
         * @param object[]  $installs
         * @param bool      $redirect
         * @param bool      $auto_install                   Since 1.2.1.7 If `true` and setting up an account with a valid license, will redirect (or return a URL) to the account page with a special parameter to trigger the auto installation processes.
         *
         * @return string If redirect is `false`, returns the next page the user should be redirected to.
         */
        private function install_many_with_new_user(
            $user_id,
            $user_public_key,
            $user_secret_key,
            $is_marketing_allowed,
            $is_extensions_tracking_allowed,
            $is_diagnostic_tracking_allowed,
            array $installs,
            $redirect = true,
            $auto_install = false
        ) {
            $this->setup_user( $user_id, $user_public_key, $user_secret_key );

            if ( ! is_null( $is_marketing_allowed ) ) {
                $this->disable_opt_in_notice_and_lock_user();
            }

            FS_Permission_Manager::instance( $this )->update_permissions_tracking_flag( array(
                FS_Permission_Manager::PERMISSION_DIAGNOSTIC => $is_diagnostic_tracking_allowed,
                FS_Permission_Manager::PERMISSION_EXTENSIONS => $is_extensions_tracking_allowed,
            ) );

            $install_ids = array();

            foreach ( $installs as $install ) {
                $install_ids[] = $install->id;
            }

            $items_per_request = 25;
            $left              = count( $install_ids );
            $offset            = 0;

            $installs = array();
            while ( $left > 0 ) {
                $result = $this->get_api_user_scope()->get( "/plugins/{$this->_module_id}/installs.json?ids=" . implode( ',', array_slice( $install_ids, $offset, $items_per_request ) ) );

                if ( ! $this->is_api_result_object( $result, 'installs' ) ) {
                    // @todo Handle API error.
                }

                $installs = array_merge( $installs, $result->installs );

                $left   -= $items_per_request;
                $offset += $items_per_request;
            }

            foreach ( $installs as &$install ) {
                $install = new FS_Site( $install );
            }

            return $this->setup_network_account(
                $this->_user,
                $installs,
                $redirect,
                $auto_install
            );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.4
         *
         * @param string|bool $email
         * @param bool        $redirect
         * @param string|bool $license_key      Since 1.2.1.5
         * @param bool        $is_pending_trial Since 1.2.1.5
         * @param bool        $is_suspicious_email Since 2.5.0
         * @param bool        $has_upgrade_context Since 2.5.3
         * @param bool|string $support_email_address Since 2.5.3
         *
         * @return string Since 1.2.1.5 if $redirect is `false`, return the pending activation page.
         */
        private function set_pending_confirmation(
            $email = false,
            $redirect = true,
            $license_key = false,
            $is_pending_trial = false,
            $is_suspicious_email = false,
            $has_upgrade_context = false,
            $support_email_address = false
        ) {
            $is_network_admin = fs_is_network_admin();

            if ( $this->_ignore_pending_mode && ! $has_upgrade_context ) {
                /**
                 * If explicitly asked to ignore pending mode, set to anonymous mode
                 * if require confirmation before finalizing the opt-in except after completing a purchase (otherwise, in this case, they wouldn't see any notice telling them that they should receive their license key via email).
                 *
                 * @author Vova Feldman
                 * @since  1.2.1.6
                 */
                $this->skip_connection( $is_network_admin );
            } else {
                // Install must be activated via email since
                // user with the same email already exist.
                $this->_storage->is_pending_activation = true;
                $this->_add_pending_activation_notice(
                    $email,
                    $is_pending_trial,
                    $is_suspicious_email,
                    $has_upgrade_context,
                    $support_email_address
                );
            }

            if ( ! empty( $license_key ) ) {
                $this->_storage->pending_license_key = $license_key;
            }

            // Remove the opt-in sticky notice.
            $this->_admin_notices->remove_sticky( array(
                'connect_account',
                'trial_promotion',
            ) );

            $next_page = $this->get_after_activation_url( 'after_pending_connect_url' );

            if ( $redirect ) {
                // Reload the page with a pending activation message.
                fs_redirect( $next_page );
            }

            return $next_page;
        }

        /**
         * Install plugin with current logged WP user info.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.7
         */
        function _install_with_current_user() {
            $this->_logger->entrance();

            if ( $this->is_registered() ) {
                return;
            }

            if ( fs_request_is_action( $this->get_unique_affix() . '_activate_existing' ) && fs_request_is_post() ) {
				check_admin_referer( $this->get_unique_affix() . '_activate_existing' );

                /**
                 * @author Vova Feldman (@svovaf)
                 * @since  1.1.9 Add license key if given.
                 */
                $license_key = fs_request_get_raw( 'license_secret_key' );

                FS_Permission_Manager::instance( $this )->update_permissions_tracking_flag( array(
                    FS_Permission_Manager::PERMISSION_DIAGNOSTIC => fs_request_get_bool( 'is_diagnostic_tracking_allowed', null ),
                    FS_Permission_Manager::PERMISSION_EXTENSIONS => fs_request_get_bool( 'is_extensions_tracking_allowed', null ),
                ) );

                $this->install_with_current_user( $license_key );
            }
        }


        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.4
         *
         * @param string|bool $license_key
         * @param number|bool $trial_plan_id
         * @param array       $sites Since 2.0.0
         * @param bool        $redirect
         *
         * @return object|string If redirect is `false`, returns the next page the user should be redirected to, or the API error object if failed to install.
         */
        function install_with_current_user(
            $license_key = false,
            $trial_plan_id = false,
            $sites = array(),
            $redirect = true
        ) {
            // Get current logged WP user.
            $current_user = self::_get_current_wp_user();

            // Find the relevant FS user by the email.
            $user = self::_get_user_by_email( $current_user->user_email );

            return $this->install_with_user( $user, $license_key, $trial_plan_id, $redirect, true, $sites );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param \FS_User    $user
         * @param string|bool $license_key
         * @param number|bool $trial_plan_id
         * @param bool        $redirect
         * @param bool        $setup_account Since 2.0.0. When set to FALSE, executes a light installation without setting up the account as if it's the first opt-in.
         * @param array       $sites         Since 2.0.0. If not empty, should be a collection of site details for the bulk install API request.
         *
         * @return \FS_Site|object|string If redirect is `false`, returns the next page the user should be redirected to, or the API error object if failed to install. If $setup_account is set to `false`, return the newly created install.
         */
        function install_with_user(
            FS_User $user,
            $license_key = false,
            $trial_plan_id = false,
            $redirect = true,
            $setup_account = true,
            $sites = array()
        ) {
            // We have to set the user before getting user scope API handler.
            $this->_user = $user;

            // Install the plugin.
            $result = $this->create_installs_with_user(
                $user,
                $license_key,
                $trial_plan_id,
                $sites,
                $redirect
            );

            if ( ! $this->is_api_result_entity( $result ) &&
                 ! $this->is_api_result_object( $result, 'installs' )
            ) {
                // @todo Handler potential API error of the $result
            }

            if ( empty( $sites ) ) {
                $site        = new FS_Site( $result );
                $this->_site = $site;

                if ( ! $setup_account ) {
                    $this->_store_site();

                    $this->sync_plan_if_not_exist( $site->plan_id );

                    if ( ! empty( $license_key ) && FS_Plugin_License::is_valid_id( $site->license_id ) ) {
                        $this->sync_license_if_not_exist( $site->license_id, $license_key );
                    }

                    $this->_admin_notices->remove_sticky( 'connect_account', false );

                    return $site;
                }

                return $this->setup_account( $this->_user, $this->_site, $redirect );
            } else {
                $installs = array();
                foreach ( $result->installs as $install ) {
                    $installs[] = new FS_Site( $install );
                }

                return $this->setup_network_account(
                    $user,
                    $installs,
                    $redirect
                );
            }
        }

        /**
         * Initiate an API request to create a collection of installs.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param \FS_User $user
         * @param bool     $license_key
         * @param bool     $trial_plan_id
         * @param array    $sites
         * @param bool     $redirect
         * @param bool     $silent
         *
         * @return object|mixed
         */
        private function create_installs_with_user(
            FS_User $user,
            $license_key = false,
            $trial_plan_id = false,
            $sites = array(),
            $redirect = false,
            $silent = false
        ) {
            $extra_install_params = array(
                'uid'             => $this->get_anonymous_id(),
                'is_disconnected' => false,
            );

            if ( ! empty( $license_key ) ) {
                $extra_install_params['license_key'] = $this->apply_filters( 'license_key', $license_key );

                if ( $silent ) {
                    $extra_install_params['ignore_license_owner'] = true;
                }
            } else if ( FS_Plugin_Plan::is_valid_id( $trial_plan_id ) ) {
                $extra_install_params['trial_plan_id'] = $trial_plan_id;
            }

            if ( ! empty( $sites ) ) {
                $extra_install_params['sites'] = $sites;
            }

            $args = $this->get_install_data_for_api( $extra_install_params, false, false );

            // Install the plugin.
            $result = $this->get_api_user_scope_by_user( $user )->call(
                "/plugins/{$this->get_id()}/installs.json",
                'post',
                $args
            );

            if ( ! $this->is_api_result_entity( $result ) &&
                 ! $this->is_api_result_object( $result, 'installs' )
            ) {
                if ( ! empty( $args['license_key'] ) ) {
                    // Pass the fully entered license key to the failure handler.
                    $args['license_key'] = $license_key;
                }

                $result = $this->apply_filters( 'after_install_failure', $result, $args );

                if ( ! $silent ) {
                    $this->_admin_notices->add(
                        sprintf( $this->get_text_inline( 'Couldn\'t activate %s.', 'could-not-activate-x' ), $this->get_plugin_name() ) . ' ' .
                        $this->get_text_inline( 'Please contact us with the following message:', 'contact-us-with-error-message' ) . ' ' . '<b>' . $result->error->message . '</b>',
                        $this->get_text_x_inline( 'Oops', 'exclamation', 'oops' ) . '...',
                        'error'
                    );
                }

                if ( $redirect ) {
                    /**
                     * We set the user before getting the user scope API handler, so the user became temporarily
                     * registered (`is_registered() = true`). Since the API returned an error and we will redirect,
                     * we have to set the user to `null`, otherwise, the user will be redirected to the wrong
                     * activation page based on the return value of `is_registered()`. In addition, in case the
                     * context plugin doesn't have a settings menu and the default page is the `Plugins` page,
                     * misleading plugin activation errors will be shown on the `Plugins` page.
                     *
                     * @author Leo Fajardo (@leorw)
                     */
                    $user = $this->_user;

                    $this->_user = null;

                    fs_redirect( $this->get_activation_url( array( 'error' => $result->error->message ) ) );

                    /**
                     * Restore the user after the redirect, this is relevant when there are cases where the redirect will choose not to do anything.
                     */
                    $this->_user = $user;
                }
            }

            return $result;
        }

        /**
         * Tries to activate add-on account based on parent plugin info.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param Freemius          $parent_fs
         * @param bool|int|null     $network_level_or_blog_id True for network level opt-in and integer for opt-in for specified blog in the network.
         * @param FS_Plugin_License $bundle_license           Since 2.4.0. If provided, this license will be activated for the add-on.
         */
        private function _activate_addon_account(
            Freemius $parent_fs,
            $network_level_or_blog_id = null,
            FS_Plugin_License $bundle_license = null
        ) {
            if ( $this->is_registered() ) {
                // Already activated.
                return;
            }

            $permission_ids = FS_Permission_Manager::get_all_permission_ids();
            $permissions = array();
            foreach ( $permission_ids as $permission_id ) {
                $permissions[ $permission_id ] = FS_Permission_Manager::instance( $parent_fs )->is_permission( $permission_id, true );
            }

            FS_Permission_Manager::instance( $this )->update_permissions_tracking_flag( $permissions );

            /**
             * Do not override the `uid` if network-level opt-in since the call to `get_sites_for_network_level_optin()`
             * already returns the data for the current blog.
             *
             * @author Leo Fajardo (@leorw)
             * @since 2.3.0
             */
            $uid_param_to_override = ( true === $network_level_or_blog_id ) ?
                array() :
                array( 'uid' => $this->get_anonymous_id() );

            $params = $this->get_install_data_for_api(
                $uid_param_to_override,
                false,
                false,
                /**
                 * Do not include the data for the current blog if network-level opt-in since the call to `get_sites_for_network_level_optin`
                 * already includes the data for it.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.3.0
                 */
                ( true !== $network_level_or_blog_id )
            );

            if ( true === $network_level_or_blog_id ) {
                $params['sites'] = $this->get_sites_for_network_level_optin();

                if ( empty( $params['sites'] ) ) {
                    return;
                }
            }

            if ( is_object( $bundle_license ) ) {
                $params['license_key'] = $bundle_license->secret_key;
            }

            // Activate add-on with parent plugin credentials.
            $result = $parent_fs->get_api_site_scope()->call(
                "/addons/{$this->_plugin->id}/installs.json",
                'post',
                $params
            );

            if ( ! $this->is_api_result_object( $result, 'installs' ) ) {
                if ( is_object( $bundle_license ) ) {
                    /**
                     * When a license object is provided, it's an attempt by the SDK to activate a bundle license and not a user-initiated action, therefore, do not show any admin notice to avoid confusion (e.g.: the notice will show up just above the opt-in link). If the license activation fails, the admin will see an opt-in link instead.
                     *
                     * @author Leo Fajardo (@leorw)
                     * @since  2.4.0
                     */
                } else {
                    $error_message = FS_Api::is_api_error_object( $result ) ?
                        $result->error->message :
                        $this->get_text_inline( 'An unknown error has occurred.', 'unknown-error' );

                    $this->_admin_notices->add(
                        sprintf( $this->get_text_inline( 'Couldn\'t activate %s.', 'could-not-activate-x' ), $this->get_plugin_name() ) . ' ' .
                        $this->get_text_inline( 'Please contact us with the following message:', 'contact-us-with-error-message' ) . ' ' . '<b>' . $error_message . '</b>',
                        $this->get_text_x_inline( 'Oops', 'exclamation', 'oops' ) . '...',
                        'error'
                    );
                }

                return;
            }

            $addon_installs = $result->installs;
            foreach ( $addon_installs as $key => $addon_install ) {
                $addon_installs[ $key ] = new FS_Site( $addon_install );
            }

            $first_install = $addon_installs[0];

            // Get user information based on parent's plugin.
            $user = $parent_fs->get_user();

            // First of all, set site and user info - otherwise we won't
            // be able to invoke API calls.
            $this->_site = $first_install;
            $this->_user = $user;

            // Sync add-on plans.
            $this->_sync_plans();

            $this->handle_account_connection( $addon_installs, ! fs_is_network_admin() );

            // Get site's current plan.
            //$this->_site->plan = $this->_get_plan_by_id( $this->_site->plan->id );

            // Sync licenses.
            $this->_sync_licenses();

            if ( ! fs_is_network_admin() ) {
                // Try to activate premium license.
                $this->_activate_license( true, $bundle_license );

                if ( is_object( $bundle_license ) ) {
                    $this->maybe_activate_bundle_license( $bundle_license );
                }
            } else {
                if ( is_object( $bundle_license ) ) {
                    $premium_license = $bundle_license;
                } else {
                    $license_id = fs_request_get( 'license_id' );

                    if ( is_object( $this->_site ) &&
                        FS_Plugin_License::is_valid_id( $license_id ) &&
                        $license_id == $this->_site->license_id
                    ) {
                        // License is already activated.
                        return;
                    }

                    $premium_license = FS_Plugin_License::is_valid_id( $license_id ) ?
                        $this->_get_license_by_id( $license_id ) :
                        $this->_get_available_premium_license();
                }

                if ( is_object( $premium_license ) ) {
                    $this->maybe_network_activate_addon_license( $premium_license );
                }
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @param FS_Site[] $installs
         * @param bool      $is_site_level
         */
        private function handle_account_connection( $installs, $is_site_level ) {
            $first_install = $installs[0];

            if ( $is_site_level ) {
                $this->_set_account( $this->_user, $first_install );

                $this->do_action( 'after_account_connection', $this->_user, $first_install );
            } else {
                $this->_store_user();

                // Map site addresses to their blog IDs.
                $address_to_blog_map = $this->get_address_to_blog_map();

                $first_blog_id      = null;
                $blog_2_install_map = array();
                foreach ( $installs as $install ) {
                    $address = trailingslashit( fs_strip_url_protocol( $install->url ) );
                    $blog_id = $address_to_blog_map[ $address ];

                    $this->_store_site( true, $blog_id, $install );

                    if ( is_null( $first_blog_id ) ) {
                        $first_blog_id = $blog_id;
                    }

                    $blog_2_install_map[ $blog_id ] = $install;
                }

                if ( ! FS_User::is_valid_id( $this->_storage->network_user_id ) ||
                    ! is_object( self::_get_user_by_id( $this->_storage->network_user_id ) )
                ) {
                    // Store network user.
                    $this->_storage->network_user_id = $this->_user->id;
                }

                if ( ! FS_Site::is_valid_id( $this->_storage->network_install_blog_id ) ) {
                    $this->_storage->network_install_blog_id = $first_blog_id;
                }

                if ( count( $installs ) === count( $address_to_blog_map ) ) {
                    // Super admin opted in for all sites in the network.
                    $this->_storage->is_network_connected = true;
                }

                $this->_store_licenses( false );

                self::$_accounts->store();

                // Don't sync the installs data on network upgrade
                if ( ! $this->network_upgrade_mode_completed() ) {
                    $this->send_installs_update();
                }

                $current_blog = get_current_blog_id();

                foreach ( $blog_2_install_map as $blog_id => $install ) {
                    $this->switch_to_blog( $blog_id );

                    $this->do_action( 'after_account_connection', $this->_user, $install );
                }

                // Switch install context back to the first install.
                $this->switch_to_blog(
                    $current_blog,
                    $first_install,
                    ( $this->_site->id != $first_install->id )
                );

                $this->do_action( 'after_network_account_connection', $this->_user, $blog_2_install_map );
            }
        }

        /**
         * Tries to activate parent account based on add-on's info.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @param Freemius $parent_fs
         */
        private function activate_parent_account( Freemius $parent_fs ) {
            if ( ! $this->is_addon() ) {
                // This is not an add-on.
                return;
            }

            if ( $parent_fs->is_registered() ) {
                // Already activated.
                return;
            }

            // Activate parent with add-on's user credentials.
            $parent_install = $this->get_api_user_scope()->call(
                "/plugins/{$parent_fs->_plugin->id}/installs.json",
                'post',
                $parent_fs->get_install_data_for_api( array(
                    'uid' => $parent_fs->get_anonymous_id(),
                ), false, false )
            );

            if ( isset( $parent_install->error ) ) {
                $this->_admin_notices->add(
                    sprintf( $this->get_text_inline( 'Couldn\'t activate %s.', 'could-not-activate-x' ), $this->get_plugin_name() ) . ' ' .
                    $this->get_text_inline( 'Please contact us with the following message:', 'contact-us-with-error-message' ) . ' ' . '<b>' . $parent_install->error->message . '</b>',
                    $this->get_text_x_inline( 'Oops', 'exclamation', 'oops' ) . '...',
                    'error'
                );

                return;
            }

            $parent_fs->_admin_notices->remove_sticky( 'connect_account' );

            if ( $parent_fs->is_pending_activation() ) {
                $parent_fs->clear_pending_activation_mode();
            }

            // Get user information based on parent's plugin.
            $user = $this->get_user();

            // First of all, set site info - otherwise we won't
            // be able to invoke API calls.
            $parent_fs->_site = new FS_Site( $parent_install );
            $parent_fs->_user = $user;

            // Sync add-on plans.
            $parent_fs->_sync_plans();

            $parent_fs->update_license_required_permissions_if_anonymous();

            $parent_fs->_set_account( $user, $parent_fs->_site );
        }

        #endregion

        #----------------------------------------------------------------------------------
        #region Admin Menu Items
        #----------------------------------------------------------------------------------

        private $_menu_items = array();

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.8
         *
         * @return array
         */
        function get_menu_items() {
            return $this->_menu_items;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.7
         *
         * @return string
         */
        function get_menu_slug() {
            return $this->_menu->get_slug();
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         */
        function _prepare_admin_menu() {
//			if ( ! $this->is_on() ) {
//				return;
//			}

            if ( is_object( $this->_site ) && ! $this->is_registered() ) {
                return;
            }
            
            /**
             * When running from a site admin with a network activated module and the connection
             * was NOT delegated and the user still haven't skipped or opted-in, then hide the
             * site level settings.
             *
             * @author Vova Feldman (@svovaf)
             * @since  2.0.0
             */
            $should_hide_site_admin_settings = (
                $this->_is_network_active &&
                ! fs_is_network_admin() &&
                ! $this->is_delegated_connection() &&
                ! $this->is_anonymous() &&
                ! $this->is_registered()
            );

            $should_hide_site_admin_settings = $this->apply_filters( 'should_hide_site_admin_settings_on_network_activation_mode', $should_hide_site_admin_settings );

            if ( ( false === $this->has_api_connectivity() && ! $this->is_enable_anonymous() ) ||
                 $should_hide_site_admin_settings
            ) {
                $this->_menu->remove_menu_item( $should_hide_site_admin_settings );
            } else {
                $this->do_action( fs_is_network_admin() ?
                    'before_network_admin_menu_init' :
                    'before_admin_menu_init'
                );

                $this->add_menu_action();

                $this->add_network_menu_when_missing();

                $this->add_submenu_items();
            }
        }

        /**
         * Admin dashboard menu items modifications.
         *
         * NOTE: admin_menu action executed before admin_init.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.7
         *
         */
        private function add_menu_action() {
            if ( $this->is_activation_mode() ) {
                if ( $this->show_opt_in_on_setting_page() ) {
                    $this->override_plugin_menu_with_activation();
                } else {
                    /**
                     * Handle theme opt-in when the opt-in form shows as a dialog box in the themes page.
                     */
                    if ( fs_request_is_action( $this->get_unique_affix() . '_activate_existing' ) ) {
                        add_action( 'load-themes.php', array( &$this, '_install_with_current_user' ) );
                    } else if ( fs_request_is_action( $this->get_unique_affix() . '_activate_new' ) ||
                                fs_request_get_bool( 'pending_activation' )
                    ) {
                        add_action( 'load-themes.php', array( &$this, '_install_with_new_user' ) );
                    }
                }
            } else {
                if ( ! $this->is_registered() ) {
                    // If not registered try to install user.
                    if ( fs_request_is_action( $this->get_unique_affix() . '_activate_new' ) ) {
                        $this->_install_with_new_user();
                    }
                } else if (
                    fs_request_is_action( 'sync_user' ) &&
                    ( ! $this->has_settings_menu() || $this->show_opt_in_on_themes_page() )
                ) {
                    $this->_handle_account_user_sync();
                }
            }
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         */
        function _redirect_on_clicked_menu_link() {
            $this->_logger->entrance();

            $page = fs_request_get('page');
            $page = is_string($page) ? strtolower($page) : '';

            $this->_logger->log( 'page = ' . $page );

            foreach ( $this->_menu_items as $priority => $items ) {
                foreach ( $items as $item ) {
                    if ( isset( $item['url'] ) ) {
                        if ( $page === $this->_menu->get_slug( strtolower( $item['menu_slug'] ) ) ) {
                            $this->_logger->log( 'Redirecting to ' . $item['url'] );

                            fs_redirect( $item['url'] );
                        }
                    }
                }
            }
        }

        /**
         * Remove plugin's all admin menu items & pages, and replace with activation page.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         */
        private function override_plugin_menu_with_activation() {
            $this->_logger->entrance();

            $hook = false;

            if ( ! $this->has_settings_menu() ) {
                // Add the opt-in page without a menu item.
                $hook = FS_Admin_Menu_Manager::add_subpage(
                    '',
                    $this->get_plugin_name(),
                    $this->get_plugin_name(),
                    'manage_options',
                    $this->_slug,
                    array( &$this, '_connect_page_render' )
                );
            } else if ( $this->_menu->is_top_level() ) {
                if ( $this->_menu->is_override_exact() ) {
                    // Make sure the current page is matching the activation page.
                    if ( ! $this->is_matching_url( $this->get_activation_url() ) ) {
                        return;
                    }
                }

                $hook = $this->_menu->override_menu_item( array( &$this, '_connect_page_render' ) );

                if ( false === $hook ) {
                    // Create new menu item just for the opt-in.
                    $hook = FS_Admin_Menu_Manager::add_page(
                        $this->get_plugin_name(),
                        $this->get_plugin_name(),
                        'manage_options',
                        $this->_menu->get_slug(),
                        array( &$this, '_connect_page_render' )
                    );
                }
            } else {
                $menus = array( $this->_menu->get_parent_slug() );

                if ( $this->_menu->is_override_exact() ) {
                    // Make sure the current page is matching the activation page.
                    if ( ! $this->is_matching_url( $this->get_activation_url() ) ) {
                        return;
                    }
                }

                foreach ( $menus as $parent_slug ) {
                    $hook = $this->_menu->override_submenu_action(
                        $parent_slug,
                        $this->_menu->get_raw_slug(),
                        array( &$this, '_connect_page_render' )
                    );

                    if ( false !== $hook ) {
                        // Found plugin's submenu item.
                        break;
                    }
                }
            }

            if ( $this->is_activation_page() ) {
                // Clean admin page from distracting content.
                self::_clean_admin_content_section();
            }

            if ( false !== $hook ) {
                if ( fs_request_is_action( $this->get_unique_affix() . '_activate_existing' ) ) {
                    $this->_install_with_current_user();
                } else if ( fs_request_is_action( $this->get_unique_affix() . '_activate_new' ) ) {
                    $this->_install_with_new_user();
                }
            }
        }

        /**
         * If a plugin was network activated and connected but don't have a network
         * level settings, then add an artificial menu item for the Account and other
         * Freemius settings.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         */
        private function add_network_menu_when_missing() {
            $this->_logger->entrance();

            if ( ! $this->_is_network_active ) {
                // Plugin wasn't activated on the network level.
                return;
            }

            if ( ! fs_is_network_admin() ) {
                // The context is not the network admin.
                return;
            }

            if ( $this->_menu->has_network_menu() ) {
                // Plugin already has a network level menu.
                return;
            }

            if ( $this->is_network_activation_mode() ) {
                /**
                 * Do not add during activation mode, otherwise, there will be duplicate menus while the opt-in
                 * screen is being shown.
                 *
                 * @author Leo Fajardo (@leorw)
                 */
                return;
            }

            if ( ! WP_FS__SHOW_NETWORK_EVEN_WHEN_DELEGATED ) {
                if ( $this->is_network_delegated_connection() ) {
                    // Super-admin delegated the connection to the site admins.
                    return;
                }
            }

            if ( ! $this->_menu->has_menu() || $this->_menu->is_top_level() ) {

                if ( $this->_menu->has_menu() ||
                     ! $this->is_addon() ||
                     $this->is_activation_mode()
                ) {
                    $this->_dynamically_added_top_level_page_hook_name = $this->_menu->add_page_and_update(
                        $this->get_plugin_name(),
                        $this->get_plugin_name(),
                        'manage_options',
                        $this->_menu->has_menu() ? $this->_menu->get_slug() : $this->_slug
                    );
                }
            } else {
                $this->_menu->add_subpage_and_update(
                    $this->_menu->get_parent_slug(),
                    $this->get_plugin_name(),
                    $this->get_plugin_name(),
                    'manage_options',
                    $this->_menu->get_slug()
                );
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.1
         *
         * return string
         */
        function get_top_level_menu_capability() {
            global $menu;

            $top_level_menu_slug = $this->get_top_level_menu_slug();

            foreach ( $menu as $menu_info ) {
                /**
                 * The second element in the menu info array is the capability/role that has access to the menu and the
                 * third element is the menu slug.
                 */
                if ( $menu_info[2] === $top_level_menu_slug ) {
                    return $menu_info[1];
                }
            }

            return 'read';
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.0
         *
         * @return string
         */
        private function get_top_level_menu_slug() {
            return ( $this->is_addon() ?
                $this->get_parent_instance()->_menu->get_top_level_menu_slug() :
                $this->_menu->get_top_level_menu_slug() );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @return string
         */
        function get_pricing_cta_label() {
            $label = $this->get_text_inline( 'Upgrade', 'upgrade' );

            if ( $this->is_in_trial_promotion() &&
                 ! $this->is_paying_or_trial()
            ) {
                // If running a trial promotion, modify the pricing to load the trial.
                $label = $this->get_text_inline( 'Start Trial', 'start-trial' );
            } else if ( $this->is_paying() ) {
                $label = $this->get_text_inline( 'Pricing', 'pricing' );
            }

            return $label;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @return bool
         */
        function is_pricing_page_visible() {
            return (
                // Has at least one paid plan.
                $this->has_paid_plan() &&
                // Didn't ask to hide the pricing page.
                $this->is_page_visible( 'pricing' ) &&
                // Don't have a valid active license or has more than one plan.
                ( ! $this->is_paying() || ! $this->is_single_plan( true ) )
            );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @param bool $is_activation_mode
         *
         * @return bool
         */
        private function should_add_submenu_or_action_links( $is_activation_mode ) {
            if ( $this->is_addon() ) {
                // No submenu items or action links for add-ons.
                return false;
            }

            if ( $this->show_opt_in_on_themes_page() ) {
                if ( ! fs_is_network_admin() ) {
                    // Also add action links or submenu items when running in a free .org theme so the tabs will be visible.
                    return true;
                }
            } else if ( $is_activation_mode ) {
                // Don't show submenu-items/tabs in activation mode, unless it's a wp.org theme.
                return false;
            }

            if ( fs_is_network_admin() ) {
                /**
                 * Add submenu items or action links to network level when plugin was network activated and the super
                 * admin did NOT delegate the connection of all sites to site admins.
                 */
                return (
                    $this->_is_network_active &&
                    ( WP_FS__SHOW_NETWORK_EVEN_WHEN_DELEGATED ||
                        ! $this->is_network_delegated_connection() )
                );
            }

            return ( ! $this->_is_network_active || $this->is_delegated_connection() );
        }

        /**
         * Add default Freemius menu items.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.0
         * @since  1.2.2.7 Also add submenu items when running in a free .org theme so the tabs will be visible.
         */
        private function add_submenu_items() {
            $this->_logger->entrance();

            $is_activation_mode = $this->is_activation_mode();

            $add_submenu_items = $this->should_add_submenu_or_action_links( $is_activation_mode );

            if ( $add_submenu_items ) {
                if ( $this->has_affiliate_program() ) {
                    // Add affiliation page.
                    $this->add_submenu_item(
                        $this->get_text_inline( 'Affiliation', 'affiliation' ),
                        array( &$this, '_affiliation_page_render' ),
                        $this->get_plugin_name() . ' &ndash; ' . $this->get_text_inline( 'Affiliation', 'affiliation' ),
                        'manage_options',
                        'affiliation',
                        'Freemius::_clean_admin_content_section',
                        WP_FS__DEFAULT_PRIORITY,
                        $this->is_submenu_item_visible( 'affiliation' )
                    );
                }
            }

            if ( $add_submenu_items ||
                ( $is_activation_mode &&
                    $this->is_only_premium() &&
                    $this->is_admin_page( 'account' ) &&
                    fs_request_is_action( $this->get_unique_affix() . '_sync_license' )
                )
            ) {
                if ( ! WP_FS__DEMO_MODE && $this->is_registered() ) {
                    $show_account = (
                        $this->is_submenu_item_visible( 'account' ) &&
                        /**
                         * @since 1.2.2.7 Don't show the Account for free WP.org themes without any paid plans.
                         */
                        ( ! $this->is_free_wp_org_theme() || $this->has_paid_plan() )
                    );

                    // Add user account page.
                    $this->add_submenu_item(
                        $this->get_text_inline( 'Account', 'account' ),
                        array( &$this, '_account_page_render' ),
                        $this->get_plugin_name() . ' &ndash; ' . $this->get_text_inline( 'Account', 'account' ),
                        'manage_options',
                        'account',
                        array( &$this, '_account_page_load' ),
                        WP_FS__DEFAULT_PRIORITY,
                        ( $add_submenu_items && $show_account )
                    );
                }
            }

            if ( $add_submenu_items ) {
                if (! WP_FS__DEMO_MODE && ! $this->is_whitelabeled() ) {
                    // Add contact page.
                    if ( $this->is_premium() ) {
                        $this->add_submenu_item(
                            $this->get_text_inline( 'Contact Us', 'contact-us' ),
                            array( &$this, '_contact_page_render' ),
                            $this->get_plugin_name() . ' &ndash; ' . $this->get_text_inline( 'Contact Us', 'contact-us' ),
                            'manage_options',
                            'contact',
                            'Freemius::_clean_admin_content_section',
                            WP_FS__DEFAULT_PRIORITY,
                            $this->is_submenu_item_visible( 'contact' )
                        );
                    } else {
                        $this->add_submenu_link_item(
                            $this->get_text_inline( 'Contact Us', 'contact-us' ),
                            FS_Contact_Form_Manager::instance()->get_standalone_link( $this ),
                            'contact',
                            'manage_options',
                            WP_FS__DEFAULT_PRIORITY,
                            $this->is_submenu_item_visible( 'contact' ),
                            'fs_external_contact',
                            true
                        );
                    }
                }

                if ( $this->has_addons() ) {
                    $this->add_submenu_item(
                        $this->get_text_inline( 'Add-Ons', 'add-ons' ),
                        array( &$this, '_addons_page_render' ),
                        $this->get_plugin_name() . ' &ndash; ' . $this->get_text_inline( 'Add-Ons', 'add-ons' ),
                        'manage_options',
                        'addons',
                        array( &$this, '_addons_page_load' ),
                        WP_FS__LOWEST_PRIORITY - 1,
                        $this->is_submenu_item_visible( 'addons' )
                    );
                }
            }

            if ( $add_submenu_items ||
                ( $is_activation_mode && $this->is_only_premium() && $this->is_admin_page( 'pricing' ) )
            ) {
                if (! WP_FS__DEMO_MODE && ! $this->is_whitelabeled() ) {
                    $show_pricing = (
                        $this->is_submenu_item_visible( 'pricing' ) &&
                        $this->is_pricing_page_visible()
                    );

                    $pricing_cta_text = $this->get_pricing_cta_label();
                    $pricing_class    = 'upgrade-mode';
                    if ( $show_pricing ) {
                        if ( $this->is_in_trial_promotion() &&
                             ! $this->is_paying_or_trial()
                        ) {
                            // If running a trial promotion, modify the pricing to load the trial.
                            $pricing_class = 'trial-mode';
                        } else if ( $this->is_paying() ) {
                            $pricing_class = '';
                        }
                    }

                    $custom_pricing_url        = $this->get_pricing_url_with_filter( null );
                    $pricing_menu_title        = $pricing_cta_text . '&nbsp;&nbsp;' . ( is_rtl() ? $this->get_text_x_inline( '&#x2190;', 'ASCII arrow left icon', 'symbol_arrow-left' ) : $this->get_text_x_inline( '&#x27a4;', 'ASCII arrow right icon', 'symbol_arrow-right' ) );
                    $show_pricing_submenu_item = ( $add_submenu_items && $show_pricing );

                    // Add upgrade/pricing submenu item.
                    if ( ! is_null( $custom_pricing_url ) ) {
                        $this->add_submenu_link_item(
                            $pricing_menu_title,
                            $custom_pricing_url,
                            'pricing',
                            'manage_options',
                            WP_FS__LOWEST_PRIORITY,
                            $show_pricing_submenu_item,
                            $pricing_class
                        );
                    } else {
                        $this->add_submenu_item(
                            $pricing_menu_title,
                            array( &$this, '_pricing_page_render' ),
                            $this->get_plugin_name() . ' &ndash; ' . $this->get_text_x_inline( 'Pricing', 'noun', 'pricing' ),
                            'manage_options',
                            'pricing',
                            'Freemius::_clean_admin_content_section',
                            WP_FS__LOWEST_PRIORITY,
                            $show_pricing_submenu_item,
                            $pricing_class
                        );
                    }
                }
            }

            if ( ! $is_activation_mode || ( true !== $this->_storage->require_license_activation ) ) {
                /**
                 * Add the other menu items if there are any when not in activation mode or license activation is not
                 * required (license activation is required for registered or anonymous users after activating the
                 * premium version when the site is not in trial mode or there's no active valid license).
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.2.1
                 */
                if ( 0 < count( $this->_menu_items ) ) {
                    if ( ! $this->_menu->is_top_level() ) {
                        fs_enqueue_local_style( 'fs_common', '/admin/common.css' );

                        // Append submenu items right after the plugin's submenu item.
                        $this->order_sub_submenu_items();
                    } else {
                        // Append submenu items.
                        $this->embed_submenu_items();
                    }
                }
            }
        }

        /**
         * Moved the actual submenu item additions to a separated function,
         * in order to support sub-submenu items when the plugin's settings
         * only have a submenu and not top-level menu item.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.4
         */
        private function embed_submenu_items() {
            $item_classes = $this->_menu->is_top_level() ? 'fs-submenu-item' : 'fs-submenu-item fs-sub';

            $item_template = '<span class="' . $item_classes . ' %1$s %2$s %3$s" data-fs-external-url="%5$s" data-fs-new-tab="%6$s">%4$s</span>';

            $top_level_menu_capability = $this->get_top_level_menu_capability();

            ksort( $this->_menu_items );

            $is_first_submenu_item = true;

            foreach ( $this->_menu_items as $priority => $items ) {
                foreach ( $items as $item ) {
                    $capability = ( ! empty( $item['capability'] ) ? $item['capability'] : $top_level_menu_capability );

                    $menu_item = sprintf(
                        $item_template,
                        $this->get_unique_affix(),
                        $item['menu_slug'],
                        ! empty( $item['class'] ) ? $item['class'] : '',
                        $item['menu_title'],
                        esc_attr( isset( $item['url'] ) ? $item['url'] : '' ),
                        esc_attr( isset( $item['new_tab'] ) ? 'true' : 'false' )
                    );

                    $top_level_menu_slug = $this->get_top_level_menu_slug();
                    $menu_slug           = $this->_menu->get_slug( $item['menu_slug'] );

                    if ( ! isset( $item['url'] ) ) {
                        $hook = FS_Admin_Menu_Manager::add_subpage(
                            $item['show_submenu'] ?
                                $top_level_menu_slug :
                                '',
                            $item['page_title'],
                            $menu_item,
                            $capability,
                            $menu_slug,
                            $item['render_function']
                        );

                        if ( false !== $item['before_render_function'] ) {
                            add_action( "load-$hook", $item['before_render_function'] );
                        }
                    } else {
                        FS_Admin_Menu_Manager::add_subpage(
                            $item['show_submenu'] ?
                                $top_level_menu_slug :
                                '',
                            $item['page_title'],
                            $menu_item,
                            $capability,
                            $menu_slug,
                            array( $this, '' )
                        );
                    }

                    if ( $item['show_submenu'] && $is_first_submenu_item ) {
                        if ( $this->_is_network_active && ! empty( $this->_dynamically_added_top_level_page_hook_name ) ) {
                            /**
                             * If the top-level menu has been dynamically created, remove the first submenu item that
                             * WordPress automatically creates when there's no submenu item whose slug matches the
                             * parent's. In the following example, the `Awesome Plugin` submenu item will be removed.
                             *
                             * Awesome Plugin
                             *     - Awesome Plugin <-- we want to remove this since there's no real setting page for the top-level
                             *
                             * @author Leo Fajardo (@leorw)
                             */
                            remove_submenu_page( $top_level_menu_slug, $top_level_menu_slug );
                        }

                        $is_first_submenu_item = false;
                    }
                }
            }
        }

        /**
         * Re-order the submenu items so all Freemius added new submenu items
         * are added right after the plugin's settings submenu item.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.4
         */
        private function order_sub_submenu_items() {
            global $submenu;

            $menu_slug = $this->_menu->get_top_level_menu_slug();

            /**
             * Before "admin_menu" fires, WordPress will loop over the default submenus and remove pages for which the user
             * does not have permissions. So in case a plugin does not have top-level menu but does have submenus under any
             * of the default menus, only users that have the right role can access its sub-submenus (Account, Contact Us,
             * Support Forum, etc.) since $submenu[ $menu_slug ] will be empty if the user doesn't have permission.
             *
             * In case a plugin does not have submenus under any of the default menus but does have submenus under the menu
             * of another plugin, only users that have the right role can access its sub-submenus since we will use the
             * capability needed to access the parent menu as the capability for the submenus that we will add.
             */
            if ( empty( $submenu[ $menu_slug ] ) ) {
                return;
            }

            $top_level_menu = &$submenu[ $menu_slug ];

            $all_submenu_items_after = array();

            $found_submenu_item = false;

            foreach ( $top_level_menu as $submenu_id => $meta ) {
                if ( $found_submenu_item ) {
                    // Remove all submenu items after the plugin's submenu item.
                    $all_submenu_items_after[] = $meta;
                    unset( $top_level_menu[ $submenu_id ] );
                }

                if ( $this->_menu->get_raw_slug() === $meta[2] ) {
                    // Found the submenu item, put all below.
                    $found_submenu_item = true;
                    continue;
                }
            }

            // Embed all plugin's new submenu items.
            $this->embed_submenu_items();

            // Start with specially high number to make sure it's appended.
            $i = max( 10000, max( array_keys( $top_level_menu ) ) + 1 );
            foreach ( $all_submenu_items_after as $meta ) {
                $top_level_menu[ $i ] = $meta;
                $i ++;
            }

            // Sort submenu items.
            ksort( $top_level_menu );
        }

        /**
         * Helper method to return the module's support forum URL.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @return string
         */
        function get_support_forum_url() {
            return $this->apply_filters( 'support_forum_url', "https://wordpress.org/support/{$this->_module_type}/{$this->_slug}" );
        }

        /**
         * Displays the Support Forum link when enabled.
         *
         * Can be filtered like so:
         *
         *  function _fs_show_support_menu( $is_visible, $menu_id ) {
         *      if ( 'support' === $menu_id ) {
         *            return _fs->is_registered();
         *        }
         *        return $is_visible;
         *    }
         *    _fs()->add_filter('is_submenu_visible', '_fs_show_support_menu', 10, 2);
         *
         */
        function _add_default_submenu_items() {
            if ( ! $this->is_on() ) {
                return;
            }

            if ( ! $this->is_activation_mode() &&
                 ( ( $this->_is_network_active && fs_is_network_admin() ) ||
                   ( ! $this->_is_network_active && is_admin() ) )
            ) {
                $this->add_submenu_link_item(
                    $this->apply_filters( 'support_forum_submenu', $this->get_text_inline( 'Support Forum', 'support-forum' ) ),
                    $this->get_support_forum_url(),
                    'wp-support-forum',
                    null,
                    50,
                    $this->is_submenu_item_visible( 'support' ),
                    '',
                    true
                );
            }
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         *
         * @param string        $menu_title
         * @param callable      $render_function
         * @param bool|string   $page_title
         * @param string        $capability
         * @param bool|string   $menu_slug
         * @param bool|callable $before_render_function
         * @param int           $priority
         * @param bool          $show_submenu
         * @param string        $class Since 1.2.1.5 can add custom classes to menu items.
         */
        function add_submenu_item(
            $menu_title,
            $render_function,
            $page_title = false,
            $capability = 'manage_options',
            $menu_slug = false,
            $before_render_function = false,
            $priority = WP_FS__DEFAULT_PRIORITY,
            $show_submenu = true,
            $class = ''
        ) {
            $this->_logger->entrance( 'Title = ' . $menu_title );

            if ( $this->is_addon() ) {
                $parent_fs = $this->get_parent_instance();

                if ( is_object( $parent_fs ) ) {
                    $parent_fs->add_submenu_item(
                        $menu_title,
                        $render_function,
                        $page_title,
                        $capability,
                        $menu_slug,
                        $before_render_function,
                        $priority,
                        $show_submenu,
                        $class
                    );

                    return;
                }
            }

            if ( ! isset( $this->_menu_items[ $priority ] ) ) {
                $this->_menu_items[ $priority ] = array();
            }

            $this->_menu_items[ $priority ][] = array(
                'page_title'             => is_string( $page_title ) ? $page_title : $menu_title,
                'menu_title'             => $menu_title,
                'capability'             => $capability,
                'menu_slug'              => is_string( $menu_slug ) ? $menu_slug : strtolower( $menu_title ),
                'render_function'        => $render_function,
                'before_render_function' => $before_render_function,
                'show_submenu'           => $show_submenu,
                'class'                  => $class,
            );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         *
         * @param string $menu_title
         * @param string $url
         * @param bool   $menu_slug
         * @param string $capability
         * @param int    $priority
         * @param bool   $show_submenu
         * @param string $class
         * @param bool   $new_tab
         */
        function add_submenu_link_item(
            $menu_title,
            $url,
            $menu_slug = false,
            $capability = 'read',
            $priority = WP_FS__DEFAULT_PRIORITY,
            $show_submenu = true,
            $class = '',
            $new_tab = false
        ) {
            $this->_logger->entrance( 'Title = ' . $menu_title . '; Url = ' . $url );

            if ( $this->is_addon() ) {
                $parent_fs = $this->get_parent_instance();

                if ( is_object( $parent_fs ) ) {
                    $parent_fs->add_submenu_link_item(
                        $menu_title,
                        $url,
                        $menu_slug,
                        $capability,
                        $priority,
                        $show_submenu,
                        $class,
                        $new_tab
                    );

                    return;
                }
            }

            if ( ! isset( $this->_menu_items[ $priority ] ) ) {
                $this->_menu_items[ $priority ] = array();
            }

            $this->_menu_items[ $priority ][] = array(
                'menu_title'             => $menu_title,
                'capability'             => $capability,
                'menu_slug'              => is_string( $menu_slug ) ? $menu_slug : strtolower( $menu_title ),
                'url'                    => $url,
                'page_title'             => $menu_title,
                'render_function'        => 'fs_dummy',
                'before_render_function' => '',
                'show_submenu'           => $show_submenu,
                'class'                  => $class,
                'new_tab'                => $new_tab,
            );
        }

        #endregion ------------------------------------------------------------------

        #--------------------------------------------------------------------------------
        #region Admin Notices
        #--------------------------------------------------------------------------------

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.3.1
         *
         * @param string|string[] $ids
         * @param int|null        $network_level_or_blog_id
         *
         * @uses FS_Admin_Notices::remove_sticky()
         */
        function remove_sticky( $ids, $network_level_or_blog_id = null ) {
            $this->_admin_notices->remove_sticky( $ids, $network_level_or_blog_id );
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Actions / Hooks / Filters
        #--------------------------------------------------------------------------------

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7
         *
         * @param string $tag
         *
         * @return string
         */
        public function get_action_tag( $tag ) {
            return self::get_action_tag_static( $tag, $this->_slug, $this->is_plugin() );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.6
         *
         * @param string $tag
         * @param string $slug
         * @param bool   $is_plugin
         *
         * @return string
         */
        static function get_action_tag_static( $tag, $slug = '', $is_plugin = true ) {
            $action = "fs_{$tag}";

            if ( ! empty( $slug ) ) {
                $action .= '_' . self::get_module_unique_affix( $slug, $is_plugin );
            }

            return $action;
        }

        /**
         * Returns a string that can be used to generate a unique action name,
         * option name, HTML element ID, or HTML element class.
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.2.2
         *
         * @return string
         */
        public function get_unique_affix() {
            return self::get_module_unique_affix( $this->_slug, $this->is_plugin() );
        }

        /**
         * Returns a string that can be used to generate a unique action name,
         * option name, HTML element ID, or HTML element class.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.5
         *
         * @param string $slug
         * @param bool   $is_plugin
         *
         * @return string
         */
        static function get_module_unique_affix( $slug, $is_plugin = true ) {
            $affix = $slug;

            if ( ! $is_plugin ) {
                $affix .= '-' . WP_FS__MODULE_TYPE_THEME;
            }

            return $affix;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1
         * @since  1.2.2.5 The AJAX action names are based on the module ID, not like the non-AJAX actions that are
         *         based on the slug for backward compatibility.
         *
         * @param string $tag
         *
         * @return string
         */
        function get_ajax_action( $tag ) {
            return self::get_ajax_action_static( $tag, $this->_module_id );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.7
         *
         * @param string $tag
         *
         * @return string
         */
        function get_ajax_security( $tag ) {
            return wp_create_nonce( $this->get_ajax_action( $tag ) );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.7
         *
         * @param string $tag
         */
        function check_ajax_referer( $tag ) {
            check_ajax_referer( $this->get_ajax_action( $tag ), 'security' );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.6
         * @since  1.2.2.5 The AJAX action names are based on the module ID, not like the non-AJAX actions that are
         *         based on the slug for backward compatibility.
         *
         * @param string      $tag
         * @param number|null $module_id
         *
         * @return string
         */
        static function get_ajax_action_static( $tag, $module_id = null ) {
            $action = "fs_{$tag}";

            if ( ! empty( $module_id ) ) {
                $action .= "_{$module_id}";
            }

            return $action;
        }

        /**
         * Do action, specific for the current context plugin.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         *
         * @param string $tag     The name of the action to be executed.
         * @param mixed  $arg,... Optional. Additional arguments which are passed on to the
         *                        functions hooked to the action. Default empty.
         *
         * @uses   do_action()
         */
        function do_action( $tag, $arg = '' ) {
            $args = func_get_args();

            $this->_logger->entrance( $tag );

            call_user_func_array( 'do_action', array_merge(
                    array( $this->get_action_tag( $tag ) ),
                    array_slice( $args, 1 ) )
            );
        }

        /**
         * Add action, specific for the current context plugin.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         *
         * @param string   $tag
         * @param callable $function_to_add
         * @param int      $priority
         * @param int      $accepted_args
         *
         * @uses   add_action()
         */
        function add_action(
            $tag,
            $function_to_add,
            $priority = WP_FS__DEFAULT_PRIORITY,
            $accepted_args = 1
        ) {
            $this->_logger->entrance( $tag );

            add_action( $this->get_action_tag( $tag ), $function_to_add, $priority, $accepted_args );
        }

        /**
         * Add AJAX action, specific for the current context plugin.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1
         *
         * @param string   $tag
         * @param callable $function_to_add
         * @param int      $priority
         *
         * @uses   add_action()
         *
         * @return bool True if action added, false if no need to add the action since the AJAX call isn't matching.
         */
        function add_ajax_action(
            $tag,
            $function_to_add,
            $priority = WP_FS__DEFAULT_PRIORITY
        ) {
            $this->_logger->entrance( $tag );

            return self::add_ajax_action_static(
                $tag,
                $function_to_add,
                $priority,
                $this->_module_id
            );
        }

        /**
         * Add AJAX action.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.6
         *
         * @param string      $tag
         * @param callable    $function_to_add
         * @param int         $priority
         * @param number|null $module_id
         *
         * @return bool True if action added, false if no need to add the action since the AJAX call isn't matching.
         * @uses   add_action()
         *
         */
        static function add_ajax_action_static(
            $tag,
            $function_to_add,
            $priority = WP_FS__DEFAULT_PRIORITY,
            $module_id = null
        ) {
            self::$_static_logger->entrance( $tag );

            if ( ! self::is_ajax_action_static( $tag, $module_id ) ) {
                return false;
            }

            add_action(
                'wp_ajax_' . self::get_ajax_action_static( $tag, $module_id ),
                $function_to_add,
                $priority,
                0
            );

            self::$_static_logger->info( "$tag AJAX callback action added." );

            return true;
        }

        /**
         * Send a JSON response back to an Ajax request.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.5
         *
         * @param mixed $response
         */
        static function shoot_ajax_response( $response ) {
            wp_send_json( $response );
        }

        /**
         * Send a JSON response back to an Ajax request, indicating success.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.5
         *
         * @param mixed $data Data to encode as JSON, then print and exit.
         */
        static function shoot_ajax_success( $data = null ) {
            wp_send_json_success( $data );
        }

        /**
         * Send a JSON response back to an Ajax request, indicating failure.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.5
         *
         * @param mixed $error Optional error message.
         */
        static function shoot_ajax_failure( $error = '' ) {
            $result = array( 'success' => false );
            if ( ! empty( $error ) ) {
                $result['error'] = $error;
            }

            wp_send_json( $result );
        }

        /**
         * Returns an AJAX URL with a special extra param to indicate whether the request was triggered from the network admin or blog admin.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.5.1
         *
         * @param string $wrap_with By default, returns the AJAX URL wrapped with single quotes.
         *
         * @return string
         */
        static function ajax_url( $wrap_with = "'") {
            if ( fs_is_network_admin() ) {
                $param_name = '_fs_network_admin';
            } else {
                $param_name = '_fs_blog_admin';
            }

            $url = admin_url( 'admin-ajax.php', 'relative' );
            $url .= ( false === strpos( $url, '?' ) ) ? '?' : '&';
            $url .= "{$param_name}=true";

            return "{$wrap_with}{$url}{$wrap_with}";
        }

        /**
         * Apply filter, specific for the current context plugin.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @param string $tag   The name of the filter hook.
         * @param mixed  $value The value on which the filters hooked to `$tag` are applied on.
         *
         * @return mixed The filtered value after all hooked functions are applied to it.
         *
         * @uses   apply_filters()
         */
        function apply_filters( $tag, $value ) {
            $args = func_get_args();

            $this->_logger->entrance( $tag );

            array_unshift( $args, $this->get_unique_affix() );

            return call_user_func_array( 'fs_apply_filter', $args );
        }

        /**
         * Add filter, specific for the current context plugin.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @param string   $tag
         * @param callable $function_to_add
         * @param int      $priority
         * @param int      $accepted_args
         *
         * @uses   add_filter()
         */
        function add_filter( $tag, $function_to_add, $priority = WP_FS__DEFAULT_PRIORITY, $accepted_args = 1 ) {
            $this->_logger->entrance( $tag );

            add_filter( $this->get_action_tag( $tag ), $function_to_add, $priority, $accepted_args );
        }

        /**
         * Check if has filter.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.4
         *
         * @param string        $tag
         * @param callable|bool $function_to_check Optional. The callback to check for. Default false.
         *
         * @return false|int
         *
         * @uses   has_filter()
         */
        function has_filter( $tag, $function_to_check = false ) {
            $this->_logger->entrance( $tag );

            return has_filter( $this->get_action_tag( $tag ), $function_to_check );
        }

        #endregion

        /**
         * Override default i18n text phrases.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.6
         *
         * @param string[] string $key_value
         *
         * @uses   fs_override_i18n()
         */
        function override_i18n( $key_value ) {
            fs_override_i18n( $key_value, $this->_slug );
        }

        /* Account Page
        ------------------------------------------------------------------------------------------------------------------*/
        /**
         * Update site information.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         *
         * @param bool     $store                    Flush to Database if true.
         * @param null|int $network_level_or_blog_id Since 2.0.0
         * @param \FS_Site $site                     Since 2.0.0
         */
        private function _store_site( $store = true, $network_level_or_blog_id = null, FS_Site $site = null, $is_backup = false ) {
            $this->_logger->entrance();

            if ( is_null( $site ) ) {
                $site = $this->_site;
            }

            if ( !isset( $site ) || !is_object($site) || empty( $site->id ) ) {
                $this->_logger->error( "Empty install ID, can't store site." );

                return;
            }

            $site_clone = clone $site;

            $sites = self::get_all_sites( $this->_module_type, $network_level_or_blog_id, $is_backup );

            if (
                ! $is_backup &&
                is_object( $this->_user ) && $this->_user->id != $site->user_id
            ) {
                $this->sync_user_by_current_install( $site->user_id );

                $prev_stored_user_id = $this->_storage->get( 'prev_user_id', false, $network_level_or_blog_id );

                if ( empty( $prev_stored_user_id ) &&
                     is_object($this->_user) && $this->_user->id != $site->user_id
                ) {
                    /**
                     * Store the current user ID as the previous user ID so that the previous user can be used
                     * as the install's owner while the new owner's details are not yet available.
                     *
                     * This will be executed only in the `replica` site. For example, there are 2 sites, namely `original`
                     * and `replica`, then an ownership change was initiated and completed in the `original`, the `replica`
                     * will be using the previous user until it is updated again (e.g.: until the next clone of `original`
                     * into `replica`.
                     *
                     * @author Leo Fajardo (@leorw)
                     */
                    $this->_storage->store( 'prev_user_id', $sites[ $this->_slug ]->user_id, $network_level_or_blog_id );
                }
            }

            $sites[ $this->_slug ] = $site_clone;

            $this->set_account_option(
                ( $is_backup ? 'prev_' : '' ) . 'sites',
                $sites,
                $store,
                $network_level_or_blog_id
            );
        }

        /**
         * Stores the context site in the sites backup storage. This logic is used before deleting the site info so that it can be restored later on if necessary (e.g., if the automatic clone resolution attempt fails).
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         */
        private function back_up_site() {
            $this->_logger->entrance();

            $site_clone = clone $this->_site;

            $this->_store_site( true, null, $site_clone, true );
        }

        /**
         * Update plugin's plans information.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.2
         *
         * @param bool $store Flush to Database if true.
         */
        private function _store_plans( $store = true ) {
            $this->_logger->entrance();

            $plans = self::get_all_plans( $this->_module_type );

            // Copy plans.
            $encrypted_plans = array();
            for ( $i = 0, $len = count( $this->_plans ); $i < $len; $i ++ ) {
                $encrypted_plans[] = self::_encrypt_entity( $this->_plans[ $i ] );
            }

            $plans[ $this->_slug ] = $encrypted_plans;

            $this->set_account_option( 'plans', $plans, $store );
        }

        /**
         * Update user's plugin licenses.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         *
         * @param bool                $store
         * @param number|bool         $module_id
         * @param FS_Plugin_License[] $licenses
         */
        private function _store_licenses( $store = true, $module_id = false, $licenses = array() ) {
            $this->_logger->entrance();

            $all_licenses = self::get_all_licenses();

            if ( ! FS_Plugin::is_valid_id( $module_id ) ) {
                $module_id = $this->_module_id;

                $user_licenses = is_array( $this->_licenses ) ?
                    $this->_licenses :
                    array();

                if ( empty( $user_licenses ) ) {
                    // If the context user doesn't have any license, don't update the licenses collection.
                    return;
                }

                $new_user_licenses_map = array();
                foreach ( $user_licenses as $user_license ) {
                    $new_user_licenses_map[ $user_license->id ] = $user_license;
                }

                self::store_user_id_license_ids_map( array_keys( $new_user_licenses_map ), $this->_module_id, $this->_user->id );

                // Update user licenses.
                $licenses_to_update_count = count( $new_user_licenses_map );
                foreach ( $all_licenses[ $module_id ] as $key => $license ) {
                    if ( 0 === $licenses_to_update_count ) {
                        break;
                    }

                    if ( isset( $new_user_licenses_map[ $license->id ] ) ) {
                        // Update license.
                        $all_licenses[ $module_id ][ $key ] = $new_user_licenses_map[ $license->id ];
                        unset( $new_user_licenses_map[ $license->id ] );

                        $licenses_to_update_count --;
                    }
                }

                if ( ! empty( $new_user_licenses_map ) ) {
                    // Add new licenses.
                    $all_licenses[ $module_id ] = array_merge( array_values( $new_user_licenses_map ), $all_licenses[ $module_id ] );
                }

                $licenses = $all_licenses[ $module_id ];
            }

            if ( ! isset( $all_licenses[ $module_id ] ) ) {
                $all_licenses[ $module_id ] = array();
            }

            $all_licenses[ $module_id ] = $licenses;

            self::$_accounts->set_option( 'all_licenses', $all_licenses, $store );
        }

        /**
         * Update user information.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         *
         * @param bool $store Flush to Database if true.
         */
        private function _store_user( $store = true ) {
            $this->_logger->entrance();

            if ( empty( $this->_user->id ) ) {
                $this->_logger->error( "Empty user ID, can't store user." );

                return;
            }

            $users                     = self::get_all_users();
            $users[ $this->_user->id ] = $this->_user;
            self::$_accounts->set_option( 'users', $users, $store );
        }

        /**
         * Update new updates information.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @param FS_Plugin_Tag|null $update
         * @param bool               $store Flush to Database if true.
         * @param bool|number        $plugin_id
         */
        private function _store_update( $update, $store = true, $plugin_id = false ) {
            $this->_logger->entrance();

            if ( $update instanceof FS_Plugin_Tag ) {
                $update->updated = time();
            }

            if ( ! is_numeric( $plugin_id ) ) {
                $plugin_id = $this->_plugin->id;
            }

            $updates               = self::get_all_updates();
            $updates[ $plugin_id ] = $update;
            self::$_accounts->set_option( 'updates', $updates, $store );
        }

        /**
         * Update new updates information.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.6
         *
         * @param FS_Plugin[] $plugin_addons
         * @param bool        $store Flush to Database if true.
         */
        private function _store_addons( $plugin_addons, $store = true ) {
            $this->_logger->entrance();

            $addons                       = self::get_all_addons();
            $addons[ $this->_plugin->id ] = $plugin_addons;
            self::$_accounts->set_option( 'addons', $addons, $store );
        }

        /**
         * Delete plugin's associated add-ons.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.8
         *
         * @param bool $store
         *
         * @return bool
         */
        private function _delete_account_addons( $store = true ) {
            $all_addons = self::get_all_account_addons();

            if ( ! isset( $all_addons[ $this->_plugin->id ] ) ) {
                return false;
            }

            unset( $all_addons[ $this->_plugin->id ] );

            self::$_accounts->set_option( 'account_addons', $all_addons, $store );

            return true;
        }

        /**
         * Update account add-ons list.
         *
         * @author   Vova Feldman (@svovaf)
         * @since    1.0.6
         *
         * @param FS_Plugin[] $addons
         * @param bool        $store Flush to Database if true.
         */
        private function _store_account_addons( $addons, $store = true ) {
            $this->_logger->entrance();

            $all_addons                       = self::get_all_account_addons();
            $all_addons[ $this->_plugin->id ] = $addons;
            self::$_accounts->set_option( 'account_addons', $all_addons, $store );
        }

        /**
         * Purges the cache for the valid user licenses API call so that when the `Account` or `Add-Ons` page is loaded,
         * the valid user licenses will be fetched again and the account add-ons may be updated.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.2.4
         */
        private function purge_valid_user_licenses_cache() {
            if ( ! $this->is_registered() ) {
                return;
            }

            $this->get_api_user_scope()->purge_cache( $this->get_valid_user_licenses_endpoint() );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @param array       $all_licenses
         * @param number|null $site_license_id
         * @param bool        $include_parent_licenses
         *
         * @return array
         */
        private function get_foreign_licenses_info( $all_licenses, $site_license_id = null, $include_parent_licenses = false ) {
            $foreign_licenses = array(
                'ids'          => array(),
                'license_keys' => array()
            );

            $parent_license_ids_map = array();

            foreach ( $all_licenses as $license ) {
                if ( $license->user_id == $this->_user->id || $license->id == $site_license_id ) {
                    continue;
                }

                $foreign_licenses['ids'][]          = $license->id;
                $foreign_licenses['license_keys'][] = $license->secret_key;

                if (
                    $include_parent_licenses &&
                    is_object( $this->_license ) &&
                    FS_Plugin_License::is_valid_id( $this->_license->parent_license_id ) &&
                    ! isset( $parent_license_ids_map[ $this->_license->parent_license_id ] )
                ) {
                    /**
                     * Include the parent license's info only if it has not been included before since child licenses
                     * can have the same parent license.
                     */
                    $foreign_licenses['ids'][]          = $this->_license->parent_license_id;
                    $foreign_licenses['license_keys'][] = $license->secret_key;

                    $parent_license_ids_map[ $this->_license->parent_license_id ] = true;
                }
            }

            if ( empty( $foreign_licenses['ids'] ) ) {
                $foreign_licenses = array();
            }

            return $foreign_licenses;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @return string
         */
        private function get_valid_user_licenses_endpoint() {
            $user_licenses_endpoint = '/licenses.json?type=active' .
                ( FS_Plugin::is_valid_id( $this->get_bundle_id() ) ? '&is_enriched=true' : '' );

            $foreign_licenses = $this->get_foreign_licenses_info( self::get_all_licenses( $this->_module_id ), null, true );

            if ( ! empty ( $foreign_licenses ) ) {
                $foreign_licenses = array(
                    // Prefix with `+` to tell the server to include foreign licenses in the licenses collection.
                    'ids'          => ( urlencode( '+' ) . implode( ',', $foreign_licenses['ids'] ) ),
                    'license_keys' => implode( ',', array_map( 'urlencode', $foreign_licenses['license_keys'] ) )
                );

                $user_licenses_endpoint = add_query_arg( $foreign_licenses, $user_licenses_endpoint );
            }

            return $user_licenses_endpoint;
        }

        /**
         * Fetches active licenses that are enriched with product type if there's a context `bundle_id` and bundle
         * licenses enriched with product IDs if there are any. From the licenses, the `get_updated_account_addons`
         * method filters out non–add-on product IDs and stores the add-on IDs.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.2.4
         *
         * @return stdClass[] array
         */
        private function fetch_valid_user_licenses() {
            $this->_logger->entrance();

            $result = $this->get_api_user_scope()->get( $this->get_valid_user_licenses_endpoint() );

            if ( ! $this->is_api_result_object( $result, 'licenses' ) ||
                ! is_array( $result->licenses )
            ) {
                return array();
            }

            return $result->licenses;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.2.4
         *
         * @return number[] Account add-on IDs.
         */
        function get_updated_account_addons() {
            $addons = $this->get_addons();
            if ( empty( $addons ) ) {
                return array();
            }

            $account_addons = $this->get_account_addons();
            if ( ! is_array( $account_addons ) ) {
                $account_addons = array();
            }

            $user_licenses = $this->is_registered() ?
                $this->fetch_valid_user_licenses() :
                array();

            if ( empty( $user_licenses ) ) {
                return $account_addons;
            }

            $addon_ids = array();
            foreach ( $addons as $addon ) {
                $addon_ids[] = $addon->id;
            }

            $license_product_ids = array();

            foreach ( $user_licenses as $license ) {
                if ( isset( $license->plugin_type ) && 'bundle' === $license->plugin_type ) {
                    $license_product_ids = array_merge( $license_product_ids, $license->products );
                } else {
                    $license_product_ids[] = $license->plugin_id;
                }
            }

            // Filter out non–add-on IDs.
            $new_account_addons = array_intersect( $addon_ids, $license_product_ids );
            if ( count( $new_account_addons ) !== count( $account_addons ) ) {
                $this->_store_account_addons( array_unique( $new_account_addons ) );
            }

            return $new_account_addons;
        }

        /**
         * Store account params in the Database.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.1
         *
         * @param null|int $blog_id Since 2.0.0
         */
        private function _store_account( $blog_id = null ) {
            $this->_logger->entrance();

            $this->_store_site( false, $blog_id );
            $this->_store_user( false );
            $this->_store_plans( false );
            $this->_store_licenses( false );

            self::$_accounts->store( $blog_id );
        }

        /**
         * Sync user's information.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         * @uses   FS_Api
         */
        private function _handle_account_user_sync() {
            $this->_logger->entrance();

            $api = $this->get_api_user_scope();

            // Get user's information.
            $user = $api->get( '/', true );

            if ( isset( $user->id ) ) {
                $this->_user->first = $user->first;
                $this->_user->last  = $user->last;
                $this->_user->email = $user->email;

                $is_menu_item_account_visible = $this->is_submenu_item_visible( 'account' );

                if ( $user->is_verified &&
                     ( ! isset( $this->_user->is_verified ) || false === $this->_user->is_verified )
                ) {
                    $this->_user->is_verified = true;

                    $this->do_action( 'account_email_verified', $user->email );

                    $this->_admin_notices->add(
                        $this->get_text_inline( 'Your email has been successfully verified - you are AWESOME!', 'email-verified-message' ),
                        $this->get_text_x_inline( 'Right on', 'a positive response', 'right-on' ) . '!',
                        'success',
                        // Make admin sticky if account menu item is invisible,
                        // since the page will be auto redirected to the plugin's
                        // main settings page, and the non-sticky message
                        // will disappear.
                        ! $is_menu_item_account_visible,
                        'email_verified'
                    );
                }

                // Flush user details to DB.
                $this->_store_user();

                $this->do_action( 'after_account_user_sync', $user );

                /**
                 * If account menu item is hidden, redirect to plugin's main settings page.
                 *
                 * @author Vova Feldman (@svovaf)
                 * @since  1.1.6
                 *
                 * @link   https://github.com/Freemius/wordpress-sdk/issues/6
                 */
                if ( ! $is_menu_item_account_visible ) {
                    fs_redirect( $this->_get_admin_page_url() );
                }
            }
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         * @uses   FS_Api
         *
         * @param number|bool $license_id
         *
         * @return FS_Subscription|object|bool
         */
        private function _fetch_site_license_subscription( $license_id = false ) {
            $this->_logger->entrance();
            $api = $this->get_api_site_scope();

            if ( ! is_numeric( $license_id ) ) {
                $license_id = FS_Plugin_License::is_valid_id( $this->_license->parent_license_id ) ?
                    $this->_license->parent_license_id :
                    $this->_license->id;
            }

            $result = $api->get( "/licenses/{$license_id}/subscriptions.json", true );

            return ! isset( $result->error ) ?
                ( ( is_array( $result->subscriptions ) && 0 < count( $result->subscriptions ) ) ?
                    new FS_Subscription( $result->subscriptions[0] ) :
                    false
                ) :
                $result;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         * @uses   FS_Api
         *
         * @param number|bool $plan_id
         *
         * @return FS_Plugin_Plan|object
         */
        private function _fetch_site_plan( $plan_id = false ) {
            $this->_logger->entrance();
            $api = $this->get_api_site_scope();

            if ( ! is_numeric( $plan_id ) ) {
                $plan_id = $this->_site->plan_id;
            }

            $plan = $api->get( "/plans/{$plan_id}.json", true );

            return ! isset( $plan->error ) ? new FS_Plugin_Plan( $plan ) : $plan;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         * @uses   FS_Api
         *
         * @return FS_Plugin_Plan[]|object
         */
        private function _fetch_plugin_plans() {
            $this->_logger->entrance();
            $api = $this->get_current_or_network_user_api_scope();

            /**
             * @since 1.2.3 When running in DEV mode, retrieve pending plans as well.
             */
            $result = $api->get( $this->add_show_pending( "/plugins/{$this->_module_id}/plans.json" ), true );

            if ( $this->is_api_result_object( $result, 'plans' ) && is_array( $result->plans ) ) {
                for ( $i = 0, $len = count( $result->plans ); $i < $len; $i ++ ) {
                    $result->plans[ $i ] = new FS_Plugin_Plan( $result->plans[ $i ] );
                }

                $result = $result->plans;
            }

            return $result;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param number $plan_id
         *
         * @return \FS_Plugin_Plan|object
         */
        private function fetch_plan_by_id( $plan_id ) {
            $this->_logger->entrance();
            $api = $this->get_current_or_network_user_api_scope();

            $result = $api->get( "/plugins/{$this->_module_id}/plans/{$plan_id}.json", true );

            return $this->is_api_result_entity( $result ) ?
                new FS_Plugin_Plan( $result ) :
                $result;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         * @uses   FS_Api
         *
         * @param number|bool $plugin_id
         * @param number|bool $site_license_id
         * @param array       $foreign_licenses @since 2.0.0. This is used by network-activated plugins.
         * @param number|null $blog_id
         *
         * @return FS_Plugin_License[]|object
         */
        private function _fetch_licenses(
            $plugin_id = false,
            $site_license_id = false,
            $foreign_licenses = array(),
            $blog_id = null
        ) {
            $this->_logger->entrance();

            $api = $this->get_api_user_scope();

            if ( ! is_numeric( $plugin_id ) ) {
                $plugin_id = $this->_plugin->id;
            }

            $user_licenses_endpoint = "/plugins/{$plugin_id}/licenses.json?is_enriched=true";
            if ( ! empty ( $foreign_licenses ) ) {
                $foreign_licenses = array(
                    // Prefix with `+` to tell the server to include foreign licenses in the licenses collection.
                    'ids'          => ( urlencode( '+' ) . implode( ',', $foreign_licenses['ids'] ) ),
                    'license_keys' => implode( ',', array_map( 'urlencode', $foreign_licenses['license_keys'] ) )
                );

                $user_licenses_endpoint = add_query_arg( $foreign_licenses, $user_licenses_endpoint );
            }

            $result = $api->get( $user_licenses_endpoint, true );

            $is_site_license_synced = false;

            $api_errors = array();

            if ( $this->is_api_result_object( $result, 'licenses' ) &&
                 is_array( $result->licenses )
            ) {
                for ( $i = 0, $len = count( $result->licenses ); $i < $len; $i ++ ) {
                    $result->licenses[ $i ] = new FS_Plugin_License( $result->licenses[ $i ] );

                    if ( ( ! $is_site_license_synced ) && is_numeric( $site_license_id ) ) {
                        $is_site_license_synced = ( $site_license_id == $result->licenses[ $i ]->id );
                    }
                }

                $result = $result->licenses;
            } else {
                $api_errors[] = $result;
                $result       = array();
            }

            if ( ! $is_site_license_synced ) {
                if ( ! is_null( $blog_id ) ) {
                    /**
                     * If blog ID is not null, the request is for syncing of the license of a single site via the
                     * network-level "Account" page.
                     *
                     * @author Leo Fajardo (@leorw)
                     */
                    $this->switch_to_blog( $blog_id );
                }

                $api = $this->get_api_site_scope();

                if ( is_numeric( $site_license_id ) ) {
                    // Try to retrieve a foreign license that is linked to the install.
                    $api_result = $api->call( '/licenses.json?is_enriched=true' );

                    if ( $this->is_api_result_object( $api_result, 'licenses' ) &&
                         is_array( $api_result->licenses )
                    ) {
                        $licenses = $api_result->licenses;

                        if ( ! empty( $licenses ) ) {
                            $result[] = new FS_Plugin_License( $licenses[0] );
                        }
                    } else {
                        $api_errors[] = $api_result;
                    }
                } else if (
                    is_object( $this->_license ) &&
                    /**
                     * Sync only if the license belongs to the context plugin. `$plugin_id` can be an add-on ID while
                     * the FS instance that does the syncing is the parent FS instance.
                     *
                     * @author Leo Fajardo (@leorw)
                     * @since 2.3.0
                     */
                    $this->_license->plugin_id == $plugin_id
                ) {
                    $is_license_in_result = false;
                    if ( ! empty( $result ) ) {
                        foreach ( $result as $license ) {
                            if ( $license->id == $this->_license->id ) {
                                $is_license_in_result = true;
                                break;
                            }
                        }
                    }

                    if ( ! $is_license_in_result ) {
                        // Fetch foreign license by ID and license key.
                        $license = $api->get( "/licenses/{$this->_license->id}.json?license_key=" .
                                              urlencode( $this->_license->secret_key ) . '&is_enriched=true' );

                        if ( $this->is_api_result_entity( $license ) ) {
                            $result[] = new FS_Plugin_License( $license );
                        } else {
                            $api_errors[] = $license;
                        }
                    }
                }

                if ( ! is_null( $blog_id ) ) {
                    $this->switch_to_blog( $this->_storage->network_install_blog_id );
                }
            }

            if ( is_array( $result ) && 0 < count( $result ) ) {
                // If found at least one license, return license collection even if there are errors.
                return $result;
            }

            if ( ! empty( $api_errors ) ) {
                // If found any errors and no licenses, return first error.
                return $api_errors[0];
            }

            // Fallback to empty licenses list.
            return $result;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param number $license_id
         * @param string $license_key
         *
         * @return \FS_Plugin_License|object
         */
        private function fetch_license_by_key( $license_id, $license_key ) {
            $this->_logger->entrance();

            $api = $this->get_current_or_network_user_api_scope();

            $result = $api->get( "/licenses/{$license_id}.json?license_key=" . urlencode( $license_key ) );

            return $this->is_api_result_entity( $result ) ?
                new FS_Plugin_License( $result ) :
                $result;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.0
         * @uses   FS_Api
         *
         * @param number|bool $plugin_id
         * @param bool        $flush
         *
         * @return FS_Payment[]|object
         */
        function _fetch_payments( $plugin_id = false, $flush = false ) {
            $this->_logger->entrance();

            $api = $this->get_api_user_scope();

            if ( ! is_numeric( $plugin_id ) ) {
                $plugin_id = $this->_plugin->id;
            }

            $include_bundles = (
                is_object( $this->_plugin ) &&
                FS_Plugin::is_valid_id( $this->_plugin->bundle_id )
            );

            $result = $api->get(
                "/plugins/{$plugin_id}/payments.json?include_addons=true" . ($include_bundles ? '&include_bundles=true' : ''),
                $flush
            );

            if ( ! isset( $result->error ) ) {
                for ( $i = 0, $len = count( $result->payments ); $i < $len; $i ++ ) {
                    $result->payments[ $i ] = new FS_Payment( $result->payments[ $i ] );
                }
                $result = $result->payments;
            }

            return $result;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.5
         * @uses   FS_Api
         *
         * @param bool $flush
         *
         * @return \FS_Billing|mixed
         */
        function _fetch_billing( $flush = false ) {
            require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-billing.php';

            $billing = $this->get_api_user_scope()->get( 'billing.json', $flush );

            if ( $this->is_api_result_entity( $billing ) ) {
                $billing = new FS_Billing( $billing );
            }

            return $billing;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         *
         * @param FS_Plugin_License[] $licenses
         * @param number              $module_id
         */
        private function _update_licenses( $licenses, $module_id ) {
            $this->_logger->entrance();

            if ( is_array( $licenses ) ) {
                for ( $i = 0, $len = count( $licenses ); $i < $len; $i ++ ) {
                    $licenses[ $i ]->updated = time();
                }
            }

            $this->_store_licenses( true, $module_id, $licenses );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @param bool|number $plugin_id
         * @param bool        $flush      Since 1.1.7.3
         * @param int         $expiration Since 1.2.2.7
         * @param bool|string $newer_than Since 2.2.1
         *
         * @return object|false New plugin tag info if exist.
         */
        private function _fetch_newer_version( $plugin_id = false, $flush = true, $expiration = WP_FS__TIME_24_HOURS_IN_SEC, $newer_than = false ) {
            $latest_tag = $this->_fetch_latest_version( $plugin_id, $flush, $expiration, $newer_than );

            if ( ! is_object( $latest_tag ) ) {
                return false;
            }

            $plugin_version = $this->get_plugin_version();

            // Check if version is actually newer.
            $has_new_version =
                // If it's an non-installed add-on then always return latest.
                ( $this->_is_addon_id( $plugin_id ) && ! $this->is_addon_activated( $plugin_id ) ) ||
                // Compare versions.
                version_compare( $plugin_version, $latest_tag->version, '<' );

            $this->_logger->departure( $has_new_version ? 'Found newer plugin version ' . $latest_tag->version : 'No new version' );

            $is_latest_version_beta = ( 'beta' === $latest_tag->release_mode );

            $this->_storage->beta_data = array(
                'is_beta' => $is_latest_version_beta,
                'version' => $latest_tag->version
            );

            return $has_new_version ? $latest_tag : false;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         *
         * @param bool|number $plugin_id
         * @param bool        $flush      Since 1.1.7.3
         * @param int         $expiration Since 1.2.2.7
         * @param bool|string $newer_than Since 2.2.1
         *
         * @return bool|FS_Plugin_Tag
         */
        function get_update( $plugin_id = false, $flush = true, $expiration = FS_Plugin_Updater::UPDATES_CHECK_CACHE_EXPIRATION, $newer_than = false ) {
            $this->_logger->entrance();

            if ( ! is_numeric( $plugin_id ) ) {
                $plugin_id = $this->_plugin->id;
            }

            $this->check_updates( true, $plugin_id, $flush, $expiration, $newer_than );
            $updates = $this->get_all_updates();

            return isset( $updates[ $plugin_id ] ) && is_object( $updates[ $plugin_id ] ) ? $updates[ $plugin_id ] : false;
        }

        /**
         * Check if site assigned with active license.
         *
         * @author     Vova Feldman (@svovaf)
         * @since      1.0.6
         *
         * @deprecated Please use has_active_valid_license() instead because license can be cancelled.
         */
        function has_active_license() {
            return (
                is_object( $this->_license ) &&
                is_numeric( $this->_license->id ) &&
                ! $this->_license->is_expired()
            );
        }

        /**
         * Check if site assigned with active & valid (not expired) license.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1
         *
         * @param bool $check_expiration
         */
        function has_active_valid_license( $check_expiration = true ) {
            return self::is_active_valid_license( $this->_license, $check_expiration );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.3.1
         */
        function is_data_debug_mode() {
            if ( is_null( $this->is_whitelabeled ) || ! $this->is_whitelabeled ) {
                return false;
            }

            $fs = $this->is_addon() ?
                $this->get_parent_instance() :
                $this;

            if ( $fs->is_network_active() && fs_is_network_admin() ) {
                $is_developer_license_debug_mode = get_site_transient( "fs_{$this->get_id()}_data_debug_mode" );
            } else {
                $is_developer_license_debug_mode = get_transient( "fs_{$this->get_id()}_data_debug_mode" );
            }

            return ( 'true' === $is_developer_license_debug_mode );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.3.1
         */
        function _set_data_debug_mode() {
            if ( ! $this->is_whitelabeled( true ) ) {
                return;
            }

            $license_or_user_key = fs_request_get_raw( 'license_or_user_key' );

            $transient_value = ( ! empty( $license_or_user_key ) ) ?
                'true' :
                'false';

            if ( 'true' === $transient_value ) {
                $stored_key = $this->_storage->get( ! FS_User::is_valid_id( $this->_storage->last_license_user_id ) ?
                    'last_license_key' :
                    'last_license_user_key'
                );

                if ( md5( $license_or_user_key ) !== $stored_key ) {
                    $this->shoot_ajax_failure( sprintf(
                        '%s... %s',
                        $this->get_text_x_inline( 'Oops', 'exclamation', 'oops' ),
                        $this->get_text_inline(
                            'seems like the key you entered doesn\'t match our records.',
                            'developer-or-license-not-found'
                        )
                    ) );
                }
            }

            if ( $this->is_network_active() && fs_is_network_admin() ) {
                set_site_transient(
                    "fs_{$this->get_id()}_data_debug_mode",
                    $transient_value,
                    WP_FS__TIME_24_HOURS_IN_SEC / 24
                );
            } else {
                set_transient(
                    "fs_{$this->get_id()}_data_debug_mode",
                    $transient_value,
                    WP_FS__TIME_24_HOURS_IN_SEC / 24
                );
            }

            if ( 'true' === $transient_value ) {
                $this->_admin_notices->add_sticky(
                    $this->get_text_inline(
                        'Debug mode was successfully enabled and will be automatically disabled in 60 min. You can also disable it earlier by clicking the "Stop Debug" link.',
                        'data_debug_mode_enabled'
                    ),
                    'data_debug_mode_enabled'
                );
            }

            $this->shoot_ajax_success();
        }

        /**
         * Check if a given license is active & valid (not expired).
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.1.3
         *
         * @param FS_Plugin_License $license
         * @param bool              $check_expiration
         *
         * @return bool
         */
        private static function is_active_valid_license( $license, $check_expiration = true ) {
            return (
                is_object( $license ) &&
                FS_Plugin_License::is_valid_id( $license->id ) &&
                $license->is_active() &&
                ( ! $check_expiration || $license->is_valid() )
            );
        }

        /**
         * Checks if there's any site that is associated with an active & valid license.
         * This logic is used to determine if the admin can download the premium code base from a network level admin.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.1.3
         *
         * @return bool
         */
        function has_any_active_valid_license() {
            if ( ! fs_is_network_admin() ) {
                return $this->has_active_valid_license();
            }

            $installs            = $this->get_blog_install_map();
            $all_plugin_licenses = self::get_all_licenses( $this->_module_id );

            foreach ( $installs as $blog_id => $install ) {
                if ( ! FS_Plugin_License::is_valid_id( $install->license_id ) ) {
                    continue;
                }

                foreach ( $all_plugin_licenses as $license ) {
                    if ( $license->id == $install->license_id ) {
                        if ( self::is_active_valid_license( $license ) ) {
                            return true;
                        }
                    }
                }
            }

            return false;
        }

        /**
         * Check if site assigned with license with enabled features.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @return bool
         */
        function has_features_enabled_license() {
            return (
                is_object( $this->_license ) &&
                is_numeric( $this->_license->id ) &&
                $this->_license->is_features_enabled()
            );
        }

        /**
         * Checks if the product is activated with a bundle license.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.4.0
         *
         * @return bool
         */
        function is_activated_with_bundle_license() {
            if ( ! $this->has_features_enabled_license() ) {
                return false;
            }

            return FS_Plugin_License::is_valid_id( $this->_license->parent_license_id );
        }

        /**
         * Check if user is a trial or have feature enabled license.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7
         *
         * @return bool
         */
        function can_use_premium_code() {
            return $this->is_trial() || $this->has_features_enabled_license();
        }

        /**
         * Checks if the current user can activate plugins or switch themes. Note that this method should only be used
         * after the `init` action is triggered because it is using `current_user_can()` which is only functional after
         * the context user is authenticated.
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.2.2
         *
         * @return bool
         */
        function is_user_admin() {
            /**
             * Require a super-admin when network activated, running from the network level OR if
             * running from the site level but not delegated the opt-in.
             *
             * @author Vova Feldman (@svovaf)
             * @since  2.0.0
             */
            if ( $this->_is_network_active &&
                 ( fs_is_network_admin() || ! $this->is_delegated_connection() )
            ) {
                return is_super_admin();
            }

            return ( $this->is_plugin() && current_user_can( is_multisite() ? 'manage_options' : 'activate_plugins' ) )
                   || ( $this->is_theme() && current_user_can( 'switch_themes' ) );
        }

        /**
         * Sync site's plan.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         *
         * @uses   FS_Api
         *
         * @param bool $background             Hints the method if it's a background sync. If false, it means that was initiated by
         *                                     the admin.
         * @param bool $is_context_single_site @since 2.0.0. This is used when syncing a license for a single install from the
         *                                     network-level "Account" page.
         * @param int|null $current_blog_id    @since 2.2.3. This is passed from the `execute_cron` method and used by the
         *                                     `_sync_plugin_license` method in order to switch to the previous blog when sending
         *                                      updates for a single site in case `execute_cron` has switched to a different blog.
         */
        private function _sync_license( $background = false, $is_context_single_site = false, $current_blog_id = null ) {
            $this->_logger->entrance();

            $plugin_id = fs_request_get( 'plugin_id', $this->get_id() );

            $is_addon_sync = ( ! $this->_plugin->is_addon() && $plugin_id != $this->get_id() );

            if ( $is_addon_sync ) {
                $this->_sync_addon_license( $plugin_id, $background );
            } else {
                $this->_sync_plugin_license( $background, true, $is_context_single_site, $current_blog_id );
            }

            $this->do_action( 'after_account_plan_sync', $this->get_plan_name() );
        }

        /**
         * Sync plugin's add-on license.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         * @uses   FS_Api
         *
         * @param number $addon_id
         * @param bool   $background
         */
        private function _sync_addon_license( $addon_id, $background ) {
            $this->_logger->entrance();

            if ( $this->is_addon_activated( $addon_id ) ) {
                // If already installed, use add-on sync.
                $fs_addon = self::get_instance_by_id( $addon_id );

                if (
                    // Add-on is network activated and network integrated.
                    $fs_addon->is_network_active() ||
                    // Background sync cron.
                    self::is_cron() ||
                    // Add-on is not network activated or not network integrated.
                    ! fs_is_network_admin()
                ) {
                    $fs_addon->_sync_license( $background );

                    return;
                }
            }

            // Validate add-on exists.
            $addon = $this->get_addon( $addon_id );

            if ( ! is_object( $addon ) ) {
                return;
            }

            // Add add-on into account add-ons.
            $account_addons = $this->get_account_addons();
            if ( ! is_array( $account_addons ) ) {
                $account_addons = array();
            }
            $account_addons[] = $addon->id;
            $account_addons   = array_unique( $account_addons );
            $this->_store_account_addons( $account_addons );

            // Load add-on licenses.
            $licenses = $this->_fetch_licenses( $addon->id );

            // Sync add-on licenses.
            if ( $this->is_array_instanceof( $licenses, 'FS_Plugin_License' ) ) {
                $this->_update_licenses( $licenses, $addon->id );

                if ( ! $this->is_addon_installed( $addon->id ) && FS_License_Manager::has_premium_license( $licenses ) ) {
                    $plans_result = $this->get_api_site_or_plugin_scope()->get( $this->add_show_pending( "/addons/{$addon_id}/plans.json" ) );

                    if ( ! isset( $plans_result->error ) ) {
                        $plans = array();
                        foreach ( $plans_result->plans as $plan ) {
                            $plans[] = new FS_Plugin_Plan( $plan );
                        }

                        $this->_admin_notices->add_sticky(
                            sprintf(
                                ( FS_Plan_Manager::instance()->has_free_plan( $plans ) ?
                                    $this->get_text_inline( 'Your %s Add-on plan was successfully upgraded.', 'addon-successfully-upgraded-message' ) :
                                    /* translators: %s:product name, e.g. Facebook add-on was successfully... */
                                    $this->get_text_inline( '%s Add-on was successfully purchased.', 'addon-successfully-purchased-message' ) ),
                                $addon->title
                            ) . ' ' . $this->get_latest_download_link(
                                $this->get_text_inline( 'Download the latest version', 'download-latest-version' ),
                                $addon_id
                            ),
                            'addon_plan_upgraded_' . $addon->slug,
                            $this->get_text_x_inline( 'Yee-haw', 'interjection expressing joy or exuberance', 'yee-haw' ) . '!'
                        );
                    }
                }
            }
        }

        /**
         * Sync site's plugin plan.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         * @uses   FS_Api
         *
         * @param bool $background             Hints the method if it's a background sync. If false, it means that was initiated by the admin.
         * @param bool $send_installs_update   Since 2.0.0
         * @param bool $is_context_single_site Since 2.0.0. This is used when sending an update for a single install and
         *                                     syncing its license from the network-level "Account" page (e.g.: after
         *                                     activating a license only for the single install).
         * @param int|null $current_blog_id    Since 2.2.3. This is passed from the `execute_cron` method so that it
         *                                     can be used here to switch to the previous blog in case `execute_cron`
         *                                     has switched to a different blog.
         */
        private function _sync_plugin_license(
            $background = false,
            $send_installs_update = true,
            $is_context_single_site = false,
            $current_blog_id = null
        ) {
            $this->_logger->entrance();

            $plan_change = 'none';

            $is_site_level_sync = ( $is_context_single_site || fs_is_blog_admin() || ! $this->_is_network_active );

            if ( ! $send_installs_update ) {
                $site = $this->_site;
            } else {
                /**
                 * Sync site info.
                 *
                 * @todo This line will execute install sync on a daily basis, even if running the free version (for opted-in users). The reason we want to keep it that way is for cases when the user was a paying customer, then there was a failure in subscription payment, and then after some time the payment was successful. This could be heavily optimized. For example, we can skip the $flush if the current install was never associated with a paid version.
                 */
                if ( $is_site_level_sync ) {
                    /**
                     * Switch to the previous blog since `execute_cron` may have switched to a different blog.
                     *
                     * @author Leo Fajardo (@leorw)
                     * @since 2.2.3
                     */
                    if ( is_numeric( $current_blog_id ) ) {
                        $this->switch_to_blog( $current_blog_id );
                    }

                    $result   = $this->send_install_update( array(), true, true );
                    $is_valid = $this->is_api_result_entity( $result );
                } else {
                    $result   = $this->send_installs_update( array(), true, true );
                    $is_valid = $this->is_api_result_object( $result, 'installs' );
                }

                if ( ! $is_valid ) {
                    if ( $is_context_single_site ) {
                        // Switch back to the main blog so that the following logic will have the right entities.
                        $this->switch_to_blog( $this->_storage->network_install_blog_id );
                    }

                    // Show API message only if not background sync or if paying customer.
                    if ( ! $background || $this->is_paying() ) {
                        // Try to ping API to see if not blocked.
                        if ( FS_Api::is_blocked( $result ) ) {
                            /**
                             * @author Vova Feldman (@svovaf)
                             * @since  1.1.6 Only show message related to one of the Freemius powered plugins. Once it will be resolved it will fix the issue for all plugins anyways. There's no point to scare users with multiple error messages.
                             */
                            if ( ! self::$_global_admin_notices->has_sticky( 'api_blocked' ) ) {
                                // Add notice immediately if not a background sync.
                                $add_notice = ( ! $background );

                                if ( ! $add_notice ) {
                                    $counter = (int) get_transient( '_fs_api_connection_retry_counter' );

                                    // We only want to add the notice after 3 consecutive failures.
                                    $add_notice = ( 3 <= $counter );

                                    if ( ! $add_notice ) {
                                        /**
                                         * Update counter transient only if notice shouldn't be added. If it is added the transient will be reset anyway, because the retries mechanism should only start counting if the admin isn't aware of the connectivity issue.
                                         *
                                         * Also, since the background sync happens once a day, setting the transient expiration for a week should be enough to count 3 failures, if there's an actual connectivity issue.
                                         */
                                        set_transient( '_fs_api_connection_retry_counter', $counter + 1, WP_FS__TIME_WEEK_IN_SEC );
                                    }
                                }

                                // Add notice instantly for not-background sync and only after 3 failed attempts for background sync.
                                if ( $add_notice ) {
                                    self::$_global_admin_notices->add(
                                        $this->generate_api_blocked_notice_message_from_result( $result ),
                                        '',
                                        'error',
                                        $background,
                                        'api_blocked'
                                    );

                                    add_action( 'admin_footer', array( 'Freemius', '_add_api_connectivity_notice_handler_js' ) );

                                    // Notice was just shown, reset connectivity counter.
                                    delete_transient( '_fs_api_connection_retry_counter' );
                                }
                            }
                        } else if ( is_object( $result ) ) {
                            // Authentication params are broken.
                            $this->_admin_notices->add(
                                $this->get_text_inline( 'It seems like one of the authentication parameters is wrong. Update your Public Key, Secret Key & User ID, and try again.', 'wrong-authentication-param-message' ) . '<br> ' . $this->get_text_inline( 'Error received from the server:', 'server-error-message' ) . var_export( $result->error, true ),
                                '',
                                'error'
                            );
                        }
                    }

                    // No reason to continue with license sync while there are API issues.
                    return;
                }

                // API is working now. Delete the transient and start afresh.
                delete_transient('_fs_api_connection_retry_counter');

                if ( $is_site_level_sync ) {
                    $site = new FS_Site( $result );
                } else {
                    // Map site addresses to their blog IDs.
                    $address_to_blog_map = $this->get_address_to_blog_map();

                    // Find the current context install.
                    $site = null;
                    foreach ( $result->installs as $install ) {
                        if ( $install->id == $this->_site->id ) {
                            $site = new FS_Site( $install );
                        } else {
                            $address = trailingslashit( fs_strip_url_protocol( $install->url ) );
                            $blog_id = $address_to_blog_map[ $address ];

                            $this->_store_site( true, $blog_id, new FS_Site( $install ) );
                        }
                    }
                }

                // Sync plans.
                $this->_sync_plans();
            }

            // Remove sticky API connectivity message.
            self::$_global_admin_notices->remove_sticky( 'api_blocked' );

            if ( ! $this->has_paid_plan() ) {
                $this->_site = $site;
                $this->_store_site(
                    true,
                    $is_site_level_sync ?
                        null :
                        $this->get_network_install_blog_id()
                );
            } else {
                $context_blog_id = 0;

                if ( $is_context_single_site ) {
                    $context_blog_id = get_current_blog_id();

                    // Switch back to the main blog in order to properly sync the license.
                    $this->switch_to_blog( $this->_storage->network_install_blog_id );
                }

                /**
                 * Sync licenses. Pass the site's license ID so that the foreign licenses will be fetched if the license
                 * associated with that ID is not included in the user's licenses collection.
                 * Save previous value to manage remote license renewals.
                 */
                $was_license_expired_before_sync = is_object( $this->_license ) && $this->_license->is_expired();
                $this->_sync_licenses(
                    $site->license_id,
                    ( $is_context_single_site ?
                        $context_blog_id :
                        null
                    )
                );

                if ( $is_context_single_site ) {
                    $this->switch_to_blog( $context_blog_id );
                }

                // Check if plan / license changed.
                if ( $site->plan_id != $this->_site->plan_id ||
                     // Check if trial started.
                     $site->trial_plan_id != $this->_site->trial_plan_id ||
                     $site->trial_ends != $this->_site->trial_ends ||
                     // Check if license changed.
                     $site->license_id != $this->_site->license_id
                ) {
                    if ( $site->is_trial() && ( ! $this->_site->is_trial() || $site->trial_ends != $this->_site->trial_ends ) ) {
                        // New trial started.
                        $this->_site = $site;
                        $plan_change = 'trial_started';

                        // For trial with subscription use-case.
                        $new_license = is_null( $site->license_id ) ? null : $this->_get_license_by_id( $site->license_id );

                        if ( is_object( $new_license ) && $new_license->is_valid() ) {
                            $this->_site = $site;
                            $this->_update_site_license( $new_license );
                            $this->_store_licenses();

                            $this->_sync_site_subscription( $this->_license );
                        }
                    } else if ( $this->_site->is_trial() && ! $site->is_trial() && ! is_numeric( $site->license_id ) ) {
                        // Was in trial, but now trial expired and no license ID.
                        // New trial started.
                        $this->_site = $site;
                        $plan_change = 'trial_expired';
                    } else {
                        $is_free = $this->is_free_plan();

                        // Make sure license exist and not expired.
                        $new_license = is_null( $site->license_id ) ?
                            null :
                            $this->_get_license_by_id( $site->license_id );

                        if ( $is_free && is_null( $new_license ) && $this->has_any_license() && $this->_license->is_cancelled ) {
                            // License cancelled.
                            $this->_site = $site;
                            $this->_update_site_license( $new_license );
                            $this->_store_licenses();

                            $plan_change = 'cancelled';
                        } else if ( $is_free && ( ( ! is_object( $new_license ) || $new_license->is_expired() ) ) ) {
                            // The license is expired, so ignore upgrade method.
                            $this->_site = $site;
                        } else {
                            // License changed.
                            $this->_site = $site;

                            /**
                             * IMPORTANT:
                             * The line below should be executed before trying to activate the license on the rest of the network, otherwise, the license' activation counters may be out of sync + there's no need to activate the license on the context site since it's already activated on it.
                             *
                             * @author Vova Feldman (@svovaf)
                             * @since  2.0.0
                             */
                            $this->_update_site_license( $new_license );

                            if ( ! $is_context_single_site &&
                                 fs_is_network_admin() &&
                                 $this->_is_network_active &&
                                 $new_license->quota > 1 &&
                                 get_blog_count() > 1
                            ) {
                                // See if license can activated on all sites.
                                if ( ! $this->try_activate_license_on_network( $this->_user, $new_license ) ) {
                                    if ( ! fs_request_get_bool( 'auto_install' ) ) {
                                        // Open the license activation dialog box on the account page.
                                        add_action( 'admin_footer', array(
                                            &$this,
                                            '_open_license_activation_dialog_box'
                                        ) );
                                    }
                                }
                            }

                            $this->_store_licenses();

                            $plan_change = $is_free ?
                                ( $this->is_only_premium() ? 'activated' : 'upgraded' ) :
                                ( is_object( $new_license ) ?
                                    'changed' :
                                    'downgraded' );
                        }
                    }

                    // Store updated site info.
                    $this->_store_site(
                        true,
                        $is_site_level_sync ?
                            null :
                            $this->get_network_install_blog_id()
                    );
                } else {
                    if ( ! is_object( $this->_license ) ) {
                        $this->maybe_update_whitelabel_flag(
                            FS_Plugin_License::is_valid_id( $site->license_id ) ?
                                $this->get_license_by_id( $site->license_id ) :
                                null
                        );
                    } else {
                        $this->maybe_update_whitelabel_flag( $this->_license );

                        if ( $this->_license->is_expired() ) {
                            if ( ! $this->has_features_enabled_license() ) {
                                $this->_deactivate_license();
                                $plan_change = 'downgraded';
                            } else {
                                $last_time_expired_license_notice_was_shown = $this->_storage->get( 'expired_license_notice_shown', 0 );

                                if ( time() - ( 14 * WP_FS__TIME_24_HOURS_IN_SEC ) >= $last_time_expired_license_notice_was_shown ) {
                                    /**
                                     * Show the expired license notice every 14 days.
                                     *
                                     * @author Leo Fajardo (@leorw)
                                     * @since 2.3.1
                                     */
                                    $plan_change = 'expired';
                                }
                            }
                        } else if ( $was_license_expired_before_sync ) {
                            /**
                             * If license was expired but it is not anymore.
                             *
                             *
                             * @author Daniele Alessandra (@danielealessandra)
                             */
                            $plan_change = 'extended';
                        }
                    }

                    if ( is_numeric( $site->license_id ) && is_object( $this->_license ) ) {
                        $this->_sync_site_subscription( $this->_license );
                    }
                }

                if ( ! $this->is_addon() &&
                     $this->_site->is_beta() !== $site->is_beta()
                ) {
                    // Beta flag updated.
                    $this->_site = $site;

                    $this->_store_site(
                        true,
                        $is_site_level_sync ?
                            null :
                            $this->get_network_install_blog_id()
                    );
                }

                if ( $this->is_addon() || $this->has_addons() ) {
                    /**
                     * Purge the valid user licenses cache so that when the "Account" or the "Add-Ons" page is loaded,
                     * an updated valid user licenses collection will be fetched from the server which is used to also
                     * update the account add-ons (add-ons the user has licenses for).
                     *
                     * @author Leo Fajardo (@leorw)
                     * @since 2.2.4
                     */
                    $this->purge_valid_user_licenses_cache();
                }
            }

            $hmm_text = $this->get_text_x_inline( 'Hmm', 'something somebody says when they are thinking about what you have just said.', 'hmm' ) . '...';

            if ( $this->apply_filters( 'has_paid_plan_account', $this->has_paid_plan() ) ) {
                switch ( $plan_change ) {
                    case 'none':
                        if ( ! $background && is_admin() ) {
                            $plan = $this->is_trial() ?
                                $this->get_trial_plan() :
                                $this->get_plan();

                            if ( $plan->is_free() ) {
                                $this->_admin_notices->add(
                                    sprintf(
                                        $this->get_text_inline( 'It looks like you are still on the %s plan. If you did upgrade or change your plan, it\'s probably an issue on our side - sorry.', 'plan-did-not-change-message' ),
                                        '<i><b>' . $plan->title . ( $this->is_trial() ? ' ' . $this->get_text_x_inline( 'Trial', 'trial period', 'trial' ) : '' ) . '</b></i>'
                                    ) . ' ' . sprintf(
                                        '<a href="%s">%s</a>',
                                        $this->contact_url(
                                            'bug',
                                            sprintf( $this->get_text_inline( 'I have upgraded my account but when I try to Sync the License, the plan remains %s.', 'plan-did-not-change-email-message' ),
                                                strtoupper( $plan->name )
                                            )
                                        ),
                                        $this->get_text_inline( 'Please contact us here', 'contact-us-here' )
                                    ),
                                    $hmm_text
                                );
                            }
                        }
                        break;
                    case 'upgraded':
                    case 'activated':
                        $this->add_after_plan_activation_or_upgrade_instructions_notice( 'upgraded' === $plan_change );

                        $this->_admin_notices->remove_sticky( array(
                            'trial_started',
                            'trial_promotion',
                            'trial_expired',
                            'activation_complete',
                            'license_expired',
                        ) );
                        break;
                    case 'extended':
                        $this->_admin_notices->remove_sticky( array(
                            'trial_expired',
                            'license_expired',
                        ) );
                        break;
                    case 'changed':
                        $this->_admin_notices->add_sticky(
                            sprintf(
                                $this->get_text_inline( 'Your plan was successfully changed to %s.', 'plan-changed-to-x-message' ),
                                $this->get_plan_title()
                            ),
                            'plan_changed'
                        );

                        $this->_admin_notices->remove_sticky( array(
                            'trial_started',
                            'trial_promotion',
                            'trial_expired',
                            'activation_complete',
                        ) );
                        break;
                    case 'downgraded':
                        $this->_admin_notices->add_sticky(
                            ($this->has_free_plan() ?
                                sprintf( $this->get_text_inline( 'Your license has expired. You can still continue using the free %s forever.', 'license-expired-blocking-message' ), $this->_module_type ) :
                                /* translators: %1$s: product title; %2$s, %3$s: wrapping HTML anchor element; %4$s: 'plugin', 'theme', or 'add-on'. */
                                sprintf( $this->get_text_inline( 'Your license has expired. %1$sUpgrade now%2$s to continue using the %3$s without interruptions.', 'license-expired-blocking-message_premium-only' ), sprintf('<a href="%s">', $this->pricing_url()), '</a>', $this->get_module_label(true) ) ),
                            'license_expired',
                            $hmm_text
                        );
                        $this->_admin_notices->remove_sticky( 'plan_upgraded' );
                        break;
                    case 'cancelled':
                        $this->_admin_notices->add(
                            $this->get_text_inline( 'Your license has been cancelled. If you think it\'s a mistake, please contact support.', 'license-cancelled' ) . ' ' .
                            sprintf(
                                '<a href="%s">%s</a>',
                                $this->contact_url( 'bug' ),
                                $this->get_text_inline( 'Please contact us here', 'contact-us-here' )
                            ),
                            $hmm_text,
                            'error'
                        );
                        $this->_admin_notices->remove_sticky( 'plan_upgraded' );
                        break;
                    case 'expired':
                        $this->_admin_notices->add_sticky(
                            sprintf( $this->get_text_inline( 'Your license has expired. You can still continue using all the %s features, but you\'ll need to renew your license to continue getting updates and support.', 'license-expired-non-blocking-message' ), $this->get_plan()->title ),
                            'license_expired',
                            $hmm_text
                        );

                        $this->_storage->expired_license_notice_shown = WP_FS__SCRIPT_START_TIME;

                        $this->_admin_notices->remove_sticky( 'plan_upgraded' );
                        break;
                    case 'trial_started':
                        $this->add_complete_upgrade_instructions_notice(
                            sprintf(
                                $this->get_text_inline( 'Your trial has been successfully started.', 'trial-started-message' ),
                                '<i>' . $this->get_plugin_name() . '</i>'
                            ),
                            'trial_started',
                            $this->get_trial_plan()->title
                        );

                        $this->_admin_notices->remove_sticky( array(
                            'trial_promotion',
                        ) );
                        break;
                    case 'trial_expired':
                        $this->_admin_notices->add_sticky(
                            ($this->has_free_plan() ?
                                $this->get_text_inline( 'Your free trial has expired. You can still continue using all our free features.', 'trial-expired-message' ) :
                                /* translators: %1$s: product title; %2$s, %3$s: wrapping HTML anchor element; %4$s: 'plugin', 'theme', or 'add-on'. */
                                sprintf( $this->get_text_inline( 'Your free trial has expired. %1$sUpgrade now%2$s to continue using the %3$s without interruptions.', 'trial-expired-message_premium-only' ), sprintf('<a href="%s">', $this->pricing_url()), '</a>', $this->get_module_label(true))),
                            'trial_expired',
                            $hmm_text
                        );
                        $this->_admin_notices->remove_sticky( array(
                            'trial_started',
                            'trial_promotion',
                            'plan_upgraded',
                        ) );
                        break;
                }
            }

            if ( 'none' !== $plan_change ) {
                if (
                    ! is_object( $this->_license ) ||
                    ! $this->_license->is_whitelabeled
                ) {
                    $this->_admin_notices->remove_sticky( 'license_whitelabeled' );
                }

                $this->do_action( 'after_license_change', $plan_change, $this->get_plan() );
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.4
         *
         * @param mixed $result
         *
         * @return string
         */
        private function generate_api_blocked_notice_message_from_result( $result ) {
            $api_domains = $this->apply_filters( 'api_domains', array(
                'api.freemius.com',
                'wp.freemius.com',
            ) );

            $api_domains_list_items = '';

            foreach( $api_domains as $api_domain ) {
                $api_domains_list_items .= "<li>{$api_domain}</li>";
            }
            
            $error_message = sprintf(
                $this->get_text_inline( 'Your server is blocking the access to Freemius\' API, which is crucial for %1$s synchronization. Please contact your host to whitelist the following domains:%2$s', 'server-blocking-access' ),
                $this->get_plugin_name(),
                "<ol>{$api_domains_list_items}</ol><a href='#' class='fs-api-request-error-show-details-link'>" . $this->get_text_inline( 'Show error details', 'show-error-details' ) . " <span class='dashicons dashicons-arrow-down-alt2'></span></a>"
            );

            $error_message =
                "<div>{$error_message}</div>" .
                '<div class="fs-api-request-error-details" style="display: none">' .
                    '<strong>' . $this->get_text_inline( 'Error received from the server:', 'server-error-message' ) . '</strong><br>' .
                    $result->error->message .
                '</div>';

            return $error_message;
        }

        /**
         * Include the required JS at the footer of the admin to trigger the license activation dialog box.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         */
        public function _open_license_activation_dialog_box() {
            $vars = array( 'license_id' => $this->_site->license_id );
            fs_require_once_template( 'js/open-license-activation.php', $vars );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         *
         * @param bool                   $background
         * @param FS_Plugin_License|null $premium_license
         */
        protected function _activate_license( $background = false, $premium_license = null ) {
            $this->_logger->entrance();

            if ( is_null( $premium_license ) ) {
                $license_id = fs_request_get( 'license_id' );

                if ( is_object( $this->_site ) &&
                    FS_Plugin_License::is_valid_id( $license_id ) &&
                    $license_id == $this->_site->license_id
                ) {
                    // License is already activated.
                    return;
                }

                $premium_license = FS_Plugin_License::is_valid_id( $license_id ) ?
                    $this->_get_license_by_id( $license_id ) :
                    $this->_get_available_premium_license();
            }

            if ( ! is_object( $premium_license ) ) {
                return;
            }

            if ( ! is_object( $this->_site ) ) {
                // Not yet opted-in.
                $user = $this->get_current_or_network_user();
                if ( ! is_object( $user ) ) {
                    $user = self::_get_user_by_id( $premium_license->user_id );
                }

                if ( is_object( $user ) ) {
                    $this->install_with_user( $user, $premium_license->secret_key, false, false, false );
                } else {
                    $this->opt_in(
                        false,
                        false,
                        false,
                        $premium_license->secret_key
                    );

                    return;
                }
            }


            /**
             * If the premium license is already associated with the install, just
             * update the license reference (activation is not required).
             *
             * @since 1.1.9
             */
            if ( $premium_license->id == $this->_site->license_id ) {
                // License is already activated.
                $this->_update_site_license( $premium_license );
                $this->_store_account();

                return;
            }

            if ( $this->_site->user_id != $premium_license->user_id ) {
                $api_request_params = array( 'license_key' => $premium_license->secret_key );
            } else {
                $api_request_params = array();
            }

            $api     = $this->get_api_site_scope();
            $license = $api->call( "/licenses/{$premium_license->id}.json?is_enriched=true", 'put', $api_request_params );

            if ( ! $this->is_api_result_entity( $license ) ) {
                if ( ! $background ) {
                    $this->_admin_notices->add( sprintf(
                        '%s %s',
                        $this->get_text_inline( 'It looks like the license could not be activated.', 'license-activation-failed-message' ),
                        ( is_object( $license ) && isset( $license->error ) ?
                            $license->error->message :
                            sprintf( '%s<br><code>%s</code>',
                                $this->get_text_inline( 'Error received from the server:', 'server-error-message' ),
                                var_export( $license, true )
                            )
                        )
                    ),
                        $this->get_text_x_inline( 'Hmm', 'something somebody says when they are thinking about what you have just said.', 'hmm' ) . '...',
                        'error'
                    );
                }

                return;
            }

            $premium_license = new FS_Plugin_License( $license );

            // Updated site plan.
            $site = $this->get_api_site_scope()->get( '/', true );
            if ( $this->is_api_result_entity( $site ) ) {
                $this->_site = new FS_Site( $site );
            }
            $this->_update_site_license( $premium_license );

            $this->_store_account();

            if ( $this->is_addon() || $this->has_addons() ) {
                /**
                 * Purge the valid user licenses cache so that when the "Account" or the "Add-Ons" page is loaded,
                 * an updated valid user licenses collection will be fetched from the server which is used to also
                 * update the account add-ons (add-ons the user has licenses for).
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.2.4
                 */
                $this->purge_valid_user_licenses_cache();
            }

            if ( ! $background ) {
                $this->add_complete_upgrade_instructions_notice(
                    $this->get_text_inline( 'Your license was successfully activated.', 'license-activated-message' ),
                    'license_activated'
                );
            }

            $this->_admin_notices->remove_sticky( array(
                'trial_promotion',
                'license_expired',
            ) );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         *
         * @param bool $show_notice
         */
        protected function _deactivate_license( $show_notice = true ) {
            $this->_logger->entrance();

            $hmm_text = $this->get_text_x_inline( 'Hmm', 'something somebody says when they are thinking about what you have just said.', 'hmm' ) . '...';

            if ( ! FS_Plugin_License::is_valid_id( $this->_site->license_id ) ) {
                $this->_admin_notices->add(
                    sprintf( $this->get_text_inline( 'It looks like your site currently doesn\'t have an active license.', 'no-active-license-message' ), $this->get_plan_title() ),
                    $hmm_text
                );

                return;
            }

            $api     = $this->get_api_site_scope();
            $license = $api->call( "/licenses/{$this->_site->license_id}.json", 'delete' );

            $this->handle_license_deactivation_result( $license, $hmm_text, $show_notice );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.2.1
         *
         * @param FS_Plugin_License $license
         * @param bool|string       $hmm_text
         * @param bool              $show_notice
         */
        private function handle_license_deactivation_result( $license, $hmm_text = false, $show_notice = true ) {
            if ( isset( $license->error ) ) {
                $this->_admin_notices->add(
                    $this->get_text_inline( 'It looks like the license deactivation failed.', 'license-deactivation-failed-message' ) . '<br> ' .
                    $this->get_text_inline( 'Error received from the server:', 'server-error-message' ) . ' ' . var_export( $license->error, true ),
                    $hmm_text,
                    'error'
                );

                return;
            }

            // Update license cache.
            if ( is_array( $this->_licenses ) ) {
                for ( $i = 0, $len = count( $this->_licenses ); $i < $len; $i ++ ) {
                    if ( $license->id == $this->_licenses[ $i ]->id ) {
                        $this->_licenses[ $i ] = new FS_Plugin_License( $license );
                    }
                }
            }

            // Update site plan to default.
            $this->_sync_plans();
            $this->_site->plan_id = $this->_plans[0]->id;
            // Unlink license from site.
            $this->_update_site_license( null );

            $this->_store_account();

            if ( $show_notice ) {
                $this->_admin_notices->add(
                    sprintf( $this->is_only_premium() ?
                        $this->get_text_inline( 'Your %s license was successfully deactivated.', 'license-deactivation-message_premium-only' ) :
                        $this->get_text_inline( 'Your license was successfully deactivated, you are back to the %s plan.', 'license-deactivation-message' ),
                        $this->get_plan_title()
                    ),
                    $this->get_text_inline( 'O.K', 'ok' )
                );
            }

            $this->_admin_notices->remove_sticky( array(
                'plan_upgraded',
                'license_activated',
            ) );
        }

        /**
         * Site plan downgrade.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @return object
         *
         * @uses   FS_Api
         */
        private function _downgrade_site() {
            $this->_logger->entrance();

            $deactivate_license = fs_request_get_bool( 'deactivate_license' );

            $api  = $this->get_api_site_scope();
            $site = $api->call( 'downgrade.json', 'put', array( 'deactivate_license' => $deactivate_license ) );

            $plan_downgraded = false;
            $plan            = false;
            if ( $this->is_api_result_entity( $site ) ) {
                $prev_plan_id = $this->_site->plan_id;

                // Update new site plan id.
                $this->_site->plan_id = $site->plan_id;

                $plan         = $this->get_plan();
                $subscription = $this->_sync_site_subscription( $this->_license );

                // Plan downgraded if plan was changed or subscription was cancelled.
                $plan_downgraded = ( $plan instanceof FS_Plugin_Plan && $prev_plan_id != $plan->id ) ||
                                   ( is_object( $subscription ) && ! isset( $subscription->error ) && ! $subscription->is_active() );
            } else {
                // handle different error cases.
                $this->handle_license_deactivation_result(
                    $site,
                    $this->get_text_x_inline( 'Hmm', 'something somebody says when they are thinking about what you have just said.', 'hmm' ) . '...'
                );
            }

            if ( ! $plan_downgraded ) {
                return (object) array(
                    'error' => (object) array(
                        'message' => $this->get_text_inline( 'Seems like we are having some temporary issue with your subscription cancellation. Please try again in few minutes.', 'subscription-cancellation-failure-message' )
                    )
                );
            }

            // Remove previous sticky message about upgrade (if exist).
            $this->_admin_notices->remove_sticky( 'plan_upgraded' );

            $this->_admin_notices->add(
                sprintf( $this->get_text_inline( 'Your subscription was successfully cancelled. Your %s plan license will expire in %s.', 'plan-x-downgraded-message' ),
                    $plan->title,
                    human_time_diff( time(), strtotime( $this->_license->expiration ) )
                )
            );

            // Store site updates.
            $this->_store_site();

            if ( $deactivate_license &&
                 ! FS_Plugin_License::is_valid_id( $site->license_id )
            ) {
                if ( $this->_site->is_localhost() ) {
                    $this->_license->activated_local = max( 0, $this->_license->activated_local - 1 );
                } else {
                    $this->_license->activated = max( 0, $this->_license->activated - 1 );
                }

                // Handle successful license deactivation result.
                $this->handle_license_deactivation_result( $this->_license );
            }

            return $site;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.8.1
         *
         * @param bool|string $plan_name
         * @param bool        $add_sticky_notice
         *
         * @return bool If trial was successfully started.
         */
        function start_trial( $plan_name = false, $add_sticky_notice = false ) {
            $this->_logger->entrance();

            // Alias.
            $oops_text = $this->get_text_x_inline( 'Oops', 'exclamation', 'oops' ) . '...';

            if ( $this->is_trial() ) {
                // Already in trial mode.
                $this->_admin_notices->add(
                    sprintf( $this->get_text_inline( 'You are already running the %s in a trial mode.', 'in-trial-mode' ), $this->_module_type ),
                    $oops_text,
                    'error',
                    $add_sticky_notice
                );

                return false;
            }

            if ( $this->_site->is_trial_utilized() && ! $this->is_payments_sandbox() ) {
                // Trial was already utilized.
                $this->_admin_notices->add(
                    $this->get_text_inline( 'You already utilized a trial before.', 'trial-utilized' ),
                    $oops_text,
                    'error',
                    $add_sticky_notice
                );

                return false;
            }

            if ( false !== $plan_name ) {
                $plan = $this->get_plan_by_name( $plan_name );

                if ( false === $plan ) {
                    // Plan doesn't exist.
                    $this->_admin_notices->add(
                        sprintf( $this->get_text_inline( 'Plan %s do not exist, therefore, can\'t start a trial.', 'trial-plan-x-not-exist' ), $plan_name ),
                        $oops_text,
                        'error',
                        $add_sticky_notice
                    );

                    return false;
                }

                if ( ! $plan->has_trial() ) {
                    // Plan doesn't exist.
                    $this->_admin_notices->add(
                        sprintf( $this->get_text_inline( 'Plan %s does not support a trial period.', 'plan-x-no-trial' ), $plan_name ),
                        $oops_text,
                        'error',
                        $add_sticky_notice
                    );

                    return false;
                }
            } else {
                if ( ! $this->has_trial_plan() ) {
                    // None of the plans have a trial.
                    $this->_admin_notices->add(
                        sprintf( $this->get_text_inline( 'None of the %s\'s plans supports a trial period.', 'no-trials' ), $this->_module_type ),
                        $oops_text,
                        'error',
                        $add_sticky_notice
                    );

                    return false;
                }

                $plans_with_trial = FS_Plan_Manager::instance()->get_trial_plans( $this->_plans );

                $plan = $plans_with_trial[0];
            }

            $trial_params = array();

            if ( $this->is_payments_sandbox() ) {
                $trial_params['trial_timestamp'] = time();
                $trial_params['trial_token']     = FS_Security::instance()->get_trial_token(
                    $this->get_plugin(),
                    $plan,
                    $trial_params['trial_timestamp']
                );
            }

            $api   = $this->get_api_site_scope();
            $trial = $api->call( "plans/{$plan->id}/trials.json", 'post', $trial_params );

            if ( ! $this->is_api_result_entity( $trial ) ) {
                // Some API error while trying to start the trial.
                $this->_admin_notices->add(
                    $this->get_api_error_message( $trial ),
                    $oops_text,
                    'error',
                    $add_sticky_notice
                );

                return false;
            }

            // Sync license.
            $this->_sync_license();

            return $this->is_trial();
        }

        /**
         * Cancel site trial.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @return object
         *
         * @uses   FS_Api
         */
        private function _cancel_trial() {
            $this->_logger->entrance();

            if ( ! $this->is_trial() ) {
                return (object) array(
                    'error' => (object) array(
                        'message' => $this->get_text_inline( 'It looks like you are not in trial mode anymore so there\'s nothing to cancel :)', 'trial-cancel-no-trial-message' )
                    )
                );
            }

            $trial_plan = $this->get_trial_plan();

            $api  = $this->get_api_site_scope();
            $site = $api->call( 'trials.json', 'delete' );

            $trial_cancelled = false;

            if ( $this->is_api_result_entity( $site ) ) {
                $prev_trial_ends = $this->_site->trial_ends;

                if ( $this->is_paid_trial() ) {
                    $this->_license->expiration   = $site->trial_ends;
                    $this->_license->is_cancelled = true;
                    $this->_update_site_license( $this->_license );
                    $this->_store_licenses();

                    // Clear subscription reference.
                    $this->_sync_site_subscription( null );
                }

                // Update site info.
                $this->_site = new FS_Site( $site );

                $trial_cancelled = ( $prev_trial_ends != $site->trial_ends );
            } else {
                // @todo handle different error cases.
            }

            if ( ! $trial_cancelled ) {
                return (object) array(
                    'error' => (object) array(
                        'message' => $this->get_text_inline( 'Seems like we are having some temporary issue with your trial cancellation. Please try again in few minutes.', 'trial-cancel-failure-message' )
                    )
                );
            }

            // Remove previous sticky messages about upgrade or trial (if exist).
            $this->_admin_notices->remove_sticky( array(
                'trial_started',
                'trial_promotion',
                'plan_upgraded',
            ) );

            // Store site updates.
            $this->_store_site();

            if ( ! $this->is_addon() ||
                 ! $this->deactivate_premium_only_addon_without_license( true )
            ) {
                $this->_admin_notices->add(
                    sprintf( $this->get_text_inline( 'Your %s free trial was successfully cancelled.', 'trial-cancel-message' ), $trial_plan->title )
                );
            }

            return $site;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param bool|number $plugin_id
         *
         * @return bool
         */
        private function _is_addon_id( $plugin_id ) {
            return is_numeric( $plugin_id ) && ( $this->get_id() != $plugin_id );
        }

        /**
         * Check if user eligible to download premium version updates.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @return bool
         */
        private function _can_download_premium() {
            return $this->has_any_active_valid_license() ||
                   ( $this->is_trial() && ! $this->get_trial_plan()->is_free() );
        }

        /**
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param bool|number $addon_id
         * @param string      $type "json" or "zip"
         *
         * @return string
         */
        private function _get_latest_version_endpoint( $addon_id = false, $type = 'json' ) {

            $is_addon = $this->_is_addon_id( $addon_id );

            $is_premium = null;
            if ( ! $is_addon ) {
                $is_premium = ( $this->is_premium() || $this->_can_download_premium() );
            } else if ( $this->is_addon_activated( $addon_id ) ) {
                $fs_addon   = self::get_instance_by_id( $addon_id );
                $is_premium = ( $fs_addon->is_premium() || $fs_addon->_can_download_premium() );
            }

            // If add-on, then append add-on ID.
            $endpoint = ( $is_addon ? "/addons/$addon_id" : '' ) .
                        '/updates/latest.' . $type;

            // If add-on and not yet activated, try to fetch based on server licensing.
            if ( is_bool( $is_premium ) ) {
                $endpoint = add_query_arg( 'is_premium', json_encode( $is_premium ), $endpoint );
            }

            if ( $this->has_secret_key() ) {
                $endpoint = add_query_arg( 'type', 'all', $endpoint );
            } else if ( is_object( $this->_site ) && $this->_site->is_beta() ) {
                $endpoint = add_query_arg( 'type', 'beta', $endpoint );
            }

            return $endpoint;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @param bool|number $addon_id
         * @param bool        $flush        Since 1.1.7.3
         * @param int         $expiration   Since 1.2.2.7
         * @param bool|string $newer_than   Since 2.2.1
         * @param bool|string $fetch_readme Since 2.2.1
         *
         * @return object|false Plugin latest tag info.
         */
        function _fetch_latest_version(
            $addon_id = false,
            $flush = true,
            $expiration = WP_FS__TIME_24_HOURS_IN_SEC,
            $newer_than = false,
            $fetch_readme = true
        ) {
            $this->_logger->entrance();

            if ( $this->is_unresolved_clone( true ) ) {
                return false;
            }

            $switch_to_blog_id = null;

            /**
             * @since 1.1.7.3 Check for plugin updates from Freemius only if opted-in.
             * @since 1.1.7.4 Also check updates for add-ons.
             */
            if (
                 ( ! $this->is_registered() || ! FS_Permission_Manager::instance( $this )->is_essentials_tracking_allowed() ) &&
                 ! $this->_is_addon_id( $addon_id )
            ) {
                if ( ! is_multisite() ) {
                    return false;
                }

                $installs_map = $this->get_blog_install_map();

                foreach ( $installs_map as $blog_id => $install ) {
                    if ( ! FS_Permission_Manager::instance( $this )->is_essentials_tracking_allowed( $blog_id ) ) {
                        continue;
                    }

                    /**
                     * @var FS_Site $install
                     */
                    if ( $install->is_trial() ) {
                        $switch_to_blog_id = $blog_id;
                        break;
                    }

                    if ( FS_Plugin_License::is_valid_id( $install->license_id ) ) {
                        $license = $this->get_license_by_id( $install->license_id );

                        if ( is_object( $license ) && $license->is_features_enabled() ) {
                            $switch_to_blog_id = $blog_id;
                            break;
                        }
                    }
                }

                if ( is_null( $switch_to_blog_id ) ) {
                    return false;
                }
            }

            $current_blog_id = is_numeric( $switch_to_blog_id ) ?
                get_current_blog_id() :
                0;

            if ( is_numeric( $switch_to_blog_id ) ) {
                $this->switch_to_blog( $switch_to_blog_id );
            }

            $latest_version_endpoint = $this->_get_latest_version_endpoint( $addon_id, 'json' );

            if ( ! empty( $newer_than ) ) {
                $latest_version_endpoint = add_query_arg( 'newer_than', $newer_than, $latest_version_endpoint );
            }

            if ( true === $fetch_readme ) {
                $latest_version_endpoint = add_query_arg( 'readme', 'true', $latest_version_endpoint );

                // Don't cache the API response when fetching readme information.
                $expiration = null;
            }

            $tag = $this->get_api_site_or_plugin_scope()->get(
                $latest_version_endpoint,
                $flush,
                $expiration
            );

            if ( is_numeric( $switch_to_blog_id ) ) {
                $this->switch_to_blog( $current_blog_id );
            }

            $latest_version = ( is_object( $tag ) && isset( $tag->version ) ) ? $tag->version : 'couldn\'t get';

            $this->_logger->departure( 'Latest version ' . $latest_version );

            return ( is_object( $tag ) && isset( $tag->version ) ) ? $tag : false;
        }

        #----------------------------------------------------------------------------------
        #region Download Plugin
        #----------------------------------------------------------------------------------

        /**
         * Download latest plugin version, based on plan.
         *
         * Not like _download_latest(), this will redirect the page
         * to secure download url to prevent dual download (from FS to WP server,
         * and then from WP server to the client / browser).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @param bool|number $plugin_id
         *
         * @uses   FS_Api
         * @uses   wp_redirect()
         */
        private function download_latest_directly( $plugin_id = false ) {
            $this->_logger->entrance();

            wp_redirect( $this->get_latest_download_api_url( $plugin_id ) );
        }

        /**
         * Get latest plugin FS API download URL.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @param bool|number $plugin_id
         *
         * @return string
         */
        private function get_latest_download_api_url( $plugin_id = false ) {
            $this->_logger->entrance();

            $download_api_url = $this->get_api_site_scope()->get_signed_url(
                $this->_get_latest_version_endpoint( $plugin_id, 'zip' )
            );

            return str_replace( 'http:', 'https:', $download_api_url );
        }

        /**
         * Get payment invoice URL.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.0
         *
         * @param bool|number $payment_id
         *
         * @return string
         */
        function _get_invoice_api_url( $payment_id = false ) {
            $this->_logger->entrance();

            $url = $this->get_api_user_scope()->get_signed_url(
                "/payments/{$payment_id}/invoice.pdf"
            );

            if ( ! fs_starts_with( $url, 'https://' ) ) {
                // Always use HTTPS for invoices.
                $url = 'https' . substr( $url, 4 );
            }

            return $url;
        }

        /**
         * Get latest plugin download link.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @param string      $label
         * @param bool|number $plugin_id
         *
         * @return string
         */
        private function get_latest_download_link( $label, $plugin_id = false ) {
            return sprintf(
                '<a target="_blank" rel="noopener" href="%s">%s</a>',
                $this->_get_latest_download_local_url( $plugin_id ),
                $label
            );
        }

        /**
         * Get latest plugin download local URL.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @param bool|number $plugin_id
         *
         * @return string
         */
        function _get_latest_download_local_url( $plugin_id = false ) {
            // Add timestamp to protect from caching.
            $params = array( 'ts' => WP_FS__SCRIPT_START_TIME );

            if ( ! empty( $plugin_id ) ) {
                $params['plugin_id'] = $plugin_id;
            } else if ( $this->is_addon() ) {
                $params['plugin_id'] = $this->get_id();
            }

            $fs = $this->is_addon() ?
                $this->get_parent_instance() :
                $this;

            return $this->apply_filters( 'download_latest_url', $fs->get_account_url( 'download_latest', $params ) );
        }

        #endregion Download Plugin ------------------------------------------------------------------

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @uses   FS_Api
         *
         * @param bool        $background Hints the method if it's a background updates check. If false, it means that
         *                                was initiated by the admin.
         * @param bool|number $plugin_id
         * @param bool        $flush      Since 1.1.7.3
         * @param int         $expiration Since 1.2.2.7
         * @param bool|string $newer_than Since 2.2.1
         */
        private function check_updates(
            $background = false,
            $plugin_id = false,
            $flush = true,
            $expiration = FS_Plugin_Updater::UPDATES_CHECK_CACHE_EXPIRATION,
            $newer_than = false
        ) {
            $this->_logger->entrance();

            // Check if there's a newer version for download.
            $new_version = $this->_fetch_newer_version( $plugin_id, $flush, $expiration, $newer_than );

            $update = null;
            if ( is_object( $new_version ) ) {
                $update = new FS_Plugin_Tag( $new_version );

                if ( ! $background ) {
                    $this->_admin_notices->add(
                        sprintf(
                        /* translators: %s: Numeric version number (e.g. '2.1.9' */
                            $this->get_text_inline( 'Version %s was released.', 'version-x-released' ) . ' ' . $this->get_text_inline( 'Please download %s.', 'please-download-x' ),
                            $update->version,
                            sprintf(
                                '<a href="%s" target="_blank" rel="noopener">%s</a>',
                                $this->get_account_url( 'download_latest' ),
                                sprintf(
                                /* translators: %s: plan name (e.g. latest "Professional" version) */
                                    $this->get_text_inline( 'the latest %s version here', 'latest-x-version' ),
                                    $this->get_plan_title()
                                )
                            )
                        ),
                        $this->get_text_inline( 'New', 'new' ) . '!'
                    );
                }
            } else if ( false === $new_version && ! $background ) {
                $this->_admin_notices->add(
                    $this->get_text_inline( 'Seems like you got the latest release.', 'you-have-latest' ),
                    $this->get_text_inline( 'You are all good!', 'you-are-good' )
                );
            }

            $this->_store_update( $update, true, $plugin_id );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @param bool $flush Since 1.1.7.3 add 24 hour cache by default.
         *
         * @return FS_Plugin[]
         *
         * @uses   FS_Api
         */
        private function sync_addons( $flush = false ) {
            $this->_logger->entrance();

            $api = $this->get_api_site_or_plugin_scope();

            $path = $this->add_show_pending( '/addons.json?enriched=true&count=50' );

            /**
             * @since 1.2.1
             *
             * If there's a cached version of the add-ons and not asking
             * for a flush, just use the currently stored add-ons.
             */
            if ( ! $flush && $api->is_cached( $path ) ) {
                $addons = self::get_all_addons();

                return isset( $addons[ $this->_plugin->id ] ) ?
                    $addons[ $this->_plugin->id ] :
                    array();
            }

            $result = $api->get( $path, $flush );

            $addons = array();
            if ( $this->is_api_result_object( $result, 'plugins' ) &&
                 is_array( $result->plugins )
            ) {
                for ( $i = 0, $len = count( $result->plugins ); $i < $len; $i ++ ) {
                    $addons[ $i ] = new FS_Plugin( $result->plugins[ $i ] );
                }

                $this->_store_addons( $addons, true );
            }

            return $addons;
        }

        /**
         * Handle user email update.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         * @uses   FS_Api
         *
         * @param string $new_email
         *
         * @return object
         */
        private function update_email( $new_email ) {
            $this->_logger->entrance();

            $api  = $this->get_api_user_scope();
            $user = $api->call( "?plugin_id={$this->_plugin->id}&fields=id,email,is_verified", 'put', array(
                'email'                   => $new_email,
                'after_email_confirm_url' => $this->_get_admin_page_url(
                    'account',
                    array( 'fs_action' => 'sync_user' )
                ),
            ) );

            if ( ! isset( $user->error ) ) {
                $this->_user->email       = $user->email;
                $this->_user->is_verified = $user->is_verified;
                $this->_store_user();
            } else {
                // handle different error cases.
            }

            return $user;
        }

        #----------------------------------------------------------------------------------
        #region API Error Handling
        #----------------------------------------------------------------------------------

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.1
         *
         * @param mixed $result
         *
         * @return bool Is API result contains an error.
         */
        private function is_api_error( $result ) {
            return FS_Api::is_api_error( $result );
        }

        /**
         * Checks if given API result is a non-empty and not an error object.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.5
         *
         * @param mixed       $result
         * @param string|null $required_property Optional property we want to verify that is set.
         *
         * @return bool
         */
        function is_api_result_object( $result, $required_property = null ) {
            return FS_Api::is_api_result_object( $result, $required_property );
        }

        /**
         * Checks if given API result is a non-empty entity object with non-empty ID.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.5
         *
         * @param mixed $result
         *
         * @return bool
         */
        private function is_api_result_entity( $result ) {
            return FS_Api::is_api_result_entity( $result );
        }

        #endregion

        /**
         * Make sure a given argument is an array of a specific type.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.5
         *
         * @param mixed  $array
         * @param string $class
         *
         * @return bool
         */
        private function is_array_instanceof( $array, $class ) {
            return ( is_array( $array ) && ( empty( $array ) || $array[0] instanceof $class ) );
        }

        /**
         * Start install ownership change.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.1
         * @uses   FS_Api
         *
         * @param string $candidate_email
         * @param string $transfer_type
         *
         * @return bool Is ownership change successfully initiated.
         */
        private function init_change_owner( $candidate_email, $transfer_type ) {
            $this->_logger->entrance();

            $installs_info_by_slug_map = $this->get_parent_and_addons_installs_info();
            $install_ids               = array();

            foreach ( $installs_info_by_slug_map as $slug => $install_info ) {
                $install = $install_info['install'];

                if ( $this->_user->id != $install->user_id ) {
                    // Skip add-on installs that are not owned by the parent product's install's owner.
                    continue;
                }

                $install_ids[ $slug ] = $install->id;
            }

            $api    = $this->get_api_site_scope();
            $result = $api->call( "/users/{$this->_user->id}.json", 'put', array(
                'email'             => $candidate_email,
                'transfer_type'     => $transfer_type,
                'install_ids'       => implode( ',', array_values( $install_ids ) ),
                'after_confirm_url' => $this->_get_admin_page_url(
                    'account',
                    array( 'fs_action' => 'change_owner' )
                ),
            ) );

            return ! $this->is_api_error( $result );
        }

        /**
         * Handle install ownership change.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.1
         * @uses   FS_Api
         *
         * @return bool Was ownership change successfully complete.
         */
        private function complete_change_owner() {
            $this->_logger->entrance();

            $install_ids = fs_request_get( 'install_ids' );

            if ( ! empty( $install_ids ) ) {
                $install_ids = explode( ',', $install_ids );

                foreach ( $install_ids as $key => $install_id ) {
                    if ( ! FS_Site::is_valid_id( $install_id ) ) {
                        unset( $install_ids[ $key ] );
                    }
                }
            }

            if ( ! is_array( $install_ids ) ) {
                $install_ids = array();
            }

            $user             = new FS_User();
            $user->id         = fs_request_get( 'user_id' );
            $user->public_key = fs_request_get_raw( 'user_public_key' );
            $user->secret_key = fs_request_get_raw( 'user_secret_key' );

            $prev_user   = $this->_user;
            $this->_user = $user;

            $result = $this->get_api_user_scope( true )->get(
                "/installs.json?install_ids=" . implode( ',', $install_ids )
            );

            $current_blog_sites = self::get_all_sites( $this->get_module_type() );

            if ( $this->is_api_result_object( $result, 'installs' ) ) {
                $site_id_slug_map = array();

                foreach ( $current_blog_sites as $slug => $site ) {
                    $site_id_slug_map[ $site->id ] = $slug;
                }

                foreach ( $result->installs as $install ) {
                    $site = new FS_Site( $install );

                    if ( ! isset( $site_id_slug_map[ $install->id ] ) ) {
                        continue;
                    }

                    $current_blog_sites[ $site_id_slug_map[ $install->id ] ] = clone $site;

                    if ( $this->_site->id == $site->id ) {
                        $this->_site = $site;
                    }
                }
            }

            // Validate install's user and given user.
            if ( $user->id != $this->_site->user_id ) {
                $this->_user = $prev_user;

                return false;
            }

            $this->set_account_option( 'sites', $current_blog_sites, true );

            // Fetch new user information.
            $user_result = $this->get_api_user_scope( true )->get();
            $user        = new FS_User( $user_result );
            $this->_user = $user;

            $this->_set_account( $user, $this->_site );

            $remove_user       = true;
            $all_modules_sites = FS_DebugManager::get_all_modules_sites();

            foreach ( $all_modules_sites as $sites_by_module_type ) {
                foreach ( $sites_by_module_type as $sites_by_slug ) {
                    foreach ( $sites_by_slug as $site ) {
                        if ( $prev_user->id == $site->user_id ) {
                            $remove_user = false;
                            break;
                        }
                    }

                    if ( ! $remove_user ) {
                        break;
                    }
                }

                if ( ! $remove_user ) {
                    break;
                }
            }

            if ( $remove_user ) {
                $users = self::get_all_users();

                if ( isset( $users[ $prev_user->id ] ) ) {
                    unset( $users[ $prev_user->id ] );
                } else {
                    // If the prev user wasn't found by the key, iterate over the users collection.
                    foreach ( $users as $key => $user ) {
                        if ( $user->id == $prev_user->id ) {
                            unset( $users[ $key ] );
                            break;
                        }
                    }
                }

                $this->set_account_option( 'users', $users, true );
            }

            return true;
        }

        /**
         * Completes ownership change by license.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.3.2
         *
         * @param number              $user_id
         * @param array[string]number $install_ids_by_slug_map
         *
         */
        private function complete_ownership_change_by_license( $user_id, $install_ids_by_slug_map ) {
            $this->_logger->entrance();

            $this->sync_user_by_current_install( $user_id );

            $result = $this->get_api_user_scope( true )->get(
                "/installs.json?install_ids=" . implode( ',', $install_ids_by_slug_map )
            );

            if ( $this->is_api_result_object( $result, 'installs' ) ) {
                $sites                   = self::get_all_sites( $this->get_module_type() );
                $install_ids_by_slug_map = array_flip( $install_ids_by_slug_map );

                foreach ( $result->installs as $install ) {
                    $site = new FS_Site( $install );

                    $sites[ $install_ids_by_slug_map[ $site->id ] ] = clone $site;
                }

                $this->set_account_option( 'sites', $sites, true );
            }
        }

        /**
         * Handle user name update.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         * @uses   FS_Api
         *
         * @return object
         */
        private function update_user_name() {
            $this->_logger->entrance();
            $name = fs_request_get( 'fs_user_name_' . $this->get_unique_affix(), '' );

            $api  = $this->get_api_user_scope();
            $user = $api->call( "?plugin_id={$this->_plugin->id}&fields=id,first,last", 'put', array(
                'name' => $name,
            ) );

            if ( ! isset( $user->error ) ) {
                $this->_user->first = $user->first;
                $this->_user->last  = $user->last;
                $this->_store_user();
            } else {
                // handle different error cases.

            }

            return $user;
        }

        /**
         * Verify user email.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         * @uses   FS_Api
         */
        private function verify_email() {
            $this->_handle_account_user_sync();

            if ( $this->_user->is_verified() ) {
                return;
            }

            $api    = $this->get_api_site_scope();
            $result = $api->call( "/users/{$this->_user->id}/verify.json", 'put', array(
                'after_email_confirm_url' => $this->_get_admin_page_url(
                    'account',
                    array( 'fs_action' => 'sync_user' )
                )
            ) );

            if ( ! isset( $result->error ) ) {
                $this->_admin_notices->add( sprintf(
                    $this->get_text_inline( 'Verification mail was just sent to %s. If you can\'t find it after 5 min, please check your spam box.', 'verification-email-sent-message' ),
                    sprintf( '<a href="mailto:%1$s">%2$s</a>', esc_url( $this->_user->email ), $this->_user->email )
                ) );
            } else {
                // handle different error cases.

            }
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.2
         *
         * @param array     $params
         * @param bool|null $network
         *
         * @return string
         */
        function get_activation_url( $params = array(), $network = null ) {
            if ( $this->is_addon() && $this->has_free_plan() ) {
                /**
                 * @author Vova Feldman (@svovaf)
                 * @since  1.2.1.7 Add-on's activation is the parent's module activation.
                 */
                return $this->get_parent_instance()->get_activation_url( $params );
            }

            return $this->apply_filters( 'connect_url', $this->_get_admin_page_url( '', $params, $network ) );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.5
         *
         * @param array $params
         *
         * @return string
         */
        function get_reconnect_url( $params = array() ) {
            $params['fs_action']       = 'reset_anonymous_mode';
            $params['fs_unique_affix'] = $this->get_unique_affix();

            return $this->get_activation_url( $params );
        }

        /**
         * Get the URL of the page that should be loaded after the user connect
         * or skip in the opt-in screen.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.3
         *
         * @param string    $filter Filter name.
         * @param array     $params Since 1.2.2.7
         * @param bool|null $network
         *
         * @return string
         */
        function get_after_activation_url( $filter, $params = array(), $network = null ) {
            if ( $this->show_opt_in_on_themes_page() &&
                 ( fs_request_has( 'pending_activation' ) ||
                   // For cases when the first time path is set, even though it's a WP.org theme.
                   fs_request_get_bool( $this->get_unique_affix() . '_show_optin' ) )
            ) {
                $first_time_path = '';
            } else {
                $first_time_path = $this->_menu->get_first_time_path(
                    fs_is_network_admin() && $this->_is_network_active
                );
            }

            if ( $this->_is_network_active &&
                 fs_is_network_admin() &&
                 ! $this->_menu->has_network_menu() &&
                 $this->is_network_registered()
            ) {
                $target_url = $this->get_account_url();
            } else {
                // Default plugin's page.
                $target_url = $this->_get_admin_page_url( '', array(), $network );
            }

            return add_query_arg( $params, $this->apply_filters(
                $filter,
                empty( $first_time_path ) ?
                    $target_url :
                    $first_time_path
            ) );
        }

        /**
         * Handle account page updates / edits / actions.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.2
         *
         */
        private function _handle_account_edits() {
            if ( ! $this->is_user_admin() ) {
                return;
            }

            $action = fs_get_action();

            if ( empty( $action ) ) {
                return;
            }

            $plugin_id  = fs_request_get( 'plugin_id', $this->get_id() );
            $install_id = fs_request_get( 'install_id', '' );

            // Alias.
            $oops_text = $this->get_text_x_inline( 'Oops', 'exclamation', 'oops' ) . '...';

            $is_network_action       = $this->is_network_level_action();
            $blog_id                 = $this->is_network_level_site_specific_action();
            $is_parent_plugin_action = ( $plugin_id == $this->get_id() );

            if ( is_numeric( $blog_id ) ) {
                $this->switch_to_blog( $blog_id );
            } else {
                $blog_id = '';
            }

            switch ( $action ) {
                case 'opt_in':
                    check_admin_referer( trim( "{$action}:{$blog_id}:{$install_id}", ':' ) );

                    if ( $is_parent_plugin_action ) {
                        if ( $is_network_action && ! empty( $blog_id ) ) {
                            if ( ! $this->is_registered() ) {
                                $this->install_with_user(
                                    $this->get_network_user(),
                                    false,
                                    false,
                                    false,
                                    false
                                );

                                $this->_admin_notices->add(
                                    $this->get_text_inline( 'Site successfully opted in.', 'successful-opt-in' ),
                                    $this->get_text_inline( 'Awesome', 'awesome' )
                                );
                            }
                        }
                    }
                    break;

                case 'toggle_tracking':
                    check_admin_referer( trim( "{$action}:{$blog_id}:{$install_id}", ':' ) );

                    if ( $is_parent_plugin_action ) {
                        if ( $is_network_action && ! empty( $blog_id ) ) {
                            if ( $this->is_registered( true ) ) {
                                if ( $this->is_tracking_prohibited( $blog_id ) ) {
                                    if ( $this->toggle_site_tracking( true, $blog_id ) ) {
                                        $this->_admin_notices->add(
                                            sprintf( $this->get_text_inline( 'Sharing diagnostic data with %s helps to provide functionality that\'s more relevant to your website, avoid WordPress or PHP version incompatibilities that can break your website, and recognize which languages & regions the plugin should be translated and tailored to.', 'opt-out-message-appreciation' ), "<b>{$this->get_plugin_title()}</b>" ),
                                            $this->get_text_inline( 'Thank you!', 'thank-you' )
                                        );
                                    }
                                } else {
                                    if ( $this->toggle_site_tracking( false, $blog_id ) ) {
                                        $install = $this->get_install_by_blog_id( $blog_id );

                                        $this->_admin_notices->add(
                                            sprintf(
                                                $this->get_text_inline( 'Diagnostic data will no longer be sent from %s to %s.', 'opted-out-successfully' ),
                                                self::get_unfiltered_site_url( $blog_id, true ),
                                                "<b>{$this->get_plugin_title()}</b>"
                                            )
                                        );
                                    }
                                }
                            }
                        }
                    }

                    break;

                case 'delete_account':
                    check_admin_referer( trim( "{$action}:{$blog_id}:{$install_id}", ':' ) );

                    $is_network_deletion = $is_network_action && empty( $blog_id );

                    if ( $is_parent_plugin_action ) {
                        // Delete add-on installs if have any.
                        $installed_addons = $this->get_installed_addons();
                        foreach ( $installed_addons as $fs_addon ) {
                            if ( $is_network_deletion ) {
                                $fs_addon->delete_network_account_event();
                            } else {
                                $fs_addon->delete_account_event();
                            }
                        }

                        if ( $is_network_deletion ) {
                            $this->delete_network_account_event();
                        } else {
                            $this->delete_account_event();
                        }

                        // Clear user and site.
                        $this->_site = null;
                        $this->_user = null;

                        $this->maybe_set_slug_and_network_menu_exists_flag();

                        fs_redirect( $this->get_activation_url() );
                    } else {
                        if ( $this->is_addon_activated( $plugin_id ) ) {
                            $fs_addon = self::get_instance_by_id( $plugin_id );

                            if ( $is_network_deletion ) {
                                $fs_addon->delete_network_account_event();
                            } else {
                                $fs_addon->delete_account_event();
                            }

                            fs_redirect( $this->_get_admin_page_url( 'account' ) );
                        }
                    }

                    return;

                case 'downgrade_account':
                    if ( is_numeric( $blog_id ) ) {
                        check_admin_referer( trim( "{$action}:{$blog_id}:{$install_id}", ':' ) );
                    } else {
                        check_admin_referer( $action );
                    }

                    $switch_to_network_install_blog_after_cancellation = (
                        is_numeric( $blog_id ) &&
                        $plugin_id == $this->get_id() &&
                        ! $this->is_trial()
                    );

                    $result = $this->cancel_subscription_or_trial( $plugin_id );
                    if ( $this->is_api_error( $result ) ) {
                        $this->_admin_notices->add(
                            $result->error->message,
                            $this->get_text_x_inline( 'Oops', 'exclamation', 'oops' ) . '...',
                            'error'
                        );
                    }

                    if ( $switch_to_network_install_blog_after_cancellation ) {
                        $this->switch_to_blog( $this->_storage->network_install_blog_id );
                    }

                    return;

                case 'activate_license':
                    check_admin_referer( trim( "{$action}:{$blog_id}:{$install_id}", ':' ) );

                    $fs = $this;
                    if ( $plugin_id != $this->get_id() ) {
                        $fs = $this->is_addon_activated( $plugin_id ) ?
                            self::get_instance_by_id( $plugin_id ) :
                            null;
                    }

                    if ( is_object( $fs ) ) {
                        $fs->_activate_license();

                        /**
                         * Remove the product ID from `$_REQUEST` so that the syncing of the license for the other products will work properly.
                         *
                         * @author Leo Fajardo (@leorw)
                         * @since 2.4.0
                         */
                        unset( $_REQUEST['plugin_id'] );

                        if ( $this->is_bundle_license_auto_activation_enabled() ) {
                            $fs->maybe_activate_bundle_license( null, array(), is_numeric( $blog_id ) ? $blog_id : 0 );
                        }
                    }

                    return;

                case 'deactivate_license':
                    check_admin_referer( trim( "{$action}:{$blog_id}:{$install_id}", ':' ) );

                    if ( $plugin_id == $this->get_id() ) {
                        $this->_deactivate_license();

                        if ( $this->is_only_premium() ) {
                            // Clear user and site.
                            $this->_site = null;
                            $this->_user = null;

                            if ( ! $is_network_action ) {
                                fs_redirect( $this->get_activation_url() );
                            } else if ( is_numeric( $blog_id ) ) {
                                $this->switch_to_blog( $this->_storage->network_install_blog_id );
                            }
                        }
                    } else {
                        if ( $this->is_addon_activated( $plugin_id ) ) {
                            $fs_addon = self::get_instance_by_id( $plugin_id );
                            $fs_addon->_deactivate_license();
                        }
                    }

                    return;

                case 'check_updates':
                    check_admin_referer( $action );
                    $this->check_updates();

                    return;

                case 'change_owner':
                    $state = fs_request_get( 'state', 'init' );
                    switch ( $state ) {
                        case 'init':
                            // The nonce is injected by the error handler in `_email_address_update_ajax_handler` function.
                            check_admin_referer( 'change_owner' );

                            $candidate_email = fs_request_get( 'candidate_email' );
                            $transfer_type   = fs_request_get( 'transfer_type' );

                            if ( $this->init_change_owner( $candidate_email, $transfer_type ) ) {
                                if ( 'transfer' === $transfer_type ) {
                                    $this->_admin_notices->add( sprintf( $this->get_text_inline( 'A confirmation email was just sent to %s. The email owner must confirm the update within the next 4 hours.', 'change-owner-request-sent-x-transfer' ), '<b>' . $this->_user->email . '</b>' ) );
                                } else {
                                    $this->_admin_notices->add( sprintf( $this->get_text_inline( 'A confirmation email was just sent to %s. You must confirm the update within the next 4 hours. If you cannot find the email, please check your spam folder.', 'change-owner-request-sent-x' ), '<b>' . $this->_user->email . '</b>' ) );
                                }
                            }
                            break;
                        case 'owner_confirmed':
                            // We cannot (or need not to) check the nonce and referer here, because the link comes from the email sent by our API.
                            $candidate_email = fs_request_get( 'candidate_email', '' );

                            if ( ! is_email($candidate_email ) ) {
                                return;
                            }

                            $this->_admin_notices->add( sprintf( $this->get_text_inline( 'Thanks for confirming the ownership change. An email was just sent to %s for final approval.', 'change-owner-request_owner-confirmed' ), '<b>' . $candidate_email . '</b>' ) );
                            break;
                        case 'candidate_confirmed':
                            // We do not need to validate the authenticity of this request here, because the `complete_change_owner` does that for us through API calls.
                            if ( $this->complete_change_owner() ) {
                                $this->_admin_notices->add_sticky(
                                    sprintf( $this->get_text_inline( '%s is the new owner of the account.', 'change-owner-request_candidate-confirmed' ), '<b>' . $this->_user->email . '</b>' ),
                                    'ownership_changed',
                                    $this->get_text_x_inline( 'Congrats', 'as congratulations', 'congrats' ) . '!'
                                );
                            } else {
                                // @todo Handle failed ownership change message.
                            }
                            break;
                    }

                    return;

                case 'update_user_name':
                    check_admin_referer( 'update_user_name' );

                    $result = $this->update_user_name();

                    if ( isset( $result->error ) ) {
                        $this->_admin_notices->add(
                            $this->get_text_inline( 'Please provide your full name.', 'name-update-failed-message' ),
                            $oops_text,
                            'error'
                        );
                    } else {
                        $this->_admin_notices->add( $this->get_text_inline( 'Your name was successfully updated.', 'name-updated-message' ) );
                    }

                    return;

                #region Actions that might be called from external links (e.g. email)

                /**
                 * !!IMPORTANT!!: We cannot check for a valid nonce in this region, because the links could be coming from emails.
                 */

                case 'cancel_trial':
                    $result = $this->cancel_subscription_or_trial( $plugin_id );
                    if ( $this->is_api_error( $result ) ) {
                        $this->_admin_notices->add(
                            $result->error->message,
                            $this->get_text_x_inline( 'Oops', 'exclamation', 'oops' ) . '...',
                            'error'
                        );
                    }

                    return;

                case 'verify_email':
                    $this->verify_email();

                    return;

                case 'sync_user':
                    $this->_handle_account_user_sync();

                    return;

                case $this->get_unique_affix() . '_sync_license':
                    $this->_sync_license();

                    return;

                case 'download_latest':
                    $this->download_latest_directly( $plugin_id );

                    return;

                #endregion
            }

            if ( WP_FS__IS_POST_REQUEST ) {
                $properties = array( 'site_secret_key', 'site_id', 'site_public_key' );
                foreach ( $properties as $p ) {
                    if ( 'update_' . $p === $action ) {
                        check_admin_referer( $action );

                        $this->_logger->log( $action );

                        $site_property                      = substr( $p, strlen( 'site_' ) );
                        $site_property_value                = fs_request_get( 'fs_' . $p . '_' . $this->get_unique_affix(), '' );
                        $this->get_site()->{$site_property} = $site_property_value;

                        // Store account after modification.
                        $this->_store_site();

                        $this->do_action( 'account_property_edit', 'site', $site_property, $site_property_value );

                        $this->_admin_notices->add( sprintf(
                        /* translators: %s: User's account property (e.g. email address, name) */
                            $this->get_text_inline( 'You have successfully updated your %s.', 'x-updated' ),
                            '<b>' . str_replace( '_', ' ', $p ) . '</b>'
                        ) );

                        return;
                    }
                }
            }
        }

        /**
         * Adds CSS classes for the body tag in the admin.
         *
         * @param string $classes Space-separated string of class names.
         * 
         * @return string $classes FS Admin body tag class names.
         */
        public function fs_addons_body_class( $classes ) {
            $classes .= ' plugins-php';
            return $classes;
        }

        /**
         * Account page resources load.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         */
        function _account_page_load() {
            $this->_logger->entrance();

            $this->_logger->info( var_export( $_REQUEST, true ) );

            fs_enqueue_local_style( 'fs_account', '/admin/account.css' );

            if ( $this->has_addons() ) {
                wp_enqueue_script( 'plugin-install' );
                add_thickbox();
                add_filter( 'admin_body_class', array( $this, 'fs_addons_body_class' ) );
            }

            if ( $this->has_paid_plan() &&
                 ! $this->has_any_license() &&
                 ! $this->is_sync_executed() &&
                 $this->is_tracking_allowed()
            ) {
                /**
                 * If no licenses found and no sync job was executed during the last 24 hours,
                 * just execute the sync job right away (blocking execution).
                 *
                 * @since 1.1.7.3
                 */
                $this->run_manual_sync();
            }

            $this->_handle_account_edits();

            if (
                is_object( $this->_license ) &&
                $this->_license->user_id == $this->_user->id &&
                ! $this->is_whitelabeled( true )
            ) {
                $this->_admin_notices->add(
                    sprintf(
                        $this->get_text_inline( "Is this your client's site? %s if you wish to hide sensitive info like your email, license key, prices, billing address & invoices from the WP Admin.", 'license_not_whitelabeled' ),
                        sprintf(
                            '<a href="#" class="fs-toggle-whitelabel-mode">%s</a>',
                            $this->get_text_inline( 'Click here', 'click-here' )
                        )
                    ),
                    '',
                    'success',
                    false,
                    'license_not_whitelabeled'
                );
            }

            $this->do_action( 'account_page_load_before_departure' );
        }

        /**
         * Renders the "Affiliation" page.
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.2.3
         */
        function _affiliation_page_render() {
            $this->_logger->entrance();

            $this->fetch_affiliate_and_terms();

            fs_enqueue_local_style( 'fs_affiliation', '/admin/affiliation.css' );

            $is_bundle_context = $this->has_bundle_context();

            $plugin_title = $this->get_plugin_title();

            if ( $is_bundle_context ) {
                $plugin_title = $this->plugin_affiliate_terms->plugin_title;

                // Add the suffix "Bundle" only if the word is not present in the title itself.
                if ( false === mb_stripos( $plugin_title, fs_text_inline( 'Bundle', 'bundle' ) ) ) {
                    $plugin_title = $this->apply_filters(
                        'formatted_bundle_title',
                        $plugin_title . ' ' . fs_text_inline( 'Bundle', 'bundle' )
                    );
                }
            }

            $vars = array(
                'id'           => $this->_module_id,
                'plugin_title' => $plugin_title,
            );
            echo $this->apply_filters( "/forms/affiliation.php", fs_get_template( '/forms/affiliation.php', $vars ) );
        }


        /**
         * Render account page.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.0
         */
        function _account_page_render() {
            $this->_logger->entrance();

            $template = 'account.php';
            $vars     = array( 'id' => $this->_module_id );

            /**
             * Added filter to the template to allow developers wrapping the template
             * in custom HTML (e.g. within a wizard/tabs).
             *
             * @author Vova Feldman (@svovaf)
             * @since  1.2.1.6
             */
            echo $this->apply_filters( "templates/{$template}", fs_get_template( $template, $vars ) );
        }

        /**
         * Render account connect page.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.7
         */
        function _connect_page_render() {
            $this->_logger->entrance();

            $vars = array( 'id' => $this->_module_id );

            /**
             * Added filter to the template to allow developers wrapping the template
             * in custom HTML (e.g. within a wizard/tabs).
             *
             * @author Vova Feldman (@svovaf)
             * @since  1.2.1.6
             */
            echo $this->apply_filters( 'templates/connect.php', fs_get_template( 'connect.php', $vars ) );
        }

        /**
         * Load required resources before add-ons page render.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         */
        function _addons_page_load() {
            $this->_logger->entrance();

            fs_enqueue_local_style( 'fs_addons', '/admin/add-ons.css' );

            wp_enqueue_script( 'plugin-install' );
            add_thickbox();
            add_filter( 'admin_body_class', array( $this, 'fs_addons_body_class' ) );

            if ( ! $this->is_registered() && $this->is_org_repo_compliant() ) {
                $this->_admin_notices->add(
                    sprintf( $this->get_text_inline( 'Just letting you know that the add-ons information of %s is being pulled from an external server.', 'addons-info-external-message' ), '<b>' . $this->get_plugin_name() . '</b>' ),
                    $this->get_text_x_inline( 'Heads up', 'advance notice of something that will need attention.', 'heads-up' ),
                    'update-nag'
                );
            }
        }

        /**
         * Render add-ons page.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         */
        function _addons_page_render() {
            $this->_logger->entrance();

            $vars = array( 'id' => $this->_module_id );

            /**
             * Added filter to the template to allow developers wrapping the template
             * in custom HTML (e.g. within a wizard/tabs).
             *
             * @author Vova Feldman (@svovaf)
             * @since  1.2.1.6
             */
            echo $this->apply_filters( 'templates/add-ons.php', fs_get_template( 'add-ons.php', $vars ) );
        }

        /* Pricing & Upgrade
        ------------------------------------------------------------------------------------------------------------------*/
        /**
         * Render pricing page.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.0
         */
        function _pricing_page_render() {
            $this->_logger->entrance();

            fs_enqueue_local_style( 'fs_common', '/admin/common.css' );
            fs_enqueue_local_style( 'fs_checkout', '/admin/checkout.css' );

            $vars = array( 'id' => $this->_module_id );

            if ( 'true' === fs_request_get( 'checkout', false ) ) {
                echo $this->apply_filters( 'templates/checkout.php', fs_get_template( 'checkout.php', $vars ) );
            } else {
                echo $this->apply_filters( 'templates/pricing.php', fs_get_template( 'pricing.php', $vars ) );
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.3.1
         */
        function _add_pricing_ajax_handler() {
            $this->add_ajax_action( 'pricing_ajax_action', array( &$this, '_fs_pricing_ajax_action_handler' ) );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.1
         */
        function _fs_pricing_ajax_action_handler() {
            $this->check_ajax_referer( 'pricing_ajax_action' );

            $result         = null;
            $pricing_action = fs_request_get( 'pricing_action' );

            switch ( $pricing_action ) {
                case 'fetch_pricing_data':
                    $params = array(
                        'is_enriched'  => true,
                        'trial'        => fs_request_get_bool( 'trial' ),
                        'sandbox'      => fs_request_get_raw( 'sandbox' ),
                        's_ctx_type'   => fs_request_get_raw( 's_ctx_type' ),
                        's_ctx_id'     => fs_request_get_raw( 's_ctx_id' ),
                        's_ctx_ts'     => fs_request_get_raw( 's_ctx_ts' ),
                        's_ctx_secure' => fs_request_get_raw( 's_ctx_secure' ),
                    );

                    $bundle_id         = $this->get_bundle_id();
                    $bundle_public_key = $this->get_bundle_public_key();

                    $has_bundle_context = ( FS_Plugin::is_valid_id( $bundle_id ) && ! empty( $bundle_public_key ) );

                    if ( ! $has_bundle_context ) {
                        $api = $this->get_api_plugin_scope();
                    } else {
                        $api = FS_Api::instance(
                            $bundle_id,
                            'plugin',
                            $bundle_id,
                            $bundle_public_key,
                            ! $this->is_live(),
                            false,
                            $this->get_sdk_version()
                        );

                        $params['plugin_id']         = $this->get_id();
                        $params['plugin_public_key'] = $this->get_public_key();
                    }

                    $result = $api->get( 'pricing.json?' . http_build_query( $params ) );
                    break;
                case 'start_trial':
                    $trial_plan_id = fs_request_get( 'plan_id' );

                    if ( $this->is_registered() && $this->is_tracking_allowed() ) {
                        $plan = $this->_get_plan_by_id( $trial_plan_id );

                        if ( ! $plan ) {
                            $this->shoot_ajax_failure( 'Invalid plan ID.' );
                            return;
                        }

                        $result = $this->start_trial( $plan->name, true );
                    } else {
                        // @todo - This fails for sandbox trial at the moment if the trial was already utilized.
                        $result = $this->opt_in(
                            false,
                            false,
                            false,
                            false,
                            false,
	                        $trial_plan_id
                        );
                    }
            }

            if ( is_object( $result ) && $this->is_api_error( $result ) ) {
                $this->_logger->api_error( $result );

                self::shoot_ajax_failure(
                    isset( $result->error ) ?
                        ( is_string( $result->error ) ? $result->error : $result->error->message ) :
                        var_export( $result, true )
                );
            }

            $this->shoot_ajax_success( $result );
        }

        #----------------------------------------------------------------------------------
        #region Contact Us
        #----------------------------------------------------------------------------------

        /**
         * Render contact-us page.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         */
        function _contact_page_render() {
            $this->_logger->entrance();

            $vars = array( 'id' => $this->_module_id );

            /**
             * Added filter to the template to allow developers wrapping the template
             * in custom HTML (e.g. within a wizard/tabs).
             *
             * @author Vova Feldman (@svovaf)
             * @since  2.1.3
             */
            echo $this->apply_filters( 'templates/contact.php', fs_get_template( 'contact.php', $vars ) );
        }

        #endregion ------------------------------------------------------------------------

        /**
         * Hide all admin notices to prevent distractions.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         *
         * @uses   remove_all_actions()
         */
        private static function _hide_admin_notices() {
            remove_all_actions( 'admin_notices' );
            remove_all_actions( 'network_admin_notices' );
            remove_all_actions( 'all_admin_notices' );
            remove_all_actions( 'user_admin_notices' );
        }

        static function _clean_admin_content_section_hook() {
            $hide_admin_notices = true;

            if ( fs_request_is_action( 'allow_clone_resolution_notice' ) ) {
                check_admin_referer( 'fs_allow_clone_resolution_notice' );

                $hide_admin_notices = false;
            }

            if ( $hide_admin_notices ) {
                self::_hide_admin_notices();
            }

            // Hide footer.
            echo '<style>#wpfooter { display: none !important; }</style>';
        }

        /**
         * Attach to admin_head hook to hide all admin notices.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         */
        static function _clean_admin_content_section() {
            add_action( 'admin_head', 'Freemius::_clean_admin_content_section_hook' );
        }

        /* CSS & JavaScript
        ------------------------------------------------------------------------------------------------------------------*/
        /*		function _enqueue_script($handle, $src) {
                    $url = plugins_url( substr( WP_FS__DIR_JS, strlen( $this->_plugin_dir_path ) ) . '/assets/js/' . $src );

                    $this->_logger->entrance( 'script = ' . $url );

                    wp_enqueue_script( $handle, $url );
                }*/

        /* SDK
        ------------------------------------------------------------------------------------------------------------------*/
        private $_user_api;

        /**
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.2
         *
         * @param bool $flush
         *
         * @return FS_Api
         */
        function get_api_user_scope( $flush = false ) {
            if ( ! isset( $this->_user_api ) || $flush ) {
                $this->_user_api = $this->get_api_user_scope_by_user( $this->_user );
            }

            return $this->_user_api;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param \FS_User $user
         *
         * @return \FS_Api
         */
        private function get_api_user_scope_by_user( FS_User $user ) {
            return FS_Api::instance(
                $this->_module_id,
                'user',
                $user->id,
                $user->public_key,
                ! $this->is_live(),
                $user->secret_key,
                $this->get_sdk_version()
            );
        }

        /**
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.0.0
         *
         * @param bool $flush
         *
         * @return FS_Api
         */
        private function get_current_or_network_user_api_scope( $flush = false ) {
            if ( ! $this->_is_network_active ||
                 ( isset( $this->_user ) && $this->_user instanceof FS_User )
            ) {
                return $this->get_api_user_scope( $flush );
            }

            $user = $this->get_current_or_network_user();

            $this->_user_api = FS_Api::instance(
                $this->_module_id,
                'user',
                $user->id,
                $user->public_key,
                ! $this->is_live(),
                $user->secret_key,
                $this->get_sdk_version()
            );

            return $this->_user_api;
        }

        private $_site_api;

        /**
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.2
         *
         * @param bool $flush
         *
         * @return FS_Api
         */
        private function get_api_site_scope( $flush = false ) {
            if ( ! isset( $this->_site_api ) || $flush ) {
                $this->_site_api = FS_Api::instance(
                    $this->_module_id,
                    'install',
                    $this->_site->id,
                    $this->_site->public_key,
                    ! $this->is_live(),
                    $this->_site->secret_key,
                    $this->get_sdk_version(),
                    self::get_unfiltered_site_url()
                );
            }

            return $this->_site_api;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.0
         *
         * @param string $path
         * @param string $method
         * @param array  $params
         * @param bool   $flush_instance
         *
         * @return array|mixed|string|void
         * @throws Freemius_Exception
         */
        private function api_site_call( $path, $method = 'GET', $params = array(), $flush_instance = false ) {
            $result = $this->get_api_site_scope( $flush_instance )->call( $path, $method, $params );

            /**
             * Checks if the local install's URL is different from the remote install's URL, update the local install if necessary, and then run the clone handler if the install's URL is different from the URL of the site.
             *
             * @author Leo Fajardo (@leorw)
             * @since  2.5.0
             */
            if (
                $this->is_registered() &&
                FS_Api::is_api_result_entity( $result ) &&
                isset( $result->url )
            ) {
                $stored_local_url  = trailingslashit( $this->_site->url );
                $stored_remote_url = trailingslashit( $result->url );

                if ( $stored_local_url !== $stored_remote_url ) {
                    $this->_site->url = $result->url;
                    $this->_store_site();
                }

                if ( fs_strip_url_protocol( $stored_remote_url ) !== self::get_unfiltered_site_url( null, true, true ) ) {
                    FS_Clone_Manager::instance()->maybe_run_clone_resolution();
                }
            }

            return $result;
        }

        private $_plugin_api;

        /**
         * Get plugin public API scope.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.7
         *
         * @return FS_Api
         */
        function get_api_plugin_scope() {
            if ( ! isset( $this->_plugin_api ) ) {
                $this->_plugin_api = FS_Api::instance(
                    $this->_module_id,
                    'plugin',
                    $this->_plugin->id,
                    $this->_plugin->public_key,
                    ! $this->is_live(),
                    false,
                    $this->get_sdk_version()
                );
            }

            return $this->_plugin_api;
        }

        /**
         * Get bundle public API scope.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.3.1
         *
         * @return FS_Api
         */
        function get_api_bundle_scope() {
            return FS_Api::instance(
                $this->get_bundle_id(),
                'plugin',
                $this->get_bundle_id(),
                $this->get_bundle_public_key(),
                ! $this->is_live(),
                false,
                $this->get_sdk_version()
            );
        }

        /**
         * Get site API scope object (fallback to public plugin scope when not registered).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.7
         *
         * @return FS_Api
         */
        function get_api_site_or_plugin_scope() {
            return $this->is_registered() ?
                $this->get_api_site_scope() :
                $this->get_api_plugin_scope();
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.2.3.1
         *
         * @param object $result
         */
        private function maybe_modify_api_curl_error_message( $result ) {
            if (
                'cUrlMissing' !== $result->error->type &&
                ( 'CurlException' !== $result->error->type || CURLE_COULDNT_CONNECT != $result->error->code )  &&
                ( 'HttpRequestFailed' !== $result->error->type || false === strpos( $result->error->message, 'cURL error ' . CURLE_COULDNT_CONNECT ) )
            ) {
                return;
            }

            $result->error->message = $this->esc_html_inline( 'We use PHP cURL library for the API calls, which is a very common library and usually installed and activated out of the box. Unfortunately, cURL is not activated (or disabled) on your server.', 'curl-missing-message' ) .
                ' ' .
                $this->esc_html_inline(
                    sprintf(
                        'Please contact your hosting provider and ask them to whitelist %s for external connection.',
                        implode(
                            ', ',
                            $this->apply_filters( 'api_domains', array(
                                'api.freemius.com',
                                'wp.freemius.com'
                            ) )
                        )
                    ),
                    'connectivity-whitelist'
                ) .
                ' ' .
                sprintf(
                    $this->esc_html_inline( 'Once you are done, deactivate the %s and activate it again.', 'connectivity-reactivate-module' ),
                    $this->get_module_type()
                );
        }

        /**
         * Show trial promotional notice (if any trial exist).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @param FS_Plugin_Plan[] $plans
         */
        function _check_for_trial_plans( $plans ) {
            /**
             * For some reason core's do_action() flattens arrays when it has a single object item. Therefore, we need to restructure the array as expected.
             *
             * @author Vova Feldman (@svovaf)
             * @since  2.1.2
             */
            if ( ! is_array( $plans ) && is_object( $plans ) ) {
                $plans = array( $plans );
            }

            if ( ! $this->is_array_instanceof( $plans, 'FS_Plugin_Plan' ) ) {
                $plans = array();
            }

            $this->_storage->has_trial_plan = FS_Plan_Manager::instance()->has_trial_plan( $plans );
        }

        /**
         * During trial promotion the "upgrade" submenu item turns to
         * "start trial" to encourage the trial. Since we want to keep
         * the same menu item handler and there's no robust way to
         * add new arguments to the menu item link's querystring,
         * use JavaScript to find the menu item and update the href of
         * the link.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.5
         */
        function _fix_start_trial_menu_item_url() {
            $template_args = array( 'id' => $this->_module_id );
            fs_require_template( 'add-trial-to-pricing.php', $template_args );
        }

        /**
         * Check if module is currently in a trial promotion mode.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @return bool
         */
        function is_in_trial_promotion() {
            return $this->_admin_notices->has_sticky( 'trial_promotion' );
        }

        /**
         * Show trial promotional notice (if any trial exist).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @return bool If trial notice added.
         */
        function _add_trial_notice() {
            if ( ! $this->is_user_admin() ) {
                return false;
            }

            if ( ! $this->is_user_in_admin() ) {
                return false;
            }

            if ( $this->_is_network_active ) {
                if ( fs_is_network_admin() ) {
                    // Network level trial is disabled at the moment.
                    return false;
                }

                if ( ! $this->is_delegated_connection() ) {
                    // Only delegated sites should support trials.
                    return false;
                }
            }

            // Check if trial message is already shown.
            if ( $this->is_in_trial_promotion() ) {
                add_action( 'admin_footer', array( &$this, '_fix_start_trial_menu_item_url' ) );

                $this->_menu->add_counter_to_menu_item( 1, 'fs-trial' );

                return false;
            }

            if ( $this->is_premium() && ! WP_FS__DEV_MODE ) {
                // Don't show trial if running the premium code, unless running in DEV mode.
                return false;
            }

            if ( ! $this->has_trial_plan() ) {
                // No plans with trial.
                return false;
            }

            if ( ! $this->apply_filters( 'show_trial', true ) ) {
                // Developer explicitly asked not to show the trial promo.
                return false;
            }

            if ( $this->is_registered() ) {
                // Check if trial already utilized.
                if ( $this->_site->is_trial_utilized() ) {
                    return false;
                }

                if ( $this->is_paying_or_trial() ) {
                    // Don't show trial if paying or already in trial.
                    return false;
                }
            }

            if ( $this->is_activation_mode() || $this->is_pending_activation() ) {
                // If not yet opted-in/skipped, or pending activation, don't show trial.
                return false;
            }

            $last_time_trial_promotion_shown = $this->_storage->get( 'trial_promotion_shown', false );
            $was_promotion_shown_before      = ( false !== $last_time_trial_promotion_shown );

            // Show promotion if never shown before and 24 hours after initial activation with FS.
            if ( ! $was_promotion_shown_before &&
                 $this->_storage->install_timestamp > ( time() - $this->apply_filters( 'show_first_trial_after_n_sec', WP_FS__TIME_24_HOURS_IN_SEC ) )
            ) {
                return false;
            }

            // OR if promotion was shown before, try showing it every 30 days.
            if ( $was_promotion_shown_before &&
                 $this->apply_filters( 'reshow_trial_after_every_n_sec', 30 * WP_FS__TIME_24_HOURS_IN_SEC ) > time() - $last_time_trial_promotion_shown
            ) {
                return false;
            }

            $trial_period    = $this->_trial_days;
            $require_payment = $this->_is_trial_require_payment;
            $trial_url       = $this->get_trial_url();
            $plans_string    = strtolower( $this->get_text_inline( 'Awesome', 'awesome' ) );

            if ( $this->is_registered() ) {
                // If opted-in, override trial with up to date data from API.
                $trial_plans       = FS_Plan_Manager::instance()->get_visible_trial_plans( $this->_plans );
                $trial_plans_count = count( $trial_plans );

                if ( 0 === $trial_plans_count ) {
                    // If there's no plans with a trial just exit.
                    return false;
                }

                /**
                 * @var FS_Plugin_Plan $paid_plan
                 */
                $paid_plan       = $trial_plans[0];
                $require_payment = $paid_plan->is_require_subscription;
                $trial_period    = $paid_plan->trial_period;

                $total_paid_plans = count( $this->_plans ) - ( FS_Plan_Manager::instance()->has_free_plan( $this->_plans ) ? 1 : 0 );

                if ( $total_paid_plans !== $trial_plans_count ) {
                    // Not all paid plans have a trial - generate a string of those that have it.
                    for ( $i = 0; $i < $trial_plans_count; $i ++ ) {
                        $plans_string .= sprintf(
                            ' <a href="%s">%s</a>',
                            $trial_url,
                            $trial_plans[ $i ]->title
                        );

                        if ( $i < $trial_plans_count - 2 ) {
                            $plans_string .= ', ';
                        } else if ( $i == $trial_plans_count - 2 ) {
                            $plans_string .= ' and ';
                        }
                    }
                }
            }

            $message = sprintf(
                $this->get_text_x_inline( 'Hey', 'exclamation', 'hey' ) . '! ' . $this->get_text_inline( 'How do you like %s so far? Test all our %s premium features with a %d-day free trial.', 'trial-x-promotion-message' ),
                sprintf( '<b>%s</b>', $this->get_plugin_name() ),
                $plans_string,
                $trial_period
            );

            // "No Credit-Card Required" or "No Commitment for N Days".
            $cc_string = $require_payment ?
                sprintf( $this->get_text_inline( 'No commitment for %s days - cancel anytime!', 'no-commitment-for-x-days' ), $trial_period ) :
                $this->get_text_inline( 'No credit card required', 'no-cc-required' ) . '!';


            // Start trial button.
            $button = ' ' . sprintf(
                    '<a style="margin-left: 10px; vertical-align: super;" href="%s"><button class="button button-primary">%s &nbsp;&#10140;</button></a>',
                    $trial_url,
                    $this->get_text_x_inline( 'Start free trial', 'call to action', 'start-free-trial' )
                );

            $this->_admin_notices->add_sticky(
                $this->apply_filters( 'trial_promotion_message', "{$message} {$cc_string} {$button}" ),
                'trial_promotion',
                '',
                'promotion'
            );

            $this->_storage->trial_promotion_shown = WP_FS__SCRIPT_START_TIME;

            return true;
        }

        /**
         * Lets users/customers know that the product has an affiliate program.
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.2.2.11
         *
         * @return bool Returns true if the notice has been added.
         */
        function _add_affiliate_program_notice() {
            if ( ! $this->is_user_admin() ) {
                return false;
            }

            if ( ! $this->is_user_in_admin() ) {
                return false;
            }

            // Check if the notice is already shown.
            if ( $this->_admin_notices->has_sticky( 'affiliate_program' ) ) {
                return false;
            }

            if (
                // Product has no affiliate program.
                ! $this->has_affiliate_program() ||
                // User has applied for an affiliate account.
                ! empty( $this->_storage->affiliate_application_data )
            ) {
                return false;
            }

            if ( ! $this->apply_filters( 'show_affiliate_program_notice', true ) ) {
                // Developer explicitly asked not to show the notice about the affiliate program.
                return false;
            }

            if ( $this->is_activation_mode() || $this->is_pending_activation() ) {
                // If not yet opted in/skipped, or pending activation, don't show the notice.
                return false;
            }

            $last_time_notice_was_shown = $this->_storage->get( 'affiliate_program_notice_shown', false );
            $was_notice_shown_before    = ( false !== $last_time_notice_was_shown );

            /**
             * Do not show the notice if it was already shown before or less than 30 days have passed since the initial
             * activation with FS.
             */
            if ( $was_notice_shown_before ||
                 $this->_storage->install_timestamp > ( time() - ( WP_FS__TIME_24_HOURS_IN_SEC * 30 ) )
            ) {
                return false;
            }

            if ( ! $this->is_paying() &&
                 FS_Plugin::AFFILIATE_MODERATION_CUSTOMERS == $this->_plugin->affiliate_moderation
            ) {
                // If the user is not a customer and the affiliate program is only for customers, don't show the notice.
                return false;
            }

            $message = sprintf(
                $this->get_text_inline( 'Hey there, did you know that %s has an affiliate program? If you like the %s you can become our ambassador and earn some cash!', 'become-an-ambassador-admin-notice' ),
                sprintf( '<strong>%s</strong>', $this->get_plugin_name() ),
                $this->get_module_label( true )
            );

            // HTML code for the "Learn more..." button.
            $button = ' ' . sprintf(
                    '<a style="display: block; margin-top: 10px;" href="%s"><button class="button button-primary">%s &nbsp;&#10140;</button></a>',
                    $this->_get_admin_page_url( 'affiliation' ),
                    $this->get_text_inline( 'Learn more', 'learn-more' ) . '...'
                );

            $this->_admin_notices->add_sticky(
                $this->apply_filters( 'affiliate_program_notice', "{$message} {$button}" ),
                'affiliate_program',
                '',
                'promotion'
            );

            $this->_storage->affiliate_program_notice_shown = WP_FS__SCRIPT_START_TIME;

            return true;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.5
         */
        function _enqueue_common_css() {
            if ( $this->has_paid_plan() && ! $this->is_paying() ) {
                // Add basic CSS for admin-notices and menu-item colors.
                fs_enqueue_local_style( 'fs_common', '/admin/common.css' );
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.2
         */
        function _show_theme_activation_optin_dialog() {
            fs_enqueue_local_style( 'fs_connect', '/admin/connect.css' );

            add_action( 'admin_footer', array( &$this, '_add_fs_theme_activation_dialog' ) );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  1.2.2
         */
        function _add_fs_theme_activation_dialog() {
            global $pagenow;

            if ( 'themes.php' !== $pagenow ) {
                return;
            }

            $vars = array( 'id' => $this->_module_id );
            fs_require_once_template( 'connect.php', $vars );
        }

        /* Action Links
        ------------------------------------------------------------------------------------------------------------------*/
        private $_action_links_hooked = false;
        private $_action_links = array();

        /**
         * Hook to plugin action links filter.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.0
         */
        private function hook_plugin_action_links() {
            $this->_logger->entrance();

            $this->_action_links_hooked = true;

            $this->_logger->log( 'Adding action links hooks.' );

            // Add action link to settings page.
            add_filter( 'plugin_action_links_' . $this->_plugin_basename, array(
                &$this,
                '_modify_plugin_action_links_hook'
            ), WP_FS__DEFAULT_PRIORITY, 2 );
            add_filter( 'network_admin_plugin_action_links_' . $this->_plugin_basename, array(
                &$this,
                '_modify_plugin_action_links_hook'
            ), WP_FS__DEFAULT_PRIORITY, 2 );
        }

        /**
         * Add plugin action link.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.0
         *
         * @param      $label
         * @param      $url
         * @param bool $external
         * @param int  $priority
         * @param bool $key
         */
        function add_plugin_action_link( $label, $url, $external = false, $priority = WP_FS__DEFAULT_PRIORITY, $key = false ) {
            $this->_logger->entrance();

            if ( ! isset( $this->_action_links[ $priority ] ) ) {
                $this->_action_links[ $priority ] = array();
            }

            if ( false === $key ) {
                $key = preg_replace( "/[^A-Za-z0-9 ]/", '', strtolower( $label ) );
            }

            $this->_action_links[ $priority ][] = array(
                'label'    => $label,
                'href'     => $url,
                'key'      => $key,
                'external' => $external
            );
        }

        /**
         * Adds Upgrade and Add-Ons links to the main Plugins page link actions collection.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.0
         */
        function _add_upgrade_action_link() {
            $this->_logger->entrance();

            $is_activation_mode = $this->is_activation_mode();

            $add_action_links = $this->should_add_submenu_or_action_links( $is_activation_mode );

            /**
             * The following logic is based on the logic in `add_submenu_items()` method that decides when the "Upgrade"
             * and "Add-Ons" menus should be added.
             *
             * @author Leo Fajardo (@leorw)
             * @since 2.3.0
             */
            $add_upgrade_link = (
                $add_action_links ||
                ( $is_activation_mode && $this->is_only_premium() )
            ) && ! WP_FS__DEMO_MODE && ( ! $this->is_whitelabeled() );

            $add_addons_link = ( $add_action_links && $this->has_addons() );

            if ( ! $add_upgrade_link && ! $add_addons_link ) {
                return;
            }

            if (
                $add_upgrade_link &&
                $this->is_pricing_page_visible() &&
                $this->is_submenu_item_visible( 'pricing' )
            ) {
                $this->add_plugin_action_link(
                    $this->get_text_inline( 'Upgrade', 'upgrade' ),
                    $this->get_upgrade_url(),
                    false,
                    7,
                    'upgrade'
                );
            }

            if (
                $add_addons_link &&
                $this->has_addons() &&
                $this->is_submenu_item_visible( 'addons' )
            ) {
                $this->add_plugin_action_link(
                    $this->get_text_inline( 'Add-Ons', 'add-ons' ),
                    $this->_get_admin_page_url( 'addons' ),
                    false,
                    9,
                    'addons'
                );
            }
        }

        /**
         * Adds "Activate License" or "Change License" link to the main Plugins page link actions collection.
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.1.9
         */
        function _add_license_action_link() {
            $this->_logger->entrance();

            if ( ! self::is_ajax() ) {
                // Inject license activation dialog UI and client side code.
                add_action( 'admin_footer', array( &$this, '_add_license_activation_dialog_box' ) );
            }

            $link_text = $this->is_free_plan() ?
                $this->get_text_inline( 'Activate License', 'activate-license' ) :
                $this->get_text_inline( 'Change License', 'change-license' );

            $this->add_plugin_action_link(
                $link_text,
                '#',
                false,
                11,
                ( 'activate-license ' . $this->get_unique_affix() )
            );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.0.2
         */
        function _add_premium_version_upgrade_selection_action() {
            $this->_logger->entrance();

            if ( ! self::is_ajax() ) {
                add_action( 'admin_footer', array( &$this, '_add_premium_version_upgrade_selection_dialog_box' ) );
            }
        }

        /**
         * Adds "Opt In" or "Opt Out" link to the main "Plugins" page link actions collection.
         *
         * @author Leo Fajardo (@leorw)
         * @since  1.2.1.5
         */
        function _add_tracking_links() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $this->_logger->entrance();

            if ( $this->is_only_premium() && $this->is_free_plan() ) {
                // Don't add tracking links for premium-only products that were opted-in by relation (add-on or a parent product) before activating any license.
                return;
            }

            if (
                $this->is_addon() &&
                ! $this->is_only_premium()
            ) {
                $parent = $this->get_parent_instance();

                if ( is_object( $parent ) && $parent->is_anonymous() ) {
                    return;
                }
            }

            if ( fs_is_network_admin() ) {
                if ( ! $this->_is_network_active ) {
                    // Don't add tracking links when browsing the network WP Admin and the plugin is not network active.
                    return;
                } else if ( $this->is_network_delegated_connection() ) {
                    // Don't add tracking links when browsing the network WP Admin and the activation has been delegated to site admins.
                    return;
                }
            } else {
                if ( $this->_is_network_active && ! $this->is_delegated_connection() ) {
                    // Don't add tracking links when browsing the sub-site WP Admin, the plugin is network active, and the connection was not delegated.
                    return;
                }
            }

            if ( fs_request_is_action_secure( $this->get_unique_affix() . '_reconnect' ) ) {
                if ( ! $this->is_registered() && $this->is_anonymous() ) {
                    $this->connect_again();

                    return;
                }
            }

            if ( ( $this->is_plugin() && ! self::is_plugins_page() ) ||
                 ( $this->is_theme() && ! self::is_themes_page() )
            ) {
                // Only show tracking links on the plugins and themes pages.
                return;
            }

            if (
                $this->is_activation_mode() &&
                $this->is_premium() &&
                ! $this->is_registered()
            ) {
                // If not yet registered and running the premium code base, a license activation link will already be shown.
                return;
            }

            if ( $this->is_registered() && $this->is_tracking_allowed() ) {
                if ( ! $this->is_premium() && ! $this->is_enable_anonymous() ) {
                    // If opted in and tracking is allowed, don't allow to opt out if not premium and anonymous mode is disabled.
                    return;
                }
            }

            if ( $this->add_ajax_action( 'toggle_permission_tracking', array( &$this, '_toggle_permission_tracking_callback' ) ) ) {
                return;
            }

            $link_text_id = '';
            $url          = '#';

            if ( $this->is_registered( true ) ) {
                if ( $this->is_registered() && $this->is_tracking_allowed() ) {
                    $link_text_id = $this->get_text_inline( 'Opt Out', 'opt-out' );
                } else {
                    $link_text_id = $this->get_text_inline( 'Opt In', 'opt-in' );
                }
            } else if ( $this->is_anonymous() || $this->is_activation_mode() ) {
                /**
                 * Show opt-in link only if skipped or in activation mode.
                 */
                $link_text_id = $this->get_text_inline( 'Opt In', 'opt-in' );

                $params = ! $this->is_anonymous() ?
                    array() :
                    array(
                        'nonce'     => wp_create_nonce( $this->get_unique_affix() . '_reconnect' ),
                        'fs_action' => ( $this->get_unique_affix() . '_reconnect' ),
                    );

                $url = $this->get_activation_url( $params );
            }

            add_action( 'admin_footer', array( &$this, '_add_optout_dialog' ) );

            if ( ! empty( $link_text_id ) && $this->is_plugin() && self::is_plugins_page() ) {
                $this->add_plugin_action_link(
                    $link_text_id,
                    $url,
                    false,
                    13,
                    "opt-in-or-opt-out {$this->_slug}"
                );
            }
        }

        /**
         * Get the URL of the page that should be loaded right after the plugin activation.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7.4
         *
         * @return string
         */
        function get_after_plugin_activation_redirect_url() {
            $url = false;

            if ( ! $this->is_addon() || ! $this->has_free_plan() ) {
                $first_time_path = $this->_menu->get_first_time_path(
                    fs_is_network_admin() && $this->_is_network_active
                );

                if ( $this->is_activation_mode() ) {
                    $url = $this->get_activation_url();
                } else if ( ! empty( $first_time_path ) ) {
                    $url = $first_time_path;
                } else {
                    $page = '';
                    if ( ! empty( $this->_dynamically_added_top_level_page_hook_name ) ) {
                        if ( $this->is_network_registered() ) {
                            $page = 'account';
                        } else if ( $this->is_pending_activation() || $this->is_network_anonymous() ) {
                            $this->maybe_set_slug_and_network_menu_exists_flag();
                        }
                    }

                    $url = $this->_get_admin_page_url( $page );
                }
            } else {
                $plugin_fs = false;

                if ( $this->is_parent_plugin_installed() ) {
                    $plugin_fs = self::get_parent_instance();
                }

                if ( is_object( $plugin_fs ) ) {
                    if ( ! $plugin_fs->is_registered() ) {
                        // Forward to parent plugin connect when parent not registered.
                        $url = $plugin_fs->get_activation_url();
                    } else {
                        // Forward to account page.
                        $url = $plugin_fs->_get_admin_page_url( 'account' );
                    }
                }
            }

            return $url;
        }

        /**
         * Forward page to activation page.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.3
         */
        function _redirect_on_activation_hook() {
            if ( $this->apply_filters( 'redirect_on_activation', true ) ) {
                $url = $this->get_after_plugin_activation_redirect_url();

                if ( is_string( $url ) ) {
                    fs_redirect( $url );
                }
            }
        }

        /**
         * Modify plugin's page action links collection.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.0
         *
         * @param array $links
         * @param       $file
         *
         * @return array
         */
        function _modify_plugin_action_links_hook( $links, $file ) {
            $this->_logger->entrance();

            $passed_deactivate = false;
            $deactivate_link   = '';
            $before_deactivate = array();
            $after_deactivate  = array();
            foreach ( $links as $key => $link ) {
                if ( 'deactivate' === $key ) {
                    $deactivate_link   = $link;
                    $passed_deactivate = true;
                    continue;
                }

                if ( ! $passed_deactivate ) {
                    $before_deactivate[ $key ] = $link;
                } else {
                    $after_deactivate[ $key ] = $link;
                }
            }

            ksort( $this->_action_links );

            foreach ( $this->_action_links as $new_links ) {
                foreach ( $new_links as $link ) {
                    $before_deactivate[ $link['key'] ] = '<a href="' . $link['href'] . '"' . ( $link['external'] ? ' target="_blank" rel="noopener"' : '' ) . '>' . $link['label'] . '</a>';
                }
            }

            if ( ! empty( $deactivate_link ) ) {
                /**
                 * This HTML element is used to identify the correct plugin when attaching an event to its Deactivate link.
                 *
                 * @since 1.2.1.6 Always show the deactivation feedback form since we added automatic free version deactivation upon premium code activation.
                 */
                $deactivate_link .= '<i class="fs-module-id" data-module-id="' . $this->_module_id . '"></i>';

                // Append deactivation link.
                $before_deactivate['deactivate'] = $deactivate_link;
            }

            return array_merge( $before_deactivate, $after_deactivate );
        }

        /**
         * Adds admin message.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @param string $message
         * @param string $title
         * @param string $type
         */
        function add_admin_message( $message, $title = '', $type = 'success' ) {
            $this->_admin_notices->add( $message, $title, $type );
        }

        /**
         * Adds sticky admin message.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.0
         *
         * @param string $message
         * @param string $id
         * @param string $title
         * @param string $type
         */
        function add_sticky_admin_message( $message, $id, $title = '', $type = 'success' ) {
            $this->_admin_notices->add_sticky( $message, $id, $title, $type );
        }

        /**
         * Check if the paid version of the module is installed.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.2.0
         *
         * @return bool
         */
        private function is_premium_version_installed() {
            $premium_plugin_basename = $this->premium_plugin_basename();

            if ( $this->is_theme() ) {
                return $this->can_activate_theme( $this->get_premium_slug() );
            }

            return file_exists( fs_normalize_path( WP_PLUGIN_DIR . '/' . $premium_plugin_basename ) );
        }

        /**
         * Helper function that returns the final steps for the upgrade completion.
         *
         * If the module is already running the premium code, returns an empty string.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1
         *
         * @param string $plan_title
         *
         * @return string
         */
        private function get_complete_upgrade_instructions( $plan_title = '' ) {
            $this->_logger->entrance();

            $activate_license_string = $this->get_license_network_activation_notice();

            if ( ! $this->has_premium_version() || $this->is_premium() ) {
                return '' . $activate_license_string;
            }

            if ( empty( $plan_title ) ) {
                $plan_title = $this->get_plan_title();
            }

            if ( $this->is_premium_version_installed() ) {
                /**
                 * If the premium version is already installed, instead of showing the installation instructions,
                 * tell the current user to activate it.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.2.1
                 */
                $premium_theme_slug_or_plugin_basename = $this->is_theme() ?
                    $this->get_premium_slug() :
                    $this->premium_plugin_basename();

                return sprintf(
                /* translators: %1$s: Product title; %2$s: Plan title */
                    $this->get_text_inline( ' The paid version of %1$s is already installed. Please activate it to start benefiting the %2$s features. %3$s', 'activate-premium-version' ),
                    sprintf( '<em>%s</em>', esc_html( $this->get_plugin_title() ) ),
                    $plan_title,
                    sprintf(
                        '<a style="margin-left: 10px;" href="%s"><button class="button button-primary">%s</button></a>',
                        ( $this->is_theme() ?
                            wp_nonce_url( 'themes.php?action=activate&amp;stylesheet=' . $premium_theme_slug_or_plugin_basename, 'switch-theme_' . $premium_theme_slug_or_plugin_basename ) :
                            wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $premium_theme_slug_or_plugin_basename, 'activate-plugin_' . $premium_theme_slug_or_plugin_basename ) ),
                        esc_html( sprintf(
                        /* translators: %s: Plan title */
                            $this->get_text_inline( 'Activate %s features', 'activate-x-features' ),
                            $plan_title
                        ) )
                    )
                );
            } else {
                // @since 1.2.1.5 The free version is auto deactivated.
                $deactivation_step = version_compare( $this->version, '1.2.1.5', '<' ) ?
                    ( '<li>' . $this->esc_html_inline( 'Deactivate the free version', 'deactivate-free-version' ) . '.</li>' ) :
                    '';

                return sprintf(
                    ' %s: <ol><li>%s.</li>%s<li>%s (<a href="%s" target="_blank" rel="noopener">%s</a>).</li></ol>',
                    $this->get_text_inline( 'Please follow these steps to complete the upgrade', 'follow-steps-to-complete-upgrade' ),
                    ( empty( $activate_license_string ) ? '' : $activate_license_string . '</li><li>' ) .
                    $this->get_latest_download_link( sprintf(
                    /* translators: %s: Plan title */
                        $this->get_text_inline( 'Download the latest %s version', 'download-latest-x-version' ),
                        $plan_title
                    ) ),
                    $deactivation_step,
                    $this->get_text_inline( 'Upload and activate the downloaded version', 'upload-and-activate' ),
                    $this->apply_filters( 'upload_and_install_video_url', '//bit.ly/wp-' . $this->_module_type . '-upload' ),
                    $this->get_text_inline( 'How to upload and activate?', 'howto-upload-activate' )
                );
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.3
         *
         * @param string $message_before_the_instructions
         * @param string $message_id
         * @param string $plan_title
         */
        private function add_complete_upgrade_instructions_notice(
            $message_before_the_instructions,
            $message_id,
            $plan_title = ''
        ) {
            $this->_admin_notices->add_sticky(
                $message_before_the_instructions .
                $this->get_complete_upgrade_instructions( $plan_title ),
                $message_id,
                $this->get_text_x_inline( 'Yee-haw', 'interjection expressing joy or exuberance', 'yee-haw' ) . '!'
            );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.5.3
         *
         * @param bool $is_upgrade
         */
        private function add_after_plan_activation_or_upgrade_instructions_notice( $is_upgrade = true ) {
            $this->add_complete_upgrade_instructions_notice(
                $is_upgrade ?
                    $this->get_text_inline( 'Your plan was successfully upgraded.', 'plan-upgraded-message' ) :
                    $this->get_text_inline( 'Your plan was successfully activated.', 'plan-activated-message' ),
                'plan_upgraded'
            );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.1.0
         *
         * @param string      $url
         * @param array       $request
         * @param int         $success_cache_expiration
         * @param int         $failure_cache_expiration
         * @param bool        $maybe_enrich_request_for_debug
         *
         * @return WP_Error|array
         */
        static function safe_remote_post(
            &$url,
            $request,
            $success_cache_expiration = 0,
            $failure_cache_expiration = 0,
            $maybe_enrich_request_for_debug = true
        ) {
            $should_cache = ($success_cache_expiration + $failure_cache_expiration > 0);

            $cache_key = $should_cache ? md5( fs_strip_url_protocol($url) . json_encode( $request ) ) : false;

            $response = (!WP_FS__DEBUG_SDK && ( false !== $cache_key )) ?
                get_transient( $cache_key ) :
                false;

            if ( false === $response ) {
                if ( $maybe_enrich_request_for_debug ) {
                    FS_DebugManager::enrich_request_for_debug( $url, $request );
                }

                if ( ! isset( $request['method'] ) ) {
                    $request['method'] = 'POST';
                }

                $response = FS_Api::remote_request( $url, $request );

                if (
                    'https://' === substr( $url, 0, 8 ) &&
                    FS_Api::is_ssl_error_response( $response )
                ) {
                    // Failed due to old version of cURL or Open SSL (SSLv3 is not supported by CloudFlare).
                    $url = 'http://' . substr( $url, 8 );

                    $request['timeout'] = 15;
                    $response           = FS_Api::remote_request( $url, $request );
                }

                if ( false !== $cache_key ) {
                    set_transient(
                        $cache_key,
                        $response,
                        ( ( $response instanceof WP_Error ) ?
                            $failure_cache_expiration :
                            $success_cache_expiration )
                    );
                }
            }

            return $response;
        }

        /**
         * This method is used to enrich the after upgrade notice instructions when the upgraded
         * license cannot be activated network wide (license quota isn't large enough).
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return string
         */
        private function get_license_network_activation_notice() {
            if ( ! $this->_is_network_active ) {
                // Module isn't network level activated.
                return '';
            }

            if ( ! fs_is_network_admin() ) {
                // Not network level admin.
                return '';
            }

            if ( get_blog_count() == 1 ) {
                // There's only a single site in the network so if there's a context license it was already activated.
                return '';
            }

            if ( ! is_object( $this->_license ) ) {
                // No context license.
                return '';
            }

            if ( $this->_license->is_single_site() && 0 < $this->_license->activated ) {
                // License was already utilized (this is not 100% the case if all the network is localhost sites and the license can be utilized on unlimited localhost sites).
                return '';
            }

            if ( $this->can_activate_license_on_network( $this->_license ) ) {
                // License can be activated on all the network, so probably, the license is already activate on all the network (that's how the after upgrade sync works).
                return '';
            }

            return sprintf(
                $this->get_text_inline( '%sClick here%s to choose the sites where you\'d like to activate the license on.', 'network-choose-sites-for-license' ),
                '<a href="' . $this->get_account_url( false, array( 'activate_license' => 'true' ) ) . '">',
                '</a>'
            );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.7
         *
         * @param string $key
         *
         * @return string
         */
        function get_text( $key ) {
            return fs_text( $key, $this->_slug );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text Translatable string.
         * @param string $key  String key for overrides.
         *
         * @return string
         */
        function get_text_inline( $text, $key = '' ) {
            return _fs_text_inline( $text, $key, $this->_slug );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text    Translatable string.
         * @param string $context Context information for the translators.
         * @param string $key     String key for overrides.
         *
         * @return string
         */
        function get_text_x_inline( $text, $context, $key ) {
            return _fs_text_x_inline( $text, $context, $key, $this->_slug );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text Translatable string.
         * @param string $key  String key for overrides.
         *
         * @return string
         */
        function esc_html_inline( $text, $key ) {
            return esc_html( _fs_text_inline( $text, $key, $this->_slug ) );
        }

        #----------------------------------------------------------------------------------
        #region Versioning
        #----------------------------------------------------------------------------------

        /**
         * Check if Freemius in SDK upgrade mode.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @return bool
         */
        function is_sdk_upgrade_mode() {
            return isset( $this->_storage->sdk_upgrade_mode ) ?
                $this->_storage->sdk_upgrade_mode :
                false;
        }

        /**
         * Turn SDK upgrade mode off.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         */
        function set_sdk_upgrade_complete() {
            $this->_storage->sdk_upgrade_mode = false;
        }

        /**
         * Check if plugin upgrade mode.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @return bool
         */
        function is_plugin_upgrade_mode() {
            return isset( $this->_storage->plugin_upgrade_mode ) ?
                $this->_storage->plugin_upgrade_mode :
                false;
        }

        /**
         * Turn plugin upgrade mode off.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         */
        function set_plugin_upgrade_complete() {
            $this->_storage->plugin_upgrade_mode = false;

            $license_migration = ! empty( $this->_storage->license_migration ) ?
                $this->_storage->license_migration :
                array();

            $license_migration['is_migrating'] = false;

            $this->_storage->license_migration = $license_migration;
        }

        #endregion

        #----------------------------------------------------------------------------------
        #region Permissions
        #----------------------------------------------------------------------------------

        /**
         * Check if specific permission requested.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.6
         *
         * @param string $permission
         *
         * @return bool
         */
        function is_permission_requested( $permission ) {
            return isset( $this->_permissions[ $permission ] ) && ( true === $this->_permissions[ $permission ] );
        }

        #endregion

        #----------------------------------------------------------------------------------
        #region Auto Activation
        #----------------------------------------------------------------------------------

        /**
         * Hints the SDK if running an auto-installation.
         *
         * @var bool
         */
        private $_isAutoInstall = false;

        /**
         * After upgrade callback to install and auto activate a plugin.
         * This code will only be executed on explicit request from the user,
         * following the practice Jetpack are using with their theme installations.
         *
         * @link   https://make.wordpress.org/plugins/2017/03/16/clarification-of-guideline-8-executable-code-and-installs/
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.7
         */
        function _install_premium_version_ajax_action() {
            $this->_logger->entrance();

            $this->check_ajax_referer( 'install_premium_version' );

            if ( ! $this->is_registered() ) {
                // Not registered.
                self::shoot_ajax_failure( array(
                    'message' => $this->get_text_inline( 'Auto installation only works for opted-in users.', 'auto-install-error-not-opted-in' ),
                    'code'    => 'premium_installed',
                ) );
            }

            $plugin_id = fs_request_get( 'target_module_id', $this->get_id() );

            if ( ! FS_Plugin::is_valid_id( $plugin_id ) ) {
                // Invalid ID.
                self::shoot_ajax_failure( array(
                    'message' => $this->get_text_inline( 'Invalid module ID.', 'auto-install-error-invalid-id' ),
                    'code'    => 'invalid_module_id',
                ) );
            }

            if ( $plugin_id == $this->get_id() ) {
                if ( $this->is_premium() ) {
                    // Already using the premium code version.
                    self::shoot_ajax_failure( array(
                        'message' => $this->get_text_inline( 'Premium version already active.', 'auto-install-error-premium-activated' ),
                        'code'    => 'premium_installed',
                    ) );
                }
                if ( ! $this->can_use_premium_code() ) {
                    // Don't have access to the premium code.
                    self::shoot_ajax_failure( array(
                        'message' => $this->get_text_inline( 'You do not have a valid license to access the premium version.', 'auto-install-error-invalid-license' ),
                        'code'    => 'invalid_license',
                    ) );
                }
                if ( ! $this->has_release_on_freemius() ) {
                    // Plugin is a serviceware, no premium code version.
                    self::shoot_ajax_failure( array(
                        'message' => $this->get_text_inline( 'Plugin is a "Serviceware" which means it does not have a premium code version.', 'auto-install-error-serviceware' ),
                        'code'    => 'premium_version_missing',
                    ) );
                }
            } else {
                $addon = $this->get_addon( $plugin_id );

                if ( ! is_object( $addon ) ) {
                    // Invalid add-on ID.
                    self::shoot_ajax_failure( array(
                        'message' => $this->get_text_inline( 'Invalid module ID.', 'auto-install-error-invalid-id' ),
                        'code'    => 'invalid_module_id',
                    ) );
                }

                if ( $this->is_addon_activated( $plugin_id, true ) ) {
                    // Premium add-on version is already activated.
                    self::shoot_ajax_failure( array(
                        'message' => $this->get_text_inline( 'Premium add-on version already installed.', 'auto-install-error-premium-addon-activated' ),
                        'code'    => 'premium_installed',
                    ) );
                }
            }

            $this->_isAutoInstall = true;

            // Try to install and activate.
            $updater = FS_Plugin_Updater::instance( $this );
            $result  = $updater->install_and_activate_plugin( $plugin_id );

            if ( is_array( $result ) && ! empty( $result['message'] ) ) {
                self::shoot_ajax_failure( array(
                    'message' => $result['message'],
                    'code'    => $result['code'],
                ) );
            }

            self::shoot_ajax_success( $result );
        }

        /**
         * Displays module activation dialog box after a successful upgrade
         * where the user explicitly requested to auto download and install
         * the premium version.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.7
         */
        function _add_auto_installation_dialog_box() {
            $this->_logger->entrance();

            if ( ! $this->is_registered() ) {
                // Not registered.
                return;
            }

            $plugin_id = fs_request_get( 'plugin_id', $this->get_id() );

            if ( ! FS_Plugin::is_valid_id( $plugin_id ) ) {
                // Invalid module ID.
                return;
            }

            if ( $plugin_id == $this->get_id() ) {
                if ( $this->is_premium() ) {
                    // Already using the premium code version.
                    return;
                }
                if ( ! $this->can_use_premium_code() ) {
                    // Don't have access to the premium code.
                    return;
                }
                if ( ! $this->has_release_on_freemius() ) {
                    // Plugin is a serviceware, no premium code version.
                    return;
                }
            } else {
                $addon = $this->get_addon( $plugin_id );

                if ( ! is_object( $addon ) ) {
                    // Invalid add-on ID.
                    return;
                }

                if ( $this->is_addon_activated( $plugin_id, true ) ) {
                    // Premium add-on version is already activated.
                    return;
                }
            }

            $vars = array(
                'id'               => $this->_module_id,
                'target_module_id' => $plugin_id,
                'slug'             => $this->_slug,
            );

            fs_require_template( 'auto-installation.php', $vars );
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Tabs Integration
        #--------------------------------------------------------------------------------

        #region Module's Original Tabs

        /**
         * Inject a JavaScript logic to capture the theme tabs HTML.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         */
        function _tabs_capture() {
            $this->_logger->entrance();

            if (
                ! $this->is_product_settings_page() ||
                ! $this->should_page_include_tabs() ||
                ! $this->is_matching_url( $this->main_menu_url() )
            ) {
                return;
            }

            $params = array(
                'id' => $this->_module_id,
            );

            fs_require_once_template( 'tabs-capture-js.php', $params );
        }

        /**
         * Cache theme's tabs HTML for a week. The cache will also be set as expired
         * after version and type (free/premium) changes, in addition to the week period.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         */
        function _store_tabs_ajax_action() {
            $this->_logger->entrance();

            $this->check_ajax_referer( 'store_tabs' );

            // Init filesystem if not yet initiated.
            WP_Filesystem();

            // Get POST body HTML data.
            global $wp_filesystem;
            $tabs_html = $wp_filesystem->get_contents( "php://input" );

            if ( is_string( $tabs_html ) ) {
                $tabs_html = trim( $tabs_html );
            }

            if ( ! is_string( $tabs_html ) || empty( $tabs_html ) ) {
                self::shoot_ajax_failure();
            }

            $this->_cache->set( 'tabs', $tabs_html, 7 * WP_FS__TIME_24_HOURS_IN_SEC );

            self::shoot_ajax_success();
        }

        /**
         * Cache theme's settings page custom styles. The cache will also be set as expired
         * after version and type (free/premium) changes, in addition to the week period.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         */
        function _store_tabs_styles() {
            $this->_logger->entrance();

            if (
                ! $this->is_product_settings_page() ||
                ! $this->should_page_include_tabs() ||
                ! $this->is_matching_url( $this->main_menu_url() )
            ) {
                return;
            }

            $wp_styles = wp_styles();

            $theme_styles_url = get_template_directory_uri();

            $stylesheets = array();
            foreach ( $wp_styles->queue as $handler ) {
                if ( fs_starts_with( $handler, 'fs_' ) ) {
                    // Assume that stylesheets that their handler starts with "fs_" belong to the SDK.
                    continue;
                }

                /**
                 * @var _WP_Dependency $stylesheet
                 */
                $stylesheet = $wp_styles->registered[ $handler ];

                if ( fs_starts_with( $stylesheet->src, $theme_styles_url ) ) {
                    $stylesheets[] = $stylesheet->src;
                }
            }

            if ( ! empty( $stylesheets ) ) {
                $this->_cache->set( 'tabs_stylesheets', $stylesheets, 7 * WP_FS__TIME_24_HOURS_IN_SEC );
            }
        }

        /**
         * Check if module's original settings page has any tabs.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @return bool
         */
        private function has_tabs() {
            return $this->_cache->has( 'tabs' );
        }

        /**
         * Get module's settings page HTML content, starting
         * from the beginning of the <div class="wrap"> element,
         * until the tabs HTML (including).
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @return string
         */
        private function get_tabs_html() {
            $this->_logger->entrance();

            return $this->_cache->get( 'tabs' );
        }

        /**
         * Check if page should include tabs.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @return bool
         */
        private function should_page_include_tabs() {
            if ( ! $this->has_settings_menu() ) {
                // Don't add tabs if no settings at all.
                return false;
            }

            if ( self::NAVIGATION_TABS !== $this->_navigation ) {
                // Only add tabs to themes for now.
                return false;
            }

            if ( $this->is_theme() && ! $this->has_paid_plan() && ! $this->has_addons() ) {
                // Only add tabs to monetizing themes.
                return false;
            }

            if ( ! $this->is_product_settings_page() ) {
                // Only add tabs if browsing one of the product's setting pages.
                return false;
            }

            if ( $this->is_activation_mode() && $this->is_activation_page() ) {
                // Don't include tabs in the activation page.
                return false;
            }

            if ( $this->is_admin_page( 'pricing' ) && fs_request_get_bool( 'checkout' ) ) {
                // Don't add tabs on checkout page, we want to reduce distractions
                // as much as possible.
                return false;
            }

            return true;
        }

        /**
         * Add the tabs HTML before the setting's page content and
         * enqueue any required stylesheets.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @return bool If tabs were included.
         */
        function _add_tabs_before_content() {
            $this->_logger->entrance();

            if ( ! $this->should_page_include_tabs() ) {
                return false;
            }

            $tabs_html = $this->get_tabs_html();

            if ( empty( $tabs_html ) ) {
                return false;
            }

            /**
             * Enqueue the original stylesheets that are included in the
             * theme settings page. That way, if the theme settings has
             * some custom _styled_ content above the tabs UI, this
             * will make sure that the styling is preserved.
             */
            $stylesheets = $this->_cache->get( 'tabs_stylesheets', array() );
            if ( is_array( $stylesheets ) ) {
                for ( $i = 0, $len = count( $stylesheets ); $i < $len; $i ++ ) {
                    wp_enqueue_style( "fs_{$this->_module_id}_tabs_{$i}", $stylesheets[ $i ] );
                }
            }

            // Cut closing </div> tag.
            echo substr( trim( $tabs_html ), 0, - 6 );

            return true;
        }

        /**
         * Add the tabs closing HTML after the setting's page content.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @return bool If tabs closing HTML was included.
         */
        function _add_tabs_after_content() {
            $this->_logger->entrance();

            if ( ! $this->should_page_include_tabs() ) {
                return false;
            }

            echo '</div>';

            return true;
        }

        #endregion

        /**
         * Add in-page JavaScript to inject the Freemius tabs into
         * the module's setting tabs section.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         */
        function _add_freemius_tabs() {
            $this->_logger->entrance();

            if ( ! $this->should_page_include_tabs() ) {
                return;
            }

            $params = array( 'id' => $this->_module_id );
            fs_require_once_template( 'tabs.php', $params );
        }

        #endregion

        #--------------------------------------------------------------------------------
        #region Customizer Integration for Themes
        #--------------------------------------------------------------------------------

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         *
         * @param WP_Customize_Manager $customizer
         */
        function _customizer_register( $customizer ) {
            $this->_logger->entrance();

            if ( $this->is_pricing_page_visible() ) {
                require_once WP_FS__DIR_INCLUDES . '/customizer/class-fs-customizer-upsell-control.php';

                $customizer->add_section( 'freemius_upsell', array(
                    'title'    => '&#9733; ' . $this->get_text_inline( 'View paid features', 'view-paid-features' ),
                    'priority' => 1,
                ) );
                $customizer->add_setting( 'freemius_upsell', array(
                    'sanitize_callback' => 'esc_html',
                ) );

                $customizer->add_control( new FS_Customizer_Upsell_Control( $customizer, 'freemius_upsell', array(
                    'fs'       => $this,
                    'section'  => 'freemius_upsell',
                    'priority' => 100,
                ) ) );
            }

            if ( $this->is_page_visible( 'contact' ) || $this->is_page_visible( 'support' ) ) {
                require_once WP_FS__DIR_INCLUDES . '/customizer/class-fs-customizer-support-section.php';

                // Main Documentation Link In Customizer Root.
                $customizer->add_section( new FS_Customizer_Support_Section( $customizer, 'freemius_support', array(
                    'fs'       => $this,
                    'priority' => 1000,
                ) ) );
            }
        }

        #endregion

        /**
         * If the theme has a paid version, add some custom
         * styling to the theme's premium version (if exists)
         * to highlight that it's the premium version of the
         * same theme, making it easier for identification
         * after the user upgrades and upload it to the site.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.2.7
         */
        function _style_premium_theme() {
            $this->_logger->entrance();

            if ( ! self::is_themes_page() ) {
                // Only include in the themes page.
                return;
            }

            if ( ! $this->has_paid_plan() ) {
                // Only include if has any paid plans.
                return;
            }

            $params = null;
            fs_require_once_template( '/js/jquery.content-change.php', $params );

            $params = array(
                'slug' => $this->_slug,
                'id'   => $this->_module_id,
            );

            fs_require_template( '/js/style-premium-theme.php', $params );
        }

        /**
         * This method will return the absolute URL of the module's local icon.
         *
         * When you are running your plugin or theme on a **localhost** environment, if the icon
         * is not found in the local assets folder, try to fetch the icon URL from Freemius. If not set and
         * it's a plugin hosted on WordPress.org, try fetching the icon URL from wordpress.org.
         * If an icon is found, this method will automatically attempt to download the icon and store it
         * in /freemius/assets/img/{slug}.{png|jpg|gif|svg}.
         *
         * It's important to mention that this method is NOT phoning home since the developer will deploy
         * the product with the local icon in the assets folder. The download process just simplifies
         * the process for the developer.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return string
         */
        function get_local_icon_url() {
            global $fs_active_plugins;

            /**
             * @since 1.1.7.5
             */
            $local_path = $this->apply_filters( 'plugin_icon', false );

            if ( is_string( $local_path ) ) {
                $icons = array( $local_path );
            } else {
                $img_dir = WP_FS__DIR_IMG;

                // Locate the main assets folder.
                if ( 1 < count( $fs_active_plugins->plugins ) ) {
                    $plugin_or_theme_img_dir = ( $this->is_plugin() ? WP_PLUGIN_DIR : get_theme_root( get_stylesheet() ) );

                    foreach ( $fs_active_plugins->plugins as $sdk_path => &$data ) {
                        if ( $data->plugin_path == $this->get_plugin_basename() ) {
                            $img_dir = $plugin_or_theme_img_dir
                                       . '/'
                                       /**
                                        * The basename will be `themes` or the basename of a custom themes directory.
                                        *
                                        * @author Leo Fajardo (@leorw)
                                        * @since 2.2.3
                                        */
                                       . str_replace( '../' . basename( $plugin_or_theme_img_dir ) . '/', '', $sdk_path )
                                       . '/assets/img';

                            break;
                        }
                    }
                }

                // Try to locate the icon in the assets folder.
                $icons = glob( fs_normalize_path( $img_dir . "/{$this->_slug}.*" ) );

                if ( ! is_array( $icons ) || 0 === count( $icons ) ) {
                    if ( ! WP_FS__IS_LOCALHOST && $this->is_theme() ) {
                        $icons = array(
                            fs_normalize_path( $img_dir . '/theme-icon.png' )
                        );
                    } else {
                        $icon_found = false;
                        $local_path = fs_normalize_path( "{$img_dir}/{$this->_slug}.png" );

                        if ( ! function_exists( 'get_filesystem_method' ) ) {
                            require_once ABSPATH . 'wp-admin/includes/file.php';
                        }

                        $have_write_permissions = ( 'direct' === get_filesystem_method( array(), fs_normalize_path( $img_dir ) ) );

                        /**
                         * IMPORTANT: THIS CODE WILL NEVER RUN AFTER THE PLUGIN IS IN THE REPO.
                         *
                         * This code will only be executed once during the testing
                         * of the plugin in a local environment. The plugin icon file WILL
                         * already exist in the assets folder when the plugin is deployed to
                         * the repository.
                         */
                        if ( WP_FS__IS_LOCALHOST && $have_write_permissions ) {
                            // Fetch icon from Freemius.
                            $icon = $this->fetch_remote_icon_url();

                            // Fetch icon from WordPress.org.
                            if ( empty( $icon ) && $this->is_plugin() && $this->is_org_repo_compliant() ) {
                                if ( ! function_exists( 'plugins_api' ) ) {
                                    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
                                }

                                $plugin_information = plugins_api( 'plugin_information', array(
                                    'slug'   => $this->_slug,
                                    'fields' => array(
                                        'sections' => false,
                                        'tags'     => false,
                                        'icons'    => true
                                    )
                                ) );

                                if (
                                    ! is_wp_error( $plugin_information )
                                    && isset( $plugin_information->icons )
                                    && ! empty( $plugin_information->icons )
                                ) {
                                    /**
                                     * Get the smallest icon.
                                     *
                                     * @author Leo Fajardo (@leorw)
                                     * @since  1.2.2
                                     */
                                    $icon = end( $plugin_information->icons );
                                }
                            }

                            if ( ! empty( $icon ) ) {
                                if ( 0 !== strpos( $icon, 'http' ) ) {
                                    $icon = 'http:' . $icon;
                                }

                                /**
                                 * Get a clean file extension, e.g.: "jpg" and not "jpg?rev=1305765".
                                 *
                                 * @author Leo Fajardo (@leorw)
                                 * @since  1.2.2
                                 */
                                $ext = pathinfo( strtok( $icon, '?' ), PATHINFO_EXTENSION );

                                $local_path = fs_normalize_path( "{$img_dir}/{$this->_slug}.{$ext}" );

                                // Try to download the icon.
                                $icon_found = fs_download_image( $icon, $local_path );
                            }
                        }

                        if ( ! $icon_found ) {
                            // No icons found, fallback to default icon.
                            if ( $have_write_permissions ) {
                                // If have write permissions, copy default icon.
                                copy( fs_normalize_path( $img_dir . "/{$this->_module_type}-icon.png" ), $local_path );
                            } else {
                                // If doesn't have write permissions, use default icon path.
                                $local_path = fs_normalize_path( $img_dir . "/{$this->_module_type}-icon.png" );
                            }
                        }

                        $icons = array( $local_path );
                    }
                }
            }

            $icon_dir = dirname( $icons[0] );

            return fs_img_url( substr( $icons[0], strlen( $icon_dir ) ), $icon_dir );
        }

        /**
         * Fetch module's extended info.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return object|mixed
         */
        private function fetch_module_info() {
            return $this->get_api_plugin_scope()->get( 'info.json', false, WP_FS__TIME_WEEK_IN_SEC );
        }

        /**
         * Fetch module's remote icon URL.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @return string
         */
        function fetch_remote_icon_url() {
            $info = $this->fetch_module_info();

            return ( $this->is_api_result_object( $info, 'icon' ) && is_string( $info->icon ) ) ?
                $info->icon :
                '';
        }

        #--------------------------------------------------------------------------------
        #region GDPR
        #--------------------------------------------------------------------------------

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.1.0
         *
         * @param array $user_plugins
         *
         * @return string
         */
        private function get_gdpr_admin_notice_string( $user_plugins ) {
            $this->_logger->entrance();

            $addons = self::get_all_addons();

            foreach ( $user_plugins as $user_plugin ) {
                $has_addons = isset( $addons[ $user_plugin->id ] );

                if ( WP_FS__MODULE_TYPE_PLUGIN === $user_plugin->type && ! $has_addons ) {
                    if ( $this->_module_id == $user_plugin->id ) {
                        $addons     = $this->get_addons();
                        $has_addons = ( ! empty( $addons ) );
                    } else {
                        $plugin_api = FS_Api::instance(
                            $user_plugin->id,
                            'plugin',
                            $user_plugin->id,
                            $user_plugin->public_key,
                            ! $user_plugin->is_live,
                            false,
                            $this->get_sdk_version()
                        );

                        $addons_result = $plugin_api->get( '/addons.json?enriched=true', true );

                        if ( $this->is_api_result_object( $addons_result, 'plugins' ) &&
                             is_array( $addons_result->plugins ) &&
                             ! empty( $addons_result->plugins )
                        ) {
                            $has_addons = true;
                        }
                    }
                }

                $user_plugin->has_addons = $has_addons;
            }

            $is_single_parent_product = ( 1 === count( $user_plugins ) );

            $multiple_products_text = '';

            if ( $is_single_parent_product ) {
                $single_parent_product = reset( $user_plugins );

                $thank_you = sprintf(
                    "<span data-plugin-id='%d'>%s</span>",
                    $single_parent_product->id,
                    sprintf(
                        $single_parent_product->has_addons ?
                            $this->get_text_inline( 'Thank you so much for using %s and its add-ons!', 'thank-you-for-using-product-and-its-addons' ) :
                            $this->get_text_inline( 'Thank you so much for using %s!', 'thank-you-for-using-product' ),
                        sprintf('<b><i>%s</i></b>', $single_parent_product->title)
                    )
                );

                $already_opted_in = sprintf(
                    $this->get_text_inline( "You've already opted-in to our usage-tracking, which helps us keep improving the %s.", 'already-opted-in-to-product-usage-tracking' ),
                    ( WP_FS__MODULE_TYPE_THEME === $single_parent_product->type ) ? WP_FS__MODULE_TYPE_THEME : WP_FS__MODULE_TYPE_PLUGIN
                );
            } else {
                $thank_you        = $this->get_text_inline( 'Thank you so much for using our products!', 'thank-you-for-using-products' );
                $already_opted_in = $this->get_text_inline( "You've already opted-in to our usage-tracking, which helps us keep improving them.", 'already-opted-in-to-products-usage-tracking' );

                $products_and_add_ons = '';
                foreach ( $user_plugins as $user_plugin ) {
                    if ( ! empty( $products_and_add_ons ) ) {
                        $products_and_add_ons .= ', ';
                    }

                    if ( ! $user_plugin->has_addons ) {
                        $products_and_add_ons .= sprintf(
                            "<span data-plugin-id='%d'>%s</span>",
                            $user_plugin->id,
                            $user_plugin->title
                        );
                    } else {
                        $products_and_add_ons .= sprintf(
                            "<span data-plugin-id='%d'>%s</span>",
                            $user_plugin->id,
                            sprintf(
                                $this->get_text_inline( '%s and its add-ons', 'product-and-its-addons' ),
                                $user_plugin->title
                            )
                        );
                    }
                }

                $multiple_products_text = sprintf(
                    "<small class='products'><strong>%s:</strong> %s</small>",
                    $this->get_text_inline( 'Products', 'products' ),
                    $products_and_add_ons
                );
            }

            $actions = sprintf(
                '<ul><li>%s<span class="action-description"> - %s</span></li><li>%s<span class="action-description"> - %s</span></li></ul>',
                sprintf('<button class="button button-primary allow-marketing">%s</button>', $this->get_text_inline( 'Yes', 'yes' ) ),
                $this->get_text_inline( 'send me security & feature updates, educational content and offers.', 'send-updates' ),
                sprintf('<button class="button button-secondary">%s</button>', $this->get_text_inline( 'No', 'no' ) ),
                sprintf(
                    $this->get_text_inline( 'do %sNOT%s send me security & feature updates, educational content and offers.', 'do-not-send-updates' ),
                    '<span class="underlined">',
                    '</span>'
                )
            );

            return sprintf(
                '%s %s %s',
                $thank_you,
                $already_opted_in,
                sprintf( $this->get_text_inline( 'Due to the new %sEU General Data Protection Regulation (GDPR)%s compliance requirements it is required that you provide your explicit consent, again, confirming that you are onboard :-)', 'due-to-gdpr-compliance-requirements' ), '<a href="https://ec.europa.eu/info/law/law-topic/data-protection_en/" target="_blank" rel="noopener noreferrer">', '</a>' ) .
                '<br><br>' .
                '<b>' . $this->get_text_inline( "Please let us know if you'd like us to contact you for security & feature updates, educational content, and occasional offers:", 'contact-for-updates' ) . '</b>' .
                $actions .
                ( $is_single_parent_product ? '' : $multiple_products_text )
            );
        }

        /**
         * This method is called for opted-in users to fetch the is_marketing_allowed flag of the user for all the
         * plugins and themes they've opted in to.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.1.0
         *
         * @param string      $user_email
         * @param string      $license_key
         * @param array       $plugin_ids
         * @param string|null $license_key
         *
         * @return array|false
         */
        private function fetch_user_marketing_flag_status_by_plugins( $user_email, $license_key, $plugin_ids ) {
            $request = array(
                'method'  => 'POST',
                'body'    => array(),
                'timeout' => WP_FS__DEBUG_SDK ? 60 : 30,
            );

            if ( is_string( $user_email ) ) {
                $request['body']['email'] = $user_email;
            } else {
                $request['body']['license_key'] = $license_key;
            }

            $result = array();

            $url              = WP_FS__ADDRESS . '/action/service/user_plugin/';
            $total_plugin_ids = count( $plugin_ids );

            $plugin_ids_count_per_request = 10;
            for ( $i = 1; $i <= $total_plugin_ids; $i += $plugin_ids_count_per_request ) {
                $plugin_ids_set = array_slice( $plugin_ids, $i - 1, $plugin_ids_count_per_request );

                $request['body']['plugin_ids'] = $plugin_ids_set;

                $response = self::safe_remote_post(
                    $url,
                    $request,
                    WP_FS__TIME_24_HOURS_IN_SEC,
                    WP_FS__TIME_12_HOURS_IN_SEC
                );

                if ( ! is_wp_error( $response ) ) {
                    $decoded = is_string( $response['body'] ) ?
                        json_decode( $response['body'] ) :
                        null;

                    if (
                        !is_object($decoded) ||
                        !isset($decoded->success) ||
                        true !== $decoded->success ||
                        !isset( $decoded->data ) ||
                        !is_array( $decoded->data )
                    ) {
                        return false;
                    }

                    $result = array_merge( $result, $decoded->data );
                }
            }

            return $result;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.1.0
         */
        function _maybe_show_gdpr_admin_notice() {
            if ( ! $this->is_user_in_admin() ) {
                return;
            }

            if ( ! $this->should_handle_gdpr_admin_notice() ) {
                return;
            }

            if ( ! $this->is_user_admin() ) {
                return;
            }

            require_once WP_FS__DIR_INCLUDES . '/class-fs-user-lock.php';

            $lock = FS_User_Lock::instance();

            /**
             * Try to acquire a 60-sec lock based on the WP user and thread/process ID.
             */
            if ( ! $lock->try_lock( 60 ) ) {
                return;
            }

            /**
             * @var $current_wp_user WP_User
             */
            $current_wp_user = self::_get_current_wp_user();

            /**
             * @var FS_User $current_fs_user
             */
            $current_fs_user = Freemius::_get_user_by_email( $current_wp_user->user_email );

            $ten_years_in_sec = 10 * 365 * WP_FS__TIME_24_HOURS_IN_SEC;

            if ( ! is_object( $current_fs_user ) ) {
                // 10-year lock.
                $lock->lock( $ten_years_in_sec );

                return;
            }

            $gdpr = FS_GDPR_Manager::instance();

            if ( $gdpr->is_opt_in_notice_shown() ) {
                // 30-day lock.
                $lock->lock( 30 * WP_FS__TIME_24_HOURS_IN_SEC );

                return;
            }

            if ( ! $gdpr->should_show_opt_in_notice() ) {
                // 10-year lock.
                $lock->lock( $ten_years_in_sec );

                return;
            }

            $last_time_notice_shown  = $gdpr->last_time_notice_was_shown();
            $was_notice_shown_before = ( false !== $last_time_notice_shown );

            if ( $was_notice_shown_before &&
                 30 * WP_FS__TIME_24_HOURS_IN_SEC > time() - $last_time_notice_shown
            ) {
                // If the notice was shown before, show it again after 30 days from the last time it was shown.
                return;
            }

            /**
             * Find all plugin IDs that were installed by the current admin.
             */
            $plugin_ids_map = self::get_user_opted_in_module_ids_map( $current_fs_user->id );

            if ( empty( $plugin_ids_map )) {
                $lock->lock( $ten_years_in_sec );

                return;
            }

            $user_plugins = $this->fetch_user_marketing_flag_status_by_plugins(
                $current_fs_user->email,
                null,
                array_keys( $plugin_ids_map )
            );

            if ( empty( $user_plugins ) ) {
                $lock->lock(
                    is_array($user_plugins) ?
                        $ten_years_in_sec :
                        // Lock for 24-hours on errors.
                        WP_FS__TIME_24_HOURS_IN_SEC
                );

                return;
            }

            $has_unset_marketing_optin = false;

            foreach ( $user_plugins as $user_plugin ) {
                if ( true == $user_plugin->is_marketing_allowed ) {
                    unset( $plugin_ids_map[ $user_plugin->plugin_id ] );
                }

                if ( ! $has_unset_marketing_optin && is_null( $user_plugin->is_marketing_allowed ) ) {
                    $has_unset_marketing_optin = true;
                }
            }

            if ( empty( $plugin_ids_map ) ||
                 ( $was_notice_shown_before && ! $has_unset_marketing_optin )
            ) {
                $lock->lock( $ten_years_in_sec );

                return;
            }

            $modules = array_merge(
                array_values( self::maybe_get_entities_account_option( 'plugins', array() ) ),
                array_values( self::maybe_get_entities_account_option( 'themes', array() ) )
            );

            foreach ( $modules as $module ) {
                if ( ! FS_Plugin::is_valid_id( $module->parent_plugin_id ) && isset( $plugin_ids_map[ $module->id ] ) ) {
                    $plugin_ids_map[ $module->id ] = $module;
                }
            }

            $plugin_title = null;
            if ( 1 === count( $plugin_ids_map ) ) {
                $module       = reset( $plugin_ids_map );
                $plugin_title = $module->title;
            }

            $gdpr->add_opt_in_sticky_notice(
                $this->get_gdpr_admin_notice_string( $plugin_ids_map ),
                $plugin_title
            );

            $this->add_gdpr_optin_ajax_handler_and_style();

            $gdpr->notice_was_just_shown();

            // 30-day lock.
            $lock->lock( 30 * WP_FS__TIME_24_HOURS_IN_SEC );
        }

        /**
         * Prevents the GDPR opt-in admin notice from being added if the user has already chosen to allow or not allow
         * marketing.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.1.0
         */
        private function disable_opt_in_notice_and_lock_user() {
            FS_GDPR_Manager::instance()->disable_opt_in_notice();

            require_once WP_FS__DIR_INCLUDES . '/class-fs-user-lock.php';

            // 10-year lock.
            FS_User_Lock::instance()->lock( 10 * 365 * WP_FS__TIME_24_HOURS_IN_SEC );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.5.4
         */
        static function _add_api_connectivity_notice_handler_js() {
            fs_require_once_template( 'api-connectivity-message-js.php' );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.1.0
         */
        function _add_gdpr_optin_js() {
            $vars = array( 'id' => $this->_module_id );

            fs_require_once_template( 'gdpr-optin-js.php', $vars );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.1.0
         */
        function enqueue_gdpr_optin_notice_style() {
            fs_enqueue_local_style( 'fs_gdpr_optin_notice', '/admin/gdpr-optin-notice.css' );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.1.0
         */
        function _maybe_add_gdpr_optin_ajax_handler() {
            $this->add_ajax_action( 'fetch_is_marketing_required_flag_value', array( &$this, '_fetch_is_marketing_required_flag_value_ajax_action' ) );

            if ( FS_GDPR_Manager::instance()->is_opt_in_notice_shown() ) {
                $this->add_gdpr_optin_ajax_handler_and_style();
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.1.0
         */
        function _fetch_is_marketing_required_flag_value_ajax_action() {
            $this->_logger->entrance();

            $this->check_ajax_referer( 'fetch_is_marketing_required_flag_value' );

            $license_key = fs_request_get_raw( 'license_key' );

            if ( empty($license_key) ) {
                self::shoot_ajax_failure( $this->get_text_inline( 'License key is empty.', 'empty-license-key' ) );
            }

            $user_plugins = $this->fetch_user_marketing_flag_status_by_plugins(
                null,
                $license_key,
                array( $this->_module_id )
            );

            if ( ! is_array( $user_plugins ) ||
                 empty($user_plugins) ||
                 !isset($user_plugins[0]->plugin_id) ||
                 $user_plugins[0]->plugin_id != $this->_module_id
            ) {
                /**
                 * If faced an error or if the module ID do not match to the current module, ask for GDPR opt-in.
                 *
                 * @author Vova Feldman (@svovaf)
                 */
                self::shoot_ajax_success( array(
                    'is_marketing_allowed' => null,
                    'license_owner_id'     => null
                ) );
            }

            self::shoot_ajax_success( array(
                'is_marketing_allowed' => $user_plugins[0]->is_marketing_allowed,
                'license_owner_id'     => ( isset( $user_plugins[0]->license_owner_id ) ? $user_plugins[0]->license_owner_id : null )
            ) );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.2
         *
         * @param number[] $install_ids
         *
         * @return array {
         *      An array of objects containing the installs' licenses owners data.
         *
         *      @property number $id User ID.
         *      @property string $email User email (can be masked email).
         * }
         */
        private function fetch_installs_licenses_owners_data( $install_ids ) {
            $this->_logger->entrance();

            $response = $this->get_api_user_scope()->get(
                '/licenses_owners.json?install_ids=' . implode( ',', $install_ids )
            );

            $license_owners = array();

            if ( $this->is_api_result_object( $response, 'owners' ) ) {
                $license_owners = $response->owners;
            }

            return $license_owners;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.1.0
         */
        private function add_gdpr_optin_ajax_handler_and_style() {
            // Add GDPR action AJAX callback.
            $this->add_ajax_action( 'gdpr_optin_action', array( &$this, '_gdpr_optin_ajax_action' ) );

            add_action( 'admin_footer', array( &$this, '_add_gdpr_optin_js' ) );
            add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_gdpr_optin_notice_style' ) );
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.1.0
         */
        function _gdpr_optin_ajax_action() {
            $this->_logger->entrance();

            $this->check_ajax_referer( 'gdpr_optin_action' );

            if ( ! fs_request_has( 'is_marketing_allowed' ) || ! fs_request_has( 'plugin_ids' ) ) {
                self::shoot_ajax_failure();
            }

            $current_wp_user = self::_get_current_wp_user();

            $plugin_ids = fs_request_get( 'plugin_ids', array() );
            if ( ! is_array( $plugin_ids ) || empty( $plugin_ids ) ) {
                self::shoot_ajax_failure();
            }

            $modules = array_merge(
                array_values( self::maybe_get_entities_account_option( 'plugins', array() ) ),
                array_values( self::maybe_get_entities_account_option( 'themes', array() ) )
            );

            foreach ( $modules as $key => $module ) {
                if ( ! in_array( $module->id, $plugin_ids ) ) {
                    unset( $modules[ $key ] );
                }
            }

            if ( empty( $modules ) ) {
                self::shoot_ajax_failure();
            }

            $user_api = $this->get_api_user_scope_by_user( Freemius::_get_user_by_email( $current_wp_user->user_email ) );

            foreach ( $modules as $module ) {
                $user_api->call( "?plugin_id={$module->id}", 'put', array(
                    'is_marketing_allowed' => ( true == fs_request_get_bool( 'is_marketing_allowed' ) )
                ) );
            }

            FS_GDPR_Manager::instance()->remove_opt_in_notice();

            require_once WP_FS__DIR_INCLUDES . '/class-fs-user-lock.php';

            // 10-year lock.
            FS_User_Lock::instance()->lock( 10 * 365 * WP_FS__TIME_24_HOURS_IN_SEC );

            self::shoot_ajax_success();
        }

        /**
         * Checks if the GDPR admin notice should be handled. By default, this logic is off, unless the integrator adds the special 'handle_gdpr_admin_notice' filter.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.1.0
         *
         * @return bool
         */
        private function should_handle_gdpr_admin_notice() {
            return $this->apply_filters(
                'handle_gdpr_admin_notice',
                // Default to false.
                false
            );
        }

        #endregion

        #----------------------------------------------------------------------------------
        #region Marketing
        #----------------------------------------------------------------------------------

        /**
         * Check if current user purchased any other plugins before.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @return bool
         */
        function has_purchased_before() {
            // TODO: Implement has_purchased_before() method.
            throw new Exception( 'not implemented' );
        }

        /**
         * Check if current user classified as an agency.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @return bool
         */
        function is_agency() {
            // TODO: Implement is_agency() method.
            throw new Exception( 'not implemented' );
        }

        /**
         * Check if current user classified as a developer.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @return bool
         */
        function is_developer() {
            // TODO: Implement is_developer() method.
            throw new Exception( 'not implemented' );
        }

        /**
         * Check if current user classified as a business.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.9
         *
         * @return bool
         */
        function is_business() {
            // TODO: Implement is_business() method.
            throw new Exception( 'not implemented' );
        }

        #endregion

        #----------------------------------------------------------------------------------
        #region Helper
        #----------------------------------------------------------------------------------

        /**
         * If running with a secret key, assume it's the developer and show pending plans as well.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.1.2
         *
         * @param string $path
         *
         * @return string
         */
        function add_show_pending( $path ) {
            if ( ! $this->has_secret_key() ) {
                return $path;
            }

            return $path . ( false !== strpos( $path, '?' ) ? '&' : '?' ) . 'show_pending=true';
        }

        #endregion
    }
