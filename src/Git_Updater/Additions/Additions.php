<?php
/**
 * Git Updater
 *
 * @author    Andy Fragen
 * @license   MIT
 * @link      https://github.com/afragen/git-updater
 * @package   git-updater
 */

namespace Fragen\Git_Updater\Additions;

use Fragen\Singleton;

/**
 * Class Additions
 *
 * Add repos without required headers to Git Updater.
 * Uses JSON config data file and companion plugin.
 *
 * @uses \Fragen\Singleton
 */
class Additions {
	/**
	 * Holds array of plugin/theme headers to add to Git Updater.
	 *
	 * @access public
	 * @var array
	 */
	public $add_to_git_updater;

	/**
	 * Register config.
	 *
	 * @access public
	 *
	 * @param string $config The repo config.
	 * @param array  $repos  The repos to pull from.
	 * @param string $type   The plugin type ('plugin' or 'theme').
	 *
	 * @return bool
	 */
	public function register( $config, $repos, $type ) {
		if ( empty( $config ) ) {
			return false;
		}

		$this->add_headers( $config, $repos, $type );
		$this->add_source( $config );

		return true;
	}

	/**
	 * Add Git Updater headers to plugins/themes via a filter hooks.
	 *
	 * @access public
	 * @uses   \Fragen\Git_Updater\Additions::add_to_git_updater()
	 *
	 * @param array  $config The repo config.
	 * @param array  $repos  The repos to pull from.
	 * @param string $type   The plugin type ('plugin' or 'theme').
	 *
	 * @return void
	 */
	public function add_headers( $config, $repos, $type ) {
		foreach ( $config as $repo ) {
			$addition  = [];
			$additions = [];

			$repo_type = explode( '_', $repo['type'] )[1];
			$file_path = 'plugin' === $repo_type ? WP_PLUGIN_DIR . "/{$repo['slug']}" : null;
			$file_path = 'theme' === $repo_type ? get_theme_root() . "/{$repo['slug']}/style.css" : $file_path;

			if ( $type !== $repo_type ) {
				continue;
			}

			$all_headers = Singleton::get_instance( 'Base', $this )->get_headers( $repo_type );

			$additions[ $repo['slug'] ]['type'] = $repo_type;
			if ( file_exists( $file_path ) ) {
				$additions[ $repo['slug'] ] = get_file_data( $file_path, $all_headers );
			}

			switch ( $repo['type'] ) {
				case 'github_plugin':
				case 'github_theme':
					$addition[ 'GitHub' . ucwords( $repo_type ) . 'URI' ] = $repo['uri'];
					break;
				case 'bitbucket_plugin':
				case 'bitbucket_theme':
					$addition[ 'Bitbucket' . ucwords( $repo_type ) . 'URI' ] = $repo['uri'];
					break;
				case 'gitlab_plugin':
				case 'gitlab_theme':
					$addition[ 'GitLab' . ucwords( $repo_type ) . 'URI' ] = $repo['uri'];
					break;
				case 'gitea_plugin':
				case 'gitea_theme':
					$addition[ 'Gitea' . ucwords( $repo_type ) . 'URI' ] = $repo['uri'];
					break;
			}

			$addition['PrimaryBranch'] = ! empty( $repo['primary_branch'] ) ? $repo['primary_branch'] : 'master';
			$addition['ReleaseAsset']  = ! empty( $repo['release_asset'] ) ? true : false;

			$this->add_to_git_updater[ $repo['slug'] ] = array_merge( $additions[ $repo['slug'] ], $addition );
		}
	}

	/**
	 * Add home_url() as element of addition
	 *
	 * @param array $config Array of config data.
	 *
	 * @return void
	 */
	public function add_source( $config ) {
		$pre_config = $config;
		foreach ( $config as $key => $addition ) {
			if ( ! isset( $addition['source'] ) ) {
				$config[ $key ]['source'] = md5( home_url() );
			}
		}
		if ( $pre_config !== $config ) {
			update_site_option( 'git_updater_additions', $config );
		}
	}
}
