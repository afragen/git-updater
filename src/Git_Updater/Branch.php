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
}
