<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

use Fragen\GitHub_Updater\Base,
	Fragen\GitHub_Updater\Plugin,
	Fragen\GitHub_Updater\Theme,
	Fragen\GitHub_Updater\Install;


/**
 * Manage GitHub Updater repositories and commands.
 *
 * Class GitHub_Updater_CLI
 */
class GitHub_Updater_CLI extends WP_CLI_Command {

	/**
	 * Update plugin update transient for GitHub Updater repositories.
	 *
	 * After running your are able to use any of the standard
	 * `wp plugin` commands with GitHub Updater repositories.
	 *
	 * ## EXAMPLES
	 *     wp plugin github-updater-init
	 *
	 * @subcommand github-updater-init
	 */
	public function init_plugins() {
		$base = new Base();
		$base->forced_meta_update_plugins( true );
		$current = get_site_transient( 'update_plugins' );
		$current = Plugin::instance()->pre_set_site_transient_update_plugins( $current );
		set_site_transient( 'update_plugins', $current );

		WP_CLI::success( sprintf( esc_html__( 'Now use %s commands with GitHub Updater repositories.', 'github-updater' ), '`wp plugin`' ) );
	}

	/**
	 * Update theme update transient for GitHub Updater repositories.
	 *
	 * After running your are able to use any of the standard
	 * `wp theme` commands with GitHub Updater repositories.
	 *
	 * ## EXAMPLES
	 *     wp theme github-updater-init
	 *
	 * @subcommand github-updater-init
	 */
	public function init_themes() {
		$base = new Base();
		$base->forced_meta_update_themes( true );
		$current = get_site_transient( 'update_themes' );
		$current = Theme::instance()->pre_set_site_transient_update_themes( $current );
		set_site_transient( 'update_themes', $current );

		WP_CLI::success( sprintf( esc_html__( 'Now use %s commands with GitHub Updater repositories.', 'github-updater' ), '`wp theme`' ) );
	}

	/**
	 * Install plugin using GitHub Updater.
	 *
	 * ## OPTIONS
	 *
	 * <uri>
	 * : URI to the repo being installed
	 *
	 * [--field=<access_token>]
	 * : GitHub or GitLab access token if not already saved
	 *
	 * [--bitbucket_private=<private>]
	 * : Boolean indicating private Bitbucket repository
	 * ---
	 * default: false
	 * options:
	 *   - true
	 *   - false
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp plugin github-updater-install https://github.com/afragen/test-plugin
	 *
	 *     wp plugin github-updater-install https://bitbucket.org/afragen/my-private-repo --bitbucket_private=true
	 *
	 *     wp plugin github-updater-install https://github.com/afragen/my-private-repo --field=lks9823evalki
	 *
	 * @param array $args       An array of $uri
	 * @param array $assoc_args Array of optional arguments.
	 *
	 * @subcommand github-updater-install
	 */
	public function install_plugin( $args, $assoc_args ) {
		list( $uri ) = $args;
		$private = isset( $assoc_args['field'] ) ? $assoc_args['field'] : $assoc_args['bitbucket_private'];
		$headers = parse_url( $uri, PHP_URL_PATH );
		$slug    = basename( $headers );
		new Install( 'plugin', $uri, $private );

		WP_CLI::success( sprintf( esc_html__( 'Plugin %s installed.', 'github-updater' ), "'$slug'" ) );
	}

	/**
	 * Install theme using GitHub Updater.
	 *
	 * ## OPTIONS
	 *
	 * <uri>
	 * : URI to the repo being installed
	 *
	 * [--field=<access_token>]
	 * : GitHub or GitLab access token if not already saved
	 *
	 * [--bitbucket_private=<private>]
	 * : Boolean indicating private Bitbucket repository
	 * ---
	 * default: false
	 * options:
	 *   - true
	 *   - false
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp theme github-updater-install https://bitbucket.org/afragen/test-bitbucket-child
	 *
	 *     wp theme github-updater-install https://bitbucket.org/afragen/my-private-repo --bitbucket_private=true
	 *
	 *     wp theme github-updater-install https://github.com/afragen/my-private-repo --field=lks9823evalki
	 *
	 * @param array $args       An array of $uri
	 * @param array $assoc_args Array of optional arguments.
	 *
	 * @subcommand github-updater-install
	 */
	public function install_theme( $args, $assoc_args ) {
		list( $uri ) = $args;
		$private = isset( $assoc_args['field'] ) ? $assoc_args['field'] : $assoc_args['bitbucket_private'];
		$headers = parse_url( $uri, PHP_URL_PATH );
		$slug    = basename( $headers );
		new Install( 'theme', $uri, $private );

		WP_CLI::success( sprintf( esc_html__( 'Theme %s installed.', 'github-updater' ), "'$slug'" ) );
	}

}

$ghu_cli = new GitHub_Updater_CLI();
WP_CLI::add_command( 'plugin github-updater-init', array( $ghu_cli, 'init_plugins' ) );
WP_CLI::add_command( 'theme github-updater-init', array( $ghu_cli, 'init_themes' ) );
WP_CLI::add_command( 'plugin github-updater-install', array( $ghu_cli, 'install_plugin' ) );
WP_CLI::add_command( 'theme github-updater-install', array( $ghu_cli, 'install_theme' ) );

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

/**
 * Class GitHub_Upgrader_CLI_Plugin_Installer_Skin
 */
class GitHub_Upgrader_CLI_Plugin_Installer_Skin extends \Plugin_Installer_Skin {
	public function header() {}
	public function footer() {}
	public function error( $errors ) {
		if ( is_wp_error( $errors ) ) {
			WP_CLI::error( $errors->get_error_message() . "\n" . $errors->get_error_data() );
		};
	}
	public function feedback( $string ) {}
}

/**
 * Class GitHub_Upgrader_CLI_Theme_Installer_Skin
 */
class GitHub_Upgrader_CLI_Theme_Installer_Skin extends \Theme_Installer_Skin {
	public function header() {}
	public function footer() {}
	public function error( $errors ) {
		if ( is_wp_error( $errors ) ) {
			WP_CLI::error( $errors->get_error_message() . "\n" . $errors->get_error_data() );
		};
	}
	public function feedback( $string ) {}
}
