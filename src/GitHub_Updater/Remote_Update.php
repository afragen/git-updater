<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

namespace Fragen\GitHub_Updater;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Remote_Update
 * Compatibility class for remote update services.
 *
 * @package Fragen\GitHub_Updater
 */
class Remote_Update extends Base {

	/**
	 * Constructor.
	 */
	public function __construct() {

		switch ( true ) {
			case is_plugin_active( 'ithemes-sync/init.php' ): // iThemes Sync
				add_action( 'wp_ajax_nopriv_ithemes_sync_request', array( &$this, 'init' ), 15 );
				add_filter( 'github_updater_remote_update_request', array( __CLASS__, 'iThemes_Sync' ) );
				break;
			case is_plugin_active( 'worker/init.php' ): // ManageWP - Worker
				add_filter( 'github_updater_remote_update_request', array( __CLASS__, 'ManageWP' ) );
				break;
			case is_plugin_active( 'mainwp/mainwp.php' ): // MainWP
				add_filter( 'github_updater_remote_update_request', array( __CLASS__, 'MainWP' ) );
				break;
			case is_plugin_active( 'iwp-client/init.php' ): // InfiniteWP
				add_filter( 'github_updater_remote_update_request', array( __CLASS__, 'InfiniteWP' ) );
				break;
			case is_plugin_active( 'wpremote/plugin.php' ): // WP-Remote
				add_filter( 'github_updater_remote_update_request', array( __CLASS__, 'WP_Remote' ) );
				break;
		}

	}

	/**
	 * Correct $_GET for iThemes Sync.
	 *
	 * @param $get
	 *
	 * @return array|mixed|object
	 */
	public static function iThemes_Sync( $get ) {
		//set_site_transient( 'ghu_remote_service', 'iThemes Sync active', 9999 );
		//set_site_transient( 'ghu_it_request', $get, 9999 );
		//set_site_transient( 'ghu_it_request_post', $get, 9999 );
		if ( isset( $get['ithemes-sync-request'] ) ) {
			$get  = json_decode( stripslashes( $get['request'] ), true );
			$args = $get['arguments'];
			if ( isset( $args['plugin'] ) ) {
				$get['plugin'] = $args['plugin'];
			} elseif ( isset( $args['theme'] ) ) {
				$get['theme']  = $args['theme'];
			}
		}

		return $get;
	}

	public static function ManageWP( $get ) {
		set_site_transient( 'ghu_remote_service', 'ManageWP active', 9999 );
		return $get;
	}

	public static function MainWP( $get ) {
		set_site_transient( 'ghu_remote_service', 'MainWP active', 9999 );
		return $get;
	}

	public static function InfiniteWP( $get ) {
		set_site_transient( 'ghu_remote_service', 'InfiniteWP active', 9999 );
		return $get;
	}

	public static function WP_Remote( $get ) {
		set_site_transient( 'ghu_remote_service', 'WP-Remote active', 9999 );
		return $get;
	}

}
