<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

namespace Fragen\GitHub_Updater;

use Fragen\Singleton;
use Fragen\GitHub_Updater\Traits\API_Common;
use Fragen\GitHub_Updater\Traits\GHU_Trait;
use Fragen\GitHub_Updater\Traits\Basic_Auth_Loader;
use Fragen\GitHub_Updater\API\GitHub_API;
use Fragen\GitHub_Updater\API\Bitbucket_API;
use Fragen\GitHub_Updater\API\Bitbucket_Server_API;
use Fragen\GitHub_Updater\API\GitLab_API;
use Fragen\GitHub_Updater\API\Gitea_API;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class API
 */
class API {
	use API_Common, GHU_Trait, Basic_Auth_Loader;

	/**
	 * Holds HTTP error code from API call.
	 *
	 * @var array ( $this->type->slug => $code )
	 */
	protected static $error_code = [];

	/**
	 * Holds site options.
	 *
	 * @var array $options
	 */
	protected static $options;

	/**
	 * Holds extra headers.
	 *
	 * @var array $extra_headers
	 */
	protected static $extra_headers;

	/**
	 * Variable for setting update transient hours.
	 *
	 * @var integer
	 */
	protected $hours = 12;

	/**
	 * Variable to hold all repository remote info.
	 *
	 * @access protected
	 * @var array
	 */
	protected $response = [];

	/**
	 * Variable to hold AWS redirect URL.
	 *
	 * @var string|\WP_Error $redirect
	 */
	protected $redirect;

	/**
	 * API constructor.
	 */
	public function __construct() {
		static::$options       = $this->get_class_vars( 'Base', 'options' );
		static::$extra_headers = $this->get_class_vars( 'Base', 'extra_headers' );
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
			$args['user-agent']   .= '; GitHub Updater - https://github.com/afragen/github-updater';
			$args['wp-rest-cache'] = [ 'tag' => 'github-updater' ];
		}

