<?php

/**
 * PHPUnit bootstrap file.
 *
 * @package Git_Updater
 */

ini_set( 'error_log', '/dev/null' );

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
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
	require dirname( dirname( __FILE__ ) ) . '/git-updater.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Define a writable, environment-independent WP_LANG_DIR inside the plugin tree
// BEFORE WordPress loads. WordPress defines WP_LANG_DIR itself during its
// bootstrap (wp-settings.php), which would otherwise win and point at a path
// that isn't reliably writable across environments (e.g. /tmp/wordpress in CI
// vs the mac-env container), breaking the language-pack fixture seeding in
// tests/test-language-pack.php. Defining it here makes our value take effect.
if ( ! defined( 'WP_LANG_DIR' ) ) {
	define( 'WP_LANG_DIR', dirname( __DIR__ ) . '/tests/fixtures/wp-languages' );
}

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

// Load base test case.
require_once __DIR__ . '/class-gu-test-case.php';
