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

use Fragen\Singleton,
	Fragen\GitHub_Updater\API\GitHub_API,
	Fragen\GitHub_Updater\API\GitLab_API,
	Fragen\GitHub_Updater\API\Gitea_API;


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
 * @uses    \Fragen\GitHub_Updater\Base
 */
class API {

	/**
	 * Variable for setting update transient hours.
	 *
	 * @var integer
	 */
	protected static $hours = 12;

	/**
	 * Holds HTTP error code from API call.
	 *
	 * @var array ( $this->type-repo => $code )
	 */
	protected static $error_code = array();

	/**
	 * Variable to hold all repository remote info.
	 *
	 * @access protected
	 * @var    array
	 */
	protected $response = array();

	/**
	 * Holds instance of class Base.
	 *
	 * @var \Fragen\GitHub_Updater\Base
	 */
	protected $base;

	/**
	 * Holds site options.
	 *
	 * @var array $options
	 */
	protected static $options;

	/**
	 * Holds extra headers.
	 *
	 * @var
	 */
	protected static $extra_headers;

	/**
	 * API constructor.
	 *
	 */
	public function __construct() {
		$this->base            = $base = Singleton::get_instance( 'Base', $this );
		static::$options       = $base::$options;
		static::$extra_headers = $this->base->add_headers( array() );
	}

	/**
	 * Adds custom user agent for GitHub Updater.
	 *
	 * @access public
	 *
	 * @param array  $args Existing HTTP Request arguments.
	 * @param string $url  URL being passed.
	 *
	 * @return array Amended HTTP Request arguments.
	 */
	public static function http_request_args( $args, $url ) {
		$args['sslverify'] = true;
		if ( false === stripos( $args['user-agent'], 'GitHub Updater' ) ) {
			$args['user-agent']    .= '; GitHub Updater - https://github.com/afragen/github-updater';
			$args['wp-rest-cache'] = array( 'tag' => 'github-updater' );
		}

		return $args;
	}

	/**
	 * Shiny updates results in the update transient being reset with only the wp.org data.
	 * This catches the response and reloads the transients.
	 *
	 * @uses \Fragen\GitHub_Updater\Base
	 * @uses \Fragen\GitHub_Updater\Base::make_update_transient_current()
	 *
	 * @param mixed  $response HTTP server response.
	 * @param array  $args     HTTP response arguments.
	 * @param string $url      URL of HTTP response.
	 *
	 * @return mixed $response Just a pass through, no manipulation.
	 */
	public static function wp_update_response( $response, $args, $url ) {
		$parsed_url = parse_url( $url );

		if ( 'api.wordpress.org' === $parsed_url['host'] ) {
			if ( isset( $args['body']['plugins'] ) ) {
				Singleton::get_instance( 'Base', new self() )->make_update_transient_current( 'update_plugins' );
			}
			if ( isset( $args['body']['themes'] ) ) {
				Singleton::get_instance( 'Base', new self() )->make_update_transient_current( 'update_themes' );
			}
		}

		return $response;
	}

