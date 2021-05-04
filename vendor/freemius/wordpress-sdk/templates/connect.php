<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.0.7
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * @var array    $VARS
	 * @var Freemius $fs
	 */
	$fs   = freemius( $VARS['id'] );
	$slug = $fs->get_slug();

	$is_pending_activation = $fs->is_pending_activation();
	$is_premium_only       = $fs->is_only_premium();
	$has_paid_plans        = $fs->has_paid_plan();
	$is_premium_code       = $fs->is_premium();
	$is_freemium           = $fs->is_freemium();

	$fs->_enqueue_connect_essentials();

	$current_user = Freemius::_get_current_wp_user();

	$first_name = $current_user->user_firstname;
	if ( empty( $first_name ) ) {
		$first_name = $current_user->nickname;
	}

	$site_url     = get_site_url();
	$protocol_pos = strpos( $site_url, '://' );
	if ( false !== $protocol_pos ) {
		$site_url = substr( $site_url, $protocol_pos + 3 );
	}

	$freemius_site_www = 'https://freemius.com';

	$freemius_usage_tracking_url = $fs->get_usage_tracking_terms_url();
	$freemius_plugin_terms_url   = $fs->get_eula_url();

	$freemius_site_url = $fs->is_premium() ?
		$freemius_site_www :
		$freemius_usage_tracking_url;

	if ( $fs->is_premium() ) {
		$freemius_site_url .= '?' . http_build_query( array(
				'id'   => $fs->get_id(),
				'slug' => $slug,
			) );
	}

	$freemius_link = '<a href="' . $freemius_site_url . '" target="_blank" rel="noopener" tabindex="1">freemius.com</a>';

	$error = fs_request_get( 'error' );

	$require_license_key = $is_premium_only ||
	                       ( $is_freemium && $is_premium_code && fs_request_get_bool( 'require_license', true ) );

	if ( $is_pending_activation ) {
		$require_license_key = false;
	}

	if ( $require_license_key ) {
		$fs->_add_license_activation_dialog_box();
	}

	$is_optin_dialog = (
		$fs->is_theme() &&
		$fs->is_themes_page() &&
		$fs->show_opt_in_on_themes_page()
	);

	if ( $is_optin_dialog ) {
		$show_close_button             = false;
		$previous_theme_activation_url = '';

		if ( ! $is_premium_code ) {
			$show_close_button = true;
		} else if ( $is_premium_only ) {
			$previous_theme_activation_url = $fs->get_previous_theme_activation_url();
			$show_close_button             = ( ! empty( $previous_theme_activation_url ) );
		}
	}

	$is_network_level_activation = (
		fs_is_network_admin() &&
		$fs->is_network_active() &&
		! $fs->is_network_delegated_connection()
	);

	$fs_user = Freemius::_get_user_by_email( $current_user->user_email );

	$activate_with_current_user = (
		is_object( $fs_user ) &&
		! $is_pending_activation &&
		// If requires a license for activation, use the user associated with the license for the opt-in.
		! $require_license_key &&
		! $is_network_level_activation
	);

    $optin_params = $fs->get_opt_in_params( array(), $is_network_level_activation );
    $sites        = isset( $optin_params['sites'] ) ? $optin_params['sites'] : array();

    $is_network_upgrade_mode = ( fs_is_network_admin() && $fs->is_network_upgrade_mode() );

    /* translators: %s: name (e.g. Hey John,) */
    $hey_x_text = esc_html( sprintf( fs_text_x_inline( 'Hey %s,', 'greeting', 'hey-x', $slug ), $first_name ) );

    $is_gdpr_required = ( ! $is_pending_activation && ! $require_license_key ) ?
	    FS_GDPR_Manager::instance()->is_required() :
        false;

    if ( is_null( $is_gdpr_required ) ) {
        $is_gdpr_required = $fs->fetch_and_store_current_user_gdpr_anonymously();
    }
