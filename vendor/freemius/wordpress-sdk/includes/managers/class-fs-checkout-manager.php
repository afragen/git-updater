<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2024, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       2.9.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	class FS_Checkout_Manager {

		# region Singleton

		/**
		 * @var FS_Checkout_Manager
		 */
		private static $_instance;

		/**
		 * @return FS_Checkout_Manager
		 */
		static function instance() {
			if ( ! isset( self::$_instance ) ) {
				self::$_instance = new FS_Checkout_Manager();
			}

			return self::$_instance;
		}

		private function __construct() {
		}

		#endregion

		/**
		 * Retrieves the query params needed to load the Freemius Checkout in the context of the plugin.
		 *
		 * @param Freemius $fs
		 * @param number   $plugin_id
		 * @param number   $plan_id
		 * @param number   $licenses
		 *
		 * @return array
		 */
		public function get_query_params( Freemius $fs, $plugin_id, $plan_id, $licenses ) {
			$timestamp = time();

			$context_params = array(
				'plugin_id'      => $fs->get_id(),
				'public_key'     => $fs->get_public_key(),
				'plugin_version' => $fs->get_plugin_version(),
				'mode'           => 'dashboard',
				'trial'          => fs_request_get_bool( 'trial' ),
				'is_ms'          => ( fs_is_network_admin() && $fs->is_network_active() ),
			);

			if ( FS_Plugin_Plan::is_valid_id( $plan_id ) ) {
				$context_params['plan_id'] = $plan_id;
			}

			if ( $licenses === strval( intval( $licenses ) ) && $licenses > 0 ) {
				$context_params['licenses'] = $licenses;
			}

			if ( $plugin_id == $fs->get_id() ) {
				$is_premium = $fs->is_premium();

				$bundle_id = $fs->get_bundle_id();
				if ( ! is_null( $bundle_id ) ) {
					$context_params['bundle_id'] = $bundle_id;
				}
			} else {
				// Identify the module code version of the checkout context module.
				if ( $fs->is_addon_activated( $plugin_id ) ) {
					$fs_addon   = Freemius::get_instance_by_id( $plugin_id );
					$is_premium = $fs_addon->is_premium();
				} else {
					// If add-on isn't activated assume the premium version isn't installed.
					$is_premium = false;
				}
			}

			// Get site context secure params.
			if ( $fs->is_registered() ) {
				$site = $fs->get_site();

				if ( $plugin_id != $fs->get_id() ) {
					if ( $fs->is_addon_activated( $plugin_id ) ) {
						$fs_addon   = Freemius::get_instance_by_id( $plugin_id );
						$addon_site = $fs_addon->get_site();
						if ( is_object( $addon_site ) ) {
							$site = $addon_site;
						}
					}
				}

				$context_params = array_merge(
					$context_params,
					FS_Security::instance()->get_context_params(
						$site,
						$timestamp,
						'checkout'
					)
				);
			} else {
				$current_user = Freemius::_get_current_wp_user();

				// Add site and user info to the request, this information
				// is NOT being stored unless the user complete the purchase
				// and agrees to the TOS.
				$context_params = array_merge( $context_params, array(
					'user_firstname' => $current_user->user_firstname,
					'user_lastname'  => $current_user->user_lastname,
					'user_email'     => $current_user->user_email,
					'home_url'       => home_url(),
				) );

				$fs_user = Freemius::_get_user_by_email( $current_user->user_email );

				if ( is_object( $fs_user ) && $fs_user->is_verified() ) {
					$context_params = array_merge(
						$context_params,
						FS_Security::instance()->get_context_params(
							$fs_user,
							$timestamp,
							'checkout'
						)
					);
				}
			}

			if ( $fs->is_payments_sandbox() ) {
				// Append plugin secure token for sandbox mode authentication.
				$context_params['sandbox'] = FS_Security::instance()->get_secure_token(
					$fs->get_plugin(),
					$timestamp,
					'checkout'
				);

				/**
				 * @since 1.1.7.3 Add security timestamp for sandbox even for anonymous user.
				 */
				if ( empty( $context_params['s_ctx_ts'] ) ) {
					$context_params['s_ctx_ts'] = $timestamp;
				}
			}

			$can_user_install = (
				( $fs->is_plugin() && current_user_can( 'install_plugins' ) ) ||
				( $fs->is_theme() && current_user_can( 'install_themes' ) )
			);

			return array_merge( $context_params, $_GET, array(
				// Current plugin version.
				'plugin_version' => $fs->get_plugin_version(),
				'sdk_version'    => WP_FS__SDK_VERSION,
				'is_premium'     => $is_premium ? 'true' : 'false',
				'can_install'    => $can_user_install ? 'true' : 'false',
			) );
		}

		/**
		 * The return URL to pass to the checkout when the checkout is loaded in "redirect" mode.
		 *
		 * @param Freemius $fs
		 *
		 * @return string
		 */
		public function get_checkout_redirect_return_url( Freemius $fs ) {
			$request_url = remove_query_arg( '_wp_http_referer' );

			return fs_nonce_url(
				$fs->checkout_url(
					fs_request_get( 'billing_cycle' ),
					fs_request_get_bool( 'trial' ),
					array(
						'process_redirect' => 'true',
						'_wp_http_referer' => $request_url,
					)
				),
				$this->get_checkout_redirect_nonce_action( $fs )
			);
		}

		/**
		 * @param array  $query_params
		 * @param string $base_url
		 *
		 * @return string
		 */
		public function get_full_checkout_url( array $query_params, $base_url = FS_CHECKOUT__ADDRESS ) {
			return $base_url . '/?' . http_build_query( $query_params );
		}

		/**
		 * Verifies the redirect after a checkout with the nonce.
		 *
		 * @param Freemius $fs
		 */
		public function verify_checkout_redirect_nonce( Freemius $fs ) {
			check_admin_referer( $this->get_checkout_redirect_nonce_action( $fs ) );
		}

		/**
		 * Get the URL to process a new install after the checkout.
		 *
		 * @param Freemius $fs
		 * @param number   $plugin_id
		 *
		 * @return string
		 */
		public function get_install_url( Freemius $fs, $plugin_id ) {
			return fs_nonce_url( $fs->_get_admin_page_url( 'account', array(
				'fs_action' => $fs->get_unique_affix() . '_activate_new',
				'plugin_id' => $plugin_id,
			) ), $fs->get_unique_affix() . '_activate_new' );
		}

		/**
		 * Get the URL to process a pending activation after the checkout.
		 *
		 * @param Freemius $fs
		 * @param number   $plugin_id
		 *
		 * @return string
		 */
		public function get_pending_activation_url( Freemius $fs, $plugin_id ) {
			return fs_nonce_url( $fs->_get_admin_page_url( 'account', array(
				'fs_action'           => $fs->get_unique_affix() . '_activate_new',
				'plugin_id'           => $plugin_id,
				'pending_activation'  => true,
				'has_upgrade_context' => true,
			) ), $fs->get_unique_affix() . '_activate_new' );
		}

		private function get_checkout_redirect_nonce_action( Freemius $fs ) {
			return $fs->get_unique_affix() . '_checkout_redirect';
		}
	}