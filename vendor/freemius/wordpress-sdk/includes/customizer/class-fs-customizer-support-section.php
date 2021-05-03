<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.2.2.7
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class Zerif_Customizer_Theme_Info_Main
	 *
	 * @since  1.0.0
	 * @access public
	 */
	class FS_Customizer_Support_Section extends WP_Customize_Section {

		function __construct( $manager, $id, $args = array() ) {
			$manager->register_section_type( 'FS_Customizer_Support_Section' );

			parent::__construct( $manager, $id, $args );
		}

		/**
		 * The type of customize section being rendered.
		 *
		 * @since  1.0.0
		 * @access public
		 * @var    string
		 */
		public $type = 'freemius-support-section';

		/**
		 * @var Freemius
		 */
		public $fs = null;

		/**
		 * Add custom parameters to pass to the JS via JSON.
		 *
		 * @since  1.0.0
		 */
		public function json() {
			$json = parent::json();

			$is_contact_visible = $this->fs->is_page_visible( 'contact' );
			$is_support_visible = $this->fs->is_page_visible( 'support' );

			$json['theme_title'] = $this->fs->get_plugin_name();

			if ( $is_contact_visible && $is_support_visible ) {
				$json['theme_title'] .= ' ' . $this->fs->get_text_inline( 'Support', 'support' );
			}

			if ( $is_contact_visible ) {
				$json['contact'] = array(
					'label' => $this->fs->get_text_inline( 'Contact Us', 'contact-us' ),
					'url'   => $this->fs->contact_url(),
				);
			}

			if ( $is_support_visible ) {
				$json['support'] = array(
					'label' => $this->fs->get_text_inline( 'Support Forum', 'support-forum' ),
					'url'   => $this->fs->get_support_forum_url()
				);
			}

			return $json;
		}

		/**
		 * Outputs the Underscore.js template.
		 *
		 * @since  1.0.0
		 */
		protected function render_template() {
			?>
			<li id="fs_customizer_support"
			    class="accordion-section control-section control-section-{{ data.type }} cannot-expand">
				<h3 class="accordion-section-title">
					<span>{{ data.theme_title }}</span>
					<# if ( data.contact && data.support ) { #>
					<div class="button-group">
					<# } #>
						<# if ( data.contact ) { #>
							<a class="button" href="{{ data.contact.url }}" target="_blank" rel="noopener noreferrer">{{ data.contact.label }} </a>
							<# } #>
						<# if ( data.support ) { #>
							<a class="button" href="{{ data.support.url }}" target="_blank" rel="noopener noreferrer">{{ data.support.label }} </a>
							<# } #>
					<# if ( data.contact && data.support ) { #>
					</div>
					<# } #>
				</h3>
			</li>
			<?php
		}
	}