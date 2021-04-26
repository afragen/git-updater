<?php
    /**
     * @var array    $VARS
     * @var Freemius $fs
     */
    $fs       = $VARS['parent_fs'];
    $addon_id = $VARS['addon_id'];
    $odd      = $VARS['odd'];
    $slug     = $fs->get_slug();

    $fs_blog_id = $VARS['fs_blog_id'];

    $active_plugins_directories_map = $VARS['active_plugins_directories_map'];

    $addon_info         = $VARS['addon_info'];
    $is_addon_activated = $fs->is_addon_activated( $addon_id );
    $is_addon_connected = $addon_info['is_connected'];
    $is_addon_installed = $VARS['is_addon_installed'];

    $fs_addon = ( $is_addon_connected && $is_addon_installed ) ?
        freemius( $addon_id ) :
        false;

    // Aliases.
    $download_latest_text         = fs_text_x_inline( 'Download Latest', 'as download latest version', 'download-latest', $slug );
    $downgrading_plan_text        = fs_text_inline( 'Downgrading your plan', 'downgrading-plan', $slug );
    $cancelling_subscription_text = fs_text_inline( 'Cancelling the subscription', 'cancelling-subscription', $slug );
    /* translators: %1$s: Either 'Downgrading your plan' or 'Cancelling the subscription' */
    $downgrade_x_confirm_text     = fs_text_inline( '%1$s will immediately stop all future recurring payments and your %s plan license will expire in %s.', 'downgrade-x-confirm', $slug );
    $prices_increase_text         = fs_text_inline( 'Please note that we will not be able to grandfather outdated pricing for renewals/new subscriptions after a cancellation. If you choose to renew the subscription manually in the future, after a price increase, which typically occurs once a year, you will be charged the updated price.', 'pricing-increase-warning', $slug );
    $cancel_trial_confirm_text         = fs_text_inline( 'Cancelling the trial will immediately block access to all premium features. Are you sure?', 'cancel-trial-confirm', $slug );
    $after_downgrade_non_blocking_text = fs_text_inline( 'You can still enjoy all %s features but you will not have access to %s security & feature updates, nor support.', 'after-downgrade-non-blocking', $slug );
    $after_downgrade_blocking_text     = fs_text_inline( 'Once your license expires you can still use the Free version but you will NOT have access to the %s features.', 'after-downgrade-blocking', $slug );
    /* translators: %s: Plan title (e.g. "Professional") */
    $activate_plan_text = fs_text_inline( 'Activate %s Plan', 'activate-x-plan', $slug );
    $version_text       = fs_text_x_inline( 'Version', 'product version', 'version', $slug );
    /* translators: %s: Time period (e.g. Auto renews in "2 months") */
    $renews_in_text = fs_text_inline( 'Auto renews in %s', 'renews-in', $slug );
    /* translators: %s: Time period (e.g. Expires in "2 months") */
    $expires_in_text   = fs_text_inline( 'Expires in %s', 'expires-in', $slug );
    $cancel_trial_text = fs_text_inline( 'Cancel Trial', 'cancel-trial', $slug );
    $change_plan_text  = fs_text_inline( 'Change Plan', 'change-plan', $slug );
    $upgrade_text      = fs_text_x_inline( 'Upgrade', 'verb', 'upgrade', $slug );
    $addons_text       = fs_text_inline( 'Add-Ons', 'add-ons', $slug );
    $downgrade_text    = fs_text_x_inline( 'Downgrade', 'verb', 'downgrade', $slug );
    $trial_text        = fs_text_x_inline( 'Trial', 'trial period', 'trial', $slug );
    $free_text         = fs_text_inline( 'Free', 'free', $slug );
    $activate_text     = fs_text_inline( 'Activate', 'activate', $slug );
    $plan_text         = fs_text_x_inline( 'Plan', 'as product pricing plan', 'plan', $slug );

    // Defaults.
    $plan                   = null;
    $is_paid_trial          = false;
    /**
     * @var FS_Plugin_License $license
     */
    $license                = null;
    $site                   = null;
    $is_active_subscription = false;
    $subscription           = null;
    $is_paying              = false;
    $show_upgrade           = false;
    $is_whitelabeled        = $VARS['is_whitelabeled'];

    if ( is_object( $fs_addon ) ) {
        $is_paying                  = $fs_addon->is_paying();
        $user                       = $fs_addon->get_user();
        $site                       = $fs_addon->get_site();
        $license                    = $fs_addon->_get_license();
        $subscription               = ( is_object( $license ) ?
            $fs_addon->_get_subscription( $license->id ) :
            null );
        $plan                       = $fs_addon->get_plan();
        $plan_name                  = $plan->name;
        $plan_title                 = $plan->title;
        $is_paid_trial              = $fs_addon->is_paid_trial();
        $version                    = $fs_addon->get_plugin_version();
        $is_whitelabeled            = (
            $fs_addon->is_whitelabeled( true ) &&
            ! $fs_addon->get_parent_instance()->is_data_debug_mode()
        );
        $show_upgrade               = (
            ! $is_whitelabeled &&
            $fs_addon->has_paid_plan() &&
            ! $is_paying &&
            ! $is_paid_trial &&
            ! $fs_addon->_has_premium_license()
        );
    } else if ( $is_addon_connected ) {
        if (
            empty( $addon_info ) ||
            ! isset( $addon_info['site'] )
        ) {
            $is_addon_connected = false;
        } else {
            /**
             * @var FS_Site $site
             */
            $site    = $addon_info['site'];
            $version = $addon_info['version'];

            $plan_name = isset( $addon_info['plan_name'] ) ?
                $addon_info['plan_name'] :
                '';

            $plan_title = isset( $addon_info['plan_title'] ) ?
                $addon_info['plan_title'] :
                '';

            if ( isset( $addon_info['license'] ) ) {
                $license = $addon_info['license'];
            }

            if ( isset( $addon_info['subscription'] ) ) {
                $subscription = $addon_info['subscription'];
            }

            $has_valid_and_active_license = (
                is_object( $license ) &&
                $license->is_active() &&
                $license->is_valid()
            );

            $is_paid_trial = (
                $site->is_trial() &&
                $has_valid_and_active_license &&
                ( $site->trial_plan_id == $license->plan_id )
            );

            $is_whitelabeled = $addon_info['is_whitelabeled'];
        }
    }

    $has_feature_enabled_license = (
        is_object( $license ) &&
        $license->is_features_enabled()
    );

    $is_active_subscription = ( is_object( $subscription ) && $subscription->is_active() );

    $show_delete_install_button = ( ! $is_paying && WP_FS__DEV_MODE && ! $is_whitelabeled );
