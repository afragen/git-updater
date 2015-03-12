<?php

if ( ! class_exists( 'WPUpdatePhp' ) ) {

	class WPUpdatePhp {
		/** @var String */
		private $minimum_version;

		/**
		 * @var String
		 */
		private $plugin_name;

		/**
		 * @param $minimum_version
		 */
		public function __construct( $minimum_version ) {
			$this->minimum_version = $minimum_version;
		}

		/**
		 * @param $file
		 *
		 * @return mixed
		 */
		private function set_plugin_name_from_file( $file ) {
			include_once( ABSPATH . '/wp-admin/includes/plugin.php' );
			$headers = get_plugin_data( $file );
			return $headers['Name'];
		}

		/**
		 * @param $version
		 *
		 * @return bool
		 */
		public function does_it_meet_required_php_version( $version = PHP_VERSION ) {
			if ( $this->is_minimum_php_version( $version ) ) {
				return true;
			}

			$this->load_minimum_required_version_notice();
			return false;
		}

		/**
		 * @param $version
		 *
		 * @return boolean
		 */
		private function is_minimum_php_version( $version ) {
			return version_compare( $this->minimum_version, $version, '<=' );
		}

		/**
		 * @param $file
		 *
		 * @return bool
		 */
		public function does_my_plugin_meet_required_php_version( $file ) {
			$this->plugin_name = $this->set_plugin_name_from_file( $file );
			return $this->does_it_meet_required_php_version();
		}

		/**
		 * @return void
		 */
		private function load_minimum_required_version_notice() {
			if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
				add_action( 'admin_notices', array( $this, 'admin_notice' ) );

				//for some reason admin_notice hook doesn't load but admin_head does
				add_action( 'admin_head', array( $this, 'admin_notice' ) );
			}
		}

		/**
		 * Generate admin notice
		 */
		public function admin_notice() {
			?>
			<div class="error">
				<p>
					<?php printf( __( 'Unfortunately, <strong>%1$s</strong> can not run on PHP versions older than %2$s. Read more information about <a href="http://www.wpupdatephp.com/update/">how you can update</a>.', 'github-updater' ), $this->plugin_name, $this->minimum_version ); ?>
				</p>
			</div>
		<?php
		}

	}
	
}

