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

/**
 * Class Language_Pack_API
 */
class Language_Pack_API extends API {

	/**
	 * Constructor.
	 *
	 * @param \stdClass $type Object of repository data.
	 */
	public function __construct( $type ) {
		parent::__construct();
		self::$method   = 'translation';
		$this->type     = $type;
		$this->response = $this->get_repo_cache();
	}

	/**
	 * Get/process Language Packs.
	 *
	 * @param array $headers Array of headers of Language Pack.
	 *
	 * @return bool When invalid response.
	 */
	public function get_language_pack( $headers ) {
		$response = ! empty( $this->response['languages'] ) ? $this->response['languages'] : false;

		if ( ! $response ) {
			$response = $this->get_language_pack_json( $this->type->git, $headers, $response );

			if ( $response ) {
				foreach ( $response as $locale ) {
					$package = $this->process_language_pack_package( $this->type->git, $locale, $headers );

					$response->{$locale->language}->package = $package;
					$response->{$locale->language}->type    = $this->type->type;
					$response->{$locale->language}->version = $this->type->local_version;
				}

				$this->set_repo_cache( 'languages', $response );
			} else {
				return false;
			}
		}

		$this->type->language_packs = $response;

		return true;
	}

	/**
	 * Get language-pack.json from appropriate host.
	 *
	 * @param string $git      (github).
	 * @param array  $headers  Array of headers.
	 * @param mixed  $response API response.
	 *
	 * @return array|bool|mixed
	 */
	private function get_language_pack_json( $git, $headers, $response ) {
		if ( 'github' === $git ) {
			$response = $this->api( '/repos/' . $headers['owner'] . '/' . $headers['repo'] . '/contents/language-pack.json' );
			$response = isset( $response->content )
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
				? json_decode( base64_decode( $response->content ) )
				: null;
		}

		/**
		 * Filter to set API specific Language Pack response.
		 *
		 * @since 10.0.0
		 * @param \stdClass $response Object of Language Pack API response.
		 * @param string    $git      Name of git host.
		 * @param array     $headers  Array of repo headers.
		 * @param \stdClass Current class object.
		 */
		$response = apply_filters( 'gu_get_language_pack_json', $response, $git, $headers, $this );

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		return $response;
	}

	/**
	 * Process $package for update transient.
	 *
	 * @param string    $git     Name of API, eg 'github'.
	 * @param \stdClass $locale  Locale.
	 * @param array     $headers Array of headers.
	 *
	 * @return null|string
	 */
	private function process_language_pack_package( $git, $locale, $headers ) {
		$package = null;
		if ( 'github' === $git ) {
			$package = [ $headers['uri'], 'blob/master' ];
			$package = implode( '/', $package ) . $locale->package;
			$package = add_query_arg( [ 'raw' => 'true' ], $package );
		}

		/**
		 * Filter to process API specific language pack packages.
		 *
		 * @since 10.0.0
		 * @param null|string $package URL to language pack.
		 * @param string      $git     Name of git host.
		 * @param \stdClass   $locale  Object of language pack data.
		 * @param array       $headers Array of repository headers.
		 */
		$package = \apply_filters( 'gu_post_process_language_pack_package', $package, $git, $locale, $headers );

		return $package;
	}
}
