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
	 * @param array  $args HTTP GET REQUEST args.
	 * @param string $url  URL.
	 *
	 * @return array $args
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
	 * @access public
	 *
	 * @param array  $args Args passed to the URL.
	 * @param string $url  The URL.
	 *
	 * @return array $args
	 */
	final public function add_auth_header( $args, $url ) {
		$credentials = $this->get_credentials( $url );
		if ( ! $credentials['isset'] || $credentials['api.wordpress'] ) {
			return $args;
		}
		if ( null !== $credentials['token'] ) {
			if ( 'github' === $credentials['type'] ) {
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
	 * @return array $credentials
	 */
	private function get_credentials( $url ) {
		$options = get_site_option( 'git_updater' );
		$headers = parse_url( $url );

		/**
		 * Filter hook to set an API domain for updating.
		 *
		 * @since 12.6.0
		 * @param string Default is 'api.wordpress.org'.
		 */
		$api_domain = apply_filters( 'gu_api_domain', 'api.wordpress.org' );

		$credentials = [
			'api.wordpress' => isset( $headers['host'] ) === $api_domain ? $headers['host'] : false,
			'isset'         => false,
			'token'         => null,
			'type'          => null,
			'enterprise'    => null,
			'slug'          => null,
		];

		if ( $credentials['api.wordpress'] ) {
			return $credentials;
		}

		$repos = array_merge(
			Singleton::get_instance( 'Plugin', $this )->get_plugin_configs(),
			Singleton::get_instance( 'Theme', $this )->get_theme_configs()
		);
		$slug  = $this->get_slug_for_credentials( $headers, $repos, $url, $options );
		$type  = $this->get_type_for_credentials( $slug, $repos, $url );

		// Set $type for Language Packs.
		if ( $type instanceof Language_Pack_API ) {
			$type = $type->type->git;
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
	 * @param array  $headers Array of headers from parse_url().
	 * @param array  $repos   Array of repositories.
	 * @param string $url     URL being called by API.
	 * @param array  $options Array of site options.
	 *
	 * @return bool|string $slug
	 */
	private function get_slug_for_credentials( $headers, $repos, $url, $options ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$slug = isset( $_REQUEST['slug'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['slug'] ) ) : false;
		$slug = ! $slug && isset( $_REQUEST['plugin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) : $slug;

		// Some installers, like TGMPA, pass an array.
		$slug = is_array( $slug ) ? array_pop( $slug ) : $slug;

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
	 * @param string $slug  Repository slug.
	 * @param array  $repos Array of repositories.
	 * @param string $url   URL being called by API.
	 *
	 * @return string $slug
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

		// Set for Remote Install.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$type = isset( $_POST['git_updater_api'], $_POST['git_updater_repo'] )
			&& str_contains( $url, basename( sanitize_text_field( wp_unslash( $_POST['git_updater_repo'] ) ) ) )
			? sanitize_text_field( wp_unslash( $_POST['git_updater_api'] ) )
			: $type;
		// phpcs:enable

		return $type;
	}

	/**
	 * Removes authentication header for Release Assets.
	 * Storage in AmazonS3 buckets, uses Query String Request Authentication Alternative.
	 *
	 * @access public
	 * @link   http://docs.aws.amazon.com/AmazonS3/latest/dev/RESTAuthentication.html#RESTAuthenticationQueryStringAuth
	 *
	 * @param array  $args The URL arguments passed.
	 * @param string $url  The URL.
	 *
	 * @return array $args
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
	 * @param array $args The URL arguments passed.
	 *
	 * @return array $args
	 */
	final public function add_accept_header( $args ) {
		$repo_cache = [];
		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = [];
		}
		foreach ( $args['headers'] as $key => $value ) {
			if ( in_array( $key, $this->get_running_git_servers(), true ) ) {
				$repo_cache = $this->get_repo_cache( $value );
				if ( 'github' === $key && isset( $repo_cache['release_asset_download'] ) ) {
					$octet_stream    = [ 'Accept' => 'application/octet-stream' ];
					$args['headers'] = array_merge( $args['headers'], $octet_stream );
				}
				unset( $args['headers'][ $key ] );
			}
		}

		return $args;
	}
}
