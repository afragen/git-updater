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
 * Class API
 *
 * @package Fragen\GitHub_Updater
 */
abstract class API extends Base {

	/**
	 * Variable to hold all repository remote info.
	 *
	 * @var array
	 */
	protected $response = array();

	/**
	 * Adds custom user agent for GitHub Updater.
	 *
	 * @param array  $args Existing HTTP Request arguments.
	 * @param string $url  URL being passed.
	 *
	 * @return array Amended HTTP Request arguments.
	 */
	public static function http_request_args( $args, $url ) {
		$args['sslverify'] = true;
		if ( false === stristr( $args['user-agent'], 'GitHub Updater' ) ) {
			$args['user-agent']    = $args['user-agent'] . '; GitHub Updater - https://github.com/afragen/github-updater';
			$args['wp-rest-cache'] = array( 'tag' => 'github-updater' );
		}

		return $args;
	}

	/**
	 * Shiny updates results in the update transient being reset with only the wp.org data.
	 * This catches the response and reloads the transients.
	 *
	 * @param mixed  $response HTTP server response.
	 * @param array  $args     HTTP response arguments.
	 * @param string $url      URL of HTTP response.
	 *
	 * @return mixed $response Just a pass through, no manipulation.
	 */
	public static function wp_update_response( $response, $args, $url ) {
		$parsed_url = parse_url( $url );
		$base       = new Base();

		if ( 'api.wordpress.org' === $parsed_url['host'] ) {
			if ( isset( $args['body']['plugins'] ) ) {
				$base->make_update_transient_current( 'update_plugins' );
			}
			if ( isset( $args['body']['themes'] ) ) {
				$base->make_update_transient_current( 'update_themes' );
			}
		}

		return $response;
	}

	/**
	 * Return repo data for API calls.
	 *
	 * @return array
	 */
	protected function return_repo_type() {
		$type        = explode( '_', $this->type->type );
		$arr         = array();
		$arr['type'] = $type[1];

		switch ( $type[0] ) {
			case 'github':
				$arr['repo']          = 'github';
				$arr['base_uri']      = 'https://api.github.com';
				$arr['base_download'] = 'https://github.com';
				break;
			case 'bitbucket':
				$arr['repo'] = 'bitbucket';
				if ( empty( $this->type->enterprise ) ) {
					$arr['base_uri']      = 'https://bitbucket.org/api';
					$arr['base_download'] = 'https://bitbucket.org';

				} else {
					$arr['base_uri']      = $this->type->enterprise_api;
					$arr['base_download'] = $this->type->enterprise;
				}
				break;
			case 'gitlab':
				$arr['repo']          = 'gitlab';
				$arr['base_uri']      = 'https://gitlab.com/api/v3';
				$arr['base_download'] = 'https://gitlab.com';
				break;
		}

		return $arr;
	}

