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
 * Class Branch_Finder
 *
 * @package Fragen\GitHub_Updater
 */
class Branch_Finder extends API {

	/**
	 * Holds repo cache data.
	 *
	 * @var null
	 */
	public $cache;

	/**
	 * Branch_Finder constructor.
	 *
	 * @param null $cache
	 */
	public function __construct( $cache = null ) {
		$this->cache = $cache;

		add_filter( 'http_api_debug', array( $this, 'set_branch_on_switch' ), 10, 5 );
	}

	/**
	 * Set new branch on branch switch.
	 *
	 * @param $response
	 * @param $type
	 * @param $class
	 * @param $args
	 * @param $url
	 */
	public function set_branch_on_switch( $response, $type, $class, $args, $url ) {
		$repo = isset( $_GET['plugin'] ) ? dirname( $_GET['plugin'] ) : null;
		$repo = isset( $_GET['theme'] ) ? $_GET['theme'] : $repo;

		if ( isset( $_GET['action'] ) &&
		     ( 'upgrade-plugin' === $_GET['action'] || 'upgrade-theme' === $_GET['action'] ) &&
		     ( $repo === $this->cache['repo'] &&
		       array_key_exists( $_GET['rollback'], $this->cache['branches'] )
		     ) &&
		     false !== strpos( $url, $this->cache['repo'] )
		) {
			$this->set_repo_cache( 'current_branch', $_GET['rollback'], $repo );
			self::$options[ 'current_branch_' . $repo ] = $_GET['rollback'];
			update_site_option( 'github_updater', self::$options );

		}
		remove_filter( 'http_api_debug', array( $this, 'set_branch_on_switch' ) );
	}

	/**
	 * Get the current repo branch.
	 *
	 * @param $repo
	 *
	 * @return mixed
	 */
	public function get_current_branch( $repo ) {
		$current_branch = ! empty( $this->cache['current_branch'] )
			? $this->cache['current_branch']
			: $repo->branch;

		return $current_branch;
	}

}
