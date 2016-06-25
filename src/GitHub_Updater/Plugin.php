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
	protected $tag = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( isset( $_GET['force-check'] ) ) {
			$this->delete_all_transients( 'plugins' );
		}

		/*
		 * Get details of installed git sourced plugins.
		 */
		$this->config = $this->get_plugin_meta();

		if ( empty( $this->config ) ) {
			return false;
		}
	}

	/**
	 * Returns an array of configurations for the known plugins.
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
		include_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		$plugins        = get_plugins();
		$git_plugins    = array();
		$all_plugins    = array();
		$update_plugins = get_site_transient( 'update_plugins' );

		if ( empty( $update_plugins ) ) {
			wp_update_plugins();
			$update_plugins = get_site_transient( 'update_plugins' );
		}
		if ( isset( $update_plugins->response, $update_plugins->no_update ) ) {
			$all_plugins = array_merge( (array) $update_plugins->response, (array) $update_plugins->no_update );
		}

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
				$repo_enterprise_uri = null;
				$repo_enterprise_api = null;

				if ( empty( $headers[ $value ] ) ||
				     false === stristr( $value, 'Plugin' )
				) {
					continue;
				}

				$header_parts = explode( ' ', $value );
				$repo_parts   = $this->get_repo_parts( $header_parts[0], 'plugin' );

				if ( $repo_parts['bool'] ) {
					$header = $this->parse_header_uri( $headers[ $value ] );
				}

				$self_hosted_parts = array_diff( array_keys( self::$extra_repo_headers ), array( 'branch' ) );
				foreach ( $self_hosted_parts as $part ) {
					if ( array_key_exists( $repo_parts[ $part ], $headers ) &&
					     ! empty( $headers[ $repo_parts[ $part ] ] )
					) {
						$repo_enterprise_uri = $headers[ $repo_parts[ $part ] ];
					}
				}

				if ( ! empty( $repo_enterprise_uri ) ) {
					$repo_enterprise_uri = trim( $repo_enterprise_uri, '/' );
					switch ( $header_parts[0] ) {
						case 'GitHub':
							$repo_enterprise_api = $repo_enterprise_uri . '/api/v3';
							break;
						case 'GitLab':
							$repo_enterprise_api = $repo_enterprise_uri . '/api/v3';
							break;
					}
				}

				$git_plugin['type']                = $repo_parts['type'];
				$git_plugin['uri']                 = $repo_parts['base_uri'] . $header['owner_repo'];
				$git_plugin['enterprise']          = $repo_enterprise_uri;
				$git_plugin['enterprise_api']      = $repo_enterprise_api;
				$git_plugin['owner']               = $header['owner'];
				$git_plugin['repo']                = $header['repo'];
				$git_plugin['extended_repo']       = implode( '-', array(
					$repo_parts['git_server'],
					$header['owner'],
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
				$git_plugin['private']                 = true;
				$git_plugin['dot_org']                 = false;
			}
			if ( isset( $all_plugins[ $plugin ]->id ) ) {
				$git_plugin['dot_org'] = true;
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

			/*
			 * Update plugin transient with rollback (branch switching) data.
			 */
			if ( ! empty( $_GET['rollback'] ) &&
			     ( isset( $_GET['plugin'] ) && $_GET['plugin'] === $plugin->slug )
			) {
				$this->tag         = $_GET['rollback'];
				$updates_transient = get_site_transient( 'update_plugins' );
				$rollback          = array(
					'slug'        => $plugin->repo,
					'plugin'      => $plugin->slug,
					'new_version' => $this->tag,
					'url'         => $plugin->uri,
					'package'     => $this->repo_api->construct_download_link( false, $this->tag ),
				);
				if ( array_key_exists( $this->tag, $plugin->branches ) ) {
					$rollback['new_version'] = '0.0.0';
				}
				$updates_transient->response[ $plugin->slug ] = (object) $rollback;
				set_site_transient( 'update_plugins', $updates_transient );
			}

			if ( ! is_multisite() || is_network_admin() ) {
				add_action( "after_plugin_row_$plugin->slug", array( &$this, 'plugin_branch_switcher' ), 15, 3 );
			}
		}
		$this->make_force_check_transient( 'plugins' );
		$this->load_pre_filters();
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

		/*
		 * Create after_plugin_row_
		 */
		echo $enclosure['open'];
		printf( esc_html__( 'Current branch is `%1$s`, try %2$sanother branch%3$s.', 'github-updater' ),
			$branch,
			'<a href="#" onclick="jQuery(\'#' . $id . '\').toggle();return false;">',
			'</a>'
		);

		print( '<ul id="' . $id . '" style="display:none; width: 100%;">' );
		foreach ( $branches as $branch => $uri ) {
			printf( '<li><a href="%s%s" aria-label="' . esc_html__( 'Switch to branch ', 'github-updater' ) . $branch . '">%s</a></li>',
				$nonced_update_url,
				'&rollback=' . urlencode( $branch ),
				esc_attr( $branch )
			);
		}
		print( '</ul>' );
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
			if ( ! is_null( $repo ) ) {
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
		$match = false;
		if ( ! ( 'plugin_information' === $action ) ) {
			return $false;
		}

		$transient    = 'ghu-' . md5( $response->slug . 'wporg' );
		$wp_repo_data = get_site_transient( $transient );
		if ( ! $wp_repo_data ) {
			$wp_repo_data = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.0/' . $response->slug );
			if ( is_wp_error( $wp_repo_data ) ) {
				return false;
			}
			set_site_transient( $transient, $wp_repo_data, ( 12 * HOUR_IN_SECONDS ) );
		}

		$wp_repo_body = unserialize( $wp_repo_data['body'] );
		if ( is_object( $wp_repo_body ) ) {
			$response = $wp_repo_body;
		}

		foreach ( (array) $this->config as $plugin ) {
			/*
			 * Fix for extended naming.
			 */
			$repos = $this->get_repo_slugs( $plugin->repo );
			if ( $response->slug === $repos['repo'] || $response->slug === $repos['extended_repo'] ) {
				$response->slug = $repos['repo'];
				$match          = true;
			} else {
				continue;
			}
			$contributors = array();
			if ( strtolower( $response->slug ) === strtolower( $plugin->repo ) ) {
				if ( is_object( $wp_repo_body ) && 'master' === $plugin->branch ) {
					return $response;
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
				foreach ( $plugin->contributors as $contributor ) {
					$contributors[ $contributor ] = '//profiles.wordpress.org/' . $contributor;
				}
				$response->contributors = $contributors;
				if ( ! $plugin->private ) {
					$response->num_ratings = $plugin->num_ratings;
					$response->rating      = $plugin->rating;
				}
			}
			break;
		}

		if ( ! $match ) {
			return $false;
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
			$response = null;

			if ( $this->can_update( $plugin ) ) {
				$response = array(
					'slug'        => dirname( $plugin->slug ),
					'plugin'      => $plugin->slug,
					'new_version' => $plugin->remote_version,
					'url'         => $plugin->uri,
					'package'     => $plugin->download_link,
				);

				/*
				 * If branch is 'master' and plugin is in wp.org repo then pull update from wp.org
				 */
				if ( $plugin->dot_org && 'master' === $plugin->branch ) {
					continue;
				}

				/*
				 * Don't overwrite if branch switching.
				 */
				if ( $this->tag &&
				     ( isset( $_GET['plugin'] ) && $plugin->slug === $_GET['plugin'] )
				) {
					continue;
				}

				$transient->response[ $plugin->slug ] = (object) $response;
			}
		}

		return $transient;
	}
}
