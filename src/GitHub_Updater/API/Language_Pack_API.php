<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

namespace Fragen\GitHub_Updater\API;

use Fragen\GitHub_Updater\API;
use Fragen\GitHub_Updater\Traits\GHU_Trait;

/**
 * Class Language_Pack_API
 */
class Language_Pack_API extends API {
	use GHU_Trait;

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
	 * @param string $git      (github|bitbucket|gitlab|gitea).
	 * @param array  $headers  Array of headers.
	 * @param mixed  $response API response.
	 *
	 * @return array|bool|mixed
	 */
	private function get_language_pack_json( $git, $headers, $response ) {
		switch ( $git ) {
			case 'github':
				$response = $this->api( '/repos/' . $headers['owner'] . '/' . $headers['repo'] . '/contents/language-pack.json' );
				$response = isset( $response->content )
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
					? json_decode( base64_decode( $response->content ) )
					: null;
				break;
			case 'bitbucket':
				$response = $this->api( '/2.0/repositories/' . $headers['owner'] . '/' . $headers['repo'] . '/src/master/language-pack.json' );
				break;
			case 'gitlab':
				$id       = rawurlencode( $headers['owner'] . '/' . $headers['repo'] );
				$response = $this->api( '/projects/' . $id . '/repository/files/language-pack.json' );
				$response = isset( $response->content )
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
					? json_decode( base64_decode( $response->content ) )
					: null;
				break;
			case 'gitea':
				$response = $this->api( '/repos/' . $headers['owner'] . '/' . $headers['repo'] . '/raw/master/language-pack.json' );
				$response = isset( $response->content )
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
					? json_decode( base64_decode( $response->content ) )
					: null;
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
	 * @param string $git     (github|bitbucket|gitlab|gitea).
	 * @param string $locale  Locale.
	 * @param array  $headers Array of headers.
	 *
	 * @return array|null|string
	 */
	private function process_language_pack_package( $git, $locale, $headers ) {
		$package = null;
		switch ( $git ) {
			case 'github':
				$package = [ 'https://github.com', $headers['owner'], $headers['repo'], 'blob/master' ];
				$package = implode( '/', $package ) . $locale->package;
				$package = add_query_arg( [ 'raw' => 'true' ], $package );
				break;
			case 'bitbucket':
				$package = [ 'https://bitbucket.org', $headers['owner'], $headers['repo'], 'raw/master' ];
				$package = implode( '/', $package ) . $locale->package;
				break;
			case 'gitlab':
				$package = [ 'https://gitlab.com', $headers['owner'], $headers['repo'], 'raw/master' ];
				$package = implode( '/', $package ) . $locale->package;
				break;
			case 'gitea':
				// TODO: make sure this works as expected.
				$package = [ $headers['uri'], 'raw/master' ];
				$package = implode( '/', $package ) . $local->package;
				break;
		}

		return $package;
	}
}
