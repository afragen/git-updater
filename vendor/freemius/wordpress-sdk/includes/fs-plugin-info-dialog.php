<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2015, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       1.0.6
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * Class FS_Plugin_Info_Dialog
     *
     * @author Vova Feldman (@svovaf)
     * @since  1.1.7
     */
    class FS_Plugin_Info_Dialog {
        /**
         * @since 1.1.7
         *
         * @var FS_Logger
         */
        private $_logger;

        /**
         * @since 1.1.7
         *
         * @var Freemius
         */
        private $_fs;

        /**
         * Collection of plugin installation, update, download, activation, and purchase actions. This is used in
         * populating the actions dropdown list when there are at least 2 actions. If there's only 1 action, a button
         * is used instead.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @var string[]
         */
        private $actions;

        /**
         * Contains plugin status information that is used to determine which actions should be part of the actions
         * dropdown list.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @var string[]
         */
        private $status;

        function __construct( Freemius $fs ) {
            $this->_fs = $fs;

            $this->_logger = FS_Logger::get_logger( WP_FS__SLUG . '_' . $fs->get_slug() . '_info', WP_FS__DEBUG_SDK, WP_FS__ECHO_DEBUG_SDK );

            // Remove default plugin information action.
            remove_all_actions( 'install_plugins_pre_plugin-information' );

            // Override action with custom plugins function for add-ons.
            add_action( 'install_plugins_pre_plugin-information', array( &$this, 'install_plugin_information' ) );

            // Override request for plugin information for Add-ons.
            add_filter(
                'fs_plugins_api',
                array( &$this, '_get_addon_info_filter' ),
                WP_FS__DEFAULT_PRIORITY, 3 );
        }

        /**
         * Generate add-on plugin information.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         *
         * @param array       $data
         * @param string      $action
         * @param object|null $args
         *
         * @return array|null
         */
        function _get_addon_info_filter( $data, $action = '', $args = null ) {
            $this->_logger->entrance();

            $parent_plugin_id = fs_request_get( 'parent_plugin_id', $this->_fs->get_id() );

            if ( $this->_fs->get_id() != $parent_plugin_id ||
                 ( 'plugin_information' !== $action ) ||
                 ! isset( $args->slug )
            ) {
                return $data;
            }

            // Find add-on by slug.
            $selected_addon = $this->_fs->get_addon_by_slug( $args->slug, WP_FS__DEV_MODE );

            if ( false === $selected_addon ) {
                return $data;
            }

            if ( ! isset( $selected_addon->info ) ) {
                // Setup some default info.
                $selected_addon->info                  = new stdClass();
                $selected_addon->info->selling_point_0 = 'Selling Point 1';
                $selected_addon->info->selling_point_1 = 'Selling Point 2';
                $selected_addon->info->selling_point_2 = 'Selling Point 3';
                $selected_addon->info->description     = '<p>Tell your users all about your add-on</p>';
            }

            fs_enqueue_local_style( 'fs_addons', '/admin/add-ons.css' );

            $data = $args;

            $has_free_plan = false;
            $has_paid_plan = false;

            // Load add-on pricing.
            $has_pricing  = false;
            $has_features = false;
            $plans        = false;

            $result = $this->_fs->get_api_plugin_scope()->get( $this->_fs->add_show_pending( "/addons/{$selected_addon->id}/pricing.json?type=visible" ) );

            if ( ! isset( $result->error ) ) {
                $plans = $result->plans;

                if ( is_array( $plans ) ) {
                    for ( $i = 0, $len = count( $plans ); $i < $len; $i ++ ) {
                        $pricing  = isset( $plans[ $i ]->pricing ) ? $plans[ $i ]->pricing : null;
                        $features = isset( $plans[ $i ]->features ) ? $plans[ $i ]->features : null;

                        $plans[ $i ] = new FS_Plugin_Plan( $plans[ $i ] );
                        $plan        = $plans[ $i ];

                        if ( 'free' == $plans[ $i ]->name ||
                             ! is_array( $pricing ) ||
                             0 == count( $pricing )
                        ) {
                            $has_free_plan = true;
                        }

                        if ( is_array( $pricing ) && 0 < count( $pricing ) ) {
                            $filtered_pricing = array();

                            foreach ( $pricing as $prices ) {
                                $prices = new FS_Pricing( $prices );

                                if ( ! $prices->is_usd() ) {
                                    /**
                                     * Skip non-USD pricing.
                                     *
                                     * @author Leo Fajardo (@leorw)
                                     * @since 2.3.1
                                     */
                                    continue;
                                }

                                if ( ( $prices->has_monthly() && $prices->monthly_price > 1.0 ) ||
                                     ( $prices->has_annual() && $prices->annual_price > 1.0 ) ||
                                     ( $prices->has_lifetime() && $prices->lifetime_price > 1.0 )
                                ) {
                                    $filtered_pricing[] = $prices;
                                }
                            }

                            if ( ! empty( $filtered_pricing ) ) {
                                $has_paid_plan = true;

                                $plan->pricing = $filtered_pricing;

                                $has_pricing = true;
                            }
                        }

                        if ( is_array( $features ) && 0 < count( $features ) ) {
                            $plan->features = $features;

                            $has_features = true;
                        }
                    }
                }
            }

            $latest = null;

            if ( ! $has_paid_plan && $selected_addon->is_wp_org_compliant ) {
                $repo_data = FS_Plugin_Updater::_fetch_plugin_info_from_repository(
                    'plugin_information', (object) array(
                    'slug'   => $selected_addon->slug,
                    'is_ssl' => is_ssl(),
                    'fields' => array(
                        'banners'         => true,
                        'reviews'         => true,
                        'downloaded'      => false,
                        'active_installs' => true
                    )
                ) );

                if ( ! empty( $repo_data ) ) {
                    $data                 = $repo_data;
                    $data->wp_org_missing = false;
                } else {
                    // Couldn't find plugin on .org.
                    $selected_addon->is_wp_org_compliant = false;

                    // Plugin is missing, not on Freemius nor WP.org.
                    $data->wp_org_missing = true;
                }

                $data->fs_missing = ( ! $has_free_plan || $data->wp_org_missing );
            } else {
                $data->has_purchased_license = false;
                $data->wp_org_missing        = false;

                $fs_addon              = null;
                $current_addon_version = false;
                if ( $this->_fs->is_addon_activated( $selected_addon->id ) ) {
                    $fs_addon              = $this->_fs->get_addon_instance( $selected_addon->id );
                    $current_addon_version = $fs_addon->get_plugin_version();
                } else if ( $this->_fs->is_addon_installed( $selected_addon->id ) ) {
                    $addon_plugin_data = get_plugin_data(
                        ( WP_PLUGIN_DIR . '/' . $this->_fs->get_addon_basename( $selected_addon->id ) ),
                        false,
                        false
                    );

                    if ( ! empty( $addon_plugin_data ) ) {
                        $current_addon_version = $addon_plugin_data['Version'];
                    }
                }

                // Fetch latest version from Freemius.
                $latest = $this->_fs->_fetch_latest_version(
                    $selected_addon->id,
                    true,
                    WP_FS__TIME_24_HOURS_IN_SEC,
                    $current_addon_version
                );

                if ( $has_paid_plan ) {
                    $blog_id           = fs_request_get( 'fs_blog_id' );
                    $has_valid_blog_id = is_numeric( $blog_id );

                    if ( $has_valid_blog_id ) {
                        switch_to_blog( $blog_id );
                    }

                    $data->checkout_link = $this->_fs->checkout_url(
                        WP_FS__PERIOD_ANNUALLY,
                        false,
                        array(),
                        ( $has_valid_blog_id ? false : null )
                    );

                    if ( $has_valid_blog_id ) {
                        restore_current_blog();
                    }
                }

                /**
                 * Check if there's a purchased license in case the add-on can only be installed/downloaded as part of a purchased bundle.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.4.1
                 */
                if ( is_object( $fs_addon ) ) {
                    $data->has_purchased_license = $fs_addon->has_active_valid_license();
                } else {
                    $account_addons = $this->_fs->get_account_addons();
                    if ( ! empty( $account_addons ) && in_array( $selected_addon->id, $account_addons ) ) {
                        $data->has_purchased_license = true;
                    }
                }

                if ( $has_free_plan || $data->has_purchased_license ) {
                    $data->download_link = $this->_fs->_get_latest_download_local_url( $selected_addon->id );
                }

                $data->fs_missing = (
                    false === $latest &&
                    (
                        empty( $selected_addon->premium_releases_count ) ||
                        ! ( $selected_addon->premium_releases_count > 0 )
                    )
                );

                // Fetch as much as possible info from local files.
                $plugin_local_data = $this->_fs->get_plugin_data();
                $data->author      = $plugin_local_data['Author'];

                if ( ! empty( $selected_addon->info->banner_url ) ) {
                    $data->banners = array(
                        'low' => $selected_addon->info->banner_url,
                    );
                }

                if ( ! empty( $selected_addon->info->screenshots ) ) {
                    $view_vars                     = array(
                        'screenshots' => $selected_addon->info->screenshots,
                        'plugin'      => $selected_addon,
                    );
                    $data->sections['screenshots'] = fs_get_template( '/plugin-info/screenshots.php', $view_vars );
                }

                if ( is_object( $latest ) ) {
                    $data->version      = $latest->version;
                    $data->last_updated = $latest->created;
                    $data->requires     = $latest->requires_platform_version;
                    $data->requires_php = $latest->requires_programming_language_version;
                    $data->tested       = $latest->tested_up_to_version;
                } else if ( ! empty( $current_addon_version ) ) {
                    $data->version = $current_addon_version;
                } else {
                    // Add dummy version.
                    $data->version = '1.0.0';

                    // Add message to developer to deploy the plugin through Freemius.
                }
            }

            $data->name = $selected_addon->title;
            $view_vars  = array( 'plugin' => $selected_addon );

            if ( is_object( $latest ) && isset( $latest->readme ) && is_object( $latest->readme ) ) {
                $latest_version_readme_data = $latest->readme;
                if ( isset( $latest_version_readme_data->sections ) ) {
                    $data->sections = (array) $latest_version_readme_data->sections;
                } else {
                    $data->sections = array();
                }
            }

            $data->sections['description'] = fs_get_template( '/plugin-info/description.php', $view_vars );

            if ( $has_pricing ) {
                // Add plans to data.
                $data->plans = $plans;

                if ( $has_features ) {
                    $view_vars                  = array(
                        'plans'  => $plans,
                        'plugin' => $selected_addon,
                    );
                    $data->sections['features'] = fs_get_template( '/plugin-info/features.php', $view_vars );
                }
            }

            $data->has_free_plan       = $has_free_plan;
            $data->has_paid_plan       = $has_paid_plan;
            $data->is_paid             = $has_paid_plan;
            $data->is_wp_org_compliant = $selected_addon->is_wp_org_compliant;
            $data->premium_slug        = $selected_addon->premium_slug;
            $data->addon_id            = $selected_addon->id;

            if ( ! isset( $data->has_purchased_license ) ) {
                $data->has_purchased_license = false;
            }

            return $data;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7
         *
         * @param FS_Plugin_Plan $plan
         *
         * @return string
         */
        private function get_billing_cycle( FS_Plugin_Plan $plan ) {
            $billing_cycle = null;

            if ( 1 === count( $plan->pricing ) && 1 == $plan->pricing[0]->licenses ) {
                $pricing = $plan->pricing[0];
                if ( isset( $pricing->annual_price ) ) {
                    $billing_cycle = 'annual';
                } else if ( isset( $pricing->monthly_price ) ) {
                    $billing_cycle = 'monthly';
                } else if ( isset( $pricing->lifetime_price ) ) {
                    $billing_cycle = 'lifetime';
                }
            } else {
                foreach ( $plan->pricing as $pricing ) {
                    if ( isset( $pricing->annual_price ) ) {
                        $billing_cycle = 'annual';
                    } else if ( isset( $pricing->monthly_price ) ) {
                        $billing_cycle = 'monthly';
                    } else if ( isset( $pricing->lifetime_price ) ) {
                        $billing_cycle = 'lifetime';
                    }

                    if ( ! is_null( $billing_cycle ) ) {
                        break;
                    }
                }
            }

            return $billing_cycle;
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param FS_Plugin_Plan $plan
         * @param FS_Pricing     $pricing
         *
         * @return float|null|string
         */
        private function get_price_tag( FS_Plugin_Plan $plan, FS_Pricing $pricing ) {
            $price_tag = '';
            if ( isset( $pricing->annual_price ) ) {
                $price_tag = $pricing->annual_price . ( $plan->is_block_features ? ' / year' : '' );
            } else if ( isset( $pricing->monthly_price ) ) {
                $price_tag = $pricing->monthly_price . ' / mo';
            } else if ( isset( $pricing->lifetime_price ) ) {
                $price_tag = $pricing->lifetime_price;
            }

            return '$' . $price_tag;
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.3.0
         *
         * @param object         $api
         * @param FS_Plugin_Plan $plan
         *
         * @return string
         */
        private function get_actions_dropdown( $api, $plan = null ) {
            $this->actions = isset( $this->actions ) ?
                $this->actions :
                $this->get_plugin_actions( $api );

            $actions = $this->actions;

            $checkout_cta = $this->get_checkout_cta( $api, $plan );
            if ( ! empty( $checkout_cta ) ) {
                /**
                 * If there's no license yet, make the checkout button the main CTA. Otherwise, make it the last item in
                 * the actions dropdown.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.3.0
                 */
                if ( ! $api->has_purchased_license ) {
                    array_unshift( $actions, $checkout_cta );
                } else {
                    $actions[] = $checkout_cta;
                }
            }

            if ( empty( $actions ) ) {
                return '';
            }

            $total_actions = count( $actions );
            if ( 1 === $total_actions ) {
                return $actions[0];
            }

            ob_start();

            ?>
            <div class="fs-cta fs-dropdown">
                <div class="button-group">
                    <?php
                        // This should NOT be sanitized as the $actions are HTML buttons already.
                        echo $actions[0] ?>
                    <div class="button button-primary fs-dropdown-arrow-button">
                        <span class="fs-dropdown-arrow"></span>
                        <ul class="fs-dropdown-list" style="display: none">
                            <?php for ( $i = 1; $i < $total_actions; $i ++ ) : ?>
                                <li><?php echo str_replace( 'button button-primary', '', $actions[ $i ] ) ?></li>
                            <?php endfor ?>
                        </ul>
                    </div>
                </div>
            </div>
            <?php

            return ob_get_clean();
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7
         *
         * @param object         $api
         * @param FS_Plugin_Plan $plan
         *
         * @return string
         */
        private function get_checkout_cta( $api, $plan = null ) {
            if ( empty( $api->checkout_link ) ||
                 ! isset( $api->plans ) ||
                 ! is_array( $api->plans ) ||
                 0 == count( $api->plans )
            ) {
                return '';
            }

            if ( is_null( $plan ) ) {
                foreach ( $api->plans as $p ) {
                    if ( ! empty( $p->pricing ) ) {
                        $plan = $p;
                        break;
                    }
                }
            }

            $blog_id           = fs_request_get( 'fs_blog_id' );
            $has_valid_blog_id = is_numeric( $blog_id );

            if ( $has_valid_blog_id ) {
                switch_to_blog( $blog_id );
            }

            $addon_checkout_url = $this->_fs->addon_checkout_url(
                $plan->plugin_id,
                $plan->pricing[0]->id,
                $this->get_billing_cycle( $plan ),
                $plan->has_trial(),
                ( $has_valid_blog_id ? false : null )
            );

            if ( $has_valid_blog_id ) {
                restore_current_blog();
            }

            return '<a class="button button-primary fs-checkout-button right" href="' . $addon_checkout_url . '" target="_parent">' .
                   esc_html( ! $plan->has_trial() ?
                       (
                           $api->has_purchased_license ?
                               fs_text_inline( 'Purchase More', 'purchase-more', $api->slug ) :
                               fs_text_x_inline( 'Purchase', 'verb', 'purchase', $api->slug )
                       ) :
                       sprintf(
                       /* translators: %s: N-days trial */
                           fs_text_inline( 'Start my free %s', 'start-free-x', $api->slug ),
                           $this->get_trial_period( $plan )
                       )
                   ) .
                   '</a>';
        }

        /**
         * @author Leo Fajardo (@leorw)
         * @since  2.3.0
         *
         * @param object $api
         *
         * @return string[]
         */
        private function get_plugin_actions( $api ) {
            $this->status = isset( $this->status ) ?
                $this->status :
                install_plugin_install_status( $api );

            $is_update_available = ( 'update_available' === $this->status['status'] );

            if ( $is_update_available && empty( $this->status['url'] ) ) {
                return array();
            }

            $blog_id = fs_request_get( 'fs_blog_id' );

            $active_plugins_directories_map = Freemius::get_active_plugins_directories_map( $blog_id );

            $actions = array();

            $is_addon_activated = $this->_fs->is_addon_activated( $api->slug );
            $fs_addon           = null;

            $is_free_installed    = null;
            $is_premium_installed = null;

            $has_installed_version = ( 'install' !== $this->status['status'] );

            if ( ! $api->has_paid_plan && ! $api->has_purchased_license ) {
                /**
                 * Free-only add-on.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.3.0
                 */
                $is_free_installed    = $has_installed_version;
                $is_premium_installed = false;
            } else if ( ! $api->has_free_plan ) {
                /**
                 * Premium-only add-on.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.3.0
                 */
                $is_free_installed    = false;
                $is_premium_installed = $has_installed_version;
            } else {
                /**
                 * Freemium add-on.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.3.0
                 */
                if ( ! $has_installed_version ) {
                    $is_free_installed    = false;
                    $is_premium_installed = false;
                } else {
                    $fs_addon = $is_addon_activated ?
                        $this->_fs->get_addon_instance( $api->slug ) :
                        null;

                    if ( is_object( $fs_addon ) ) {
                        if ( $fs_addon->is_premium() ) {
                            $is_premium_installed = true;
                        } else {
                            $is_free_installed = true;
                        }
                    }

                    if ( is_null( $is_free_installed ) ) {
                        $is_free_installed = file_exists( fs_normalize_path( WP_PLUGIN_DIR . "/{$api->slug}/{$api->slug}.php" ) );
                        if ( ! $is_free_installed ) {
                            /**
                             * Check if there's a plugin installed in a directory named `$api->slug`.
                             *
                             * @author Leo Fajardo (@leorw)
                             * @since 2.3.0
                             */
                            $installed_plugins = get_plugins( '/' . $api->slug );
                            $is_free_installed = ( ! empty( $installed_plugins ) );
                        }
                    }

                    if ( is_null( $is_premium_installed ) ) {
                        $is_premium_installed = file_exists( fs_normalize_path( WP_PLUGIN_DIR . "/{$api->premium_slug}/{$api->slug}.php" ) );
                        if ( ! $is_premium_installed ) {
                            /**
                             * Check if there's a plugin installed in a directory named `$api->premium_slug`.
                             *
                             * @author Leo Fajardo (@leorw)
                             * @since 2.3.0
                             */
                            $installed_plugins    = get_plugins( '/' . $api->premium_slug );
                            $is_premium_installed = ( ! empty( $installed_plugins ) );
                        }
                    }
                }

                $has_installed_version = ( $is_free_installed || $is_premium_installed );
            }

            $this->status['is_free_installed']    = $is_free_installed;
            $this->status['is_premium_installed'] = $is_premium_installed;

            $can_install_free_version           = false;
            $can_install_free_version_update    = false;
            $can_download_free_version          = false;
            $can_activate_free_version          = false;
            $can_install_premium_version        = false;
            $can_install_premium_version_update = false;
            $can_download_premium_version       = false;
            $can_activate_premium_version       = false;

            if ( ! $api->has_purchased_license ) {
                if ( $api->has_free_plan ) {
                    if ( $has_installed_version ) {
                        if ( $is_update_available ) {
                            $can_install_free_version_update = true;
                        } else if ( ! $is_premium_installed && ! isset( $active_plugins_directories_map[ dirname( $this->status['file'] ) ] ) ) {
                            $can_activate_free_version = true;
                        }
                    } else {
                        if (
                            $this->_fs->is_premium() ||
                            ! $this->_fs->is_org_repo_compliant() ||
                            $api->is_wp_org_compliant
                        ) {
                            $can_install_free_version  = true;
                        } else {
                            $can_download_free_version = true;
                        }
                    }
                }
            } else {
                if ( ! is_object( $fs_addon ) && $is_addon_activated ) {
                    $fs_addon = $this->_fs->get_addon_instance( $api->slug );
                }

                $can_download_premium_version = true;

                if ( ! isset( $active_plugins_directories_map[ dirname( $this->status['file'] ) ] ) ) {
                    if ( $is_premium_installed ) {
                        $can_activate_premium_version = ( ! $is_addon_activated || ! $fs_addon->is_premium() );
                    } else if ( $is_free_installed ) {
                        $can_activate_free_version = ( ! $is_addon_activated );
                    }
                }

                if ( $this->_fs->is_premium() || ! $this->_fs->is_org_repo_compliant() ) {
                    if ( $is_update_available ) {
                        $can_install_premium_version_update = true;
                    } else if ( ! $is_premium_installed ) {
                        $can_install_premium_version = true;
                    }
                }
            }

            if (
                $can_install_premium_version ||
                $can_install_premium_version_update
            ) {
                if ( is_numeric( $blog_id ) ) {
                    /**
                     * Replace the network status URL with a blog admin–based status URL if the `Add-Ons` page is loaded
                     * from a specific blog admin page (when `fs_blog_id` is valid) in order for plugin installation/update
                     * to work.
                     *
                     * @author Leo Fajardo (@leorw)
                     * @since 2.3.0
                     */
                    $this->status['url'] = self::get_blog_status_url( $blog_id, $this->status['url'], $this->status['status'] );
                }

                /**
                 * Add the `fs_allow_updater_and_dialog` param to the install/update URL so that the add-on can be
                 * installed/updated.
                 *
                 * @author Leo Fajardo (@leorw)
                 * @since 2.3.0
                 */
                $this->status['url'] = str_replace( '?', '?fs_allow_updater_and_dialog=true&amp;', $this->status['url'] );
            }

            if ( $can_install_free_version_update || $can_install_premium_version_update ) {
                $actions[] = $this->get_cta(
                    ( $can_install_free_version_update ?
                        fs_esc_html_inline( 'Install Free Version Update Now', 'install-free-version-update-now', $api->slug ) :
                        fs_esc_html_inline( 'Install Update Now', 'install-update-now', $api->slug ) ),
                    true,
                    false,
                    $this->status['url'],
                    '_parent'
                );
            } else if ( $can_install_free_version || $can_install_premium_version ) {
                $actions[] = $this->get_cta(
                    ( $can_install_free_version ?
                        fs_esc_html_inline( 'Install Free Version Now', 'install-free-version-now', $api->slug ) :
                        fs_esc_html_inline( 'Install Now', 'install-now', $api->slug ) ),
                    true,
                    false,
                    $this->status['url'],
                    '_parent'
                );
            }

            $download_latest_action = '';

            if (
                ! empty( $api->download_link ) &&
                ( $can_download_free_version || $can_download_premium_version )
            ) {
                $download_latest_action = $this->get_cta(
                    ( $can_download_free_version ?
                        fs_esc_html_x_inline( 'Download Latest Free Version', 'as download latest version', 'download-latest-free-version', $api->slug ) :
                        fs_esc_html_x_inline( 'Download Latest', 'as download latest version', 'download-latest', $api->slug ) ),
                    true,
                    false,
                    esc_url( $api->download_link )
                );
            }

            if ( ! $can_activate_free_version && ! $can_activate_premium_version ) {
                if ( ! empty( $download_latest_action ) ) {
                    $actions[] = $download_latest_action;
                }
            } else {
                $activate_action = sprintf(
                    '<a class="button button-primary edit" href="%s" title="%s" target="_parent">%s</a>',
                    wp_nonce_url( ( is_numeric( $blog_id ) ? trailingslashit( get_admin_url( $blog_id ) ) : '' ) . 'plugins.php?action=activate&amp;plugin=' . $this->status['file'], 'activate-plugin_' . $this->status['file'] ),
                    fs_esc_attr_inline( 'Activate this add-on', 'activate-this-addon', $api->slug ),
                    $can_activate_free_version ?
                        fs_text_inline( 'Activate Free Version', 'activate-free', $api->slug ) :
                        fs_text_inline( 'Activate', 'activate', $api->slug )
                );

                if ( ! $can_download_premium_version && ! empty( $download_latest_action ) ) {
                    $actions[] = $download_latest_action;

                    $download_latest_action = '';
                }

                if ( $can_install_premium_version || $can_install_premium_version_update ) {
                    if ( $can_download_premium_version && ! empty( $download_latest_action ) ) {
                        $actions[] = $download_latest_action;

                        $download_latest_action = '';
                    }

                    $actions[] = $activate_action;
                } else {
                    array_unshift( $actions, $activate_action );
                }

                if ( ! empty ($download_latest_action ) ) {
                    $actions[] = $download_latest_action;
                }
            }

            return $actions;
        }

        /**
         * Rebuilds the status URL based on the admin URL.
         *
         * @author Leo Fajardo (@leorw)
         * @since 2.3.0
         *
         * @param int    $blog_id
         * @param string $network_status_url
         * @param string $status
         *
         * @return string
         */
        private static function get_blog_status_url( $blog_id, $network_status_url, $status ) {
            if ( ! in_array( $status, array( 'install', 'update_available' ) ) ) {
                return $network_status_url;
            }

            $action = ( 'install' === $status ) ?
                'install-plugin' :
                'upgrade-plugin';

            $url_params = fs_parse_url_params( $network_status_url, true );

            if ( empty( $url_params ) || ! isset( $url_params['plugin'] ) ) {
                return $network_status_url;
            }

            $plugin = $url_params['plugin'];

            return wp_nonce_url( get_admin_url( $blog_id,"update.php?action={$action}&plugin={$plugin}"), "{$action}_{$plugin}");
        }

        /**
         * Helper method to get a CTA button HTML.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         *
         * @param string $label
         * @param bool   $is_primary
         * @param bool   $is_disabled
         * @param string $href
         * @param string $target
         *
         * @return string
         */
        private function get_cta(
            $label,
            $is_primary = true,
            $is_disabled = false,
            $href = '',
            $target = '_blank'
        ) {
            $classes = array();

            if ( ! $is_primary ) {
                $classes[] = 'left';
            } else {
                $classes[] = 'button-primary';
                $classes[] = 'right';
            }

            if ( $is_disabled ) {
                $classes[] = 'disabled';
            }

            $rel = ( '_blank' === $target ) ? ' rel="noopener noreferrer"' : '';

            return sprintf(
                '<a %s class="button %s">%s</a>',
                empty( $href ) ? '' : 'href="' . $href . '" target="' . $target . '"' . $rel,
                implode( ' ', $classes ),
                $label
            );
        }

        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7
         *
         * @param FS_Plugin_Plan $plan
         *
         * @return string
         */
        private function get_trial_period( $plan ) {
            $trial_period = (int) $plan->trial_period;

            switch ( $trial_period ) {
                case 30:
                    return 'month';
                case 60:
                    return '2 months';
                default:
                    return "{$plan->trial_period} days";
            }
        }

        /**
         * Display plugin information in dialog box form.
         *
         * Based on core install_plugin_information() function.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.0.6
         */
        function install_plugin_information() {
            global $tab;

            if ( empty( $_REQUEST['plugin'] ) ) {
                return;
            }

            $args = array(
                'slug'   => wp_unslash( $_REQUEST['plugin'] ),
                'is_ssl' => is_ssl(),
                'fields' => array(
                    'banners'         => true,
                    'reviews'         => true,
                    'downloaded'      => false,
                    'active_installs' => true
                )
            );

            if ( is_array( $args ) ) {
                $args = (object) $args;
            }

            if ( ! isset( $args->per_page ) ) {
                $args->per_page = 24;
            }

            if ( ! isset( $args->locale ) ) {
                $args->locale = get_locale();
            }

            $api = apply_filters( 'fs_plugins_api', false, 'plugin_information', $args );

            if ( is_wp_error( $api ) ) {
                wp_die( $api );
            }

            $plugins_allowedtags = array(
                'a'       => array(
                    'href'   => array(),
                    'title'  => array(),
                    'target' => array(),
                    // Add image style for screenshots.
                    'class'  => array()
                ),
                'style'   => array(),
                'abbr'    => array( 'title' => array() ),
                'acronym' => array( 'title' => array() ),
                'code'    => array(),
                'pre'     => array(),
                'em'      => array(),
                'strong'  => array(),
                'div'     => array( 'class' => array() ),
                'span'    => array( 'class' => array() ),
                'p'       => array(),
                'ul'      => array(),
                'ol'      => array(),
                'li'      => array( 'class' => array() ),
                'i'       => array( 'class' => array() ),
                'h1'      => array(),
                'h2'      => array(),
                'h3'      => array(),
                'h4'      => array(),
                'h5'      => array(),
                'h6'      => array(),
                'img'     => array( 'src' => array(), 'class' => array(), 'alt' => array() ),
//			'table' => array(),
//			'td' => array(),
//			'tr' => array(),
//			'th' => array(),
//			'thead' => array(),
//			'tbody' => array(),
            );

            $plugins_section_titles = array(
                'description'  => fs_text_x_inline( 'Description', 'Plugin installer section title', 'description', $api->slug ),
                'installation' => fs_text_x_inline( 'Installation', 'Plugin installer section title', 'installation', $api->slug ),
                'faq'          => fs_text_x_inline( 'FAQ', 'Plugin installer section title', 'faq', $api->slug ),
                'screenshots'  => fs_text_inline( 'Screenshots', 'screenshots', $api->slug ),
                'changelog'    => fs_text_x_inline( 'Changelog', 'Plugin installer section title', 'changelog', $api->slug ),
                'reviews'      => fs_text_x_inline( 'Reviews', 'Plugin installer section title', 'reviews', $api->slug ),
                'other_notes'  => fs_text_x_inline( 'Other Notes', 'Plugin installer section title', 'other-notes', $api->slug ),
            );

            // Sanitize HTML
//		foreach ( (array) $api->sections as $section_name => $content ) {
//			$api->sections[$section_name] = wp_kses( $content, $plugins_allowedtags );
//		}

            foreach ( array( 'version', 'author', 'requires', 'tested', 'homepage', 'downloaded', 'slug' ) as $key ) {
                if ( isset( $api->$key ) ) {
                    $api->$key = wp_kses( $api->$key, $plugins_allowedtags );
                }
            }

            // Add after $api->slug is ready.
            $plugins_section_titles['features'] = fs_text_x_inline( 'Features & Pricing', 'Plugin installer section title', 'features-and-pricing', $api->slug );

            $_tab = esc_attr( $tab );

            $section = isset( $_REQUEST['section'] ) ? wp_unslash( $_REQUEST['section'] ) : 'description'; // Default to the Description tab, Do not translate, API returns English.
            if ( empty( $section ) || ! isset( $api->sections[ $section ] ) ) {
                $section_titles = array_keys( (array) $api->sections );
                $section        = array_shift( $section_titles );
            }

            iframe_header( fs_text_inline( 'Plugin Install', 'plugin-install', $api->slug ) );

            $_with_banner = '';

//	var_dump($api->banners);
            if ( ! empty( $api->banners ) && ( ! empty( $api->banners['low'] ) || ! empty( $api->banners['high'] ) ) ) {
                $_with_banner = 'with-banner';
                $low          = empty( $api->banners['low'] ) ? $api->banners['high'] : $api->banners['low'];
                $high         = empty( $api->banners['high'] ) ? $api->banners['low'] : $api->banners['high'];
                ?>
                <style type="text/css">
                    #plugin-information-title.with-banner
                    {
                        background-image: url( <?php echo esc_url( $low ); ?> );
                    }

                    @media only screen and ( -webkit-min-device-pixel-ratio: 1.5 )
                    {
                        #plugin-information-title.with-banner
                        {
                            background-image: url( <?php echo esc_url( $high ); ?> );
                        }
                    }
                </style>
                <?php
            }

            echo '<div id="plugin-information-scrollable">';
            echo "<div id='{$_tab}-title' class='{$_with_banner}'><div class='vignette'></div><h2>{$api->name}</h2></div>";
            echo "<div id='{$_tab}-tabs' class='{$_with_banner}'>\n";

            foreach ( (array) $api->sections as $section_name => $content ) {
                if ( 'reviews' === $section_name && ( empty( $api->ratings ) || 0 === array_sum( (array) $api->ratings ) ) ) {
                    continue;
                }

                if ( isset( $plugins_section_titles[ $section_name ] ) ) {
                    $title = $plugins_section_titles[ $section_name ];
                } else {
                    $title = ucwords( str_replace( '_', ' ', $section_name ) );
                }

                $class       = ( $section_name === $section ) ? ' class="current"' : '';
                $href        = add_query_arg( array( 'tab' => $tab, 'section' => $section_name ) );
                $href        = esc_url( $href );
                $san_section = esc_attr( $section_name );
                echo "\t<a name='$san_section' href='$href' $class>" . esc_html( $title ) . "</a>\n";
            }

            echo "</div>\n";

            ?>
        <div id="<?php echo $_tab; ?>-content" class='<?php echo $_with_banner; ?>'>
            <div class="fyi">
                <?php if ( $api->is_paid ) : ?>
                    <?php if ( isset( $api->plans ) ) : ?>
                        <div class="plugin-information-pricing">
                        <?php foreach ( $api->plans as $plan ) : ?>
                            <?php
                            if ( empty( $plan->pricing ) ) {
                                continue;
                            }

                            /**
                             * @var FS_Plugin_Plan $plan
                             */
                            ?>
                            <?php $first_pricing = $plan->pricing[0] ?>
                            <?php $is_multi_cycle = $first_pricing->is_multi_cycle() ?>
                            <div class="fs-plan<?php if ( ! $is_multi_cycle ) {
                                echo ' fs-single-cycle';
                            } ?>" data-plan-id="<?php echo $plan->id ?>">
                                <h3 data-plan="<?php echo $plan->id ?>"><?php echo esc_html( sprintf( fs_text_x_inline( '%s Plan', 'e.g. Professional Plan', 'x-plan', $api->slug ), $plan->title ) ) ?></h3>
                                <?php $has_annual = $first_pricing->has_annual() ?>
                                <?php $has_monthly = $first_pricing->has_monthly() ?>
                                <div class="nav-tab-wrapper">
                                    <?php $billing_cycles = array( 'monthly', 'annual', 'lifetime' ) ?>
                                    <?php $i = 0;
                                        foreach ( $billing_cycles as $cycle ) : ?>
                                            <?php $prop = "{$cycle}_price";
                                            if ( isset( $first_pricing->{$prop} ) ) : ?>
                                                <?php $is_featured = ( 'annual' === $cycle && $is_multi_cycle ) ?>
                                                <?php
                                                $prices = array();
                                                foreach ( $plan->pricing as $pricing ) {
                                                    if ( isset( $pricing->{$prop} ) ) {
                                                        $prices[] = array(
                                                            'id'       => $pricing->id,
                                                            'licenses' => $pricing->licenses,
                                                            'price'    => $pricing->{$prop}
                                                        );
                                                    }
                                                }
                                                ?>
                                                <a class="nav-tab" data-billing-cycle="<?php echo $cycle ?>"
                                                   data-pricing="<?php echo esc_attr( json_encode( $prices ) ) ?>">
                                                    <?php if ( $is_featured ) : ?>
                                                        <label>
                                                            &#9733; <?php fs_esc_html_echo_x_inline( 'Best', 'e.g. the best product', 'best', $api->slug ) ?>
                                                            &#9733;</label>
                                                    <?php endif ?>
                                                    <?php
                                                        switch ( $cycle ) {
                                                            case 'monthly':
                                                                fs_esc_html_echo_x_inline( 'Monthly', 'as every month', 'monthly', $api->slug );
                                                                break;
                                                            case 'annual':
                                                                fs_esc_html_echo_x_inline( 'Annual', 'as once a year', 'annual', $api->slug );
                                                                break;
                                                            case 'lifetime':
                                                                fs_esc_html_echo_inline( 'Lifetime', 'lifetime', $api->slug );
                                                                break;
                                                        }
                                                    ?>
                                                </a>
                                            <?php endif ?>
                                            <?php $i ++; endforeach ?>
                                    <?php wp_enqueue_script( 'jquery' ) ?>
                                    <script type="text/javascript">
                                        (function ($, undef) {
                                            var
                                                _formatBillingFrequency = function (cycle) {
                                                    switch (cycle) {
                                                        case 'monthly':
                                                            return '<?php printf( fs_text_x_inline( 'Billed %s', 'e.g. billed monthly', 'billed-x', $api->slug ), fs_text_x_inline( 'Monthly', 'as every month', 'monthly', $api->slug ) ) ?>';
                                                        case 'annual':
                                                            return '<?php printf( fs_text_x_inline( 'Billed %s', 'e.g. billed monthly', 'billed-x', $api->slug ), fs_text_x_inline( 'Annually', 'as once a year', 'annually', $api->slug ) ) ?>';
                                                        case 'lifetime':
                                                            return '<?php printf( fs_text_x_inline( 'Billed %s', 'e.g. billed monthly', 'billed-x', $api->slug ), fs_text_x_inline( 'Once', 'as once a year', 'once', $api->slug ) ) ?>';
                                                    }
                                                },
                                                _formatLicensesTitle    = function (pricing) {
                                                    switch (pricing.licenses) {
                                                        case 1:
                                                            return '<?php fs_esc_attr_echo_inline( 'Single Site License', 'license-single-site', $api->slug ) ?>';
                                                        case null:
                                                            return '<?php fs_esc_attr_echo_inline( 'Unlimited Licenses', 'license-unlimited', $api->slug ) ?>';
                                                        default:
                                                            return '<?php fs_esc_attr_echo_inline( 'Up to %s Sites', 'license-x-sites', $api->slug ) ?>'.replace('%s', pricing.licenses);
                                                    }
                                                },
                                                _formatPrice            = function (pricing, cycle, multipleLicenses) {
                                                    if (undef === multipleLicenses)
                                                        multipleLicenses = true;

                                                    var priceCycle;
                                                    switch (cycle) {
                                                        case 'monthly':
                                                            priceCycle = ' / <?php fs_echo_x_inline( 'mo', 'as monthly period', 'mo', $api->slug ) ?>';
                                                            break;
                                                        case 'lifetime':
                                                            priceCycle = '';
                                                            break;
                                                        case 'annual':
                                                        default:
                                                            priceCycle = ' / <?php fs_echo_x_inline( 'year', 'as annual period', 'year', $api->slug ) ?>';
                                                            break;
                                                    }

                                                    if (!multipleLicenses && 1 == pricing.licenses) {
                                                        return '$' + pricing.price + priceCycle;
                                                    }

                                                    return _formatLicensesTitle(pricing) + ' - <var class="fs-price">$' + pricing.price + priceCycle + '</var>';
                                                },
                                                _checkoutUrl            = function (plan, pricing, cycle) {
                                                    return '<?php echo esc_url_raw( remove_query_arg( 'billing_cycle', add_query_arg( array( 'plugin_id' => $plan->plugin_id ), $api->checkout_link ) ) ) ?>' +
                                                        '&plan_id=' + plan +
                                                        '&pricing_id=' + pricing +
                                                        '&billing_cycle=' + cycle<?php if ( $plan->has_trial() ) {
                                                        echo " + '&trial=true'";
                                                    }?>;
                                                },
                                                _updateCtaUrl           = function (plan, pricing, cycle) {
                                                    $('.plugin-information-pricing .fs-checkout-button, #plugin-information-footer .fs-checkout-button').attr('href', _checkoutUrl(plan, pricing, cycle));
                                                };

                                            $(document).ready(function () {
                                                var $plan = $('.plugin-information-pricing .fs-plan[data-plan-id=<?php echo $plan->id ?>]');
                                                $plan.find('input[type=radio]').on('click', function () {
                                                    _updateCtaUrl(
                                                        $plan.attr('data-plan-id'),
                                                        $(this).val(),
                                                        $plan.find('.nav-tab-active').attr('data-billing-cycle')
                                                    );

                                                    $plan.find('.fs-trial-terms .fs-price').html(
                                                        $(this).parents('label').find('.fs-price').html()
                                                    );
                                                });

                                                $plan.find('.nav-tab').click(function () {
                                                    if ($(this).hasClass('nav-tab-active'))
                                                        return;

                                                    var $this        = $(this),
                                                        billingCycle = $this.attr('data-billing-cycle'),
                                                        pricing      = JSON.parse($this.attr('data-pricing')),
                                                        $pricesList  = $this.parents('.fs-plan').find('.fs-pricing-body .fs-licenses'),
                                                        html         = '';

                                                    // Un-select previously selected tab.
                                                    $plan.find('.nav-tab').removeClass('nav-tab-active');

                                                    // Select current tab.
                                                    $this.addClass('nav-tab-active');

                                                    // Render licenses prices.
                                                    if (1 == pricing.length) {
                                                        html = '<li><label><?php echo fs_esc_attr_x_inline( 'Price', 'noun', 'price', $api->slug ) ?>: ' + _formatPrice(pricing[0], billingCycle, false) + '</label></li>';
                                                    } else {
                                                        for (var i = 0; i < pricing.length; i++) {
                                                            html += '<li><label><input name="pricing-<?php echo $plan->id ?>" type="radio" value="' + pricing[i].id + '">' + _formatPrice(pricing[i], billingCycle) + '</label></li>';
                                                        }
                                                    }
                                                    $pricesList.html(html);

                                                    if (1 < pricing.length) {
                                                        // Select first license option.
                                                        $pricesList.find('li:first input').click();
                                                    }
                                                    else {
                                                        _updateCtaUrl(
                                                            $plan.attr('data-plan-id'),
                                                            pricing[0].id,
                                                            billingCycle
                                                        );
                                                    }

                                                    // Update billing frequency.
                                                    $plan.find('.fs-billing-frequency').html(_formatBillingFrequency(billingCycle));

                                                    if ('annual' === billingCycle) {
                                                        $plan.find('.fs-annual-discount').show();
                                                    } else {
                                                        $plan.find('.fs-annual-discount').hide();
                                                    }
                                                });

                                                <?php if ( $has_annual ) : ?>
                                                // Select annual by default.
                                                $plan.find('.nav-tab[data-billing-cycle=annual]').click();
                                                <?php else : ?>
                                                // Select first tab.
                                                $plan.find('.nav-tab:first').click();
                                                <?php endif ?>
                                            });
                                        }(jQuery));
                                    </script>
                                </div>
                                <div class="fs-pricing-body">
                                    <span class="fs-billing-frequency"></span>
                                    <?php $annual_discount = ( $has_annual && $has_monthly ) ? $plan->pricing[0]->annual_discount_percentage() : 0 ?>
                                    <?php if ( $annual_discount > 0 ) : ?>
                                        <span
                                            class="fs-annual-discount"><?php printf(
                                            /* translators: %s: Discount (e.g. discount of $5 or 10%) */
                                                fs_esc_html_inline( 'Save %s', 'save-x', $api->slug ), $annual_discount . '%' ) ?></span>
                                    <?php endif ?>
                                    <ul class="fs-licenses">
                                    </ul>
                                    <?php echo $this->get_actions_dropdown( $api, $plan ) ?>
                                    <div style="clear:both"></div>
                                    <?php if ( $plan->has_trial() ) : ?>
                                        <?php $trial_period = $this->get_trial_period( $plan ) ?>
                                        <ul class="fs-trial-terms">
                                            <li>
                                                <i class="dashicons dashicons-yes"></i><?php echo esc_html( sprintf( fs_text_inline( 'No commitment for %s - cancel anytime', 'no-commitment-x', $api->slug ), $trial_period ) ) ?>
                                            </li>
                                            <li>
                                                <i class="dashicons dashicons-yes"></i><?php printf( esc_html( fs_text_inline( 'After your free %s, pay as little as %s', 'after-x-pay-as-little-y', $api->slug ) ), $trial_period, '<var class="fs-price">' . $this->get_price_tag( $plan, $plan->pricing[0] ) . '</var>' ) ?>
                                            </li>
                                        </ul>
                                    <?php endif ?>
                                </div>
                            </div>
                        <?php endforeach ?>
                      </div>
                    <?php endif ?>
                <?php endif ?>
                <div>
                    <h3><?php fs_echo_inline( 'Details', 'details', $api->slug ) ?></h3>
                    <ul>
                        <?php if ( ! empty( $api->version ) ) { ?>
                            <li>
                                <strong><?php fs_esc_html_echo_x_inline( 'Version', 'product version', 'version', $api->slug ); ?>
                                    :</strong> <?php echo $api->version; ?></li>
                            <?php
                        }
                            if ( ! empty( $api->author ) ) {
                                ?>
                                <li>
                                    <strong><?php fs_echo_x_inline( 'Author', 'as the plugin author', 'author', $api->slug ); ?>
                                        :</strong> <?php echo links_add_target( $api->author, '_blank' ); ?>
                                </li>
                                <?php
                            }
                            if ( ! empty( $api->last_updated ) ) {
                                ?>
                                <li><strong><?php fs_echo_inline( 'Last Updated', 'last-updated', $api->slug ); ?>
                                        :</strong> <span
                                        title="<?php echo $api->last_updated; ?>">
				<?php echo esc_html( sprintf(
                /* translators: %s: time period (e.g. "2 hours" ago) */
                    fs_text_x_inline( '%s ago', 'x-ago', $api->slug ),
                    human_time_diff( strtotime( $api->last_updated ) )
                ) ) ?>
			</span></li>
                                <?php
                            }
                            if ( ! empty( $api->requires ) ) {
                                ?>
                                <li>
                                    <strong><?php fs_esc_html_echo_inline( 'Requires WordPress Version', 'requires-wordpress-version', $api->slug ) ?>
                                        :</strong> <?php echo esc_html( sprintf(
                                            /* translators: %s: Version number. */
                                            fs_text_inline( '%s or higher', 'x-or-higher', $api->slug ), $api->requires )
                                    ) ?>
                                </li>
                                <?php
                            }
                            if ( ! empty( $api->tested ) ) {
                                ?>
                                <li>
                                    <strong><?php fs_esc_html_echo_inline( 'Compatible up to', 'compatible-up-to', $api->slug ); ?>
                                        :</strong> <?php echo $api->tested; ?>
                                </li>
                                <?php
                            }
                            if ( ! empty( $api->requires_php ) ) {
                                ?>
                                <li>
                                    <strong><?php fs_esc_html_echo_inline( 'Requires PHP Version', 'requires-php-version', $api->slug ); ?>:</strong>
                                    <?php
                                        echo esc_html( sprintf(
                                            /* translators: %s: Version number. */
                                            fs_text_inline( '%s or higher', 'x-or-higher', $api->slug ), $api->requires_php )
                                        );
                                    ?>
                                </li>
                                <?php
                            }
                            if ( ! empty( $api->downloaded ) ) {
                                ?>
                                <li>
                                    <strong><?php fs_esc_html_echo_inline( 'Downloaded', 'downloaded', $api->slug ) ?>
                                        :</strong> <?php echo esc_html( sprintf(
                                        ( ( 1 == $api->downloaded ) ?
                                            /* translators: %s: 1 or One (Number of times downloaded) */
                                            fs_text_inline( '%s time', 'x-time', $api->slug ) :
                                            /* translators: %s: Number of times downloaded */
                                            fs_text_inline( '%s times', 'x-times', $api->slug )
                                        ),
                                        number_format_i18n( $api->downloaded )
                                    ) ); ?>
                                </li>
                                <?php
                            }
                            if ( ! empty( $api->slug ) && true == $api->is_wp_org_compliant ) {
                                ?>
                                <li><a target="_blank"
                                       rel="noopener noreferrer"
                                       href="https://wordpress.org/plugins/<?php echo $api->slug; ?>/"><?php fs_esc_html_echo_inline( 'WordPress.org Plugin Page', 'wp-org-plugin-page', $api->slug ) ?>
                                        &#187;</a>
                                </li>
                                <?php
                            }
                            if ( ! empty( $api->homepage ) ) {
                                ?>
                                <li><a target="_blank"
                                       rel="noopener noreferrer"
                                       href="<?php echo esc_url( $api->homepage ); ?>"><?php fs_esc_html_echo_inline( 'Plugin Homepage', 'plugin-homepage', $api->slug ) ?>
                                        &#187;</a>
                                </li>
                                <?php
                            }
                            if ( ! empty( $api->donate_link ) && empty( $api->contributors ) ) {
                                ?>
                                <li><a target="_blank"
                                       rel="noopener noreferrer"
                                       href="<?php echo esc_url( $api->donate_link ); ?>"><?php fs_esc_html_echo_inline( 'Donate to this plugin', 'donate-to-plugin', $api->slug ) ?>
                                        &#187;</a>
                                </li>
                            <?php } ?>
                    </ul>
                </div>
                <?php if ( ! empty( $api->rating ) ) { ?>
                    <h3><?php fs_echo_inline( 'Average Rating', 'average-rating', $api->slug ); ?></h3>
                    <?php wp_star_rating( array(
                        'rating' => $api->rating,
                        'type'   => 'percent',
                        'number' => $api->num_ratings
                    ) ); ?>
                    <small>(<?php echo esc_html( sprintf(
                            fs_text_inline( 'based on %s', 'based-on-x', $api->slug ),
                            sprintf(
                                ( ( 1 == $api->num_ratings ) ?
                                    /* translators: %s: 1 or One */
                                    fs_text_inline( '%s rating', 'x-rating', $api->slug ) :
                                    /* translators: %s: Number larger than 1 */
                                    fs_text_inline( '%s ratings', 'x-ratings', $api->slug )
                                ),
                                number_format_i18n( $api->num_ratings )
                            ) ) ) ?>)
                    </small>
                    <?php
                }

                    if ( ! empty( $api->ratings ) && array_sum( (array) $api->ratings ) > 0 ) {
                        foreach ( $api->ratings as $key => $ratecount ) {
                            // Avoid div-by-zero.
                            $_rating     = $api->num_ratings ? ( $ratecount / $api->num_ratings ) : 0;
                            $stars_label = sprintf(
                                ( ( 1 == $key ) ?
                                    /* translators: %s: 1 or One */
                                    fs_text_inline( '%s star', 'x-star', $api->slug ) :
                                    /* translators: %s: Number larger than 1 */
                                    fs_text_inline( '%s stars', 'x-stars', $api->slug )
                                ),
                                number_format_i18n( $key )
                            );
                            ?>
                            <div class="counter-container">
                              <span class="counter-label"><a
                                href="https://wordpress.org/support/view/plugin-reviews/<?php echo $api->slug; ?>?filter=<?php echo $key; ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                title="<?php echo esc_attr( sprintf(
                                  /* translators: %s: # of stars (e.g. 5 stars) */
                                  fs_text_inline( 'Click to see reviews that provided a rating of %s', 'click-to-reviews', $api->slug ),
                                  $stars_label
                                ) ) ?>"><?php echo $stars_label ?></a></span>
                                <span class="counter-back">
                                <span class="counter-bar" style="width: <?php echo absint(92 * $_rating); ?>px;"></span>
                              </span>
                              <span class="counter-count"><?php echo number_format_i18n( $ratecount ); ?></span>
                            </div>
                            <?php
                        }
                    }
                    if ( ! empty( $api->contributors ) ) {
                        ?>
                        <h3><?php fs_echo_inline( 'Contributors', 'contributors', $api->slug ); ?></h3>
                        <ul class="contributors">
                            <?php
                                foreach ( (array) $api->contributors as $contrib_username => $contrib_profile ) {
                                    if ( empty( $contrib_username ) && empty( $contrib_profile ) ) {
                                        continue;
                                    }
                                    if ( empty( $contrib_username ) ) {
                                        $contrib_username = preg_replace( '/^.+\/(.+)\/?$/', '\1', $contrib_profile );
                                    }
                                    $contrib_username = sanitize_user( $contrib_username );
                                    if ( empty( $contrib_profile ) ) {
                                        echo "<li><img src='https://wordpress.org/grav-redirect.php?user={$contrib_username}&amp;s=36' width='18' height='18' />{$contrib_username}</li>";
                                    } else {
                                        echo "<li><a href='{$contrib_profile}' target='_blank' rel='noopener noreferrer'><img src='https://wordpress.org/grav-redirect.php?user={$contrib_username}&amp;s=36' width='18' height='18' />{$contrib_username}</a></li>";
                                    }
                                }
                            ?>
                        </ul>
                        <?php if ( ! empty( $api->donate_link ) ) { ?>
                            <a target="_blank"
                               rel="noopener noreferrer"
                               href="<?php echo esc_url( $api->donate_link ); ?>"><?php fs_echo_inline( 'Donate to this plugin', 'donate-to-plugin', $api->slug ) ?>
                                &#187;</a>
                        <?php } ?>
                    <?php } ?>
            </div>
            <div id="section-holder" class="wrap">
            <?php
            $requires_php = isset( $api->requires_php ) ? $api->requires_php : null;
            $requires_wp  = isset( $api->requires ) ? $api->requires : null;

            $compatible_php = empty( $requires_php ) || version_compare( PHP_VERSION, $requires_php, '>=' );

            // Strip off any -alpha, -RC, -beta, -src suffixes.
            list( $wp_version ) = explode( '-', $GLOBALS['wp_version'] );

            $compatible_wp  = empty( $requires_wp ) || version_compare( $wp_version, $requires_wp, '>=' );
            $tested_wp      = ( empty( $api->tested ) || version_compare( $wp_version, $api->tested, '<=' ) );

            if ( ! $compatible_php ) {
                echo '<div class="notice notice-error notice-alt"><p><strong>' . fs_text_inline( 'Error', 'error', $api->slug ) . ':</strong> ' . fs_text_inline( 'This plugin requires a newer version of PHP.', 'newer-php-required-error', $api->slug );

                if ( current_user_can( 'update_php' ) ) {
                    $wp_get_update_php_url = function_exists( 'wp_get_update_php_url' ) ?
                        wp_get_update_php_url() :
                        'https://wordpress.org/support/update-php/';

                    printf(
                    /* translators: %s: URL to Update PHP page. */
                        ' ' . fs_text_inline( '<a href="%s" target="_blank">Click here to learn more about updating PHP</a>.', 'php-update-learn-more-link', $api->slug ),
                        esc_url( $wp_get_update_php_url )
                    );

                    if ( function_exists( 'wp_update_php_annotation' ) ) {
                        wp_update_php_annotation( '</p><p><em>', '</em>' );
                    }
                } else {
                    echo '</p>';
                }
                echo '</div>';
            }

            if ( ! $tested_wp ) {
                echo '<div class="notice notice-warning"><p>' . '<strong>' . fs_text_inline( 'Warning', 'warning', $api->slug ) . ':</strong> ' . fs_text_inline( 'This plugin has not been tested with your current version of WordPress.', 'not-tested-warning', $api->slug ) . '</p></div>';
            } else if ( ! $compatible_wp ) {
                echo '<div class="notice notice-warning"><p>' . '<strong>' . fs_text_inline( 'Warning', 'warning', $api->slug ) . ':</strong> ' . fs_text_inline( 'This plugin has not been marked as compatible with your version of WordPress.', 'not-compatible-warning', $api->slug ) . '</p></div>';
            }

            foreach ( (array) $api->sections as $section_name => $content ) {
                $content = links_add_base_url( $content, 'https://wordpress.org/plugins/' . $api->slug . '/' );
                $content = links_add_target( $content, '_blank' );

                $san_section = esc_attr( $section_name );

                $display = ( $section_name === $section ) ? 'block' : 'none';

                if ( 'description' === $section_name &&
                     ( ( $api->is_wp_org_compliant && $api->wp_org_missing ) ||
                       ( ! $api->is_wp_org_compliant && $api->fs_missing ) )
                ) {
                    $missing_notice = array(
                        'type'    => 'error',
                        'id'      => md5( microtime() ),
                        'message' => $api->is_paid ?
                            fs_text_inline( 'Paid add-on must be deployed to Freemius.', 'paid-addon-not-deployed', $api->slug ) :
                            fs_text_inline( 'Add-on must be deployed to WordPress.org or Freemius.', 'free-addon-not-deployed', $api->slug ),
                    );
                    fs_require_template( 'admin-notice.php', $missing_notice );
                }
                echo "\t<div id='section-{$san_section}' class='section' style='display: {$display};'>\n";
                echo $content;
                echo "\t</div>\n";
            }
            echo "</div>\n";
            echo "</div>\n";
            echo "</div>\n"; // #plugin-information-scrollable
            echo "<div id='$tab-footer'>\n";

            if (
                ! empty( $api->download_link ) &&
                ! empty( $this->status ) &&
                in_array( $this->status['status'], array( 'newer_installed', 'latest_installed' ) )
            ) {
                if ( 'newer_installed' === $this->status['status'] ) {
                    echo $this->get_cta(
                        ( $this->status['is_premium_installed'] ?
                            esc_html( sprintf( fs_text_inline( 'Newer Version (%s) Installed', 'newer-installed', $api->slug ), $this->status['version'] ) ) :
                            esc_html( sprintf( fs_text_inline( 'Newer Free Version (%s) Installed', 'newer-free-installed', $api->slug ), $this->status['version'] ) ) ),
                        false,
                        true
                    );
                } else {
                    echo $this->get_cta(
                        ( $this->status['is_premium_installed'] ?
                            fs_esc_html_inline( 'Latest Version Installed', 'latest-installed', $api->slug ) :
                            fs_esc_html_inline( 'Latest Free Version Installed', 'latest-free-installed', $api->slug ) ),
                        false,
                        true
                    );
                }
            }

            echo $this->get_actions_dropdown( $api, null );

            echo "</div>\n";
            ?>
            <script type="text/javascript">
                ( function( $, undef ) {
                    var $dropdowns = $( '.fs-dropdown' );

                    $( '#plugin-information' )
                        .click( function( evt ) {
                            var $target = $( evt.target );

                            if (
                                $target.hasClass( 'fs-dropdown-arrow-button' ) ||
                                ( 0 !== $target.parents( '.fs-dropdown-arrow-button' ).length )
                            ) {
                                var $dropdown = $target.parents( '.fs-dropdown' ),
                                    isActive  = $dropdown.hasClass( 'active' );

                                if ( ! isActive ) {
                                    /**
                                     * Close the other dropdown if it's active.
                                     *
                                     * @author Leo Fajardo (@leorw)
                                     * @since 2.3.0
                                     */
                                    $( '.fs-dropdown.active' ).each( function() {
                                        toggleDropdown( $( this ), false );
                                    } );
                                }

                                /**
                                 * Toggle the current dropdown.
                                 *
                                 * @author Leo Fajardo (@leorw)
                                 * @since 2.3.0
                                 */
                                toggleDropdown( $dropdown, ! isActive );

                                return true;
                            }

                            /**
                             * Close all dropdowns.
                             *
                             * @author Leo Fajardo (@leorw)
                             * @since 2.3.0
                             */
                            toggleDropdown( $( this ).find( '.fs-dropdown' ), false );
                        });

                    if ( 0 !== $dropdowns.length ) {
                        /**
                         * Add the `up` class so that the bottom dropdown's content will be shown above its buttons.
                         *
                         * @author Leo Fajardo (@leorw)
                         * @since 2.3.0
                         */
                        $( '#plugin-information-footer' ).find( '.fs-dropdown' ).addClass( 'up' );
                    }

                    /**
                     * Returns the default state of the dropdown arrow button and hides the dropdown list.
                     *
                     * @author Leo Fajardo (@leorw)
                     * @since 2.3.0
                     *
                     * @param {Object}  [$dropdown]
                     * @param {Boolean} [state]
                     */
                    function toggleDropdown( $dropdown, state ) {
                        if ( undef === $dropdown ) {
                            var $activeDropdown = $dropdowns.find( '.active' );
                            if ( 0 !== $activeDropdown.length ) {
                                $dropdown = $activeDropdown;
                            }
                        }

                        if ( undef === $dropdown ) {
                            return;
                        }

                        if ( undef === state ) {
                            state = false;
                        }

                        $dropdown.toggleClass( 'active', state );
                        $dropdown.find( '.fs-dropdown-list' ).toggle( state );
                        $dropdown.find( '.fs-dropdown-arrow-button' ).toggleClass( 'active', state );
                    }
                } )( jQuery );
            </script>
            <?php
            iframe_footer();
            exit;
        }
    }
