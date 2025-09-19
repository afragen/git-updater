<?php
/**
 * @package   Freemius
 * @copyright Copyright (c) 2015, Freemius, Inc.
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
 * @since     2.2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @var array $VARS
 */
$fs   = freemius( $VARS['id'] );
$slug = $fs->get_slug();

/**
 * @var FS_Plugin_License $license
 */
$license = $VARS['license'];

$has_trial = $VARS['has_trial'];

$subscription_cancellation_context = $has_trial ?
    fs_text_inline( 'trial', 'trial', $slug ) :
    fs_text_inline( 'subscription', 'subscription', $slug );

$plan         = $fs->get_plan();
$module_label = $fs->get_module_label( true );

if ( $VARS['is_license_deactivation'] ) {
    $subscription_cancellation_text = '';
} else {
    $subscription_cancellation_text = sprintf(
        ( $fs->is_theme() ?
            fs_text_inline(
                "Deactivating or uninstalling the %s will automatically disable the license, which you'll be able to use on another site.",
                'deactivation-or-uninstall-message',
                $slug
            ) :
            fs_text_inline(
                "Uninstalling the %s will automatically disable the license, which you'll be able to use on another site.",
                'uninstall-message',
                $slug
            ) ),
        $module_label
    ) . ' ';
}

    $subscription_cancellation_text .= sprintf(
    fs_text_inline(
        'In case you are NOT planning on using this %s on this site (or any other site) - would you like to cancel the %s as well?',
        'cancel-subscription-message',
        $slug
    ),
    ( $VARS['is_license_deactivation'] ? fs_text_inline( 'license', 'license', $slug ) : $module_label ),
    $subscription_cancellation_context
);

$cancel_subscription_action_label = sprintf(
    fs_esc_html_inline(
        "Cancel %s - I no longer need any security & feature updates, nor support for %s because I'm not planning to use the %s on this, or any other site.",
        'cancel-x',
        $slug
    ),
    esc_html( $subscription_cancellation_context ),
    sprintf( '<strong>%s</strong>', esc_html( $fs->get_plugin_title() ) ),
    esc_html( $module_label )
);

$keep_subscription_active_action_label = esc_html( sprintf(
    fs_text_inline(
        "Don't cancel %s - I'm still interested in getting security & feature updates, as well as be able to contact support.",
        'dont-cancel-x',
        $slug
    ),
    $subscription_cancellation_context
) );

$subscription_cancellation_text = esc_html( $subscription_cancellation_text );

$subscription_cancellation_html = <<< HTML
    <div class="notice notice-error inline"><p></p></div><p>{$subscription_cancellation_text}</p>
    <ul class="subscription-actions">
        <li>
            <label>
                <input type="radio" name="cancel-subscription" value="false"/>
                <span>{$keep_subscription_active_action_label}</span>
            </label>
        </li>
        <li>
            <label>
                <input type="radio" name="cancel-subscription" value="true"/>
                <span>{$cancel_subscription_action_label}</span>
            </label>
        </li>
    </ul>
HTML;

$downgrading_plan_text                      = fs_text_inline( 'Downgrading your plan', 'downgrading-plan', $slug );
$cancelling_subscription_text               = fs_text_inline( 'Cancelling the subscription', 'cancelling-subscription', $slug );
/* translators: %1$s: Either 'Downgrading your plan' or 'Cancelling the subscription' */
$downgrade_x_confirm_text                   = fs_text_inline( '%1$s will immediately stop all future recurring payments and your %2$s plan license will expire in %3$s.', 'downgrade-x-confirm', $slug );
$prices_increase_text                       = fs_text_inline( 'Please note that we will not be able to grandfather outdated pricing for renewals/new subscriptions after a cancellation. If you choose to renew the subscription manually in the future, after a price increase, which typically occurs once a year, you will be charged the updated price.', 'pricing-increase-warning', $slug );
$after_downgrade_non_blocking_text          = fs_text_inline( 'You can still enjoy all %s features but you will not have access to %s security & feature updates, nor support.', 'after-downgrade-non-blocking', $slug );
$after_downgrade_blocking_text              = fs_text_inline( 'Once your license expires you can still use the Free version but you will NOT have access to the %s features.', 'after-downgrade-blocking', $slug );
$after_downgrade_blocking_text_premium_only = fs_text_inline( 'Once your license expires you will no longer be able to use the %s, unless you activate it again with a valid premium license.', 'after-downgrade-blocking-premium-only', $slug );

$subscription_cancellation_confirmation_message = $has_trial ?
    fs_text_inline( 'Cancelling the trial will immediately block access to all premium features. Are you sure?', 'cancel-trial-confirm', $slug ) :
    sprintf(
        '%s %s %s %s',
        sprintf(
            $downgrade_x_confirm_text,
            ($fs->is_only_premium() ? $cancelling_subscription_text : $downgrading_plan_text ),
            $plan->title,
            human_time_diff( time(), strtotime( $license->expiration ) )
        ),
        (
        $license->is_block_features ?
            (
                $fs->is_only_premium() ?
                    sprintf( $after_downgrade_blocking_text_premium_only, $module_label ) :
                    sprintf( $after_downgrade_blocking_text, $plan->title )
            ) :
            sprintf( $after_downgrade_non_blocking_text, $plan->title, $fs->get_module_label( true ) )
        ),
        $prices_increase_text,
        fs_esc_attr_inline( 'Are you sure you want to proceed?', 'proceed-confirmation', $slug )
    );