?>
<tr<?php if ( $odd ) {
    echo ' class="alternate"';
} ?>>
    <td>
        <!-- Title -->
        <?php echo $addon_info['title'] ?>
    </td>
    <?php if ( $is_addon_connected ) : ?>
        <!-- ID -->
        <td><?php echo $site->id ?></td>
        <!--/ ID -->

        <!-- Version -->
        <td><?php echo $version ?></td>
        <!--/ Version -->

        <!-- Plan Title -->
        <td><?php echo strtoupper( is_string( $plan_name ) ? $plan_title : $free_text ) ?></td>
        <!--/ Plan Title -->

        <!-- Expiration -->
        <td>
        <?php if ( $site->is_trial() || is_object( $license ) ) : ?>
            <?php
                $tags = array();

                if ( $site->is_trial() ) {
                    $tags[] = array( 'label' => $trial_text, 'type' => 'success' );

                    $tags[] = array(
                        'label' => sprintf(
                            ( $is_paid_trial ?
                                $renews_in_text :
                                $expires_in_text ),
                            human_time_diff( time(), strtotime( $site->trial_ends ) )
                        ),
                        'type'  => ( $is_paid_trial ? 'success' : 'warn' )
                    );
                } else {
                    if ( is_object( $license ) ) {
                        if ( $license->is_cancelled ) {
                            $tags[] = array(
                                'label' => fs_text_inline( 'Cancelled', 'cancelled', $slug ),
                                'type'  => 'error'
                            );
                        } else if ( $license->is_expired() ) {
                            $tags[] = array(
                                'label' => fs_text_inline( 'Expired', 'expired', $slug ),
                                'type'  => 'error'
                            );
                        } else if ( $license->is_lifetime() ) {
                            $tags[] = array(
                                'label' => fs_text_inline( 'No expiration', 'no-expiration', $slug ),
                                'type'  => 'success'
                            );
                        } else if ( ! $is_active_subscription && ! $license->is_first_payment_pending() ) {
                            $tags[] = array(
                                'label' => sprintf( $expires_in_text, human_time_diff( time(), strtotime( $license->expiration ) ) ),
                                'type'  => 'warn'
                            );
                        } else if ( $is_active_subscription && ! $subscription->is_first_payment_pending() ) {
                            $tags[] = array(
                                'label' => sprintf( $renews_in_text, human_time_diff( time(), strtotime( $subscription->next_payment ) ) ),
                                'type'  => 'success'
                            );
                        }
                    }
                }

                foreach ( $tags as $t ) {
                    printf( '<label class="fs-tag fs-%s">%s</label>' . "\n", $t['type'], $t['label'] );
                }
            ?>
        <?php endif ?>
        </td>
        <!--/ Expiration -->

        <?php
        $buttons = array();
        $is_license_activation_added = false;

        if ( $is_addon_activated ) {
            if ( ! $is_whitelabeled ) {
                if ( $is_paying ) {
                    $buttons[] = fs_ui_get_action_button(
                        $fs->get_id(),
                        'account',
                        'deactivate_license',
                        fs_text_inline( 'Deactivate License', 'deactivate-license', $slug ),
                        '',
                        array( 'plugin_id' => $addon_id ),
                        false,
                        true
                    );

                    $human_readable_license_expiration = human_time_diff( time(), strtotime( $license->expiration ) );
                    $downgrade_confirmation_message    = sprintf(
                        $downgrade_x_confirm_text,
                        ( $fs_addon->is_only_premium() ? $cancelling_subscription_text : $downgrading_plan_text ),
                        $plan->title,
                        $human_readable_license_expiration
                    );

                    $after_downgrade_message = ! $license->is_block_features ?
                        sprintf( $after_downgrade_non_blocking_text, $plan->title, $fs_addon->get_module_label( true ) ) :
                        sprintf( $after_downgrade_blocking_text, $plan->title );

                    if ( ! $license->is_lifetime() && $is_active_subscription ) {
                        $buttons[] = fs_ui_get_action_button(
                            $fs->get_id(),
                            'account',
                            'downgrade_account',
                            esc_html( $fs_addon->is_only_premium() ? fs_text_inline( 'Cancel Subscription', 'cancel-subscription', $slug ) : $downgrade_text ),
                            '',
                            array( 'plugin_id' => $addon_id ),
                            false,
                            false,
                            false,
                            ( $downgrade_confirmation_message . ' ' . $after_downgrade_message . ' ' . $prices_increase_text ),
                            'POST'
                        );
                    }
                } else if ( $is_paid_trial ) {
                    $buttons[] = fs_ui_get_action_button(
                        $fs->get_id(),
                        'account',
                        'cancel_trial',
                        esc_html( $cancel_trial_text ),
                        '',
                        array( 'plugin_id' => $addon_id ),
                        false,
                        false,
                        'dashicons dashicons-download',
                        $cancel_trial_confirm_text,
                        'POST'
                    );
                } else if ( ! $has_feature_enabled_license ) {
                    $premium_licenses = $fs_addon->get_available_premium_licenses();

                    if ( ! empty( $premium_licenses ) ) {
                        $premium_license               = $premium_licenses[0];
                        $has_multiple_premium_licenses = ( 1 < count( $premium_licenses ) );

                        if ( ! $has_multiple_premium_licenses ) {
                            $premium_plan = $fs_addon->_get_plan_by_id( $premium_license->plan_id );
                            $site         = $fs_addon->get_site();

                            $buttons[] = fs_ui_get_action_button(
                                $fs->get_id(),
                                'account',
                                'activate_license',
                                esc_html( sprintf( $activate_plan_text, $premium_plan->title, ( $site->is_localhost() && $premium_license->is_free_localhost ) ? '[localhost]' : ( 1 < $premium_license->left() ? $premium_license->left() . ' left' : '' ) ) ),
                                ($has_multiple_premium_licenses ?
                                    'activate-license-trigger ' . $fs_addon->get_unique_affix() :
                                    ''),
                                array(
                                    'plugin_id'  => $addon_id,
                                    'license_id' => $premium_license->id,
                                ),
                                true,
                                true
                            );

                            $is_license_activation_added = true;
                        }
                    }
                }
            }

//            if ( 0 == count( $buttons ) ) {
                if ( $fs_addon->is_premium() && ! $is_license_activation_added ) {
                    $fs_addon->_add_license_activation_dialog_box();

                    $buttons[] = fs_ui_get_action_button(
                        $fs->get_id(),
                        'account',
                        'activate_license',
                        ( ! $has_feature_enabled_license ) ?
                            fs_esc_html_inline( 'Activate License', 'activate-license', $slug ) :
                            fs_esc_html_inline( 'Change License', 'change-license', $slug ),
                        'activate-license-trigger ' . $fs_addon->get_unique_affix(),
                        array(
                            'plugin_id' => $addon_id,
                        ),
                        (! $has_feature_enabled_license),
                        true
                    );

                    $is_license_activation_added = true;
                }

                if ( $fs_addon->has_paid_plan() ) {
                    // Add sync license only if non of the other CTAs are visible.
                    $buttons[] = fs_ui_get_action_button(
                        $fs->get_id(),
                        'account',
                        $fs->get_unique_affix() . '_sync_license',
                        fs_esc_html_x_inline( 'Sync', 'as synchronize', 'sync', $slug ),
                        '',
                        array( 'plugin_id' => $addon_id ),
                        false,
                        true
                    );
                }
//            }
        } else if ( ! $show_upgrade ) {
            if ( $fs->is_addon_installed( $addon_id ) ) {
                $addon_file = $fs->get_addon_basename( $addon_id );

                if ( ! isset( $active_plugins_directories_map[ dirname( $addon_file ) ] ) ) {
                    $buttons[]  = sprintf(
                        '<a class="button button-primary edit" href="%s" title="%s">%s</a>',
                        wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $addon_file, 'activate-plugin_' . $addon_file ),
                        fs_esc_attr_inline( 'Activate this add-on', 'activate-this-addon', $slug ),
                        $activate_text
                    );
                }
            } else {
                if ( $fs->is_allowed_to_install() ) {
                    $buttons[] = sprintf(
                        '<a class="button button-primary edit" href="%s">%s</a>',
                        wp_nonce_url( self_admin_url( 'update.php?' . ( ( isset( $addon_info['has_paid_plan'] ) && $addon_info['has_paid_plan'] ) ? 'fs_allow_updater_and_dialog=true&' : '' ) . 'action=install-plugin&plugin=' . $addon_info['slug'] ), 'install-plugin_' . $addon_info['slug'] ),
                        fs_text_inline( 'Install Now', 'install-now', $slug )
                    );
                } else {
                    $buttons[] = sprintf(
                        '<a target="_blank" rel="noopener" class="button button-primary edit" href="%s">%s</a>',
                        $fs->_get_latest_download_local_url( $addon_id ),
                        esc_html( $download_latest_text )
                    );
                }
            }
        }

        if ( $show_upgrade ) {
            $buttons[] = sprintf( '<a href="%s" class="thickbox button button-small button-primary" aria-label="%s" data-title="%s"><i class="dashicons dashicons-cart"></i> %s</a>',
                esc_url( network_admin_url( 'plugin-install.php?fs_allow_updater_and_dialog=true' . ( ! empty( $fs_blog_id ) ? '&fs_blog_id=' . $fs_blog_id : '' ) . '&tab=plugin-information&parent_plugin_id=' . $fs->get_id() . '&plugin=' . $addon_info['slug'] .
                                            '&TB_iframe=true&width=600&height=550' ) ),
                esc_attr( sprintf( fs_text_inline( 'More information about %s', 'more-information-about-x', $slug ), $addon_info['title'] ) ),
                esc_attr( $addon_info['title'] ),
                ( $fs_addon->has_free_plan() ?
                    $upgrade_text :
                    fs_text_x_inline( 'Purchase', 'verb', 'purchase', $slug ) )
            );
        }

        $buttons_count = count( $buttons );
        ?>

        <!-- Actions -->
        <td><?php if ( $buttons_count > 1 ) : ?>
            <div class="button-group"><?php endif ?>
                <?php foreach ( $buttons as $button ) {
                        echo $button;
                    } ?>
                <?php if ( $buttons_count > 1 ) : ?></div><?php endif ?></td>
        <!--/ Actions -->

    <?php else : ?>
        <?php // Add-on NOT Installed or was never connected.
            $is_addon_installed_by_filesystem = $fs->is_addon_installed( $addon_id );
        ?>
        <!-- Action -->
        <td colspan="<?php echo ( $is_addon_installed_by_filesystem || $show_delete_install_button ) ? '5' : '4' ?>">
            <?php if ( $is_addon_installed_by_filesystem ) : ?>
                <?php $addon_file = $fs->get_addon_basename( $addon_id ) ?>
                <?php if ( ! isset( $active_plugins_directories_map[ dirname( $addon_file ) ] ) ) : ?>
                <a class="button button-primary"
                   href="<?php echo wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $addon_file, 'activate-plugin_' . $addon_file ) ?>"
                   title="<?php fs_esc_attr_echo_inline( 'Activate this add-on', 'activate-this-addon', $slug ) ?>"
                   class="edit"><?php echo esc_html( $activate_text ) ?></a>
                <?php endif ?>
            <?php else : ?>
                <?php if ( $fs->is_allowed_to_install() ) : ?>
                    <a class="button button-primary"
                       href="<?php echo wp_nonce_url( self_admin_url( 'update.php?' . ( ( isset( $addon_info['has_paid_plan'] ) && $addon_info['has_paid_plan'] ) ? 'fs_allow_updater_and_dialog=true&' : '' ) . 'action=install-plugin&plugin=' . $addon_info['slug'] ), 'install-plugin_' . $addon_info['slug'] ) ?>"><?php fs_esc_html_echo_inline( 'Install Now', 'install-now', $slug ) ?></a>
                <?php else : ?>
                    <a target="_blank" rel="noopener" class="button button-primary"
                       href="<?php echo $fs->_get_latest_download_local_url( $addon_id ) ?>"><?php echo esc_html( $download_latest_text ) ?></a>
                <?php endif ?>
            <?php endif ?>
        </td>
        <!--/ Action -->
    <?php endif ?>
    <?php if ( $show_delete_install_button ) : ?>
    <!-- Optional Delete Action -->
        <td>
            <?php
                if ( $is_addon_activated ) {
                    fs_ui_action_button(
                        $fs->get_id(), 'account',
                        'delete_account',
                        fs_text_x_inline( 'Delete', 'verb', 'delete', $slug ),
                        '',
                        array( 'plugin_id' => $addon_id ),
                        false,
                        $show_upgrade
                    );
                }
            ?>
        </td>
        <!--/ Optional Delete Action -->
    <?php endif ?>
</tr>