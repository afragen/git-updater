<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2015, Freemius, Inc.
     * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
     * @since       2.0.0
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * @var array             $VARS
     * @var Freemius          $fs
     * @var FS_Plugin_License $main_license
     */
    $fs                    = $VARS['freemius'];
    $slug                  = $fs->get_slug();
    $site                  = $VARS['site'];
    $main_license          = $VARS['license'];
    $is_data_debug_mode    = $fs->is_data_debug_mode();
    $is_whitelabeled       = $fs->is_whitelabeled();
    $has_paid_plan         = $fs->has_paid_plan();
    $is_premium            = $fs->is_premium();
    $main_user             = $VARS['user'];
    $blog_id               = $site['blog_id'];

    $install       = $VARS['install'];
    $is_registered = ! empty( $install );
    $license       = null;
    $trial_plan    = $fs->get_trial_plan();
    $free_text     = fs_text_inline( 'Free', 'free', $slug );

    if ( $is_whitelabeled && is_object( $install ) && $fs->is_delegated_connection( $blog_id ) ) {
        $is_whitelabeled = $fs->is_whitelabeled( true, $blog_id );
    }
?>
    <tr class="fs-site-details" data-blog-id="<?php echo $blog_id ?>"<?php if ( $is_registered ) : ?> data-install-id="<?php echo $install->id ?>"<?php endif ?>>
        <!-- Install ID or Opt-in option -->
        <td><?php if ( $is_registered ) : ?>
                <?php echo $install->id ?>
            <?php else : ?>
                <?php $action = 'opt_in' ?>
                <form action="<?php echo $fs->_get_admin_page_url( 'account' ) ?>" method="POST">
                    <input type="hidden" name="fs_action" value="<?php echo $action ?>">
                    <?php wp_nonce_field( trim( "{$action}:{$blog_id}", ':' ) ) ?>
                    <input type="hidden" name="blog_id" value="<?php echo $blog_id ?>">
                    <button class="fs-opt-in button button-small"><?php fs_esc_html_echo_inline( 'Opt In', 'opt-in', $slug ) ?></button>
                </form>
            <?php endif ?>
        </td>
        <!--/ Install ID or Opt-in option -->

        <!-- Site URL -->
        <td class="fs-field-url fs-main-column"><?php echo fs_strip_url_protocol( $site['url'] ) ?></td>
        <!--/ Site URL -->

        <!-- License Activation / Deactivation -->
        <td><?php if ( $has_paid_plan ) {
                $view_params = array(
                    'freemius' => $fs,
                    'slug'     => $slug,
                    'blog_id'  => $blog_id,
                    'class'    => 'button-small',
                );

                $license = null;
                if ( $is_registered ) {
                    $view_params['install_id']   = $install->id;
                    $view_params['is_localhost'] = $install->is_localhost();

                    $has_license = FS_Plugin_License::is_valid_id( $install->license_id );
                    $license     = $has_license ?
                        $fs->_get_license_by_id( $install->license_id ) :
                        null;
                } else {
                    $view_params['is_localhost'] = FS_Site::is_localhost_by_address( $site['url'] );
                }

                if ( ! $is_whitelabeled ) {
                    if ( is_object( $license ) ) {
                        $view_params['license'] = $license;

                        // Show license deactivation button.
                        fs_require_template( 'account/partials/deactivate-license-button.php', $view_params );
                    } else {
                        if ( is_object( $main_license ) && $main_license->can_activate( $view_params['is_localhost'] ) ) {
                            // Main license is available for activation.
                            $available_license = $main_license;
                        } else {
                            // Try to find any available license for activation.
                            $available_license = $fs->_get_available_premium_license( $view_params['is_localhost'] );
                        }

                        if ( is_object( $available_license ) ) {
                            $premium_plan = $fs->_get_plan_by_id( $available_license->plan_id );

                            $view_params['license'] = $available_license;
                            $view_params['class'] .= ' button-primary';
                            $view_params['plan'] = $premium_plan;

                            fs_require_template( 'account/partials/activate-license-button.php', $view_params );
                        }
                    }
                }
            } ?></td>
        <!--/ License Activation / Deactivation -->

        <!-- Plan -->
        <td><?php if ( $is_registered ) : ?>
                <?php
                if ( ! $has_paid_plan ) {
                    $plan_title = $free_text;
                } else {
                    if ( $install->is_trial() ) {
                        if ( is_object( $trial_plan ) && $trial_plan->id == $install->trial_plan_id ) {
                            $plan_title = is_string( $trial_plan->name ) ?
                                strtoupper( $trial_plan->title ) :
                                fs_text_inline( 'Trial', 'trial', $slug );
                        } else {
                            $plan_title = fs_text_inline( 'Trial', 'trial', $slug );
                        }
                    } else {
                        $plan       = $fs->_get_plan_by_id( $install->plan_id );
                        $plan_title = strtoupper( is_string( $plan->title ) ?
                            $plan->title :
                            strtoupper( $free_text )
                        );
                    }
                }
                ?>
                <code><?php echo $plan_title ?></code>
            <?php endif ?></td>
        <!--/ Plan -->

        <!-- More details button -->
        <td><?php if ( $is_registered ) : ?>
                <button class="fs-show-install-details button button-small">More details <i
                        class="dashicons dashicons-arrow-right-alt2"></i>
                </button><?php endif ?></td>
        <!--/ More details button -->
    </tr>
