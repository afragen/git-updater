<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  GPL-3.0-or-later
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater\Traits;

use Fragen\Git_Updater\Readme_Parser;
use Parsedown;
use stdClass;

/**
 * Trait API_Common
 */
trait API_Common {
	/**
	 * Holds loose class method name.
	 *
	 * @var string|null
	 */
	protected static $method;

	/**
	 * Decode API responses that are base64 encoded.
	 *
	 * @param  string $git      Name of API, eg 'github'.
	 * @param  mixed  $response API response.
	 * @return mixed  $response
	 */
	private function decode_response( $git, $response ) {
		if ( 'github' === $git ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$response = isset( $response->content ) ? base64_decode( $response->content ) : $response;
		}

		/**
		 * Filter the API decode response.
		 *
		 * @param mixed  $response API response.
		 * @param string $git      Git host.
		 */
		return apply_filters( 'gu_decode_response', $response, $git );
	}

	/**
	 * Parse API response to release asset URI.
	 *
	 * @param  string $git      Name of API, eg 'github'.
	 * @param  string $request  Query to API->api().
	 * @param  mixed  $response API response.
	 * @return array<string, mixed>|string|\WP_Error|stdClass $response Release asset download link.
	 */
	private function parse_release_asset( $git, $request, $response ) {
		if ( is_wp_error( $response ) || ! ( is_array( $response ) || is_object( $response ) ) ) {
			return '';
		}
		if ( in_array( $git, [ 'github', 'gitea' ], true ) ) {
			if ( str_contains( $request, 'latest' ) ) {
				// Convert single $response to array of releases.
				$response = [ $response ];
			}
			$release_assets     = [];
			$created_at         = [];
			$dev_release_assets = [];
			$dev_created_at     = [];
			foreach ( $response as $release ) {
				// Ignore leading 'v' and skip anything with dash or words.
				if ( ! preg_match( '/[^v]+[-a-z]+/', $release->tag_name ) ) {
					foreach ( $release->assets as $asset ) {
						if ( str_starts_with( $asset->name, $this->type->slug ) ) {
							$release_assets[ $release->tag_name ] = $asset->url;
							$created_at[ $release->tag_name ]     = $asset->created_at;
							continue 2;
						}
					}
				}
				// Dev releases.
				if ( preg_match( '/[^v]+(?:nightly|alpha|beta|RC){1}[0-9]{0,}/i', $release->tag_name ) ) {
					foreach ( $release->assets as $asset ) {
						if ( str_starts_with( $asset->name, $this->type->slug ) ) {
							$dev_release_assets[ $release->tag_name ] = $asset->url;
							$dev_created_at[ $release->tag_name ]     = $asset->created_at;
							continue 2;
						}
					}
				}
			}
			uksort( $release_assets, fn ( $a, $b ) => version_compare( ltrim( $b, 'v' ), ltrim( $a, 'v' ) ) );
			uksort( $created_at, fn ( $a, $b ) => version_compare( ltrim( $b, 'v' ), ltrim( $a, 'v' ) ) );
			uksort( $dev_release_assets, fn ( $a, $b ) => version_compare( ltrim( $b, 'v' ), ltrim( $a, 'v' ) ) );
			uksort( $dev_created_at, fn ( $a, $b ) => version_compare( ltrim( $b, 'v' ), ltrim( $a, 'v' ) ) );
			$response = [
				'assets'         => $release_assets,
				'created_at'     => $created_at,
				'dev_assets'     => $dev_release_assets,
				'dev_created_at' => $dev_created_at,
			];
		}

		/**
		 * Filter release asset response.
		 *
		 * @since 10.0.0
		 * @param stdClass $response API response.
		 * @param string   $git      Name of git host.
		 * @param string   $request  Schema of API REST endpoint.
		 * @param static   $instance Class object.
		 */
		$response = apply_filters( 'gu_parse_release_asset', $response, $git, $request, $this );

		return $response;
	}

