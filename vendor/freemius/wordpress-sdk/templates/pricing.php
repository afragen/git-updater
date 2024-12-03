<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.0.3
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'json2' );
	fs_enqueue_local_script( 'postmessage', 'nojquery.ba-postmessage.js' );
	fs_enqueue_local_script( 'fs-postmessage', 'postmessage.js' );
	fs_enqueue_local_style( 'fs_common', '/admin/common.css' );

	/**
	 * @var array    $VARS
	 * @var Freemius $fs
	 */
	$fs        = freemius( $VARS['id'] );
	$slug 	   = $fs->get_slug();
	$timestamp = time();

	$context_params = array(
		'plugin_id'         => $fs->get_id(),
		'plugin_public_key' => $fs->get_public_key(),
		'plugin_version'    => $fs->get_plugin_version(),
	);

	$bundle_id = $fs->get_bundle_id();
	if ( ! is_null( $bundle_id ) ) {
	    $context_params['bundle_id'] = $bundle_id;
    }

	// Get site context secure params.
	if ( $fs->is_registered() ) {
		$context_params = array_merge( $context_params, FS_Security::instance()->get_context_params(
			$fs->get_site(),
			$timestamp,
			'upgrade'
		) );
	} else {
		$context_params['home_url'] = home_url();
	}

	if ( $fs->is_payments_sandbox() ) // Append plugin secure token for sandbox mode authentication.)
	{
		$context_params['sandbox'] = FS_Security::instance()->get_secure_token(
			$fs->get_plugin(),
			$timestamp,
			'checkout'
		);
	}

	$query_params = array_merge( $context_params, $_GET, array(
		'next'             => $fs->_get_sync_license_url( false, false ),
		'plugin_version'   => $fs->get_plugin_version(),
		// Billing cycle.
		'billing_cycle'    => fs_request_get( 'billing_cycle', WP_FS__PERIOD_ANNUALLY ),
		'is_network_admin' => fs_is_network_admin() ? 'true' : 'false',
        'currency'         => $fs->apply_filters( 'default_currency', 'usd' ),
        'discounts_model'  => $fs->apply_filters( 'pricing/discounts_model', 'absolute' ),
	) );

    $pricing_js_url = fs_asset_url( $fs->get_pricing_js_path() );

    wp_enqueue_script( 'freemius-pricing', $pricing_js_url );

    $pricing_css_path = $fs->apply_filters( 'pricing/css_path', null );
    if ( is_string( $pricing_css_path ) ) {
        wp_enqueue_style( 'freemius-pricing', fs_asset_url( $pricing_css_path ) );
    }

	$has_tabs = $fs->_add_tabs_before_content();

	if ( $has_tabs ) {
		$query_params['tabs'] = 'true';
	}
?>
	<div id="fs_pricing" class="wrap fs-section fs-full-size-wrapper">
        <div id="fs_pricing_wrapper" data-public-url="<?php echo trailingslashit( dirname( $pricing_js_url ) ) ?>"></div>
        <?php
        $pricing_config = array_merge( array(
            'contact_url'            => $fs->contact_url(),
            'is_production'          => ( defined( 'WP_FS__IS_PRODUCTION_MODE' ) ? WP_FS__IS_PRODUCTION_MODE : null ),
            'menu_slug'              => $fs->get_menu_slug(),
            'mode'                   => 'dashboard',
            'fs_wp_endpoint_url'     => WP_FS__ADDRESS,
            'request_handler_url'    => admin_url(
                'admin-ajax.php?' . http_build_query( array(
                    'module_id' => $fs->get_id(),
                    'action'    => $fs->get_ajax_action( 'pricing_ajax_action' ),
                    'security'  => $fs->get_ajax_security( 'pricing_ajax_action' )
                ) )
            ),
            'selector'               => '#fs_pricing_wrapper',
            'unique_affix'           => $fs->get_unique_affix(),
            'show_annual_in_monthly' => $fs->apply_filters( 'pricing/show_annual_in_monthly', true ),
            'license'                => $fs->has_active_valid_license() ? $fs->_get_license() : null,
            'plugin_icon'            => $fs->get_local_icon_url(),
            'disable_single_package' => $fs->apply_filters( 'pricing/disable_single_package', false ),
        ), $query_params );

        wp_add_inline_script( 'freemius-pricing', 'Freemius.pricing.new( ' . json_encode( $pricing_config ) . ' )' );
        ?>
	</div>
<?php
	if ( $has_tabs ) {
		$fs->_add_tabs_after_content();
	}
