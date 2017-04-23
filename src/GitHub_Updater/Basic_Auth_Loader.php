<?php
/**
 * GitHub Updater
 *
 * @package   Fragen\GitHub_Updater
 * @author    Andy Fragen
 * @author    Gary Jones
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

namespace Fragen\GitHub_Updater;


class Basic_Auth_Loader extends API {

	/**
	 * Basic_Auth_Loader object.
	 *
	 * @var bool|\Fragen\GitHub_Updater\Basic_Auth_Loader
	 */
	private static $instance = false;

	/**
	 * The Basic_Auth_Loader object can be created/obtained via this
	 * method - this prevents potential duplicate loading.
	 *
	 * @return object $instance Basic_Auth_Loader
	 */
	public static function instance() {
		if ( false === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Load hooks for Bitbucket authentication headers.
	 */
	public function load_authentication_hooks() {
		add_filter( 'http_request_args', array( &$this, 'maybe_basic_authenticate_http' ), 5, 2 );
		add_filter( 'http_request_args', array( &$this, 'http_release_asset_auth' ), 15, 2 );
	}

	/**
	 * Remove hooks for Bitbucket authentication headers.
	 */
	public function remove_authentication_hooks() {
		remove_filter( 'http_request_args', array( &$this, 'maybe_basic_authenticate_http' ) );
		remove_filter( 'http_request_args', array( &$this, 'http_release_asset_auth' ) );
	}

}
