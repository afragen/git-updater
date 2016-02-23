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

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

/**
 * Class JsonUpgraderSkin
 *
 * @package Fragen\GitHub_Updater
 */
class JsonUpgraderSkin extends \WP_Upgrader_Skin {

	public $messages=array();
	public $error;

	public function feedback($string) {
		if ( isset( $this->upgrader->strings[$string] ) )
			$string = $this->upgrader->strings[$string];

		if ( strpos($string, '%') !== false ) {
			$args = func_get_args();
			$args = array_splice($args, 1);
			if ( $args ) {
			    $args = array_map( 'strip_tags', $args );
			    $args = array_map( 'esc_html', $args );
			    $string = vsprintf($string, $args);
			}
		}
		if ( empty($string) )
			return;

		//echo "$string\n";

		$this->messages[]=$string;
	}

	public function error($errors) {
		$this->error=TRUE;
		parent::error($errors);
	}

	public function decrement_update_count() {
	}

    public function header() {
    }

	public function footer() {
	}
}