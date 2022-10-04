<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater\WP_CLI;

use WP_CLI;
use WP_CLI_Command;
use Fragen\Singleton;

// Add WP-CLI commands.
$cli = new CLI();
WP_CLI::add_command( 'git-updater', get_class( $cli ) );

/**
 * Manage Git Updater PRO commands.
 *
 * Class Git_Updater_CLI
 */
class CLI extends WP_CLI_Command {
	/**
	 * Clear Git Updater cache.
	 *
	 * ## OPTIONS
	 *
	 * <delete>
	 * : delete the cache
	 *
	 * ## EXAMPLES
	 *
	 *     wp git-updater cache delete
	 *
	 * @param array $args Array of arguments.
	 *
	 * @subcommand cache
	 */
	public function cache( $args ) {
		list($action) = $args;
		if ( 'delete' === $action ) {
			Singleton::get_instance( 'CLI_Common', $this )->delete_all_cached_data();
			WP_CLI::success( 'Git Updater cache has been cleared.' );
		} else {
			WP_CLI::error( sprintf( 'Incorrect command syntax, see %s for proper syntax.', '`wp help git-updater cache`' ) );
		}
		WP_CLI::success( 'WP-Cron is now running.' );
		WP_CLI::runcommand( 'cron event run --due-now' );
	}

	/**
	 * Reset Git Updater REST API key.
	 *
	 * ## EXAMPLES
	 *
	 *     wp git-updater reset-api-key
	 *
	 * @subcommand reset-api-key
	 */
	public function reset_api_key() {
		delete_site_option( 'git_updater_api_key' );
		Singleton::get_instance( 'Fragen\Git_Updater\Remote_Management', $this )->ensure_api_key_is_set();
		$namespace = Singleton::get_instance( 'Fragen\Git_Updater\Base', $this )->get_class_vars( 'Fragen\Git_Updater\REST\REST_API', 'namespace' );
		$api_key   = get_site_option( 'git_updater_api_key' );
		$api_url   = add_query_arg(
			[ 'key' => $api_key ],
			\home_url( "wp-json/$namespace/update/" )
		);

		WP_CLI::success( 'Git Updater REST API key has been reset.' );
		WP_CLI::success( sprintf( 'The new REST API key is: `%s`', $api_key ) );
		WP_CLI::success( sprintf( 'The current REST API endpoint for updating is `%s`', $api_url ) );
	}
}
