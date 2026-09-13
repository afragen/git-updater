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

use Fragen\Singleton;
use Fragen\Git_Updater\OAuth\OAuth_Connect;
use Fragen\Git_Updater\API\GitHub_API;
use Fragen\Git_Updater\API\Language_Pack_API;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Trait Basic_Auth_Loader
 */
trait Basic_Auth_Loader {
	/**
	 * Add authentication headers for download packages.
	 * Remove authentication headers from release assets.
	 * Hooks into 'http_request_args' filter.
	 *
	 * @param array<string, mixed> $args HTTP GET REQUEST args.
	 * @param string               $url  URL.
	 *
	 * @return array<string, mixed>
	 */
	final public function download_package( $args, $url ) {
		if ( null !== $args['filename'] ) {
			$args = array_merge( $args, $this->add_auth_header( $args, $url ) );
			$args = array_merge( $args, $this->unset_release_asset_auth( $args, $url ) );
			$args = array_merge( $args, $this->add_accept_header( $args ) );
		}
		remove_filter( 'http_request_args', [ $this, 'download_package' ] );

		return $args;
	}

	/**
	 * Add authentication header to wp_remote_get().
	 *
	 * Credentials are destination-scoped and fail-closed: a request to a host
	 * that is not authorized for the resolved credential type is sent without
	 * any Authorization header.
	 *
	 * @access public
	 *
	 * @param array<string, mixed> $args Args passed to the URL.
	 * @param string               $url  The URL.
	 *
	 * @return array<string, mixed>
	 */
	final public function add_auth_header( $args, $url ) {
		$credentials = $this->get_credentials( $url );
		if ( ! $credentials['isset'] || $credentials['api.wordpress'] ) {
			return $args;
		}

		// Never attach credentials to a host that is not authorized for this
		// credential type; skip the add-on auth header filter too.
		if ( ! $this->is_allowed_credential_host( $url, $credentials['type'] ) ) {
			return $args;
		}

		if ( null !== $credentials['token'] ) {
			// Proactive refresh: check expiry before using the token.
			$provider = $credentials['type'] ?? null;
			if ( $provider && isset( OAuth_Connect::PROVIDERS[ $provider ] ) ) {
				$oauth = Singleton::get_instance( OAuth_Connect::class, $this );
				if ( $oauth->is_token_expired( $provider ) ) {
					$new_token = $oauth->refresh_token( $provider );
					if ( $new_token ) {
						$credentials['token'] = $new_token;
					} elseif ( null === ( get_site_option( 'git_updater', [] )[ OAuth_Connect::PROVIDERS[ $provider ]['option_key'] ] ?? null ) ) {
						// Refresh failed and the token was deleted from storage.
						// Clear the stale local token so we don't send a dead credential.
						$credentials['token'] = null;
					}
				}
			}

			if ( null !== $credentials['token'] && 'github' === $credentials['type'] ) {
				$args['headers']['Authorization'] = 'Bearer ' . $credentials['token'];
				$args['headers']['github']        = $credentials['slug'];
			}

			/**
			 * Filter Basic Authentication header.
			 *
			 * @since 10.0.0
			 * @param array $args        Array of HTTP GET REQUEST headers.
			 * @param array $credentials Array of repository credential data.
			 */
			$args = apply_filters( 'gu_get_auth_header', $args, $credentials );

		} elseif ( null !== $credentials['type'] ) { // No access token.
			$args['headers'][ $credentials['type'] ] = $credentials['slug'];
		}
		$args['headers'] = $args['headers'] ?? [];

		return $args;
	}

