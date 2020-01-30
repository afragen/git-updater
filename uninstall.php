<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link    https://github.com/afragen/github-updater
 * @package github-updater
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = [ 'github_updater', 'github_updater_api_key', 'github_updater_remote_management' ];
foreach ( $options as $option ) {
	delete_option( $option );
	delete_site_option( $option );
}

global $wpdb;
$table         = is_multisite() ? $wpdb->base_prefix . 'sitemeta' : $wpdb->base_prefix . 'options';
$column        = is_multisite() ? 'meta_key' : 'option_name';
$delete_string = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s LIMIT 1000';
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( $wpdb->prepare( $delete_string, [ '%ghu-%' ] ) );

@unlink( WP_CONTENT_DIR . '/tmp-readme.txt' );
