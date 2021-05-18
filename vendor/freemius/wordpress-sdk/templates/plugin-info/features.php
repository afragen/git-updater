<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.0.6
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * @var array $VARS
	 *
	 * @var FS_Plugin $plugin
	 */
	$plugin = $VARS['plugin'];

	$plans = $VARS['plans'];

	$features_plan_map = array();
	foreach ( $plans as $plan ) {
		if (!empty($plan->features) && is_array($plan->features)) {
			foreach ( $plan->features as $feature ) {
				if ( ! isset( $features_plan_map[ $feature->id ] ) ) {
					$features_plan_map[ $feature->id ] = array( 'feature' => $feature, 'plans' => array() );
				}

				$features_plan_map[ $feature->id ]['plans'][ $plan->id ] = $feature;
			}
		}

		// Add support as a feature.
		if ( ! empty( $plan->support_email ) ||
		     ! empty( $plan->support_skype ) ||
		     ! empty( $plan->support_phone ) ||
		     true === $plan->is_success_manager
		) {
			if ( ! isset( $features_plan_map['support'] ) ) {
				$support_feature        = new stdClass();
				$support_feature->id    = 'support';
				$support_feature->title = fs_text_inline( 'Support', $plugin->slug );
				$features_plan_map[ $support_feature->id ] = array( 'feature' => $support_feature, 'plans' => array() );
			} else {
				$support_feature = $features_plan_map['support'];
			}

			$features_plan_map[ $support_feature->id ]['plans'][ $plan->id ] = $support_feature;
		}
	}

	// Add updates as a feature for all plans.
	$updates_feature        = new stdClass();
	$updates_feature->id    = 'updates';
	$updates_feature->title = fs_text_inline( 'Unlimited Updates', 'unlimited-updates', $plugin->slug );
	$features_plan_map[ $updates_feature->id ] = array( 'feature' => $updates_feature, 'plans' => array() );
	foreach ( $plans as $plan ) {
		$features_plan_map[ $updates_feature->id ]['plans'][ $plan->id ] = $updates_feature;
	}
?>
<div class="fs-features">
	<table>
		<thead>
		<tr>
			<th></th>
			<?php foreach ( $plans as $plan ) : ?>
				<th>
					<?php echo $plan->title ?>
					<span class="fs-price"><?php
							if ( empty( $plan->pricing ) ) {
								fs_esc_html_echo_inline( 'Free', 'free', $plugin->slug );
							} else {
								foreach ( $plan->pricing as $pricing ) {
									/**
									 * @var FS_Pricing $pricing
									 */
									if ( 1 == $pricing->licenses ) {
										if ( $pricing->has_annual() ) {
											echo "\${$pricing->annual_price} / " . fs_esc_html_x_inline( 'year', 'as annual period', 'year', $plugin->slug );
										} else if ( $pricing->has_monthly() ) {
											echo "\${$pricing->monthly_price} / " . fs_esc_html_x_inline( 'mo', 'as monthly period', 'mo', $plugin->slug );
										} else {
											echo "\${$pricing->lifetime_price}";
										}
									}
								}
							}
						?></span>
				</th>
			<?php endforeach ?>
		</tr>
		</thead>
		<tbody>
		<?php $odd = true;
			foreach ( $features_plan_map as $feature_id => $data ) : ?>
				<tr class="fs-<?php echo $odd ? 'odd' : 'even' ?>">
					<td><?php echo esc_html( ucfirst( $data['feature']->title ) ) ?></td>
					<?php foreach ( $plans as $plan ) : ?>
						<td>
							<?php if ( isset( $data['plans'][ $plan->id ] ) ) : ?>
								<?php if ( ! empty( $data['plans'][ $plan->id ]->value ) ) : ?>
									<b><?php echo esc_html( $data['plans'][ $plan->id ]->value ) ?></b>
								<?php else : ?>
									<i class="dashicons dashicons-yes"></i>
								<?php endif ?>
							<?php endif ?>
						</td>
					<?php endforeach ?>
				</tr>
				<?php $odd = ! $odd; endforeach ?>
		</tbody>
	</table>
</div>