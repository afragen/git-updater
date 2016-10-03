<?php

namespace Fragen\GitHub_Updater;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

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
	 * @var bool|Additions
	 */
	private static $instance = false;

	/**
	 * Holds array of plugin/theme headers to add to GitHub Updater.
	 *
	 * @var
	 */
	public $add_to_github_updater = array();

	/**
	 * Singleton
	 *
	 * @return object $instance Additions
	 */
	public static function instance() {
		if ( false === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register JSON config file.
	 *
	 * @param $config
	 * @param $repos
	 * @param $type
	 *
	 * @return bool
	 */
	public function register( $config, $repos, $type ) {
		if ( empty( $config ) ) {
			return false;
		}
		if ( null === ( $config = json_decode( $config, true ) ) ) {
			$error = new \WP_Error( 'json_invalid', 'JSON ' . json_last_error_msg() );
			Messages::instance()->create_error_message( $error );

			return false;
		}

		$this->add_headers( $config, $repos, $type );
	}

	/**
	 * Add GitHub Updater headers to plugins/themes via a filter hooks.
	 *
	 * @param $config
	 * @param $repos
	 * @param $type
	 */
	public function add_headers( $config, $repos, $type ) {
		$this->add_to_github_updater = array();
		foreach ( $config as $repo ) {
			// Continue if repo not installed.
			if ( ! array_key_exists( $repo['slug'], $repos ) ) {
				continue;
			}

			$addition                   = array();
			$additions[ $repo['slug'] ] = array();

			if ( 'plugin' === $type ) {
				$additions[ $repo['slug'] ] = $repos[ $repo['slug'] ];
			}

			switch ( $repo['type'] ) {
				case 'github_plugin':
				case 'github_theme':
					$addition['slug']                                  = $repo['slug'];
					$addition[ 'GitHub ' . ucwords( $type ) . ' URI' ] = $repo['uri'];
					break;
				case 'bitbucket_plugin':
				case 'bitbucket_theme':
					$addition['slug']                                     = $repo['slug'];
					$addition[ 'Bitbucket ' . ucwords( $type ) . ' URI' ] = $repo['uri'];
					break;
				case 'gitlab_plugin':
				case 'gitlab_theme':
					$addition['slug']                                  = $repo['slug'];
					$addition[ 'GitLab ' . ucwords( $type ) . ' URI' ] = $repo['uri'];
					break;
			}

			$this->add_to_github_updater[ $repo['slug'] ] = array_merge( $additions[ $repo['slug'] ], $addition );
		}
	}

}
