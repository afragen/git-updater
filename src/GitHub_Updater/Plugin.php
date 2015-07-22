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

		/*
		 * Get details of git sourced plugins.
		 */
		$this->config = $this->get_plugin_meta();

		if ( empty( $this->config ) ) {
			return false;
		}
		if ( isset( $_GET['force-check'] ) ) {
			$this->delete_all_transients( 'plugins' );
		}

		foreach ( (array) $this->config as $plugin ) {
			$this->repo_api = null;
			switch( $plugin->type ) {
				case 'github_plugin':
					$this->repo_api = new GitHub_API( $plugin );
					break;
				case 'bitbucket_plugin':
					$this->repo_api = new Bitbucket_API( $plugin );
					break;
				case 'gitlab_plugin';
					$this->repo_api = new GitLab_API( $plugin );
					break;
			}

			if ( is_null( $this->repo_api ) ) {
				continue;
			}

			$this->{$plugin->type} = $plugin;
			$this->set_defaults( $plugin->type );

			if ( $this->repo_api->get_remote_info( basename( $plugin->slug ) ) ) {
				$this->repo_api->get_repo_meta();
				$this->repo_api->get_remote_tag();
				$changelog = $this->get_changelog_filename( $plugin->type );
				if ( $changelog ) {
					$this->repo_api->get_remote_changes( $changelog );
				}
				$this->repo_api->get_remote_readme();
				$plugin->download_link = $this->repo_api->construct_download_link();
			}

			/*
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
					'package'     => $this->repo_api->construct_download_link( false, $this->tag ),
				);
				$updates_transient->response[ $plugin->slug ] = (object) $rollback;
				set_site_transient( 'update_plugins', $updates_transient );
			}

			add_action( "after_plugin_row_$plugin->slug", array( &$this, 'wp_plugin_update_row' ), 15, 3 );
		}

		$this->make_force_check_transient( 'plugins' );

		add_filter( 'plugin_row_meta', array( &$this, 'plugin_row_meta' ), 10, 2 );
		add_filter( 'pre_set_site_transient_update_plugins', array( &$this, 'pre_set_site_transient_update_plugins' ) );
		add_filter( 'plugins_api', array( &$this, 'plugins_api' ), 99, 3 );
		add_filter( 'upgrader_source_selection', array( &$this, 'upgrader_source_selection' ), 10, 3 );
		add_filter( 'http_request_args', array( 'Fragen\\GitHub_Updater\\API', 'http_request_args' ), 10, 2 );

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

		$wp_list_table = _get_list_table( 'WP_MS_Themes_List_Table' );
		$plugin        = $this->get_repo_slugs( dirname( $plugin_file ) );
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
		$repo          = dirname ( $file );
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
			if ( false !== stristr( $links[2], 'Visit plugin site' ) ) {
				unset( $links[2] );
				$links[] = sprintf( '<a href="%s" class="thickbox">%s</a>',
					network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $repo .
					                   '&TB_iframe=true&width=600&height=550' ),
					__( 'View details', 'github-updater' )
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
			/*
			 * Fix for extended naming.
			 */
			$repos = $this->get_repo_slugs( $plugin->repo );
			if ( $response->slug === $repos['repo'] || $response->slug === $repos['extended_repo'] ) {
				$response->slug = $repos['repo'];
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
				$response->version       = $plugin->remote_version;
				$response->sections      = $plugin->sections;
				$response->requires      = $plugin->requires;
				$response->tested        = $plugin->tested;
				$response->downloaded    = $plugin->downloaded;
				$response->donate_link   = $plugin->donate;
				$response->last_updated  = $plugin->last_updated;
				$response->download_link = $plugin->download_link;
				foreach ( $plugin->contributors as $contributor ) {
					$contributors[ $contributor ] = '//profiles.wordpress.org/' . $contributor;
				}
				$response->contributors  = $contributors;
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
				if ( $plugin->dot_org ) {
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