	/**
	 * Return repo data for API calls.
	 *
	 * @access protected
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
			case 'gitea':
				$arr['repo']          = 'gitea';
				$arr['base_uri']      = $this->type->enterprise . '/api/v1';
				$arr['base_download'] = $this->type->enterprise;
		}

		return $arr;
	}

	/**
	 * Call the API and return a json decoded body.
	 * Create error messages.
	 *
	 * @link http://developer.github.com/v3/
	 *
	 * @param string $url The URL to send the request to.
	 *
	 * @return boolean|\stdClass
	 */
	protected function api( $url ) {

		add_filter( 'http_request_args', array( &$this, 'http_request_args' ), 10, 2 );

		$type          = $this->return_repo_type();
		$response      = wp_remote_get( $this->get_api_url( $url ) );
		$code          = (int) wp_remote_retrieve_response_code( $response );
		$allowed_codes = array( 200, 404 );

		if ( is_wp_error( $response ) ) {
			Singleton::get_instance( 'Messages', $this )->create_error_message( $response );

			return false;
		}
		if ( ! in_array( $code, $allowed_codes, true ) ) {
			self::$error_code = array_merge(
				self::$error_code,
				array(
					$this->type->repo => array(
						'repo' => $this->type->repo,
						'code' => $code,
						'name' => $this->type->name,
						'git'  => $this->type->type,
					),
				)
			);
			if ( 'github' === $type['repo'] ) {
				GitHub_API::ratelimit_reset( $response, $this->type->repo );
			}
			Singleton::get_instance( 'Messages', $this )->create_error_message( $type['repo'] );

			return false;
		}

		// Gitea doesn't return json encoded raw file.
		if ( $this instanceof Gitea_API ) {
			$body = wp_remote_retrieve_body( $response );
			if ( null === json_decode( $body ) ) {
				return $body;
			}
		}

		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Return API url.
	 *
	 * @access protected
	 *
	 * @param string      $endpoint      The endpoint to access.
	 * @param bool|string $download_link The plugin or theme download link. Defaults to false.
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
				Singleton::get_instance( 'Basic_Auth_Loader', $this, static::$options )->load_authentication_hooks();
				if ( $this->type->enterprise_api ) {
					if ( $download_link ) {
						break;
					}
					$endpoint = Singleton::get_instance( 'API\Bitbucket_Server_API', $this, new \stdClass() )->add_endpoints( $this, $endpoint );

					return $this->type->enterprise_api . $endpoint;
				}
				break;
			case 'gitea':
				if ( $download_link ) {
					$type['base_download'] = $type['base_uri'];
					break;
				}
				$api      = new Gitea_API( $type['type'] );
				$endpoint = $api->add_endpoints( $this, $endpoint );
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
	 * @access protected
	 *
	 * @param \stdClass $response The response.
	 *
	 * @return bool true if invalid
	 */
	protected function validate_response( $response ) {
		return empty( $response ) || isset( $response->message );
	}

	/**
	 * Returns repo cached data.
	 *
	 * @access protected
	 *
	 * @param string|bool $repo Repo name or false.
	 *
	 * @return array|bool The repo cache. False if expired.
	 */
	public function get_repo_cache( $repo = false ) {
		if ( ! $repo ) {
			$repo = isset( $this->type->repo ) ? $this->type->repo : 'ghu';
		}
		$cache_key = 'ghu-' . md5( $repo );
		$cache     = get_site_option( $cache_key );

		if ( empty( $cache['timeout'] ) || time() > $cache['timeout'] ) {
			return false;
		}

		return $cache;
	}

	/**
	 * Sets repo data for cache in site option.
	 *
	 * @access protected
	 *
	 * @param string      $id       Data Identifier.
	 * @param mixed       $response Data to be stored.
	 * @param string|bool $repo     Repo name or false.
	 * @param string|bool $timeout  Timeout for cache.
	 *                              Default is static::$hours (12 hours).
	 *
	 * @return bool
	 */
	public function set_repo_cache( $id, $response, $repo = false, $timeout = false ) {
		if ( ! $repo ) {
			$repo = isset( $this->type->repo ) ? $this->type->repo : 'ghu';
		}
		$cache_key = 'ghu-' . md5( $repo );
		$timeout   = $timeout ? $timeout : '+' . static::$hours . ' hours';

		$this->response['timeout'] = strtotime( $timeout );
		$this->response[ $id ]     = $response;

		update_site_option( $cache_key, $this->response );

		return true;
	}

	/**
	 * Query wp.org for plugin/theme information.
	 * Exit early and false for override dot org active.
	 *
	 * @access protected
	 *
	 * @return bool|int|mixed|string|\WP_Error
	 */
	protected function get_dot_org_data() {
		if ( $this->base->is_override_dot_org() ) {
			return false;
		}

		$slug     = $this->type->repo;
		$response = isset( $this->response['dot_org'] ) ? $this->response['dot_org'] : false;

		if ( ! $response ) {
			//@TODO shorten syntax for PHP 5.4
			$type     = explode( '_', $this->type->type );
			$type     = $type[1];
			$url      = 'https://api.wordpress.org/' . $type . 's/info/1.1/';
			$url      = add_query_arg( array( 'action' => $type . '_information', 'request[slug]' => $slug ), $url );
			$response = wp_remote_get( $url );

			if ( is_wp_error( $response ) ) {
				Singleton::get_instance( 'Messages', $this )->create_error_message( $response );

				return false;
			}

			$response = json_decode( $response['body'] );
			$response = ! empty( $response ) && ! isset( $response->error ) ? 'in dot org' : 'not in dot org';

			$this->set_repo_cache( 'dot_org', $response );
		}

		return 'in dot org' === $response;
	}

	/**
	 * Check if a local file for the repository exists.
	 * Only checks the root directory of the repository.
	 *
	 * @access protected
	 *
	 * @param string $filename The filename to check for.
	 *
	 * @return bool
	 */
	protected function local_file_exists( $filename ) {
		return file_exists( $this->type->local_path . $filename );
	}

	/**
	 * Returns static class variable $error_code.
	 *
	 * @return array self::$error_code
	 */
	public function get_error_codes() {
		return self::$error_code;
	}

	/**
	 * Add appropriate access token to endpoint.
	 *
	 * @access protected
	 *
	 * @param GitHub_API|GitLab_API $git      Class containing the GitAPI used.
	 * @param string                $endpoint The endpoint being accessed.
	 *
	 * @return string $endpoint
	 */
	protected function add_access_token_endpoint( $git, $endpoint ) {
		// This will return if checking during shiny updates.
		if ( null === static::$options ) {
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
			case 'gitea_plugin':
			case 'gitea_theme':
				$key              = 'access_token';
				$token            = 'gitea_access_token';
				$token_enterprise = 'gitea_access_token';
				break;
		}

		// Add hosted access token.
		if ( ! empty( static::$options[ $token ] ) ) {
			$endpoint = add_query_arg( $key, static::$options[ $token ], $endpoint );
		}

		// Add Enterprise access token.
		if ( ! empty( $git->type->enterprise ) &&
		     ! empty( static::$options[ $token_enterprise ] )
		) {
			$endpoint = remove_query_arg( $key, $endpoint );
			$endpoint = add_query_arg( $key, static::$options[ $token_enterprise ], $endpoint );
		}

		// Add repo access token.
		if ( ! empty( static::$options[ $git->type->repo ] ) ) {
			$endpoint = remove_query_arg( $key, $endpoint );
			$endpoint = add_query_arg( $key, static::$options[ $git->type->repo ], $endpoint );
		}

		return $endpoint;
	}

	/**
	 * Test to exit early if no update available, saves API calls.
	 *
	 * @param $response array|bool
	 * @param $branch   bool
	 *
	 * @return bool
	 */
	protected function exit_no_update( $response, $branch = false ) {
		/**
		 * Filters the return value of exit_no_update.
		 *
		 * @since 6.0.0
		 * @return bool `true` will exit this function early, default will not.
		 */
		if ( apply_filters( 'ghu_always_fetch_update', false ) ) {
			return false;
		}

		if ( $branch ) {
			return empty( static::$options['branch_switch'] );
		}

		return ( ! isset( $_POST['ghu_refresh_cache'] ) && ! $response && ! $this->base->can_update_repo( $this->type ) );
	}

	/**
	 * Sort tags and set object data.
	 *
	 * @param array $parsed_tags
	 *
	 * @return bool
	 */
	protected function sort_tags( $parsed_tags ) {
		if ( empty( $parsed_tags ) ) {
			return false;
		}

		list( $tags, $rollback ) = $parsed_tags;
		usort( $tags, 'version_compare' );
		krsort( $rollback );

		$newest_tag     = array_slice( $tags, - 1, 1, true );
		$newest_tag_key = key( $newest_tag );
		$newest_tag     = $tags[ $newest_tag_key ];

		$this->type->newest_tag = $newest_tag;
		$this->type->tags       = $tags;
		$this->type->rollback   = $rollback;

		return true;
	}

	/**
	 * Get local file info if no update available. Save API calls.
	 *
	 * @param $repo
	 * @param $file
	 *
	 * @return null|string
	 */
	protected function get_local_info( $repo, $file ) {
		$response = false;

		if ( isset( $_POST['ghu_refresh_cache'] ) ) {
			return $response;
		}

		if ( is_dir( $repo->local_path ) &&
		     file_exists( $repo->local_path . $file )
		) {
			$response = file_get_contents( $repo->local_path . $file );
		}

		switch ( $repo->type ) {
			case 'bitbucket_plugin':
			case 'bitbucket_theme':
				break;
			default:
				$response = base64_encode( $response );
				break;
		}

		return $response;
	}

	/**
	 * Set repo object file info.
	 *
	 * @param $response
	 */
	protected function set_file_info( $response ) {
		$this->type->transient            = $response;
		$this->type->remote_version       = strtolower( $response['Version'] );
		$this->type->requires_php_version = ! empty( $response['Requires PHP'] ) ? $response['Requires PHP'] : $this->type->requires_php_version;
		$this->type->requires_wp_version  = ! empty( $response['Requires WP'] ) ? $response['Requires WP'] : $this->type->requires_wp_version;
		$this->type->dot_org              = $response['dot_org'];
	}

	/**
	 * Add remote data to type object.
	 *
	 * @access protected
	 */
	protected function add_meta_repo_object() {
		$this->type->rating       = $this->make_rating( $this->type->repo_meta );
		$this->type->last_updated = $this->type->repo_meta['last_updated'];
		$this->type->num_ratings  = $this->type->repo_meta['watchers'];
		$this->type->is_private   = $this->type->repo_meta['private'];
	}

	/**
	 * Create some sort of rating from 0 to 100 for use in star ratings.
	 * I'm really just making this up, more based upon popularity.
	 *
	 * @param $repo_meta
	 *
	 * @return integer
	 */
	protected function make_rating( $repo_meta ) {
		$watchers    = empty( $repo_meta['watchers'] ) ? $this->type->watchers : $repo_meta['watchers'];
		$forks       = empty( $repo_meta['forks'] ) ? $this->type->forks : $repo_meta['forks'];
		$open_issues = empty( $repo_meta['open_issues'] ) ? $this->type->open_issues : $repo_meta['open_issues'];

		$rating = abs( (int) round( $watchers + ( $forks * 1.5 ) - ( $open_issues * 0.1 ) ) );

		if ( 100 < $rating ) {
			return 100;
		}

		return $rating;
	}

	/**
	 * Set data from readme.txt.
	 * Prefer changelog from CHANGES.md.
	 *
	 * @param array $readme Array of parsed readme.txt data
	 *
	 * @return bool
	 */
	protected function set_readme_info( $readme ) {
		foreach ( (array) $this->type->sections as $section => $value ) {
			if ( 'description' === $section ) {
				continue;
			}
			$readme['sections'][ $section ] = $value;
		}

		$readme['remaining_content'] = ! empty( $readme['remaining_content'] ) ? $readme['remaining_content'] : null;
		if ( empty( $readme['sections']['other_notes'] ) ) {
			unset( $readme['sections']['other_notes'] );
		} else {
			$readme['sections']['other_notes'] .= $readme['remaining_content'];
		}
		unset( $readme['sections']['screenshots'], $readme['sections']['installation'] );
		$readme['sections']       = ! empty( $readme['sections'] ) ? $readme['sections'] : array();
		$this->type->sections     = array_merge( (array) $this->type->sections, (array) $readme['sections'] );
		$this->type->tested       = isset( $readme['tested'] ) ? $readme['tested'] : null;
		$this->type->requires     = isset( $readme['requires'] ) ? $readme['requires'] : null;
		$this->type->donate_link  = isset( $readme['donate_link'] ) ? $readme['donate_link'] : null;
		$this->type->contributors = isset( $readme['contributors'] ) ? $readme['contributors'] : null;

		return true;
	}

}
