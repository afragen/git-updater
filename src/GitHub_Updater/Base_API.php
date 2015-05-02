<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

namespace Fragen\GitHub_Updater;

class Base_API extends Base {

	/**
	 * Fixes {@link https://github.com/UCF/Theme-Updater/issues/3}.
	 * Adds custom user agent for GitHub Updater.
	 *
	 * @param  array $args Existing HTTP Request arguments.
	 *
	 * @return array Amended HTTP Request arguments.
	 */
	public static function http_request_args( $args, $url ) {
		$args['sslverify'] = false;
		if ( false === stristr( $args['user-agent'], 'GitHub Updater' ) ) {
			$args['user-agent'] = $args['user-agent'] . '; GitHub Updater - https://github.com/afragen/github-updater';
		}

		return $args;
	}

	/**
	 * Return repo data for API calls.
	 *
	 * @return array
	 */
	protected function return_repo_type() {
		switch ( $this->type->type ) {
			case ( stristr( $this->type->type, 'github' ) ):
				$arr['repo']          = 'github';
				$arr['base_uri']      = 'https://api.github.com';
				$arr['base_download'] = 'https://github.com';
				break;
			case( stristr( $this->type->type, 'bitbucket' ) ):
				$arr['repo']          = 'bitbucket';
				$arr['base_uri']      = 'https://bitbucket.org/api';
				$arr['base_download'] = 'https://bitbucket.org';
				break;
			case (stristr( $this->type->type, 'gitlab' ) ):
				$arr['repo']          = 'gitlab';
				$arr['base_uri']      = null;
				$arr['base_download'] = null;
				break;
			default:
				$arr = array();
		}

		return $arr;
	}

	/**
	 * Call the API and return a json decoded body.
	 * Create error messages.
	 *
	 * @see http://developer.github.com/v3/
	 *
	 * @param string $url
	 *
	 * @return boolean|object
	 */
	protected function api( $url ) {
		$type          = $this->return_repo_type();
		$response      = wp_remote_get( $this->_get_api_url( $url ) );
		$code          = (integer) wp_remote_retrieve_response_code( $response );
		$allowed_codes = array( 200, 404 );

		if ( is_wp_error( $response ) ) {
			return false;
		}
		if ( ! in_array( $code, $allowed_codes, false ) ) {
			self::$error_code = array_merge(
				self::$error_code,
				array( $this->type->repo => array(
					'repo' => $this->type->repo,
					'code' => $code,
					'name' => $this->type->name,
					)
				) );
			if ( 'github' === $type['repo'] ) {
				GitHub_API::_ratelimit_reset( $response, $this->type->repo );
			}
			Messages::create_error_message();
			return false;
		}

		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Return API url.
	 *
	 * @param string $endpoint
	 *
	 * @return string
	 */
	private function _get_api_url( $endpoint ) {
		$type     = $this->return_repo_type();
		$segments = array(
			'owner' => $this->type->owner,
			'repo'  => $this->type->repo,
		);

		/**
		 * Add or filter the available segments that are used to replace placeholders.
		 *
		 * @param array $segments list of segments.
		 */
		$segments = apply_filters( 'github_updater_api_segments', $segments );

		foreach ( $segments as $segment => $value ) {
			$endpoint = str_replace( '/:' . sanitize_key( $segment ), '/' . sanitize_text_field( $value ), $endpoint );
		}

		if ( 'github' === $type['repo'] ) {
			$endpoint = GitHub_API::add_github_endpoints( $this, $endpoint );
			if ( $this->type->enterprise ) {
				return $endpoint;
			}
		}

		return $type['base_uri'] . $endpoint;
	}

	/**
	 * Validate wp_remote_get response.
	 *
	 * @param $response
	 *
	 * @return bool true if invalid
	 */
	public static function validate_response( $response ) {
		if ( empty( $response ) || isset( $response->message ) ) {
			return true;
		}

		return false;
	}

}