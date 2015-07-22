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
			case is_plugin_active( 'iwp-client/init.php' ): // InfiniteWP
				add_filter( 'github_updater_remote_update_request', array( __CLASS__, 'InfiniteWP' ) );
				break;
			case is_plugin_active( 'worker/init.php' ): // ManageWP - Worker
				add_filter( 'github_updater_remote_update_request', array( __CLASS__, 'ManageWP' ) );
				break;
			case is_plugin_active( 'mainwp/mainwp.php' ): // MainWP
				add_filter( 'github_updater_remote_update_request', array( __CLASS__, 'MainWP' ) );
				break;
			case is_plugin_active( 'wpremote/plugin.php' ): // WP-Remote
				add_filter( 'github_updater_remote_update_request', array( __CLASS__, 'WP_Remote' ) );
				break;
		}

	}

	/**
	 * Correct $_REQUEST for iThemes Sync.
	 *
	 * @param $request
	 *
	 * @return array|mixed|object
	 */
	public static function iThemes_Sync( $request ) {
		if ( isset( $request['ithemes-sync-request'] ) ) {
			$request  = json_decode( stripslashes( $request['request'] ), true );
			$args = $request['arguments'];
			if ( isset( $args['plugin'] ) ) {
				$request['plugin'] = $args['plugin'];
			} elseif ( isset( $args['theme'] ) ) {
				$request['theme']  = $args['theme'];
			}
		}

		return $request;
	}

	/**
	 * $_REQUEST for InfiniteWP already correct. However, updating for child themes not functioning.
	 *
	 * @param $request
	 *
	 * @return mixed
	 */
	public static function InfiniteWP( $request ) {
		return $request;
	}

	public static function ManageWP( $request ) {
		set_site_transient( 'ghu_remote_service', 'ManageWP active', 3600 );
		return $request;
	}

	public static function MainWP( $request ) {
		set_site_transient( 'ghu_remote_service', 'MainWP active', 3600 );
		return $request;
	}

	public static function WP_Remote( $request ) {
		set_site_transient( 'ghu_remote_service', 'WP-Remote active', 3600 );
		return $request;
	}

}
