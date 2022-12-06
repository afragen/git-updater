<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2015, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       1.2.3
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * @var array    $VARS
     * @var Freemius $fs
     * @var string   $plugin_title
     */
    $fs           = freemius( $VARS['id'] );
    $plugin_title = $VARS['plugin_title'];

    $slug = $fs->get_slug();

    $user            = $fs->get_user();
    $affiliate       = $fs->get_affiliate();
    $affiliate_terms = $fs->get_affiliate_terms();

    $module_type  = $fs->is_plugin() ?
        WP_FS__MODULE_TYPE_PLUGIN :
        WP_FS__MODULE_TYPE_THEME;

    $commission = $affiliate_terms->get_formatted_commission();

    $readonly                      = false;
    $is_affiliate                  = is_object( $affiliate );
    $is_pending_affiliate          = false;
    $email_address                 = ( is_object( $user ) ?
        $user->email :
        '' );
    $full_name                     = ( is_object( $user ) ?
        $user->get_name() :
        '' );
    $paypal_email_address          = '';
    $domain                        = '';
    $extra_domains                 = array();
    $promotion_method_social_media = false;
    $promotion_method_mobile_apps  = false;
    $statistics_information        = false;
    $promotion_method_description  = false;
    $members_dashboard_login_url   = 'https://users.freemius.com/login';

    $affiliate_application_data = $fs->get_affiliate_application_data();

    if ( $is_affiliate && $affiliate->is_pending() ) {
        $readonly             = 'readonly';
        $is_pending_affiliate = true;

        $paypal_email_address         = $affiliate->paypal_email;
        $domain                       = $affiliate->domain;
        $statistics_information       = $affiliate_application_data['stats_description'];
        $promotion_method_description = $affiliate_application_data['promotion_method_description'];

        if ( ! empty( $affiliate_application_data['additional_domains'] ) ) {
            $extra_domains = $affiliate_application_data['additional_domains'];
        }

        if ( ! empty( $affiliate_application_data['promotion_methods'] ) ) {
            $promotion_methods             = explode( ',', $affiliate_application_data['promotion_methods'] );
            $promotion_method_social_media = in_array( 'social_media', $promotion_methods );
            $promotion_method_mobile_apps  = in_array( 'mobile_apps', $promotion_methods );
        }
    } else {
        $current_user  = Freemius::_get_current_wp_user();
        $full_name     = trim( $current_user->user_firstname . ' ' . $current_user->user_lastname );
        $email_address = $current_user->user_email;
        $domain        = Freemius::get_unfiltered_site_url( null, true );
    }

    $affiliate_tracking = 30;

    if ( is_object( $affiliate_terms ) ) {
        $affiliate_tracking = ( ! is_null( $affiliate_terms->cookie_days ) ?
            ( $affiliate_terms->cookie_days . '-day' ) :
            fs_text_inline( 'Non-expiring', 'non-expiring', $slug ) );
    }

    $apply_to_become_affiliate_text = fs_text_inline( 'Apply to become an affiliate', 'apply-to-become-an-affiliate', $slug );

    $module_id                   = $fs->get_id();
    $affiliate_program_terms_url = "https://freemius.com/plugin/{$module_id}/{$slug}/legal/affiliate-program/";
