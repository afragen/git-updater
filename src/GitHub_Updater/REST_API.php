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

use Fragen\GitHub_Updater\Traits\GHU_Trait;
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
	use GHU_Trait;

	/**
	 * Holds REST namespace.
	 *
	 * @var string
	 */
	public static $namespace = 'github-updater/v1';

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
				'methods'  => \WP_REST_Server::READABLE,
				'callback' => [ $this, 'test' ],
			]
		);

		register_rest_route(
			self::$namespace,
			'repos',
			[
				'show_in_index' => false,
				'methods'       => \WP_REST_Server::READABLE,
				'callback'      => [ $this, 'get_remote_repo_data' ],
				'args'          => [
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
					'show_in_index' => false,
					'methods'       => \WP_REST_Server::READABLE,
					'callback'      => [ new REST_Update(), 'process_request' ],
					'args'          => $update_args,
				],
				[
					'show_in_index' => false,
					'methods'       => \WP_REST_Server::CREATABLE,
					'callback'      => [ new REST_Update(), 'process_request' ],
					'args'          => $update_args,
				],
			]
		);
	}

	/**
	 * Simple REST endpoint return.
	 *
	 * @return string
	 */
	public function test() {
		return 'Connected to GitHub Updater!';
	}

	/**
	 * Get repo data for Git Remote Updater.
	 *
	 * @param \WP_REST_Request $request REST API response.
	 *
	 * @return string
	 */
	public function get_remote_repo_data( \WP_REST_Request $request ) {
		// Test for API key and exit if incorrect.
		if ( $this->get_class_vars( 'Remote_Management', 'api_key' ) !== $request->get_param( 'key' ) ) {
			return [ 'error' => 'Bad API key. No repo data for you.' ];
		}
		$ghu_plugins = Singleton::get_instance( 'Plugin', $this )->get_plugin_configs();
		$ghu_themes  = Singleton::get_instance( 'Theme', $this )->get_theme_configs();
		$ghu_tokens  = array_merge( $ghu_plugins, $ghu_themes );

		$site    = $request->get_header( 'host' );
		$api_url = add_query_arg(
			[
				'key' => $request->get_param( 'key' ),
			],
			home_url( 'wp-json/' . self::$namespace . '/update/' )
		);
		foreach ( $ghu_tokens as $token ) {
			$slugs[] = [
				'slug'    => $token->slug,
				'type'    => $token->type,
				'branch'  => $token->branch,
				'version' => $token->local_version,
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
