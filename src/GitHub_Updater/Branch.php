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

use Fragen\Singleton;


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
class Branch {

	/**
	 * Holds repo cache data.
	 *
	 * @access public
	 * @var null
	 */
	public $cache;

	/**
	 * Holds site options.
	 *
	 * @var array $options
	 */
	protected static $options;

	/**
	 * Branch constructor.
	 *
	 * @access public
	 *
	 * @param null $cache
	 */
	public function __construct( $cache = null ) {
		$this->cache     = $cache;
		$base            = Singleton::get_instance( 'Base', $this );
		static::$options = $base::$options;
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
		$this->cache = Singleton::get_instance( 'API_PseudoTrait', $this )->get_repo_cache( $repo );

		if ( isset( $_GET['action'], $_GET['rollback'], $this->cache['branches'] ) &&
		     ( 'upgrade-plugin' === $_GET['action'] || 'upgrade-theme' === $_GET['action'] )
		) {
			$current_branch = array_key_exists( $_GET['rollback'], $this->cache['branches'] )
				? $_GET['rollback']
				: 'master';
			Singleton::get_instance( 'API_PseudoTrait', $this )->set_repo_cache( 'current_branch', $current_branch, $repo );
			static::$options[ 'current_branch_' . $repo ] = $current_branch;
			update_site_option( 'github_updater', static::$options );
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
		Singleton::get_instance( 'API_PseudoTrait', $this )->set_repo_cache( 'current_branch', $install['github_updater_branch'], $install['repo'] );
		static::$options[ 'current_branch_' . $install['repo'] ] = $install['github_updater_branch'];
		update_site_option( 'github_updater', static::$options );
	}

}
