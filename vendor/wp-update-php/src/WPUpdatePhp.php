<?php
/**
 * WPUpdatePHP
 *
 * @package   WPUpdatePhp
 * @author    Coen Jacobs
 * @license   GPL-2.0+
 * @link      https://github.com/WPupdatePHP/wp-update-php
 */

/**
 * WPUpdatePhp.
 */
class WPUpdatePhp {
	/** @var string */
	private $minimum_version;

	/** @var string */
	private $recommended_version;

	/** @var string */
	private $plugin_name = '';

	/**
	 * @param string $minimum_version     Minimum version of PHP.
	 * @param string $recommended_version Recommended version of PHP.
	 */
	public function __construct( $minimum_version, $recommended_version = null ) {
		$this->minimum_version     = $minimum_version;
		$this->recommended_version = $recommended_version;
	}

	/**
	 * Set the plugin name for the admin notice.
	 *
	 * @param string $name Name of the plugin to be used in admin notices.
	 */
	public function set_plugin_name( $name ) {
		$this->plugin_name = $name;
	}

	/**
	 * Check given PHP version against minimum required version.
	 *
	 * @param string $version Optional. PHP version to check against.
	 *                        Default is the current PHP version as a string in
	 *                        "major.minor.release[extra]" notation.
	 * @return bool True if supplied PHP version meets minimum required version.
	 */
	public function does_it_meet_required_php_version( $version = PHP_VERSION ) {
		if ( $this->version_passes_requirement( $this->minimum_version, $version ) ) {
			return true;
		}

		$this->load_version_notice( array( $this, 'minimum_admin_notice' ) );
		return false;
	}

	/**
	 * Check given PHP version against recommended version.
	 *
	 * @param string $version Optional. PHP version to check against.
	 *                        Default is the current PHP version as a string in
	 *                        "major.minor.release[extra]" notation.
	 * @return bool True if supplied PHP version meets recommended version.
	 */
	public function does_it_meet_recommended_php_version( $version = PHP_VERSION ) {
		if ( $this->version_passes_requirement( $this->recommended_version, $version ) ) {
			return true;
		}

		$this->load_version_notice( array( $this, 'recommended_admin_notice' ) );
		return false;
	}

	/**
	 * Check that one PHP version is less than or equal to another.
	 *
	 * @param string $requirement The baseline version of PHP.
	 * @param string $version     The given version of PHP.
	 * @return bool True if the requirement is less than or equal to given version.
	 */
	private function version_passes_requirement( $requirement, $version ) {
		return version_compare( $requirement, $version, '<=' );
	}

	/**
	 * Conditionally hook in an admin notice.
	 *
	 * @param callable $callback Callable that displays admin notice.
	 */
	private function load_version_notice( $callback ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			add_action( 'admin_notices', $callback );
			add_action( 'network_admin_notices', $callback );
		}
	}

	/**
	 * Return the string to be shown in the admin notice.
	 *
	 * This is based on the level (`recommended` or default `minimum`) of the
	 * notice. This will also add the plugin name to the notice string, if set.
	 *
	 * @param string $level Optional. Admin notice level, `recommended` or `minimum`.
	 *                      Default is `minimum`.
	 * @return string
	 */
	public function get_admin_notice( $level = 'minimum' ) {
		if ( 'recommended' === $level ) {
			if ( ! empty( $this->plugin_name ) ) {
				return '<p>' . $this->plugin_name . ' recommends a PHP version higher than ' . $this->recommended_version . '. Read more information about <a href="http://www.wpupdatephp.com/update/">how you can update</a>.</p>';
			} else {
				return '<p>This plugin recommends a PHP version higher than ' . $this->recommended_version . '. Read more information about <a href="http://www.wpupdatephp.com/update/">how you can update</a>.</p>';
			}
		}

		if ( ! empty( $this->plugin_name ) ) {
			return '<p>Unfortunately, ' . $this->plugin_name . ' cannot run on PHP versions older than ' . $this->minimum_version . '. Read more information about <a href="http://www.wpupdatephp.com/update/">how you can update</a>.</p>';
		} else {
			return '<p>Unfortunately, this plugin cannot run on PHP versions older than ' . $this->minimum_version . '. Read more information about <a href="http://www.wpupdatephp.com/update/">how you can update</a>.</p>';
		}
	}

	/**
	 * Method hooked into admin_notices when minimum required PHP version is not
	 * available to show this in a notice.
	 *
	 * @hook admin_notices
	 */
	public function minimum_admin_notice() {
		echo '<div class="error">';
		echo $this->get_admin_notice( 'minimum' );
		echo '</div>';
	}

	/**
	 * Method hooked into admin_notices when recommended PHP version is not
	 * available to show this in a notice.
	 *
	 * @hook admin_notices
	 */
	public function recommended_admin_notice() {
		echo '<div class="error">';
		echo $this->get_admin_notice( 'recommended' );
		echo '</div>';
	}
}
