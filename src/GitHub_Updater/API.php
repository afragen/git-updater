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
	Fragen\GitHub_Updater\Traits\API_Trait,
	Fragen\GitHub_Updater\Traits\Basic_Auth_Loader,
	Fragen\GitHub_Updater\API\GitHub_API,
	Fragen\GitHub_Updater\API\Bitbucket_API,
	Fragen\GitHub_Updater\API\Bitbucket_Server_API,
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
	use API_Trait, Basic_Auth_Loader;

	/**
	 * Variable for setting update transient hours.
	 *
	 * @var integer
	 */
	public $hours = 12;

	/**
	 * Variable to hold all repository remote info.
	 *
	 * @access protected
	 * @var    array
	 */
	protected $response = array();

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
	 * Holds HTTP error code from API call.
	 *
	 * @var array ( $this->type-repo => $code )
	 */
	public static $error_code = array();

	/**
	 * API constructor.
	 *
	 */
	public function __construct() {
		static::$options       = $this->get_class_vars( 'Base', 'options' );
		static::$extra_headers = Singleton::get_instance( 'Base', $this )->add_headers( array() );
	}

	/**
	 * Add data in Settings page.
	 *
	 * @param object $git Git API object.
	 */
	public function settings_hook( $git ) {
		add_action( 'github_updater_add_settings', function( $auth_required ) use ( $git ) {
			$git->add_settings( $auth_required );
		} );
		add_filter( 'github_updater_add_repo_setting_field', array( $this, 'add_setting_field' ), 10, 2 );
	}

	/**
	 * Add data to the setting_field in Settings.
	 *
	 * @param array  $fields
	 * @param array  $repo
	 * @param string $type
	 *
	 * @return array
	 */
	public function add_setting_field( $fields, $repo ) {
		if ( ! empty( $fields ) ) {
			return $fields;
		}

		return $this->get_repo_api( $repo->type, $repo )->add_repo_setting_field();
	}

	/**
	 * Add Install settings fields.
	 *
	 * @param object $git Git API from caller.
	 */
	public function add_install_fields( $git ) {
		add_action( 'github_updater_add_install_settings_fields', function( $type ) use ( $git ) {
			$git->add_install_settings_fields( $type );
		} );
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
			static::$error_code = array_merge(
				static::$error_code,
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

		$repo_api = $this->get_repo_api( $type['repo'] . '_' . $type['type'], $type );
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
				$this->load_authentication_hooks();
				if ( $this->type->enterprise_api ) {
					if ( $download_link ) {
						break;
					}
					$endpoint = $repo_api->add_endpoints( $this, $endpoint );

					return $this->type->enterprise_api . $endpoint;
				}
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
	 * Exit early and false for override dot org active.
	 *
	 * @access protected
	 *
	 * @return bool|int|mixed|string|\WP_Error
	 */
	protected function get_dot_org_data() {
		if ( $this->is_override_dot_org() ) {
			return false;
		}

		$slug     = $this->type->repo;
		$response = isset( $this->response['dot_org'] ) ? $this->response['dot_org'] : false;

		if ( ! $response ) {
			$type     = explode( '_', $this->type->type )[1];
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

		return ( ! isset( $_POST['ghu_refresh_cache'] ) && ! $response && ! $this->can_update_repo( $this->type ) );
	}


}
