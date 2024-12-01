<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2024, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       2.9.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	class FS_Contact_Form_Manager {

		# region Singleton

		/**
		 * @var FS_Contact_Form_Manager
		 */
		private static $_instance;

		/**
		 * @return FS_Contact_Form_Manager
		 */
		static function instance() {
			if ( ! isset( self::$_instance ) ) {
				self::$_instance = new FS_Contact_Form_Manager();
			}

			return self::$_instance;
		}

		private function __construct() {
		}

		#endregion

		/**
		 * Retrieves the query params needed to load the Freemius Contact Form in the context of the plugin.
		 *
		 * @param Freemius $fs
		 *
		 * @return array<string, string>
		 */
		public function get_query_params( Freemius $fs ) {
			$context_params = array(
				'plugin_id'         => $fs->get_id(),
				'plugin_public_key' => $fs->get_public_key(),
				'plugin_version'    => $fs->get_plugin_version(),
			);

			// Get site context secure params.
			if ( $fs->is_registered() ) {
				$context_params = array_merge( $context_params, FS_Security::instance()->get_context_params(
					$fs->get_site(),
					time(),
					'contact'
				) );
			}

			return array_merge( $_GET, array_merge( $context_params, array(
				'plugin_version' => $fs->get_plugin_version(),
				'wp_login_url'   => wp_login_url(),
				'site_url'       => Freemius::get_unfiltered_site_url(),
				//		'wp_admin_css' => get_bloginfo('wpurl') . "/wp-admin/load-styles.php?c=1&load=buttons,wp-admin,dashicons",
			) ) );
		}

		/**
		 * Retrieves the standalone link to the Freemius Contact Form.
		 *
		 * @param Freemius $fs
		 *
		 * @return string
		 */
		public function get_standalone_link( Freemius $fs ) {
			$query_params = $this->get_query_params( $fs );

			$query_params['is_standalone'] = 'true';
			$query_params['parent_url']    = admin_url( add_query_arg( null, null ) );

			return WP_FS__ADDRESS . '/contact/?' . http_build_query( $query_params );
		}
	}