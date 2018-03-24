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

use Fragen\Singleton;


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
 * @package Fragen\GitHub_Updater
 * @author  Andy Fragen
 * @author  Codepress
 * @link    https://github.com/codepress/github-plugin-updater
 */
class Plugin extends Base {

	/**
	 * Rollback variable
	 *
	 * @var string branch
	 */
	public $tag = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
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

		$repo_cache            = Singleton::get_instance( 'API_PseudoTrait', $this )->get_repo_cache( 'repos' );
		static::$extra_headers = ! empty( $repo_cache['extra_headers'] )
			? $repo_cache['extra_headers']
			: static::$extra_headers;

		$plugins = ! empty( $repo_cache['plugins'] ) ? $repo_cache['plugins'] : false;
		if ( ! $plugins ) {
			$plugins = get_plugins();
			Singleton::get_instance( 'API_PseudoTrait', $this )->set_repo_cache( 'plugins', $plugins, 'repos', '+30 minutes' );
			Singleton::get_instance( 'API_PseudoTrait', $this )->set_repo_cache( 'extra_headers', static::$extra_headers, 'repos', '+30 minutes' );
		}

		$git_plugins = array();

		/**
		 * Filter to add plugins not containing appropriate header line.
		 *
		 * @since   5.4.0
		 * @access  public
		 *
		 * @param   array $additions    Listing of plugins to add.
		 *                              Default null.
		 * @param   array $plugins      Listing of all plugins.
		 * @param         string        'plugin'    Type being passed.
		 */
		$additions = apply_filters( 'github_updater_additions', null, $plugins, 'plugin' );
		$plugins   = array_merge( $plugins, (array) $additions );

		foreach ( (array) $plugins as $plugin => $headers ) {
			$git_plugin = array();

			foreach ( (array) static::$extra_headers as $value ) {
				$header = null;

				if ( in_array( $value, array( 'Requires PHP', 'Requires WP', 'Languages' ), true ) ) {
					continue;
				}

				if ( empty( $headers[ $value ] ) || false === stripos( $value, 'Plugin' ) ) {
					continue;
				}

				$header_parts = explode( ' ', $value );
				$repo_parts   = $this->get_repo_parts( $header_parts[0], 'plugin' );

				if ( $repo_parts['bool'] ) {
					$header = $this->parse_header_uri( $headers[ $value ] );
					if ( empty( $header ) ) {
						continue;
					}
				}

				$header         = $this->parse_extra_headers( $header, $headers, $header_parts, $repo_parts );
				$current_branch = 'current_branch_' . $header['repo'];
				$branch         = isset( static::$options[ $current_branch ] )
					? static::$options[ $current_branch ]
					: false;

				$git_plugin['type']           = $repo_parts['type'];
				$git_plugin['uri']            = $header['base_uri'] . '/' . $header['owner_repo'];
				$git_plugin['enterprise']     = $header['enterprise_uri'];
				$git_plugin['enterprise_api'] = $header['enterprise_api'];
				$git_plugin['owner']          = $header['owner'];
				$git_plugin['repo']           = $header['repo'];
				$git_plugin['branch']         = $branch ?: 'master';
				$git_plugin['slug']           = $plugin;
				$git_plugin['local_path']     = WP_PLUGIN_DIR . '/' . $header['repo'] . '/';

				// @TODO remove extended naming stuff
				$git_plugin['extended_repo'] = implode( '-', array(
					$repo_parts['git_server'],
					str_replace( '/', '-', $header['owner'] ),
					$header['repo'],
				) );

				$plugin_data                           = get_plugin_data( WP_PLUGIN_DIR . '/' . $git_plugin['slug'] );
				$git_plugin['author']                  = $plugin_data['AuthorName'];
				$git_plugin['name']                    = $plugin_data['Name'];
				$git_plugin['local_version']           = strtolower( $plugin_data['Version'] );
				$git_plugin['sections']['description'] = $plugin_data['Description'];
				$git_plugin['languages']               = $header['languages'];
				$git_plugin['ci_job']                  = $header['ci_job'];
				$git_plugin['release_asset']           = $header['release_asset'];
				$git_plugin['broken']                  = ( empty( $header['owner'] ) || empty( $header['repo'] ) );

				$git_plugin['banners']['high'] =
					file_exists( trailingslashit( WP_PLUGIN_DIR ) . $header['repo'] . '/assets/banner-1544x500.png' )
						? trailingslashit( WP_PLUGIN_URL ) . $header['repo'] . '/assets/banner-1544x500.png'
						: null;

				$git_plugin['banners']['low'] =
					file_exists( trailingslashit( WP_PLUGIN_DIR ) . $header['repo'] . '/assets/banner-772x250.png' )
						? trailingslashit( WP_PLUGIN_URL ) . $header['repo'] . '/assets/banner-772x250.png'
						: null;

				$git_plugin['icons'] = array();
				$icons               = array(
					'svg'    => 'icon.svg',
					'1x_png' => 'icon-128x128.png',
					'1x_jpg' => 'icon-128x128.jpg',
					'2x_png' => 'icon-256x256.png',
					'2x_jpg' => 'icon-256x256.jpg',
				);
				foreach ( $icons as $key => $filename ) {
					$key  = preg_replace( '/_png|_jpg/', '', $key );
					$icon = file_exists( $git_plugin['local_path'] . 'assets/' . $filename )
						? $git_plugin['icons'][ $key ] = trailingslashit( WP_PLUGIN_URL ) . $git_plugin['repo'] . '/assets/' . $filename
						: null;
				}
			}

			// Exit if not git hosted plugin.
			if ( empty( $git_plugin ) ) {
				continue;
			}

			if ( ! is_dir( $git_plugin['local_path'] ) ) {
				// Delete get_plugins() and wp_get_themes() cache.
				delete_site_option( 'ghu-' . md5( 'repos' ) );
			}

			$git_plugins[ $git_plugin['repo'] ] = (object) $git_plugin;
		}

