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
 * Class Branch
 *
 * @package Fragen\GitHub_Updater
 */
class Branch extends API {

	/**
	 * Holds repo cache data.
	 *
	 * @var null
	 */
	public $cache;

	/**
	 * Branch constructor.
	 *
	 * @param null $cache
	 */
	public function __construct( $cache = null ) {
		$this->cache = $cache;

		add_filter( 'http_response', array( $this, 'set_branch_on_switch' ), 10, 3 );
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

	/**
	 * Set current branch on branch switch.
	 *
	 * @param $response
	 * @param $r
	 * @param $url
	 *
	 * @return $response Just a pass through.
	 */
	public function set_branch_on_switch( $response, $r, $url ) {
		$repo = isset( $_GET['plugin'] ) ? dirname( $_GET['plugin'] ) : null;
		$repo = isset( $_GET['theme'] ) ? $_GET['theme'] : $repo;

		if ( isset( $_GET['action'], $this->cache['repo'] ) &&
		     ( 'upgrade-plugin' === $_GET['action'] || 'upgrade-theme' === $_GET['action'] ) &&
		     $repo === $this->cache['repo'] &&
		     false !== strpos( $url, $this->cache['repo'] )
		) {
			$current_branch = array_key_exists( $_GET['rollback'], $this->cache['branches'] )
				? $_GET['rollback']
				: 'master';
			$this->set_repo_cache( 'current_branch', $current_branch, $repo );
			self::$options[ 'current_branch_' . $repo ] = $current_branch;
			update_site_option( 'github_updater', self::$options );
		}
		remove_filter( 'http_response', array( $this, 'set_branch_on_switch' ) );

		return $response;
	}

	/**
	 * Set current branch on install.
	 *
	 * @param $install
	 */
	public function set_branch_on_install( $install ) {
		$this->set_repo_cache( 'current_branch', $install['github_updater_branch'], $install['repo'] );
		self::$options[ 'current_branch_' . $install['repo'] ] = $install['github_updater_branch'];
		update_site_option( 'github_updater', self::$options );
	}

}
