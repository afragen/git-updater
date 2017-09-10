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


/**
 * Class Language_Pack_API
 *
 * @package Fragen\GitHub_Updater
 */
class Language_Pack_API extends API {

	/**
	 * Holds loose class method name.
	 *
	 * @var null
	 */
	public static $method = 'translation';

	/**
	 * Constructor.
	 *
	 * @param \stdClass $type
	 */
	public function __construct( $type ) {
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
		$type     = explode( '_', $this->type->type );

		if ( ! $response ) {
			$response = $this->get_language_pack_json( $type[0], $headers, $response );

			if ( $response ) {
				foreach ( $response as $locale ) {
					$package = $this->process_language_pack_package( $type[0], $locale, $headers );

					$response->{$locale->language}->package = $package;
					$response->{$locale->language}->type    = $type[1];
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
	 * @param string $type     ( github|bitbucket|gitlab )
	 * @param array  $headers
	 * @param mixed  $response API response.
	 *
	 * @return array|bool|mixed
	 */
	private function get_language_pack_json( $type, $headers, $response ) {
		switch ( $type ) {
			case 'github':
				$response = $this->api( '/repos/' . $headers['owner'] . '/' . $headers['repo'] . '/contents/language-pack.json' );
				$contents = base64_decode( $response->content );
				$response = json_decode( $contents );
				break;
			case 'bitbucket':
				$response = $this->api( '/1.0/repositories/' . $headers['owner'] . '/' . $headers['repo'] . '/src/master/language-pack.json' );
				$response = json_decode( $response->data );
				break;
			case 'gitlab':
				$id       = urlencode( $headers['owner'] . '/' . $headers['repo'] );
				$response = $this->api( '/projects/' . $id . '/repository/files?file_path=language-pack.json' );
				$contents = base64_decode( $response->content );
				$response = json_decode( $contents );
				break;
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		return $response;
	}

	/**
	 * Process $package for update transient.
	 *
	 * @param string $type ( github|bitbucket|gitlab )
	 * @param string $locale
	 * @param array  $headers
	 *
	 * @return array|null|string
	 */
	private function process_language_pack_package( $type, $locale, $headers ) {
		$package = null;
		switch ( $type ) {
			case 'github':
				$package = array( 'https://github.com', $headers['owner'], $headers['repo'], 'blob/master' );
				$package = implode( '/', $package ) . $locale->package;
				$package = add_query_arg( array( 'raw' => 'true' ), $package );
				break;
			case 'bitbucket':
				$package = array( 'https://bitbucket.org', $headers['owner'], $headers['repo'], 'raw/master' );
				$package = implode( '/', $package ) . $locale->package;
				break;
			case 'gitlab':
				$package = array( 'https://gitlab.com', $headers['owner'], $headers['repo'], 'raw/master' );
				$package = implode( '/', $package ) . $locale->package;
				break;
		}

		return $package;
	}

}