	/**
	 * Get credentials for authentication headers.
	 *
	 * @access private
	 *
	 * @param string $url The URL.
	 *
	 * @return array<string, mixed>
	 */
	private function get_credentials( $url ) {
		$options = get_site_option( 'git_updater' );
		$headers = parse_url( $url );

		/**
		 * Filter hook to set an API domain for updating.
		 *
		 * @since 12.6.0
		 * @param string $api_domain Default is 'api.wordpress.org'.
		 */
		$api_domain = apply_filters( 'gu_api_domain', 'api.wordpress.org' );

		$credentials = [
			'api.wordpress' => isset( $headers['host'] ) && $headers['host'] === $api_domain ? $headers['host'] : false,
			'isset'         => false,
			'token'         => null,
			'type'          => null,
			'enterprise'    => null,
			'slug'          => null,
		];

		if ( $credentials['api.wordpress'] ) {
			return $credentials;
		}

		static $cached_repos = null;
		if ( null === $cached_repos ) {
			$cached_repos = array_merge(
				Singleton::get_instance( 'Plugin', $this )->get_plugin_configs(),
				Singleton::get_instance( 'Theme', $this )->get_theme_configs()
			);
		}
		$repos = $cached_repos;
		$slug  = $this->get_slug_for_credentials( $headers, $repos, $url, $options );
		$type  = $this->get_type_for_credentials( $slug, $repos, $url );

		// Set type/slug for Language Packs.
		if ( $this instanceof Language_Pack_API ) {
			$type = $this->type->git;
			$slug = $slug ?: $this->type->slug;
		}

		if ( 'github' === $type || $this instanceof GitHub_API ) {
			$token = ! empty( $options['github_access_token'] ) ? $options['github_access_token'] : null;
			$token = ! empty( $options[ $slug ] ) ? $options[ $slug ] : $token;
			$type  = 'github';

			$credentials['type']       = $type;
			$credentials['isset']      = true;
			$credentials['token']      = $token ?? null;
			$credentials['enterprise'] = ! in_array( $headers['host'], [ 'github.com', 'api.github.com' ], true );
			$credentials['slug']       = $slug;
		}

		// Filter hook args.
		$args = [
			'type'    => $type,
			'options' => $options,
			'headers' => $headers,
			'slug'    => $slug,
			'object'  => $this,
		];

		/**
		 * Filter API credentials data.
		 *
		 * @since 10.0.0
		 * @param array $credentials Array of API credentials data.
		 * @param array $args        Array of hook args.
		 */
		$credentials = apply_filters( 'gu_post_get_credentials', $credentials, $args );

		return $credentials;
	}

