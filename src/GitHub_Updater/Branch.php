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
 * Class Branch
 *
 * @package Fragen\GitHub_Updater
 */
class Branch extends API {

	/**
	 * Holds repo cache data.
	 *
	 * @access public
	 * @var null
	 */
	public $cache;

	/**
	 * Branch constructor.
	 *
	 * @access public
	 *
	 * @param null $cache
	 */
	public function __construct( $cache = null ) {
		$this->cache = $cache;
	}

	/**
	 * Get the current repo branch.
	 *
	 * @access public
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
	 * @access public
	 *
	 * @param string $repo Repository slug.
	 */
	public function set_branch_on_switch( $repo ) {
		$this->cache = $this->get_repo_cache( $repo );

		if ( isset( $_GET['action'], $this->cache['branches'] ) &&
		     ( 'upgrade-plugin' === $_GET['action'] || 'upgrade-theme' === $_GET['action'] )
		) {
			$current_branch = array_key_exists( $_GET['rollback'], $this->cache['branches'] )
				? $_GET['rollback']
				: 'master';
			$this->set_repo_cache( 'current_branch', $current_branch, $repo );
			self::$options[ 'current_branch_' . $repo ] = $current_branch;
			update_site_option( 'github_updater', self::$options );
		}
	}

	/**
	 * Set current branch on install.
	 *
	 * @access public
	 *
	 * @param array $install Array of install data.
	 */
	public function set_branch_on_install( $install ) {
		$this->set_repo_cache( 'current_branch', $install['github_updater_branch'], $install['repo'] );
		self::$options[ 'current_branch_' . $install['repo'] ] = $install['github_updater_branch'];
		update_site_option( 'github_updater', self::$options );
	}

}
