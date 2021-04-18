<?php
/**
 * Git Updater PRO
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater-pro
 * @package  git-updater-pro
 */

namespace Fragen\Git_Updater\PRO\REST;

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
	 * Register REST API endpoints.
	 *
	 * @return void
	 */
	public function register_endpoints() {
		register_rest_route(
			self::$namespace,
			'test',
			[
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

		$update_args = [
			'key'        => [
				'default'           => false,
				'required'          => true,
				'validate_callback' => 'sanitize_text_field',
			],
			'plugin'     => [
				'default'           => false,
				'validate_callback' => 'sanitize_text_field',
			],
			'theme'      => [
				'default'           => false,
				'validate_callback' => 'sanitize_text_field',
			],
			'tag'        => [
				'default'           => 'master',
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
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'deprecated' ],
				'permission_callback' => '__return_true',
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
		return 'Connected to Git Updater PRO!';
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
}
