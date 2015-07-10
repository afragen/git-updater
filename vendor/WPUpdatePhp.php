<?php

if ( class_exists( 'WPUpdatePhp' ) ) {
	return;
}

class WPUpdatePhp {
	/** @var string */
	private $minimum_version;

	/** @var string */
	private $recommended_version;

	/** @var string */
	private $plugin_name = '';

	/**
	 * @param $minimum_version string
	 * @param $recommended_version string
	 */
	public function __construct( $minimum_version, $recommended_version = null ) {
		$this->minimum_version = $minimum_version;
		$this->recommended_version = $recommended_version;
		$this->plugin_name = __( 'this plugin', 'github-updater' );
	}

	/**
	 * @param $name string Name of the plugin to be used in admin notices
	 */
	public function set_plugin_name( $name ) {
		$this->plugin_name = $name;
	}

	/**
	 * @param $version
	 *
	 * @return bool
	 */
	public function does_it_meet_required_php_version( $version = PHP_VERSION ) {
		if ( $this->version_passes_requirement( $this->minimum_version, $version ) ) {
			return true;
		}

		$this->load_version_notice( array( $this, 'minimum_admin_notice' ) );
		return false;
	}

	/**
	 * @param $version
	 *
	 * @return bool
	 */
	public function does_it_meet_recommended_php_version( $version = PHP_VERSION ) {
		if ( $this->version_passes_requirement( $this->recommended_version, $version ) ) {
			return true;
		}

		$this->load_version_notice( array( $this, 'recommended_admin_notice' ) );
		return false;
	}

	/**
	 * @param $requirement
	 * @param $version
	 *
	 * @return bool
	 */
	private function version_passes_requirement( $requirement, $version ) {
		return version_compare( $requirement, $version, '<=' );
	}

	/**
	 * @param $callback
	 *
	 * @return void
	 */
	private function load_version_notice( $callback ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			add_action( 'admin_notices', $callback );
			add_action( 'network_admin_notices', $callback );
		}
	}

	/**
	 * Method hooked into admin_notices when minimum PHP version is not available to show this in a notice
	 * @hook admin_notices
	 */
	public function minimum_admin_notice() {
		?>
		<div class="error notice is-dismissible">
			<p>
			<?php printf( __( 'Unfortunately, %1$s can not run on PHP versions older than %2$s.', 'github-updater' ), $this->plugin_name, $this->minimum_version ); ?>
			<br>
			<?php printf( __( 'Read more information about %show you can update%s.', 'github-updater' ), '<a href="http://www.wpupdatephp.com/update/">', '</a>' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Method hooked into admin_notices when recommended PHP version is not available to show this in a notice
	 * @hook admin_notices
	 */
	public function recommended_admin_notice() {
		?>
		<div class="error notice is-dismissible">
			<p>
				<?php printf( __( '%1$s recommends a PHP version greater than %2$s.', 'github-updater' ), ucfirst( $this->plugin_name ), $this->recommended_version ); ?>
				<br>
				<?php printf( __( 'Read more information about %show you can update%s.', 'github-updater' ), '<a href="http://www.wpupdatephp.com/update/">', '</a>' ); ?>
			</p>
		</div>
		<?php
	}
}