?>
<?php
	if ( $is_optin_dialog ) { ?>
<div id="fs_theme_connect_wrapper">
	<?php
		if ( $show_close_button ) { ?>
			<button class="close dashicons dashicons-no"><span class="screen-reader-text">Close connect dialog</span>
			</button>
			<?php
		}
	?>
	<?php
		}

		/**
		 * Allows developers to include custom HTML before the opt-in content.
		 *
		 * @author Vova Feldman
		 * @since 2.3.2
		 */
		$fs->do_action( 'connect/before' );
	?>
	<div id="fs_connect"
	     class="wrap<?php if ( ! fs_is_network_admin() && ( ! $fs->is_enable_anonymous() || $is_pending_activation || $require_license_key ) ) {
		     echo ' fs-anonymous-disabled';
	     } ?><?php echo $require_license_key ? ' require-license-key' : '' ?>">
		<div class="fs-visual">
			<b class="fs-site-icon"><i class="dashicons dashicons-wordpress"></i></b>
			<i class="dashicons dashicons-plus fs-first"></i>
			<?php
				$vars = array( 'id' => $fs->get_id() );
				fs_require_once_template( 'plugin-icon.php', $vars );
			?>
			<i class="dashicons dashicons-plus fs-second"></i>
			<img class="fs-connect-logo" width="80" height="80" src="//img.freemius.com/connect-logo.png"/>
		</div>
		<div class="fs-content">
			<?php if ( ! empty( $error ) ) : ?>
				<p class="fs-error"><?php echo esc_html( $error ) ?></p>
			<?php endif ?>
			<p><?php
					$button_label = fs_text_inline( 'Allow & Continue', 'opt-in-connect', $slug );
					$message = '';

					if ( $is_pending_activation ) {
						$button_label = fs_text_inline( 'Re-send activation email', 'resend-activation-email', $slug );

						$message = $fs->apply_filters( 'pending_activation_message', sprintf(
						    /* translators: %s: name (e.g. Thanks John!) */
							fs_text_inline( 'Thanks %s!', 'thanks-x', $slug ) . '<br>' .
							fs_text_inline( 'You should receive an activation email for %s to your mailbox at %s. Please make sure you click the activation button in that email to %s.', 'pending-activation-message', $slug ),
							$first_name,
							'<b>' . $fs->get_plugin_name() . '</b>',
							'<b>' . $current_user->user_email . '</b>',
							fs_text_inline( 'complete the install', 'complete-the-install', $slug )
						) );
					} else if ( $require_license_key ) {
						$button_label = $is_network_upgrade_mode ?
                            fs_text_inline( 'Activate License', 'agree-activate-license', $slug ) :
                            fs_text_inline( 'Agree & Activate License', 'agree-activate-license', $slug );

						$message = $fs->apply_filters(
						    'connect-message_on-premium',
							sprintf( fs_text_inline( 'Welcome to %s! To get started, please enter your license key:', 'thanks-for-purchasing', $slug ), '<b>' . $fs->get_plugin_name() . '</b>' ),
							$first_name,
							$fs->get_plugin_name()
						);
					} else {
						$filter                = 'connect_message';
						$default_optin_message = $is_gdpr_required ?
							fs_text_inline( 'Never miss an important update - opt in to our security & feature updates notifications, educational content, offers, and non-sensitive diagnostic tracking with %4$s.', 'connect-message', $slug) :
							fs_text_inline( 'Never miss an important update - opt in to our security and feature updates notifications, and non-sensitive diagnostic tracking with %4$s.', 'connect-message', $slug);

						if ( $fs->is_plugin_update() ) {
							// If Freemius was added on a plugin update, set different
							// opt-in message.
							$default_optin_message = $is_gdpr_required ?
								fs_text_inline( 'Never miss an important update - opt in to our security & feature updates notifications, educational content, offers, and non-sensitive diagnostic tracking with %4$s. If you skip this, that\'s okay! %1$s will still work just fine.', 'connect-message_on-update', $slug ) :
								fs_text_inline( 'Never miss an important update - opt in to our security & feature updates notifications, and non-sensitive diagnostic tracking with %4$s. If you skip this, that\'s okay! %1$s will still work just fine.', 'connect-message_on-update', $slug );

							// If user customized the opt-in message on update, use
							// that message. Otherwise, fallback to regular opt-in
							// custom message if exist.
							if ( $fs->has_filter( 'connect_message_on_update' ) ) {
								$filter = 'connect_message_on_update';
							}
						}

						$message = $fs->apply_filters(
						    $filter,
                            ($is_network_upgrade_mode ?
                                '' :
                                /* translators: %s: name (e.g. Hey John,) */
                                $hey_x_text . '<br>'
                            ) .
							sprintf(
								esc_html( $default_optin_message ),
								'<b>' . esc_html( $fs->get_plugin_name() ) . '</b>',
								'<b>' . $current_user->user_login . '</b>',
								'<a href="' . $site_url . '" target="_blank" rel="noopener noreferrer">' . $site_url . '</a>',
								$freemius_link
							),
							$first_name,
							$fs->get_plugin_name(),
							$current_user->user_login,
							'<a href="' . $site_url . '" target="_blank" rel="noopener noreferrer">' . $site_url . '</a>',
							$freemius_link,
							$is_gdpr_required
						);
					}

					if ( $is_network_upgrade_mode ) {
                        $network_integration_text = esc_html( fs_text_inline( 'We\'re excited to introduce the Freemius network-level integration.', 'connect_message_network_upgrade', $slug ) );

                        if ($is_premium_code){
                            $message = $network_integration_text . ' ' . sprintf( fs_text_inline( 'During the update process we detected %d site(s) that are still pending license activation.', 'connect_message_network_upgrade-premium', $slug ), count( $sites ) );

                            $message .= '<br><br>' . sprintf( fs_text_inline( 'If you\'d like to use the %s on those sites, please enter your license key below and click the activation button.', 'connect_message_network_upgrade-premium-activate-license', $slug ), $is_premium_only ? $fs->get_module_label( true ) : sprintf(
                                /* translators: %s: module type (plugin, theme, or add-on) */
                                    fs_text_inline( "%s's paid features", 'x-paid-features', $slug ),
                                    $fs->get_module_label( true )
                                ) );

                            /* translators: %s: module type (plugin, theme, or add-on) */
                            $message .= ' ' . sprintf( fs_text_inline( 'Alternatively, you can skip it for now and activate the license later, in your %s\'s network-level Account page.', 'connect_message_network_upgrade-premium-skip-license', $slug ), $fs->get_module_label( true ) );
                        }else {
                            $message = $network_integration_text . ' ' . sprintf( fs_text_inline( 'During the update process we detected %s site(s) in the network that are still pending your attention.', 'connect_message_network_upgrade-free', $slug ), count( $sites ) ) . '<br><br>' . ( fs_starts_with( $message, $hey_x_text . '<br>' ) ? substr( $message, strlen( $hey_x_text . '<br>' ) ) : $message );
                        }
                    }

					echo $message;
				?></p>
			<?php if ( $require_license_key ) : ?>
				<div class="fs-license-key-container">
					<input id="fs_license_key" name="fs_key" type="text" required maxlength="<?php echo $fs->apply_filters('license_key_maxlength', 32) ?>"
					       placeholder="<?php fs_esc_attr_echo_inline( 'License key', 'license-key', $slug ) ?>" tabindex="1"/>
					<i class="dashicons dashicons-admin-network"></i>
					<a class="show-license-resend-modal show-license-resend-modal-<?php echo $fs->get_unique_affix() ?>"
					   href="#"><?php fs_esc_html_echo_inline( "Can't find your license key?", 'cant-find-license-key', $slug ); ?></a>
				</div>

				<?php
				/**
				 * Allows developers to include custom HTML after the license input container.
				 *
				 * @author Vova Feldman
				 * @since 2.1.2
				 */
				 $fs->do_action( 'connect/after_license_input' );
				?>

                <?php
                    $send_updates_text = sprintf(
                        '%s<span class="action-description"> - %s</span>',
                        $fs->get_text_inline( 'Yes', 'yes' ),
                        $fs->get_text_inline( 'send me security & feature updates, educational content and offers.', 'send-updates' )
                    );

                    $do_not_send_updates_text = sprintf(
                        '%s<span class="action-description"> - %s</span>',
                        $fs->get_text_inline( 'No', 'no' ),
                        sprintf(
                            $fs->get_text_inline( 'do %sNOT%s send me security & feature updates, educational content and offers.', 'do-not-send-updates' ),
                            '<span class="underlined">',
                            '</span>'
                        )
                    );
                ?>
                <div id="fs_marketing_optin">
                    <span class="fs-message"><?php fs_echo_inline( "Please let us know if you'd like us to contact you for security & feature updates, educational content, and occasional offers:", 'contact-for-updates' ) ?></span>
                    <div class="fs-input-container">
                        <label>
                            <input type="radio" name="allow-marketing" value="true" tabindex="1" />
                            <span class="fs-input-label"><?php echo $send_updates_text ?></span>
                        </label>
                        <label>
                            <input type="radio" name="allow-marketing" value="false" tabindex="1" />
                            <span class="fs-input-label"><?php echo $do_not_send_updates_text ?></span>
                        </label>
                    </div>
                </div>
			<?php endif ?>
			<?php if ( $is_network_level_activation ) : ?>
            <?php
                $vars = array(
                    'id'                  => $fs->get_id(),
                    'sites'               => $sites,
                    'require_license_key' => $require_license_key
                );

                echo fs_get_template( 'partials/network-activation.php', $vars );
            ?>
			<?php endif ?>
		</div>
		<div class="fs-actions">
			<?php if ( $fs->is_enable_anonymous() && ! $is_pending_activation && ( ! $require_license_key || $is_network_upgrade_mode ) ) : ?>
				<a id="skip_activation" href="<?php echo fs_nonce_url( $fs->_get_admin_page_url( '', array( 'fs_action' => $fs->get_unique_affix() . '_skip_activation' ), $is_network_level_activation ), $fs->get_unique_affix() . '_skip_activation' ) ?>"
				   class="button button-secondary" tabindex="2"><?php fs_esc_html_echo_x_inline( 'Skip', 'verb', 'skip', $slug ) ?></a>
			<?php endif ?>
			<?php if ( $is_network_level_activation && $fs->apply_filters( 'show_delegation_option', true ) ) : ?>
				<a id="delegate_to_site_admins" class="fs-tooltip-trigger <?php echo is_rtl() ? ' rtl' : '' ?>" href="<?php echo fs_nonce_url( $fs->_get_admin_page_url( '', array( 'fs_action' => $fs->get_unique_affix() . '_delegate_activation' ) ), $fs->get_unique_affix() . '_delegate_activation' ) ?>"><?php fs_esc_html_echo_inline( 'Delegate to Site Admins', 'delegate-to-site-admins', $slug ) ?><span class="fs-tooltip"><?php fs_esc_html_echo_inline( 'If you click it, this decision will be delegated to the sites administrators.', 'delegate-sites-tooltip', $slug ) ?></span></a>
			<?php endif ?>
			<?php if ( $activate_with_current_user ) : ?>
				<form action="" method="POST">
					<input type="hidden" name="fs_action"
					       value="<?php echo $fs->get_unique_affix() ?>_activate_existing">
					<?php wp_nonce_field( 'activate_existing_' . $fs->get_public_key() ) ?>
					<input type="hidden" name="is_extensions_tracking_allowed" value="1">
					<button class="button button-primary" tabindex="1"
					        type="submit"><?php echo esc_html( $button_label ) ?></button>
				</form>
			<?php else : ?>
				<form method="post" action="<?php echo WP_FS__ADDRESS ?>/action/service/user/install/">
					<?php unset( $optin_params['sites']); ?>
					<?php foreach ( $optin_params as $name => $value ) : ?>
						<input type="hidden" name="<?php echo $name ?>" value="<?php echo esc_attr( $value ) ?>">
					<?php endforeach ?>
					<input type="hidden" name="is_extensions_tracking_allowed" value="1">
					<button class="button button-primary" tabindex="1"
					        type="submit"<?php if ( $require_license_key ) {
						echo ' disabled="disabled"';
					} ?>><?php echo esc_html( $button_label ) ?></button>
				</form>
			<?php endif ?>
            <?php if ( $require_license_key ) : ?>
                <a id="license_issues_link" href="<?php echo $fs->apply_filters( 'known_license_issues_url', 'https://freemius.com/help/documentation/wordpress-sdk/license-activation-issues/' ) ?>" target="_blank"><?php fs_esc_html_echo_inline( 'License issues?', 'license-issues', $slug ) ?></a>
            <?php endif ?>
		</div><?php

			// Set core permission list items.
			$permissions = array();

			/**
			 * When activating a license key the information of the admin is not collected, we gather the user info from the license.
			 *
			 * @since 2.3.2
			 * @author Vova Feldman
			 */
			if ( ! $require_license_key ) {
				$permissions['profile'] = array(
					'icon-class' => 'dashicons dashicons-admin-users',
					'label'      => $fs->get_text_inline( 'Your Profile Overview', 'permissions-profile' ),
					'desc'       => $fs->get_text_inline( 'Name and email address', 'permissions-profile_desc' ),
					'priority'   => 5,
				);
			}

            $permissions['site'] = array(
                'icon-class' => 'dashicons dashicons-admin-settings',
                'tooltip'    => ( $require_license_key ? sprintf( $fs->get_text_inline( 'So you can manage and control your license remotely from the User Dashboard.', 'permissions-site_tooltip' ), $fs->get_module_type() ) : '' ),
                'label'      => $fs->get_text_inline( 'Your Site Overview', 'permissions-site' ),
                'desc'       => $fs->get_text_inline( 'Site URL, WP version, PHP info', 'permissions-site_desc' ),
                'priority'   => 10,
            );

            if ( ! $require_license_key ) {
                $permissions['notices'] = array(
                    'icon-class' => 'dashicons dashicons-testimonial',
                    'label'      => $fs->get_text_inline( 'Admin Notices', 'permissions-admin-notices' ),
                    'desc'       => $fs->get_text_inline( 'Updates, announcements, marketing, no spam', 'permissions-newsletter_desc' ),
                    'priority'   => 13,
                );
            }

            $permissions['events'] = array(
                'icon-class' => 'dashicons dashicons-admin-' . ( $fs->is_plugin() ? 'plugins' : 'appearance' ),
                'tooltip'    => ( $require_license_key ? sprintf( $fs->get_text_inline( 'So you can reuse the license when the %s is no longer active.', 'permissions-events_tooltip' ), $fs->get_module_type() ) : '' ),
                'label'      => sprintf( $fs->get_text_inline( 'Current %s Status', 'permissions-events' ), ucfirst( $fs->get_module_type() ) ),
                'desc'       => $fs->get_text_inline( 'Active, deactivated, or uninstalled', 'permissions-events_desc' ),
                'priority'   => 20,
            );

			// Add newsletter permissions if enabled.
			if ( $is_gdpr_required || $fs->is_permission_requested( 'newsletter' ) ) {
				$permissions['newsletter'] = array(
					'icon-class' => 'dashicons dashicons-email-alt',
					'label'      => $fs->get_text_inline( 'Newsletter', 'permissions-newsletter' ),
					'desc'       => $fs->get_text_inline( 'Updates, announcements, marketing, no spam', 'permissions-newsletter_desc' ),
					'priority'   => 15,
				);
			}

            $permissions['extensions'] = array(
                'icon-class' => 'dashicons dashicons-menu',
                'label'      => $fs->get_text_inline( 'Plugins & Themes', 'permissions-extensions' ) . ( $require_license_key ? ' (' . $fs->get_text_inline( 'optional' ) . ')' : '' ),
                'tooltip'    => $fs->get_text_inline( 'To help us troubleshoot any potential issues that may arise from other plugin or theme conflicts.', 'permissions-events_tooltip' ),
                'desc'       => $fs->get_text_inline( 'Title, slug, version, and is active', 'permissions-extensions_desc' ),
                'priority'   => 25,
                'optional'   => true,
                'default'    => $fs->apply_filters( 'permission_extensions_default', ! $require_license_key )
            );

			// Allow filtering of the permissions list.
			$permissions = $fs->apply_filters( 'permission_list', $permissions );

			// Sort by priority.
			uasort( $permissions, 'fs_sort_by_priority' );

			if ( ! empty( $permissions ) ) : ?>
				<div class="fs-permissions">
					<?php if ( $require_license_key ) : ?>
						<p class="fs-license-sync-disclaimer"><?php
                                echo sprintf(
									fs_esc_html_inline( 'The %1$s will periodically send %2$s to %3$s for security & feature updates delivery, and license management.', 'license-sync-disclaimer', $slug ),
									$fs->get_module_label( true ),
									sprintf('<a class="fs-trigger" href="#" tabindex="1">%s</a>', fs_esc_html_inline('diagnostic data', 'send-data')),
									'<a class="fs-tooltip-trigger' . (is_rtl() ? ' rtl' : '') . '" href="' . $freemius_site_url . '" target="_blank" rel="noopener" tabindex="1">freemius.com <i class="dashicons dashicons-editor-help" style="text-decoration: none;"><span class="fs-tooltip" style="width: 170px">' . $fs->get_text_inline( 'Freemius is our licensing and software updates engine', 'permissions-extensions_desc' ) . '</span></i></a>'
								) ?></p>
					<?php else : ?>
					<a class="fs-trigger" href="#" tabindex="1"><?php fs_esc_html_echo_inline( 'What permissions are being granted?', 'what-permissions', $slug ) ?></a>
                    <?php endif ?>
					<ul><?php
							foreach ( $permissions as $id => $permission ) : ?>
								<li id="fs-permission-<?php echo esc_attr( $id ); ?>"
								    class="fs-permission fs-<?php echo esc_attr( $id ); ?>">
									<i class="<?php echo esc_attr( $permission['icon-class'] ); ?>"></i>
									<?php if ( isset( $permission['optional'] ) && true === $permission['optional'] ) : ?>
										<div class="fs-switch fs-small fs-round fs-<?php echo (! isset( $permission['default'] ) || true === $permission['default'] ) ?  'on' : 'off' ?>">
											<div class="fs-toggle"></div>
										</div>
									<?php endif ?>

									<div class="fs-permission-description">
										<span<?php if ( ! empty($permission['tooltip']) ) : ?> class="fs-tooltip-trigger"<?php endif ?>><?php echo esc_html( $permission['label'] ); ?><?php if ( ! empty($permission['tooltip']) ) : ?><i class="dashicons dashicons-editor-help"><span class="fs-tooltip" style="width: 200px"><?php echo $permission['tooltip'] ?></span></i><?php endif ?></span>

										<p><?php echo esc_html( $permission['desc'] ); ?></p>
									</div>
								</li>
							<?php endforeach; ?>
					</ul>
				</div>
			<?php endif ?>
		<?php if ( $is_premium_code && $is_freemium ) : ?>
			<div class="fs-freemium-licensing">
				<p>
					<?php if ( $require_license_key ) : ?>
						<?php fs_esc_html_echo_inline( 'Don\'t have a license key?', 'dont-have-license-key', $slug ) ?>
						<a data-require-license="false" tabindex="1"><?php fs_esc_html_echo_inline( 'Activate Free Version', 'activate-free-version', $slug ) ?></a>
					<?php else : ?>
						<?php fs_echo_inline( 'Have a license key?', 'have-license-key', $slug ) ?>
						<a data-require-license="true" tabindex="1"><?php fs_esc_html_echo_inline( 'Activate License', 'activate-license', $slug ) ?></a>
					<?php endif ?>
				</p>
			</div>
		<?php endif ?>
		<div class="fs-terms">
			<a href="https://freemius.com/privacy/" target="_blank" rel="noopener"
			   tabindex="1"><?php fs_esc_html_echo_inline( 'Privacy Policy', 'privacy-policy', $slug ) ?></a>
			&nbsp;&nbsp;-&nbsp;&nbsp;
			<a href="<?php echo $require_license_key ? $freemius_plugin_terms_url : $freemius_usage_tracking_url ?>" target="_blank" rel="noopener" tabindex="1"><?php $require_license_key ? fs_echo_inline( 'License Agreement', 'license-agreement', $slug ) : fs_echo_inline( 'Terms of Service', 'tos', $slug ) ?></a>
		</div>
	</div>
	<?php
		/**
		 * Allows developers to include custom HTML after the opt-in content.
		 *
		 * @author Vova Feldman
		 * @since 2.3.2
		 */
		$fs->do_action( 'connect/after' );

		if ( $is_optin_dialog ) { ?>
</div>
<?php
	}
