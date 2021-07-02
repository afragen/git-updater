<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater;

/**
 * Class Ignore
 *
 * For when you want Git Updater to ignore specific repositories.
 */
class Ignore {

	/**
	 * Holds array of repositories to ignore.
	 *
	 * @var array
	 */
	public static $repos;

	/**
	 * Constructor.
	 *
	 * @param string $slug Repository slug.
	 * @param string $file Repository file, 'test-plugin/plugin.php' or 'test-child/style.css'.
	 */
	public function __construct( $slug = null, $file = null ) {
		self::$repos[ $slug ] = $file;
		$this->load_hooks();
	}

	/**
	 * Load hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {

		// Remove repository from array of repositories.
		add_filter(
			'gu_config_pre_process',
			function( $config ) {
				foreach ( self::$repos as $slug => $file ) {
					unset( $config[ $slug ] );
				}

				return $config;
			},
			10,
			1
		);

		// Fix to display properly in Settings git subtab.
		add_filter(
			'gu_display_repos',
			function( $type_repos ) {
				foreach ( self::$repos  as $slug => $file ) {
					if ( isset( $type_repos[ $slug ] ) ) {
						$type_repos[ $slug ]->remote_version = false;
						$type_repos[ $slug ]->dismiss        = true;
					}
				}

				return $type_repos;
			},
			10,
			1
		);

		// Don't display Settings token field.
		add_filter(
			'gu_add_repo_setting_field',
			function( $arr, $token ) {
				foreach ( self::$repos as $file ) {
					if ( $file === $token->file ) {
						$arr = [];
					}
				}

				return $arr;
			},
			15,
			2
		);
	}
}
