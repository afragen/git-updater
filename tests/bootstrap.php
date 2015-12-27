<?php
/**
 * Bootstrap the testing environment
 * Uses wordpress tests (http://github.com/nb/wordpress-tests/) which uses PHPUnit
 * @package wordpress-plugin-tests
 *
 * Usage: change the below array to any plugin(s) you want activated during the tests
 *        value should be the path to the plugin relative to /wp-content/
 *
 * Note: Do note change the name of this file. PHPUnit will automatically fire this file when run.
 *
 */

require_once ( dirname( __FILE__ ) . '/lib/testcase.php' );
global $wpdb, $current_site, $current_blog, $wp_rewrite, $shortcode_tags, $wp;

$GLOBALS['wp_tests_options'] = array(
	'active_plugins' => array( 'github-updater/github-updater.php' ),
);

require_once dirname( __FILE__ ) . '/bootstrap.php';
