<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater\REST;

use WP_Upgrader_Skin;

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
class Rest_Upgrader_Skin extends WP_Upgrader_Skin {
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
	 * @param string $message  Message.
	 * @param array  ...$args Array of args.
	 */
	public function feedback( $message, ...$args ) {
		if ( isset( $this->upgrader->strings[ $message ] ) ) {
			$string = $this->upgrader->strings[ $message ];
		}

		if ( empty( $string ) ) {
			return;
		}

		if ( str_contains( $string, '%' ) ) {
			if ( $args ) {
				$args   = array_map( 'strip_tags', $args );
				$args   = array_map( 'esc_html', $args );
				$string = vsprintf( $string, $args );
			}
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