<?php if ( $is_registered ) : ?>
    <!-- More details -->
    <tr class="fs-install-details" data-install-id="<?php echo $install->id ?>" style="display: none">
        <td colspan="5">
            <table class="widefat fs-key-value-table">
                <tbody>
                <?php $row_index = 0 ?>
                <!-- Blog ID -->
                <tr <?php if ( 1 == $row_index % 2 ) {
                    echo ' class="alternate"';
                } ?>>
                    <td>
                        <nobr><?php fs_esc_html_echo_inline( 'Blog ID', 'blog-id', $slug ) ?>:</nobr>
                    </td>
                    <td><code><?php echo $blog_id ?></code></td>
                    <td><?php if ( ! FS_Plugin_License::is_valid_id( $install->license_id ) ) : ?>
                        <!-- Toggle Usage Tracking -->
                        <?php $action = 'toggle_tracking' ?>
                        <?php $is_disconnected = ! FS_Permission_Manager::instance( $fs )->is_homepage_url_tracking_allowed( $blog_id ) ?>
                        <form action="<?php echo $fs->_get_admin_page_url( 'account' ) ?>" method="POST">
                            <input type="hidden" name="fs_action" value="<?php echo $action ?>">
                            <?php wp_nonce_field( trim( "{$action}:{$blog_id}:{$install->id}", ':' ) ) ?>
                            <input type="hidden" name="install_id" value="<?php echo $install->id ?>">
                            <input type="hidden" name="blog_id" value="<?php echo $blog_id ?>">
                            <button class="fs-toggle-tracking button button-small<?php if ( $is_disconnected ) {
                                echo ' button-primary';
                            } ?>" data-is-disconnected="<?php echo $is_disconnected ? 'true' : 'false' ?>"><?php $is_disconnected ? fs_esc_html_echo_inline( 'Opt In', 'opt-in', $slug ) : fs_esc_html_echo_inline( 'Opt Out', 'opt-out', $slug ) ?></button>
                        </form>
                    <!--/ Toggle Usage Tracking -->
                    <?php endif ?></td>
                </tr>
                <?php $row_index ++ ?>
                <!--/ Blog ID -->

                <?php if ( $install->user_id != $main_user->id ) : ?>
                    <?php
                    /**
                     * @var FS_User $user
                     */
                    $user = Freemius::_get_user_by_id( $install->user_id ) ?>
                    <?php if ( is_object( $user ) ) : ?>
                        <!-- User Name -->
                        <tr <?php if ( 1 == $row_index % 2 ) {
                            echo ' class="alternate"';
                        } ?>>
                            <td>
                                <nobr><?php fs_esc_html_echo_inline( 'Owner Name', 'owner-name', $slug ) ?>:</nobr>
                            </td>
                            <td colspan="2"><code><?php echo htmlspecialchars( $user->get_name() ) ?></code></td>
                        </tr>
                        <?php $row_index ++ ?>
                        <!--/ User Name -->

                        <!-- User Email -->
                        <tr <?php if ( 1 == $row_index % 2 ) {
                            echo ' class="alternate"';
                        } ?>>
                            <td>
                                <nobr><?php fs_esc_html_echo_inline( 'Owner Email', 'owner-email', $slug ) ?>:</nobr>
                            </td>
                            <td colspan="2"><code><?php echo htmlspecialchars( $user->email ) ?></code></td>
                        </tr>
                        <?php $row_index ++ ?>
                        <!--/ User Email -->

                        <!-- User ID -->
                        <tr <?php if ( 1 == $row_index % 2 ) {
                            echo ' class="alternate"';
                        } ?>>
                            <td>
                                <nobr><?php fs_esc_html_echo_inline( 'Owner ID', 'owner-id', $slug ) ?>:</nobr>
                            </td>
                            <td colspan="2"><code><?php echo $user->id ?></code></td>
                        </tr>
                        <?php $row_index ++ ?>
                        <!--/ User ID -->
                    <?php endif ?>
                <?php endif ?>

                <!-- Public Key -->
                <tr <?php if ( 1 == $row_index % 2 ) {
                    echo ' class="alternate"';
                } ?>>
                    <td>
                        <nobr><?php fs_esc_html_echo_inline( 'Public Key', 'public-key', $slug ) ?>:</nobr>
                    </td>
                    <td><code><?php echo htmlspecialchars( $install->public_key ) ?></code></td>
                    <td></td>
                </tr>
                <?php $row_index ++ ?>
                <!--/ Public Key -->

                <!-- Secret Key -->
                <tr <?php if ( 1 == $row_index % 2 ) {
                    echo ' class="alternate"';
                } ?>>
                    <td>
                        <nobr><?php fs_esc_html_echo_inline( 'Secret Key', 'secret-key', $slug ) ?>:</nobr>
                    </td>
                    <td>
                        <code><?php echo FS_Plugin_License::mask_secret_key_for_html( $install->secret_key ) ?></code>
                        <?php if ( ! $is_whitelabeled ) : ?>
                        <input type="text" value="<?php echo htmlspecialchars( $install->secret_key ) ?>"
                               style="display: none" readonly/></td>
                        <?php endif ?>
                    <?php if ( ! $is_whitelabeled ) : ?>
                    <td><button class="button button-small fs-toggle-visibility"><?php fs_esc_html_echo_x_inline( 'Show', 'verb', 'show', $slug ) ?></button></td>
                    <?php endif ?>
                </tr>
                <?php $row_index ++ ?>
                <!--/ Secret Key -->

                <?php if ( is_object( $license ) ) : ?>
                    <!-- License Key -->
                    <tr <?php if ( 1 == $row_index % 2 ) {
                        echo ' class="alternate"';
                    } ?>>
                        <td>
                            <nobr><?php fs_esc_html_echo_inline( 'License Key', 'license-key', $slug ) ?>:</nobr>
                        </td>
                        <td>
                            <code><?php echo $license->get_html_escaped_masked_secret_key() ?></code>
                            <?php if ( ! $is_whitelabeled ) : ?>
                            <input type="text" value="<?php echo htmlspecialchars( $license->secret_key ) ?>"
                                   style="display: none" readonly/></td>
                            <?php endif ?>
                        <?php if ( ! $is_whitelabeled ) : ?>
                        <td>
                            <button class="button button-small fs-toggle-visibility"><?php fs_esc_html_echo_x_inline( 'Show', 'verb', 'show', $slug ) ?></button>
                            <button class="button button-small activate-license-trigger <?php echo $fs->get_unique_affix() ?>"><?php fs_esc_html_echo_inline( 'Change License', 'change-license', $slug ) ?></button>
                        </td>
                        <?php endif ?>
                    </tr>
                    <?php $row_index ++ ?>
                    <!--/ License Key -->

                    <?php if ( ! is_object( $main_license ) || $main_license->id != $license->id ) : ?>
                        <?php $subscription = $fs->_get_subscription( $license->id ) ?>
                        <?php if ( ! $license->is_lifetime() && is_object( $subscription ) ) : ?>
                            <!-- Subscription -->
                            <tr <?php if ( 1 == $row_index % 2 ) {
                                echo ' class="alternate"';
                            } ?>>
                                <td>
                                    <nobr><?php fs_esc_html_echo_inline( 'Subscription', 'subscription', $slug ) ?>:</nobr>
                                </td>
                                <?php
                                    $is_active_subscription = $subscription->is_active();

                                    $renews_in_text = fs_text_inline( 'Auto renews in %s', 'renews-in', $slug );
                                    /* translators: %s: Time period (e.g. Expires in "2 months") */
                                    $expires_in_text = fs_text_inline( 'Expires in %s', 'expires-in', $slug );
                                ?>
                                <td>
                                    <code><?php echo $subscription->id ?> - <?php
                                        echo ( 12 == $subscription->billing_cycle ?
                                            _fs_text_inline( 'Annual', 'annual', $slug ) :
                                            _fs_text_inline( 'Monthly', 'monthly', $slug )
                                        );
                                        ?>
                                    </code>
                                    <?php if ( ! $is_active_subscription && ! $license->is_first_payment_pending() ) : ?>
                                        <label class="fs-tag fs-warn"><?php echo esc_html( sprintf( $expires_in_text, human_time_diff( time(), strtotime( $license->expiration ) ) ) ) ?></label>
                                    <?php elseif ( $is_active_subscription && ! $subscription->is_first_payment_pending() ) : ?>
                                        <label class="fs-tag fs-success"><?php echo esc_html( sprintf( $renews_in_text, human_time_diff( time(), strtotime( $subscription->next_payment ) ) ) ) ?></label>
                                    <?php endif ?>
                                </td>
                                <td><?php if ( $is_active_subscription ) : ?>
                                <?php
                                        $downgrading_plan_text        = fs_text_inline( 'Downgrading your plan', 'downgrading-plan', $slug );
                                        $cancelling_subscription_text = fs_text_inline( 'Cancelling the subscription', 'cancelling-subscription', $slug );
                                        /* translators: %1$s: Either 'Downgrading your plan' or 'Cancelling the subscription' */
                                        $downgrade_x_confirm_text          = fs_text_inline( '%1$s will immediately stop all future recurring payments and your %2$s plan license will expire in %3$s.', 'downgrade-x-confirm', $slug );
                                        $prices_increase_text              = fs_text_inline( 'Please note that we will not be able to grandfather outdated pricing for renewals/new subscriptions after a cancellation. If you choose to renew the subscription manually in the future, after a price increase, which typically occurs once a year, you will be charged the updated price.', 'pricing-increase-warning', $slug );
                                        $after_downgrade_non_blocking_text = fs_text_inline( 'You can still enjoy all %s features but you will not have access to %s security & feature updates, nor support.', 'after-downgrade-non-blocking', $slug );
                                        $after_downgrade_blocking_text     = fs_text_inline( 'Once your license expires you can still use the Free version but you will NOT have access to the %s features.', 'after-downgrade-blocking', $slug );
                                        $downgrade_text                    = fs_text_x_inline( 'Downgrade', 'verb', 'downgrade', $slug );

                                    $human_readable_license_expiration = human_time_diff( time(), strtotime( $license->expiration ) );
                                    $downgrade_confirmation_message    = sprintf(
                                        $downgrade_x_confirm_text,
                                        ( $fs->is_only_premium() ? $cancelling_subscription_text : $downgrading_plan_text ),
                                        $plan->title,
                                        $human_readable_license_expiration
                                    );

                                    $after_downgrade_message = ! $license->is_block_features ?
                                        sprintf( $after_downgrade_non_blocking_text, $plan->title, $fs->get_module_label( true ) ) :
                                        sprintf( $after_downgrade_blocking_text, $plan->title );
                                ?>
                                    <?php $action = 'downgrade_account' ?>
                                    <form id="fs_downgrade" action="<?php echo $fs->_get_admin_page_url( 'account' ) ?>" method="POST">
                                        <input type="hidden" name="fs_action" value="<?php echo $action ?>">
                                        <?php wp_nonce_field( trim( "{$action}:{$blog_id}", ':' ) ) ?>
                                        <input type="hidden" name="blog_id" value="<?php echo $blog_id ?>">
                                        <button class="button button-small" onclick="if (confirm('<?php echo esc_attr( $downgrade_confirmation_message . ' ' . $after_downgrade_message . ' ' . $prices_increase_text ) ?>')) { this.parentNode.submit(); } else { return false; }"><?php echo $downgrade_text ?></button>
                                    </form>
                                <?php endif ?></td>
                            </tr>
                            <?php $row_index ++ ?>
                        <?php endif ?>
                        <!--/ Subscription -->
                    <?php endif ?>
                <?php endif ?>

                </tbody>
            </table>
        </td>
    </tr>
    <!--/ More details -->
<?php endif ?>