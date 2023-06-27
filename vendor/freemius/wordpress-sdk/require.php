<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2016, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.1.9
	 */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

	// Configuration should be loaded first.
	require_once dirname( __FILE__ ) . '/config.php';
	require_once WP_FS__DIR_INCLUDES . '/fs-core-functions.php';
	require_once WP_FS__DIR_INCLUDES . '/fs-html-escaping-functions.php';

	// Logger must be loaded before any other.
	require_once WP_FS__DIR_INCLUDES . '/class-fs-logger.php';
	require_once WP_FS__DIR_INCLUDES . '/debug/debug-bar-start.php';

//		require_once WP_FS__DIR_INCLUDES . '/managers/class-fs-abstract-manager.php';
	require_once WP_FS__DIR_INCLUDES . '/managers/class-fs-option-manager.php';
	require_once WP_FS__DIR_INCLUDES . '/managers/class-fs-gdpr-manager.php';
	require_once WP_FS__DIR_INCLUDES . '/managers/class-fs-clone-manager.php';
	require_once WP_FS__DIR_INCLUDES . '/managers/class-fs-permission-manager.php';
	require_once WP_FS__DIR_INCLUDES . '/managers/class-fs-cache-manager.php';
	require_once WP_FS__DIR_INCLUDES . '/managers/class-fs-admin-notice-manager.php';
	require_once WP_FS__DIR_INCLUDES . '/managers/class-fs-admin-menu-manager.php';
	require_once WP_FS__DIR_INCLUDES . '/managers/class-fs-key-value-storage.php';
	require_once WP_FS__DIR_INCLUDES . '/managers/class-fs-license-manager.php';
	require_once WP_FS__DIR_INCLUDES . '/managers/class-fs-plan-manager.php';
	require_once WP_FS__DIR_INCLUDES . '/managers/class-fs-plugin-manager.php';
	require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-entity.php';
	require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-scope-entity.php';
	require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-user.php';
	require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-site.php';
	require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-plugin.php';
	require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-affiliate.php';
	require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-affiliate-terms.php';
	require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-plugin-info.php';
	require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-plugin-tag.php';
	require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-plugin-plan.php';
	require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-pricing.php';
	require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-payment.php';
	require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-plugin-license.php';
	require_once WP_FS__DIR_INCLUDES . '/entities/class-fs-subscription.php';
	require_once WP_FS__DIR_INCLUDES . '/class-fs-api.php';
	require_once WP_FS__DIR_INCLUDES . '/class-fs-plugin-updater.php';
	require_once WP_FS__DIR_INCLUDES . '/class-fs-security.php';
    require_once WP_FS__DIR_INCLUDES . '/class-fs-options.php';
    require_once WP_FS__DIR_INCLUDES . '/class-fs-storage.php';
    require_once WP_FS__DIR_INCLUDES . '/class-fs-admin-notices.php';
	require_once WP_FS__DIR_INCLUDES . '/class-freemius-abstract.php';
	require_once WP_FS__DIR_INCLUDES . '/sdk/Exceptions/Exception.php';
	require_once WP_FS__DIR_INCLUDES . '/class-freemius.php';
