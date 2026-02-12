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
	 * @var null
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
	 * @return array|string|WP_Error $response Release asset download link.
	 */
	private function parse_release_asset( $git, $request, $response ) {
		if ( is_wp_error( $response ) ) {
			return '';
		}
		if ( in_array( $git, [ 'github', 'gitea' ], true ) ) {
			if ( str_contains( $request, 'latest' ) ) {
				// Convert single $response to array of releases.
				$release    = $response;
				$response   = [];
				$response[] = $release ?? [];
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
		 * @param stdClass $this     Class object.
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
	final public function get_remote_api_info( $git, $request ) {
		$this->response = $this->get_repo_cache( $this->type->slug );
		$response       = $this->response[ $this->type->slug ] ?? false;

		if ( ! $response ) {
			self::$method = 'file';
			$response     = $this->api( $request );
			$response     = $this->decode_response( $git, $response );
		}

		if ( $response && is_string( $response ) ) {
			$response = $this->get_file_headers( $response, $this->type->type );
			$this->set_repo_cache( $this->type->slug, $response );
			$this->set_repo_cache( 'repo', $this->type->slug );
		}

		if ( ! is_array( $response ) || $this->validate_response( $response ) ) {
			return false;
		}

		$response['dot_org'] = $this->get_dot_org_data();
		$this->set_file_info( $response );

		return true;
	}

	/**
	 * Get remote info for tags.
	 *
	 * @param string $git     Name of API, eg 'github'.
	 * @param string $request API request.
	 *
	 * @return bool
	 */
	final public function get_remote_api_tag( $git, $request ) {
		$this->response = $this->get_repo_cache( $this->type->slug );
		$repo_type      = $this->return_repo_type();
		$response       = $this->response['tags'] ?? false;

		if ( ! $response ) {
			self::$method = 'tags';
			$response     = $this->api( $request );

			if ( ! $response || is_wp_error( $response ) ) {
				$response          = new stdClass();
				$response->message = 'No tags found';
			}

			if ( $response ) {
				$response = $this->parse_tag_response( $response );
				$this->set_repo_cache( 'tags', $response );
			}
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		$tags = $this->parse_tags( $response, $repo_type );
		return $this->sort_tags( $tags );
	}

	/**
	 * Read the remote CHANGES.md file.
	 *
	 * @param string $git     Name of API, eg 'github'.
	 * @param string $changes Name of changelog file - deprecated.
	 * @param string $request API request.
	 *
	 * @return bool
	 */
	final public function get_remote_api_changes( $git, $changes, $request ) {
		$changelogs     = [ 'CHANGES.md', 'CHANGELOG.md', 'changes.md', 'changelog.md', 'changelog.txt' ];
		$this->response = $this->get_repo_cache( $this->type->slug );
		$response       = $this->response['changes'] ?? false;
		$changelogs     = ! empty( $this->response['contents'] ) ? array_intersect( $this->response['contents']['files'], $changelogs ) : $changelogs;

		if ( ! $response ) {
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

			if ( ! is_string( $response ) || is_wp_error( $response ) ) {
				$response          = new stdClass();
				$response->message = 'No changelog found';
				$this->set_repo_cache( 'changes', $response );
			}
		}

		if ( $this->validate_response( $response ) && ! is_string( $response ) ) {
			return false;
		}

		if ( ! isset( $this->response['changes'] ) ) {
			$parser   = new Parsedown();
			$response = $parser->text( $response );
			$this->set_repo_cache( 'changes', $response );
		}

		$this->type->sections['changelog'] = $response;

		return true;
	}

	/**
	 * Read and parse remote readme.txt.
	 *
	 * @param string $git     Name of API, eg 'github'.
	 * @param string $request API request.
	 *
	 * @return bool
	 */
	final public function get_remote_api_readme( $git, $request ) {
		$readmes        = [ 'readme.txt', 'README.md', 'readme.md' ];
		$this->response = $this->get_repo_cache( $this->type->slug );
		$response       = $this->response['readme'] ?? false;
		$readmes        = ! empty( $this->response['contents'] ) ? array_intersect( $this->response['contents']['files'], $readmes ) : $readmes;

		// Use readme.txt if it exists.
		$readme_txt = array_filter(
			$readmes,
			function ( $readme ) {
				if ( 'readme.txt' === $readme ) {
					return $readme;
				}
			}
		);
		$readmes    = array_unique( array_merge( $readme_txt, $readmes ) );

		if ( ! $response ) {
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

			if ( ! is_string( $response ) || is_wp_error( $response ) ) {
				$response          = new stdClass();
				$response->message = 'No readme found';
				$this->set_repo_cache( 'readme', $response );
			}
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		if ( ! isset( $this->response['readme'] ) ) {
			$parser   = new Readme_Parser( $response, $this->type->slug );
			$response = $parser->parse_data();
			$this->set_repo_cache( 'readme', $response );
		}

		$this->set_readme_info( $response );

		return true;
	}

	/**
	 * Read the repository meta from API.
	 *
	 * @param string $git     Name of API, eg 'github'.
	 * @param string $request API request.
	 *
	 * @return bool
	 */
	final public function get_remote_api_repo_meta( $git, $request ) {
		$this->response = $this->get_repo_cache( $this->type->slug );
		$response       = $this->response['meta'] ?? false;

		if ( ! $response ) {
			self::$method = 'meta';
			$response     = $this->api( $request );

			if ( $response ) {
				$response = $this->parse_meta_response( $response );
				$this->set_repo_cache( 'meta', $response );
			}
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		$this->type->repo_meta = $response;
		$this->add_meta_repo_object();

		return true;
	}

	/**
	 * Read the assets folder of the repo.
	 *
	 * @param string $git     Name of API, eg 'github'.
	 * @param string $request API request.
	 *
	 * @return bool
	 */
	final public function get_remote_api_assets( $git, $request ) {
		$assets         = [ '.wordpress-org', 'assets' ];
		$this->response = $this->get_repo_cache( $this->type->slug );
		$response       = $this->response['assets'] ?? false;
		$assets         = ! empty( $this->response['contents'] ) ? array_intersect( (array) $this->response['contents']['dirs'], $assets ) : $assets;

		if ( ! $response ) {
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
				$response          = new stdClass();
				$response->message = 'No assets found';
			}

			$response = $this->parse_asset_dir_response( $response );
			$this->set_repo_cache( 'assets', $response );
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Create array of branches and download links as array.
	 *
	 * @param string $git     Name of API, eg 'github'.
	 * @param string $request API request.
	 *
	 * @return bool
	 */
	final public function get_remote_api_branches( $git, $request ) {
		$branches       = [];
		$this->response = $this->get_repo_cache( $this->type->slug );
		$response       = $this->response['branches'] ?? false;

		if ( $this->exit_no_update( $response, true ) ) {
			return false;
		}

		if ( ! $response ) {
			self::$method = 'branches';
			$response     = $this->api( $request );

			/**
			 * Filter API branch response.
			 *
			 * @since 10.0.0
			 * @param array|stdClass $response
			 * @param string         $git      Name of API, eg 'github'.
			 */
			$response = apply_filters( 'gu_parse_api_branches', $response, $git );

			if ( $this->validate_response( $response ) || is_scalar( $response ) ) {
				return false;
			}

			if ( $response ) {
				$branches = $this->parse_branch_response( $response );
				$this->set_repo_cache( 'branches', (array) $branches );
			}
		}

		$this->type->branches = (array) $response;

		return true;
	}

	/**
	 * Get API release asset download link.
	 *
	 * @param  string $git     Name of API, eg 'github'.
	 * @param  string $request Query for API->api().
	 * @return string|array $response Release asset URI.
	 */
	final public function get_api_release_asset( $git, $request ) {
		$this->response = $this->get_repo_cache( $this->type->slug );
		$response       = $this->response['release_asset'] ?? false;

		if ( $response && $this->exit_no_update( $response ) ) {
			return false;
		}

		if ( ! $response ) {
			self::$method = 'release_asset';
			$response     = $this->api( $request );
			$response     = $this->parse_release_asset( $git, $request, $response );

			if ( ! $response && ! is_wp_error( $response ) ) {
				$response          = new stdClass();
				$response->message = 'No release asset found';
			}
		}

		if ( $response && ! isset( $this->response['release_asset'] ) ) {
			$this->set_repo_cache( 'release_asset', $response );
			$this->set_repo_cache( 'release_asset_download', $response );
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		$this->type->release_assets[ $this->type->newest_tag ] = $response;

		return $response;
	}

	/**
	 * Get API release assets.
	 *
	 * @param  string $git     Name of API, eg 'github'.
	 * @param  string $request Query for API->api().
	 * @return array $response Release asset URI.
	 */
	final public function get_api_release_assets( $git, $request ) {
		$this->response = $this->get_repo_cache( $this->type->slug );
		$response       = $this->response['release_assets'] ?? false;

		if ( $response && $this->exit_no_update( $response ) ) {
			return false;
		}

		if ( ! $response ) {
			self::$method = 'release_asset';
			$response     = $this->api( $request );
			$response     = $this->parse_release_asset( $git, $request, $response );

			if ( ! $response && ! is_wp_error( $response ) ) {
				$response          = new stdClass();
				$response->message = 'No release assets found';
			}
		}

		if ( $response && ! isset( $this->response['release_assets'] ) ) {
			$this->set_repo_cache( 'release_assets', $response );
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		$this->type->release_assets     = $response['assets'] ?? $response;
		$this->type->created_at         = $response['created_at'] ?? [];
		$this->type->dev_release_assets = $response['dev_assets'] ?? [];
		$this->type->dev_created_at     = $response['dev_created_at'] ?? [];

		return $response;
	}

	/**
	 * Read the root contents of the repo.
	 *
	 * @param string $git     Name of API, eg 'github'.
	 * @param string $request API request.
	 *
	 * @return bool
	 */
	final public function get_remote_api_contents( $git, $request ) {
		$this->response = $this->get_repo_cache( $this->type->slug );
		$response       = $this->response['contents'] ?? false;

		if ( ! $response ) {
			self::$method = 'contents';
			$response     = $this->api( $request );

			if ( $response ) {
				$response = $this->parse_contents_response( $response );
				$this->set_repo_cache( 'contents', $response );
			}
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		return true;
	}
}
