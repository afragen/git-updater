<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  GPL-3.0-or-later
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
	 * Load hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_action( 'rest_api_init', [ $this, 'register_endpoints' ] );

		/*
		 * Deprecated AJAX request. The `nopriv` variant is intentional — external CI / webhook
		 * callers POST here without a logged-in session. Authentication is the shared API key,
		 * validated inside Rest_Update::process_request() with hash_equals(). The response must
		 * not echo the inbound query string back to the caller (see the `webhook` field, which
		 * is built from a curated allow-list, never $_GET). Do not weaken this without
		 * revisiting the threat model.
		 */
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

		$download_args = [
			'slug'      => [
				'required'          => true,
				'validate_callback' => 'sanitize_title_with_dashes',
			],
			'expires'   => [
				'required'          => true,
				'validate_callback' => function ( $value ) {
					return is_numeric( $value );
				},
			],
			'signature' => [
				'required'          => true,
				'validate_callback' => function ( $value ) {
					return preg_match( '/^[a-f0-9]{64}$/', $value );
				},
			],
		];

		register_rest_route(
			self::$namespace,
			'/download/(?P<slug>[a-z0-9-]+)',
			[
				[
					'show_in_index'       => false,
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'proxy_download' ],
					'permission_callback' => '__return_true',
					'args'                => $download_args,
				],
				[
					'show_in_index'       => false,
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'proxy_download' ],
					'permission_callback' => '__return_true',
					'args'                => $download_args,
				],
			]
		);

		register_rest_route(
			self::$namespace,
			'/download-token/(?P<slug>[a-z0-9-]+)',
			[
				[
					'show_in_index'       => false,
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_download_token' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'slug' => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_title_with_dashes',
						],
					],
				],
			]
		);

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
						'key'  => [
							'default'           => null,
							'required'          => true,
							'validate_callback' => 'sanitize_text_field',
						],
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
					'callback'            => [ new Rest_Update(), 'process_request' ],
					'permission_callback' => '__return_true',
					'args'                => $update_args,
				],
				[
					'show_in_index'       => false,
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ new Rest_Update(), 'process_request' ],
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
					'callback'            => [ new Rest_Update(), 'process_request' ],
					'permission_callback' => '__return_true',
					'args'                => $update_args,
				],
				[
					'show_in_index'       => false,
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ new Rest_Update(), 'process_request' ],
					'permission_callback' => '__return_true',
					'args'                => $update_args,
				],
			]
		);
	}

	/**
	 * Return deprecation notice.
	 *
	 * @return array<string, mixed>
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
	 * @return array<string, string>
	 */
	public function get_namespace() {
		return [ 'namespace' => self::$namespace ];
	}

	/**
	 * Get repo data for Git Remote Updater.
	 *
	 * @param WP_REST_Request $request REST API response.
	 *
	 * @return array<string, mixed>
	 */
	public function get_remote_repo_data( WP_REST_Request $request ) {
		// Test for API key and exit if incorrect.
		if ( ! hash_equals( (string) $this->get_class_vars( 'Remote_Management', 'api_key' ), (string) $request->get_param( 'key' ) ) ) {
			return [ 'error' => 'Bad API key. No repo data for you.' ];
		}
		$slugs      = [];
		$gu_plugins = Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this )->get_plugin_configs();
		$gu_themes  = Singleton::get_instance( 'Fragen\Git_Updater\Theme', $this )->get_theme_configs();
		$gu_tokens  = array_merge( $gu_plugins, $gu_themes );

		wp_update_plugins();
		$current        = get_site_transient( 'update_plugins' );
		$plugin_updates = $current->response ?? [];

		wp_update_themes();
		$current       = get_site_transient( 'update_themes' );
		$theme_updates = $current->response ?? [];

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
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_api_data( WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );
		if ( ! $slug ) {
			return [ 'error' => 'The REST request likely has an invalid query argument. It requires a `slug`.' ];
		}
		$channel    = null !== $request->get_param( 'channel' );
		$gu_plugins = Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this )->get_plugin_configs();
		$gu_themes  = Singleton::get_instance( 'Fragen\Git_Updater\Theme', $this )->get_theme_configs();
		$gu_repos   = array_merge( $gu_plugins, $gu_themes );

		// Don't allow non-shared repos via this API. Set via Additions tab.
		$additions = get_site_option( 'git_updater_additions', [] );
		foreach ( $additions as $addition ) {
			$addition_slug = str_contains( $addition['type'], 'plugin' ) ? dirname( $addition['slug'] ) : $addition['slug'];

			if ( $addition_slug === $slug ) {
				if ( isset( $addition['private_package'] ) && true === (bool) $addition['private_package'] ) {
					return [ 'error' => 'Specified repo is not shared.' ];
				}
			}
		}

		if ( ! array_key_exists( $slug, $gu_repos ) ) {
			return [ 'error' => 'Specified repo does not exist.' ];
		}

		add_filter( 'gu_disable_wpcron', '__return_false' );
		$repo_data = Singleton::get_instance( 'Fragen\Git_Updater\Base', $this )->get_remote_repo_meta( $gu_repos[ $slug ] );

		if ( ! is_object( $repo_data ) || '0.0.0' === $repo_data->remote_version ) {
			$rate_limit = 'github' === $repo_data->git ? $this->get_github_rate_limit_headers() : [];
			return [
				'error'      => 'API data response is incorrect.',
				'rate_limit' => $rate_limit,
			];
		}

		// Get release assets and dev release assets.
		$release_assets     = $repo_data->release_assets ?? [];
		$dev_release_assets = $repo_data->dev_release_assets ?? [];

		// Is dev channel more current than stable?
		$current_asset_version     = array_key_first( $release_assets ) ?? '';
		$current_dev_asset_version = array_key_first( $dev_release_assets ) ?? '';
		$use_channel               = version_compare( $current_asset_version, $current_dev_asset_version, '<' );

		// Set remote version based on channel selection.
		$remote_version = $repo_data->remote_version;
		if ( $repo_data->release_asset && $channel && $use_channel ) {
			$remote_version = $current_dev_asset_version;
			$remote_version = ltrim( $remote_version, 'v' );
		}

		$last_updated = ! empty( $repo_data->created_at ) ? reset( $repo_data->created_at ) : $repo_data->last_updated;

		$last_updated = $channel && $use_channel && ! empty( $repo_data->dev_created_at ) ? reset( $repo_data->dev_created_at ) : $last_updated;

		// Get versions from release assets or tags. Limit to 20.
		if ( $repo_data->release_asset ) {
			$versions = $channel && $use_channel ? $dev_release_assets : $release_assets;
		} else {
			$versions = $repo_data->tags ?? [];
		}
		$versions = array_slice( (array) $versions, 0, 20, true );

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
			'dev_channel'       => $channel,
			'use_dev_channel'   => $channel && $use_channel,
			'release_asset'     => $repo_data->release_asset,
			'version'           => $remote_version,
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
			'versions'          => $versions,
			'created_at'        => $channel && $use_channel ? $repo_data->dev_created_at : $repo_data->created_at,
			'donate_link'       => $repo_data->donate_link,
			'banners'           => $repo_data->banners,
			'icons'             => $repo_data->icons,
			'last_updated'      => gmdate( 'Y-m-d h:ia T', strtotime( $last_updated ) ),
			'added'             => gmdate( 'Y-m-d', strtotime( $repo_data->added ) ),
			'num_ratings'       => $repo_data->num_ratings,
			'rating'            => $repo_data->rating,
			'active_installs'   => $repo_data->downloaded,
			'homepage'          => $repo_data->homepage,
			'external'          => 'xxx',
		];
		uksort( $repo_api_data['versions'], fn ( $a, $b ) => version_compare( $b, $a ) );

		$repo_cache = $this->get_repo_cache( $slug, false );
		$api        = Singleton::get_instance( 'Fragen\Git_Updater\API\API', $this );

		// Update release asset download link.
		if ( $repo_data->release_asset ) {
			if ( ( isset( $repo_cache['release_asset_download'] )
				&& ! isset( $repo_cache['release_asset_redirect'] ) )
				&& 'bitbucket' !== $repo_api_data['git']
			) {
				$repo_api_data['download_link'] = $channel && $use_channel && ! empty( $versions )
					? reset( $versions )
					: $repo_cache['release_asset_download'];
			} elseif ( isset( $repo_cache['release_asset'] ) && $repo_cache['release_asset'] ) {
				$repo_api_data['download_link'] = Singleton::get_instance( 'Fragen\Git_Updater\API\API', $this )->get_release_asset_redirect( $repo_cache['release_asset'], true, true );
			}
		}

		// All endpoints: use signed proxy URL for repos that need auth,
		// and never expose auth tokens to clients.
		$needs_proxy = $repo_api_data['is_private']
			|| ! empty( $this->get_class_vars( 'API\API', 'options' )[ $slug ] )
			|| in_array( $repo_api_data['git'], [ 'gitlab', 'gitea' ], true )
			|| $this->has_uses_lite( $slug );

		if ( $needs_proxy && ! empty( $repo_api_data['download_link'] ) ) {
			// Strictly isolate the token URL to the git-updater-lite update-api route.
			if ( str_contains( $request->get_route(), 'update-api' ) ) {
				$repo_api_data['download_link'] = rest_url( self::$namespace . '/download-token/' . rawurlencode( $slug ) );
			} else {
				// Main plugin continues to get the direct signed URL (12-hour default).
				$repo_api_data['download_link'] = $this->sign_download_url( $slug );
			}
		}

		return $repo_api_data;
	}

	/**
	 * Get Additions plugin|theme API data.
	 *
	 * @param WP_REST_Request $request REST API response.
	 *
	 * @return array<string, mixed>
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
	 * @return array<int, array<string, mixed>>
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
		// Test for API key and exit if incorrect.
		if ( ! hash_equals( (string) $this->get_class_vars( 'Remote_Management', 'api_key' ), (string) $request->get_param( 'key' ) ) ) {
			return (object) [ 'error' => 'Bad API key. No flush for you.' ];
		}

		$slug = $request->get_param( 'slug' );
		if ( ! $slug ) {
			return (object) [ 'error' => 'The REST request likely has an invalid query argument. It requires a `slug`.' ];
		}
		$cache_key = $this->get_cache_key( $slug );
		$flush     = delete_site_option( $cache_key );
		$message   = $flush
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
	 * Check if a slug is flagged as using git-updater-lite in Additions.
	 *
	 * @param string $slug The package slug (folder name for plugins, theme slug for themes).
	 *
	 * @return bool
	 */
	private function has_uses_lite( string $slug ): bool {
		$additions = get_site_option( 'git_updater_additions', [] );
		foreach ( $additions as $addition ) {
			$addition_slug = str_contains( $addition['type'], 'plugin' ) ? dirname( $addition['slug'] ) : $addition['slug'];

			if ( $addition_slug === $slug && ! empty( $addition['uses_lite'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Generate a signed download URL for the proxy endpoint.
	 *
	 * @param string $slug          The package slug.
	 * @param int    $ttl_seconds   Time-to-live in seconds. Default 43200 (12 hours).
	 *
	 * @return string
	 */
	private function sign_download_url( string $slug, int $ttl_seconds = 43200 ): string {
		$expires   = time() + $ttl_seconds;
		$payload   = $slug . '|' . $expires;
		$secret    = wp_salt( 'auth' );
		$signature = hash_hmac( 'sha256', $payload, $secret );

		return add_query_arg(
			[
				'expires'   => $expires,
				'signature' => $signature,
			],
			rest_url( self::$namespace . '/download/' . rawurlencode( $slug ) )
		);
	}

	/**
	 * Verify a signed download request.
	 *
	 * @param string $slug      The package slug.
	 * @param int    $expires   Unix timestamp when the signature expires.
	 * @param string $signature The HMAC-SHA256 signature to verify.
	 *
	 * @return bool
	 */
	private function verify_download_signature( string $slug, int $expires, string $signature ): bool {
		if ( $expires < time() ) {
			return false;
		}
		$payload  = $slug . '|' . $expires;
		$secret   = wp_salt( 'auth' );
		$expected = hash_hmac( 'sha256', $payload, $secret );

		$valid = hash_equals( $expected, $signature );

		if ( ! $valid ) {
			error_log(
				sprintf(
					'git-updater verify_signature: slug=%s, expires=%d, sig=%s, expected=%s, secret_len=%d',
					$slug,
					$expires,
					$signature,
					$expected,
					strlen( $secret )
				)
			);
		}

		return $valid;
	}

	/**
	 * Generate a short-lived download token for git-updater-lite.
	 *
	 * @param WP_REST_Request $request REST API request with slug.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_download_token( WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );

		// 1. Optional Server-Centric Domain Validation
		$incoming_domain    = sanitize_text_field( $request->get_header( 'X-GU-Site-Domain' ) );
		$authorized_domains = (array) apply_filters( 'git_updater_lite_authorized_domains', [], $slug );

		if ( ! empty( $authorized_domains ) ) {
			$domain_valid = false;
			foreach ( $authorized_domains as $base_domain ) {
				$base_domain = strtolower( trim( $base_domain ) );
				// Matches exact domain OR any subdomain (e.g., staging.example.com, www.example.com).
				if ( $incoming_domain === $base_domain || str_ends_with( $incoming_domain, '.' . $base_domain ) ) {
					$domain_valid = true;
					break;
				}
			}

			if ( ! $domain_valid ) {
				return new WP_Error(
					'gu_unauthorized_domain',
					'Domain not authorized for this package. Please contact the plugin developer.',
					[ 'status' => 403 ]
				);
			}
		}

		// 2. Standard Repo Validation (handles private_package checks, etc.)
		$repo_api_data = $this->build_download_metadata( $slug );
		if ( is_wp_error( $repo_api_data ) ) {
			return $repo_api_data;
		}

		// 3. Generate and return short-lived signed URL (60 seconds for lite)
		$signed_url = $this->sign_download_url( $slug, 60 );
		return rest_ensure_response( [ 'download_link' => $signed_url ] );
	}

	/**
	 * Proxy download endpoint: fetches the package from the upstream
	 * provider using stored auth tokens and streams it to the client.
	 *
	 * @param WP_REST_Request $request REST API request with slug, expires, signature.
	 *
	 * @return WP_Error|void
	 */
	public function proxy_download( WP_REST_Request $request ) {
		$slug    = $request->get_param( 'slug' );
		$expires = (int) $request->get_param( 'expires' );
		$signature = $request->get_param( 'signature' );

		if ( ! $this->verify_download_signature( $slug, $expires, $signature ) ) {
			return new WP_Error(
				'gu_invalid_signature',
				'Download link has expired or is invalid.',
				[ 'status' => 403 ]
			);
		}

		try {
			// Resolve upstream URL + auth headers server-side.
			$repo_api_data = $this->build_download_metadata( $slug );
			if ( is_wp_error( $repo_api_data ) ) {
				error_log(
					sprintf(
						'git-updater proxy_download: build_download_metadata failed for slug=%s, code=%s, message=%s',
						$slug,
						$repo_api_data->get_error_code(),
						$repo_api_data->get_error_message()
					)
				);
				return $repo_api_data;
			}

			$download_url = $repo_api_data['download_link'] ?? '';
			$auth_args    = $repo_api_data['auth_header'] ?? [];

			if ( empty( $download_url ) ) {
				return new WP_Error(
					'gu_no_download_link',
					'No download link available for this package.',
					[ 'status' => 404 ]
				);
			}

			// Create temp file and register cleanup in case of fatal error or disconnect.
			$temp_file = wp_tempnam( "gu_download_{$slug}" );
			$this->register_temp_file_cleanup( $temp_file );

			// Fetch upstream with auth headers (Authorization + Accept + identification).
			$upstream_args = array_merge(
				$auth_args,
				[
					'stream'   => true,
					'filename' => $temp_file,
				]
			);
			$upstream      = wp_remote_get( $download_url, $upstream_args );

			if ( is_wp_error( $upstream ) ) {
				error_log(
					sprintf(
						'git-updater proxy_download: upstream fetch failed for slug=%s, url=%s, error=%s',
						$slug,
						$download_url,
						$upstream->get_error_message()
					)
				);
				return new WP_Error(
					'gu_upstream_error',
					'Failed to download package from upstream: ' . $upstream->get_error_message(),
					[ 'status' => 502 ]
				);
			}

			$status_code = wp_remote_retrieve_response_code( $upstream );
			if ( 200 !== $status_code ) {
				error_log(
					sprintf(
						'git-updater proxy_download: upstream returned HTTP %d for slug=%s, url=%s',
						$status_code,
						$slug,
						$download_url
					)
				);
				return new WP_Error(
					'gu_upstream_http_error',
					"Upstream returned HTTP {$status_code}.",
					[ 'status' => 502 ]
				);
			}

			// Validate that upstream returned a zip file, not an HTML error page.
			$fp   = fopen( $temp_file, 'rb' );
			$head = fread( $fp, 4 );
			fclose( $fp );

			if ( $head !== "PK\x03\x04" ) {
				wp_delete_file( $temp_file );
				return new WP_Error(
					'gu_not_a_zip',
					'Upstream did not return a valid zip file.',
					[ 'status' => 502 ]
				);
			}

			// Stream binary zip directly — WP_REST_Response JSON-encodes the body.
			$this->send_file( $temp_file, sanitize_file_name( $slug . '.zip' ) );
		} catch ( \Throwable $e ) {
			error_log(
				sprintf(
					'git-updater proxy_download: exception for slug=%s: %s in %s:%d',
					$slug,
					$e->getMessage(),
					$e->getFile(),
					$e->getLine()
				)
			);
			return new WP_Error(
				'gu_proxy_exception',
				'Proxy download failed: ' . $e->getMessage(),
				[ 'status' => 502 ]
			);
		}
	}

	/**
	 * Send a file as a binary download response and terminate.
	 *
	 * Protected so tests can override to capture content without calling exit.
	 *
	 * @param string $file     Absolute path to the file.
	 * @param string $filename Download filename for Content-Disposition.
	 *
	 * @return void
	 *
	 * @codeCoverageIgnore — overridden in tests; production calls exit.
	 */
	protected function send_file( string $file, string $filename ): void {
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $file ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Transfer-Encoding: binary' );

		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		wp_delete_file( $file );
		exit;
	}

	/**
	 * Register a shutdown function to clean up a temp file.
	 *
	 * @codeCoverageIgnore — shutdown callbacks cannot be tested without terminating PHP.
	 *
	 * @param string $temp_file Absolute path to the temp file.
	 *
	 * @return void
	 */
	private function register_temp_file_cleanup( string $temp_file ): void {
		register_shutdown_function(
			function () use ( $temp_file ) {
				if ( file_exists( $temp_file ) ) {
					wp_delete_file( $temp_file );
				}
			}
		);
	}

	/**
	 * Resolve the upstream download URL and auth headers for a slug.
	 * Used internally by the download proxy — never exposed to clients.
	 *
	 * @param string $slug The package slug.
	 *
	 * @return array<string, mixed>|WP_Error { download_link, auth_header } or error.
	 */
	protected function build_download_metadata( string $slug ): array|WP_Error {
		$gu_plugins = Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this )->get_plugin_configs();
		$gu_themes  = Singleton::get_instance( 'Fragen\Git_Updater\Theme', $this )->get_theme_configs();
		$gu_repos   = array_merge( $gu_plugins, $gu_themes );

		// Don't allow non-shared repos via this API. Set via Additions tab.
		$additions = get_site_option( 'git_updater_additions', [] );
		foreach ( $additions as $addition ) {
			$addition_slug = str_contains( $addition['type'], 'plugin' ) ? dirname( $addition['slug'] ) : $addition['slug'];

			if ( $addition_slug === $slug ) {
				if ( isset( $addition['private_package'] ) && true === (bool) $addition['private_package'] ) {
					return new WP_Error(
						'gu_private_package',
						'Specified repo is not shared.',
						[ 'status' => 403 ]
					);
				}
			}
		}

		if ( ! array_key_exists( $slug, $gu_repos ) ) {
			return new WP_Error(
				'gu_repo_not_found',
				'Specified repo does not exist.',
				[ 'status' => 404 ]
			);
		}

		add_filter( 'gu_disable_wpcron', '__return_false' );
		$repo_data = Singleton::get_instance( 'Fragen\Git_Updater\Base', $this )->get_remote_repo_meta( $gu_repos[ $slug ] );

		if ( ! is_object( $repo_data ) || '0.0.0' === $repo_data->remote_version ) {
			return new WP_Error(
				'gu_api_error',
				'API data response is incorrect.',
				[ 'status' => 502 ]
			);
		}

		$download_link = $repo_data->download_link ?? '';

		// Build auth headers server-side (Authorization + Accept + identification).
		$auth_header = [];
		if ( $download_link ) {
			$api         = Singleton::get_instance( 'Fragen\Git_Updater\API\API', $this );
			$auth_header = $api->add_auth_header( [], $download_link );
			$auth_header = $api->unset_release_asset_auth( $auth_header, $download_link );
			$auth_header = $api->add_accept_header( $auth_header, $download_link );
		}

		// Override download_link for release assets.
		if ( $repo_data->release_asset ) {
			$repo_cache = $this->get_repo_cache( $slug, false );

			if ( ( isset( $repo_cache['release_asset_download'] )
				&& ! isset( $repo_cache['release_asset_redirect'] ) )
				&& 'bitbucket' !== $repo_data->git
			) {
				$download_link = $repo_cache['release_asset_download'];
			} elseif ( isset( $repo_cache['release_asset'] ) && $repo_cache['release_asset'] ) {
				$download_link = Singleton::get_instance( 'Fragen\Git_Updater\API\API', $this )->get_release_asset_redirect( $repo_cache['release_asset'], true, true );
				$auth_header   = [];
			}
		}

		$result = [
			'download_link' => $download_link,
		];

		if ( ! empty( $auth_header['headers'] ) ) {
			$result['auth_header'] = $auth_header;
		}

		return $result;
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
		$echo_args   = [
			'plugin' => $request->get_param( 'plugin' ),
			'theme'  => $request->get_param( 'theme' ),
		];

		try {
			// Test for API key and exit if incorrect.
			if ( ! hash_equals( (string) $this->get_class_vars( 'Remote_Management', 'api_key' ), (string) $request->get_param( 'key' ) ) ) {
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
				'webhook'      => $echo_args,
				'elapsed_time' => round( ( microtime( true ) - $start ) * 1000, 2 ) . ' ms',
			];
			$rest_update->log_exit( $response, 200 );

		} catch ( Exception $e ) {
			$response = [
				'success'      => false,
				'messages'     => $e->getMessage(),
				'webhook'      => $echo_args,
				'elapsed_time' => round( ( microtime( true ) - $start ) * 1000, 2 ) . ' ms',
			];
			$rest_update->log_exit( $response, 418 );
		}
	}
}
