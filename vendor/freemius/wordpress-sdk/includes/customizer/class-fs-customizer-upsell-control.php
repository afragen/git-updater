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
	 * Class FS_Customizer_Upsell_Control
	 */
	class FS_Customizer_Upsell_Control extends WP_Customize_Control {

		/**
		 * Control type
		 *
		 * @var string control type
		 */
		public $type = 'freemius-upsell-control';

		/**
		 * @var Freemius
		 */
		public $fs = null;

		/**
		 * @param WP_Customize_Manager $manager the customize manager class.
		 * @param string               $id      id.
		 * @param array                $args    customizer manager parameters.
		 */
		public function __construct( WP_Customize_Manager $manager, $id, array $args ) {
			$manager->register_control_type( 'FS_Customizer_Upsell_Control' );

			parent::__construct( $manager, $id, $args );
		}

		/**
		 * Enqueue resources for the control.
		 */
		public function enqueue() {
			fs_enqueue_local_style( 'fs_customizer', 'customizer.css' );
		}

		/**
		 * Json conversion
		 */
		public function to_json() {
			$pricing_cta = esc_html( $this->fs->get_pricing_cta_label() ) . '&nbsp;&nbsp;' . ( is_rtl() ? '&#x2190;' : '&#x27a4;' );

			parent::to_json();

			$this->json['button_text'] = $pricing_cta;
			$this->json['button_url']  = $this->fs->is_in_trial_promotion() ?
				$this->fs->get_trial_url() :
				$this->fs->get_upgrade_url();

			$api = FS_Plugin::is_valid_id( $this->fs->get_bundle_id() ) ?
				$this->fs->get_api_bundle_scope() :
				$this->fs->get_api_plugin_scope();

			// Load features.
			$pricing = $api->get( $this->fs->add_show_pending( "pricing.json" ) );

			if ( $this->fs->is_api_result_object( $pricing, 'plans' ) ) {
				// Add support features.
				if ( is_array( $pricing->plans ) && 0 < count( $pricing->plans ) ) {
					$support_features = array(
						'kb'                 => 'Help Center',
						'forum'              => 'Support Forum',
						'email'              => 'Priority Email Support',
						'phone'              => 'Phone Support',
						'skype'              => 'Skype Support',
						'is_success_manager' => 'Personal Success Manager',
					);

					for ( $i = 0, $len = count( $pricing->plans ); $i < $len; $i ++ ) {
						if ( 'free' == $pricing->plans[$i]->name ) {
							continue;
						}

						if ( ! isset( $pricing->plans[ $i ]->features ) ||
                            ! is_array( $pricing->plans[ $i ]->features ) ) {
							$pricing->plans[$i]->features = array();
						}

						foreach ( $support_features as $key => $label ) {
							$key = ( 'is_success_manager' !== $key ) ?
								"support_{$key}" :
								$key;

							if ( ! empty( $pricing->plans[ $i ]->{$key} ) ) {

								$support_feature        = new stdClass();
								$support_feature->title = $label;

								$pricing->plans[ $i ]->features[] = $support_feature;
							}
						}
					}

                    $this->json['plans'] = $pricing->plans;
				}
			}

			$this->json['strings'] = array(
				'plan' => $this->fs->get_text_x_inline( 'Plan', 'as product pricing plan', 'plan' ),
			);
		}

		/**
		 * Control content
		 */
		public function content_template() {
			?>
			<div id="fs_customizer_upsell">
				<# if ( data.plans ) { #>
					<ul class="fs-customizer-plans">
						<# for (i in data.plans) { #>
							<# if ( 'free' != data.plans[i].name && (null != data.plans[i].features && 0 < data.plans[i].features.length) ) { #>
								<li class="fs-customizer-plan">
									<div class="fs-accordion-section-open">
										<h2 class="fs-accordion-section-title menu-item">
											<span>{{ data.plans[i].title }}</span>
											<button type="button" class="button-link item-edit" aria-expanded="true">
												<span class="screen-reader-text">Toggle section: {{ data.plans[i].title }} {{ data.strings.plan }}</span>
												<span class="toggle-indicator" aria-hidden="true"></span>
											</button>
										</h2>
										<div class="fs-accordion-section-content">
											<# if ( data.plans[i].description ) { #>
												<h3>{{ data.plans[i].description }}</h3>
											<# } #>
											<# if ( data.plans[i].features ) { #>
												<ul>
													<# for ( j in data.plans[i].features ) { #>
														<li><div class="fs-feature">
																<span class="dashicons dashicons-yes"></span><span><# if ( data.plans[i].features[j].value ) { #>{{ data.plans[i].features[j].value }} <# } #>{{ data.plans[i].features[j].title }}</span>
																<# if ( data.plans[i].features[j].description ) { #>
																	<span class="dashicons dashicons-editor-help"><span class="fs-feature-desc">{{ data.plans[i].features[j].description }}</span></span>
																	<# } #>
															</div></li>
														<# } #>
												</ul>
												<# } #>
													<# if ( 'free' != data.plans[i].name ) { #>
														<a href="{{ data.button_url }}" class="button button-primary" target="_blank">{{{ data.button_text }}}</a>
														<# } #>
										</div>
									</div>
								</li>
							<# } #>
						<# } #>
					</ul>
				<# } #>
			</div>
		<?php }
	}