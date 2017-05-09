<?php
/**
 * GitHub Updater
 *
 * @package   Fragen\GitHub_Updater
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
 * Class Basic_Auth_Loader
 *
 * @package Fragen\GitHub_Updater
 */
class Basic_Auth_Loader {

	/**
	 * Stores Basic::$options.
	 *
	 * @var mixed
	 */
	private static $options;

	/**
	 * Stores the object calling Basic_Auth_Loader.
	 *
	 * @var
	 */
	private static $object;

	/**
	 * Basic_Auth_Loader object.
	 *
	 * @var bool|object
	 */
	private static $instance = false;

	/**
	 * Basic_Auth_Loader constructor.
	 *
	 * @param array $options
	 */
	public function __construct( $options ) {
		self::$options = empty( $options )
			? get_site_option( 'github_updater', array() )
			: $options;
	}

	/**
	 * The Basic_Auth_Loader object can be created/obtained via this
	 * method - this prevents potential duplicate loading.
	 *
	 * @param array $options
	 *
	 * @return object $instance Basic_Auth_Loader
	 */
	public static function instance( $options ) {
		if ( false === self::$instance ) {
			self::$instance = new static( $options );
			$backtrace      = debug_backtrace();
			self::$object   = $backtrace[1]['object'];
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
		$type         = self::$object;
		$username_key = null;
		$password_key = null;
		$credentials  = array(
			'username'      => null,
			'password'      => null,
			'api.wordpress' => 'api.wordpress.org' === $headers['host'] ? true : false,
			'isset'         => false,
			'private'       => false,
		);

		$slug  = isset( $_REQUEST['plugin'] ) ? dirname( $_REQUEST['plugin'] ) : false;
		$slug  = isset( $_REQUEST['theme'] ) ? $_REQUEST['theme'] : $slug;
		$slug  = isset( $_REQUEST['slug'] ) ? $_REQUEST['slug'] : $slug;
		$repos = isset( $_REQUEST )
			? array_merge(
				Plugin::instance()->get_plugin_configs(),
				Theme::instance()->get_theme_configs()
			)
			: false;
		$type  = $repos && $slug ? $repos[ $slug ]->type : $type;

		switch ( $type ) {
			case ( 'bitbucket_plugin' ):
			case ( 'bitbucket_theme' ):
			case ( $type instanceof Bitbucket_API ):
			case ( $type instanceof Bitbucket_Server_API ):
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