		return $git_plugins;
	}

	/**
	 * Get remote plugin meta to populate $config plugin objects.
	 * Calls to remote APIs to get data.
	 */
	public function get_remote_plugin_meta() {
		$plugins = array();
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
				$this->get_remote_repo_meta( $plugin );
				$plugin->waiting = false;
			} else {
				$plugin->waiting          = true;
				$plugins[ $plugin->repo ] = $plugin;
			}

			//current_filter() check due to calling hook for shiny updates, don't show row twice
			if ( ! $plugin->release_asset && 'init' === current_filter() &&
			     ( ! is_multisite() || is_network_admin() )
			) {
				add_action( "after_plugin_row_$plugin->slug", array( &$this, 'plugin_branch_switcher' ), 15, 3 );
			}
		}

		if ( ! wp_next_scheduled( 'ghu_get_remote_plugin' ) &&
		     ! $this->is_duplicate_wp_cron_event( 'ghu_get_remote_plugin' ) &&
		     ! apply_filters( 'github_updater_disable_wpcron', false )
		) {
			wp_schedule_single_event( time(), 'ghu_get_remote_plugin', array( $plugins ) );
		}

		// Update plugin transient with rollback (branch switching) data.
		add_filter( 'wp_get_update_data', array( &$this, 'set_rollback' ) );

		if ( ! static::is_wp_cli() ) {
			$this->load_pre_filters();
		}
	}

	/**
	 * Load pre-update filters.
	 */
	public function load_pre_filters() {
		add_filter( 'plugin_row_meta', array( &$this, 'plugin_row_meta' ), 10, 2 );
		add_filter( 'plugins_api', array( &$this, 'plugins_api' ), 99, 3 );
		add_filter( 'pre_set_site_transient_update_plugins', array( &$this, 'pre_set_site_transient_update_plugins' ) );
	}

	/**
	 * Add branch switch row to plugins page.
	 *
	 * @param $plugin_file
	 * @param $plugin_data
	 *
	 * @return bool
	 */
	public function plugin_branch_switcher( $plugin_file, $plugin_data ) {
		if ( empty( static::$options['branch_switch'] ) ) {
			return false;
		}

		$enclosure         = $this->update_row_enclosure( $plugin_file, 'plugin', true );
		$plugin            = $this->get_repo_slugs( dirname( $plugin_file ) );
		$nonced_update_url = wp_nonce_url(
			$this->get_update_url( 'plugin', 'upgrade-plugin', $plugin_file ),
			'upgrade-plugin_' . $plugin_file
		);

		if ( ! empty( $plugin ) ) {
			$id       = $plugin['repo'] . '-id';
			$branches = isset( $this->config[ $plugin['repo'] ]->branches )
				? $this->config[ $plugin['repo'] ]->branches
				: null;
		} else {
			return false;
		}

		// Get current branch.
		$repo   = $this->config[ $plugin['repo'] ];
		$branch = Singleton::get_instance( 'Branch', $this )->get_current_branch( $repo );

		$branch_switch_data                      = array();
		$branch_switch_data['slug']              = $plugin['repo'];
		$branch_switch_data['nonced_update_url'] = $nonced_update_url;
		$branch_switch_data['id']                = $id;
		$branch_switch_data['branch']            = $branch;
		$branch_switch_data['branches']          = $branches;

		/*
		 * Create after_plugin_row_
		 */
		echo $enclosure['open'];
		$this->make_branch_switch_row( $branch_switch_data );
		echo $enclosure['close'];

		return true;
	}

	/**
	 * Add 'View details' link to plugins page.
	 *
	 * @param $links
	 * @param $file
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
				$links[] = sprintf( '<a href="%s" class="thickbox">%s</a>',
					esc_url(
						add_query_arg(
							array(
								'tab'       => 'plugin-information',
								'plugin'    => $repo,
								'TB_iframe' => 'true',
								'width'     => 600,
								'height'    => 550,
							),
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
	 * @param $false
	 * @param $action
	 * @param $response
	 *
	 * @return mixed
	 */
	public function plugins_api( $false, $action, $response ) {
		if ( ! ( 'plugin_information' === $action ) ) {
			return $false;
		}

		$plugin = isset( $this->config[ $response->slug ] ) ? $this->config[ $response->slug ] : false;

		// wp.org plugin.
		if ( ! $plugin || ( $plugin->dot_org && 'master' === $plugin->branch ) ) {
			return $false;
		}

		$response->slug          = $plugin->repo;
		$response->plugin_name   = $plugin->name;
		$response->name          = $plugin->name;
		$response->author        = $plugin->author;
		$response->homepage      = $plugin->uri;
		$response->donate_link   = $plugin->donate_link;
		$response->version       = $plugin->remote_version;
		$response->sections      = $plugin->sections;
		$response->requires      = $plugin->requires;
		$response->tested        = $plugin->tested;
		$response->downloaded    = $plugin->downloaded;
		$response->last_updated  = $plugin->last_updated;
		$response->download_link = $plugin->download_link;
		$response->banners       = $plugin->banners;
		$response->icons         = ! empty( $plugin->icons ) ? $plugin->icons : array();
		$response->contributors  = $plugin->contributors;
		if ( ! $this->is_private( $plugin ) ) {
			$response->num_ratings = $plugin->num_ratings;
			$response->rating      = $plugin->rating;
		}

		return $response;
	}

	/**
	 * Hook into pre_set_site_transient_update_plugins to update from GitHub.
	 *
	 * @param $transient
	 *
	 * @return mixed
	 */
	public function pre_set_site_transient_update_plugins( $transient ) {

		foreach ( (array) $this->config as $plugin ) {

			if ( $this->can_update_repo( $plugin ) ) {
				$response = array(
					'slug'        => dirname( $plugin->slug ),
					'plugin'      => $plugin->slug,
					'new_version' => $plugin->remote_version,
					'url'         => $plugin->uri,
					'package'     => $plugin->download_link,
					'icons'       => $plugin->icons,
					'branch'      => $plugin->branch,
					'branches'    => array_keys( $plugin->branches ),
					'type'        => $plugin->type,
				);

				// Skip on RESTful updating.
				if ( isset( $_GET['action'], $_GET['plugin'] ) &&
				     'github-updater-update' === $_GET['action'] &&
				     $response['slug'] === $_GET['plugin']
				) {
					continue;
				}

				// If branch is 'master' and plugin is in wp.org repo then pull update from wp.org.
				if ( $plugin->dot_org && 'master' === $plugin->branch ) {
					$transient = empty( $transient ) ? get_site_transient( 'update_plugins' ) : $transient;
					if ( isset( $transient->response[ $plugin->slug ], $transient->response[ $plugin->slug ]->type ) ) {
						unset( $transient->response[ $plugin->slug ] );
					}
					if ( ! $this->tag ) {
						continue;
					}
				}

				$transient->response[ $plugin->slug ] = (object) $response;
			}

			// Unset if override dot org AND same slug on dot org.
			if ( isset( $transient->response[ $plugin->slug ] ) &&
			     ! isset( $transient->response[ $plugin->slug ]->type ) &&
			     $this->is_override_dot_org()
			) {
				unset( $transient->response[ $plugin->slug ] );
			}

			// Set transient on rollback.
			if ( $this->tag &&
			     ( isset( $_GET['plugin'], $_GET['rollback'] ) && $plugin->slug === $_GET['plugin'] )
			) {
				$transient->response[ $plugin->slug ] = $this->set_rollback_transient( 'plugin', $plugin );
			}
		}

		return $transient;
	}

}
