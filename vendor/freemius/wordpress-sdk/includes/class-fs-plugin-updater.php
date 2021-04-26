<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2015, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       1.0.4
     *
     * @link        https://github.com/easydigitaldownloads/EDD-License-handler/blob/master/EDD_SL_Plugin_Updater.php
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    class FS_Plugin_Updater {

        /**
         * @var Freemius
         * @since 1.0.4
         */
        private $_fs;
        /**
         * @var FS_Logger
         * @since 1.0.4
         */
        private $_logger;
        /**
         * @var object
         * @since 1.1.8.1
         */
        private $_update_details;
        /**
         * @var array
         * @since 2.1.2
         */
        private $_translation_updates;

        private static $_upgrade_basename = null;

        #--------------------------------------------------------------------------------
        #region Singleton
        #--------------------------------------------------------------------------------

        /**
         * @var FS_Plugin_Updater[]
         * @since 2.0.0
         */
        private static $_INSTANCES = array();

        /**
         * @param Freemius $freemius
         *
         * @return FS_Plugin_Updater
         */
        static function instance( Freemius $freemius ) {
            $key = $freemius->get_id();

            if ( ! isset( self::$_INSTANCES[ $key ] ) ) {
                self::$_INSTANCES[ $key ] = new self( $freemius );
            }

            return self::$_INSTANCES[ $key ];
        }

        #endregion

        private function __construct( Freemius $freemius ) {
            $this->_fs = $freemius;

            $this->_logger = FS_Logger::get_logger( WP_FS__SLUG . '_' . $freemius->get_slug() . '_updater', WP_FS__DEBUG_SDK, WP_FS__ECHO_DEBUG_SDK );

            $this->filters();
        }

        /**
         * Initiate required filters.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         */
        private function filters() {
            // Override request for plugin information
            add_filter( 'plugins_api', array( &$this, 'plugins_api_filter' ), 10, 3 );

            $this->add_transient_filters();

            /**
             * If user has the premium plugin's code but do NOT have an active license,
             * encourage him to upgrade by showing that there's a new release, but instead
             * of showing an update link, show upgrade link to the pricing page.
             *
             * @since 1.1.6
             *
             */
            // WP 2.9+
            add_action( "after_plugin_row_{$this->_fs->get_plugin_basename()}", array(
                &$this,
                'catch_plugin_update_row'
            ), 9 );
            add_action( "after_plugin_row_{$this->_fs->get_plugin_basename()}", array(
                &$this,
                'edit_and_echo_plugin_update_row'
            ), 11, 2 );

            if ( ! $this->_fs->has_any_active_valid_license() ) {
                add_action( 'admin_head', array( &$this, 'catch_plugin_information_dialog_contents' ) );
            }

            if ( ! WP_FS__IS_PRODUCTION_MODE ) {
                add_filter( 'http_request_host_is_external', array(
                    $this,
                    'http_request_host_is_external_filter'
                ), 10, 3 );
            }

            if ( $this->_fs->is_premium() ) {
                if ( ! $this->is_correct_folder_name() ) {
                    add_filter( 'upgrader_post_install', array( &$this, '_maybe_update_folder_name' ), 10, 3 );
                }

                add_filter( 'upgrader_pre_install', array( 'FS_Plugin_Updater', '_store_basename_for_source_adjustment' ), 1, 2 );
                add_filter( 'upgrader_source_selection', array( 'FS_Plugin_Updater', '_maybe_adjust_source_dir' ), 1, 3 );

                if ( ! $this->_fs->has_any_active_valid_license() ) {
                    add_filter( 'wp_prepare_themes_for_js', array( &$this, 'change_theme_update_info_html' ), 10, 1 );
                }
            }
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.1.4
         */
        function catch_plugin_information_dialog_contents() {
            if (
                'plugin-information' !== fs_request_get( 'tab', false ) ||
                $this->_fs->get_slug() !== fs_request_get( 'plugin', false )
            ) {
                return;
            }

            add_action( 'admin_footer', array( &$this, 'edit_and_echo_plugin_information_dialog_contents' ), 0, 1 );

            ob_start();
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.1.4
         *
         * @param string $hook_suffix
         */
        function edit_and_echo_plugin_information_dialog_contents( $hook_suffix ) {
            if (
                'plugin-information' !== fs_request_get( 'tab', false ) ||
                $this->_fs->get_slug() !== fs_request_get( 'plugin', false )
            ) {
                return;
            }

            $license = $this->_fs->_get_license();

            $subscription = ( is_object( $license ) && ! $license->is_lifetime() ) ?
                $this->_fs->_get_subscription( $license->id ) :
                null;

            $contents = ob_get_clean();

            $update_button_id_attribute_pos = strpos( $contents, 'id="plugin_update_from_iframe"' );

            if ( false !== $update_button_id_attribute_pos ) {
                $update_button_start_pos = strrpos(
                    substr( $contents, 0, $update_button_id_attribute_pos ),
                    '<a'
                );

                $update_button_end_pos = ( strpos( $contents, '</a>', $update_button_id_attribute_pos ) + strlen( '</a>' ) );

                /**
                 * The part of the contents without the update button.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.2.5
                 */
                $modified_contents = substr( $contents, 0, $update_button_start_pos );

                $update_button = substr( $contents, $update_button_start_pos, ( $update_button_end_pos - $update_button_start_pos ) );

                /**
                 * Replace the plugin information dialog's "Install Update Now" button's text and URL. If there's a license,
                 * the text will be "Renew license" and will link to the checkout page with the license's billing cycle
                 * and quota. If there's no license, the text will be "Buy license" and will link to the pricing page.
                 */
                $update_button = preg_replace(
                    '/(\<a.+)(id="plugin_update_from_iframe")(.+href=")([^\s]+)(".*\>)(.+)(\<\/a>)/is',
                    is_object( $license ) ?
                        sprintf(
                            '$1$3%s$5%s$7',
                            $this->_fs->checkout_url(
                                is_object( $subscription ) ?
                                    ( 1 == $subscription->billing_cycle ? WP_FS__PERIOD_MONTHLY : WP_FS__PERIOD_ANNUALLY ) :
                                    WP_FS__PERIOD_LIFETIME,
                                false,
                                array( 'licenses' => $license->quota )
                            ),
                            fs_text_inline( 'Renew license', 'renew-license', $this->_fs->get_slug() )
                        ) :
                        sprintf(
                            '$1$3%s$5%s$7',
                            $this->_fs->pricing_url(),
                            fs_text_inline( 'Buy license', 'buy-license', $this->_fs->get_slug() )
                        ),
                    $update_button
                );

                /**
                 * Append the modified button.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.2.5
                 */
                $modified_contents .= $update_button;

                /**
                 * Append the remaining part of the contents after the update button.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.2.5
                 */
                $modified_contents .= substr( $contents, $update_button_end_pos );

                $contents = $modified_contents;
            }

            echo $contents;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         */
        private function add_transient_filters() {
            if ( $this->_fs->is_premium() && ! $this->_fs->is_tracking_allowed() ) {
                $this->_logger->log( 'Opted out sites cannot receive automatic software updates.' );

                return;
            }

            add_filter( 'pre_set_site_transient_update_plugins', array(
                &$this,
                'pre_set_site_transient_update_plugins_filter'
            ) );

            add_filter( 'pre_set_site_transient_update_themes', array(
                &$this,
                'pre_set_site_transient_update_plugins_filter'
            ) );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         */
        private function remove_transient_filters() {
            remove_filter( 'pre_set_site_transient_update_plugins', array(
                &$this,
                'pre_set_site_transient_update_plugins_filter'
            ) );

            remove_filter( 'pre_set_site_transient_update_themes', array(
                &$this,
                'pre_set_site_transient_update_plugins_filter'
            ) );
        }

        /**
         * Capture plugin update row by turning output buffering.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.6
         */
        function catch_plugin_update_row() {
            ob_start();
        }

        /**
         * Overrides default update message format with "renew your license" message.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.6
         *
         * @param string $file
         * @param array  $plugin_data
         */
        function edit_and_echo_plugin_update_row( $file, $plugin_data ) {
            $plugin_update_row = ob_get_clean();

            $current = get_site_transient( 'update_plugins' );
            if ( ! isset( $current->response[ $file ] ) ) {
                echo $plugin_update_row;

                return;
            }

            $r = $current->response[ $file ];

            $has_beta_update = $this->_fs->has_beta_update();

            if ( $this->_fs->has_any_active_valid_license() ) {
                if ( $has_beta_update ) {
                    /**
                     * Turn the "new version" text into "new Beta version".
                     *
                     * Sample input:
                     *      There is a new version of Awesome Plugin available. <a href="...>View version x.y.z details</a> or <a href="...>update now</a>.
                     * Output:
                     *      There is a new Beta version of Awesome Plugin available. <a href="...>View version x.y.z details</a> or <a href="...>update now</a>.
                     *
                     * @author Leo Fajardo (@leorw)
                     * @since 2.3.0
                     */
                    $plugin_update_row = preg_replace(
                        '/(\<div.+>)(.+)(\<a.+href="([^\s]+)"([^\<]+)\>.+\<a.+)(\<\/div\>)/is',
                        (
                            '$1' .
                            sprintf(
                                fs_text_inline( 'There is a %s of %s available.', 'new-version-available', $this->_fs->get_slug() ),
                                $has_beta_update ?
                                    fs_text_inline( 'new Beta version', 'new-beta-version', $this->_fs->get_slug() ) :
                                    fs_text_inline( 'new version', 'new-version', $this->_fs->get_slug() ),
                                $this->_fs->get_plugin_title()
                            ) .
                            ' ' .
                            '$3' .
                            '$6'
                        ),
                        $plugin_update_row
                    );
                }
            } else {
                /**
                 * Turn the "new version" text into a link that opens the plugin information dialog when clicked and
                 * make the "View version x details" text link to the checkout page instead of opening the plugin
                 * information dialog when clicked.
                 *
                 * Sample input:
                 *      There is a new version of Awesome Plugin available. <a href="...>View version x.y.z details</a> or <a href="...>update now</a>.
                 * Output:
                 *      There is a <a href="...>new version</a> of Awesome Plugin available. <a href="...>Buy a license now</a> to access version x.y.z security & feature updates, and support.
                 *      OR
                 *      There is a <a href="...>new Beta version</a> of Awesome Plugin available. <a href="...>Buy a license now</a> to access version x.y.z security & feature updates, and support.
                 *
                 * @author Leo Fajardo (@leorw)
                 */
                $plugin_update_row = preg_replace(
                    '/(\<div.+>)(.+)(\<a.+href="([^\s]+)"([^\<]+)\>.+\<a.+)(\<\/div\>)/is',
                    (
                        '$1' .
                        sprintf(
                            fs_text_inline( 'There is a %s of %s available.', 'new-version-available', $this->_fs->get_slug() ),
                            sprintf(
                                '<a href="$4"%s>%s</a>',
                                '$5',
                                $has_beta_update ?
                                    fs_text_inline( 'new Beta version', 'new-beta-version', $this->_fs->get_slug() ) :
                                    fs_text_inline( 'new version', 'new-version', $this->_fs->get_slug() )
                            ),
                            $this->_fs->get_plugin_title()
                        ) .
                        ' ' .
                        $this->_fs->version_upgrade_checkout_link( $r->new_version ) .
                        '$6'
                    ),
                    $plugin_update_row
                );
            }

            if (
                $this->_fs->is_plugin() &&
                isset( $r->upgrade_notice ) &&
                strlen( trim( $r->upgrade_notice ) ) > 0
            ) {
                $slug = $this->_fs->get_slug();

                $upgrade_notice_html = sprintf(
                    '<p class="notice fs-upgrade-notice fs-slug-%1$s fs-type-%2$s" data-slug="%1$s" data-type="%2$s"><strong>%3$s</strong> %4$s</p>',
                    $slug,
                    $this->_fs->get_module_type(),
                    fs_text_inline( 'Important Upgrade Notice:', 'upgrade_notice', $slug ),
                    esc_html( $r->upgrade_notice )
                );

                $plugin_update_row = str_replace( '</div>', '</div>' . $upgrade_notice_html, $plugin_update_row );
            }

            echo $plugin_update_row;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.0.2
         *
         * @param array $prepared_themes
         *
         * @return array
         */
        function change_theme_update_info_html( $prepared_themes ) {
            $theme_basename = $this->_fs->get_plugin_basename();

            if ( ! isset( $prepared_themes[ $theme_basename ] ) ) {
                return $prepared_themes;
            }

            $themes_update = get_site_transient( 'update_themes' );
            if ( ! isset( $themes_update->response[ $theme_basename ] ) ||
                empty( $themes_update->response[ $theme_basename ]['package'] )
            ) {
                return $prepared_themes;
            }

            $prepared_themes[ $theme_basename ]['update'] = preg_replace(
                '/(\<p.+>)(.+)(\<a.+\<a.+)\.(.+\<\/p\>)/is',
                '$1 $2 ' . $this->_fs->version_upgrade_checkout_link( $themes_update->response[ $theme_basename ]['new_version'] ) .
                '$4',
                $prepared_themes[ $theme_basename ]['update']
            );

            // Set to false to prevent the "Update now" link for the context theme from being shown on the "Themes" page.
            $prepared_themes[ $theme_basename ]['hasPackage'] = false;

            return $prepared_themes;
        }

        /**
         * Since WP version 3.6, a new security feature was added that denies access to repository with a local ip.
         * During development mode we want to be able updating plugin versions via our localhost repository. This
         * filter white-list all domains including "api.freemius".
         *
         * @link   http://www.emanueletessore.com/wordpress-download-failed-valid-url-provided/
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @param bool   $allow
         * @param string $host
         * @param string $url
         *
         * @return bool
         */
        function http_request_host_is_external_filter( $allow, $host, $url ) {
            return ( false !== strpos( $host, 'freemius' ) ) ? true : $allow;
        }

        /**
         * Check for Updates at the defined API endpoint and modify the update array.
         *
         * This function dives into the update api just when WordPress creates its update array,
         * then adds a custom API call and injects the custom plugin data retrieved from the API.
         * It is reassembled from parts of the native WordPress plugin update code.
         * See wp-includes/update.php line 121 for the original wp_update_plugins() function.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @uses   FS_Api
         *
         * @param object $transient_data Update array build by WordPress.
         *
         * @return object Modified update array with custom plugin data.
         */
        function pre_set_site_transient_update_plugins_filter( $transient_data ) {
            $this->_logger->entrance();

            /**
             * "plugins" or "themes".
             *
             * @author Leo Fajardo (@leorw)
             * @since  1.2.2
             */
            $module_type = $this->_fs->get_module_type() . 's';

            /**
             * Ensure that we don't mix plugins update info with themes update info.
             *
             * @author Leo Fajardo (@leorw)
             * @since  1.2.2
             */
            if ( "pre_set_site_transient_update_{$module_type}" !== current_filter() ) {
                return $transient_data;
            }

            if ( empty( $transient_data ) ||
                 defined( 'WP_FS__UNINSTALL_MODE' )
            ) {
                return $transient_data;
            }

            global $wp_current_filter;

            $current_plugin_version = $this->_fs->get_plugin_version();

            if ( ! empty( $wp_current_filter ) && 'upgrader_process_complete' === $wp_current_filter[0] ) {
                if (
                    is_null( $this->_update_details ) ||
                    ( is_object( $this->_update_details ) && $this->_update_details->new_version !== $current_plugin_version )
                ) {
                    /**
                     * After an update, clear the stored update details and reparse the plugin's main file in order to get
                     * the updated version's information and prevent the previous update information from showing up on the
                     * updates page.
                     *
                     * @author Leo Fajardo (@leorw)
                     * @since 2.3.1
                     */
                    $this->_update_details  = null;
                    $current_plugin_version = $this->_fs->get_plugin_version( true );
                }
            }

            if ( ! isset( $this->_update_details ) ) {
                // Get plugin's newest update.
                $new_version = $this->_fs->get_update(
                    false,
                    fs_request_get_bool( 'force-check' ),
                    WP_FS__TIME_24_HOURS_IN_SEC / 24,
                    $current_plugin_version
                );

                $this->_update_details = false;

                if ( is_object( $new_version ) && $this->is_new_version_premium( $new_version ) ) {
                    $this->_logger->log( 'Found newer plugin version ' . $new_version->version );

                    /**
                     * Cache plugin details locally since set_site_transient( 'update_plugins' )
                     * called multiple times and the non wp.org plugins are filtered after the
                     * call to .org.
                     *
                     * @since 1.1.8.1
                     */
                    $this->_update_details = $this->get_update_details( $new_version );
                }
            }

            // Alias.
            $basename = $this->_fs->premium_plugin_basename();

            if ( is_object( $this->_update_details ) ) {
                if ( isset( $transient_data->no_update ) ) {
                    unset( $transient_data->no_update[ $basename ] );
                }

                if ( ! isset( $transient_data->response ) ) {
                    $transient_data->response = array();
                }

                // Add plugin to transient data.
                $transient_data->response[ $basename ] = $this->_fs->is_plugin() ?
                    $this->_update_details :
                    (array) $this->_update_details;
            } else {
                if ( isset( $transient_data->response ) ) {
                    /**
                     * Ensure that there's no update data for the plugin to prevent upgrading the premium version to the latest free version.
                     *
                     * @author Leo Fajardo (@leorw)
                     * @since 2.3.0
                     */
                    unset( $transient_data->response[ $basename ] );
                }

                if ( ! isset( $transient_data->no_update ) ) {
                    $transient_data->no_update = array();
                }

                /**
                 * Add product to no_update transient data to properly integrate with WP 5.5 auto-updates UI.
                 *
                 * @since 2.4.1
                 * @link https://make.wordpress.org/core/2020/07/30/recommended-usage-of-the-updates-api-to-support-the-auto-updates-ui-for-plugins-and-themes-in-wordpress-5-5/
                 */
                $transient_data->no_update[ $basename ] = $this->_fs->is_plugin() ?
                    (object) array(
                        'id'            => $basename,
                        'slug'          => $this->_fs->get_slug(),
                        'plugin'        => $basename,
                        'new_version'   => $this->_fs->get_plugin_version(),
                        'url'           => '',
                        'package'       => '',
                        'icons'         => array(),
                        'banners'       => array(),
                        'banners_rtl'   => array(),
                        'tested'        => '',
                        'requires_php'  => '',
                        'compatibility' => new stdClass(),
                    ) :
                    array(
                        'theme'        => $basename,
                        'new_version'  => $this->_fs->get_plugin_version(),
                        'url'          => '',
                        'package'      => '',
                        'requires'     => '',
                        'requires_php' => '',
                    );
            }

            $slug = $this->_fs->get_slug();

            if ( $this->_fs->is_org_repo_compliant() && $this->_fs->is_freemium() ) {
                if ( ! isset( $this->_translation_updates ) ) {
                    $this->_translation_updates = array();

                    if ( current_user_can( 'update_languages' ) ) {
                        $translation_updates = $this->fetch_wp_org_module_translation_updates( $module_type, $slug );
                        if ( ! empty( $translation_updates ) ) {
                            $this->_translation_updates = $translation_updates;
                        }
                    }
                }

                if ( ! empty( $this->_translation_updates ) ) {
                    $all_translation_updates = ( isset( $transient_data->translations ) && is_array( $transient_data->translations ) ) ?
                        $transient_data->translations :
                        array();

                    $current_plugin_translation_updates_map = array();
                    foreach ( $all_translation_updates as $key => $translation_update ) {
                        if ( $module_type === ( $translation_update['type'] . 's' ) && $slug === $translation_update['slug'] ) {
                            $current_plugin_translation_updates_map[ $translation_update['language'] ] = $translation_update;
                            unset( $all_translation_updates[ $key ] );
                        }
                    }

                    foreach ( $this->_translation_updates as $translation_update ) {
                        $lang = $translation_update['language'];
                        if ( ! isset( $current_plugin_translation_updates_map[ $lang ] ) ||
                            version_compare( $translation_update['version'], $current_plugin_translation_updates_map[ $lang ]['version'], '>' )
                        ) {
                            $current_plugin_translation_updates_map[ $lang ] = $translation_update;
                        }
                    }

                    $transient_data->translations = array_merge( $all_translation_updates, array_values( $current_plugin_translation_updates_map ) );
                }
            }

            return $transient_data;
        }

        /**
         * Get module's required data for the updates mechanism.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param \FS_Plugin_Tag $new_version
         *
         * @return object
         */
        function get_update_details( FS_Plugin_Tag $new_version ) {
            $update              = new stdClass();
            $update->slug        = $this->_fs->get_slug();
            $update->new_version = $new_version->version;
            $update->url         = WP_FS__ADDRESS;
            $update->package     = $new_version->url;
            $update->tested      = $new_version->tested_up_to_version;
            $update->requires    = $new_version->requires_platform_version;

            $icon = $this->_fs->get_local_icon_url();

            if ( ! empty( $icon ) ) {
                $update->icons = array(
//                    '1x'      => $icon,
//                    '2x'      => $icon,
                    'default' => $icon,
                );
            }

            if ( $this->_fs->is_premium() ) {
                $latest_tag = $this->_fs->_fetch_latest_version( $this->_fs->get_id(), false );

                if (
                    isset( $latest_tag->readme ) &&
                    isset( $latest_tag->readme->upgrade_notice ) &&
                    ! empty( $latest_tag->readme->upgrade_notice )
                ) {
                    $update->upgrade_notice = $latest_tag->readme->upgrade_notice;
                }
            }

            $update->{$this->_fs->get_module_type()} = $this->_fs->get_plugin_basename();

            return $update;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @param FS_Plugin_Tag $new_version
         *
         * @return bool
         */
        private function is_new_version_premium( FS_Plugin_Tag $new_version ) {
            $query_str = parse_url( $new_version->url, PHP_URL_QUERY );
            if ( empty( $query_str ) ) {
                return false;
            }

            parse_str( $query_str, $params );

            return ( isset( $params['is_premium'] ) && 'true' == $params['is_premium'] );
        }

        /**
         * Update the updates transient with the module's update information.
         *
         * This method is required for multisite environment.
         * If a module is site activated (not network) and not on the main site,
         * the module will NOT be executed on the network level, therefore, the
         * custom updates logic will not be executed as well, so unless we force
         * the injection of the update into the updates transient, premium updates
         * will not work.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param \FS_Plugin_Tag $new_version
         */
        function set_update_data( FS_Plugin_Tag $new_version ) {
            $this->_logger->entrance();

            if ( ! $this->is_new_version_premium( $new_version ) ) {
                return;
            }

            $transient_key = "update_{$this->_fs->get_module_type()}s";

            $transient_data = get_site_transient( $transient_key );

            $transient_data = is_object( $transient_data ) ?
                $transient_data :
                new stdClass();

            // Alias.
            $basename  = $this->_fs->get_plugin_basename();
            $is_plugin = $this->_fs->is_plugin();

            if ( ! isset( $transient_data->response ) ||
                 ! is_array( $transient_data->response )
            ) {
                $transient_data->response = array();
            } else if ( ! empty( $transient_data->response[ $basename ] ) ) {
                $version = $is_plugin ?
                    ( ! empty( $transient_data->response[ $basename ]->new_version ) ?
                        $transient_data->response[ $basename ]->new_version :
                        null
                    ) : ( ! empty( $transient_data->response[ $basename ]['new_version'] ) ?
                        $transient_data->response[ $basename ]['new_version'] :
                        null
                    );

                if ( $version == $new_version->version ) {
                    // The update data is already set.
                    return;
                }
            }

            // Remove the added filters.
            $this->remove_transient_filters();

            $this->_update_details = $this->get_update_details( $new_version );

            // Set update data in transient.
            $transient_data->response[ $basename ] = $is_plugin ?
                $this->_update_details :
                (array) $this->_update_details;

            if ( ! isset( $transient_data->checked ) ||
                 ! is_array( $transient_data->checked )
            ) {
                $transient_data->checked = array();
            }

            // Flag the module as if it was already checked.
            $transient_data->checked[ $basename ] = $this->_fs->get_plugin_version();
            $transient_data->last_checked         = time();

            set_site_transient( $transient_key, $transient_data );

            $this->add_transient_filters();
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.0.2
         */
        function delete_update_data() {
            $this->_logger->entrance();

            $transient_key = "update_{$this->_fs->get_module_type()}s";

            $transient_data = get_site_transient( $transient_key );

            // Alias
            $basename = $this->_fs->get_plugin_basename();

            if ( ! is_object( $transient_data ) ||
                ! isset( $transient_data->response ) ||
                 ! is_array( $transient_data->response ) ||
                empty( $transient_data->response[ $basename ] )
            ) {
                return;
            }

            unset( $transient_data->response[ $basename ] );

            // Remove the added filters.
            $this->remove_transient_filters();

            set_site_transient( $transient_key, $transient_data );

            $this->add_transient_filters();
        }

        /**
         * Try to fetch plugin's info from .org repository.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.5
         *
         * @param string $action
         * @param object $args
         *
         * @return bool|mixed
         */
        static function _fetch_plugin_info_from_repository( $action, $args ) {
            $url = $http_url = 'http://api.wordpress.org/plugins/info/1.0/';
            if ( $ssl = wp_http_supports( array( 'ssl' ) ) ) {
                $url = set_url_scheme( $url, 'https' );
            }

            $args = array(
                'timeout' => 15,
                'body'    => array(
                    'action'  => $action,
                    'request' => serialize( $args )
                )
            );

            $request = wp_remote_post( $url, $args );

            if ( is_wp_error( $request ) ) {
                return false;
            }

            $res = maybe_unserialize( wp_remote_retrieve_body( $request ) );

            if ( ! is_object( $res ) && ! is_array( $res ) ) {
                return false;
            }

            return $res;
        }

        /**
         * Fetches module translation updates from wordpress.org.
         *
         * @author Leo Fajardo (@leorw)
         * @since  2.1.2
         *
         * @param string $module_type
         * @param string $slug
         *
         * @return array|null
         */
        private function fetch_wp_org_module_translation_updates( $module_type, $slug ) {
            $plugin_data = $this->_fs->get_plugin_data();

            $locales = array_values( get_available_languages() );
            $locales = apply_filters( "{$module_type}_update_check_locales", $locales );
            $locales = array_unique( $locales );

            $plugin_basename = $this->_fs->get_plugin_basename();
            if ( 'themes' === $module_type ) {
                $plugin_basename = $slug;
            }

            global $wp_version;

            $request_args = array(
                'timeout' => 15,
                'body'    => array(
                    "{$module_type}" => json_encode(
                        array(
                            "{$module_type}" => array(
                                $plugin_basename => array(
                                    'Name'   => trim( str_replace( $this->_fs->get_plugin()->premium_suffix, '', $plugin_data['Name'] ) ),
                                    'Author' => $plugin_data['Author'],
                                )
                            )
                        )
                    ),
                    'translations'    => json_encode( $this->get_installed_translations( $module_type, $slug ) ),
                    'locale'          => json_encode( $locales )
                ),
                'user-agent' => ( 'WordPress/' . $wp_version . '; ' . home_url( '/' ) )
            );

            $url = "http://api.wordpress.org/{$module_type}/update-check/1.1/";
            if ( $ssl = wp_http_supports( array( 'ssl' ) ) ) {
                $url = set_url_scheme( $url, 'https' );
            }

            $raw_response = Freemius::safe_remote_post(
                $url,
                $request_args,
                WP_FS__TIME_24_HOURS_IN_SEC,
                WP_FS__TIME_12_HOURS_IN_SEC,
                false
            );

            if ( is_wp_error( $raw_response ) ) {
                return null;
            }

            $response = json_decode( wp_remote_retrieve_body( $raw_response ), true );

            if ( ! is_array( $response ) ) {
                return null;
            }

            if ( ! isset( $response['translations'] ) || empty( $response['translations'] ) ) {
                return null;
            }

            return $response['translations'];
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since 2.1.2
         *
         * @param string $module_type
         * @param string $slug
         *
         * @return array
         */
        private function get_installed_translations( $module_type, $slug ) {
            if ( function_exists( 'wp_get_installed_translations' ) ) {
                return wp_get_installed_translations( $module_type );
            }

            $dir = "/{$module_type}";

            if ( ! is_dir( WP_LANG_DIR . $dir ) )
                return array();

            $files = scandir( WP_LANG_DIR . $dir );
            if ( ! $files )
                return array();

            $language_data = array();

            foreach ( $files as $file ) {
                if ( 0 !== strpos( $file, $slug ) ) {
                    continue;
                }

                if ( '.' === $file[0] || is_dir( WP_LANG_DIR . "{$dir}/{$file}" ) ) {
                    continue;
                }

                if ( substr( $file, -3 ) !== '.po' ) {
                    continue;
                }

                if ( ! preg_match( '/(?:(.+)-)?([a-z]{2,3}(?:_[A-Z]{2})?(?:_[a-z0-9]+)?).po/', $file, $match ) ) {
                    continue;
                }

                if ( ! in_array( substr( $file, 0, -3 ) . '.mo', $files ) )  {
                    continue;
                }

                list( , $textdomain, $language ) = $match;

                if ( '' === $textdomain ) {
                    $textdomain = 'default';
                }

                $language_data[ $textdomain ][ $language ] = wp_get_pomo_file_data( WP_LANG_DIR . "{$dir}/{$file}" );
            }

            return $language_data;
        }

        /**
         * Updates information on the "View version x.x details" page with custom data.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.4
         *
         * @uses   FS_Api
         *
         * @param object $data
         * @param string $action
         * @param mixed  $args
         *
         * @return object
         */
        function plugins_api_filter( $data, $action = '', $args = null ) {
            $this->_logger->entrance();

            if ( ( 'plugin_information' !== $action ) ||
                 ! isset( $args->slug )
            ) {
                return $data;
            }

            $addon         = false;
            $is_addon      = false;
            $addon_version = false;

            if ( $this->_fs->get_slug() !== $args->slug ) {
                $addon = $this->_fs->get_addon_by_slug( $args->slug );

                if ( ! is_object( $addon ) ) {
                    return $data;
                }

                if ( $this->_fs->is_addon_activated( $addon->id ) ) {
                    $addon_version = $this->_fs->get_addon_instance( $addon->id )->get_plugin_version();
                } else if ( $this->_fs->is_addon_installed( $addon->id ) ) {
                    $addon_plugin_data = get_plugin_data(
                        ( WP_PLUGIN_DIR . '/' . $this->_fs->get_addon_basename( $addon->id ) ),
                        false,
                        false
                    );

                    if ( ! empty( $addon_plugin_data ) ) {
                        $addon_version = $addon_plugin_data['Version'];
                    }
                }

                $is_addon = true;
            }

            $plugin_in_repo = false;
            if ( ! $is_addon ) {
                // Try to fetch info from .org repository.
                $data = self::_fetch_plugin_info_from_repository( $action, $args );

                $plugin_in_repo = ( false !== $data );
            }

            if ( ! $plugin_in_repo ) {
                $data = $args;

                // Fetch as much as possible info from local files.
                $plugin_local_data = $this->_fs->get_plugin_data();
                $data->name        = $plugin_local_data['Name'];
                $data->author      = $plugin_local_data['Author'];
                $data->sections    = array(
                    'description' => 'Upgrade ' . $plugin_local_data['Name'] . ' to latest.',
                );

                // @todo Store extra plugin info on Freemius or parse readme.txt markup.
                /*$info = $this->_fs->get_api_site_scope()->call('/information.json');

if ( !isset($info->error) ) {
    $data = $info;
}*/
            }

            $plugin_version = $is_addon ?
                $addon_version :
                $this->_fs->get_plugin_version();

            // Get plugin's newest update.
            $new_version = $this->get_latest_download_details( $is_addon ? $addon->id : false, $plugin_version );

            if ( ! is_object( $new_version ) || empty( $new_version->version ) ) {
                $data->version = $plugin_version;
            } else {
                if ( $is_addon ) {
                    $data->name    = $addon->title . ' ' . $this->_fs->get_text_inline( 'Add-On', 'addon' );
                    $data->slug    = $addon->slug;
                    $data->url     = WP_FS__ADDRESS;
                    $data->package = $new_version->url;
                }

                if ( ! $plugin_in_repo ) {
                    $data->last_updated = ! is_null( $new_version->updated ) ? $new_version->updated : $new_version->created;
                    $data->requires     = $new_version->requires_platform_version;
                    $data->tested       = $new_version->tested_up_to_version;
                }

                $data->version       = $new_version->version;
                $data->download_link = $new_version->url;

                if ( isset( $new_version->readme ) && is_object( $new_version->readme ) ) {
                    $new_version_readme_data = $new_version->readme;
                    if ( isset( $new_version_readme_data->sections ) ) {
                        $new_version_readme_data->sections = (array) $new_version_readme_data->sections;
                    } else {
                        $new_version_readme_data->sections = array();
                    }

                    if ( isset( $data->sections ) ) {
                        if ( isset( $data->sections['screenshots'] ) ) {
                            $new_version_readme_data->sections['screenshots'] = $data->sections['screenshots'];
                        }

                        if ( isset( $data->sections['reviews'] ) ) {
                            $new_version_readme_data->sections['reviews'] = $data->sections['reviews'];
                        }
                    }

                    if ( isset( $new_version_readme_data->banners ) ) {
                        $new_version_readme_data->banners = (array) $new_version_readme_data->banners;
                    } else if ( isset( $data->banners ) ) {
                        $new_version_readme_data->banners = $data->banners;
                    }

                    $wp_org_sections = array(
                        'author',
                        'author_profile',
                        'rating',
                        'ratings',
                        'num_ratings',
                        'support_threads',
                        'support_threads_resolved',
                        'active_installs',
                        'added',
                        'homepage'
                    );

                    foreach ( $wp_org_sections as $wp_org_section ) {
                        if ( isset( $data->{$wp_org_section} ) ) {
                            $new_version_readme_data->{$wp_org_section} = $data->{$wp_org_section};
                        }
                    }

                    $data = $new_version_readme_data;
                }
            }

            return $data;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.7
         *
         * @param number|bool $addon_id
         * @param bool|string $newer_than   Since 2.2.1
         * @param bool|string $fetch_readme Since 2.2.1
         *
         * @return object
         */
        private function get_latest_download_details( $addon_id = false, $newer_than = false, $fetch_readme = true ) {
            return $this->_fs->_fetch_latest_version( $addon_id, true, WP_FS__TIME_24_HOURS_IN_SEC, $newer_than, $fetch_readme );
        }

        /**
         * Checks if a given basename has a matching folder name
         * with the current context plugin.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.6
         *
         * @return bool
         */
        private function is_correct_folder_name() {
            return ( $this->_fs->get_target_folder_name() == trim( dirname( $this->_fs->get_plugin_basename() ), '/\\' ) );
        }

        /**
         * This is a special after upgrade handler for migrating modules
         * that didn't use the '-premium' suffix folder structure before
         * the migration.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.6
         *
         * @param bool  $response   Install response.
         * @param array $hook_extra Extra arguments passed to hooked filters.
         * @param array $result     Installation result data.
         *
         * @return bool
         */
        function _maybe_update_folder_name( $response, $hook_extra, $result ) {
            $basename = $this->_fs->get_plugin_basename();

            if ( true !== $response ||
                 empty( $hook_extra ) ||
                 empty( $hook_extra['plugin'] ) ||
                 $basename !== $hook_extra['plugin']
            ) {
                return $response;
            }

            $active_plugins_basenames = get_option( 'active_plugins' );

            foreach ( $active_plugins_basenames as $key => $active_plugin_basename ) {
                if ( $basename === $active_plugin_basename ) {
                    // Get filename including extension.
                    $filename = basename( $basename );

                    $new_basename = plugin_basename(
                        trailingslashit( $this->_fs->is_premium() ? $this->_fs->get_premium_slug() : $this->_fs->get_slug() ) .
                        $filename
                    );

                    // Verify that the expected correct path exists.
                    if ( file_exists( fs_normalize_path( WP_PLUGIN_DIR . '/' . $new_basename ) ) ) {
                        // Override active plugin name.
                        $active_plugins_basenames[ $key ] = $new_basename;
                        update_option( 'active_plugins', $active_plugins_basenames );
                    }

                    break;
                }
            }

            return $response;
        }

        #----------------------------------------------------------------------------------
        #region Auto Activation
        #----------------------------------------------------------------------------------

        /**
         * Installs and active a plugin when explicitly requested that from a 3rd party service.
         *
         * This logic was inspired by the TGMPA GPL licensed library by Thomas Griffin.
         *
         * @link   http://tgmpluginactivation.com/
         *
         * @author Vova Feldman
         * @since  1.2.1.7
         *
         * @link   https://make.wordpress.org/plugins/2017/03/16/clarification-of-guideline-8-executable-code-and-installs/
         *
         * @uses   WP_Filesystem
         * @uses   WP_Error
         * @uses   WP_Upgrader
         * @uses   Plugin_Upgrader
         * @uses   Plugin_Installer_Skin
         * @uses   Plugin_Upgrader_Skin
         *
         * @param number|bool $plugin_id
         *
         * @return array
         */
        function install_and_activate_plugin( $plugin_id = false ) {
            if ( ! empty( $plugin_id ) && ! FS_Plugin::is_valid_id( $plugin_id ) ) {
                // Invalid plugin ID.
                return array(
                    'message' => $this->_fs->get_text_inline( 'Invalid module ID.', 'auto-install-error-invalid-id' ),
                    'code'    => 'invalid_module_id',
                );
            }

            $is_addon = false;
            if ( FS_Plugin::is_valid_id( $plugin_id ) &&
                 $plugin_id != $this->_fs->get_id()
            ) {
                $addon = $this->_fs->get_addon( $plugin_id );

                if ( ! is_object( $addon ) ) {
                    // Invalid add-on ID.
                    return array(
                        'message' => $this->_fs->get_text_inline( 'Invalid module ID.', 'auto-install-error-invalid-id' ),
                        'code'    => 'invalid_module_id',
                    );
                }

                $slug          = $addon->slug;
                $premium_slug  = $addon->premium_slug;
                $title         = $addon->title . ' ' . $this->_fs->get_text_inline( 'Add-On', 'addon' );

                $is_addon = true;
            } else {
                $slug          = $this->_fs->get_slug();
                $premium_slug  = $this->_fs->get_premium_slug();
                $title         = $this->_fs->get_plugin_title() .
                                 ( $this->_fs->is_addon() ? ' ' . $this->_fs->get_text_inline( 'Add-On', 'addon' ) : '' );
            }

            if ( $this->is_premium_plugin_active( $plugin_id ) ) {
                // Premium version already activated.
                return array(
                    'message' => $is_addon ?
                        $this->_fs->get_text_inline( 'Premium add-on version already installed.', 'auto-install-error-premium-addon-activated' ) :
                        $this->_fs->get_text_inline( 'Premium version already active.', 'auto-install-error-premium-activated' ),
                    'code'    => 'premium_installed',
                );
            }

            $latest_version = $this->get_latest_download_details( $plugin_id, false, false );
            $target_folder  = $premium_slug;

            // Prep variables for Plugin_Installer_Skin class.
            $extra         = array();
            $extra['slug'] = $target_folder;
            $source        = $latest_version->url;
            $api           = null;

            $install_url = add_query_arg(
                array(
                    'action' => 'install-plugin',
                    'plugin' => urlencode( $slug ),
                ),
                'update.php'
            );

            if ( ! class_exists( 'Plugin_Upgrader', false ) ) {
                // Include required resources for the installation.
                require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            }

            $skin_args = array(
                'type'   => 'web',
                'title'  => sprintf( $this->_fs->get_text_inline( 'Installing plugin: %s', 'installing-plugin-x' ), $title ),
                'url'    => esc_url_raw( $install_url ),
                'nonce'  => 'install-plugin_' . $slug,
                'plugin' => '',
                'api'    => $api,
                'extra'  => $extra,
            );

//			$skin = new Automatic_Upgrader_Skin( $skin_args );
//			$skin = new Plugin_Installer_Skin( $skin_args );
            $skin = new WP_Ajax_Upgrader_Skin( $skin_args );

            // Create a new instance of Plugin_Upgrader.
            $upgrader = new Plugin_Upgrader( $skin );

            // Perform the action and install the plugin from the $source urldecode().
            add_filter( 'upgrader_source_selection', array( 'FS_Plugin_Updater', '_maybe_adjust_source_dir' ), 1, 3 );

            $install_result = $upgrader->install( $source );

            remove_filter( 'upgrader_source_selection', array( 'FS_Plugin_Updater', '_maybe_adjust_source_dir' ), 1 );

            if ( is_wp_error( $install_result ) ) {
                return array(
                    'message' => $install_result->get_error_message(),
                    'code'    => $install_result->get_error_code(),
                );
            } elseif ( is_wp_error( $skin->result ) ) {
                return array(
                    'message' => $skin->result->get_error_message(),
                    'code'    => $skin->result->get_error_code(),
                );
            } elseif ( $skin->get_errors()->get_error_code() ) {
                return array(
                    'message' => $skin->get_error_messages(),
                    'code'    => 'unknown',
                );
            } elseif ( is_null( $install_result ) ) {
                global $wp_filesystem;

                $error_code    = 'unable_to_connect_to_filesystem';
                $error_message = $this->_fs->get_text_inline( 'Unable to connect to the filesystem. Please confirm your credentials.' );

                // Pass through the error from WP_Filesystem if one was raised.
                if ( $wp_filesystem instanceof WP_Filesystem_Base &&
                     is_wp_error( $wp_filesystem->errors ) &&
                     $wp_filesystem->errors->get_error_code()
                ) {
                    $error_message = $wp_filesystem->errors->get_error_message();
                }

                return array(
                    'message' => $error_message,
                    'code'    => $error_code,
                );
            }

            // Grab the full path to the main plugin's file.
            $plugin_activate = $upgrader->plugin_info();

            // Try to activate the plugin.
            $activation_result = $this->try_activate_plugin( $plugin_activate );

            if ( is_wp_error( $activation_result ) ) {
                return array(
                    'message' => $activation_result->get_error_message(),
                    'code'    => $activation_result->get_error_code(),
                );
            }

            return $skin->get_upgrade_messages();
        }

        /**
         * Tries to activate a plugin. If fails, returns the error.
         *
         * @author Vova Feldman
         * @since  1.2.1.7
         *
         * @param string $file_path Path within wp-plugins/ to main plugin file.
         *                          This determines the styling of the output messages.
         *
         * @return bool|WP_Error
         */
        protected function try_activate_plugin( $file_path ) {
            $activate = activate_plugin( $file_path, '', $this->_fs->is_network_active() );

            return is_wp_error( $activate ) ?
                $activate :
                true;
        }

        /**
         * Check if a premium module version is already active.
         *
         * @author Vova Feldman
         * @since  1.2.1.7
         *
         * @param number|bool $plugin_id
         *
         * @return bool
         */
        private function is_premium_plugin_active( $plugin_id = false ) {
            if ( $plugin_id != $this->_fs->get_id() ) {
                return $this->_fs->is_addon_activated( $plugin_id, true );
            }

            return is_plugin_active( $this->_fs->premium_plugin_basename() );
        }

        /**
         * Store the basename since it's not always available in the `_maybe_adjust_source_dir` method below.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.2.1
         *
         * @param bool|WP_Error $response   Response.
         * @param array         $hook_extra Extra arguments passed to hooked filters.
         *
         * @return bool|WP_Error
         */
        static function _store_basename_for_source_adjustment( $response, $hook_extra ) {
            if ( isset( $hook_extra['plugin'] ) ) {
                self::$_upgrade_basename = $hook_extra['plugin'];
            } else if ( isset( $hook_extra['theme'] ) ) {
                self::$_upgrade_basename = $hook_extra['theme'];
            } else {
                self::$_upgrade_basename = null;
            }

            return $response;
        }

        /**
         * Adjust the plugin directory name if necessary.
         * Assumes plugin has a folder (not a single file plugin).
         *
         * The final destination directory of a plugin is based on the subdirectory name found in the
         * (un)zipped source. In some cases this subdirectory name is not the same as the expected
         * slug and the plugin will not be recognized as installed. This is fixed by adjusting
         * the temporary unzipped source subdirectory name to the expected plugin slug.
         *
         * @author Vova Feldman
         * @since  1.2.1.7
         * @since  2.2.1 The method was converted to static since when the admin update bulk products via the Updates section, the logic applies the `upgrader_source_selection` filter for every product that is being updated.
         *
         * @param string       $source        Path to upgrade/zip-file-name.tmp/subdirectory/.
         * @param string       $remote_source Path to upgrade/zip-file-name.tmp.
         * @param \WP_Upgrader $upgrader      Instance of the upgrader which installs the plugin.
         *
         * @return string|WP_Error
         */
        static function _maybe_adjust_source_dir( $source, $remote_source, $upgrader ) {
            if ( ! is_object( $GLOBALS['wp_filesystem'] ) ) {
                return $source;
            }

            $basename = self::$_upgrade_basename;
            $is_theme = false;

            // Figure out what the slug is supposed to be.
            if ( isset( $upgrader->skin->options['extra'] ) ) {
                // Set by the auto-install logic.
                $desired_slug = $upgrader->skin->options['extra']['slug'];
            } else if ( ! empty( $basename ) ) {
                /**
                 * If it doesn't end with ".php", it's a theme.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.2.1
                 */
                $is_theme = ( ! fs_ends_with( $basename, '.php' ) );

                $desired_slug = ( ! $is_theme ) ?
                    dirname( $basename ) :
                    // Theme slug
                    $basename;
            } else {
                // Can't figure out the desired slug, stop the execution.
                return $source;
            }

            if ( is_multisite() ) {
                /**
                 * If we are running in a multisite environment and the product is not network activated,
                 * the instance will not exist anyway. Therefore, try to update the source if necessary
                 * regardless if the Freemius instance of the product exists or not.
                 *
                 * @author Vova Feldman
                 */
            } else if ( ! empty( $basename ) ) {
                $fs = Freemius::get_instance_by_file(
                    $basename,
                    $is_theme ?
                        WP_FS__MODULE_TYPE_THEME :
                        WP_FS__MODULE_TYPE_PLUGIN
                );

                if ( ! is_object( $fs ) ) {
                    /**
                     * If the Freemius instance does not exist on a non-multisite network environment, it means that:
                     *  1. The product is not powered by Freemius; OR
                     *  2. The product is not activated, therefore, we don't mind if after the update the folder name will change.
                     *
                     * @author Leo Fajardo (@leorw)
                     * @since  2.2.1
                     */
                    return $source;
                }
            }

            $subdir_name = untrailingslashit( str_replace( trailingslashit( $remote_source ), '', $source ) );

            if ( ! empty( $subdir_name ) && $subdir_name !== $desired_slug ) {
                $from_path = untrailingslashit( $source );
                $to_path   = trailingslashit( $remote_source ) . $desired_slug;

                if ( true === $GLOBALS['wp_filesystem']->move( $from_path, $to_path ) ) {
                    return trailingslashit( $to_path );
                }

                return new WP_Error(
                    'rename_failed',
                    fs_text_inline( 'The remote plugin package does not contain a folder with the desired slug and renaming did not work.', 'module-package-rename-failure' ),
                    array(
                        'found'    => $subdir_name,
                        'expected' => $desired_slug
                    )
                );
            }

            return $source;
        }

        #endregion
    }
