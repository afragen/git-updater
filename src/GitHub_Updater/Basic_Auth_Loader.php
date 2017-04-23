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

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Basic_Auth_Loader {

	/**
	 * Basic_Auth_Loader object.
	 *
	 * @var bool|object
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

	/**
	 * Add Basic Authentication $args to http_request_args filter hook
	 * for private repositories only.
	 *
	 * @uses $this->get_credentials()
	 *
	 * @param  mixed  $args
	 * @param  string $url
	 *
	 * @return mixed $args
	 */
	public function maybe_basic_authenticate_http( $args, $url ) {
		$credentials = $this->get_credentials( $url );

		if ( $credentials['private'] && $credentials['isset'] && ! $credentials['api.wordpress'] ) {
			$username = $credentials['username'];
			$password = $credentials['password'];

			$args['headers']['Authorization'] = 'Basic ' . base64_encode( "$username:$password" );
		}

		return $args;
	}

	/**
	 * Get credentials (username/password) for Basic Authentication.
	 *
	 * @uses $this->is_repo_private()
	 *
	 * @param string $url
	 *
	 * @return array $credentials
	 */
	private function get_credentials( $url ) {
		$headers      = parse_url( $url );
		$username_key = null;
		$password_key = null;
		$credentials  = array(
			'username'      => null,
			'password'      => null,
			'api.wordpress' => 'api.wordpress.org' === $headers['host'] ? true : false,
			'isset'         => false,
			'private'       => false,
		);

		switch ( self::$calling_object ) {
			case ( self::$calling_object instanceof Bitbucket_API ):
			case ( self::$calling_object instanceof Bitbucket_Server_API ):
				$bitbucket_org = 'bitbucket.org' === $headers['host'] ? true : false;
				$username_key  = $bitbucket_org ? 'bitbucket_username' : 'bitbucket_server_username';
				$password_key  = $bitbucket_org ? 'bitbucket_password' : 'bitbucket_server_password';
				break;
		}

		if ( isset( self::$options[ $username_key ], self::$options[ $password_key ] ) ) {
			$credentials['username'] = self::$options[ $username_key ];
			$credentials['password'] = self::$options[ $password_key ];
			$credentials['isset']    = true;
			$credentials['private']  = $this->is_repo_private( $url );
		}

		return $credentials;
	}

	/**
	 * Determine if repo is private.
	 *
	 * @param string $url
	 *
	 * @return bool true if private
	 */
	private function is_repo_private( $url ) {
		// Used when updating.
		$slug = isset( $_REQUEST['rollback'], $_REQUEST['plugin'] ) ? dirname( $_REQUEST['plugin'] ) : false;
		$slug = isset( $_REQUEST['rollback'], $_REQUEST['theme'] ) ? $_REQUEST['theme'] : $slug;
		$slug = isset( $_REQUEST['slug'] ) ? $_REQUEST['slug'] : $slug;

		if ( ( $slug && array_key_exists( $slug, self::$options ) &&
		       1 == self::$options[ $slug ] &&
		       false !== stristr( $url, $slug ) )
		) {
			return true;
		}

		// Used for remote install tab.
		if ( isset( $_POST['option_page'], $_POST['is_private'] ) &&
		     'github_updater_install' === $_POST['option_page']
		) {
			return true;
		}

		// Used for refreshing cache.
		foreach ( array_keys( self::$options ) as $option ) {
			if ( 1 == self::$options[ $option ] &&
			     false !== strpos( $url, $option )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Removes Basic Authentication header for Bitbucket Release Assets.
	 * Storage in AmazonS3 buckets, uses Query String Request Authentication Alternative.
	 *
	 * @link http://docs.aws.amazon.com/AmazonS3/latest/dev/RESTAuthentication.html#RESTAuthenticationQueryStringAuth
	 *
	 * @param mixed  $args
	 * @param string $url
	 *
	 * @return mixed $args
	 */
	public function http_release_asset_auth( $args, $url ) {
		$arrURL = parse_url( $url );
		if ( isset( $arrURL['host'] ) && 'bbuseruploads.s3.amazonaws.com' === $arrURL['host'] ) {
			unset( $args['headers']['Authorization'] );
		}

		return $args;
	}

}
