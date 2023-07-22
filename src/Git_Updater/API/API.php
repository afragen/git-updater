<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater\API;

use Fragen\Singleton;
use Fragen\Git_Updater\Traits\API_Common;
use Fragen\Git_Updater\Traits\GU_Trait;
use Fragen\Git_Updater\Traits\Basic_Auth_Loader;

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
	use API_Common, GU_Trait, Basic_Auth_Loader;

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
	 * Holds 'plugin'|'theme' or plugin|theme object information for API classes.
	 *
	 * @var string|\stdClass
	 */
	public $type;

	/**
	 * Variable to hold all repository remote info.
	 *
	 * @access public
	 * @var array
	 */
	public $response = [];

	/**
	 * Variable to hold AWS redirect URL.
	 *
	 * @var string|\WP_Error $redirect
	 */
	protected $redirect;

	/**
	 * Default args to pass to wp_remote_get().
	 *
	 * @var array
	 */
	protected $default_http_get_args = [
		'sslverify'     => true,
		'user-agent'    => 'WordPress; Git Updater - https://github.com/afragen/git-updater',
		'wp-rest-cache' => [ 'tag' => 'git-updater' ],
	];

	/**
	 * API constructor.
	 */
	public function __construct() {
		static::$options       = $this->get_class_vars( 'Base', 'options' );
		static::$extra_headers = $this->get_class_vars( 'Base', 'extra_headers' );
	}

	/**
	 * Add data in Settings page.
	 *
	 * @param object $git Git API object.
	 */
	public function settings_hook( $git ) {
		add_action(
			'gu_add_settings',
			function ( $auth_required ) use ( $git ) {
				$git->add_settings( $auth_required );
			}
		);
		add_filter( 'gu_add_repo_setting_field', [ $this, 'add_setting_field' ], 10, 2 );
	}

	/**
	 * Add data to the setting_field in Settings.
	 *
	 * @param array     $fields Array of settings fields.
	 * @param \stdClass $repo   Object of repo data.
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
	 * @param string         $git  'github'.
	 * @param bool|\stdClass $repo Repository object.
	 *
	 * @return \stdClass
	 */
	public function get_repo_api( $git, $repo = false ) {
		$repo_api = null;
		$repo     = $repo ?: new \stdClass();

		if ( 'github' === $git ) {
			$repo_api = new GitHub_API( $repo );
		}

		/**
		 * Filter git host API object.
		 *
		 * @since 10.0.0
		 * @param null|\stdClass $repo_api Git API object.
		 * @param string         $git      Name of git host.
		 * @param \stdClass      $repo     Repository object.
		 *
		 * @return \stdClass
		 */
		$repo_api = \apply_filters( 'gu_get_repo_api', $repo_api, $git, $repo );

		return $repo_api;
	}

	/**
	 * Add Install settings fields.
	 *
	 * @param object $git Git API from caller.
	 */
	public function add_install_fields( $git ) {
		add_action(
			'gu_add_install_settings_fields',
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
	public function api( $url ) {
		$url         = $this->get_api_url( $url );
		$auth_header = $this->add_auth_header( [], $url );
		$type        = $this->return_repo_type();

		// Use cached API failure data to avoid hammering the API.
		$response = $this->get_repo_cache( md5( $url ) );
		$cached   = isset( $response['error_cache'] );
		$response = $response ? $response['error_cache'] : $response;
		$response = ! $response
			? wp_remote_get( $url, array_merge( $this->default_http_get_args, $auth_header ) )
			: $response;

		$code          = (int) wp_remote_retrieve_response_code( $response );
		$allowed_codes = [ 200 ];

		if ( is_wp_error( $response ) ) {
			Singleton::get_instance( 'Messages', $this )->create_error_message( $response );

			return $response;
		}

		// Cache HTTP API error code for 60 minutes.
		if ( ! in_array( $code, $allowed_codes, true ) && ! $cached ) {
			$timeout = 60;

			// Set timeout to GitHub rate limit reset.
			if ( in_array( $type['git'], [ 'github', 'gist' ], true ) ) {
				$timeout = GitHub_API::ratelimit_reset( $response, $this->type->slug );
			}
			$response['timeout'] = $timeout;
			$this->set_repo_cache( 'error_cache', $response, md5( $url ), "+{$timeout} minutes" );
		}

		static::$error_code[ $this->type->slug ] = static::$error_code[ $this->type->slug ] ?? [];
		static::$error_code[ $this->type->slug ] = array_merge(
			static::$error_code[ $this->type->slug ],
			[
				'repo' => $this->type->slug,
				'code' => $code,
				'name' => $this->type->name ?? $this->type->slug,
				'git'  => $this->type->git,
			]
		);
		if ( isset( $response['timeout'] ) ) {
			static::$error_code[ $this->type->slug ]['wait'] = $response['timeout'];
		}
		Singleton::get_instance( 'Messages', $this )->create_error_message( $type['git'] );

		if ( 'file' === self::$method && isset( $response['timeout'] ) && ! $cached && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$response_body = \json_decode( wp_remote_retrieve_body( $response ) );
			if ( null !== $response_body && \property_exists( $response_body, 'message' ) ) {
				$log_message = "Git Updater Error: {$this->type->name} ({$this->type->slug}:{$this->type->branch}) - {$response_body->message}";
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( $log_message );
			}
		}

		/**
		 * Filter HTTP GET remote response body.
		 *
		 * @since 10.0.0
		 * @param string $response HTTP remote response body.
		 * @param \stdClass $this Current API object.
		 */
		$response = apply_filters( 'gu_post_api_response_body', $response, $this );

		return json_decode( wp_remote_retrieve_body( $response ) );
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

		if ( 'github' === $this->type->git ) {
			$arr['git']           = 'github';
			$arr['base_uri']      = 'https://api.github.com';
			$arr['base_download'] = 'https://github.com';
		}

		/**
		 * Filter to add git hosts API data.
		 *
		 * @since 10.0.0
		 * @param array $arr Array of base git host data.
		 */
		$arr = \apply_filters( 'gu_api_repo_type_data', $arr, $this->type );

		return $arr;
	}

	/**
	 * Return API url.
	 *
	 * @param string      $endpoint      The endpoint to access.
	 * @param bool|string $download_link The plugin or theme download link. Defaults to false.
	 *
	 * @return string $endpoint
	 */
	public function get_api_url( $endpoint, $download_link = false ) {
		$type     = $this->return_repo_type();
		$segments = [
			'owner'   => $this->type->owner,
			'repo'    => $this->type->slug,
			'branch'  => empty( $this->type->branch ) ? $this->type->primary_branch : $this->type->branch,
			'gist_id' => $this->type->gist_id ?? null,
		];

		foreach ( $segments as $segment => $value ) {
			$endpoint = str_replace( ':' . $segment, sanitize_text_field( $value ), $endpoint );
		}

		$repo_api = $this->get_repo_api( $type['git'], $this->type );

		if ( 'github' === $type['git'] ) {
			if ( ! $this->type->enterprise && $download_link ) {
				$type['base_download'] = $type['base_uri'];
			}
			if ( $this->type->enterprise_api ) {
				$type['base_download'] = $this->type->enterprise_api;
				$type['base_uri']      = null;
			}
		}

		/**
		 * Filter API URL type for git host.
		 *
		 * @since 10.0.0
		 * @param array     $type          Array or git host data.
		 * @param \stdClass $this->type    Repo object.
		 * @param bool      $download_link Boolean is this a download link.
		 * @param string    $endpoint      Endpoint to URL.
		 */
		$type = apply_filters( 'gu_api_url_type', $type, $this->type, $download_link, $endpoint );

		$base     = $download_link ? $type['base_download'] : $type['base_uri'];
		$endpoint = $repo_api->add_endpoints( $this, $endpoint );

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
		$response = $this->response['dot_org'] ?? false;

		if ( ! $response ) {
			$url      = "https://api.wordpress.org/{$this->type->type}s/info/1.2/";
			$url      = add_query_arg(
				[
					'action'                        => "{$this->type->type}_information",
					rawurlencode( 'request[slug]' ) => $this->type->slug,
				],
				$url
			);
			$response = wp_remote_head( $url );

			if ( is_wp_error( $response ) ) {
				Singleton::get_instance( 'Messages', $this )->create_error_message( $response );

				return false;
			}

			$code     = wp_remote_retrieve_response_code( $response );
			$response = 200 === $code ? 'in dot org' : 'not in dot org';

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
		 * @since 10.0.0
		 * @param bool `true` will exit this function early, default will not.
		 */
		$always_fetch = (bool) apply_filters( 'gu_always_fetch_update', false );

		/**
		 * Filters the return value of exit_no_update.
		 *
		 * @since 6.0.0
		 * @return bool `true` will exit this function early, default will not.
		 */
		$always_fetch = $always_fetch ?: (bool) apply_filters_deprecated( 'ghu_always_fetch_update', [ false ], '10.0.0', 'gu_always_fetch_update' );

		if ( $always_fetch ) {
			return false;
		}

		if ( $branch ) {
			return empty( static::$options['branch_switch'] );
		}

		$refresh = get_site_transient( 'gu_refresh_cache' );

		return ! $refresh && ! $response && ! $this->can_update_repo( $this->type );
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
		return empty( $response ) || isset( $response->message ) || isset( $response->error ) || is_wp_error( $response );
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
	public function get_local_info( $repo, $file ) {
		$response = false;

		if ( get_site_transient( 'gu_refresh_cache' ) ) {
			return $response;
		}

		if ( is_dir( $repo->local_path )
			&& file_exists( $repo->local_path . $file )
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
		$this->type->remote_version = ! empty( $response['Version'] ) ? strtolower( $response['Version'] ) : $this->type->remote_version;
		$this->type->requires_php   = ! empty( $response['RequiresPHP'] ) ? $response['RequiresPHP'] : false;
		$this->type->requires       = ! empty( $response['RequiresWP'] ) ? $response['RequiresWP'] : null;
		$this->type->requires       = ! empty( $response['Requires'] ) ? $response['Requires'] : $this->type->requires;
		$this->type->dot_org        = $response['dot_org'];
		$this->type->primary_branch = ! empty( $response['PrimaryBranch'] ) ? $response['PrimaryBranch'] : $this->type->primary_branch;
	}

	/**
	 * Add remote data to type object.
	 *
	 * @access protected
	 */
	protected function add_meta_repo_object() {
		$this->type->last_updated = $this->type->repo_meta['last_updated'];
		$this->type->is_private   = $this->type->repo_meta['private'];
	}

	/**
	 * Set data from readme.txt.
	 * Prefer changelog from CHANGES.md.
	 *
	 * @param array $readme Array of parsed readme.txt data.
	 *
	 * @return bool
	 */
	public function set_readme_info( $readme ) {
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
		unset( $readme['sections']['screenshots'] );
		$readme['sections']   = ! empty( $readme['sections'] ) ? $readme['sections'] : [];
		$this->type->sections = array_merge( (array) $this->type->sections, (array) $readme['sections'] );

		// Normalize 'tested' version.
		if ( ! empty( $readme['tested'] ) ) {
			list( $version ) = explode( '-', get_bloginfo( 'version' ) );
			$version_arr     = explode( '.', $version );
			$tested_arr      = explode( '.', $readme['tested'] );
			if ( isset( $version_arr[2] ) ) {
				$tested_arr[2] = $version_arr[2];
			}
			$readme['tested'] = implode( '.', $tested_arr );
		}

		$this->type->tested       = $readme['tested'] ?? '';
		$this->type->requires     = $readme['requires'] ?? '';
		$this->type->requires_php = $readme['requires_php'] ?? '';
		$this->type->donate_link  = $readme['donate_link'] ?? '';
		$this->type->contributors = $readme['contributors'] ?? [];
		if ( empty( $readme['upgrade_notice'] ) ) {
			unset( $readme['upgrade_notice'] );
		} else {
			$this->type->upgrade_notice = $readme['upgrade_notice'];
		}

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
	 * @param bool   $aws   Release asset hosted on AWS.
	 *
	 * @return string|bool|\stdClass Release asset URI from AWS.
	 */
	protected function get_release_asset_redirect( $asset, $aws = false ) {
		$rest = false;
		if ( ! $asset ) {
			return false;
		}

		// Unset release asset url if older than 5 min to account for AWS expiration.
		if ( $aws && ( time() - strtotime( '-12 hours', $this->response['timeout'] ) ) >= 300 ) {
			unset( $this->response['release_asset'] );
			unset( $this->response['release_asset_redirect'] );
		}

		$response = $this->response['release_asset_redirect'] ?? false;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['key'] ) ) {
			$slug = isset( $_REQUEST['plugin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) : false;
			$slug = ! $slug && isset( $_REQUEST['theme'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['theme'] ) ) : $slug;
			$rest = $slug === $this->response['repo'];
		}
		// phpcs:enable

		if ( $this->exit_no_update( $response )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&& ! isset( $_REQUEST['override'] ) && ! isset( $_REQUEST['rollback'] )
			&& ! $rest
		) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $response || isset( $_REQUEST['override'] ) ) {
			$args         = $this->add_auth_header( [], $asset );
			$octet_stream = [ 'accept' => 'application/octet-stream' ];
			add_action( 'requests-requests.before_redirect', [ $this, 'set_redirect' ], 10, 1 );
			$args['headers'] = array_merge( $args['headers'], $octet_stream );
			wp_remote_get( $asset, $args );
		}

		if ( ! empty( $this->redirect ) ) {
			$this->set_repo_cache( 'release_asset_redirect', $this->redirect );

			return $this->redirect;
		}

		return $response;
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