	/**
	 * Read the remote file and parse headers.
	 *
	 * @param string $git     Name of API, eg 'github'.
	 * @param string $request API request.
	 *
	 * @return bool
	 */
	final public function get_remote_api_info( $git, $request ): bool {
		$cache    = $this->get_repo_cache( $this->type->slug );
		$response = is_array( $cache ) ? ( $cache[ $this->type->slug ] ?? false ) : false;

		// Capture old version before overwriting: use valid cache if available, else raw option.
		$prior       = is_array( $cache ) ? $cache : $this->get_repo_cache( $this->type->slug, false );
		$old_version = is_array( $prior ) && isset( $prior[ $this->type->slug ]['Version'] )
			? (string) $prior[ $this->type->slug ]['Version']
			: '';

		if ( ! $response ) {
			self::$method = 'file';
			$response     = $this->api( $request );
			$response     = $this->decode_response( $git, $response );
		}

		if ( $response && is_string( $response ) ) {
			$response = $this->get_file_headers( $response, $this->type->type );
		}

		if ( ! is_array( $response ) || $this->validate_response( $response ) ) {
			return false;
		}

		$response['dot_org'] = $this->get_dot_org_data();
		$this->set_file_info( $response );
		$this->set_repo_cache( $this->type->slug, $response, false, false );
		$this->set_repo_cache( 'repo', $this->type->slug, false, false );

		// Check remote version against the pre-fetch cached version; extend cache if unchanged.
		if ( $this->maybe_extend_repo_cache( $response, $this->type, $old_version ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get remote info for tags.
	 *
	 * @param string $git     Name of API, eg 'github'.
	 * @param string $request API request.
	 *
	 * @return bool|null True if data cached, null if no tags found, false on WP_Error.
	 */
	final public function get_remote_api_tag( $git, $request ) {
		self::$method = 'tags';
		$response     = $this->api( $request );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( ! $response ) {
			$response          = new stdClass();
			$response->message = 'No tags found';
			$this->set_repo_cache( 'tags', $response );
			return null;
		}

		$response = $this->parse_tag_response( $response );
		$this->set_repo_cache( 'tags', $response );

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Read the remote CHANGES.md file.
	 *
	 * @param string $git     Name of API, eg 'github'.
	 * @param string $changes Name of changelog file - deprecated.
	 * @param string $request API request.
	 *
	 * @return bool|null True if data cached, null if no changelog found, false on WP_Error.
	 */
	final public function get_remote_api_changes( $git, $changes, $request ) {
		$changelogs = [ 'CHANGES.md', 'CHANGELOG.md', 'changes.md', 'changelog.md', 'changelog.txt' ];
		$cache      = $this->get_repo_cache( $this->type->slug ) ?: [];
		$changelogs = ! empty( $cache['contents'] ) ? array_intersect( $cache['contents']['files'], $changelogs ) : $changelogs;

		$response     = false;
		self::$method = 'changes';
		foreach ( $changelogs as $changelog ) {
			$new_request = str_replace( ':changelog', $changelog, $request );
			$response    = $this->api( $new_request );

			$error = isset( $response->message );
			$error = isset( $response->error ) ? true : $error;
			if ( ! $error ) {
				break;
			}
		}
		$response = $this->decode_response( $git, $response );

		if ( ! is_string( $response ) || empty( $response ) ) {
			if ( is_wp_error( $response ) ) {
				return false;
			}
			$response          = new stdClass();
			$response->message = 'No changelog found';
			$this->set_repo_cache( 'changes', $response );
			return null;
		}

		$parser   = new Parsedown();
		$response = $parser->text( $response );
		$this->set_repo_cache( 'changes', $response );

		return true;
	}

	/**
	 * Read and parse remote readme.txt.
	 *
	 * @param string $git     Name of API, eg 'github'.
	 * @param string $request API request.
	 *
	 * @return bool|null True if data cached, null if no readme found, false on WP_Error.
	 */
	final public function get_remote_api_readme( $git, $request ) {
		$readmes = [ 'readme.txt', 'README.md', 'readme.md' ];
		$cache   = $this->get_repo_cache( $this->type->slug ) ?: [];
		$readmes = ! empty( $cache['contents'] ) ? array_intersect( $cache['contents']['files'], $readmes ) : $readmes;

		// Use readme.txt if it exists.
		$readme_txt = array_filter(
			$readmes,
			function ( $readme ) {
				if ( 'readme.txt' === $readme ) {
					return true;
				}
			}
		);
		$readmes    = array_unique( array_merge( $readme_txt, $readmes ) );

		$response     = false;
		self::$method = 'readme';

		foreach ( $readmes as $readme ) {
			$new_request = str_replace( ':readme', $readme, $request );
			$response    = $this->api( $new_request );

			$error = isset( $response->message );
			$error = isset( $response->error ) ? true : $error;
			if ( ! $error ) {
				break;
			}
		}
		$response = $this->decode_response( $git, $response );

		if ( ! is_string( $response ) ) {
			if ( is_wp_error( $response ) ) {
				return false;
			}
			$response          = new stdClass();
			$response->message = 'No readme found';
			$this->set_repo_cache( 'readme', $response );
			return null;
		}

		$parser   = new Readme_Parser( $response, $this->type->slug );
		$response = $parser->parse_data();
		$this->set_repo_cache( 'readme', $response );

		return true;
	}

	/**
	 * Read the repository meta from API.
	 *
	 * @param string $git     Name of API, eg 'github'.
	 * @param string $request API request.
	 *
	 * @return bool|null True if data cached, null if no meta found, false on WP_Error.
	 */
	final public function get_remote_api_repo_meta( $git, $request ) {
		self::$method = 'meta';
		$response     = $this->api( $request );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( ! $response ) {
			return null;
		}

		$response = $this->parse_meta_response( $response );
		$this->set_repo_cache( 'meta', $response );

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Read the assets folder of the repo.
	 *
	 * @param string $git     Name of API, eg 'github'.
	 * @param string $request API request.
	 *
	 * @return bool|null True if data cached, null if no assets found, false on WP_Error.
	 */
	final public function get_remote_api_assets( $git, $request ) {
		$assets       = [ '.wordpress-org', 'assets' ];
		$cache        = $this->get_repo_cache( $this->type->slug ) ?: [];
		$assets       = ! empty( $cache['contents'] ) ? array_intersect( (array) $cache['contents']['dirs'], $assets ) : $assets;
		$response     = false;
		self::$method = 'assets';

		foreach ( $assets as $asset ) {
			$new_request = str_replace( ':path', $asset, $request );
			$response    = $this->api( $new_request );

			if ( ! is_object( $response ) ) {
				break;
			}
		}

		$error = isset( $response->message );
		$error = isset( $response->error ) ? true : $error;
		$error = ! is_array( $response ) ? true : $error;
		$error = is_wp_error( $response ) ? true : $error;

		if ( $error ) {
			if ( is_wp_error( $response ) ) {
				return false;
			}
			$response          = new stdClass();
			$response->message = 'No assets found';
			$this->set_repo_cache( 'assets', $response );
			return null;
		}

		$response = $this->parse_asset_dir_response( $response );
		$this->set_repo_cache( 'assets', $response );
		if ( isset( $response->message ) && 'No assets found' === $response->message ) {
			return null;
		}

		if ( $this->validate_response( $response ) ) {
			return false; // @codeCoverageIgnore
		}

		return true;
	}

	/**
	 * Create array of branches and download links as array.
	 *
	 * @param string $git     Name of API, eg 'github'.
	 * @param string $request API request.
	 *
	 * @return bool|null True if data cached, null if no branches found, false on WP_Error.
	 */
	final public function get_remote_api_branches( $git, $request ) {
		self::$method = 'branches';
		$response     = $this->api( $request );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		/**
		 * Filter API branch response.
		 *
		 * @since 10.0.0
		 * @param array|stdClass $response
		 * @param string         $git      Name of API, eg 'github'.
		 */
		$response = apply_filters( 'gu_parse_api_branches', $response, $git );

		if ( ! $response ) {
			return null;
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		$branches = $this->parse_branch_response( $response );
		$this->set_repo_cache( 'branches', (array) $branches );

		return true;
	}

	/**
	 * Get API release asset download link.
	 *
	 * @param  string $git     Name of API, eg 'github'.
	 * @param  string $request Query for API->api().
	 * @return string|array<string, mixed>|false $response Release asset URI.
	 */
	final public function get_api_release_asset( $git, $request ) {
		$cache    = $this->get_repo_cache( $this->type->slug );
		$response = $cache['release_asset'] ?? false;

		if ( ! $response ) {
			self::$method = 'release_asset';
			$response     = $this->api( $request );
			$response     = $this->parse_release_asset( $git, $request, $response );

			if ( ! $response ) {
				$response          = new stdClass();
				$response->message = 'No release asset found';
			}
		}

		if ( $response && ! isset( $cache['release_asset'] ) ) {
			$this->set_repo_cache( 'release_asset', $response );
			$this->set_repo_cache( 'release_asset_download', $response );
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		return $response;
	}

	/**
	 * Get API release assets.
	 *
	 * @param  string $git     Name of API, eg 'github'.
	 * @param  string $request Query for API->api().
	 * @return array<string, mixed>|false $response Release asset URI.
	 */
	final public function get_api_release_assets( $git, $request ) {
		$cache    = $this->get_repo_cache( $this->type->slug );
		$response = $cache['release_assets'] ?? false;

		if ( ! $response ) {
			self::$method = 'release_asset';
			$response     = $this->api( $request );
			$response     = $this->parse_release_asset( $git, $request, $response );

			if ( ! $response ) {
				$response          = new stdClass();
				$response->message = 'No release assets found';
			}
		}

		if ( $response && ! isset( $cache['release_assets'] ) ) {
			$this->set_repo_cache( 'release_assets', $response );
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		return $response;
	}

	/**
	 * Read the root contents of the repo.
	 *
	 * @param string $git     Name of API, eg 'github'.
	 * @param string $request API request.
	 *
	 * @return bool|null True if data cached, null if no contents found, false on WP_Error.
	 */
	final public function get_remote_api_contents( $git, $request ) {
		self::$method = 'contents';
		$response     = $this->api( $request );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( ! $response ) {
			return null;
		}

		$response = $this->parse_contents_response( $response );
		$this->set_repo_cache( 'contents', $response );

		return true;
	}
}
