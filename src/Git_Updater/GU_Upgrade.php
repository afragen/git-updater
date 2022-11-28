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

use Fragen\Git_Updater\Traits\GU_Trait;

/**
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class GU_Upgrade
 */
class GU_Upgrade {
	use GU_Trait;

	/**
	 * DB version.
	 *
	 * @var int
	 */
	private $db_version = 8312; // TODO: change number.

	/**
	 * Run update check against db_version.
	 */
	public function run() {
		$options    = $this->get_class_vars( 'Base', 'options' );
		$db_version = isset( $options['db_version'] ) ? (int) $options['db_version'] : 6000;
		$this->schedule_access_token_cleanup();

		if ( $db_version === $this->db_version ) {
			return;
		}

		switch ( $db_version ) {
			case $db_version < $this->db_version:
				$this->delete_flush_cache();
				break;
			default:
				break;
		}

		$options = array_merge(
			(array) $options,
			[
				'db_version'     => (int) $this->db_version,
				'gu_auto_update' => '1',
			]
		);
		update_site_option( 'git_updater', $options );
	}

	/**
	 * Flush caches and delete cached options.
	 */
	private function delete_flush_cache() {
		wp_cache_flush();
		$this->delete_all_cached_data();
	}

	/**
	 * Convert GHU to GU options.
	 *
	 * @since 10.0.0
	 *
	 * @return void
	 */
	public function convert_ghu_options_to_gu_options() {
		$ghu_options = get_site_option( 'github_updater' );
		if ( $ghu_options ) {
			update_site_option( 'git_updater', $ghu_options );
			delete_site_option( 'github_updater' );
		}
		require_once ABSPATH . '/wp-admin/includes/plugin.php';
		\deactivate_plugins( 'github-updater/git-updater.php' );
	}

	/**
	 * Check for deletion of cron event.
	 *
	 * @param null|bool|\WP_Error $pre       Value to return instead. Default null to continue unscheduling the event.
	 * @param int                 $timestamp Timestamp for when to run the event.
	 * @param string              $hook      Action hook, the execution of which will be unscheduled.
	 *
	 * @return null|bool|\WP_Error
	 */
	public function pre_unschedule_event( $pre, $timestamp, $hook ) {
		if ( 'gu_delete_access_tokens' === $hook ) {
			$days = ( \wp_next_scheduled( 'gu_delete_access_tokens' ) - time() ) / \DAY_IN_SECONDS;
			if ( $days > 10 ) {
				$this->flush_tokens();
			}
		}
		return $pre;
	}

	/**
	 * Update for non-password options.
	 *
	 * @since 12.0.0
	 *
	 * @return void
	 */
	public function flush_tokens() {
		if ( gu_fs()->can_use_premium_code() || false === wp_next_scheduled( 'gu_delete_access_tokens' ) ) {
			return;
		}

		$options     = $this->get_class_vars( 'Base', 'options' );
		$new_options = \array_filter(
			$options,
			function( $value, $key ) use ( &$options ) {
				if ( 'db_version' === $key || str_contains( $key, 'current_branch' ) ) {
					return $options[ $key ];
				}
			},
			ARRAY_FILTER_USE_BOTH
		);
		update_site_option( 'git_updater', $new_options );
	}

	/**
	 * Schedule cleanup of the access tokens.
	 *
	 * @since 12.0.0
	 *
	 * @return void
	 */
	private function schedule_access_token_cleanup() {
		if ( false === wp_next_scheduled( 'gu_delete_access_tokens' ) ) {
			wp_schedule_event( time() + ( 2 * \WEEK_IN_SECONDS ), 'twicedaily', 'gu_delete_access_tokens' );
		}

		add_action( 'gu_delete_access_tokens', [ $this, 'flush_tokens' ] );
	}
}