	/**
	 * Get $slug for authentication header credentials.
	 *
	 * @param array<string, mixed> $headers Array of headers from parse_url().
	 * @param array<string, mixed> $repos   Array of repositories.
	 * @param string               $url     URL being called by API.
	 * @param array<string, mixed> $options Array of site options.
	 *
	 * @return bool|string
	 */
	private function get_slug_for_credentials( $headers, $repos, $url, $options ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$slug_raw = isset( $_REQUEST['slug'] ) ? wp_unslash( $_REQUEST['slug'] ) : false; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// Some installers, like TGMPA, pass an array — check before sanitizing.
		$slug_raw = is_array( $slug_raw ) ? array_pop( $slug_raw ) : $slug_raw;
		$slug     = $slug_raw ? sanitize_text_field( (string) $slug_raw ) : false;
		$slug     = ! $slug && isset( $_REQUEST['plugin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) : $slug;

		$slug = str_contains( $slug, '/' ) ? dirname( $slug ) : $slug;

		// Set for bulk upgrade.
		if ( ! $slug ) {
			$plugins = isset( $_REQUEST['plugins'] )
				? array_map( 'dirname', explode( ',', sanitize_text_field( wp_unslash( $_REQUEST['plugins'] ) ) ) )
				: [];
			$themes  = isset( $_REQUEST['themes'] )
				? explode( ',', sanitize_text_field( wp_unslash( $_REQUEST['themes'] ) ) )
				: [];
			// phpcs:enable
			$bulk_update = array_merge( $plugins, $themes );
			if ( ! empty( $bulk_update ) ) {
				$slug = array_filter(
					$bulk_update,
					function ( $e ) use ( $url ) {
						return str_contains( $url, $e );
					}
				);
				$slug = array_pop( $slug );
			}
		}

		// In case $type set from Base::$caller doesn't match.
		if ( ! $slug && isset( $headers['path'] ) ) {
			$path_arr = explode( '/', $headers['path'] );
			foreach ( $path_arr as $key ) {
				$key = basename( rawurldecode( $key ) ); // For GitLab.
				if ( ! empty( $options[ $key ] ) || array_key_exists( $key, $repos ) ) {
					$slug = $key;
					break;
				}
				if ( isset( $this->type->gist_id ) ) {
					if ( $key === $this->type->gist_id ) {
						$slug = $this->type->slug;
						break;
					}
				}
			}
		}

		return $slug;
	}

	/**
	 * Get repo type for authentication header credentials.
	 *
	 * @param string               $slug  Repository slug.
	 * @param array<string, mixed> $repos Array of repositories.
	 * @param string               $url   URL being called by API.
	 *
	 * @return string|null
	 */
	private function get_type_for_credentials( $slug, $repos, $url ) {
		$type = $this->get_class_vars( 'Base', 'caller' );

		$type = $slug && isset( $repos[ $slug ] ) && property_exists( $repos[ $slug ], 'git' )
			? $repos[ $slug ]->git
			: $type;

		// Set for WP-CLI.
		if ( ! $slug ) {
			foreach ( $repos as $repo ) {
				if ( property_exists( $repo, 'download_link' ) && $url === $repo->download_link ) {
					$type = $repo->git;
					break;
				}
			}
		}

		// Set for Remote Install. The posted API can only select a credential
		// type when it also owns the destination host.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$posted_api = isset( $_POST['git_updater_api'] ) ? sanitize_text_field( wp_unslash( $_POST['git_updater_api'] ) ) : '';
		$type       = $posted_api
			&& isset( $_POST['git_updater_repo'] )
			&& str_contains( $url, basename( sanitize_text_field( wp_unslash( $_POST['git_updater_repo'] ) ) ) )
			&& $this->is_allowed_credential_host( $url, $posted_api )
			? $posted_api
			: $type;
		// phpcs:enable

		return $type;
	}

	/**
	 * Hosts authorized to receive credentials, keyed by credential type.
	 *
	 * Public hosts are seeded for the bundled GitHub provider; every other
	 * provider — public and enterprise/self-hosted — contributes through the
	 * `gu_credential_hosts` filter, which each active API add-on registers.
	 * Gist hosts are credited to the `github` set because Gist authenticates as
	 * GitHub.
	 *
	 * @access public
	 *
	 * @return array<string, array<int, string>>
	 */
	final public function get_credential_hosts() {
		// Minimal bundled floor: GitHub ships with git-updater, so its public
		// hosts are seeded here. Every other provider — public and
		// enterprise/self-hosted — is contributed by its active API add-on
		// through the `gu_credential_hosts` filter below.
		$hosts = [
			'github' => [
				'github.com',
				'api.github.com',
				'codeload.github.com',
				'objects.githubusercontent.com',
				'githubusercontent.com',
			],
		];

		$installed_apis = $this->get_class_vars( 'Fragen\Git_Updater\Base', 'installed_apis' );

		$repos = array_merge(
			Singleton::get_instance( 'Plugin', $this )->get_plugin_configs(),
			Singleton::get_instance( 'Theme', $this )->get_theme_configs()
		);

		// Enterprise / self-hosted domains are only known from registered repos.
		foreach ( $repos as $repo ) {
			$type = $repo->git ?? null;
			if ( ! $type ) {
				continue;
			}
			$hosts[ $type ] = $hosts[ $type ] ?? [];
			$candidates     = [
				$repo->enterprise ?? '',
				$repo->enterprise_api ?? '',
				$repo->uri ?? '',
				$repo->base_uri ?? '',
				$repo->base_download ?? '',
				$repo->base_raw ?? '',
			];
			foreach ( $candidates as $candidate ) {
				$host = wp_parse_url( (string) $candidate, PHP_URL_HOST );
				if ( ! empty( $host ) ) {
					$hosts[ $type ][] = (string) $host;
				}
			}
		}

		/**
		 * Filter the hosts authorized to receive credentials per git provider.
		 *
		 * @since 14.4.3
		 *
		 * @param array<string, array<int, string>> $hosts          Provider => hostnames.
		 * @param array<string, bool|string>        $installed_apis Active API add-ons.
		 * @param array<string, \stdClass>          $repos          Configured repositories.
		 */
		$hosts = apply_filters( 'gu_credential_hosts', $hosts, $installed_apis, $repos );

		foreach ( $hosts as $type => $list ) {
			$list           = array_map(
				static function ( $item ) {
					return strtolower( (string) $item );
				},
				(array) $list
			);
			$hosts[ $type ] = array_values( array_unique( array_filter( $list ) ) );
		}

		return $hosts;
	}

	/**
	 * Whether a URL's host is authorized to receive credentials for a type.
	 *
	 * @access public
	 *
	 * @param string      $url  The URL being requested.
	 * @param string|null $type Credential type, e.g. 'github'.
	 *
	 * @return bool
	 */
	final public function is_allowed_credential_host( $url, $type = null ) {
		if ( empty( $type ) ) {
			return false;
		}

		// Gist authenticates as GitHub and shares the github host set.
		$type  = 'gist' === $type ? 'github' : $type;
		$hosts = $this->get_credential_hosts();
		if ( empty( $hosts[ $type ] ) ) {
			return false;
		}

		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		if ( empty( $host ) ) {
			return false;
		}
		$host = strtolower( (string) $host );

		foreach ( $hosts[ $type ] as $allowed ) {
			$allowed = strtolower( (string) $allowed );
			if ( '' === $allowed ) {
				continue; // @codeCoverageIgnore
			}
			if ( $host === $allowed || str_ends_with( $host, '.' . $allowed ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Removes authentication header for Release Assets.
	 * Storage in AmazonS3 buckets, uses Query String Request Authentication Alternative.
	 *
	 * @access public
	 * @link   http://docs.aws.amazon.com/AmazonS3/latest/dev/RESTAuthentication.html#RESTAuthenticationQueryStringAuth
	 *
	 * @param array<string, mixed> $args The URL arguments passed.
	 * @param string               $url  The URL.
	 *
	 * @return array<string, mixed>
	 */
	final public function unset_release_asset_auth( $args, $url ) {
		$releases            = false;
		$release_asset_parts = [ 's3.amazonaws.com', 'objects.githubusercontent.com', 'X-Amz-' ];
		foreach ( $release_asset_parts as $part ) {
			if ( str_contains( $url, $part ) ) {
				$releases = true;
				break;
			}
		}

		if ( $releases ) {
			unset( $args['headers']['Authorization'] );
		}

		return $args;
	}

	/**
	 * Add Accept HTTP header.
	 *
	 * @param array<string, mixed> $args The URL arguments passed.
	 *
	 * @return array<string, mixed>
	 */
	final public function add_accept_header( $args ) {
		$repo_cache = [];
		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = [];
		}
		foreach ( $args['headers'] as $key => $value ) {
			if ( in_array( $key, $this->get_running_git_servers(), true ) ) {
				$repo_cache = $this->get_repo_cache( $value, false, 'release_asset_download' );
				if ( 'github' === $key && ! empty( $repo_cache ) ) {
					$octet_stream    = [ 'Accept' => 'application/octet-stream' ];
					$args['headers'] = array_merge( $args['headers'], $octet_stream );
				}
				unset( $args['headers'][ $key ] );
			}
		}

		return $args;
	}
}
