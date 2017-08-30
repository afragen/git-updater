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
WP_CLI::add_command( 'plugin install-git', array( $class, 'install_plugin' ) );
WP_CLI::add_command( 'theme install-git', array( $class, 'install_theme' ) );

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
		$this->base = Singleton::get_instance( 'Base' );
		$this->run();
	}

	/**
	 * Off to the races.
	 */
	public function run() {
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
		$current = Singleton::get_instance( 'Plugin' )->pre_set_site_transient_update_plugins( $current );
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
		$current = Singleton::get_instance( 'Theme' )->pre_set_site_transient_update_themes( $current );
		set_site_transient( 'update_themes', $current );
	}

	/**
	 * Install plugin from GitHub, Bitbucket, or GitLab using GitHub Updater.
	 *
	 * ## OPTIONS
	 *
	 * <uri>
	 * : URI to the repo being installed
	 *
	 * [--branch=<branch_name>]
	 * : String indicating the branch name to be installed
	 * ---
	 * default: master
	 * ---
	 *
	 * [--token=<access_token>]
	 * : GitHub or GitLab access token if not already saved
	 *
	 * [--bitbucket-private]
	 * : Indicates a private Bitbucket repository
	 *
	 * [--github]
	 * : Optional to denote a GitHub repository
	 * Required when installing from a self-hosted GitHub installation
	 *
	 * [--bitbucket]
	 * : Optional switch to denote a Bitbucket repository
	 * Required when installing from a self-hosted Bitbucket installation
	 *
	 * [--gitlab]
	 * : Optional switch to denote a GitLab repository
	 * Required when installing from a self-hosted GitLab installation
	 *
	 * ## EXAMPLES
	 *
	 *     wp plugin install-git https://github.com/afragen/my-plugin
	 *
	 *     wp plugin install-git https://github.com/afragen/my-plugin --branch=develop --github
	 *
	 *     wp plugin install-git https://bitbucket.org/afragen/my-private-plugin --bitbucket-private
	 *
	 *     wp plugin install-git https://github.com/afragen/my-private-plugin --token=lks9823evalki
	 *
	 * @param array $args       An array of $uri
	 * @param array $assoc_args Array of optional arguments.
	 *
	 * @subcommand install-git
	 */
	public function install_plugin( $args, $assoc_args ) {
		list( $uri ) = $args;
		$cli_config = $this->process_args( $uri, $assoc_args );
		Singleton::get_instance( 'Install' )->install( 'plugin', $cli_config );

		$headers = parse_url( $uri, PHP_URL_PATH );
		$slug    = basename( $headers );
		$this->process_branch( $cli_config, $slug );
		WP_CLI::success( sprintf( esc_html__( 'Plugin %s installed.', 'github-updater' ), "'$slug'" ) );
	}

	/**
	 * Install theme from GitHub, Bitbucket, or GitLab using GitHub Updater.
	 *
	 * ## OPTIONS
	 *
	 * <uri>
	 * : URI to the repo being installed
	 *
	 * [--branch=<branch_name>]
	 * : String indicating the branch name to be installed
	 * ---
	 * default: master
	 * ---
	 *
	 * [--token=<access_token>]
	 * : GitHub or GitLab access token if not already saved
	 *
	 * [--bitbucket-private]
	 * : Indicates a private Bitbucket repository
	 *
	 * [--github]
	 * : Optional to denote a GitHub repository
	 * Required when installing from a self-hosted GitHub installation
	 *
	 * [--bitbucket]
	 * : Optional switch to denote a Bitbucket repository
	 * Required when installing from a self-hosted Bitbucket installation
	 *
	 * [--gitlab]
	 * : Optional switch to denote a GitLab repository
	 * Required when installing from a self-hosted GitLab installation
	 *
	 * ## EXAMPLES
	 *
	 *     wp theme install-git https://github.com/afragen/my-theme
	 *
	 *     wp theme install-git https://bitbucket.org/afragen/my-theme --branch=develop --bitbucket
	 *
	 *     wp theme install-git https://bitbucket.org/afragen/my-private-theme --bitbucket-private
	 *
	 *     wp theme install-git https://github.com/afragen/my-private-theme --token=lks9823evalki
	 *
	 * @param array $args       An array of $uri
	 * @param array $assoc_args Array of optional arguments.
	 *
	 * @subcommand install-git
	 */
	public function install_theme( $args, $assoc_args ) {
		list( $uri ) = $args;
		$cli_config = $this->process_args( $uri, $assoc_args );
		Singleton::get_instance( 'Install' )->install( 'theme', $cli_config );

		$headers = parse_url( $uri, PHP_URL_PATH );
		$slug    = basename( $headers );
		$this->process_branch( $cli_config, $slug );
		WP_CLI::success( sprintf( esc_html__( 'Theme %s installed.', 'github-updater' ), "'$slug'" ) );
	}

	/**
	 * Process WP-CLI config data.
	 *
	 * @param string $uri
	 * @param array  $assoc_args
	 *
	 * @return array $cli_config
	 */
	private function process_args( $uri, $assoc_args ) {
		$cli_config            = array();
		$cli_config['uri']     = $uri;
		$cli_config['private'] = isset( $assoc_args['token'] )
			? $assoc_args['token']
			: $assoc_args['bitbucket-private'];
		$cli_config['branch']  = isset( $assoc_args['branch'] )
			? $assoc_args['branch']
			: 'master';

		switch ( $assoc_args ) {
			case isset( $assoc_args['github'] ):
				$cli_config['git'] = 'github';
				break;
			case isset( $assoc_args['bitbucket'] ):
				$cli_config['git'] = 'bitbucket';
				break;
			case isset( $assoc_args['gitlab'] ):
				$cli_config['git'] = 'gitlab';
				break;
		}

		return $cli_config;
	}

	/**
	 * Process branch setting for WP-CLI.
	 *
	 * @param array  $cli_config
	 * @param string $slug
	 */
	private function process_branch( $cli_config, $slug ) {
		$branch_data['github_updater_branch'] = $cli_config['branch'];
		$branch_data['repo']                  = $slug;

		Singleton::get_instance( 'Branch' )->set_branch_on_install( $branch_data );
	}

}

/**
 * Use custom installer skins to display error messages.
 */
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

/**
 * Class GitHub_Upgrader_CLI_Plugin_Installer_Skin
 */
class CLI_Plugin_Installer_Skin extends \Plugin_Installer_Skin {
	public function header() {
	}

	public function footer() {
	}

	public function error( $errors ) {
		if ( is_wp_error( $errors ) ) {
			WP_CLI::error( $errors->get_error_message() . "\n" . $errors->get_error_data() );
		}
	}

	public function feedback( $string ) {
	}
}

/**
 * Class GitHub_Upgrader_CLI_Theme_Installer_Skin
 */
class CLI_Theme_Installer_Skin extends \Theme_Installer_Skin {
	public function header() {
	}

	public function footer() {
	}

	public function error( $errors ) {
		if ( is_wp_error( $errors ) ) {
			WP_CLI::error( $errors->get_error_message() . "\n" . $errors->get_error_data() );
		}
	}

	public function feedback( $string ) {
	}
}
