<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater;

use Fragen\Singleton;
use Fragen\Git_Updater\Traits\GU_Trait;
use Fragen\Git_Updater\Branch;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Plugin
 *
 * Update a WordPress plugin from a GitHub repo.
 *
 * @author  Andy Fragen
 * @author  Codepress
 * @link    https://github.com/codepress/github-plugin-updater
 */
class Plugin {
	use GU_Trait;

	/**
	 * Holds Class Base object.
	 *
	 * @var Base
	 */
	protected $base;

	/**
	 * Hold config array.
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Holds extra headers.
	 *
	 * @var array
	 */
	private static $extra_headers;

	/**
	 * Holds options.
	 *
	 * @var array
	 */
	private static $options;

	/**
	 * Rollback variable.
	 *
	 * @var string|bool
	 */
	protected $tag = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->base          = Singleton::get_instance( 'Base', $this );
		self::$extra_headers = $this->get_class_vars( 'Base', 'extra_headers' );
		self::$options       = $this->get_class_vars( 'Base', 'options' );
		$this->load_options();

		// Get details of installed git sourced plugins.
		$this->config = $this->get_plugin_meta();

		if ( null === $this->config ) {
			return;
		}
	}

	/**
	 * Returns an array of configurations for the known plugins.
	 *
	 * @return array
	 */
	public function get_plugin_configs() {
		return $this->config;
	}

	/**
	 * Get details of Git-sourced plugins from those that are installed.
	 *
	 * @return array Indexed array of associative arrays of plugin details.
	 */
	protected function get_plugin_meta() {
		// Ensure get_plugins() function is available.
		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugins     = get_plugins();
		$git_plugins = [];

		array_map(
			function ( $plugin ) use ( &$paths ) {
				$paths[ $plugin ] = WP_PLUGIN_DIR . "/{$plugin}";

				return $paths;
			},
			array_keys( $plugins )
		);

		$repos_arr = [];
		foreach ( $paths as $slug => $path ) {
			$all_headers        = $this->get_headers( 'plugin' );
			$repos_arr[ $slug ] = get_file_data( $path, $all_headers, 'plugin' );
		}

		$plugins = array_filter(
			$repos_arr,
			function ( $repo ) {
				foreach ( $repo as $key => $value ) {
					if ( in_array( $key, array_keys( self::$extra_headers ), true ) && false !== stripos( $key, 'plugin' ) && ! empty( $value ) ) {
						return $this->get_file_headers( $repo, 'plugin' );
					}
				}
			}
		);

		$additions = apply_filters( 'gu_additions', null, $plugins, 'plugin' );
		$additions = null === $additions ? apply_filters_deprecated( 'github_updater_additions', [ null, $plugins, 'plugin' ], '10.0.0', 'gu_additions' ) : $additions;

		$plugins = array_merge( $plugins, (array) $additions );
		ksort( $plugins );

		foreach ( (array) $plugins as $slug => $plugin ) {
			$git_plugin = [];
			$header     = null;
			$key        = array_filter(
				array_keys( $plugin ),
				function ( $key ) use ( $plugin ) {
					if ( false !== stripos( $key, 'pluginuri' ) && ! empty( $plugin[ $key ] && 'PluginURI' !== $key ) ) {
						return $key;
					}
				}
			);

			$key = array_pop( $key );
			if ( null === $key || ! \array_key_exists( $key, $all_headers ) ) {
				continue;
			}
			$repo_uri = $plugin[ $key ];

			$header_parts = explode( ' ', self::$extra_headers[ $key ] );
			$repo_parts   = $this->get_repo_parts( $header_parts[0], 'plugin' );

			if ( $repo_parts['bool'] ) {
				$header = $this->parse_header_uri( $plugin[ $key ] );
			}

			$header         = $this->parse_extra_headers( $header, $plugin, $header_parts );
			$current_branch = isset( $header['repo'] ) ? "current_branch_{$header['repo']}" : null;

			if ( isset( self::$options[ $current_branch ] )
			&& ( 'master' === self::$options[ $current_branch ] && 'master' !== $header['primary_branch'] )
			) {
				unset( self::$options[ $current_branch ] );
				update_site_option( 'git_updater', self::$options );
			}
			$branch = self::$options[ $current_branch ] ?? $header['primary_branch'];

			$git_plugin['type']                    = 'plugin';
			$git_plugin['git']                     = $repo_parts['git_server'];
			$git_plugin['uri']                     = "{$header['base_uri']}/{$header['owner_repo']}";
			$git_plugin['enterprise']              = $header['enterprise_uri'];
			$git_plugin['enterprise_api']          = $header['enterprise_api'];
			$git_plugin['owner']                   = $header['owner'];
			$git_plugin['slug']                    = $header['repo'];
			$git_plugin['branch']                  = $branch;
			$git_plugin['primary_branch']          = $header['primary_branch'];
			$git_plugin['file']                    = $slug;
			$git_plugin['local_path']              = trailingslashit( dirname( $paths[ $slug ] ) );
			$git_plugin['author']                  = $plugin['Author'];
			$git_plugin['name']                    = $plugin['Name'];
			$git_plugin['homepage']                = $plugin['PluginURI'];
			$git_plugin['local_version']           = strtolower( $plugin['Version'] );
			$git_plugin['sections']['description'] = $plugin['Description'];
			$git_plugin['languages']               = $header['languages'];
			$git_plugin['ci_job']                  = $header['ci_job'];
			$git_plugin['release_asset']           = $header['release_asset'];
			$git_plugin['broken']                  = ( empty( $header['owner'] ) || empty( $header['repo'] ) );

			$content_dir_regex = '/\/' . basename( WP_CONTENT_DIR ) . '.*/';
			preg_match( $content_dir_regex, $git_plugin['local_path'], $matches );

			/**
			 * Filter to specify a unique assets directory.
			 *
			 * This will not work for hidden directories, ie `.wordpress-org`
			 * as they are not reachable from the browser.
			 *
			 * @since 10.7.1
			 * @param string
			 */
			$assets_dir            = apply_filters( 'gu_plugin_assets_dir', 'assets/', $slug );
			$assets_dir            = trailingslashit( $assets_dir );
			$banner_sizes          = [
				'low_png'      => 'banner-772x250.png',
				'low_jpg'      => 'banner-772x250.jpg',
				'low_png_rtl'  => 'banner-772x250-rtl.png',
				'low_jpg_rtl'  => 'banner-772x250-rtl.jpg',
				'high_png'     => 'banner-1544x500.png',
				'high_jpg'     => 'banner-1544x500.jpg',
				'high_png_rtl' => 'banner-1544x500-rtl.png',
				'high_jpg_rtl' => 'banner-1544x500-rtl.jpg',
			];
			$git_plugin['icons']   = [];
			$git_plugin['banners'] = [];
			$icons                 = [
				'svg'    => 'icon.svg',
				'1x_png' => 'icon-128x128.png',
				'1x_jpg' => 'icon-128x128.jpg',
				'2x_png' => 'icon-256x256.png',
				'2x_jpg' => 'icon-256x256.jpg',
			];
			foreach ( $banner_sizes as $key => $size ) {
				if ( \file_exists( $git_plugin['local_path'] . $assets_dir . $size ) ) {
					$key                           = preg_replace( '/_png|_jpg|_rtl/', '', $key );
					$git_plugin['banners'][ $key ] = \home_url() . $matches[0] . $assets_dir . $size;
				}
			}
			foreach ( $icons as $key => $filename ) {
				if ( \file_exists( $git_plugin['local_path'] . $assets_dir . $filename ) ) {
					$key                         = preg_replace( '/_png|_jpg/', '', $key );
					$git_plugin['icons'][ $key ] = \home_url() . $matches[0] . $assets_dir . $filename;
				}
			}
			$git_plugin['icons']['default'] = "https://s.w.org/plugins/geopattern-icon/{$git_plugin['slug']}.svg";

			// Fix branch for .git VCS.
			if ( \file_exists( $git_plugin['local_path'] . '.git/HEAD' ) ) {
				$git_branch           = implode( '/', array_slice( explode( '/', file_get_contents( $git_plugin['local_path'] . '.git/HEAD' ) ), 2 ) );
				$git_plugin['branch'] = preg_replace( "/\r|\n/", '', $git_branch );
			}

			/**
			 * Filter config to fix repo slug.
			 * Eg change Gist ID to slug.
			 *
			 * @since 10.0.0
			 * @param array $plugin Plugin meta array.
			 */
			$git_plugin = apply_filters( 'gu_fix_repo_slug', $git_plugin );

			$git_plugins[ $git_plugin['slug'] ] = (object) $git_plugin;
		}

		return $git_plugins;
	}

	/**
	 * Get remote plugin meta to populate $config plugin objects.
	 * Calls to remote APIs to get data.
	 */
	public function get_remote_plugin_meta() {
		$plugins = [];

		/**
		 * Filter repositories.
		 *
		 * @since 10.2.0
		 * @param array $this->config Array of repository objects.
		 */
		$config = apply_filters( 'gu_config_pre_process', $this->config );

		$disable_wp_cron = (bool) apply_filters( 'gu_disable_wpcron', false );
		$disable_wp_cron = $disable_wp_cron ?: (bool) apply_filters_deprecated( 'github_updater_disable_wpcron', [ false ], '10.0.0', 'gu_disable_wpcron' );

		foreach ( (array) $config as $plugin ) {
			if ( ! $this->waiting_for_background_update( $plugin ) || static::is_wp_cli() || $disable_wp_cron ) {
				$this->base->get_remote_repo_meta( $plugin );
			} else {
				$plugins[ $plugin->slug ] = $plugin;
			}

			// current_filter() check due to calling hook for shiny updates, don't show row twice.
			if ( 'init' === current_filter()
				&& ( ! is_multisite() || is_network_admin() )
			) {
				add_action( "after_plugin_row_{$plugin->file}", [ new Branch(), 'plugin_branch_switcher' ], 15, 3 );
			}
		}

		$schedule_event = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? is_main_site() : true;

		if ( $schedule_event && ! empty( $plugins ) ) {
			if ( ! $disable_wp_cron && ! $this->is_cron_event_scheduled( 'gu_get_remote_plugin' ) ) {
				wp_schedule_single_event( time(), 'gu_get_remote_plugin', [ $plugins ] );
			}
		}

		if ( ! static::is_wp_cli() ) {
			$this->load_pre_filters();
		}
	}

	/**
	 * Load pre-update filters.
	 */
	public function load_pre_filters() {
		add_filter( 'plugins_api', [ $this, 'plugins_api' ], 99, 3 );
		add_filter( 'site_transient_update_plugins', [ $this, 'update_site_transient' ], 15, 1 );
	}

	/**
	 * Put changelog in plugins_api, return WP.org data as appropriate
	 *
	 * @param bool      $false    Default false.
	 * @param string    $action   The type of information being requested from the Plugin Installation API.
	 * @param \stdClass $response Plugin API arguments.
	 *
	 * @return mixed
	 */
	public function plugins_api( $false, $action, $response ) {
		if ( ! ( 'plugin_information' === $action ) ) {
			return $false;
		}

		$plugin = isset( $response->slug, $this->config[ $response->slug ] ) ? $this->config[ $response->slug ] : false;
		$false  = $this->set_no_api_check_readme_changes( $false, $plugin );

		// Skip if waiting for background update.
		if ( $this->waiting_for_background_update( $plugin ) ) {
			return $false;
		}

		// wp.org plugin.
		if ( ! $plugin || ( ( isset( $plugin->dot_org ) && $plugin->dot_org ) && $plugin->primary_branch === $plugin->branch ) ) {
			return $false;
		}

		$response->slug        = $plugin->slug;
		$response->plugin_name = $plugin->name;
		$response->name        = $plugin->name;
		$response->author      = $plugin->author;
		$response->homepage    = $plugin->homepage;
		$response->donate_link = $plugin->donate_link;
		$response->version     = $plugin->remote_version ?: $plugin->local_version;
		$response->sections    = $plugin->sections;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
		$response->short_description = substr( strip_tags( trim( $plugin->sections['description'] ) ), 0, 175 ) . '...';
		$response->requires          = $plugin->requires;
		$response->requires_php      = $plugin->requires_php;
		$response->tested            = $plugin->tested;
		$response->downloaded        = $plugin->downloaded ?: 0;
		$response->active_installs   = $response->downloaded;
		$response->last_updated      = $plugin->last_updated ?: null;
		$response->download_link     = $plugin->download_link ?: null;
		$response->banners           = $plugin->banners;
		$response->icons             = $plugin->icons ?: [];
		$response->contributors      = $plugin->contributors;
		$response->rating            = $plugin->rating;
		$response->num_ratings       = $plugin->num_ratings;

		return $response;
	}

	/**
	 * Hook into site_transient_update_plugins to update from GitHub.
	 *
	 * @param \stdClass $transient Plugin update transient.
	 *
	 * @return mixed
	 */
	public function update_site_transient( $transient ) {
		// needed to fix PHP 7.4 warning.
		if ( ! \is_object( $transient ) ) {
			$transient = new \stdClass();
		}

		/**
		 * Filter repositories.
		 *
		 * @since 10.2.0
		 * @param array $this->config Array of repository objects.
		 */
		$config = apply_filters( 'gu_config_pre_process', $this->config );

		foreach ( (array) $config as $plugin ) {
				$plugin_requires = $this->get_repo_requirements( $plugin );
				$response        = [
					'slug'             => $plugin->slug,
					'plugin'           => $plugin->file,
					'url'              => $plugin->uri,
					'icons'            => $plugin->icons,
					'banners'          => $plugin->banners,
					'branch'           => $plugin->branch,
					'type'             => "{$plugin->git}-{$plugin->type}",
					'update-supported' => true,
					'requires'         => $plugin_requires['RequiresWP'],
					'requires_php'     => $plugin_requires['RequiresPHP'],
				];
				if ( property_exists( $plugin, 'remote_version' ) && $plugin->remote_version ) {
					$response_api_checked = [
						'new_version'    => $plugin->remote_version,
						'package'        => $plugin->download_link,
						'tested'         => $plugin->tested,
						'requires'       => $plugin->requires,
						'requires_php'   => $plugin->requires_php,
						'branches'       => array_keys( $plugin->branches ),
						'upgrade_notice' => isset( $plugin->upgrade_notice ) ? implode( ' ', $plugin->upgrade_notice ) : null,
					];
					$response             = array_merge( $response, $response_api_checked );
				}

				if ( $this->can_update_repo( $plugin ) ) {
					// Skip on RESTful updating.
					// phpcs:disable WordPress.Security.NonceVerification.Recommended
					if ( isset( $_GET['action'], $_GET['plugin'] )
						&& 'git-updater-update' === $_GET['action']
						&& $response['slug'] === $_GET['plugin']
					) {
						continue;
					}
					// phpcs:enable

					// Pull update from dot org if not overriding.
					if ( ! $this->override_dot_org( 'plugin', $plugin ) ) {
						continue;
					}

					// Update download link for release_asset non-primary branches.
					if ( $plugin->release_asset && $plugin->primary_branch !== $plugin->branch ) {
						$response['package'] = isset( $plugin->branches[ $plugin->branch ] )
						? $plugin->branches[ $plugin->branch ]['download']
						: null;
					}

					$transient->response[ $plugin->file ] = (object) $response;
				} else {
					// Add repo without update to $transient->no_update for 'View details' link.
					if ( ! isset( $transient->no_update[ $plugin->file ] ) ) {
						$transient->no_update[ $plugin->file ] = (object) $response;
					}

					$overrides = apply_filters( 'gu_override_dot_org', [] );
					$overrides = empty( $overrides ) ? apply_filters_deprecated( 'github_updater_override_dot_org', [ [] ], '10.0.0', 'gu_override_dot_org' ) : $overrides;

					if ( isset( $transient->response[ $plugin->file ] ) && in_array( $plugin->file, $overrides, true ) ) {
						unset( $transient->response[ $plugin->file ] );
					}
				}

				// Set transient on rollback.
				if ( isset( $_GET['_wpnonce'], $_GET['plugin'], $_GET['rollback'] )
					&& wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'upgrade-plugin_' . $plugin->file )
				) {
					$transient->response[ $plugin->file ] = ( new Branch() )->set_rollback_transient( 'plugin', $plugin );
				}
		}
		if ( property_exists( $transient, 'response' ) ) {
			update_site_option( 'git_updater_plugin_updates', $transient->response );
		}

		return $transient;
	}
}
