<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater\REST;

use Fragen\Git_Updater\Traits\GU_Trait;
use Fragen\Singleton;

/**
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class REST_API
 */
class REST_API {
	use GU_Trait;

	/**
	 * Holds REST namespace.
	 *
	 * @var string
	 */
	public static $namespace = 'git-updater/v1';

	/**
	 * Load hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_action( 'rest_api_init', [ new REST_API(), 'register_endpoints' ] );

		// Deprecated AJAX request.
		add_action( 'wp_ajax_git-updater-update', [ Singleton::get_instance( 'REST\Rest_Update', $this ), 'process_request' ] );
		add_action( 'wp_ajax_nopriv_git-updater-update', [ Singleton::get_instance( 'REST\Rest_Update', $this ), 'process_request' ] );
	}

	/**
	 * Register REST API endpoints.
	 *
	 * @return void
	 */
	public function register_endpoints() {
		register_rest_route(
			self::$namespace,
			'test',
			[
				'show_in_index'       => true,
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'test' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'git-updater',
			'namespace',
			[
				'show_in_index'       => true,
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_namespace' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			self::$namespace,
			'repos',
			[
				'show_in_index'       => false,
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_remote_repo_data' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'key' => [
						'default'           => null,
						'required'          => true,
						'validate_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			self::$namespace,
			'plugins-api',
			[
				'show_in_index'       => true,
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_plugins_api_data' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'slug' => [
						'default'           => false,
						'required'          => true,
						'validate_callback' => 'sanitize_title_with_dashes',
					],
				],
			]
		);

		$update_args = [
			'key'        => [
				'default'           => false,
				'required'          => true,
				'validate_callback' => 'sanitize_text_field',
			],
			'plugin'     => [
				'default'           => false,
				'validate_callback' => 'sanitize_title_with_dashes',
			],
			'theme'      => [
				'default'           => false,
				'validate_callback' => 'sanitize_title_with_dashes',
			],
			'tag'        => [
				'default'           => false,
				'validate_callback' => 'sanitize_text_field',
			],
			'branch'     => [
				'default'           => false,
				'validate_callback' => 'sanitize_text_field',
			],
			'committish' => [
				'default'           => false,
				'validate_callback' => 'sanitize_text_field',
			],
			'override'   => [
				'default' => false,
			],
		];

		register_rest_route(
			self::$namespace,
			'update',
			[
				[
					'show_in_index'       => true,
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ new REST_Update(), 'process_request' ],
					'permission_callback' => '__return_true',
					'args'                => $update_args,
				],
				[
					'show_in_index'       => false,
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ new REST_Update(), 'process_request' ],
					'permission_callback' => '__return_true',
					'args'                => $update_args,
				],
			]
		);

