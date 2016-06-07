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
 */
abstract class API extends Base {

	/**
	 * Variable to hold all repository remote info.
	 *
	 * @var array
	 */
	protected $response = array();

	/*
	 * The following functions must be in any repository API.
	 */
	abstract public function get_remote_info( $file );
	abstract public function get_remote_tag();
	abstract public function get_remote_changes( $changes );
	abstract public function get_remote_readme();
	abstract public function get_repo_meta();
	abstract public function get_remote_branches();
	abstract public function construct_download_link();
	abstract protected function add_endpoints( $git, $endpoint );

	/**
	 * Adds custom user agent for GitHub Updater.
	 *
	 * @param  array $args Existing HTTP Request arguments.
	 *
	 * @return array Amended HTTP Request arguments.
	 */
	public static function http_request_args( $args, $url ) {
		$args['sslverify'] = true;
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
		$arr = array();
		switch ( $this->type->type ) {
			case ( stristr( $this->type->type, 'github' ) ):
				$arr['repo']          = 'github';
				$arr['base_uri']      = 'https://api.github.com';
				$arr['base_download'] = 'https://github.com';
				break;
			case ( stristr( $this->type->type, 'bitbucket' ) ):
				$arr['repo']          = 'bitbucket';
				$arr['base_uri']      = 'https://bitbucket.org/api';
				$arr['base_download'] = 'https://bitbucket.org';
				break;
			case ( stristr( $this->type->type, 'gitlab' ) ):
				$arr['repo']          = 'gitlab';
				$arr['base_uri']      = 'https://gitlab.com/api/v3';
				$arr['base_download'] = 'https://gitlab.com';
				break;
		}
		if ( false !== stristr( $this->type->type, 'plugin' ) ) {
			$arr['type'] = 'plugin';
		} elseif ( false !== stristr( $this->type->type, 'theme' ) ) {
			$arr['type'] = 'theme';
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
			Messages::instance()->create_error_message( $response );

			return false;
		}
		if ( ! in_array( $code, $allowed_codes, false ) ) {
			self::$error_code = array_merge(
				self::$error_code,
				array(
					$this->type->repo => array(
						'repo' => $this->type->repo,
						'code' => $code,
						'name' => $this->type->name,
						'git'  => $this->type->type,
					),
				) );
			if ( 'github' === $type['repo'] ) {
				GitHub_API::ratelimit_reset( $response, $this->type->repo );
			}
			Messages::instance()->create_error_message( $type['repo'] );

			return false;
		}

		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Return API url.
	 *
	 * @access private
	 *
	 * @param string $endpoint
	 *
	 * @return string $endpoint
	 */
	private function _get_api_url( $endpoint ) {
		$type     = $this->return_repo_type();
		$segments = array(
			'owner' => $this->type->owner,
			'repo'  => $this->type->repo,
		);

		/*
		 * Add or filter the available segments that are used to replace placeholders.
		 *
		 * @param array $segments list of segments.
		 */
		$segments = apply_filters( 'github_updater_api_segments', $segments );

		foreach ( $segments as $segment => $value ) {
			$endpoint = str_replace( '/:' . sanitize_key( $segment ), '/' . sanitize_text_field( $value ), $endpoint );
		}

		switch ( $type['repo'] ) {
			case 'github':
				$api      = new GitHub_API( $type['type'] );
				$endpoint = $api->add_endpoints( $this, $endpoint );
				if ( $this->type->enterprise_api ) {
					return $endpoint;
				}
				break;
			case 'gitlab':
				$api      = new GitLab_API( $type['type'] );
				$endpoint = $api->add_endpoints( $this, $endpoint );
				if ( $this->type->enterprise_api ) {
					return $endpoint;
				}
				break;
			default:
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
	protected function validate_response( $response ) {
		if ( empty( $response ) || isset( $response->message ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns site_transient and checks/stores transient id in array.
	 *
	 * @return array
	 */
	protected function get_transient() {
		$repo      = isset( $this->type->repo ) ? $this->type->repo : 'ghu';
		$transient = 'ghu-' . md5( $repo );
		if ( ! in_array( $transient, self::$transients, true ) ) {
			self::$transients[] = $transient;
		}

		return get_site_transient( $transient );
	}

	/**
	 * Used to set_site_transient and checks/stores transient id in array.
	 *
	 * @param $id
	 * @param $response
	 *
	 * @return bool
	 */
	protected function set_transient( $id, $response ) {
		$repo                  = isset( $this->type ) ? $this->type->repo : 'ghu';
		$transient             = 'ghu-' . md5( $repo );
		$this->response[ $id ] = $response;
		if ( ! in_array( $transient, self::$transients, true ) ) {
			self::$transients[] = $transient;
		}
		set_site_transient( $transient, $this->response, ( self::$hours * HOUR_IN_SECONDS ) );

		return true;
	}

	/**
	 * Create release asset download link.
	 * Filename must be `{$slug}-{$newest_tag}.zip`
	 *
	 * @return string $download_link
	 */
	protected function make_release_asset_download_link() {
		$download_link = '';
		switch ( $this->type->type ) {
			case 'github_plugin':
			case 'github_theme':
				$download_link = implode( '/', array(
					'https://github.com',
					$this->type->owner,
					$this->type->repo,
					'releases/download',
					$this->type->newest_tag,
					$this->type->repo . '-' . $this->type->newest_tag . '.zip',
				) );
				break;
			case 'bitbucket_plugin':
			case 'bitbucket_theme':
				$download_link = implode( '/', array(
					'https://bitbucket.org',
					$this->type->owner,
					$this->type->repo,
					'downloads',
					$this->type->repo . '-' . $this->type->newest_tag . '.zip',
				) );
				break;
		}

		return $download_link;
	}

}