fs_enqueue_local_style( 'fs_dialog_boxes', '/admin/dialog-boxes.css' );
?>
<script type="text/javascript">
    (function( $ ) {
        var modalHtml =
            '<div class="fs-modal fs-modal-subscription-cancellation fs-modal-subscription-cancellation-<?php echo $fs->get_id() ?>">'
            + '	<div class="fs-modal-dialog">'
            + '		<div class="fs-modal-header">'
            + '		    <h4><?php echo esc_html( sprintf( fs_text_inline( 'Cancel %s?', 'cancel-x', $slug ), ucfirst( $subscription_cancellation_context ) ) ) ?></h4>'
            + '		</div>'
            + '		<div class="fs-modal-body">'
            + '			<div class="fs-modal-panel active">' + <?php echo json_encode( $subscription_cancellation_html ) ?> + '<p class="fs-price-increase-warning" style="display: none;">' + <?php echo json_encode( $prices_increase_text ) ?> + '</p></div>'
            + '		</div>'
            + '		<div class="fs-modal-footer">'
            + '			<a href="#" class="button button-secondary button-close"><?php fs_esc_attr_echo_inline( 'Cancel', 'cancel', $slug ) ?></a>'
            + '			<a href="#" class="button button-primary button-deactivate disabled"><?php fs_esc_attr_echo_inline( 'Proceed', 'proceed', $slug ) ?></a>'
            + '		</div>'
            + '	</div>'
            + '</div>',
            $modal    = $(modalHtml);

        $modal.appendTo($('body'));

        registerEventHandlers();

        function registerEventHandlers() {
            $modal.on( 'showModal', function() {
                showModal();
            });

            $modal.on( 'closeModal', function() {
                closeModal();
            });

            $modal.on('click', '.fs-modal-footer .button', function (evt) {
                evt.preventDefault();

                if ($(this).hasClass('disabled')) {
                    return;
                }

                var _this                                   = $(this),
                    subscriptionCancellationActionEventName = <?php echo json_encode( $fs->get_action_tag( 'subscription_cancellation_action' ) ) ?>;

                if ( _this.hasClass( 'button-primary' ) ) {
                    if ( 'true' !== $modal.find( 'input[name="cancel-subscription"]:checked' ).val() ) {
                        $modal.trigger( subscriptionCancellationActionEventName, false );
                    } else {
                        if ( confirm( <?php echo json_encode( $subscription_cancellation_confirmation_message ) ?> ) ) {
                            $modal.trigger( subscriptionCancellationActionEventName, true );
                        }
                    }
                }
            });

            $modal.on('click', 'input[type="radio"]', function () {
                var
                    $selectedOption = $( this ),
                    $primaryButton  = $modal.find( '.button-primary' ),
                    isSelected      = ( 'true' === $selectedOption.val() );

                if ( isSelected ) {
                    $primaryButton.html( <?php echo json_encode( sprintf(
                        fs_text_inline( 'Cancel %s & Proceed', 'cancel-x-and-proceed', $slug ),
                        ucfirst( $subscription_cancellation_context )
                    ) ) ?> );

                    $modal.find('.fs-price-increase-warning').show();
                } else {
                    $primaryButton.html( <?php fs_json_encode_echo_inline( 'Proceed', 'proceed', $slug ) ?> );
                    $modal.find('.fs-price-increase-warning').hide();
                }

                $primaryButton.toggleClass( 'warn', isSelected );
                $primaryButton.removeClass( 'disabled' );
            });

            // If the user has clicked outside the window, cancel it.
            $modal.on('click', function (evt) {
                var $target = $(evt.target);

                // If the user has clicked anywhere in the modal dialog, just return.
                if ($target.hasClass('fs-modal-body') || $target.hasClass('fs-modal-footer')) {
                    return;
                }

                // If the user has not clicked the close button and the clicked element is inside the modal dialog, just return.
                if (
                    ( ! $target.hasClass( 'button-close' ) ) &&
                    ( $target.parents( '.fs-modal-body' ).length > 0 || $target.parents( '.fs-modal-footer' ).length > 0 )
                ) {
                    return;
                }

                closeModal();

                return false;
            });
        }

        function showModal() {
            resetModal();

            // Display the dialog box.
            $modal.addClass('active');

            $('body').addClass('has-fs-modal');
        }

        function closeModal() {
            var activeModalsCount = $( '.fs-modal.active' ).length;

            $modal.removeClass('active');

            // If child modal, do not remove the "has-fs-modal" class of the <body> element to keep its scrollbars hidden.
            if ( activeModalsCount > 1 ) {
                return;
            }

            $('body').removeClass('has-fs-modal');
        }

        function resetModal() {
            updateButtonLabels();

            if ( 0 === $modal.find( '.subscription-actions' ).length ) {
                $modal.find('.button-deactivate').removeClass('disabled');
            } else {
                $modal.find('.button-deactivate').addClass('disabled');
            }

            $modal.find('.fs-price-increase-warning').hide();

            // Uncheck all radio buttons.
            $modal.find('input[type="radio"]').prop('checked', false);

            $modal.find('.message').hide();
        }

        function showMessage(message) {
            $modal.find('.message').text(message).show();
        }

        function updateButtonLabels() {
            $modal.find('.button-primary').text( <?php fs_json_encode_echo_inline( 'Proceed', 'proceed', $slug ) ?> );

            $modal.find('.button-secondary').text( <?php fs_json_encode_echo_inline( 'Cancel', 'cancel', $slug ) ?> );
        }
    })( jQuery );
</script>