?>
<div id="fs_affiliation_content_wrapper" class="wrap">
    <form method="post" action="">
        <div id="poststuff">
            <div class="postbox">
                <div class="inside">
                    <div id="messages">
                        <div id="error_message" class="error" style="display: none">
                            <p><strong></strong></p>
                        </div>
                        <div id="message" class="updated" style="display: none">
                            <p><strong></strong></p>
                        </div>
                        <?php if ( $is_affiliate ) : ?>
                            <?php if ( $affiliate->is_active() ) : ?>
                                <div class="updated">
                                    <p><strong><?php
                                        echo sprintf(
                                            fs_esc_html_inline( "Your affiliate application for %s has been accepted! Log in to your affiliate area at: %s.", 'affiliate-application-accepted', $slug ),
                                            $plugin_title,
                                            sprintf(
                                                '<a href="%s" target="_blank" rel="noopener">%s</a>',
                                                $members_dashboard_login_url,
                                                $members_dashboard_login_url
                                            )
                                        );
                                    ?></strong></p>
                                </div>
                            <?php else : ?>
                                    <?php
                                    $message_text = '';

                                    if ( $is_pending_affiliate ) {
                                        $message_text            = fs_text_inline( "Thank you for applying for our affiliate program, we'll review your details during the next 14 days and will get back to you with further information.", 'affiliate-application-thank-you', $slug );
                                        $message_container_class = 'updated';
                                    } else if ( $affiliate->is_suspended() ) {
                                        $message_text            = fs_text_inline( 'Your affiliation account was temporarily suspended.', 'affiliate-account-suspended', $slug );
                                        $message_container_class = 'notice notice-warning';
                                    } else if ( $affiliate->is_rejected() ) {
                                        $message_text            = fs_text_inline( "Thank you for applying for our affiliate program, unfortunately, we've decided at this point to reject your application. Please try again in 30 days.", 'affiliate-application-rejected', $slug );
                                        $message_container_class = 'error';
                                    } else if ( $affiliate->is_blocked() ) {
                                        $message_text            = fs_text_inline( 'Due to violation of our affiliation terms, we decided to temporarily block your affiliation account. If you have any questions, please contact support.', 'affiliate-account-blocked', $slug );
                                        $message_container_class = 'error';
                                    }
                                    ?>
                                    <div class="<?php echo $message_container_class ?>">
                                        <p><strong><?php echo esc_html( $message_text ) ?></strong></p>
                                    </div>
                                <?php endif ?>
                            <?php endif ?>
                        </div>
                        <div class="entry-content">
                            <?php if ( ! $is_affiliate ) : ?>
                                <div id="application_messages_container">
                                    <p><?php echo esc_html( sprintf( fs_text_inline( 'Like the %s? Become our ambassador and earn cash ;-)', 'become-an-ambassador', $slug ), $module_type ) ) ?></p>
                                    <p><?php echo esc_html( sprintf( fs_text_inline( 'Refer new customers to our %s and earn %s commission on each successful sale you refer!', 'refer-new-customers', $slug ), $module_type, $commission ) ) ?></p>
                                </div>
                            <?php endif ?>
                            <h3><?php fs_esc_html_echo_inline( 'Program Summary', 'program-summary', $slug ) ?></h3>
                            <ul>
                                <li><?php echo esc_html( sprintf( fs_text_inline( '%s commission when a customer purchases a new license.', 'commission-on-new-license-purchase', $slug ), $commission ) ) ?></li>
                                <?php if ( is_object( $affiliate_terms ) && $affiliate_terms->has_renewals_commission() ) : ?>
                                    <li><?php echo esc_html( sprintf( fs_text_inline( 'Get commission for automated subscription renewals.', 'renewals-commission', $slug ) ) ) ?></li>
                                <?php endif ?>
                                <?php if ( is_object( $affiliate_terms ) && ( ! $affiliate_terms->is_session_cookie() ) ) : ?>
                                    <li><?php echo esc_html( sprintf( fs_text_inline( '%s tracking cookie after the first visit to maximize earnings potential.', 'affiliate-tracking', $slug ), $affiliate_tracking ) ) ?></li>
                                <?php endif ?>
                                <?php if ( is_object( $affiliate_terms ) && $affiliate_terms->has_lifetime_commission() ) : ?>
                                    <li><?php fs_esc_html_echo_inline( 'Unlimited commissions.', 'unlimited-commissions', $slug ) ?></li>
                                <?php endif ?>
                                <li><?php echo esc_html( sprintf( fs_text_inline( '%s minimum payout amount.', 'minimum-payout-amount', $slug ), '$100' ) ) ?></li>
                                <li><?php fs_esc_html_echo_inline( 'Payouts are in USD and processed monthly via PayPal.', 'payouts-unit-and-processing', $slug ) ?></li>
                                <li><?php fs_esc_html_echo_inline( 'As we reserve 30 days for potential refunds, we only pay commissions that are older than 30 days.', 'commission-payment', $slug ) ?></li>
                            </ul>
                            <div id="application_form_container" <?php echo ( $is_pending_affiliate ) ? '' : 'style="display: none"' ?>>
                                <h3><?php fs_esc_html_echo_inline( 'Affiliate', 'affiliate', $slug ) ?></h3>
                                <form>
                                    <div class="input-container input-container-text">
                                        <label class="input-label"><?php fs_esc_html_echo_inline( 'Email address', 'email-address', $slug ) ?></label>
                                        <input id="email_address" type="text" value="<?php echo esc_attr( $email_address ) ?>" class="regular-text" <?php echo ( $readonly || is_object( $user ) ) ? 'readonly' : '' ?>>
                                    </div>
                                    <div class="input-container input-container-text">
                                        <label class="input-label"><?php fs_esc_html_echo_inline( 'Full name', 'full-name', $slug ) ?></label>
                                        <input id="full_name" type="text" value="<?php echo esc_attr( $full_name ) ?>" class="regular-text" <?php echo $readonly ?>>
                                    </div>
                                    <div class="input-container input-container-text">
                                        <label class="input-label"><?php fs_esc_html_echo_inline( 'PayPal account email address', 'paypal-account-email-address', $slug ) ?></label>
                                        <input id="paypal_email" type="text" value="<?php echo esc_attr( $paypal_email_address ) ?>" class="regular-text" <?php echo $readonly ?>>
                                    </div>
                                    <div class="input-container input-container-text">
                                        <label class="input-label"><?php echo esc_html( sprintf( fs_text_inline( 'Where are you going to promote the %s?', 'domain-field-label', $slug ), $module_type ) ) ?></label>
                                        <input id="domain" type="text" value="<?php echo esc_attr( $domain ) ?>" class="domain regular-text" <?php echo $readonly ?>>
                                        <p class="description"><?php echo esc_html( sprintf( fs_text_inline( 'Enter the domain of your website or other websites from where you plan to promote the %s.', 'domain-field-desc', $slug ), $module_type ) ) ?></p>
                                        <?php if ( ! $is_affiliate ) : ?>
                                        <a id="add_domain" href="#" class="disabled">+ <?php fs_esc_html_echo_inline( 'Add another domain', 'add-another-domain', $slug ) ?>...</a>
                                        <?php endif ?>
                                    </div>
                                    <div id="extra_domains_container" class="input-container input-container-text" <?php echo $is_pending_affiliate ? '' : 'style="display: none"' ?>>
                                        <label class="input-label"><?php fs_esc_html_echo_inline( 'Extra Domains', 'extra-domain-fields-label', $slug ) ?></label>
                                        <p class="description"><?php fs_esc_html_echo_inline( 'Extra domains where you will be marketing the product from.', 'extra-domain-fields-desc', $slug ) ?></p>
                                        <?php if ( $is_pending_affiliate && ! empty( $extra_domains ) ) : ?>
                                            <?php foreach ( $extra_domains as $extra_domain ) : ?>
                                                <div class="extra-domain-input-container">
                                                    <input type="text" value="<?php echo esc_attr( $extra_domain ) ?>" class="domain regular-text" <?php echo $readonly ?>>
                                                </div>
                                            <?php endforeach ?>
                                        <?php endif ?>
                                    </div>
                                    <div class="input-container">
                                        <label class="input-label"><?php fs_esc_html_echo_inline( 'Promotion methods', 'promotion-methods', $slug ) ?></label>
                                        <div>
                                            <input id="promotion_method_social_media" type="checkbox" <?php checked( $promotion_method_social_media ) ?> <?php disabled( $is_affiliate ) ?>/>
                                            <label for="promotion_method_social_media"><?php fs_esc_html_echo_inline( 'Social media (Facebook, Twitter, etc.)', 'social-media', $slug ) ?></label>
                                        </div>
                                        <div>
                                            <input id="promotion_method_mobile_apps" type="checkbox" <?php checked( $promotion_method_mobile_apps ) ?> <?php disabled( $is_affiliate ) ?>/>
                                            <label for="promotion_method_mobile_apps"><?php fs_esc_html_echo_inline( 'Mobile apps', 'mobile-apps', $slug ) ?></label>
                                        </div>
                                    </div>
                                    <div class="input-container input-container-text">
                                    <label class="input-label"><nobr><?php fs_esc_html_echo_inline( 'Website, email, and social media statistics (optional)', 'statistics-information-field-label', $slug ) ?></nobr></label>
                                        <textarea id="statistics_information" rows="5" <?php echo $readonly ?> class="regular-text"><?php echo $statistics_information ?></textarea>
                                        <?php if ( ! $is_affiliate ) : ?>
                                            <p class="description"><?php fs_esc_html_echo_inline( 'Please feel free to provide any relevant website or social media statistics, e.g. monthly unique site visits, number of email subscribers, followers, etc. (we will keep this information confidential).', 'statistics-information-field-desc', $slug ) ?></p>
                                        <?php endif ?>
                                    </div>
                                    <div class="input-container input-container-text">
                                        <label class="input-label"><?php fs_esc_html_echo_inline( 'How will you promote us?', 'promotion-method-desc-field-label', $slug ) ?></label>
                                        <textarea id="promotion_method_description" rows="5" <?php echo $readonly ?> class="regular-text"><?php echo $promotion_method_description ?></textarea>
                                        <?php if ( ! $is_affiliate ) : ?>
                                            <p class="description"><?php echo esc_html( sprintf( fs_text_inline( 'Please provide details on how you intend to promote %s (please be as specific as possible).', 'promotion-method-desc-field-desc', $slug ), $plugin_title ) ) ?></p>
                                        <?php endif ?>
                                    </div>
                                    <?php if ( ! $is_affiliate ) : ?>
                                    <div>
                                        <input type="checkbox" id="legal_consent_checkbox">
                                        <label for="legal_consent_checkbox">I agree to the <a href="<?php echo $affiliate_program_terms_url ?>" target="_blank" rel="noopener">Referrer Program</a>'s terms & conditions.</label>
                                    </div>
                                    <?php endif ?>
                                </form>
                            </div>
                            <?php if ( ! $is_affiliate ) : ?>
                                <a id="cancel_button" href="#" class="button button-secondary button-cancel" style="display: none"><?php fs_esc_html_echo_inline( 'Cancel', 'cancel', $slug ) ?></a>
                                <a id="submit_button" class="button button-primary disabled" href="#" style="display: none"><?php echo esc_html( $apply_to_become_affiliate_text ) ?></a>
                                <a id="apply_button" class="button button-primary" href="#"><?php fs_esc_html_echo_inline( 'Become an affiliate', 'become-an-affiliate', $slug ) ?></a>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <script type="text/javascript">
            jQuery(function ($) {
                var
                    $contentWrapper           = $('#fs_affiliation_content_wrapper'),
                    $socialMedia              = $('#promotion_method_social_media'),
                    $mobileApps               = $('#promotion_method_mobile_apps'),
                    $applyButton              = $('#apply_button'),
                    $submitButton             = $('#submit_button'),
                    $cancelButton             = $('#cancel_button'),
                    $applicationFormContainer = $('#application_form_container'),
                    $errorMessageContainer    = $('#error_message'),
                    $domain                   = $('#domain'),
                    $addDomain                = $('#add_domain'),
                    $extraDomainsContainer    = $('#extra_domains_container'),
                    $legalConsentCheckbox     = $( '#legal_consent_checkbox' );

                $applyButton.click(function (evt) {
                    evt.preventDefault();

                    var $this = $(this);
                    $this.hide();

                    $applicationFormContainer.show();
                    $cancelButton.show();
                    $submitButton.show();

                    $contentWrapper.find('input[type="text"]:first').focus();
                });

                $submitButton.click(function (evt) {
                    evt.preventDefault();

                    var $this = $(this);

                    if ($this.hasClass('disabled')) {
                        return;
                    }

                    $errorMessageContainer.hide();

                    var
                        $emailAddress      = $('#email_address'),
                        emailAddress       = null,
                        paypalEmailAddress = $('#paypal_email').val().trim();

                    if (1 === $emailAddress.length) {
                        emailAddress = $emailAddress.val().trim();

                        if (0 === emailAddress.length) {
                            showErrorMessage('<?php fs_esc_js_echo_inline( 'Email address is required.', 'email-address-is-required', $slug ) ?>');
                            return;
                        }
                    }

                    if (0 === paypalEmailAddress.length) {
                        showErrorMessage('<?php fs_esc_js_echo_inline( 'PayPal email address is required.', 'paypal-email-address-is-required', $slug ) ?>');
                        return;
                    }

                    var
                        $extraDomains = $extraDomainsContainer.find('.domain'),
                        domain        = $domain.val().trim().toLowerCase(),
                        extraDomains  = [];

                    if (0 === domain.length) {
                        showErrorMessage('<?php fs_esc_js_echo_inline( 'Domain is required.', 'domain-is-required', $slug ) ?>');
                        return;
                    } else if ('freemius.com' === domain) {
                        showErrorMessage('<?php fs_esc_js_echo_inline( 'Invalid domain', 'invalid-domain', $slug ) ?>' + ' [' + domain + '].');
                        return;
                    }

                    if ($extraDomains.length > 0) {
                        var hasError = false;

                        $extraDomains.each(function () {
                            var
                                $this       = $(this),
                                extraDomain = $this.val().trim().toLowerCase();
                            if (0 === extraDomain.length || extraDomain === domain) {
                                return true;
                            } else if ('freemius.com' === extraDomain) {
                                showErrorMessage('<?php fs_esc_js_echo_inline( 'Invalid domain', 'invalid-domain', $slug ) ?>' + ' [' + extraDomain + '].');
                                hasError = true;
                                return false;
                            }

                            extraDomains.push(extraDomain);
                        });

                        if (hasError) {
                            return;
                        }
                    }

                    var
                        promotionMethods           = [],
                        statisticsInformation      = $('#statistics_information').val(),
                        promotionMethodDescription = $('#promotion_method_description').val();

                    if ($socialMedia.attr('checked')) {
                        promotionMethods.push('social_media');
                    }

                    if ($mobileApps.attr('checked')) {
                        promotionMethods.push('mobile_apps');
                    }

                    var affiliate = {
                        full_name                   : $('#full_name').val().trim(),
                        paypal_email                : paypalEmailAddress,
                        stats_description           : statisticsInformation,
                        promotion_method_description: promotionMethodDescription
                    };

                    if (null !== emailAddress) {
                        affiliate.email = emailAddress;
                    }

                    affiliate.domain = domain;
                    affiliate.additional_domains = extraDomains;

                    if (promotionMethods.length > 0) {
                        affiliate.promotion_methods = promotionMethods.join(',');
                    }

                    $.ajax({
                        url       : <?php echo Freemius::ajax_url() ?>,
                        method    : 'POST',
                        data      : {
                            action   : '<?php echo $fs->get_ajax_action( 'submit_affiliate_application' ) ?>',
                            security : '<?php echo $fs->get_ajax_security( 'submit_affiliate_application' ) ?>',
                            module_id: '<?php echo $module_id ?>',
                            affiliate: affiliate
                        },
                        beforeSend: function () {
                            $cancelButton.addClass('disabled');
                            $submitButton.addClass('disabled');
                            $submitButton.text('<?php fs_esc_js_echo_inline( 'Submitting', 'submitting' ) ?>...');
                        },
                        success   : function (result) {
                            if (result.success) {
                                location.reload();
                            } else {
                                if (result.error && result.error.length > 0) {
                                showErrorMessage(result.error);
                                }

                                $cancelButton.removeClass('disabled');
                                $submitButton.removeClass('disabled');
                                $submitButton.text('<?php echo esc_js( $apply_to_become_affiliate_text ) ?>')
                            }
                        }
                    });
                });

                $cancelButton.click(function (evt) {
                    evt.preventDefault();

                    var $this = $(this);

                    if ($this.hasClass('disabled')) {
                        return;
                    }

                    $applicationFormContainer.hide();
                    $this.hide();
                    $submitButton.hide();

                    $applyButton.show();

                    window.scrollTo(0, 0);
                });

                $domain.on('input propertychange', onDomainChange);

                $addDomain.click(function (evt) {
                    evt.preventDefault();

                    var
                        $this  = $(this),
                        domain = $domain.val().trim();

                    if ($this.hasClass('disabled') || 0 === domain.length) {
                        return;
                    }

                    $domain.off('input propertychange');
                    $this.addClass('disabled');

                    var
                        $extraDomainInputContainer = $('<div class="extra-domain-input-container"><input type="text" class="domain regular-text"/></div>'),
                        $extraDomainInput          = $extraDomainInputContainer.find('input'),
                        $removeDomain              = $('<a href="#" class="remove-domain"><i class="dashicons dashicons-no" title="<?php fs_esc_js_echo_inline( 'Remove', 'remove', $slug ) ?>"></i></a>');

                    $extraDomainInputContainer.append($removeDomain);

                    $extraDomainInput.on('input propertychange', onDomainChange);

                    $removeDomain.click(function (evt) {
                        evt.preventDefault();

                        var
                            $extraDomainInputs = $('.extra-domain-input-container .domain');

                        if (1 === $extraDomainInputs.length)
                            $extraDomainInputs.val('').focus();
                        else
                            $(this).parent().remove();
                    });

                    $extraDomainsContainer.show();

                    $extraDomainInputContainer.appendTo($extraDomainsContainer);
                    $extraDomainInput.focus();

                    $this.appendTo($extraDomainsContainer);
                });

                /**
                 * @author Leo Fajardo (@leorw)
                 */
                function onDomainChange() {
                    var
                        domain = $(this).val().trim();

                    if (domain.length > 0) {
                        $addDomain.removeClass('disabled');
                    } else {
                        $addDomain.addClass('disabled');
                    }
                }

                /**
                 * @author Leo Fajardo (@leorw)
                 *
                 * @param {String} message
                 */
                function showErrorMessage(message) {
                    $errorMessageContainer.find('strong').text(message);
                    $errorMessageContainer.show();

                    window.scrollTo(0, 0);
                }

                /**
                 * @author Xiaheng Chen (@xhchen)
                 *
                 * @since 2.4.0
                 */
                $legalConsentCheckbox.click( function () {
                    if ( $( this ).prop( 'checked' ) ) {
                        $submitButton.removeClass( 'disabled' );
                    } else {
                        $submitButton.addClass( 'disabled' );
                    }
                } );
            });
        </script>
    </div>
<?php
    $params = array(
        'page'           => 'affiliation',
        'module_id'      => $module_id,
        'module_slug'    => $slug,
        'module_version' => $fs->get_plugin_version(),
    );
    fs_require_template( 'powered-by.php', $params );
