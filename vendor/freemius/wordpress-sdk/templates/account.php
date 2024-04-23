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

	/**
	 * @var array $VARS
	 * @var Freemius $fs
	 */
	$fs   = freemius( $VARS['id'] );

	$slug = $fs->get_slug();

	/**
	 * @var FS_Plugin_Tag $update
	 */
	$update = $fs->has_release_on_freemius() ?
		$fs->get_update( false, false ) :
		null;

	if ( is_object($update) ) {
		/**
		 * This logic is particularly required for multisite environment.
         * If a module is site activated (not network) and not on the main site,
         * the module will NOT be executed on the network level, therefore, the
         * custom updates logic will not be executed as well, so unless we force
         * the injection of the update into the updates transient, premium updates
         * will not work.
         *
         * @author Vova Feldman (@svovaf)
         * @since  2.0.0
         */
		$updater = FS_Plugin_Updater::instance( $fs );
		$updater->set_update_data( $update );
	}

    $is_paying              = $fs->is_paying();
    $user                   = $fs->get_user();
    $site                   = $fs->get_site();
    $name                   = $user->get_name();
    $license                = $fs->_get_license();
    $is_license_foreign     = ( is_object( $license ) && $user->id != $license->user_id );
    $is_data_debug_mode     = $fs->is_data_debug_mode();
    $is_whitelabeled        = $fs->is_whitelabeled();
    $subscription           = ( is_object( $license ) ?
                                  $fs->_get_subscription( $license->id ) :
                                  null );
    $plan                   = $fs->get_plan();
    $is_active_subscription = ( is_object( $subscription ) && $subscription->is_active() );
    $is_paid_trial          = $fs->is_paid_trial();
    $has_paid_plan          = $fs->apply_filters( 'has_paid_plan_account', $fs->has_paid_plan() );
    $show_upgrade           = ( ! $is_whitelabeled && $has_paid_plan && ! $is_paying && ! $is_paid_trial );
    $trial_plan             = $fs->get_trial_plan();

    $is_plan_change_supported = (
        ! $fs->is_single_plan() &&
        ! $fs->apply_filters( 'hide_plan_change', false )
    );

	if ( $has_paid_plan ) {
        $fs->_add_license_activation_dialog_box();
	}

	if ( $fs->should_handle_user_change() ) {
        $fs->_add_email_address_update_dialog_box();
    }

    $ids_of_installs_activated_with_foreign_licenses = $fs->should_handle_user_change() ?
        $fs->get_installs_ids_with_foreign_licenses() :
        array();

    if ( ! empty( $ids_of_installs_activated_with_foreign_licenses ) ) {
        $fs->_add_user_change_dialog_box( $ids_of_installs_activated_with_foreign_licenses );
    }

    if ( $fs->is_whitelabeled( true ) || $fs->is_data_debug_mode() ) {
        $fs->_add_data_debug_mode_dialog_box();
    }

	if ( fs_request_get_bool( 'auto_install' ) ) {
		$fs->_add_auto_installation_dialog_box();
	}

	if ( fs_request_get_bool( 'activate_license' ) ) {
		// Open the license activation dialog box on the account page.
		add_action( 'admin_footer', array(
			&$fs,
			'_open_license_activation_dialog_box'
		) );
	}

    $show_billing = ( ! $is_whitelabeled && ! $fs->apply_filters( 'hide_billing_and_payments_info', false ) );
    if ( $show_billing ) {
        $payments = $fs->_fetch_payments();

        $show_billing = ( is_array( $payments ) && 0 < count( $payments ) );
    }


    $has_tabs = $fs->_add_tabs_before_content();

	// Aliases.
	$download_latest_text         = fs_text_x_inline( 'Download Latest', 'as download latest version', 'download-latest', $slug );
	$downgrading_plan_text        = fs_text_inline( 'Downgrading your plan', 'downgrading-plan', $slug );
	$cancelling_subscription_text = fs_text_inline( 'Cancelling the subscription', 'cancelling-subscription', $slug );
	/* translators: %1$s: Either 'Downgrading your plan' or 'Cancelling the subscription' */
	$downgrade_x_confirm_text          = fs_text_inline( '%1$s will immediately stop all future recurring payments and your %2$s plan license will expire in %3$s.', 'downgrade-x-confirm', $slug );
	$prices_increase_text              = fs_text_inline( 'Please note that we will not be able to grandfather outdated pricing for renewals/new subscriptions after a cancellation. If you choose to renew the subscription manually in the future, after a price increase, which typically occurs once a year, you will be charged the updated price.', 'pricing-increase-warning', $slug );
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
	$sync_license_text = fs_text_x_inline( 'Sync License', 'as synchronize license', 'sync-license', $slug );
	$cancel_trial_text = fs_text_inline( 'Cancel Trial', 'cancel-trial', $slug );
	$change_plan_text  = fs_text_inline( 'Change Plan', 'change-plan', $slug );
	$upgrade_text      = fs_text_x_inline( 'Upgrade', 'verb', 'upgrade', $slug );
	$addons_text       = fs_text_inline( 'Add-Ons', 'add-ons', $slug );
	$downgrade_text    = fs_text_x_inline( 'Downgrade', 'verb', 'downgrade', $slug );
	$trial_text        = fs_text_x_inline( 'Trial', 'trial period', 'trial', $slug );
	$free_text         = fs_text_inline( 'Free', 'free', $slug );
	$activate_text     = fs_text_inline( 'Activate', 'activate', $slug );
	$plan_text         = fs_text_x_inline( 'Plan', 'as product pricing plan', 'plan', $slug );
	$bundle_plan_text  = fs_text_inline( 'Bundle Plan', 'bundle-plan', $slug );

    $show_plan_row    = true;
    $show_license_row = is_object( $license );

	$site_view_params        = array();

    if ( fs_is_network_admin() ) {
        $sites                   = Freemius::get_sites();
        $all_installs_plan_id    = null;
        $all_installs_license_id = ( $show_license_row ? $license->id : null );
        foreach ( $sites as $s ) {
            $site_info   = $fs->get_site_info( $s );
            $install     = $fs->get_install_by_blog_id( $site_info['blog_id'] );
            $view_params = array(
                'freemius' => $fs,
                'user'     => $fs->get_user(),
                'license'  => $license,
                'site'     => $site_info,
                'install'  => $install,
            );

            $site_view_params[] = $view_params;

            if ( empty( $install ) ) {
                continue;
            }

            if ( $show_plan_row ) {
                if ( is_null( $all_installs_plan_id ) ) {
                    $all_installs_plan_id = $install->plan_id;
                } else if ( $all_installs_plan_id != $install->plan_id ) {
                    $show_plan_row = false;
                }
            }

            if ( $show_license_row && $all_installs_license_id != $install->license_id ) {
                $show_license_row = false;
            }
        }
    }

    $has_bundle_license = false;

    if ( is_object( $license ) &&
        FS_Plugin_License::is_valid_id( $license->parent_license_id )
    ) {
        // Context license has a parent license, therefore, the account has a bundle license.
        $has_bundle_license = true;
    }

    $bundle_subscription             = null;
    $is_bundle_first_payment_pending = false;

    if (
        $show_plan_row &&
        is_object( $license ) &&
        $has_bundle_license
    ) {
        $bundle_plan_title               = strtoupper( $license->parent_plan_title );
        $bundle_subscription             = $fs->_get_subscription( $license->parent_license_id );
        $is_bundle_first_payment_pending = $license->is_first_payment_pending();
    }

    $fs_blog_id = ( is_multisite() && ! is_network_admin() ) ?
        get_current_blog_id() :
        0;

    $active_plugins_directories_map = Freemius::get_active_plugins_directories_map( $fs_blog_id );

    $is_premium = $fs->is_premium();

    $account_addons       = $fs->get_updated_account_addons();
    $installed_addons     = $fs->get_installed_addons();
    $installed_addons_ids = array();

    /**
     * Store the installed add-ons' IDs into a collection which will be used in determining the add-ons to show on the "Account" page, and at the same time try to find an add-on that is activated with a bundle license if the core product is not.
     *
     * @author Leo Fajardo
     *
     * @since 2.4.0
     */
    foreach ( $installed_addons as $fs_addon ) {
        $installed_addons_ids[] = $fs_addon->get_id();

        if ( $has_bundle_license ) {
            // We already have the context bundle license details, skip.
            continue;
        }

        if (
            $show_plan_row &&
            $fs_addon->has_active_valid_license()
        ) {
            $addon_license = $fs_addon->_get_license();

            if ( FS_Plugin_License::is_valid_id( $addon_license->parent_license_id ) ) {
                // Add-on's license is associated with a parent/bundle license.
                $has_bundle_license = true;

                $bundle_plan_title               = strtoupper( $addon_license->parent_plan_title );
                $bundle_subscription             = $fs_addon->_get_subscription( $addon_license->parent_license_id );
                $is_bundle_first_payment_pending = $addon_license->is_first_payment_pending();
            }
        }
    }

    $addons_to_show = array_unique( array_merge( $installed_addons_ids, $account_addons ) );

    $is_active_bundle_subscription = ( is_object( $bundle_subscription ) && $bundle_subscription->is_active() );

    $available_license = ( $fs->is_free_plan() && ! fs_is_network_admin() ) ?
        $fs->_get_available_premium_license( $site->is_localhost() ) :
        null;

    $available_license_paid_plan = is_object( $available_license ) ?
        $fs->_get_plan_by_id( $available_license->plan_id ) :
        null;