		return $args;
	}

	/**
	 * Add data in Settings page.
	 *
	 * @param object $git Git API object.
	 */
	public function settings_hook( $git ) {
		add_action(
			'github_updater_add_settings',
			function ( $auth_required ) use ( $git ) {
				$git->add_settings( $auth_required );
			}
		);
		add_filter( 'github_updater_add_repo_setting_field', [ $this, 'add_setting_field' ], 10, 2 );
	}

	/**
	 * Add data to the setting_field in Settings.
	 *
	 * @param array $fields Array of settings fields.
	 * @param array $repo   Array of repo data.
	 *
	 * @return array
	 */
	public function add_setting_field( $fields, $repo ) {
		if ( ! empty( $fields ) ) {
			return $fields;
		}

		return $this->get_repo_api( $repo->git, $repo )->add_repo_setting_field();
	}

	/**
	 * Get repo's API.
	 *
	 * @param string         $git  (github|bitbucket|gitlab|gitea)
	 * @param bool|\stdClass $repo Repository object.
	 *
	 * @return \Fragen\GitHub_Updater\API\Bitbucket_API|
	 *                                                   \Fragen\GitHub_Updater\API\Bitbucket_Server_API|
	 *                                                   \Fragen\GitHub_Updater\API\Gitea_API|
	 *                                                   \Fragen\GitHub_Updater\API\GitHub_API|
	 *                                                   \Fragen\GitHub_Updater\API\GitLab_API $repo_api
	 */
	public function get_repo_api( $git, $repo = false ) {
		$repo_api = null;
		$repo     = $repo ?: new \stdClass();
		switch ( $git ) {
			case 'github':
				$repo_api = new GitHub_API( $repo );
				break;
			case 'bitbucket':
				if ( ! empty( $repo->enterprise ) ) {
					$repo_api = new Bitbucket_Server_API( $repo );
				} else {
					$repo_api = new Bitbucket_API( $repo );
				}
				break;
			case 'gitlab':
				$repo_api = new GitLab_API( $repo );
				break;
			case 'gitea':
				$repo_api = new Gitea_API( $repo );
				break;
		}

		return $repo_api;
	}

	/**
	 * Add Install settings fields.
	 *
	 * @param object $git Git API from caller.
	 */
	public function add_install_fields( $git ) {
		add_action(
			'github_updater_add_install_settings_fields',
			function ( $type ) use ( $git ) {
				$git->add_install_settings_fields( $type );
			}
		);
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
		add_filter( 'http_request_args', [ $this, 'http_request_args' ], 10, 2 );

		$type          = $this->return_repo_type();
		$response      = wp_remote_get( $this->get_api_url( $url ) );
		$code          = (int) wp_remote_retrieve_response_code( $response );
		$allowed_codes = [ 200, 404 ];
		remove_filter( 'http_request_args', [ $this, 'http_request_args' ] );

		if ( is_wp_error( $response ) ) {
			Singleton::get_instance( 'Messages', $this )->create_error_message( $response );

			return $response;
		}
		if ( ! in_array( $code, $allowed_codes, true ) ) {
			static::$error_code = array_merge(
				static::$error_code,
				[
					$this->type->slug => [
						'repo' => $this->type->slug,
						'code' => $code,
						'name' => $this->type->name,
						'git'  => $this->type->git,
					],
				]
			);
			if ( 'github' === $type['git'] ) {
				GitHub_API::ratelimit_reset( $response, $this->type->slug );
			}
			Singleton::get_instance( 'Messages', $this )->create_error_message( $type['git'] );

			return false;
		}

		// Gitea doesn't return json encoded raw file.
		$response = $this->convert_body_string_to_json( $response );

		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Convert response body to JSON.
	 *
	 * @param  mixed $response (JSON|string)
	 * @return mixed $response JSON encoded.
	 */
	private function convert_body_string_to_json( $response ) {
		if ( $this instanceof Gitea_API || $this instanceof Bitbucket_API || $this instanceof Bitbucket_Server_API ) {
			$body = wp_remote_retrieve_body( $response );
			if ( null === json_decode( $body ) ) {
				$response['body'] = json_encode( $body );
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
		$arr         = [];
		$arr['type'] = $this->type->type;

		switch ( $this->type->git ) {
			case 'github':
				$arr['git']           = 'github';
				$arr['base_uri']      = 'https://api.github.com';
				$arr['base_download'] = 'https://github.com';
				break;
			case 'bitbucket':
				$arr['git'] = 'bitbucket';
				if ( empty( $this->type->enterprise ) ) {
					$arr['base_uri']      = 'https://api.bitbucket.org';
					$arr['base_download'] = 'https://bitbucket.org';
				} else {
					$arr['base_uri']      = $this->type->enterprise_api;
					$arr['base_download'] = $this->type->enterprise;
				}
				break;
			case 'gitlab':
				$arr['git']           = 'gitlab';
				$arr['base_uri']      = 'https://gitlab.com/api/v4';
				$arr['base_download'] = 'https://gitlab.com';
				break;
			case 'gitea':
				$arr['git']           = 'gitea';
				$arr['base_uri']      = $this->type->enterprise . '/api/v1';
				$arr['base_download'] = $this->type->enterprise;
				break;
		}

		return $arr;
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
		$segments = [
			'owner'  => $this->type->owner,
			'repo'   => $this->type->slug,
			'branch' => empty( $this->type->branch ) ? 'master' : $this->type->branch,
		];

		foreach ( $segments as $segment => $value ) {
			$endpoint = str_replace( ':' . $segment, sanitize_text_field( $value ), $endpoint );
		}

		$repo_api = $this->get_repo_api( $type['git'], $this->type );
		$this->load_authentication_hooks();

		switch ( $type['git'] ) {
			case 'github':
				if ( ! $this->type->enterprise && $download_link ) {
					$type['base_download'] = $type['base_uri'];
					break;
				}
				if ( $this->type->enterprise_api ) {
					$type['base_download'] = $this->type->enterprise_api;
					$type['base_uri']      = null;
					if ( $download_link ) {
						break;
					}
				}
				$endpoint = $repo_api->add_endpoints( $this, $endpoint );
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
				$endpoint = $repo_api->add_endpoints( $this, $endpoint );
				break;
			case 'bitbucket':
				if ( $this->type->enterprise_api ) {
					if ( $download_link ) {
						$type['base_download'] = $type['base_uri'];
						break;
					}
					$endpoint = $repo_api->add_endpoints( $this, $endpoint );

					return $this->type->enterprise_api . $endpoint;
				}
				if ( $download_link && 'release_asset' === self::$method ) {
					$type['base_download'] = $type['base_uri'];
				}
				$endpoint = $repo_api->add_endpoints( $this, $endpoint );
				break;
			case 'gitea':
				if ( $download_link ) {
					$type['base_download'] = $type['base_uri'];
					break;
				}
				$endpoint = $repo_api->add_endpoints( $this, $endpoint );
				break;
			default:
				break;
		}

		$base = $download_link ? $type['base_download'] : $type['base_uri'];

		return $base . $endpoint;
	}

	/**
	 * Query wp.org for plugin/theme information.
	 *
	 * @access protected
	 *
	 * @return bool|int|mixed|string|\WP_Error
	 */
	protected function get_dot_org_data() {
		$response = isset( $this->response['dot_org'] ) ? $this->response['dot_org'] : false;

		if ( ! $response ) {
			$url      = "https://api.wordpress.org/{$this->type->type}s/info/1.1/";
			$url      = add_query_arg(
				[
					'action'                        => "{$this->type->type}_information",
					rawurlencode( 'request[slug]' ) => $this->type->slug,
				],
				$url
			);
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
	 * Test to exit early if no update available, saves API calls.
	 *
	 * @param array|bool $response API response.
	 * @param bool       $branch   Branch name.
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

		return ! isset( $_POST['ghu_refresh_cache'] ) && ! $response && ! $this->can_update_repo( $this->type );
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
		return empty( $response ) || isset( $response->message ) || is_wp_error( $response );
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
	 * Sort tags and set object data.
	 *
	 * @param array $parsed_tags Array of tags.
	 *
	 * @return bool
	 */
	protected function sort_tags( $parsed_tags ) {
		if ( empty( $parsed_tags ) ) {
			return false;
		}

		list($tags, $rollback) = $parsed_tags;
		usort( $tags, 'version_compare' );
		krsort( $rollback );

		$newest_tag     = array_slice( $tags, -1, 1, true );
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
	 * @param \stdClass $repo Repo data.
	 * @param string    $file Filename.
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

		return $response;
	}

	/**
	 * Set repo object file info.
	 *
	 * @param array $response Repo data.
	 */
	protected function set_file_info( $response ) {
		$this->type->transient      = $response;
		$this->type->remote_version = strtolower( $response['Version'] );
		$this->type->requires_php   = ! empty( $response['RequiresPHP'] ) ? $response['RequiresPHP'] : false;
		$this->type->requires       = ! empty( $response['RequiresWP'] ) ? $response['RequiresWP'] : null;
		$this->type->requires       = ! empty( $response['Requires'] ) ? $response['Requires'] : $this->type->requires;
		$this->type->dot_org        = $response['dot_org'];
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
	 * @param array $repo_meta Array of repo meta data.
	 *
	 * @return integer
	 */
	protected function make_rating( $repo_meta ) {
		$watchers    = ! empty( $repo_meta['watchers'] ) ? $repo_meta['watchers'] : 0;
		$forks       = ! empty( $repo_meta['forks'] ) ? $repo_meta['forks'] : 0;
		$open_issues = ! empty( $repo_meta['open_issues'] ) ? $repo_meta['open_issues'] : 0;

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
	 * @param array $readme Array of parsed readme.txt data.
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
		$readme['sections']       = ! empty( $readme['sections'] ) ? $readme['sections'] : [];
		$this->type->sections     = array_merge( (array) $this->type->sections, (array) $readme['sections'] );
		$this->type->tested       = isset( $readme['tested'] ) ? $readme['tested'] : null;
		$this->type->requires     = isset( $readme['requires'] ) ? $readme['requires'] : null;
		$this->type->requires_php = isset( $readme['requires_php'] ) ? $readme['requires_php'] : null;
		$this->type->donate_link  = isset( $readme['donate_link'] ) ? $readme['donate_link'] : null;
		$this->type->contributors = isset( $readme['contributors'] ) ? $readme['contributors'] : null;

		return true;
	}

	/**
	 * Return the redirect download link for a release asset.
	 * AWS download link sets a link expiration of ONLY 5 minutes.
	 *
	 * @since 6.1.0
	 * @uses  Requests, requires WP 4.6
	 *
	 * @param string $asset Release asset URI from git host.
	 *
	 * @return string|bool|\stdClass Release asset URI from AWS.
	 */
	protected function get_release_asset_redirect( $asset, $aws = false ) {
		if ( ! $asset ) {
			return false;
		}

		// Unset release asset url if older than 5 min to account for AWS expiration.
		if ( $aws && ( time() - strtotime( '-12 hours', $this->response['timeout'] ) ) >= 300 ) {
			unset( $this->response['release_asset_redirect'] );
		}

		$response = isset( $this->response['release_asset_redirect'] ) ? $this->response['release_asset_redirect'] : false;

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( $this->exit_no_update( $response ) && ! isset( $_REQUEST['override'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $response || isset( $_REQUEST['override'] ) ) {
			add_action( 'requests-requests.before_redirect', [ $this, 'set_redirect' ], 10, 1 );
			add_filter( 'http_request_args', [ $this, 'set_aws_release_asset_header' ] );
			wp_remote_get( $asset );
			remove_filter( 'http_request_args', [ $this, 'set_aws_release_asset_header' ] );
		}

		if ( ! empty( $this->redirect ) ) {
			$this->set_repo_cache( 'release_asset_redirect', $this->redirect );

			return $this->redirect;
		}

		return $response;
	}

	/**
	 * Set HTTP header for following AWS release assets.
	 *
	 * @since 6.1.0
	 *
	 * @param array  $args Array of data.
	 * @param string $url  URL.
	 *
	 * @return mixed $args
	 */
	public function set_aws_release_asset_header( $args, $url = '' ) {
		$args['headers']['accept'] = 'application/octet-stream';

		return $args;
	}

	/**
	 * Set AWS redirect URL from action hook.
	 *
	 * @uses `requests-requests.before_redirect` Action hook.
	 *
	 * @param  string $location URL.
	 * @return void
	 */
	public function set_redirect( $location ) {
		$this->redirect = $location;
	}
}