	/**
	 * Call the API and return a json decoded body.
	 * Create error messages.
	 *
	 * @see http://developer.github.com/v3/
	 *
	 * @param string $url
	 *
	 * @return boolean|object
	 */
	protected function api( $url ) {

		add_filter( 'http_request_args', array( &$this, 'http_request_args' ), 10, 2 );

		$type          = $this->return_repo_type();
		$response      = wp_remote_get( $this->get_api_url( $url ) );
		$code          = (integer) wp_remote_retrieve_response_code( $response );
		$allowed_codes = array( 200, 404 );

		if ( is_wp_error( $response ) ) {
			Messages::instance()->create_error_message( $response );

			return false;
		}
		if ( ! in_array( $code, $allowed_codes, false ) ) {
			self::$error_code = array_merge(
				self::$error_code,
				array(
					$this->type->repo => array(
						'repo' => $this->type->repo,
						'code' => $code,
						'name' => $this->type->name,
						'git'  => $this->type->type,
					),
				) );
			if ( 'github' === $type['repo'] ) {
				GitHub_API::ratelimit_reset( $response, $this->type->repo );
			}
			Messages::instance()->create_error_message( $type['repo'] );

			return false;
		}

		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Return API url.
	 *
	 * @access protected
	 *
	 * @param string $endpoint
	 *
	 * @return string $endpoint
	 */
	protected function get_api_url( $endpoint, $download_link = false ) {
		$type     = $this->return_repo_type();
		$segments = array(
			'owner'  => $this->type->owner,
			'repo'   => $this->type->repo,
			'branch' => empty( $this->type->branch ) ? 'master' : $this->type->branch,
		);

		foreach ( $segments as $segment => $value ) {
			$endpoint = str_replace( '/:' . $segment, '/' . sanitize_text_field( $value ), $endpoint );
		}

		switch ( $type['repo'] ) {
			case 'github':
				if ( ! $this->type->enterprise && $download_link ) {
					$type['base_download'] = $type['base_uri'];
					break;
				}
				if ( $this->type->enterprise_api ) {
					$type['base_download'] = $this->type->enterprise_api;
					if ( $download_link ) {
						break;
					}
				}
				$api      = new GitHub_API( $type['type'] );
				$endpoint = $api->add_endpoints( $this, $endpoint );
				break;
			case 'gitlab':
				if ( ! $this->type->enterprise && $download_link ) {
					break;
				}
				if ( $this->type->enterprise ) {
					$type['base_download'] = $this->type->enterprise;
					$type['base_uri']      = null;
					if ( $download_link ) {
						break;
					}
				}
				$api      = new GitLab_API( $type['type'] );
				$endpoint = $api->add_endpoints( $this, $endpoint );
				break;
			case 'bitbucket':
				if ( $this->type->enterprise_api ) {
					if ( $download_link ) {
						break;
					}
					$api      = new Bitbucket_Server_API( new \stdClass() );
					$endpoint = $api->add_endpoints( $this, $endpoint );

					return $this->type->enterprise_api . $endpoint;
				}
				break;
			default:
				break;
		}

		$base = $download_link ? $type['base_download'] : $type['base_uri'];

		return $base . $endpoint;
	}

	/**
	 * Validate wp_remote_get response.
	 *
	 * @param $response
	 *
	 * @return bool true if invalid
	 */
	protected function validate_response( $response ) {
		if ( empty( $response ) || isset( $response->message ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns repo cached data.
	 *
	 * @return array|bool false for expired cache
	 */
	protected function get_repo_cache() {
		$repo      = isset( $this->type->repo ) ? $this->type->repo : 'ghu';
		$cache_key = 'ghu-' . md5( $repo );
		$cache     = get_site_option( $cache_key );

		if ( empty( $cache['timeout'] ) || current_time( 'timestamp' ) > $cache['timeout'] ) {
			return false;
		}

		return $cache;
	}

	/**
	 * Sets repo data for cache in site option.
	 *
	 * @param string $id       Data Identifier.
	 * @param mixed  $response Data to be stored.
	 *
	 * @return bool
	 */
	protected function set_repo_cache( $id, $response ) {
		$repo      = isset( $this->type->repo ) ? $this->type->repo : 'ghu';
		$cache_key = 'ghu-' . md5( $repo );
		$timeout   = '+' . self::$hours . ' hours';

		$this->response['timeout'] = strtotime( $timeout, current_time( 'timestamp' ) );
		$this->response[ $id ]     = $response;

		update_site_option( $cache_key, $this->response );

		return true;
	}

	/**
	 * Create release asset download link.
	 * Filename must be `{$slug}-{$newest_tag}.zip`
	 *
	 * @return string $download_link
	 */
	protected function make_release_asset_download_link() {
		$download_link = '';
		switch ( $this->type->type ) {
			case 'github_plugin':
			case 'github_theme':
				$download_link = implode( '/', array(
					'https://github.com',
					$this->type->owner,
					$this->type->repo,
					'releases/download',
					$this->type->newest_tag,
					$this->type->repo . '-' . $this->type->newest_tag . '.zip',
				) );
				break;
			case 'bitbucket_plugin':
			case 'bitbucket_theme':
				$download_link = implode( '/', array(
					'https://bitbucket.org',
					$this->type->owner,
					$this->type->repo,
					'downloads',
					$this->type->repo . '-' . $this->type->newest_tag . '.zip',
				) );
				break;
			case 'gitlab_plugin':
			case 'gitlab_theme':
				$download_link = implode( '/', array(
					'https://gitlab.com/api/v3/projects',
					urlencode( $this->type->owner . '/' . $this->type->repo ),
					'builds/artifacts',
					$this->type->newest_tag,
					'download',
				) );
				$download_link = add_query_arg( 'job', $this->type->ci_job, $download_link );
				break;
		}

		return $download_link;
	}

	/**
	 * Query wp.org for plugin information.
	 *
	 * @return array|bool|mixed|string|\WP_Error
	 */
	protected function get_dot_org_data() {
		$slug     = $this->type->repo;
		$response = isset( $this->response['dot_org'] ) ? $this->response['dot_org'] : false;

		if ( ! $response ) {
			$response = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.0/' . $slug . '.json' );
			if ( is_wp_error( $response ) ) {
				return false;
			}
			$wp_repo_body = json_decode( $response['body'] );
			$response     = is_object( $wp_repo_body ) ? 'in dot org' : 'not in dot org';

			$this->set_repo_cache( 'dot_org', $response );
		}
		$response = ( 'in dot org' === $response ) ? true : false;

		return $response;
	}

	/**
	 * Check if a local file for the repository exists.
	 * Only checks the root directory of the repository.
	 *
	 * @param $filename
	 *
	 * @return bool
	 */
	protected function exists_local_file( $filename ) {
		if ( file_exists( $this->type->local_path . $filename ) ||
		     file_exists( $this->type->local_path_extended . $filename )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Add appropriate access token to endpoint.
	 *
	 * @param object $git
	 * @param string $endpoint
	 *
	 * @access private
	 *
	 * @return string $endpoint
	 */
	protected function add_access_token_endpoint( $git, $endpoint ) {
		// This will return if checking during shiny updates.
		if ( ! isset( parent::$options ) ) {
			return $endpoint;
		}
		$key              = null;
		$token            = null;
		$token_enterprise = null;

		switch ( $git->type->type ) {
			case 'github_plugin':
			case 'github_theme':
				$key              = 'access_token';
				$token            = 'github_access_token';
				$token_enterprise = 'github_enterprise_token';
				break;
			case 'gitlab_plugin':
			case 'gitlab_theme':
				$key              = 'private_token';
				$token            = 'gitlab_access_token';
				$token_enterprise = 'gitlab_enterprise_token';
				break;
		}

		// Add hosted access token.
		if ( ! empty( parent::$options[ $token ] ) ) {
			$endpoint = add_query_arg( $key, parent::$options[ $token ], $endpoint );
		}

		// Add Enterprise access token.
		if ( ! empty( $git->type->enterprise ) &&
		     ! empty( parent::$options[ $token_enterprise ] )
		) {
			$endpoint = remove_query_arg( $key, $endpoint );
			$endpoint = add_query_arg( $key, parent::$options[ $token_enterprise ], $endpoint );
		}

		// Add repo access token.
		if ( ! empty( parent::$options[ $git->type->repo ] ) ) {
			$endpoint = remove_query_arg( $key, $endpoint );
			$endpoint = add_query_arg( $key, parent::$options[ $git->type->repo ], $endpoint );
		}

		return $endpoint;
	}

	/**
	 * Add Basic Authentication $args to http_request_args filter hook
	 * for private repositories only.
	 *
	 * @param  mixed  $args
	 * @param  string $url
	 *
	 * @return mixed $args
	 */
	public function maybe_basic_authenticate_http( $args, $url ) {
		$private         = false;
		$private_install = false;

		$credentials = $this->get_credentials( $url );

		/*
		 * Check whether attempting to update private repo.
		 */
		if ( isset( $this->type->repo ) &&
		     ! empty( parent::$options[ $this->type->repo ] ) &&
		     false !== strpos( $url, $this->type->repo )
		) {
			$private = true;
		}

		/*
		 * Check whether attempting to install private repo
		 * and abort if user/pass not set.
		 */
		if ( isset( $_POST['option_page'], $_POST['is_private'] ) &&
		     'github_updater_install' === $_POST['option_page'] &&
		     'bitbucket' === $_POST['github_updater_api'] &&
		     $credentials['isset']
		) {
			$private_install = true;
		}

		if ( ( $private || $private_install ) && ! $credentials['api.wordpress'] ) {
			$username = $credentials['username'];
			$password = $credentials['password'];

			$args['headers']['Authorization'] = 'Basic ' . base64_encode( "$username:$password" );
		}

		return $args;
	}

	/**
	 * Add Basic Authentication $args to http_request_args filter hook
	 * for private repositories only during AJAX.
	 *
	 * @param mixed  $args
	 * @param string $url
	 *
	 * @return mixed $args
	 */
	public function ajax_maybe_basic_authenticate_http( $args, $url ) {
		global $wp_current_filter;

		$ajax_update    = array( 'wp_ajax_update-plugin', 'wp_ajax_update-theme' );
		$is_ajax_update = array_intersect( $ajax_update, $wp_current_filter );
		$private        = false;

		$credentials = $this->get_credentials( $url );

		if ( ! empty( $is_ajax_update ) ) {
			$this->load_options();
		}

		/*
		 * Check whether attempting to update private repo
		 * abort if user/pass not set.
		 */
		if ( ( isset( $_POST['slug'] ) && array_key_exists( $_POST['slug'], parent::$options ) &&
		       1 == parent::$options[ $_POST['slug'] ] &&
		       false !== stristr( $url, $_POST['slug'] ) ) &&
		     $credentials['isset']
		) {
			$private = true;
		}

		if ( parent::is_doing_ajax() && ! parent::is_heartbeat() && $private ) {
			$username = $credentials['username'];
			$password = $credentials['password'];

			$args['headers']['Authorization'] = 'Basic ' . base64_encode( "$username:$password" );
		}

		return $args;
	}

	/**
	 * Get credentials (username/password) for Basic Authentication.
	 *
	 * @param string $url
	 *
	 * @return array $credentials
	 */
	private function get_credentials( $url ) {
		$headers      = parse_url( $url );
		$type         = false;
		$slug         = false;
		$username_key = null;
		$password_key = null;
		$credentials  = array(
			'username'      => null,
			'password'      => null,
			'api.wordpress' => 'api.wordpress.org' === $headers['host'] ? true : false,
			'isset'         => false,
		);

		if ( isset( $_REQUEST['rollback'] ) ) {
			$slug = isset( $_REQUEST['plugin'] ) ? dirname( $_REQUEST['plugin'] ) : $_REQUEST['theme'];
		}
		$slug = isset( $_REQUEST['slug'] ) ? $_REQUEST['slug'] : $slug;

		if ( $slug ) {
			$ghu_plugins = Plugin::instance()->get_plugin_configs();
			$ghu_themes  = Theme::instance()->get_theme_configs();
			$ghu_tokens  = array_merge( $ghu_plugins, $ghu_themes );

			$type = $ghu_tokens[ $slug ]->type;
		}

		$type = $type ? explode( '_', $type ) : array( false );

		// Use switch statement for future API development.
		switch ( $type[0] ) {
			case 'bitbucket':
				$bitbucket_org = 'bitbucket.org' === $headers['host'] ? true : false;
				$username_key  = $bitbucket_org ? 'bitbucket_username' : 'bitbucket_server_username';
				$password_key  = $bitbucket_org ? 'bitbucket_password' : 'bitbucket_server_password';
				break;
		}

		if ( isset( parent::$options[ $username_key ], parent::$options[ $password_key ] ) ) {
			$credentials['username'] = parent::$options[ $username_key ];
			$credentials['password'] = parent::$options[ $password_key ];
			$credentials['isset']    = true;
		}

		return $credentials;
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