		register_rest_route(
			self::$namespace,
			'reset-branch',
			[
				'show_in_index'       => true,
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'reset_branch' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'key'    => [
						'default'           => null,
						'required'          => true,
						'validate_callback' => 'sanitize_text_field',
					],
					'plugin' => [
						'default'           => false,
						'validate_callback' => 'sanitize_title_with_dashes',
					],
					'theme'  => [
						'default'           => false,
						'validate_callback' => 'sanitize_title_with_dashes',
					],
				],
			]
		);

		register_rest_route(
			'github-updater/v1',
			'test',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'deprecated' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'github-updater/v1',
			'repos',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'deprecated' ],
				'permission_callback' => '__return_true',
			]
		);
		register_rest_route(
			'github-updater/v1',
			'update',
			[
				[
					'show_in_index'       => false,
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ new REST_Update(), 'process_request' ],
					'permission_callback' => '__return_true',
					'args'                => $update_args,
				],
				[
					'show_in_index'       => false,
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ new REST_Update(), 'process_request' ],
					'permission_callback' => '__return_true',
					'args'                => $update_args,
				],
			]
		);
	}

	/**
	 * Return deprecation notice.
	 *
	 * @return array
	 */
	public function deprecated() {
		$namespace = self::$namespace;
		return [
			'success' => false,
			'error'   => "The 'github-updater/v1' REST route namespace has been deprecated. Please use '{$namespace}'",
		];
	}

	/**
	 * Simple REST endpoint return.
	 *
	 * @return string
	 */
	public function test() {
		return 'Connected to Git Updater!';
	}

	/**
	 * Return current REST namespace.
	 *
	 * @return array
	 */
	public function get_namespace() {
		return [ 'namespace' => self::$namespace ];
	}

	/**
	 * Get repo data for Git Remote Updater.
	 *
	 * @param \WP_REST_Request $request REST API response.
	 *
	 * @return array
	 */
	public function get_remote_repo_data( \WP_REST_Request $request ) {
		// Test for API key and exit if incorrect.
		if ( $this->get_class_vars( 'Remote_Management', 'api_key' ) !== $request->get_param( 'key' ) ) {
			return [ 'error' => 'Bad API key. No repo data for you.' ];
		}
		$gu_plugins = Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this )->get_plugin_configs();
		$gu_themes  = Singleton::get_instance( 'Fragen\Git_Updater\Theme', $this )->get_theme_configs();
		$gu_tokens  = array_merge( $gu_plugins, $gu_themes );

		$plugin_updates = get_site_option( 'git_updater_plugin_updates' );
		$theme_updates  = get_site_option( 'git_updater_theme_updates' );

		$site    = $request->get_header( 'host' );
		$api_url = add_query_arg(
			[
				'key' => $request->get_param( 'key' ),
			],
			home_url( 'wp-json/' . self::$namespace . '/update/' )
		);
		foreach ( $gu_tokens as $token ) {
			$update_package = false;
			if ( 'plugin' === $token->type && array_key_exists( $token->file, (array) $plugin_updates ) ) {
				$update_package = $plugin_updates[ $token->file ];
			}
			if ( 'theme' === $token->type && array_key_exists( $token->slug, (array) $theme_updates ) ) {
				$update_package = $theme_updates[ $token->slug ];
			}
			$slugs[] = [
				'slug'           => $token->slug,
				'type'           => $token->type,
				'primary_branch' => $token->primary_branch,
				'branch'         => $token->branch,
				'version'        => $token->local_version,
				'update_package' => $update_package,
			];
		}
		$json = [
			'sites' => [
				'site'          => $site,
				'restful_start' => $api_url,
				'slugs'         => $slugs,
			],
		];

		return $json;
	}

	/**
	 * Get specific repo plugin API data.
	 *
	 * Returns data consistent with `plugins_api()` request.
	 *
	 * @param \WP_REST_Request $request REST API response.
	 *
	 * @return array|\WP_Error
	 */
	public function get_plugins_api_data( \WP_REST_Request $request ) {
		$slug     = $request->get_param( 'slug' );
		$download = $request->get_param( 'download' );
		$download = 'true' === $download || '1' === $download ? true : false;
		if ( ! $slug ) {
			return (object) [ 'error' => 'The REST request likely has an invalid query argument. It requires a `slug`.' ];
		}
		if ( false === $download && true === $download ) {
			return (object) [ 'error' => 'The REST request likely has an invalid query argument. It requires a boolean for `download`.' ];
		}
		$repo_cache = $this->get_repo_cache( $slug );
		$gu_plugins = Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this )->get_plugin_configs();

		if ( ! \array_key_exists( $slug, $gu_plugins ) ) {
			return (object) [ 'error' => 'Specified plugin does not exist.' ];
		}

		add_filter( 'gu_disable_wpcron', '__return_false' );
		$repo_data = Singleton::get_instance( 'Fragen\Git_Updater\Base', $this )->get_remote_repo_meta( $gu_plugins[ $slug ] );

		if ( ! is_object( $repo_data ) ) {
			return (object) [ 'error' => 'Plugin data response is incorrect.' ];
		}

		$plugins_api_data = [
			'name'              => $repo_data->name,
			'slug'              => $repo_data->slug,
			'git'               => $repo_data->git,
			'type'              => $repo_data->type,
			'version'           => $repo_data->remote_version,
			'author'            => $repo_data->author,
			'contributors'      => $repo_data->contributors,
			'requires'          => $repo_data->requires,
			'tested'            => $repo_data->tested,
			'requires_php'      => $repo_data->requires_php,
			'sections'          => $repo_data->sections,
			// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
			'short_description' => substr( strip_tags( trim( $repo_data->sections['description'] ) ), 0, 175 ) . '...',
			'primary_branch'    => $repo_data->primary_branch,
			'branch'            => $repo_data->branch,
			'download_link'     => $repo_data->download_link,
			'banners'           => $repo_data->banners,
			'icons'             => $repo_data->icons,
			'last_updated'      => $repo_data->last_updated,
			'num_ratings'       => $repo_data->num_ratings,
			'rating'            => $repo_data->rating,
			'active_installs'   => $repo_data->downloaded,
			'homepage'          => $repo_data->homepage,
			'external'          => 'xxx',
		];

		if ( $repo_data->release_asset ) {
			if ( property_exists( $repo_cache['release_asset_response'], 'browser_download_url' ) ) {
				$plugins_api_data['download_link']   = $repo_cache['release_asset_response']->browser_download_url;
				$plugins_api_data['active_installs'] = $repo_cache['release_asset_response']->download_count;
			} elseif ( $repo_cache['release_asset'] ) {
				$plugins_api_data['download_link'] = $repo_cache['release_asset'];
			}
		}
		if ( ! $download ) {
			$plugins_api_data['download_link']         = '';
			$plugins_api_data['sections']['changelog'] = "Refer to <a href='https://github.com/afragen/git-updater/blob/master/CHANGES.md'>changelog</a>";
		}

		return $plugins_api_data;
	}

	/**
	 * Reset branch of plugin/theme by removing from saved options.
	 *
	 * @param \WP_REST_Request $request REST API response.
	 *
	 * @throws \UnexpectedValueException Under multiple bad or missing params.
	 * @return void
	 */
	public function reset_branch( \WP_REST_Request $request ) {
		$rest_update = new Rest_Update();
		$start       = microtime( true );

		try {
			// Test for API key and exit if incorrect.
			if ( $this->get_class_vars( 'Remote_Management', 'api_key' ) !== $request->get_param( 'key' ) ) {
				throw new \UnexpectedValueException( 'Bad API key. No branch reset for you.' );
			}

			$plugin_slug = $request->get_param( 'plugin' );
			$theme_slug  = $request->get_param( 'theme' );
			$options     = $this->get_class_vars( 'Base', 'options' );
			$slug        = ! empty( $plugin_slug ) ? $plugin_slug : $theme_slug;

			if ( empty( $plugin_slug ) && empty( $theme_slug ) || ! isset( $options[ $slug ] ) ) {
				throw new \UnexpectedValueException( 'No plugin or theme specified for branch reset.' );
			}

			$this->set_repo_cache( 'current_branch', '', $slug );
			unset( $options[ "current_branch_$slug" ] );
			update_site_option( 'git_updater', $options );

			$response = [
				'success'      => true,
				'messages'     => 'Reset to primary branch complete.',
				'webhook'      => $_GET, // phpcs:ignore WordPress.Security.NonceVerification
				'elapsed_time' => round( ( microtime( true ) - $start ) * 1000, 2 ) . ' ms',
			];
			$rest_update->log_exit( $response, 200 );

		} catch ( \Exception $e ) {
			$response = [
				'success'      => false,
				'messages'     => $e->getMessage(),
				'webhook'      => $_GET, // phpcs:ignore WordPress.Security.NonceVerification
				'elapsed_time' => round( ( microtime( true ) - $start ) * 1000, 2 ) . ' ms',
			];
			$rest_update->log_exit( $response, 417 );
		}
	}
}
