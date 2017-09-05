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
	 * @access private
	 * @var    mixed
	 */
	private static $options;

	/**
	 * Stores array of git servers requiring Basic Authentication.
	 *
	 * @var array
	 */
	private static $basic_auth_required = array( 'Bitbucket' );

	/**
	 * Stores the object calling Basic_Auth_Loader.
	 *
	 * @access public
	 * @var    \stdClass
	 */
	public $caller;

	/**
	 * Basic_Auth_Loader constructor.
	 *
	 * @access public
	 *
	 * @param array $options Options to pass to the updater.
	 */
	public function __construct( $options ) {
		self::$options = empty( $options )
			? get_site_option( 'github_updater', array() )
			: $options;
	}

	/**
	 * Load hooks for Bitbucket authentication headers.
	 *
	 * @access public
	 */
	public function load_authentication_hooks() {
		add_filter( 'http_request_args', array( &$this, 'maybe_basic_authenticate_http' ), 5, 2 );
		add_filter( 'http_request_args', array( &$this, 'http_release_asset_auth' ), 15, 2 );
	}

	/**
	 * Remove hooks for Bitbucket authentication headers.
	 *
	 * @access public
	 */
	public function remove_authentication_hooks() {
		remove_filter( 'http_request_args', array( &$this, 'maybe_basic_authenticate_http' ) );
		remove_filter( 'http_request_args', array( &$this, 'http_release_asset_auth' ) );
	}

	/**
	 * Add Basic Authentication $args to http_request_args filter hook
	 * for private repositories only.
	 *
	 * @access public
	 * @uses   \Fragen\GitHub_Updater\Basic_Auth_Loader::get_credentials()
	 *
	 * @param  array  $args Args passed to the URL.
	 * @param  string $url  The URL.
	 *
	 * @return array $args
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
	 * @access public
	 * @uses   \Fragen\GitHub_Updater\Basic_Auth_Loader::is_repo_private()
	 *
	 * @param string $url The URL.
	 *
	 * @return array $credentials
	 */
	private function get_credentials( $url ) {
		$headers      = parse_url( $url );
		$type         = $this->caller;
		$username_key = null;
		$password_key = null;
		$credentials  = array(
			'username'      => null,
			'password'      => null,
			'api.wordpress' => 'api.wordpress.org' === $headers['host'],
			'isset'         => false,
			'private'       => false,
		);

		$slug = isset( $_REQUEST['slug'] ) ? $_REQUEST['slug'] : false;
		$slug = ! $slug && isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : $slug;
		$slug = ! $slug && isset( $_REQUEST['theme'] ) ? $_REQUEST['theme'] : $slug;

		// Set for bulk upgrade.
		if ( ! $slug ) {
			$plugins     = isset( $_REQUEST['plugins'] )
				? array_map( 'dirname', explode( ',', $_REQUEST['plugins'] ) )
				: array();
			$themes      = isset( $_REQUEST['themes'] )
				? explode( ',', $_REQUEST['themes'] )
				: array();
			$bulk_update = array_merge( $plugins, $themes );
			if ( ! empty( $bulk_update ) ) {
				$slug = array_filter( $bulk_update, function( $e ) use ( $url ) {
					return false !== strpos( $url, $e );
				} );
				$slug = array_pop( $slug );
			}
		}

		// Set for Remote Install.
		$type = isset( $_POST['github_updater_api'], $_POST['github_updater_repo'] ) &&
		        false !== strpos( $url, basename( $_POST['github_updater_repo'] ) )
			? $_POST['github_updater_api'] . '_install'
			: $type;

		$repos = null !== $_REQUEST
			? array_merge(
				Singleton::get_instance( 'Plugin' )->get_plugin_configs(),
				Singleton::get_instance( 'Theme' )->get_theme_configs()
			)
			: false;
		$type  = $slug && $repos &&
		         isset( $repos[ $slug ] ) && property_exists( $repos[ $slug ], 'type' )
			? $repos[ $slug ]->type
			: $type;

		switch ( $type ) {
			case ( 'bitbucket_plugin' ):
			case ( 'bitbucket_theme' ):
			case ( 'bitbucket_install' ):
			case ( $type instanceof Bitbucket_API ):
			case ( $type instanceof Bitbucket_Server_API ):
				$bitbucket_org = 'bitbucket.org' === $headers['host'];
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
	 * @access private
	 *
	 * @param string $url The URL.
	 *
	 * @return bool true if private
	 */
	private function is_repo_private( $url ) {
		// Used when updating.
		$slug = isset( $_REQUEST['rollback'], $_REQUEST['plugin'] ) ? dirname( $_REQUEST['plugin'] ) : false;
		$slug = isset( $_REQUEST['rollback'], $_REQUEST['theme'] ) ? $_REQUEST['theme'] : $slug;
		$slug = isset( $_REQUEST['slug'] ) ? $_REQUEST['slug'] : $slug;

		if ( $slug && array_key_exists( $slug, self::$options ) &&
		     1 === (int) self::$options[ $slug ] &&
		     false !== stripos( $url, $slug )
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
			if ( 1 === (int) self::$options[ $option ] &&
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
	 * @access public
	 * @link   http://docs.aws.amazon.com/AmazonS3/latest/dev/RESTAuthentication.html#RESTAuthenticationQueryStringAuth
	 *
	 * @param array  $args The URL arguments passed.
	 * @param string $url  The URL.
	 *
	 * @return array $args
	 */
	public function http_release_asset_auth( $args, $url ) {
		$arr_url = parse_url( $url );
		if ( isset( $arr_url['host'] ) && 'bbuseruploads.s3.amazonaws.com' === $arr_url['host'] ) {
			unset( $args['headers']['Authorization'] );
		}

		return $args;
	}

	/**
	 * Loads authentication hooks when updating from update-core.php.
	 *
	 * @param bool                             $reply
	 * @param string                           $package Update package URL, unused.
	 * @param \Plugin_Upgrader|\Theme_Upgrader $class   Upgrader object
	 *
	 * @return mixed
	 */
	public function upgrader_pre_download( $reply, $package, $class ) {
		if ( $class instanceof \Plugin_Upgrader &&
		     property_exists( $class->skin, 'plugin_info' )
		) {
			$headers = $class->skin->plugin_info;
			foreach ( self::$basic_auth_required as $git_server ) {
				$ghu_header = $headers[ $git_server . ' Plugin URI' ];
				if ( ! empty( $ghu_header ) ) {
					$this->load_authentication_hooks();
					break;
				}
			}
		}
		if ( $class instanceof \Theme_Upgrader &&
		     property_exists( $class->skin, 'theme_info' )
		) {
			$theme = $class->skin->theme_info;
			foreach ( self::$basic_auth_required as $git_server ) {
				$ghu_header = $theme->get( $git_server . ' Theme URI' );
				if ( ! empty( $ghu_header ) ) {
					$this->load_authentication_hooks();
					break;
				}
			}
		}
		remove_filter( 'upgrader_pre_download', array( &$this, 'upgrader_pre_download' ) );

		return $reply;
	}

}
