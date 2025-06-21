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
 * @uses Fragen\Singleton
 */
class Additions {
	use \Fragen\Git_Updater\Traits\GU_Trait;

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
	 * @uses   Fragen\Git_Updater\Additions::add_to_git_updater()
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

	/**
	 * Remove duplicate $options to unique values.
	 * Caches created in Fragen\Git_Updater\Federation::load_additions().
	 *
	 * @param array $options Array of Additions options.
	 *
	 * @return array
	 */
	public function deduplicate( $options ) {
		if ( empty( $options ) ) {
			return $options;
		}

		$plugin_addons = $this->get_repo_cache( 'git_updater_repository_add_plugin' );
		$plugin_addons = ! empty( $plugin_addons['git_updater_repository_add_plugin'] ) ? $plugin_addons['git_updater_repository_add_plugin'] : [];

		$theme_addons = $this->get_repo_cache( 'git_updater_repository_add_theme' );
		$theme_addons = ! empty( $theme_addons['git_updater_repository_add_theme'] ) ? $theme_addons['git_updater_repository_add_theme'] : [];

		$packages = array_merge( $plugin_addons, $theme_addons );

		foreach ( $packages as $key => $item ) {
			foreach ( $options as $option ) {
				if ( $item['ID'] === $option['ID'] && $item['source'] !== $option['source'] ) {
					unset( $packages[ $key ] );
				}
			}
		}

		$collections = get_site_option( 'git_updater_collections' );
		$collections = $collections ?: [];
		foreach ( $collections as $collection ) {
			foreach ( $options as $key => $item ) {
				if ( $item['source'] === $collection['ID'] ) {
					unset( $options[ $key ] );
				}
			}
		}

		$options = array_merge( $options, $packages );
		foreach ( array_keys( $options ) as $key ) {
			$options[ $key ]['release_asset']   = ! empty( $options[ $key ]['release_asset'] ) ? true : false;
			$options[ $key ]['private_package'] = ! empty( $options[ $key ]['private_package'] ) ? true : false;
			ksort( $options[ $key ] );
		}
		$options = array_map( 'unserialize', array_unique( array_map( 'serialize', $options ) ) );

		return $options;
	}
}