?>
<script type="text/javascript">
	(function ($) {
		var $html = $('html');

		<?php
		if ( $is_optin_dialog ) {
		if ( $show_close_button ) { ?>
		var $themeConnectWrapper = $('#fs_theme_connect_wrapper');

		$themeConnectWrapper.find('button.close').on('click', function () {
			<?php if ( ! empty( $previous_theme_activation_url ) ) { ?>
			location.href = '<?php echo html_entity_decode( $previous_theme_activation_url ); ?>';
			<?php } else { ?>
			$themeConnectWrapper.remove();
			$html.css({overflow: $html.attr('fs-optin-overflow')});
			<?php } ?>
		});
		<?php
		}
		?>

		$html.attr('fs-optin-overflow', $html.css('overflow'));
		$html.css({overflow: 'hidden'});

		<?php
		}
		?>

		var $primaryCta          = $('.fs-actions .button.button-primary'),
            primaryCtaLabel      = $primaryCta.html(),
		    $form                = $('.fs-actions form'),
		    isNetworkActive      = <?php echo $is_network_level_activation ? 'true' : 'false' ?>,
		    requireLicenseKey    = <?php echo $require_license_key ? 'true' : 'false' ?>,
		    hasContextUser       = <?php echo $activate_with_current_user ? 'true' : 'false' ?>,
		    isNetworkUpgradeMode = <?php echo $is_network_upgrade_mode ? 'true' : 'false' ?>,
		    $licenseSecret,
		    $licenseKeyInput     = $('#fs_license_key'),
            pauseCtaLabelUpdate  = false,
            isNetworkDelegating  = false,
            /**
             * @author Leo Fajardo (@leorw)
             * @since 2.1.0
             */
            resetLoadingMode = function() {
                // Reset loading mode.
                $primaryCta.html(primaryCtaLabel);
                $primaryCta.prop('disabled', false);
                $(document.body).css({'cursor': 'auto'});
                $('.fs-loading').removeClass('fs-loading');

                console.log('resetLoadingMode - Primary button was enabled');
            },
			setLoadingMode = function () {
				$(document.body).css({'cursor': 'wait'});
			};

		$('.fs-actions .button').on('click', function () {
			setLoadingMode();

			var $this = $(this);

			setTimeout(function () {
			    if ( ! requireLicenseKey || ! $marketingOptin.hasClass( 'error' ) ) {
                    $this.attr('disabled', 'disabled');
                }
			}, 200);
		});

		if ( isNetworkActive ) {
			var
				$multisiteOptionsContainer  = $( '.fs-multisite-options-container' ),
				$allSitesOptions            = $( '.fs-all-sites-options' ),
				$applyOnAllSites            = $( '.fs-apply-on-all-sites-checkbox' ),
				$sitesListContainer         = $( '.fs-sites-list-container' ),
				totalSites                  = <?php echo count( $sites ) ?>,
				maxSitesListHeight          = null,
				$skipActivationButton       = $( '#skip_activation' ),
				$delegateToSiteAdminsButton = $( '#delegate_to_site_admins' ),
                hasAnyInstall               = <?php echo ! is_null( $fs->find_first_install() ) ? 'true' : 'false' ?>;

			$applyOnAllSites.click(function() {
				var isChecked = $( this ).is( ':checked' );

				if ( isChecked ) {
					$multisiteOptionsContainer.find( '.action' ).removeClass( 'selected' );
					updatePrimaryCtaText( 'allow' );
				}

				$multisiteOptionsContainer.find( '.action-allow' ).addClass( 'selected' );

				$skipActivationButton.toggle();

				$delegateToSiteAdminsButton.toggle();

				$multisiteOptionsContainer.toggleClass( 'fs-apply-on-all-sites', isChecked );

				$sitesListContainer.toggle( ! isChecked );
				if ( ! isChecked && null === maxSitesListHeight ) {
					/**
					 * Set the visible number of rows to 5 (5 * height of the first row).
					 *
					 * @author Leo Fajardo (@leorw)
					 */
					maxSitesListHeight = ( 5 * $sitesListContainer.find( 'tr:first' ).height() );
					$sitesListContainer.css( 'max-height', maxSitesListHeight );
				}
			});

			$allSitesOptions.find( '.action' ).click(function( evt ) {
				var actionType = $( evt.target ).data( 'action-type' );

				$multisiteOptionsContainer.find( '.action' ).removeClass( 'selected' );
				$multisiteOptionsContainer.find( '.action-' + actionType ).toggleClass( 'selected' );

				updatePrimaryCtaText( actionType );
			});

			$sitesListContainer.delegate( '.action', 'click', function( evt ) {
				var $this = $( evt.target );
				if ( $this.hasClass( 'selected' ) ) {
					return false;
				}

				$this.parents( 'tr:first' ).find( '.action' ).removeClass( 'selected' );
				$this.toggleClass( 'selected' );

				var
					singleSiteActionType = $this.data( 'action-type' ),
					totalSelected        = $sitesListContainer.find( '.action-' + singleSiteActionType + '.selected' ).length;

				$allSitesOptions.find( '.action.selected' ).removeClass( 'selected' );

				if ( totalSelected === totalSites ) {
					$allSitesOptions.find( '.action-' + singleSiteActionType ).addClass( 'selected' );

					updatePrimaryCtaText( singleSiteActionType );
				} else {
					updatePrimaryCtaText( 'mixed' );
				}
			});

            if ( isNetworkUpgradeMode || hasAnyInstall ) {
                $skipActivationButton.click(function(){
                    $delegateToSiteAdminsButton.hide();

                    $skipActivationButton.html('<?php fs_esc_js_echo_inline( 'Skipping, please wait', 'skipping-wait', $slug ) ?>...');

                    pauseCtaLabelUpdate = true;

                    // Check all sites to be skipped.
                    $allSitesOptions.find('.action.action-skip').click();

                    $form.submit();

                    pauseCtaLabelUpdate = false;

                    return false;
                });

                $delegateToSiteAdminsButton.click(function(){
                    $delegateToSiteAdminsButton.html('<?php fs_esc_js_echo_inline( 'Delegating, please wait', 'delegating-wait', $slug ) ?>...');

                    pauseCtaLabelUpdate = true;

                    /**
                     * Set to true so that the form submission handler can differentiate delegation from license
                     * activation and the proper AJAX action will be used (when delegating, the action should be
                     * `network_activate` and not `activate_license`).
                     *
                     * @author Leo Fajardo (@leorw)
                     * @since 2.3.0
                     */
                    isNetworkDelegating = true;

                    // Check all sites to be skipped.
                    $allSitesOptions.find('.action.action-delegate').click();

                    $form.submit();

                    pauseCtaLabelUpdate = false;

                    /**
                     * Set to false so that in case the previous AJAX request has failed, the form submission handler
                     * can differentiate license activation from delegation and the proper AJAX action will be used
                     * (when activating a license, the action should be `activate_license` and not `network_activate`).
                     *
                     * @author Leo Fajardo (@leorw)
                     * @since 2.3.0
                     */
                    isNetworkDelegating = false;

                    return false;
                });
            }
		}

		/**
		 * @author Leo Fajardo (@leorw)
		 */
		function updatePrimaryCtaText( actionType ) {
            if (pauseCtaLabelUpdate)
                return;

			var text = '<?php fs_esc_js_echo_inline( 'Continue', 'continue', $slug ) ?>';

			switch ( actionType ) {
				case 'allow':
					text = '<?php fs_esc_js_echo_inline( 'Allow & Continue', 'opt-in-connect', $slug ) ?>';
					break;
				case 'delegate':
					text = '<?php fs_esc_js_echo_inline( 'Delegate to Site Admins & Continue', 'delegate-to-site-admins-and-continue', $slug ) ?>';
					break;
				case 'skip':
					text = '<?php fs_esc_js_echo_x_inline( 'Skip', 'verb', 'skip', $slug ) ?>';
					break;
			}

			$primaryCta.html( text );
		}

		var ajaxOptin = ( requireLicenseKey || isNetworkActive );

		$form.on('submit', function () {
            var $extensionsPermission = $('#fs-permission-extensions .fs-switch'),
                isExtensionsTrackingAllowed = ($extensionsPermission.length > 0) ?
                    $extensionsPermission.hasClass('fs-on') :
                    null;

            if (null === isExtensionsTrackingAllowed) {
                $('input[name=is_extensions_tracking_allowed]').remove();
            } else {
                $('input[name=is_extensions_tracking_allowed]').val(isExtensionsTrackingAllowed ? 1 : 0);
            }

			/**
			 * @author Vova Feldman (@svovaf)
			 * @since 1.1.9
			 */
			if ( ajaxOptin ) {
				if (!hasContextUser || isNetworkUpgradeMode) {
				    var action   = null,
                        security = null;

				    if ( requireLicenseKey && ! isNetworkDelegating ) {
                        action   = '<?php echo $fs->get_ajax_action( 'activate_license' ) ?>';
                        security = '<?php echo $fs->get_ajax_security( 'activate_license' ) ?>';
                    } else {
                        action   = '<?php echo $fs->get_ajax_action( 'network_activate' ) ?>';
                        security = '<?php echo $fs->get_ajax_security( 'network_activate' ) ?>';
                    }

					$('.fs-error').remove();

					var
                        licenseKey = $licenseKeyInput.val(),
                        data       = {
                            action     : action,
                            security   : security,
                            license_key: licenseKey,
                            module_id  : '<?php echo $fs->get_id() ?>'
                        };

					if (
                        requireLicenseKey &&
                        ! isNetworkDelegating &&
                        isMarketingAllowedByLicense.hasOwnProperty(licenseKey)
                    ) {
                        var
                            isMarketingAllowed = null,
                            $isMarketingAllowed   = $marketingOptin.find( 'input[type="radio"][name="allow-marketing"]:checked');


                        if ($isMarketingAllowed.length > 0)
                            isMarketingAllowed = ('true' == $isMarketingAllowed.val());

                        if ( null == isMarketingAllowedByLicense[ licenseKey ] &&
                            null == isMarketingAllowed
                        ) {
                            $marketingOptin.addClass( 'error' ).show();
                            resetLoadingMode();
                            return false;
                        } else if ( null == isMarketingAllowed ) {
                            isMarketingAllowed = isMarketingAllowedByLicense[ licenseKey ];
                        }

                        data.is_marketing_allowed = isMarketingAllowed;

						data.is_extensions_tracking_allowed = isExtensionsTrackingAllowed;
                    }

                    $marketingOptin.removeClass( 'error' );

					if ( isNetworkActive ) {
						var
							sites           = [],
							applyOnAllSites = $applyOnAllSites.is( ':checked' );

						$sitesListContainer.find( 'tr' ).each(function() {
							var
								$this       = $( this ),
								includeSite = ( ! requireLicenseKey || applyOnAllSites || $this.find( 'input' ).is( ':checked' ) );

							if ( ! includeSite )
								return;

							var site = {
								uid     : $this.find( '.uid' ).val(),
								url     : $this.find( '.url' ).val(),
								title   : $this.find( '.title' ).val(),
								language: $this.find( '.language' ).val(),
								charset : $this.find( '.charset' ).val(),
								blog_id : $this.find( '.blog-id' ).find( 'span' ).text()
							};

							if ( ! requireLicenseKey) {
                                site.action = $this.find('.action.selected').data('action-type');
                            } else if ( isNetworkDelegating ) {
							    site.action = 'delegate';
                            }

							sites.push( site );
						});

						data.sites = sites;

						if ( hasAnyInstall ) {
						    data.has_any_install = hasAnyInstall;
                        }
					}

					/**
					 * Use the AJAX opt-in when license key is required to potentially
					 * process the after install failure hook.
					 *
					 * @author Vova Feldman (@svovaf)
					 * @since 1.2.1.5
					 */
					$.ajax({
						url    : ajaxurl,
						method : 'POST',
						data   : data,
						success: function (result) {
							var resultObj = $.parseJSON(result);
							if (resultObj.success) {
								// Redirect to the "Account" page and sync the license.
								window.location.href = resultObj.next_page;
							} else {
								resetLoadingMode();

								// Show error.
								$('.fs-content').prepend('<p class="fs-error">' + (resultObj.error.message ?  resultObj.error.message : resultObj.error) + '</p>');
							}
						},
						error: function () {
							resetLoadingMode();
						}
					});

					return false;
				}
				else {
					if (null == $licenseSecret) {
						$licenseSecret = $('<input type="hidden" name="license_secret_key" value="" />');
						$form.append($licenseSecret);
					}

					// Update secret key if premium only plugin.
					$licenseSecret.val($licenseKeyInput.val());
				}
			}

			return true;
		});

		$primaryCta.on('click', function () {
			console.log('Primary button was clicked');

			$(this).addClass('fs-loading');
			$(this).html('<?php echo esc_js( $is_pending_activation ?
				fs_text_x_inline( 'Sending email', 'as in the process of sending an email', 'sending-email', $slug ) :
				fs_text_x_inline( 'Activating', 'as activating plugin', 'activating', $slug )
			) ?>...');
		});

		$('.fs-permissions .fs-trigger').on('click', function () {
			$('.fs-permissions').toggleClass('fs-open');

			return false;
		});

		$( '.fs-switch' ).click( function () {
			$(this)
				.toggleClass( 'fs-on' )
				.toggleClass( 'fs-off' );
		});

		if (requireLicenseKey) {
			/**
			 * Submit license key on enter.
			 *
			 * @author Vova Feldman (@svovaf)
			 * @since 1.1.9
			 */
			$licenseKeyInput.keypress(function (e) {
				if (e.which == 13) {
					if ('' !== $(this).val()) {
						$primaryCta.click();
						return false;
					}
				}
			});

			/**
			 * Disable activation button when empty license key.
			 *
			 * @author Vova Feldman (@svovaf)
			 * @since 1.1.9
			 */
			$licenseKeyInput.on('keyup paste delete cut', function () {
				setTimeout(function () {
                    var key = $licenseKeyInput.val();

                    if (key == previousLicenseKey){
                        return;
                    }

					if ('' === key) {
						$primaryCta.attr('disabled', 'disabled');
                        $marketingOptin.hide();
					} else {
                        $primaryCta.prop('disabled', false);

                        if (32 <= key.length){
                            fetchIsMarketingAllowedFlagAndToggleOptin();
                        } else {
                            $marketingOptin.hide();
                        }
					}

                    previousLicenseKey = key;
				}, 100);
			}).focus();
		}

		/**
		 * Set license mode trigger URL.
		 *
		 * @author Vova Feldman (@svovaf)
		 * @since 1.1.9
		 */
		var
			$connectLicenseModeTrigger = $('#fs_connect .fs-freemium-licensing a'),
			href                       = window.location.href;

		if (href.indexOf('?') > 0) {
			href += '&';
		} else {
			href += '?';
		}

		if ($connectLicenseModeTrigger.length > 0) {
			$connectLicenseModeTrigger.attr(
				'href',
				href + 'require_license=' + $connectLicenseModeTrigger.attr('data-require-license')
			);
		}

		//--------------------------------------------------------------------------------
		//region GDPR
		//--------------------------------------------------------------------------------
        var isMarketingAllowedByLicense = {},
            $marketingOptin = $('#fs_marketing_optin'),
            previousLicenseKey = null;

		if (requireLicenseKey) {

			    var
                    afterMarketingFlagLoaded = function () {
                        var licenseKey = $licenseKeyInput.val();

                        if (null == isMarketingAllowedByLicense[licenseKey]) {
                            $marketingOptin.show();

                            if ($marketingOptin.find('input[type=radio]:checked').length > 0){
                                // Focus on button if GDPR opt-in already selected is already selected.
                                $primaryCta.focus();
                            } else {
                                // Focus on the GDPR opt-in radio button.
                                $($marketingOptin.find('input[type=radio]')[0]).focus();
                            }
                        } else {
                            $marketingOptin.hide();
                            $primaryCta.focus();
                        }
                    },
                    /**
                     * @author Leo Fajardo (@leorw)
                     * @since 2.1.0
                     */
                    fetchIsMarketingAllowedFlagAndToggleOptin = function () {
                        var licenseKey = $licenseKeyInput.val();

                        if (licenseKey.length < 32) {
                            $marketingOptin.hide();
                            return;
                        }

                        if (isMarketingAllowedByLicense.hasOwnProperty(licenseKey)) {
                            afterMarketingFlagLoaded();
                            return;
                        }

                        $marketingOptin.hide();

                        setLoadingMode();

                        $primaryCta.addClass('fs-loading');
                        $primaryCta.attr('disabled', 'disabled');
                        $primaryCta.html('<?php fs_esc_js_echo_inline( 'Please wait', 'please-wait', $slug ) ?>...');

                        $.ajax({
                            url    : ajaxurl,
                            method : 'POST',
                            data   : {
                                action     : '<?php echo $fs->get_ajax_action( 'fetch_is_marketing_required_flag_value' ) ?>',
                                security   : '<?php echo $fs->get_ajax_security( 'fetch_is_marketing_required_flag_value' ) ?>',
                                license_key: licenseKey,
                                module_id  : '<?php echo $fs->get_id() ?>'
                            },
                            success: function (result) {
                                resetLoadingMode();

                                if (result.success) {
                                    result = result.data;

                                    // Cache result.
                                    isMarketingAllowedByLicense[licenseKey] = result.is_marketing_allowed;
                                }

                                afterMarketingFlagLoaded();
                            }
                        });
                    };

			$marketingOptin.find( 'input' ).click(function() {
				$marketingOptin.removeClass( 'error' );
			});
		}

		//endregion
	})(jQuery);
</script>