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

use Exception;
use Fragen\Git_Updater\Traits\GU_Trait;
use Fragen\Git_Updater\Additions\Additions;
use Fragen\Singleton;
use stdClass;
use UnexpectedValueException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

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
	 * Variable to hold all repository remote info.
	 *
	 * @access public
	 * @var array
	 */
	public $response = [];

	/**
	 * Load hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_action( 'rest_api_init', [ $this, 'register_endpoints' ] );

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
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'test' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'git-updater',
			'namespace',
			[
				'show_in_index'       => true,
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_namespace' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			self::$namespace,
			'repos',
			[
				'show_in_index'       => false,
				'methods'             => WP_REST_Server::READABLE,
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

		foreach ( [ 'plugins-api', 'themes-api', 'update-api' ] as $route ) {
			register_rest_route(
				self::$namespace,
				$route,
				[
					[
						'show_in_index'       => true,
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => [ $this, 'get_api_data' ],
						'permission_callback' => '__return_true',
						'args'                => [
							'slug' => [
								'default'           => false,
								'required'          => true,
								'validate_callback' => 'sanitize_title_with_dashes',
							],
						],
					],
					[
						'show_in_index'       => false,
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => [ $this, 'get_api_data' ],
						'permission_callback' => '__return_true',
						'args'                => [
							'slug' => [
								'default'           => false,
								'required'          => true,
								'validate_callback' => 'sanitize_title_with_dashes',
							],
						],
					],
				]
			);
		}

		register_rest_route(
			self::$namespace,
			'update-api-additions',
			[
				[
					'show_in_index'       => false,
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_additions_api_data' ],
					'permission_callback' => '__return_true',
					'args'                => [],
				],
				[
					'show_in_index'       => false,
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'get_additions_api_data' ],
					'permission_callback' => '__return_true',
					'args'                => [],
				],
			]
		);

		register_rest_route(
			self::$namespace,
			'get-additions-data',
			[
				[
					'show_in_index'       => false,
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_additions_data' ],
					'permission_callback' => '__return_true',
					'args'                => [],
				],
				[
					'show_in_index'       => false,
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'get_additions_data' ],
					'permission_callback' => '__return_true',
					'args'                => [],
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
			'flush-repo-cache',
			[
				[
					'show_in_index'       => true,
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'flush_repo_cache' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'slug' => [
							'default'           => false,
							'required'          => true,
							'validate_callback' => 'sanitize_title_with_dashes',
						],
					],
				],
				[
					'show_in_index'       => false,
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'flush_repo_cache' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'slug' => [
							'default'           => false,
							'required'          => true,
							'validate_callback' => 'sanitize_title_with_dashes',
						],
					],
				],
			]
		);

		register_rest_route(
			self::$namespace,
			'update',
			[
				[
					'show_in_index'       => true,
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ new REST_Update(), 'process_request' ],
					'permission_callback' => '__return_true',
					'args'                => $update_args,
				],
				[
					'show_in_index'       => false,
					'methods'             => WP_REST_Server::CREATABLE,
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
				'methods'             => WP_REST_Server::READABLE,
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
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'deprecated' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'github-updater/v1',
			'repos',
			[
				'methods'             => WP_REST_Server::READABLE,
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
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ new REST_Update(), 'process_request' ],
					'permission_callback' => '__return_true',
					'args'                => $update_args,
				],
				[
					'show_in_index'       => false,
					'methods'             => WP_REST_Server::CREATABLE,
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
	 * @param WP_REST_Request $request REST API response.
	 *
	 * @return array
	 */
	public function get_remote_repo_data( WP_REST_Request $request ) {
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
	 * Get specific repo plugin|theme API data.
	 *
	 * Returns data consistent with `plugins_api()` or `themes_api()` request.
	 *
	 * @param WP_REST_Request $request REST API response.
	 *
	 * @return array|WP_Error
	 */
	public function get_api_data( WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );
		if ( ! $slug ) {
			return (object) [ 'error' => 'The REST request likely has an invalid query argument. It requires a `slug`.' ];
		}
		$gu_plugins = Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this )->get_plugin_configs();
		$gu_themes  = Singleton::get_instance( 'Fragen\Git_Updater\Theme', $this )->get_theme_configs();
		$gu_repos   = array_merge( $gu_plugins, $gu_themes );

		if ( ! array_key_exists( $slug, $gu_repos ) ) {
			return (object) [ 'error' => 'Specified repo does not exist.' ];
		}

		add_filter( 'gu_disable_wpcron', '__return_false' );
		$repo_data = Singleton::get_instance( 'Fragen\Git_Updater\Base', $this )->get_remote_repo_meta( $gu_repos[ $slug ] );

		if ( ! is_object( $repo_data ) || '0.0.0' === $repo_data->remote_version ) {
			return (object) [ 'error' => 'API data response is incorrect.' ];
		}

		$repo_api_data = [
			'did'               => $repo_data->did,
			'name'              => $repo_data->name,
			'slug'              => $repo_data->slug,
			'slug_did'          => $repo_data->slug_did,
			'git'               => $repo_data->git,
			'type'              => $repo_data->type,
			'url'               => $repo_data->uri,
			'update_uri'        => $repo_data->update_uri ?? '',
			'is_private'        => $repo_data->is_private,
			'dot_org'           => $repo_data->dot_org,
			'release_asset'     => $repo_data->release_asset,
			'version'           => $repo_data->remote_version,
			'author'            => $repo_data->author,
			'author_uri'        => $repo_data->author_uri ?? '',
			'security'          => $repo_data->security ?? '',
			'license'           => $repo_data->license ?? '',
			'contributors'      => $repo_data->contributors,
			'requires'          => $repo_data->requires,
			'tested'            => $repo_data->tested,
			'requires_php'      => $repo_data->requires_php,
			'requires_plugins'  => $repo_data->requires_plugins,
			'sections'          => $repo_data->sections,
			// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
			'short_description' => substr( strip_tags( trim( $repo_data->sections['description'] ) ), 0, 147 ) . '...',
			'primary_branch'    => $repo_data->primary_branch,
			'branch'            => $repo_data->branch,
			'download_link'     => $repo_data->download_link ?? '',
			'tags'              => $repo_data->readme_tags ?? [],
			'versions'          => $repo_data->release_asset ? $repo_data->release_assets : $repo_data->tags,
			'donate_link'       => $repo_data->donate_link,
			'banners'           => $repo_data->banners,
			'icons'             => $repo_data->icons,
			'last_updated'      => gmdate( 'Y-m-d h:ia T', strtotime( $repo_data->last_updated ) ),
			'added'             => gmdate( 'Y-m-d', strtotime( $repo_data->added ) ),
			'num_ratings'       => $repo_data->num_ratings,
			'rating'            => $repo_data->rating,
			'active_installs'   => $repo_data->downloaded,
			'homepage'          => $repo_data->homepage,
			'external'          => 'xxx',
		];
		if ( ! is_wp_error( $repo_api_data['versions'] ) ) {
			uksort( $repo_api_data['versions'], fn ( $a, $b ) => version_compare( $b, $a ) );
		}

		$repo_cache = $this->get_repo_cache( $slug );
		Singleton::get_instance( 'Fragen\Git_Updater\API\API', $this )->response = $repo_cache;

		// Add HTTP headers.
		if ( $repo_api_data['download_link'] ) {
			$repo_api_data['auth_header'] = Singleton::get_instance( 'Fragen\Git_Updater\API\API', $this )->add_auth_header( [], $repo_api_data['download_link'] );
			$repo_api_data['auth_header'] = Singleton::get_instance( 'Fragen\Git_Updater\API\API', $this )->unset_release_asset_auth( $repo_api_data['auth_header'], $repo_api_data['download_link'] );
			$repo_api_data['auth_header'] = Singleton::get_instance( 'Fragen\Git_Updater\API\API', $this )->add_accept_header( $repo_api_data['auth_header'], $repo_api_data['download_link'] );
		}

		// Update release asset download link .
		if ( $repo_data->release_asset ) {
			if ( ( isset( $repo_cache['release_asset_download'] )
				|| ! isset( $repo_cache['release_asset_redirect'] ) )
				&& 'bitbucket' !== $repo_api_data['git']
			) {
				$repo_api_data['download_link'] = $repo_cache['release_asset_download'];
			} elseif ( isset( $repo_cache['release_asset'] ) && $repo_cache['release_asset'] ) {
				$_REQUEST['override']           = true;
				$repo_api_data['download_link'] = Singleton::get_instance( 'Fragen\Git_Updater\API\API', $this )->get_release_asset_redirect( $repo_cache['release_asset'], true );
				unset( $repo_api_data['auth_header'] );
			}
		}

		if ( ! $repo_api_data['is_private']
			&& ! in_array( $repo_api_data['git'], [ 'gitlab', 'gitea' ], true )
		) {
			unset( $repo_api_data['auth_header']['headers']['Authorization'] );
		}

		if ( empty( $repo_api_data['auth_header']['headers'] ) ) {
			unset( $repo_api_data['auth_header'] );
		}

		return $repo_api_data;
	}

	/**
	 * Get Additions plugin|theme API data.
	 *
	 * @param WP_REST_Request $request REST API response.
	 *
	 * @return array
	 */
	public function get_additions_api_data( WP_REST_Request $request ) {
		$api_data   = [];
		$gu_plugins = Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this )->get_plugin_configs();
		$gu_themes  = Singleton::get_instance( 'Fragen\Git_Updater\Theme', $this )->get_theme_configs();
		$gu_tokens  = array_merge( $gu_plugins, $gu_themes );
		$additions  = (array) get_site_option( 'git_updater_additions', [] );

		foreach ( $additions as $addition ) {
			$slug = str_contains( $addition['type'], 'plugin' ) ? dirname( $addition['slug'] ) : $addition['slug'];

			if ( isset( $addition['private_package'] ) && true === (bool) $addition['private_package'] ) {
				continue;
			}

			if ( array_key_exists( $slug, $gu_tokens ) ) {
				$file = $gu_tokens[ $slug ]->file;
				$request->set_param( 'slug', $slug );
				$api_data[ $slug ] = $this->get_api_data( $request );
			}
		}

		return $api_data;
	}

	/**
	 * Get Additions data.
	 *
	 * @return array
	 */
	public function get_additions_data() {
		$additions = get_site_option( 'git_updater_additions', [] );
		$additions = ( new Additions() )->deduplicate( $additions );
		$additions = array_filter(
			$additions,
			function ( $addition ) {
				return ! (bool) $addition['private_package'];
			}
		);

		return $additions;
	}

	/**
	 * Flush individual repository cache.
	 *
	 * @param WP_REST_Request $request REST API response.
	 *
	 * @return stdClass
	 */
	public function flush_repo_cache( $request ) {
		$slug = $request->get_param( 'slug' );
		if ( ! $slug ) {
			return (object) [ 'error' => 'The REST request likely has an invalid query argument. It requires a `slug`.' ];
		}
		$flush   = $this->set_repo_cache( $slug, false, $slug );
		$message = $flush
			? [
				'success' => true,
				$slug     => "Repository cache for $slug has been flushed.",
			]
			: [
				'success' => false,
				$slug     => 'Repository cache flush failed.',
			];

		return (object) $message;
	}

	/**
	 * Reset branch of plugin/theme by removing from saved options.
	 *
	 * @param WP_REST_Request $request REST API response.
	 *
	 * @throws UnexpectedValueException Under multiple bad or missing params.
	 * @return void
	 */
	public function reset_branch( WP_REST_Request $request ) {
		$rest_update = new Rest_Update();
		$start       = microtime( true );

		try {
			// Test for API key and exit if incorrect.
			if ( $this->get_class_vars( 'Remote_Management', 'api_key' ) !== $request->get_param( 'key' ) ) {
				throw new UnexpectedValueException( 'Bad API key. No branch reset for you.' );
			}

			$plugin_slug = $request->get_param( 'plugin' );
			$theme_slug  = $request->get_param( 'theme' );
			$options     = $this->get_class_vars( 'Base', 'options' );
			$slug        = ! empty( $plugin_slug ) ? $plugin_slug : $theme_slug;

			if ( ( empty( $plugin_slug ) && empty( $theme_slug ) ) || ! isset( $options[ $slug ] ) ) {
				throw new UnexpectedValueException( 'No plugin or theme specified for branch reset.' );
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

		} catch ( Exception $e ) {
			$response = [
				'success'      => false,
				'messages'     => $e->getMessage(),
				'webhook'      => $_GET, // phpcs:ignore WordPress.Security.NonceVerification
				'elapsed_time' => round( ( microtime( true ) - $start ) * 1000, 2 ) . ' ms',
			];
			$rest_update->log_exit( $response, 418 );
		}
	}
}
