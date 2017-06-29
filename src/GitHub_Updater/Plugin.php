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
	 * Plugin object.
	 *
	 * @var bool|Plugin
	 */
	private static $instance = false;

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

		/*
		 * Get details of installed git sourced plugins.
		 */
		$this->config = $this->get_plugin_meta();

		if ( empty( $this->config ) ) {
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
	 * The Plugin object can be created/obtained via this
	 * method - this prevents unnecessary work in rebuilding the object and
	 * querying to construct a list of categories, etc.
	 *
	 * @return object $instance Plugin
	 */
	public static function instance() {
		if ( false === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get details of Git-sourced plugins from those that are installed.
	 *
	 * @return array Indexed array of associative arrays of plugin details.
	 */
	protected function get_plugin_meta() {
		/*
		 * Ensure get_plugins() function is available.
		 */
		include_once ABSPATH . '/wp-admin/includes/plugin.php';

		$plugins     = get_plugins();
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

			if ( empty( $headers['GitHub Plugin URI'] ) &&
			     empty( $headers['Bitbucket Plugin URI'] ) &&
			     empty( $headers['GitLab Plugin URI'] )
			) {
				continue;
			}

			foreach ( (array) self::$extra_headers as $value ) {
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

				$header = $this->parse_extra_headers( $header, $headers, $header_parts, $repo_parts );

				$git_plugin['type']                = $repo_parts['type'];
				$git_plugin['uri']                 = $header['base_uri'] . '/' . $header['owner_repo'];
				$git_plugin['enterprise']          = $header['enterprise_uri'];
				$git_plugin['enterprise_api']      = $header['enterprise_api'];
				$git_plugin['owner']               = $header['owner'];
				$git_plugin['repo']                = $header['repo'];
				$git_plugin['extended_repo']       = implode( '-', array(
					$repo_parts['git_server'],
					str_replace( '/', '-', $header['owner'] ),
					$header['repo'],
				) );
				$git_plugin['branch']              = ! empty( $headers[ $repo_parts['branch'] ] ) ? $headers[ $repo_parts['branch'] ] : 'master';
				$git_plugin['slug']                = $plugin;
				$git_plugin['local_path']          = WP_PLUGIN_DIR . '/' . $header['repo'] . '/';
				$git_plugin['local_path_extended'] = WP_PLUGIN_DIR . '/' . $git_plugin['extended_repo'] . '/';

				$plugin_data                           = get_plugin_data( WP_PLUGIN_DIR . '/' . $git_plugin['slug'] );
				$git_plugin['author']                  = $plugin_data['AuthorName'];
				$git_plugin['name']                    = $plugin_data['Name'];
				$git_plugin['local_version']           = strtolower( $plugin_data['Version'] );
				$git_plugin['sections']['description'] = $plugin_data['Description'];
				$git_plugin['languages']               = ! empty( $header['languages'] ) ? $header['languages'] : null;
				$git_plugin['ci_job']                  = ! empty( $header['ci_job'] ) ? $header['ci_job'] : null;
				$git_plugin['release_asset']           = true === $plugin_data['Release Asset'];
				$git_plugin['broken']                  = ( empty( $header['owner'] ) || empty( $header['repo'] ) );

				$git_plugin['banners']['high'] =
					file_exists( trailingslashit( WP_PLUGIN_DIR ) . $header['repo'] . '/assets/banner-1544x500.png' )
						? trailingslashit( WP_PLUGIN_URL ) . $header['repo'] . '/assets/banner-1544x500.png'
						: null;

				$git_plugin['banners']['low'] =
					file_exists( trailingslashit( WP_PLUGIN_DIR ) . $header['repo'] . '/assets/banner-772x250.png' )
						? trailingslashit( WP_PLUGIN_URL ) . $header['repo'] . '/assets/banner-772x250.png'
						: null;

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
		foreach ( (array) $this->config as $plugin ) {

			if ( ! $this->get_remote_repo_meta( $plugin ) ) {
				continue;
			}

			// Update plugin transient with rollback (branch switching) data.
			add_filter( 'wp_get_update_data', array( &$this, 'set_rollback' ) );

			if ( ( ! is_multisite() || is_network_admin() ) && ! $plugin->release_asset &&
			     'init' === current_filter() //added due to calling hook for shiny updates, don't show row twice
			) {
				add_action( "after_plugin_row_$plugin->slug", array( &$this, 'plugin_branch_switcher' ), 15, 3 );
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
		$options = get_site_option( 'github_updater' );
		if ( empty( $options['branch_switch'] ) ) {
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
			$branches = isset( $this->config[ $plugin['repo'] ] ) ? $this->config[ $plugin['repo'] ]->branches : null;
		} else {
			return false;
		}

		/*
		 * Get current branch.
		 */
		foreach ( parent::$git_servers as $server ) {
			$branch_key = $server . ' Branch';
			$branch     = ! empty( $plugin_data[ $branch_key ] ) ? $plugin_data[ $branch_key ] : 'master';
			if ( 'master' !== $branch ) {
				break;
			}
		}

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
		$slugs         = $this->get_repo_slugs( $repo );
		$repo          = ! empty( $slugs ) ? $slugs['repo'] : null;

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
		$contributors = array();
		if ( ! ( 'plugin_information' === $action ) ) {
			return $false;
		}

		$plugin = isset( $this->config[ $response->slug ] ) ? $this->config[ $response->slug ] : false;

		// wp.org plugin.
		if ( ! $plugin || ( $plugin->dot_org && 'master' === $plugin->branch ) ) {
			return $false;
		}

		/*
		 * Fix for extended naming.
		 */
		$repos          = $this->get_repo_slugs( $plugin->repo );
		$response->slug = ( $response->slug === $repos['extended_repo'] ) ? $repos['repo'] : $plugin->repo;

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
		foreach ( $plugin->contributors as $contributor ) {
			$contributors[ $contributor ] = '//profiles.wordpress.org/' . $contributor;
		}
		$response->contributors = $contributors;
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

			if ( $this->can_update( $plugin ) ) {
				$response = array(
					'slug'        => dirname( $plugin->slug ),
					'plugin'      => $plugin->slug,
					'new_version' => $plugin->remote_version,
					'url'         => $plugin->uri,
					'package'     => $plugin->download_link,
					'branch'      => $plugin->branch,
					'branches'    => array_keys( $plugin->branches ),
				);

				/*
				 * Skip on branch switching or rollback.
				 */
				if ( $this->tag &&
				     ( isset( $_GET['plugin'], $_GET['rollback'] ) && $plugin->slug === $_GET['plugin'] )
				) {
					continue;
				}

				/*
				 * Skip on RESTful updating.
				 */
				if ( isset( $_GET['action'] ) && 'github-updater-update' === $_GET['action'] &&
				     $response['slug'] === $_GET['plugin']
				) {
					continue;
				}

				/*
				 * If branch is 'master' and plugin is in wp.org repo then pull update from wp.org
				 */
				if ( $plugin->dot_org && 'master' === $plugin->branch ) {
					$transient = empty( $transient ) ? get_site_transient( 'update_plugins' ) : $transient;
					if ( isset( $transient->response[ $plugin->slug ] ) &&
					     ! isset( $transient->response[ $plugin->slug ]->id )
					) {
						unset( $transient->response[ $plugin->slug ] );
					}
					continue;
				}

				$transient->response[ $plugin->slug ] = (object) $response;
			}
		}

		return $transient;
	}

}
