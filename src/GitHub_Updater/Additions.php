<?php

namespace Fragen\GitHub_Updater;

/**
 * Class Additions
 *
 * Add repos without GitHub Updater headers to GitHub Updater.
 * Uses JSON config data file and companion plugin.
 *
 * @package Fragen\GitHub_Updater
 * @author  Andy Fragen
 * @link    https://github.com/afragen/github-updater-additions
 */
class Additions {

	/**
	 * Holds instance of this object.
	 *
	 * @var
	 */
	private static $instance;

	/**
	 * Holds array of plugin/theme headers to add to GitHub Updater.
	 *
	 * @var
	 */
	public $add_to_github_updater = array();

	/**
	 * Singleton
	 *
	 * @return object
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Register JSON config file.
	 *
	 * @param $config
	 * @param $repos
	 *
	 * @return bool
	 */
	public function register( $config, $repos, $type ) {
		if ( empty( $config ) ) {
			return false;
		}
		if ( null === ( $config = json_decode( $config, true ) ) ) {
			return false;
		}
		if ( 'plugin' === $type ) {
			$this->add_plugin_headers( $config, $repos );
		}
		if ( 'theme' === $type ) {
			$this->add_theme_headers( $config, $repos );
		}
	}

	/**
	 * Add GitHub Updater plugin header.
	 * Adds extra header in Class Plugins via hook.
	 *
	 * @param $config
	 * @param $repos
	 */
	protected function add_plugin_headers( $config, $repos ) {
		$this->add_to_github_updater = array();
		foreach ( $config as $repo ) {
			if ( false !== strpos( $repo['type'], 'theme' ) ) {
				continue;
			}
			$addition = $repos[ $repo['slug'] ];
			switch ( $repo['type'] ) {
				case 'github_plugin':
					$addition['GitHub Plugin URI'] = $repo['uri'];
					break;
				case 'bitbucket_plugin':
					$addition['Bitbucket Plugin URI'] = $repo['uri'];
					break;
				case 'gitlab_plugin':
					$addition['GitLab Plugin URI'] = $repo['uri'];
					break;
			}
			$this->add_to_github_updater[ $repo['slug'] ] = $addition;
		}
	}

	/**
	 * Add GitHub Updater theme header.
	 * Adds header URI into Class Theme via hook.
	 *
	 * @param $config
	 * @param $theme
	 */
	public function add_theme_headers( $config, $theme ) {
		$this->add_to_github_updater = array();
		foreach ( $config as $repo ) {
			if ( false !== strpos( $repo['type'], 'plugin' ) ) {
				continue;
			}
			$addition = $theme[ $repo['slug'] ];
			switch ( $repo['type'] ) {
				case 'github_theme':
					$addition['GitHub Theme URI'] = $repo['uri'];
					break;
				case
				'bitbucket_theme':
					$addition['Bitbucket Theme URI'] = $repo['uri'];
					break;
				case 'gitlab_theme':
					$addition['GitLab Theme URI'] = $repo['uri'];
					break;
			}
			$addition['slug']                             = $repo['slug'];
			$this->add_to_github_updater[ $repo['slug'] ] = $addition;
		}
	}

}
