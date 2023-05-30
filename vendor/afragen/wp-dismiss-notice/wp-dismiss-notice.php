<?php
/**
 * WP Dismiss Notice.
 *
 * @package wp-dismiss-notice
 * @see https://github.com/w3guy/persist-admin-notices-dismissal
 */

/**
 * Class WP_Dismiss_Notice
 */
class WP_Dismiss_Notice {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'load_script' ] );
		add_action( 'wp_ajax_wp_dismiss_notice', [ __CLASS__, 'dismiss_admin_notice' ] );
	}

	/**
	 * Enqueue javascript and variables.
	 */
	public static function load_script() {

		if ( is_customize_preview() ) {
			return;
		}

		$js_url  = plugins_url( 'js/dismiss-notice.js', __FILE__, 'wp-dismiss-notice' );
		$version = json_decode( file_get_contents( __DIR__ . '/composer.json' ) )->version;

		/**
		 * Filter composer.json vendor directory.
		 * Some people don't use the standard vendor directory.
		 *
		 * @param string Composer vendor directory.
		 */
		$vendor_dir       = apply_filters( 'dismiss_notice_vendor_dir', '/vendor' );
		$composer_js_path = untrailingslashit( $vendor_dir ) . '/afragen/wp-dismiss-notice/js/dismiss-notice.js';

		$theme_js_url  = get_theme_file_uri( $composer_js_path );
		$theme_js_file = parse_url( $theme_js_url, PHP_URL_PATH );

		if ( file_exists( ABSPATH . $theme_js_file ) ) {
			$js_url = $theme_js_url;
		}

		if ( '/vendor' !== $vendor_dir ) {
			$js_url = home_url( $composer_js_path );
		}

		wp_enqueue_script(
			'dismissible-notices',
			$js_url,
			[ 'jquery', 'common' ],
			$version,
			true
		);

		wp_localize_script(
			'dismissible-notices',
			'wp_dismiss_notice',
			[
				'nonce'   => wp_create_nonce( 'wp-dismiss-notice' ),
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			]
		);
	}

	/**
	 * Handles Ajax request to persist notices dismissal.
	 * Uses check_ajax_referer to verify nonce.
	 */
	public static function dismiss_admin_notice() {
		$option_name        = isset( $_POST['option_name'] ) ? sanitize_text_field( wp_unslash( $_POST['option_name'] ) ) : false;
		$dismissible_length = isset( $_POST['dismissible_length'] ) ? sanitize_text_field( wp_unslash( $_POST['dismissible_length'] ) ) : 14;

		if ( 'forever' !== $dismissible_length ) {
			// If $dismissible_length is not an integer default to 14.
			$dismissible_length = ( 0 === absint( $dismissible_length ) ) ? 14 : $dismissible_length;
			$dismissible_length = strtotime( absint( $dismissible_length ) . ' days' );
		}

		check_ajax_referer( 'wp-dismiss-notice', 'nonce' );
		self::set_admin_notice_cache( $option_name, $dismissible_length );
		wp_die();
	}

	/**
	 * Is admin notice active?
	 *
	 * @param string $arg data-dismissible content of notice.
	 *
	 * @return bool
	 */
	public static function is_admin_notice_active( $arg ) {
		$array = explode( '-', $arg );
		array_pop( $array );
		$option_name = implode( '-', $array );
		$db_record   = self::get_admin_notice_cache( $option_name );

		if ( 'forever' === $db_record ) {
			return false;
		} elseif ( absint( $db_record ) >= time() ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Returns admin notice cached timeout.
	 *
	 * @access public
	 *
	 * @param string|bool $id admin notice name or false.
	 *
	 * @return array|bool The timeout. False if expired.
	 */
	public static function get_admin_notice_cache( $id = false ) {
		if ( ! $id ) {
			return false;
		}
		$cache_key = 'wpdn-' . md5( $id );
		$timeout   = get_site_option( $cache_key );
		$timeout   = 'forever' === $timeout ? time() + 60 : $timeout;

		if ( empty( $timeout ) || time() > $timeout ) {
			return false;
		}

		return $timeout;
	}

	/**
	 * Sets admin notice timeout in site option.
	 *
	 * @access public
	 *
	 * @param string      $id       Data Identifier.
	 * @param string|bool $timeout  Timeout for admin notice.
	 *
	 * @return bool
	 */
	public static function set_admin_notice_cache( $id, $timeout ) {
		$cache_key = 'wpdn-' . md5( $id );
		update_site_option( $cache_key, $timeout );

		return true;
	}
}

// Initialize.
add_action( 'admin_init', [ 'WP_Dismiss_Notice', 'init' ] );
