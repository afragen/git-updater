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

use Fragen\Git_Updater\Traits\GU_Trait;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Branch
 */
class Branch {
	use GU_Trait;

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
	private static $options;

	/**
	 * Branch constructor.
	 *
	 * @access public
	 *
	 * @param null $cache Data for caching.
	 */
	public function __construct( $cache = null ) {
		$this->cache = $cache;
		$this->load_options();
		self::$options = $this->get_class_vars( 'Base', 'options' );
	}

	/**
	 * Get the current repo branch.
	 *
	 * @access public
	 *
	 * @param \stdClass $repo Repository object.
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
	 * Exit early if not a rollback.
	 *
	 * @access public
	 *
	 * @param string $repo Repository slug.
	 * @return void
	 */
	public function set_branch_on_switch( $repo ) {
		$this->cache = $this->get_repo_cache( $repo );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$rollback = isset( $_GET['rollback'] ) ? wp_unslash( $_GET['rollback'] ) : false;
		// Exit early if not a rollback, ie normal update.
		if ( ! $rollback ) {
			return;
		}

		$tag_array    = isset( $this->cache['tags'] ) && is_array( $this->cache['tags'] );
		$in_tag_array = $tag_array && in_array( $rollback, $this->cache['tags'], true );
		if ( $in_tag_array ) {
			$current_branch = isset( $this->cache[ $repo ]['PrimaryBranch'] ) ? $this->cache[ $repo ]['PrimaryBranch'] : 'master';
		}

		if ( ! $in_tag_array && isset( $_GET['action'], $this->cache['branches'] )
			&& in_array( $_GET['action'], [ 'upgrade-plugin', 'upgrade-theme' ], true )
		) {
			// phpcs:enable
			$current_branch = array_key_exists( $rollback, $this->cache['branches'] )
				? sanitize_text_field( $rollback )
				: 'master';
		}
		$this->set_repo_cache( 'current_branch', $current_branch, $repo );
		self::$options[ 'current_branch_' . $repo ] = $current_branch;
		update_site_option( 'git_updater', self::$options );
	}

	/**
	 * Set current branch on install and update options.
	 *
	 * @access public
	 *
	 * @param array $install Array of install data.
	 */
	public function set_branch_on_install( $install ) {
		$this->set_repo_cache( 'current_branch', $install['git_updater_branch'], $install['repo'] );
		self::$options[ 'current_branch_' . $install['repo'] ] = $install['git_updater_branch'];
		update_site_option( 'git_updater', self::$options );
	}
}
