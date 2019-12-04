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
			array(
				'methods'  => \WP_REST_Server::READABLE,
				'callback' => array( $this, 'test' ),
			)
		);

		register_rest_route(
			self::$namespace,
			'repos',
			array(
				'methods'  => \WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_remote_repo_data' ),
				'args'     => array(
					'key' => array(
						'default'           => null,
						'validate_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		$update_args = array(
			'key'        => array(
				'default'           => false,
				'validate_callback' => 'sanitize_text_field',
			),
			'plugin'     => array(
				'default'           => false,
				'validate_callback' => 'sanitize_text_field',
			),
			'theme'      => array(
				'default'           => false,
				'validate_callback' => 'sanitize_text_field',

			),
			'tag'        => array(
				'default'           => 'master',
				'validate_callback' => 'sanitize_text_field',
			),
			'branch'     => array(
				'default'           => false,
				'validate_callback' => 'sanitize_text_field',
			),
			'committish' => array(
				'default'           => false,
				'validate_callback' => 'sanitize_text_field',
			),
			'override'   => array(
				'default' => false,
			),
		);

		register_rest_route(
			self::$namespace,
			'update',
			array(
				array(
					'methods'  => \WP_REST_Server::READABLE,
					'callback' => array( new REST_Update(), 'process_request' ),
					'args'     => $update_args,
				),
				array(
					'methods'  => \WP_REST_Server::CREATABLE,
					'callback' => array( new REST_Update(), 'process_request' ),
					'args'     => $update_args,
				),
			)
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
		$ghu_plugins = Singleton::get_instance( 'Plugin', $this )->get_plugin_configs();
		$ghu_themes  = Singleton::get_instance( 'Theme', $this )->get_theme_configs();
		$ghu_tokens  = array_merge( $ghu_plugins, $ghu_themes );

		$site    = $request->get_header( 'host' );
		$api_url = add_query_arg(
			array(
				'key' => $request->get_param( 'key' ),
			),
			home_url( 'wp-json/' . self::$namespace . '/update/' )
		);
		foreach ( $ghu_tokens as $token ) {
			$slugs[] = array(
				'slug'   => $token->slug,
				'type'   => $token->type,
				'branch' => $token->branch,
			);
		}
		$json = array(
			'sites' => array(
				'site'          => $site,
				'restful_start' => $api_url,
				'slugs'         => $slugs,
			),
		);

		return $json;
	}
}
