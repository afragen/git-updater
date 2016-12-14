<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

namespace Fragen\GitHub_Updater;

use WP_CLI,
	WP_CLI_Command;

// Add WP-CLI commands.
$class = new CLI_Integration();
WP_CLI::add_command( 'plugin github-updater-install', array( $class, 'install_plugin' ) );
WP_CLI::add_command( 'theme github-updater-install', array( $class, 'install_theme' ) );

/**
 * Manage GitHub Updater repository commands.
 *
 * Class GitHub_Updater_CLI_Integration
 */
class CLI_Integration extends WP_CLI_Command {

	/**
	 * @var \Fragen\GitHub_Updater\Base
	 */
	private $base;

	/**
	 * GitHub_Updater_CLI_Integration constructor.
	 */
	public function __construct() {
		$this->base = new Base();
		$this->init_plugins();
		$this->init_themes();
	}

	/**
	 * Update plugin update transient for GitHub Updater repositories.
	 *
	 * After running your are able to use any of the standard
	 * `wp plugin` commands with GitHub Updater repositories.
	 */
	public function init_plugins() {
		$this->base->forced_meta_update_plugins( true );
		$current = get_site_transient( 'update_plugins' );
		$current = Plugin::instance()->pre_set_site_transient_update_plugins( $current );
		set_site_transient( 'update_plugins', $current );
	}

	/**
	 * Update theme update transient for GitHub Updater repositories.
	 *
	 * After running your are able to use any of the standard
	 * `wp theme` commands with GitHub Updater repositories.
	 */
	public function init_themes() {
		$this->base->forced_meta_update_themes( true );
		$current = get_site_transient( 'update_themes' );
		$current = Theme::instance()->pre_set_site_transient_update_themes( $current );
		set_site_transient( 'update_themes', $current );
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
	 * [--bitbucket-private=<boolean>]
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
	 *     wp plugin github-updater-install https://bitbucket.org/afragen/my-private-repo --bitbucket-private=true
	 *
	 *     wp plugin github-updater-install https://github.com/afragen/my-private-repo --field=lks9823evalki
	 *
	 * @param array $args       An array of $uri
	 * @param array $assoc_args Array of optional arguments.
	 *
	 * @subcommand github-updater-install
	 */
	public function install_plugin( $args, $assoc_args ) {
		$cli_config = array();
		list( $uri ) = $args;
		$cli_config['uri'] = $uri;
		$cli_config['private'] = isset( $assoc_args['field'] )
			? $assoc_args['field']
			: $assoc_args['bitbucket-private'];
		$cli_config['branch'] = isset( $assoc_args['branch'])
			? $assoc_args['branch']
			: 'master';

		$headers = parse_url( $uri, PHP_URL_PATH );
		$slug    = basename( $headers );
		new Install( 'plugin', $cli_config );

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
	 * [--bitbucket-private=<boolean>]
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
	 *     wp theme github-updater-install https://bitbucket.org/afragen/my-private-repo --bitbucket-private=true
	 *
	 *     wp theme github-updater-install https://github.com/afragen/my-private-repo --field=lks9823evalki
	 *
	 * @param array $args       An array of $uri
	 * @param array $assoc_args Array of optional arguments.
	 *
	 * @subcommand github-updater-install
	 */
	public function install_theme( $args, $assoc_args ) {
		$cli_config = array();
		list( $uri ) = $args;
		$cli_config['uri'] = $uri;
		$cli_config['private'] = isset( $assoc_args['field'] )
			? $assoc_args['field']
			: $assoc_args['bitbucket-private'];
		$cli_config['branch'] = isset( $assoc_args['branch'])
			? $assoc_args['branch']
			: 'master';

		$headers = parse_url( $uri, PHP_URL_PATH );
		$slug    = basename( $headers );
		new Install( 'theme', $cli_config );

		WP_CLI::success( sprintf( esc_html__( 'Theme %s installed.', 'github-updater' ), "'$slug'" ) );
	}

}

/**
 * Use custom installer skins to display error messages.
 */
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skins.php';

/**
 * Class GitHub_Upgrader_CLI_Plugin_Installer_Skin
 */
class CLI_Plugin_Installer_Skin extends \Plugin_Installer_Skin {
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
class CLI_Theme_Installer_Skin extends \Theme_Installer_Skin {
	public function header() {}
	public function footer() {}
	public function error( $errors ) {
		if ( is_wp_error( $errors ) ) {
			WP_CLI::error( $errors->get_error_message() . "\n" . $errors->get_error_data() );
		};
	}
	public function feedback( $string ) {}
}
