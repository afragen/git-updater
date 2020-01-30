<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen, Mikael Lindqvist
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

namespace Fragen\GitHub_Updater;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

/**
 * Class Rest_Upgrader_Skin
 *
 * Extends WP_Upgrader_Skin and collects outputed messages for later
 * processing, rather than printing them out.
 */
class Rest_Upgrader_Skin extends \WP_Upgrader_Skin {
	/**
	 * Holds messages.
	 *
	 * @var array $messages
	 */
	public $messages = [];

	/**
	 * Boolean if errors are present.
	 *
	 * @var bool $error
	 */
	public $error;

	/**
	 * Overrides the feedback method.
	 * Adds the feedback string to the messages array.
	 *
	 * @param string $string  Message.
	 * @param array  ...$args Array of args.
	 */
	public function feedback( $string, ...$args ) {
		if ( isset( $this->upgrader->strings[ $string ] ) ) {
			$string = $this->upgrader->strings[ $string ];
		}

		if ( false !== strpos( $string, '%' ) ) {
			if ( $args ) {
				$args   = array_map( 'strip_tags', $args );
				$args   = array_map( 'esc_html', $args );
				$string = vsprintf( $string, $args );
			}
		}
		if ( empty( $string ) ) {
			return;
		}

		$this->messages[] = $string;
	}

	/**
	 * Set the error flag to true, then let the base class handle the rest.
	 *
	 * @param mixed $errors Error messages.
	 */
	public function error( $errors ) {
		$this->error = true;
		parent::error( $errors );
	}

	/**
	 * Do nothing.
	 *
	 * @param mixed $type I don't know, not used.
	 */
	protected function decrement_update_count( $type ) {
	}

	/**
	 * Do nothing.
	 */
	public function header() {
	}

	/**
	 * Do nothing.
	 */
	public function footer() {
	}
}