?>
	<div class="wrap fs-section">
		<?php if ( ! $has_tabs && ! $fs->apply_filters( 'hide_account_tabs', false ) ) : ?>
		<h2 class="nav-tab-wrapper">
			<a href="<?php echo esc_url( $fs->get_account_url() ) ?>"
			   class="nav-tab nav-tab-active"><?php fs_esc_html_echo_inline( 'Account', 'account', $slug ) ?></a>
			<?php if ( $fs->has_addons() ) : ?>
				<a href="<?php echo esc_url( $fs->_get_admin_page_url( 'addons' ) ) ?>"
				   class="nav-tab"><?php echo esc_html( $addons_text ) ?></a>
			<?php endif ?>
			<?php if ( $show_upgrade ) : ?>
				<a href="<?php echo esc_url( $fs->get_upgrade_url() ) ?>" class="nav-tab"><?php echo esc_html( $upgrade_text ) ?></a>
				<?php if ( $fs->apply_filters( 'show_trial', true ) && ! $fs->is_trial_utilized() && $fs->has_trial_plan() ) : ?>
					<a href="<?php echo esc_url( $fs->get_trial_url() ) ?>" class="nav-tab"><?php fs_esc_html_echo_inline( 'Free Trial', 'free-trial', $slug ) ?></a>
				<?php endif ?>
			<?php endif ?>
		</h2>
		<?php endif ?>

		<div id="poststuff">
			<div id="fs_account">
				<div class="has-sidebar has-right-sidebar">
					<div class="has-sidebar-content">
						<div class="postbox">
							<h3><span class="dashicons dashicons-businessman"></span> <?php fs_esc_html_echo_inline( 'Account Details', 'account-details', $slug ) ?></h3>
							<div class="fs-header-actions">
								<ul>
                                    <?php if ( $fs->is_whitelabeled( true ) ) : ?>
                                        <li>
                                            <a href="#" class="debug-license-trigger"><i class="dashicons dashicons-<?php echo $is_whitelabeled ? 'editor-code' : 'controls-pause' ?>"></i> <span><?php
                                                if ( $is_whitelabeled ) {
                                                    fs_esc_html_echo_inline( 'Start Debug', 'start-debug-license', $slug );
                                                } else {
                                                    fs_esc_html_echo_inline( 'Stop Debug', 'stop-debug-license', $slug );
                                                }
                                            ?></span></a>
                                        </li>
                                        <li>&nbsp;&bull;&nbsp;</li>
                                    <?php endif ?>
									<?php if ( $show_billing ) : ?>
                                        <li><a href="#fs_billing"><i class="dashicons dashicons-portfolio"></i> <?php fs_esc_html_echo_inline( 'Billing & Invoices', 'billing-invoices', $slug ) ?></a></li>
										<li>&nbsp;&bull;&nbsp;</li>
									<?php endif ?>
                                    <?php if ( ! $is_whitelabeled ) : ?>
                                        <?php if ( ! $is_paying ) : ?>
                                            <li>
                                                <?php
                                                    $view_params = array(
                                                        'freemius'          => $fs,
                                                        'license'           => $available_license,
                                                        'license_paid_plan' => $available_license_paid_plan,
                                                    );
                                                    fs_require_template( 'account/partials/disconnect-button.php', $view_params ); ?>
                                            </li>
                                            <li>&nbsp;&bull;&nbsp;</li>
                                        <?php endif ?>
                                        <?php if ( $is_paying ) : ?>
                                            <?php if ( ! fs_is_network_admin() ) : ?>
                                            <li>
                                                <form action="<?php echo esc_url( $fs->_get_admin_page_url( 'account' ) ) ?>" method="POST">
                                                    <input type="hidden" name="fs_action" value="deactivate_license">
                                                    <?php wp_nonce_field( 'deactivate_license' ) ?>
                                                    <a href="#" class="fs-deactivate-license"><i
                                                            class="dashicons dashicons-admin-network"></i> <?php fs_echo_inline( 'Deactivate License', 'deactivate-license', $slug ) ?>
                                                    </a>
                                                </form>
                                            </li>
                                            <li>&nbsp;&bull;&nbsp;</li>
                                            <?php endif ?>
                                            <?php if ( ! $license->is_lifetime() &&
                                                       $is_active_subscription
                                            ) : ?>
                                                <li>
                                                    <form action="<?php echo esc_url( $fs->_get_admin_page_url( 'account' ) ) ?>" method="POST">
                                                        <input type="hidden" name="fs_action" value="downgrade_account">
                                                        <?php wp_nonce_field( 'downgrade_account' ) ?>
                                                        <a href="#"
                                                           onclick="if ( confirm('<?php echo esc_attr( sprintf(
                                                               $downgrade_x_confirm_text,
                                                               ( $fs->is_only_premium() ? $cancelling_subscription_text : $downgrading_plan_text ),
                                                               $plan->title,
                                                               human_time_diff( time(), strtotime( $license->expiration ) )
                                                           ) ) ?> <?php if ( ! $license->is_block_features ) {
                                                               echo esc_attr( sprintf( $after_downgrade_non_blocking_text, $plan->title, $fs->get_module_label( true ) ) );
                                                           } else {
                                                               echo esc_attr( sprintf( $after_downgrade_blocking_text, $plan->title ) );
                                                           }?> <?php echo esc_attr( $prices_increase_text ) ?> <?php fs_esc_attr_echo_inline( 'Are you sure you want to proceed?', 'proceed-confirmation', $slug ) ?>') ) this.parentNode.submit(); return false;"><i class="dashicons dashicons-download"></i> <?php echo esc_html( $fs->is_only_premium() ? fs_text_inline( 'Cancel Subscription', 'cancel-subscription', $slug ) : $downgrade_text ) ?></a>
                                                    </form>
                                                </li>
                                                <li>&nbsp;&bull;&nbsp;</li>
                                            <?php endif ?>
                                            <?php if ( $is_plan_change_supported ) : ?>
                                                <li>
                                                    <a href="<?php echo esc_url( $fs->get_upgrade_url() ) ?>"><i
                                                            class="dashicons dashicons-grid-view"></i> <?php echo esc_html( $change_plan_text ) ?></a>
                                                </li>
                                                <li>&nbsp;&bull;&nbsp;</li>
                                            <?php endif ?>
                                        <?php elseif ( $is_paid_trial ) : ?>
                                            <li>
                                                <form action="<?php echo esc_url( $fs->_get_admin_page_url( 'account' ) ) ?>" method="POST">
                                                    <input type="hidden" name="fs_action" value="cancel_trial">
                                                    <?php wp_nonce_field( 'cancel_trial' ) ?>
                                                    <a href="#" class="fs-cancel-trial"><i
                                                            class="dashicons dashicons-download"></i> <?php echo esc_html( $cancel_trial_text ) ?></a>
                                                </form>
                                            </li>
                                            <li>&nbsp;&bull;&nbsp;</li>
                                        <?php endif ?>
                                    <?php endif ?>
                                    <li>
                                        <form action="<?php echo esc_url( $fs->_get_admin_page_url( 'account' ) ) ?>" method="POST">
                                            <input type="hidden" name="fs_action" value="<?php echo esc_attr( $fs->get_unique_affix() ) ?>_sync_license">
                                            <?php wp_nonce_field( $fs->get_unique_affix() . '_sync_license' ) ?>
                                            <a href="#" onclick="this.parentNode.submit(); return false;"><i
                                                    class="dashicons dashicons-image-rotate"></i> <?php fs_esc_html_echo_x_inline( 'Sync', 'as synchronize', 'sync', $slug ) ?></a>
                                        </form>
                                    </li>
								</ul>
							</div>
							<div class="inside">
								<table id="fs_account_details" cellspacing="0" class="fs-key-value-table">
									<?php
										$hide_license_key = ( ! $show_license_row || $fs->apply_filters( 'hide_license_key', false ) );

										$profile   = array();

    									if ( ! $is_whitelabeled ) {
                                            $profile[] = array(
                                                'id'    => 'user_name',
                                                'title' => fs_text_inline( 'Name', 'name', $slug ),
                                                'value' => $name
                                            );
                                            //					if (isset($user->email) && false !== strpos($user->email, '@'))
                                            $profile[] = array(
                                                'id'    => 'email',
                                                'title' => fs_text_inline( 'Email', 'email', $slug ),
                                                'value' => $user->email
                                            );

                                            if ( is_numeric( $user->id ) ) {
                                                $profile[] = array(
                                                    'id'    => 'user_id',
                                                    'title' => fs_text_inline( 'User ID', 'user-id', $slug ),
                                                    'value' => $user->id
                                                );
                                            }
                                        }

										$profile[] = array(
											'id'    => 'product',
											'title' => ( $fs->is_plugin() ?
												fs_text_inline( 'Plugin', 'plugin', $slug ) :
												fs_text_inline( 'Theme', 'theme', $slug ) ),
											'value' => $fs->get_plugin_title()
										);

										$profile[] = array(
											'id'    => 'product_id',
											'title' => ( $fs->is_plugin() ?
												fs_text_inline( 'Plugin', 'plugin', $slug ) :
												fs_text_inline( 'Theme', 'theme', $slug ) ) . ' ' . fs_text_inline( 'ID', 'id', $slug ),
											'value' => $fs->get_id()
										);

										if ( ! fs_is_network_admin()) {
                                            $profile[] = array(
                                                'id'    => 'site_id',
                                                'title' => fs_text_inline( 'Site ID', 'site-id', $slug ),
                                                'value' => is_string( $site->id ) ?
                                                    $site->id :
                                                    fs_text_inline( 'No ID', 'no-id', $slug )
                                            );

                                            $profile[] = array(
                                                'id'    => 'site_public_key',
                                                'title' => fs_text_inline( 'Public Key', 'public-key', $slug ),
                                                'value' => $site->public_key
                                            );

                                            $profile[] = array(
                                                'id'    => 'site_secret_key',
                                                'title' => fs_text_inline( 'Secret Key', 'secret-key', $slug ),
                                                'value' => ( ( is_string( $site->secret_key ) ) ?
                                                    $site->secret_key :
                                                    fs_text_x_inline( 'No Secret', 'as secret encryption key missing', 'no-secret', $slug )
                                                )
                                            );
                                        }

										$profile[] = array(
											'id'    => 'version',
											'title' => $version_text,
											'value' => $fs->get_plugin_version()
										);

										if ( ! fs_is_network_admin() && $is_premium ) {
										    $profile[] = array(
                                                'id'    => 'beta_program',
                                                'title' => '',
                                                'value' => $site->is_beta
                                            );
                                        }

										if ( $has_paid_plan || $has_bundle_license ) {
											if ( $fs->is_trial() ) {
											    if ( $show_plan_row ) {
                                                    $profile[] = array(
                                                        'id'    => 'plan',
                                                        'title' => $plan_text,
                                                        'value' => ( is_string( $trial_plan->name ) ?
                                                            strtoupper( $trial_plan->title ) :
                                                            fs_text_inline( 'Trial', 'trial', $slug ) )
                                                    );
                                                }
											} else {
                                                if ( $show_plan_row ) {
                                                    $profile[] = array(
                                                        'id'    => 'plan',
                                                        'title' => ( $has_bundle_license ? ucfirst( $fs->get_module_type() ) . ' ' : '' ) . $plan_text,
                                                        'value' => strtoupper( is_string( $plan->name ) ?
                                                            $plan->title :
                                                            strtoupper( $free_text )
                                                        )
                                                    );

                                                    if ( $has_bundle_license ) {
                                                        $profile[] = array(
                                                            'id'    => 'bundle_plan',
                                                            'title' => $bundle_plan_text,
                                                            'value' => $bundle_plan_title
                                                        );
                                                    }
                                                }

												if ( is_object( $license ) ) {
													if ( ! $hide_license_key ) {
														$profile[] = array(
															'id'    => 'license_key',
															'title' => fs_text_inline( 'License Key', $slug ),
															'value' => $license->secret_key,
														);
													}
												}
											}
										}
									?>
									<?php $odd = true;
										foreach ( $profile as $p ) : ?>
											<?php
											if ( 'plan' === $p['id'] && ! $has_paid_plan ) {
												// If plugin don't have any paid plans, there's no reason
												// to show current plan.
												continue;
											}
											?>
											<tr class="fs-field-<?php echo esc_attr( $p['id'] ) ?><?php if ( $odd ) : ?> alternate<?php endif ?>">
												<td>
													<nobr><?php echo esc_attr( $p['title'] ) ?><?php echo ( ! empty( $p['title'] ) ) ? ':' : '' ?></nobr>
												</td>
												<td<?php if ( 'plan' === $p['id'] || 'bundle_plan' === $p['id'] ) { echo ' colspan="2"'; }?>>
													<?php if ( in_array( $p['id'], array( 'license_key', 'site_secret_key' ) ) ) : ?>
														<code><?php echo FS_Plugin_License::mask_secret_key_for_html( $p['value'] ) ?></code>
														<?php if ( ! $is_whitelabeled ) : ?>
                                                        <input type="text" value="<?php echo htmlspecialchars( $p['value'] ) ?>" style="display: none"
														       readonly/>
                                                        <?php endif ?>
                                                    <?php elseif ( 'beta_program' === $p['id'] ) : ?>
                                                        <label>
                                                            <input type="checkbox" class="fs-toggle-beta-mode" <?php checked( true, $p['value'] ) ?>/><span><?php
                                                                fs_esc_html_echo_inline( 'Join the Beta program', 'join-beta', $slug )
                                                        ?></span></label>
													<?php else : ?>
														<code><?php echo htmlspecialchars( $p['value'] ) ?></code>
													<?php endif ?>
													<?php if ( 'email' === $p['id'] && ! $user->is_verified() ) : ?>
														<label class="fs-tag fs-warn"><?php fs_esc_html_echo_inline( 'not verified', 'not-verified', $slug ) ?></label>
													<?php endif ?>
													<?php if ( 'plan' === $p['id'] ) : ?>
														<?php if ( $fs->is_trial() ) : ?>
															<label class="fs-tag fs-success"><?php echo esc_html( $trial_text ) ?></label>
														<?php endif ?>
														<?php if ( is_object( $license ) && ! $license->is_lifetime() ) : ?>
															<?php if ( ! $is_active_subscription && ! $is_active_bundle_subscription && ! $license->is_first_payment_pending() ) : ?>
                                                                <?php $is_license_expired = $license->is_expired() ?>
                                                                <?php $expired_ago_text   = ( fs_text_inline( 'Expired', 'expired', $slug ) . ' ' . fs_text_x_inline( '%s ago', 'x-ago', $slug ) ) ?>
																<label
																	class="fs-tag <?php echo $is_license_expired ? 'fs-error' : 'fs-warn' ?>"><?php
                                                                        echo esc_html( sprintf( $is_license_expired ? $expired_ago_text : $expires_in_text, human_time_diff( time(), strtotime( $license->expiration ) ) ) )
                                                                    ?></label>
															<?php elseif ( $is_active_subscription && ! $subscription->is_first_payment_pending() ) : ?>
																<label class="fs-tag fs-success"><?php echo esc_html( sprintf( $renews_in_text, human_time_diff( time(), strtotime( $subscription->next_payment ) ) ) ) ?></label>
															<?php endif ?>
														<?php elseif ( $fs->is_trial() ) : ?>
															<label class="fs-tag fs-warn"><?php echo esc_html( sprintf( $expires_in_text, human_time_diff( time(), strtotime( $site->trial_ends ) ) ) ) ?></label>
														<?php endif ?>
                                                        <?php if ( ! $is_whitelabeled ) : ?>
														<div class="button-group">
                                                            <?php if ( is_object( $available_license ) ) : ?>
                                                                <?php
                                                                $view_params = array(
                                                                    'freemius'     => $fs,
                                                                    'slug'         => $slug,
                                                                    'license'      => $available_license,
                                                                    'plan'         => $available_license_paid_plan,
                                                                    'is_localhost' => $site->is_localhost(),
                                                                    'install_id'   => $site->id,
                                                                    'class'        => 'button-primary',
                                                                );
                                                                fs_require_template( 'account/partials/activate-license-button.php', $view_params ); ?>
															<?php else : ?>
																<form action="<?php echo esc_url( $fs->_get_admin_page_url( 'account' ) ) ?>"
																      method="POST" class="button-group">
																	<?php if ( $show_upgrade && $is_premium ) : ?>
																		<a class="button activate-license-trigger <?php echo esc_attr( $fs->get_unique_affix() ) ?>" href="#"><?php fs_esc_html_echo_inline( 'Activate License', 'activate-license', $slug ) ?></a>
																	<?php endif ?>
																	<input type="submit" class="button"
																	       value="<?php echo esc_attr( $sync_license_text ) ?>">
																	<input type="hidden" name="fs_action"
																	       value="<?php echo esc_attr( $fs->get_unique_affix() ) ?>_sync_license">
																	<?php wp_nonce_field( $fs->get_unique_affix() . '_sync_license' ) ?>
																	<?php if ( $show_upgrade || $is_plan_change_supported ) : ?>
																	<a href="<?php echo esc_url( $fs->get_upgrade_url() ) ?>"
																	   class="button<?php
																		   echo $show_upgrade ?
																			   ' button-primary fs-upgrade' :
																			   ' fs-change-plan'; ?> button-upgrade"><i
																			class="dashicons dashicons-cart"></i> <?php echo esc_html( $show_upgrade ? $upgrade_text : $change_plan_text ) ?></a>
																	<?php endif ?>
																</form>
															<?php endif ?>
														</div>
                                                        <?php endif ?>
													<?php elseif ( 'bundle_plan' === $p['id'] ) : ?>
														<?php if ( is_object( $bundle_subscription ) ) : ?>
															<?php if ( $is_active_bundle_subscription && ! $is_bundle_first_payment_pending ) : ?>
																<label class="fs-tag fs-success"><?php echo esc_html( sprintf( $renews_in_text, human_time_diff( time(), strtotime( $bundle_subscription->next_payment ) ) ) ) ?></label>
															<?php endif ?>
                                                        <?php endif ?>
													<?php elseif ( 'version' === $p['id'] && $has_paid_plan ) : ?>
														<?php if ( $fs->has_premium_version() ) : ?>
															<?php if ( $is_premium ) : ?>
																<label
																	class="fs-tag fs-<?php echo $fs->can_use_premium_code() ? 'success' : 'warn' ?>"><?php fs_esc_html_echo_inline( 'Premium version', 'premium-version', $slug ) ?></label>
															<?php elseif ( $fs->can_use_premium_code() ) : ?>
																<label class="fs-tag fs-warn"><?php fs_esc_html_echo_inline( 'Free version', 'free-version', $slug ) ?></label>
															<?php endif ?>
														<?php endif ?>
													<?php endif ?>
												</td>
                                                <?php if ( 'plan' !== $p['id'] && 'bundle_plan' !== $p['id'] ) : ?>
													<td class="fs-right">
														<?php if ( 'email' === $p['id'] && ! $user->is_verified() ) : ?>
															<form action="<?php echo $fs->_get_admin_page_url( 'account' ) ?>" method="POST">
																<input type="hidden" name="fs_action" value="verify_email">
																<?php wp_nonce_field( 'verify_email' ) ?>
																<input type="submit" class="button button-small"
																       value="<?php fs_esc_attr_echo_inline( 'Verify Email', 'verify-email', $slug ) ?>">
															</form>
														<?php endif ?>
														<?php if ( 'version' === $p['id'] ) : ?>
															<?php if ( $fs->has_release_on_freemius() ) : ?>
																<div class="button-group">
																	<?php if ( $is_paying || $fs->is_trial() ) : ?>
																		<?php if ( ! $fs->is_allowed_to_install() ) : ?>
                                                                            <a target="_blank" rel="noopener" class="button button-primary"
                                                                                href="<?php echo $fs->_get_latest_download_local_url() ?>"><?php
                                                                                $download_version_text_suffix = ( is_object( $update ) ? ' [' . $update->version . ']' : '' );

                                                                                $download_version_text = sprintf(
                                                                                    /* translators: %s: plan name (e.g. Download "Professional" Version) */
                                                                                    fs_text_inline( 'Download %s Version', 'download-x-version', $slug ),
                                                                                    ( $fs->is_trial() ? $trial_plan->title : $plan->title )
                                                                                ) .
                                                                                $download_version_text_suffix;

                                                                                $download_version_text_length = function_exists( 'mb_strlen' ) ?
                                                                                    mb_strlen( $download_version_text ) :
                                                                                    strlen( $download_version_text );

                                                                                if ( $download_version_text_length > 31 ) {
                                                                                    /**
                                                                                     * Try to limit the number of characters to 31 for now.
                                                                                     *
                                                                                     * @author Leo Fajardo (@leorw)
                                                                                     * @aince 2.3.2
                                                                                     */
                                                                                    $download_version_text = fs_text_inline( 'Download Paid Version', 'download-paid-version', $slug ) . $download_version_text_suffix;
                                                                                }

                                                                                echo $download_version_text;
                                                                            ?></a>
																		<?php elseif ( is_object( $update ) ) : ?>
																			<?php
																			$module_type = $fs->get_module_type();
																			?>
																			<a class="button button-primary"
																			   href="<?php echo wp_nonce_url( self_admin_url( "update.php?action=upgrade-{$module_type}&{$module_type}=" . $fs->get_plugin_basename() ), "upgrade-{$module_type}_" . $fs->get_plugin_basename() ) ?>"><?php echo fs_esc_html_inline( 'Install Update Now', 'install-update-now', $slug ) . ' [' . $update->version . ']' ?></a>
																		<?php endif ?>
																	<?php endif; ?>
																</div>
															<?php endif ?>
															<?php
														elseif ( in_array( $p['id'], array( 'license_key', 'site_secret_key' ) ) ) : ?>
                                                            <?php if ( ! $is_whitelabeled && ( 'site_secret_key' === $p['id'] || ! $is_license_foreign ) ) : ?>
                                                                <button class="button button-small fs-toggle-visibility"><?php fs_esc_html_echo_x_inline( 'Show', 'verb', 'show', $slug ) ?></button>
                                                            <?php endif ?>
                                                            <?php if ('license_key' === $p['id']) : ?>
                                                                <button class="button button-small activate-license-trigger <?php echo $fs->get_unique_affix() ?>"><?php fs_esc_html_echo_inline( 'Change License', 'change-license', $slug ) ?></button>
                                                            <?php endif ?>
															<?php
														elseif (/*in_array($p['id'], array('site_secret_key', 'site_id', 'site_public_key')) ||*/
														( is_string( $user->secret_key ) && in_array( $p['id'], array(
																'email',
																'user_name'
															) ) )
														) : ?>
                                                            <?php if ( 'email' !== $p['id'] || ! fs_is_network_admin() ) : ?>
															<form action="<?php echo $fs->_get_admin_page_url( 'account' ) ?>" method="POST"
															      onsubmit="var val = prompt('<?php echo esc_attr( sprintf(
                                                                      /* translators: %s: User's account property (e.g. name, email) */
															          fs_text_inline( 'What is your %s?', 'what-is-your-x', $slug ),
                                                                      $p['title']
                                                                  ) ) ?>', '<?php echo $p['value'] ?>'); if (null == val || '' === val) return false; jQuery('input[name=fs_<?php echo $p['id'] ?>_<?php echo $fs->get_unique_affix() ?>]').val(val); return true;">
																<input type="hidden" name="fs_action" value="update_<?php echo $p['id'] ?>">
																<input type="hidden" name="fs_<?php echo $p['id'] ?>_<?php echo $fs->get_unique_affix() ?>"
																       value="">
																<?php wp_nonce_field( 'update_' . $p['id'] ) ?>
																<input type="submit" class="button button-small <?php if ( 'email' === $p['id'] ) echo 'button-edit-email-address' ?>"
																       value="<?php echo fs_esc_attr_x_inline( 'Edit', 'verb', 'edit', $slug ) ?>">
															</form>
                                                            <?php endif ?>
                                                        <?php elseif ( 'user_id' === $p['id'] && ! empty( $ids_of_installs_activated_with_foreign_licenses ) ) : ?>
                                                                <input id="fs_change_user" type="submit" class="button button-small"
                                                                       value="<?php echo fs_esc_attr_inline( 'Change User', 'change-user', $slug ) ?>">
														<?php endif ?>
													</td>
												<?php endif ?>
											</tr>
											<?php
                                                if ( 'version' === $p['id'] && $is_premium ) {
                                                    /**
                                                     * If there's a row for the beta program, keep its background color
                                                     * the same as the version info row.
                                                     *
                                                     * @author Leo Fajardo (@leorw)
                                                     * @since 2.3.0
                                                     */
                                                    continue;
                                                }

                                                $odd = ! $odd;
                                        endforeach ?>
								</table>
							</div>
						</div>
						<?php if ( fs_is_network_admin() ) : ?>
						<div id="fs_sites" class="postbox">
							<h3><span class="dashicons dashicons-networking"></span> <?php fs_esc_html_echo_inline( 'Sites', 'sites', $slug ) ?></h3>
							<div class="fs-header-actions">
                                <?php if ( ! $is_whitelabeled ) : ?>
                                    <?php $has_license = is_object( $license ) ?>
                                    <?php if ( $has_license || ( $show_upgrade && $is_premium ) ) : ?>
                                        <?php
                                            $activate_license_button_text = $has_license ?
                                                fs_esc_html_inline( 'Change License', 'change-license', $slug ) :
                                                fs_esc_html_inline( 'Activate License', 'activate-license', $slug );
                                        ?>
                                        <a class="button<?php echo ( ! $has_license ? ' button-primary' : '' ) ?> activate-license-trigger <?php echo $fs->get_unique_affix() ?>" href="#"><?php echo $activate_license_button_text ?></a>
                                    <?php endif ?>
                                <?php endif ?>
								<input class="fs-search" type="text" placeholder="<?php fs_esc_attr_echo_inline( 'Search by address', 'search-by-address', $slug ) ?>..."><span class="dashicons dashicons-search"></span>
							</div>
							<div class="inside">
                                <div id="" class="fs-scrollable-table">
                                    <div class="fs-table-head">
                                        <table class="widefat">
                                            <thead>
                                            <tr>
                                                <td><?php fs_esc_html_echo_inline('ID', 'id', $slug) ?></td>
                                                <td><?php fs_esc_html_echo_inline('Address', 'address', $slug) ?></td>
                                                <td><?php fs_esc_html_echo_inline('License', 'license', $slug) ?></td>
                                                <td><?php fs_esc_html_echo_inline('Plan', 'plan', $slug) ?></td>
                                                <td></td>
                                            </tr>
                                            </thead>
                                        </table>
                                    </div>
                                    <div class="fs-table-body">
                                        <table class="widefat">
                                            <?php
                                                $current_blog_id = get_current_blog_id();

                                                foreach ( $site_view_params as $view_params ) {
                                                    fs_require_template(
                                                    	'account/partials/site.php',
	                                                    $view_params
                                                    );
                                                }

                                                /**
                                                 * It's possible for the `Freemius::switch_to_blog()` method to be called within the `site.php` template and this changes the Freemius instance's context, so this check is for restoring the previous context based on the previously retrieved site.
                                                 *
                                                 * @author Leo Fajardo (@leorw)
                                                 * @since 2.5.0
                                                 */
                                                $current_install = $fs->get_site();

                                                if (
                                                    is_object( $site ) &&
                                                    ( ! is_object( $current_install ) || $current_install->id != $site->id )
                                                ) {
                                                    $fs->switch_to_blog( $current_blog_id, $site, true );
                                                }
                                            ?>
                                        </table>
                                    </div>
                                </div>
							</div>
						</div>
						<?php endif ?>
						<?php if ( 0 < count( $addons_to_show ) ) : ?>
							<!-- Add-Ons -->
							<div class="postbox">
								<div class="">
									<!--				<div class="inside">-->
									<table id="fs_addons" class="widefat">
										<thead>
										<tr>
											<th><h3><?php echo esc_html( $addons_text ) ?></h3></th>
											<th><?php fs_esc_html_echo_inline( 'ID', 'id', $slug ) ?></th>
											<th><?php echo esc_html( $version_text ) ?></th>
											<th><?php echo esc_html( $plan_text ) ?></th>
											<th><?php fs_esc_html_echo_x_inline( 'License', 'as software license', 'license', $slug ) ?></th>
											<th></th>
											<?php if ( defined( 'WP_FS__DEV_MODE' ) && WP_FS__DEV_MODE ) : ?>
												<th></th>
											<?php endif ?>
										</tr>
										</thead>
										<tbody>
										<?php
                                            $odd = true;

                                            $installed_addons_ids_map = array_flip( $installed_addons_ids );

                                            $addon_info_by_id     = array();
                                            $hide_all_addons_data = false;

                                            if ( $fs->is_whitelabeled_by_flag() ) {
                                                $hide_all_addons_data = true;

                                                foreach ( $addons_to_show as $addon_id ) {
                                                    $is_addon_installed = isset( $installed_addons_ids_map[ $addon_id ] );
                                                    $addon_info         = $fs->_get_addon_info( $addon_id, $is_addon_installed );
                                                    $is_addon_connected = $addon_info['is_connected'];

                                                    $fs_addon = ( $is_addon_connected && $is_addon_installed ) ?
                                                        freemius( $addon_id ) :
                                                        null;

                                                    $is_whitelabeled = is_object( $fs_addon ) ?
                                                        $fs_addon->is_whitelabeled( true ) :
                                                        $addon_info['is_whitelabeled'];

                                                    if ( ! $is_whitelabeled ) {
                                                        $hide_all_addons_data = false;
                                                    }

                                                    if ( $is_data_debug_mode ) {
                                                        $is_whitelabeled = false;
                                                    }

                                                    $addon_info_by_id[ $addon_id ] = $addon_info;
                                                }
                                            }

											foreach ( $addons_to_show as $addon_id ) {
											    $is_addon_installed = isset( $installed_addons_ids_map[ $addon_id ] );

											    if (
                                                    $hide_all_addons_data &&
                                                    ! $is_addon_installed &&
                                                    ! file_exists( fs_normalize_path( WP_PLUGIN_DIR . '/' . $fs->get_addon_basename( $addon_id ) ) )
                                                ) {
											        continue;
                                                }

												$addon_view_params = array(
													'parent_fs'                      => $fs,
													'addon_id'                       => $addon_id,
													'odd'                            => $odd,
													'fs_blog_id'                     => $fs_blog_id,
                                                    'active_plugins_directories_map' => &$active_plugins_directories_map,
                                                    'is_addon_installed'             => $is_addon_installed,
                                                    'addon_info'                     => isset( $addon_info_by_id[ $addon_id ] ) ?
                                                        $addon_info_by_id[ $addon_id ] :
                                                        $fs->_get_addon_info( $addon_id, $is_addon_installed ),
                                                    'is_whitelabeled'                => ( $is_whitelabeled && ! $is_data_debug_mode )
												);

												fs_require_template(
													'account/partials/addon.php',
													$addon_view_params
												);

												$odd = ! $odd;
											} ?>
										</tbody>
									</table>
								</div>
							</div>
						<?php endif ?>

						<?php $fs->do_action( 'after_account_details' ) ?>

						<?php
							if ( $show_billing ) {
								$view_params = array( 'id' => $VARS['id'], 'payments' => $payments );
								fs_require_once_template( 'account/billing.php', $view_params );
								fs_require_once_template( 'account/payments.php', $view_params );
							}
						?>
					</div>
				</div>
			</div>
		</div>
	</div>
    <?php
        $subscription_cancellation_dialog_box_template_params = $fs->_get_subscription_cancellation_dialog_box_template_params( true );
        if ( ! empty( $subscription_cancellation_dialog_box_template_params ) ) {
            fs_require_template( 'forms/subscription-cancellation.php', $subscription_cancellation_dialog_box_template_params );
        }
    ?>
    <script type="text/javascript">
        (function ($) {
            var setLoading = function ($this, label) {
                // Set loading mode.
                $(document.body).css({'cursor': 'wait'});

                $this.css({'cursor': 'wait'});

                if ($this.is('input'))
                    $this.val(label);
                else
                    $this.html(label);

                setTimeout(function () {
                    $this.attr('disabled', 'disabled');
                }, 200);
            };

	        $('.fs-toggle-visibility').click(function () {
		        var
			        $this = $(this),
			        $parent = $this.closest('tr'),
			        $input = $parent.find('input');

		        $parent.find('code').toggle();
		        $input.toggle();

		        if ($input.is(':visible')) {
			        $this.html('<?php fs_esc_js_echo_x_inline( 'Hide', 'verb', 'hide', $slug ) ?>');
			        setTimeout(function () {
				        $input.select().focus();
			        }, 100);
		        }
		        else {
			        $this.html( '<?php fs_esc_js_echo_x_inline( 'Show', 'verb', 'show', $slug ) ?>' );
		        }
	        });

            $('.fs-toggle-tracking').click(function () {
                setLoading(
                	$(this),
	                ($(this).data('is-disconnected') ?
		                '<?php fs_esc_js_echo_inline('Opting in', 'opting-in' ) ?>' :
		                '<?php fs_esc_js_echo_inline('Opting out', 'opting-out' ) ?>') +
		                '...'
                );
            });

            <?php
                $plugin_title         = $fs->get_plugin_title();
                $processing_text      = fs_esc_js_inline( 'Processing', 'processing' );
                $confirmation_message = sprintf(
                    '%s %s',
                    sprintf( fs_esc_attr_inline( 'Get updates for bleeding edge Beta versions of %s.', 'get-beta-versions', $slug ), $plugin_title ),
                    sprintf( fs_esc_attr_inline( 'An update to a Beta version will replace your installed version of %s with the latest Beta release - use with caution, and not on production sites. You have been warned.', 'beta-version-update-caution', $slug ), $plugin_title )
                );
            ?>

            $( '.fs-toggle-beta-mode' ).click( function () {
                var $checkbox = $( this ),
                    isChecked = $checkbox.is( ':checked' );

                if ( ! isChecked || confirm( '<?php echo $confirmation_message ?>' ) ) {
                    $.ajax( {
                        url   : <?php echo Freemius::ajax_url() ?>,
                        method: 'POST',
                        data  : {
                            action   : '<?php echo $fs->get_ajax_action( 'set_beta_mode' ) ?>',
                            security : '<?php echo $fs->get_ajax_security( 'set_beta_mode' ) ?>',
                            is_beta  : isChecked,
                            module_id: <?php echo $fs->get_id() ?>
                        },
                        beforeSend: function () {
                            $checkbox.prop( 'disabled', true );
                            $checkbox.parent().find( 'span' ).text( '<?php echo $processing_text ?>' + '...' );
                        },
                        complete: function () {
                            $checkbox.prop( 'disabled', false );
                            $checkbox.parent().find( 'span' ).text( '<?php fs_esc_js_echo_inline( 'Join the Beta Program', 'join-beta', $slug ) ?>' );
                        }
                    } );

                    return true;
                }

                return false;
            });

	        $('.fs-opt-in').click(function () {
		        setLoading($(this), '<?php fs_esc_js_echo_inline('Opting in', 'opting-in' ) ?>...');
	        });

	        $( '#fs_downgrade' ).submit(function( event ) {
                event.preventDefault();

		        setLoading( $( this ).find( '.button' ), '<?php fs_esc_js_echo_inline( 'Downgrading', 'downgrading' ) ?>...' );
	        });

            $('.fs-activate-license').click(function () {
                setLoading($(this), '<?php fs_esc_js_echo_inline('Activating', 'activating' ) ?>...');
            });

            var $deactivateLicenseOrCancelTrial = $( '.fs-deactivate-license, .fs-cancel-trial' ),
                $subscriptionCancellationModal  = $( '.fs-modal-subscription-cancellation-<?php echo $fs->get_id() ?>' );

            if ( 0 !== $subscriptionCancellationModal.length ) {
                $subscriptionCancellationModal.on( '<?php echo $fs->get_action_tag( 'subscription_cancellation_action' ) ?>', function( evt, cancelSubscription ) {
                    setLoading(
                        $deactivateLicenseOrCancelTrial,
                        ( ! $deactivateLicenseOrCancelTrial.hasClass( 'fs-cancel-trial' ) ?
                            '<?php fs_esc_js_echo_inline( 'Deactivating', 'deactivating', $slug ) ?>' :
                            '<?php echo esc_html( sprintf( fs_text_inline( 'Cancelling %s', 'cancelling-x', $slug ), fs_text_inline( 'trial', 'trial', $slug ) ) ) ?>' ) + '...'
                    );

                    $subscriptionCancellationModal.find( '.fs-modal-footer .button' ).addClass( 'disabled' );
                    $deactivateLicenseOrCancelTrial.unbind( 'click' );

                    if ( false === cancelSubscription || $deactivateLicenseOrCancelTrial.hasClass( 'fs-cancel-trial' ) ) {
                        $subscriptionCancellationModal.find( '.fs-modal-footer .button-primary' ).text( $deactivateLicenseOrCancelTrial.text() );

                        $deactivateLicenseOrCancelTrial[0].parentNode.submit();
                    } else {
                        var $form = $( 'input[value="downgrade_account"],input[value="cancel_trial"]' ).parent();
                        $form.prepend( '<input type="hidden" name="deactivate_license" value="true" />' );

                        $subscriptionCancellationModal.find( '.fs-modal-footer .button-primary' ).text( '<?php echo esc_js( sprintf(
                            fs_text_inline( 'Cancelling %s...', 'cancelling-x' , $slug ),
                            $is_paid_trial ?
                                fs_text_inline( 'trial', 'trial', $slug ) :
                                fs_text_inline( 'subscription', 'subscription', $slug )
                        ) ) ?>' );

                        $form.submit();
                    }
                });
            }

            $deactivateLicenseOrCancelTrial.click(function() {
                var $this = $( this );
                if ( $this.hasClass( 'fs-cancel-trial' ) ) {
                    $subscriptionCancellationModal.find( '.fs-modal-panel' ).find( 'ul.subscription-actions, .fs-price-increase-warning' ).remove();
                    $subscriptionCancellationModal.find( '.fs-modal-panel > p' ).text( <?php echo json_encode( $cancel_trial_confirm_text ) ?> );
                    $subscriptionCancellationModal.trigger( 'showModal' );
                } else if (confirm('<?php fs_esc_attr_echo_inline( 'Deactivating your license will block all premium features, but will enable activating the license on another site. Are you sure you want to proceed?', 'deactivate-license-confirm', $slug ) ?>')) {
                    var $this = $(this);

                    if ( 0 !== $subscriptionCancellationModal.length ) {
                        $subscriptionCancellationModal.trigger( 'showModal' );
                    } else {
                        setLoading( $this, '<?php fs_esc_js_echo_inline( 'Deactivating', 'deactivating', $slug ) ?>...' );
                        $this[0].parentNode.submit();
                    }
                }

                return false;
            });

            var $sitesSection = $('#fs_sites'),
                $sitesTable = $sitesSection.find('.fs-scrollable-table'),
                $sitesTableRows = $sitesTable.find('.fs-site-details');

            $('.fs-show-install-details').click(function(){
                var installID = $(this).parents('.fs-site-details').attr('data-install-id');
                $sitesSection.find('.fs-install-details[data-install-id=' + installID + ']').toggle();
            });


            var adjustColumnWidth = function($table) {
                var $headerColumns = $table.find('.fs-table-head td'),
                    $bodyColumns   = $table.find('.fs-table-body tr:first > td');

                for (var i = 0, len = $headerColumns.length; i < len; i++) {
                    $($headerColumns[i]).width($($bodyColumns[i]).width());
                }
                for (i = 0, len = $headerColumns.length; i < len; i++) {
                    $($bodyColumns[i]).width($($headerColumns[i]).width());
                }
            };

            adjustColumnWidth($sitesTable);

            $sitesSection.find('.fs-search').keyup(function(){
                var search = $(this).val().trim();

                if ('' === search){
                    // Show all.
                    $sitesTableRows.show();
                    return;
                }

                var url;

                $sitesTableRows.each(function(index){
                    url = $(this).find('.fs-field-url').html();

                    if (-1 < url.indexOf(search)){
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });

            $( '.fs-toggle-whitelabel-mode' ).click( function () {
                var $toggleLink = $( this );

                $.ajax( {
                    url   : <?php echo Freemius::ajax_url() ?>,
                    method: 'POST',
                    data  : {
                        action   : '<?php echo $fs->get_ajax_action( 'toggle_whitelabel_mode' ) ?>',
                        security : '<?php echo $fs->get_ajax_security( 'toggle_whitelabel_mode' ) ?>',
                        module_id: <?php echo $fs->get_id() ?>
                    },
                    beforeSend: function () {
                        $toggleLink.parent().text( '<?php
                            $is_whitelabeled ?
                                fs_esc_html_echo_inline( 'Disabling white-label mode', 'disabling-whitelabel-mode' ) :
                                fs_esc_html_echo_inline( 'Enabling white-label mode', 'enabling-whitelabel-mode' )
                        ?>' + '...' );
                    },
                    complete: function () {
                        location.reload();
                    }
                } );
            });
        })(jQuery);
    </script>
<?php
	if ( $has_tabs ) {
		$fs->_add_tabs_after_content();
	}

	$params = array(
		'page'           => 'account',
		'module_id'      => $fs->get_id(),
		'module_type'    => $fs->get_module_type(),
		'module_slug'    => $slug,
		'module_version' => $fs->get_plugin_version(),
	);
    fs_require_template( 'powered-by.php', $params );
