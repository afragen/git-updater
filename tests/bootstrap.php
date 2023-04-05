<?php

/**
 * PHPUnit bootstrap file.
 *
 * @package Git_Updater
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
	var_export( $_tests_dir, true );
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	var_export( '/wp-includes/functions.php exists.', true );
} else {
	var_export( '/wp-includes/functions.php not exists.', true );
}
if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	echo dirname( dirname( __FILE__ ) ) . '/git-updater.php';
	require dirname( dirname( __FILE__ ) ) . '/git-updater.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
if ( file_exists( "{$_tests_dir}/includes/bootstrap.php" ) ) {
	var_export( '/includes/bootstap.php exists.', true );
} else {
	var_export( '/includes/bootstrap.php not exists.', true );
}
require "{$_tests_dir}/includes/bootstrap.php";
