<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

namespace Fragen\GitHub_Updater;

use Fragen\Singleton;
use Fragen\GitHub_Updater\Traits\GHU_Trait;

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
	use GHU_Trait;

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
		include_once ABSPATH . '/wp-admin/includes/plugin.php';

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
			$repos_arr[ $slug ] = get_file_data( $path, $all_headers );
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

		/**
		 * Filter to add plugins not containing appropriate header line.
		 *
		 * @since   5.4.0
		 * @access  public
		 *
		 * @param array $additions Listing of plugins to add.
		 *                         Default null.
		 * @param array $plugins   Listing of all plugins.
		 * @param string 'plugin'   Type being passed.
		 */
		$additions = apply_filters( 'github_updater_additions', null, $plugins, 'plugin' );
		$plugins   = array_merge( $plugins, (array) $additions );

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
			if ( null === $key ) {
				continue;
			}
			$repo_uri = $plugin[ $key ];

			$header_parts = explode( ' ', self::$extra_headers[ $key ] );
			$repo_parts   = $this->get_repo_parts( $header_parts[0], 'plugin' );

			if ( $repo_parts['bool'] ) {
				$header = $this->parse_header_uri( $plugin[ $key ] );
			}

			$header                                = $this->parse_extra_headers( $header, $plugin, $header_parts, $repo_parts );
			$current_branch                        = "current_branch_{$header['repo']}";
			$branch                                = isset( self::$options[ $current_branch ] )
				? self::$options[ $current_branch ]
				: false;
			$git_plugin['type']                    = 'plugin';
			$git_plugin['git']                     = $repo_parts['git_server'];
			$git_plugin['uri']                     = "{$header['base_uri']}/{$header['owner_repo']}";
			$git_plugin['enterprise']              = $header['enterprise_uri'];
			$git_plugin['enterprise_api']          = $header['enterprise_api'];
			$git_plugin['owner']                   = $header['owner'];
			$git_plugin['slug']                    = $header['repo'];
			$git_plugin['branch']                  = $branch ?: 'master';
			$git_plugin['file']                    = $slug;
			$git_plugin['local_path']              = WP_PLUGIN_DIR . "/{$header['repo']}/";
			$git_plugin['author']                  = $plugin['Author'];
			$git_plugin['name']                    = $plugin['Name'];
			$git_plugin['homepage']                = $plugin['PluginURI'];
			$git_plugin['local_version']           = strtolower( $plugin['Version'] );
			$git_plugin['sections']['description'] = $plugin['Description'];
			$git_plugin['languages']               = $header['languages'];
			$git_plugin['ci_job']                  = $header['ci_job'];
			$git_plugin['release_asset']           = $header['release_asset'];
			$git_plugin['broken']                  = ( empty( $header['owner'] ) || empty( $header['repo'] ) );
			$git_plugin['banners']['high']         =
				file_exists( WP_PLUGIN_DIR . "/{$header['repo']}/assets/banner-1544x500.png" )
					? WP_PLUGIN_URL . "/{$header['repo']}/assets/banner-1544x500.png"
					: null;
			$git_plugin['banners']['low']          =
				file_exists( WP_PLUGIN_DIR . "/{$header['repo']}/assets/banner-772x250.png" )
					? WP_PLUGIN_URL . "/{$header['repo']}/assets/banner-772x250.png"
					: null;
			$git_plugin['icons']                   = [];
			$icons                                 = [
				'svg'    => 'icon.svg',
				'1x_png' => 'icon-128x128.png',
				'1x_jpg' => 'icon-128x128.jpg',
				'2x_png' => 'icon-256x256.png',
				'2x_jpg' => 'icon-256x256.jpg',
			];
			foreach ( $icons as $key => $filename ) {
				$key                         = preg_replace( '/_png|_jpg/', '', $key );
				$git_plugin['icons'][ $key ] = file_exists( $git_plugin['local_path'] . 'assets/' . $filename )
					? WP_PLUGIN_URL . "/{$git_plugin['slug']}/assets/{$filename}"
					: null;
			}

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
		foreach ( (array) $this->config as $plugin ) {
			/**
			 * Filter to set if WP-Cron is disabled or if user wants to return to old way.
			 *
			 * @since  7.4.0
			 * @access public
			 *
			 * @param bool
			 */
			if ( ! $this->waiting_for_background_update( $plugin ) || static::is_wp_cli()
				|| apply_filters( 'github_updater_disable_wpcron', false )
			) {
				$this->base->get_remote_repo_meta( $plugin );
			} else {
				$plugins[ $plugin->slug ] = $plugin;
			}

			// current_filter() check due to calling hook for shiny updates, don't show row twice.
			if ( ! $plugin->release_asset && 'init' === current_filter() &&
				( ! is_multisite() || is_network_admin() )
			) {
				add_action( "after_plugin_row_{$plugin->file}", [ $this, 'plugin_branch_switcher' ], 15, 3 );
			}
		}

		$schedule_event = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? is_main_site() : true;

		if ( $schedule_event && ! empty( $plugins ) ) {
			if ( ! wp_next_scheduled( 'ghu_get_remote_plugin' ) &&
			! $this->is_duplicate_wp_cron_event( 'ghu_get_remote_plugin' ) &&
			! apply_filters( 'github_updater_disable_wpcron', false )
			) {
				wp_schedule_single_event( time(), 'ghu_get_remote_plugin', [ $plugins ] );
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
		add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
		add_filter( 'plugins_api', [ $this, 'plugins_api' ], 99, 3 );
		add_filter( 'site_transient_update_plugins', [ $this, 'update_site_transient' ], 15, 1 );
	}

	/**
	 * Add branch switch row to plugins page.
	 *
	 * @param string    $plugin_file
	 * @param \stdClass $plugin_data
	 *
	 * @return bool
	 */
	public function plugin_branch_switcher( $plugin_file, $plugin_data ) {
		if ( empty( self::$options['branch_switch'] ) ) {
			return false;
		}

		$enclosure         = $this->base->update_row_enclosure( $plugin_file, 'plugin', true );
		$plugin            = $this->get_repo_slugs( dirname( $plugin_file ), $this );
		$nonced_update_url = wp_nonce_url(
			$this->base->get_update_url( 'plugin', 'upgrade-plugin', $plugin_file ),
			'upgrade-plugin_' . $plugin_file
		);

		if ( ! empty( $plugin ) ) {
			$id       = $plugin['slug'] . '-id';
			$branches = isset( $this->config[ $plugin['slug'] ]->branches )
				? $this->config[ $plugin['slug'] ]->branches
				: null;
		} else {
			return false;
		}

		// Get current branch.
		$repo   = $this->config[ $plugin['slug'] ];
		$branch = Singleton::get_instance( 'Branch', $this )->get_current_branch( $repo );

		$branch_switch_data                      = [];
		$branch_switch_data['slug']              = $plugin['slug'];
		$branch_switch_data['nonced_update_url'] = $nonced_update_url;
		$branch_switch_data['id']                = $id;
		$branch_switch_data['branch']            = $branch;
		$branch_switch_data['branches']          = $branches;

		/*
		 * Create after_plugin_row_
		 */
		echo $enclosure['open'];
		$this->base->make_branch_switch_row( $branch_switch_data, $this->config );
		echo $enclosure['close'];

		return true;
	}

	/**
	 * Add 'View details' link to plugins page.
	 *
	 * @param array  $links
	 * @param string $file
	 *
	 * @return array $links
	 */
	public function plugin_row_meta( $links, $file ) {
		$regex_pattern = '/<a href="(.*)">(.*)<\/a>/';
		$repo          = dirname( $file );

		/*
		 * Sanity check for some commercial plugins.
		 */
		if ( ! isset( $links[2] ) ) {
			return $links;
		}

		preg_match( $regex_pattern, $links[2], $matches );

		/*
		 * Remove 'Visit plugin site' link in favor or 'View details' link.
		 */
		if ( array_key_exists( $repo, $this->config ) ) {
			if ( null !== $repo ) {
				unset( $links[2] );
				$links[] = sprintf(
					'<a href="%s" class="thickbox">%s</a>',
					esc_url(
						add_query_arg(
							[
								'tab'       => 'plugin-information',
								'plugin'    => $repo,
								'TB_iframe' => 'true',
								'width'     => 600,
								'height'    => 550,
							],
							network_admin_url( 'plugin-install.php' )
						)
					),
					esc_html__( 'View details', 'github-updater' )
				);
			}
		}

		return $links;
	}

	/**
	 * Put changelog in plugins_api, return WP.org data as appropriate
	 *
	 * @param bool      $false
	 * @param string    $action
	 * @param \stdClass $response
	 *
	 * @return mixed
	 */
	public function plugins_api( $false, $action, $response ) {
		if ( ! ( 'plugin_information' === $action ) ) {
			return $false;
		}

		$plugin = isset( $this->config[ $response->slug ] ) ? $this->config[ $response->slug ] : false;

		// Skip if waiting for background update.
		if ( $this->waiting_for_background_update( $plugin ) ) {
			return $false;
		}

		// wp.org plugin.
		if ( ! $plugin || ( $plugin->dot_org && 'master' === $plugin->branch ) ) {
			return $false;
		}

		$response->slug          = $plugin->slug;
		$response->plugin_name   = $plugin->name;
		$response->name          = $plugin->name;
		$response->author        = $plugin->author;
		$response->homepage      = $plugin->homepage;
		$response->donate_link   = $plugin->donate_link;
		$response->version       = $plugin->remote_version;
		$response->sections      = $plugin->sections;
		$response->requires      = $plugin->requires;
		$response->requires_php  = $plugin->requires_php;
		$response->tested        = $plugin->tested;
		$response->downloaded    = $plugin->downloaded;
		$response->last_updated  = $plugin->last_updated;
		$response->download_link = $plugin->download_link;
		$response->banners       = $plugin->banners;
		$response->icons         = ! empty( $plugin->icons ) ? $plugin->icons : [];
		$response->contributors  = $plugin->contributors;
		if ( ! $this->is_private( $plugin ) ) {
			$response->num_ratings = $plugin->num_ratings;
			$response->rating      = $plugin->rating;
		}

		return $response;
	}

	/**
	 * Hook into site_transient_update_plugins to update from GitHub.
	 *
	 * @param \stdClass $transient
	 *
	 * @return mixed
	 */
	public function update_site_transient( $transient ) {
		// needed to fix PHP 7.4 warning.
		if ( ! \is_object( $transient ) ) {
			$transient           = new \stdClass();
			$transient->response = null;
		} elseif ( ! \property_exists( $transient, 'response' ) ) {
			$transient->response = null;
		}

		foreach ( (array) $this->config as $plugin ) {
			if ( $this->can_update_repo( $plugin ) ) {
				$response = [
					'slug'         => $plugin->slug,
					'plugin'       => $plugin->file,
					'new_version'  => $plugin->remote_version,
					'url'          => $plugin->uri,
					'package'      => $plugin->download_link,
					'icons'        => $plugin->icons,
					'tested'       => $plugin->tested,
					'requires_php' => $plugin->requires_php,
					'branch'       => $plugin->branch,
					'branches'     => array_keys( $plugin->branches ),
					'type'         => "{$plugin->git}-{$plugin->type}",
				];

				// Skip on RESTful updating.
				if ( isset( $_GET['action'], $_GET['plugin'] ) &&
					'github-updater-update' === $_GET['action'] &&
					$response['slug'] === $_GET['plugin']
				) {
					continue;
				}

				// Pull update from dot org if not overriding.
				if ( ! $this->override_dot_org( 'plugin', $plugin ) ) {
					continue;
				}

				$transient->response[ $plugin->file ] = (object) $response;
			} else {
				/**
				 * Filter to return array of overrides to dot org.
				 *
				 * @since 8.5.0
				 * @return array
				 */
				$overrides = apply_filters( 'github_updater_override_dot_org', [] );
				if ( isset( $transient->response[ $plugin->file ] ) && in_array( $plugin->file, $overrides, true ) ) {
					unset( $transient->response[ $plugin->file ] );
				}
			}

			// Set transient on rollback.
			if ( isset( $_GET['plugin'], $_GET['rollback'] ) && $plugin->file === $_GET['plugin']
			) {
				$transient->response[ $plugin->file ] = $this->base->set_rollback_transient( 'plugin', $plugin );
			}
		}

		return $transient;
	}
}
