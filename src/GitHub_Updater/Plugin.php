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

/**
 * Update a WordPress plugin from a GitHub repo.
 *
 * Class    Plugin
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
	protected $tag = false;

	/**
	 * Constructor.
	 */
	public function __construct() {

		/**
		 * Get details of git sourced plugins.
		 */
		$this->config = $this->get_plugin_meta();
		
		if ( empty( $this->config ) ) {
			return false;
		}
		if ( isset( $_GET['force-check'] ) && '1' === $_GET['force-check'] ) {
			$this->delete_all_transients( 'plugins' );
		}

		foreach ( (array) $this->config as $plugin ) {
			switch( $plugin->type ) {
				case 'github_plugin':
					$repo_api = new GitHub_API( $plugin );
					break;
				case 'bitbucket_plugin':
					$repo_api = new Bitbucket_API( $plugin );
					break;
			}

			$this->{$plugin->type} = $plugin;
			$this->set_defaults( $plugin->type );

			if ( $repo_api->get_remote_info( basename( $plugin->slug ) ) ) {
				$repo_api->get_repo_meta();
				$repo_api->get_remote_tag();
				$changelog = $this->get_changelog_filename( $plugin->type );
				if ( $changelog ) {
					$repo_api->get_remote_changes( $changelog );
				}
				$plugin->download_link = $repo_api->construct_download_link();
			}

			/**
			 * Update plugin transient with rollback (branch switching) data.
			 */
			if ( ! empty( $_GET['rollback'] ) &&
			     ( isset( $_GET['plugin'] ) && $_GET['plugin'] === $plugin->slug )
			) {
				$this->tag         = $_GET['rollback'];
				$updates_transient = get_site_transient('update_plugins');
				$rollback          = array(
					'slug'        => $plugin->repo,
					'plugin'      => $plugin->slug,
					'new_version' => $this->tag,
					'url'         => $plugin->uri,
					'package'     => $repo_api->construct_download_link( false, $this->tag ),
				);
				$updates_transient->response[ $plugin->slug ] = (object) $rollback;
				set_site_transient( 'update_plugins', $updates_transient );
			}

			add_action( "after_plugin_row_$plugin->slug", array( $this, 'wp_plugin_update_row' ), 15, 3 );
		}

		$this->make_force_check_transient( 'plugins' );

		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ) );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'pre_set_site_transient_update_plugins' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 99, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'upgrader_source_selection' ), 10, 3 );
		add_filter( 'http_request_args', array( $this, 'no_ssl_http_request_args' ), 10, 2 );

		Settings::$ghu_plugins = $this->config;
	}


	/**
	 * Add branch switch row to plugins page.
	 *
	 * @param $plugin_file
	 * @param $plugin_data
	 *
	 * @return bool
	 */
	public function wp_plugin_update_row( $plugin_file, $plugin_data ) {
		$options = get_site_option( 'github_updater' );
		if ( empty( $options['branch_switch'] ) ) {
			return false;
		}

		$branch_keys   = array( 'GitHub Branch', 'Bitbucket Branch', 'GitLab Branch' );
		$wp_list_table = _get_list_table( 'WP_MS_Themes_List_Table' );
		$plugin        = dirname( $plugin_file );
		$id            = $plugin . '-id';
		$branches      = isset( $this->config[ $plugin ] ) ? $this->config[ $plugin ]->branches : null;

		if ( ! $branches ) {
			return false;
		}

		/**
		 * Get current branch.
		 */
		foreach ( $branch_keys as $branch_key ) {
			$branch = ! empty( $plugin_data[ $branch_key ] ) ? $plugin_data[ $branch_key ] : 'master';
			if ( 'master' !== $branch ) {
				break;
			}
		}

		/**
		 * Create after_plugin_row_
		 */
		if ( isset( $this->config[ $plugin ] ) ) {
			echo '<tr class="plugin-update-tr"><td colspan="' . $wp_list_table->get_column_count() . '" class="plugin-update colspanchange"><div class="update-message update-ok">';

			printf( __( 'Current branch is `%1$s`, try %2$sanother branch%3$s.', 'github-updater' ),
				$branch,
				'<a href="#" onclick="jQuery(\'#' . $id .'\').toggle();return false;">',
				'</a>'
			);

			print( '<ul id="' . $id . '" style="display:none; width: 100%;">' );
			foreach ( $branches as $branch => $uri ) {

				printf( '<li><a href="%s%s">%s</a></li>',
					wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . urlencode( $plugin_file ) ), 'upgrade-plugin_' . $plugin_file ),
					'&rollback=' . urlencode( $branch ),
					esc_attr( $branch )
				);
			}
			print( '</ul>' );
			echo '</div></td></tr>';
		}
	}

	/**
	 * Add 'View details' link to plugins page.
	 * Only works if `Plugin URI' === `GitHub Plugin URI`
	 *
	 * @param $plugin_meta
	 *
	 * @return array
	 */
	public function plugin_row_meta( $plugin_meta ) {
		$regex_pattern = '/<a href="(.*)">(.*)<\/a>/';

		/**
		 * Sanity check for some commercial plugins.
		 */
		if ( ! isset( $plugin_meta[2] ) ) {
			return $plugin_meta;
		}

		preg_match( $regex_pattern, $plugin_meta[2], $matches );

		if ( 'View details' !== $matches[2] ) {
			$slug = trim( parse_url( $matches[1], PHP_URL_PATH ), '/' );
			$repo = explode( '/', $slug );
		}

		if ( ! empty( $slug ) && isset( $repo[1] ) && array_key_exists( $repo[1], $this->config ) ) {
			/**
			 * Remove 'Visit plugin site' link in favor or 'View details' link.
			 */
			if ( false !== stristr( $plugin_meta[2], 'Visit plugin site' ) ) {
				unset( $plugin_meta[2] );
			}

			$plugin_meta[] = sprintf( '<a href="%s" class="thickbox">%s</a>',
				esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $repo[1] .
				                            '&TB_iframe=true&width=600&height=550' ) ),
				__( 'View details', 'github-updater' )
			);
		}

		return $plugin_meta;
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

		$wp_repo_data = get_site_transient( 'ghu-' . md5( $response->slug . 'wporg' ) );
		if ( ! $wp_repo_data ) {
			$wp_repo_data = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.0/' . $response->slug );
			if ( is_wp_error( $wp_repo_data ) ) {
				return false;
			}

			set_site_transient( 'ghu-' . md5( $response->slug . 'wporg' ), $wp_repo_data, ( 12 * HOUR_IN_SECONDS ) );
		}

		$wp_repo_body = unserialize( $wp_repo_data['body'] );
		if ( is_object( $wp_repo_body ) ) {
			$response = $wp_repo_body;
		}

		foreach ( (array) $this->config as $plugin ) {
			if ( strtolower( $response->slug ) === strtolower( $plugin->repo ) ) {
				if ( is_object( $wp_repo_body ) && 'master' === $plugin->branch ) {
					return $response;
				}

				$response->slug          = $plugin->repo;
				$response->plugin_name   = $plugin->name;
				$response->name          = $plugin->name;
				$response->author        = $plugin->author;
				$response->homepage      = $plugin->uri;
				$response->version       = $plugin->remote_version;
				$response->sections      = $plugin->sections;
				$response->requires      = $plugin->requires;
				$response->tested        = $plugin->tested;
				$response->downloaded    = $plugin->downloaded;
				$response->last_updated  = $plugin->last_updated;
				$response->download_link = $plugin->download_link;
				if ( ! $plugin->private ) {
					$response->num_ratings = $plugin->num_ratings;
					$response->rating      = $plugin->rating;
				}
			}
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
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		foreach ( (array) $this->config as $plugin ) {

			if ( $this->can_update( $plugin ) ) {
				$response = array(
					'slug'        => dirname( $plugin->slug ),
					'plugin'      => $plugin->slug,
					'new_version' => $plugin->remote_version,
					'url'         => $plugin->uri,
					'package'     => $plugin->download_link,
				);

				/**
				 * If branch is 'master' and plugin is in wp.org repo then pull update from wp.org
				 */
				if ( isset( $transient->response[ $plugin->slug]->id ) && 'master' === $plugin->branch ) {
					continue;
				}

				/**
				 * Don't overwrite if branch switching.
				 */
				if ( isset( $_GET['rollback'] ) &&
				     ( isset( $_GET['plugin'] ) &&
				       $plugin->slug === $_GET['plugin'] )
				) {
					continue;
				}

				$transient->response[ $plugin->slug ] = (object) $response;
			}
		}

		return $transient;
	}

}
