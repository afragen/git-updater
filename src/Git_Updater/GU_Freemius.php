<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Freemius integration.
 * Freemius 'start.php' autoloaded via composer.
 */
class GU_Freemius {

	/**
	 * Freemius integration.
	 *
	 * @return array|void
	 */
	public function init() {
		if ( ! function_exists( 'gu_fs' ) ) {

			/**
			 * Create a helper function for easy SDK access.
			 *
			 * @return \stdClass
			 */
			function gu_fs() {
				global $gu_fs;

				if ( ! isset( $gu_fs ) ) {
					// Activate multisite network integration.
					if ( ! defined( 'WP_FS__PRODUCT_11525_MULTISITE' ) ) {
						define( 'WP_FS__PRODUCT_11525_MULTISITE', true );
					}

					// Init Freemius SDK.
					require_once dirname( __DIR__, 2 ) . '/vendor/freemius/wordpress-sdk/start.php';

					$gu_fs = fs_dynamic_init(
						[
							'id'                  => '11525',
							'slug'                => 'git-updater',
							'premium_slug'        => 'git-updater',
							'type'                => 'plugin',
							'public_key'          => 'pk_aaa04d83b4c42470937266f9b4fca',
							'is_premium'          => true,
							'premium_suffix'      => '',
							// If your plugin is a serviceware, set this option to false.
							'has_premium_version' => true,
							'has_addons'          => false,
							'has_paid_plans'      => true,
							'is_org_compliant'    => false,
							'trial'               => [
								'days'               => 14,
								'is_require_payment' => true,
							],
							'menu'                => [
								'slug'    => 'git-updater',
								'support' => false,
								'network' => true,
								'parent'  => [
									'slug' => is_multisite() ? 'settings.php' : 'options-general.php',
								],
							],
						]
					);
				}

				return $gu_fs;
			}

			// Init Freemius.
			gu_fs();
			// Signal that SDK was initiated.
			do_action( 'gu_fs_loaded' );
		}

		global $gu_fs;
		$gu_fs->add_filter( 'plugin_icon', [ $this, 'add_icon' ] );
		$gu_fs->add_filter( 'is_submenu_visible', [ $this, 'is_submenu_visible' ], 10, 2 );
		$gu_fs->add_filter( 'permission_list', [ $this, 'permission_list' ] );
		$gu_fs->add_action( 'after_uninstall', [ $this, 'uninstall_cleanup' ] );
		// $gu_fs->add_filter( 'show_deactivation_feedback_form', '__return_false' );

		// Hide all Freemius menus with filter.
		if ( (bool) apply_filters( 'gu_hide_settings', false ) ) {
			$gu_fs->add_filter( 'is_submenu_visible', '__return_false' );
		}

		$this->remove_fs_plugin_updater_hooks( $gu_fs );

		// Hopefully eliminate clone resolution popup as single license for unlimited sites.
		define( 'FS__RESOLVE_CLONE_AS', 'long_term_duplicate' );
	}

	/**
	 * Add custom plugin icon to update notice.
	 *
	 * @return string
	 */
	public function add_icon() {
		return dirname( __DIR__, 2 ) . '/assets/icon.svg';
	}

	/**
	 * Show the contact submenu item only when the user have a valid non-expired license.
	 *
	 * @param bool   $is_visible The filtered value. Whether the submenu item should be visible or not.
	 * @param string $menu_id    The ID of the submenu item.
	 *
	 * @return bool If true, the menu item should be visible.
	 */
	public function is_submenu_visible( $is_visible, $menu_id ) {
		if ( 'contact' !== $menu_id ) {
			return $is_visible;
		}

		return gu_fs()->can_use_premium_code();
	}

	/**
	 * Set extensions default to true.
	 *
	 * @param array $permissions Array of opt-in permissions.
	 *
	 * @return array
	 */
	public function permission_list( $permissions ) {
		foreach ( $permissions as $key => $permission ) {
			if ( 'extensions' === $permission['id'] ) {
				$permissions[ $key ]['default'] = true;
			}
		}

		return $permissions;
	}

	/**
	 * Uninstall.
	 *
	 * @return void
	 */
	public function uninstall_cleanup() {
		require_once dirname( __DIR__, 2 ) . '/gu-uninstall.php';
	}

	/**
	 * Remove FS_Plugin_Updater hooks.
	 * Allow Git Updater to use it's own update code for itself.
	 *
	 * @param \Freemius $gu_fs Freemius object.
	 *
	 * @return void
	 */
	public function remove_fs_plugin_updater_hooks( \Freemius $gu_fs ) {
		$FS_Plugin_Updater = \FS_Plugin_Updater::instance( $gu_fs );
		$plugin_name       = 'git-updater/git-updater.php';

		// Bypass Freemius update-core.php dialog.
		remove_filter( 'admin_init', [ $gu_fs, '_add_premium_version_upgrade_selection' ] );

		// Remove Freemius View details modifications.
		remove_filter( 'plugins_api', [ $FS_Plugin_Updater, 'plugins_api_filter' ], 10 );
		remove_action( 'admin_head', [ $FS_Plugin_Updater, 'catch_plugin_information_dialog_contents' ] );

		// Remove Freemius plugin row modifications.
		remove_action(
			"after_plugin_row_{$plugin_name}",
			[
				$FS_Plugin_Updater,
				'catch_plugin_update_row',
			],
			9
		);
		remove_action(
			"after_plugin_row_{$plugin_name}",
			[
				$FS_Plugin_Updater,
				'edit_and_echo_plugin_update_row',
			],
			11
		);

		// Remove Freemius 'update_plugins' transient filter.
		remove_filter(
			'pre_set_site_transient_update_plugins',
			[
				$FS_Plugin_Updater,
				'pre_set_site_transient_update_plugins_filter',
			]
		);
	}
}
