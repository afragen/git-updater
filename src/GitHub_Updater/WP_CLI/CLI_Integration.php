<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

namespace Fragen\GitHub_Updater\WP_CLI;

use WP_CLI;
use WP_CLI_Command;
use Fragen\Singleton;

// Add WP-CLI commands.
$class = new CLI_Integration();
WP_CLI::add_command( 'plugin install-git', [ $class, 'install_plugin' ] );
WP_CLI::add_command( 'theme install-git', [ $class, 'install_theme' ] );

/**
 * Class CLI_Integration
 */
class CLI_Integration extends WP_CLI_Command {
	/**
	 * CLI_Integration constructor.
	 */
	public function __construct() {
		$this->run();
	}

	/**
	 * Off to the races.
	 */
	public function run() {
		add_filter( 'site_transient_update_plugins', [ Singleton::get_instance( 'Plugin', $this ), 'update_site_transient' ], 10, 1 );
		add_filter( 'site_transient_update_themes', [ Singleton::get_instance( 'Theme', $this ), 'update_site_transient' ], 10, 1 );
	}

	/**
	 * Install plugin from GitHub, Bitbucket, GitLab, or Gitea using GitHub Updater.
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
	 * : GitHub, GitLab, or Gitea access token if not already saved
	 *
	 * [--bitbucket-private]
	 * : Indicates a private Bitbucket repository
	 *
	 * [--slug=<slug>]
	 * : Optional string indicating the plugin slug

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
	 * [--gitea]
	 * : Optional switch to denote a Gitea repository
	 * Required when installing from a Gitea installation
	 *
	 * [--zipfile]
	 * : Optional switch to denote a Zipfile
	 * Required when installing from a Zipfile
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
	 * @param array $args       An array of $uri.
	 * @param array $assoc_args Array of optional arguments.
	 *
	 * @subcommand install-git
	 */
	public function install_plugin( $args, $assoc_args ) {
		list($uri)  = $args;
		$cli_config = $this->process_args( $uri, $assoc_args );
		Singleton::get_instance( 'Install', $this )->install( 'plugin', $cli_config );

		$headers = parse_url( $uri, PHP_URL_PATH );
		$slug    = basename( $headers );
		$this->process_branch( $cli_config, $slug );
		WP_CLI::success( sprintf( 'Plugin %s installed.', "'$slug'" ) );
	}

	/**
	 * Install theme from GitHub, Bitbucket, GitLab, or Gitea using GitHub Updater.
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
	 * [--slug=<slug>]
	 * : Optional string indicating the theme slug
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
	 * [--gitea]
	 * : Optional switch to denote a Gitea repository
	 * Required when installing from a Gitea installation
	 *
	 * [--zipfile]
	 * : Optional switch to denote a Zipfile
	 * Required when installing from a Zipfile
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
	 * @param array $args       An array of $uri.
	 * @param array $assoc_args Array of optional arguments.
	 *
	 * @subcommand install-git
	 */
	public function install_theme( $args, $assoc_args ) {
		list($uri)  = $args;
		$cli_config = $this->process_args( $uri, $assoc_args );
		Singleton::get_instance( 'Install', $this )->install( 'theme', $cli_config );

		$headers = parse_url( $uri, PHP_URL_PATH );
		$slug    = basename( $headers );
		$this->process_branch( $cli_config, $slug );
		WP_CLI::success( sprintf( 'Theme %s installed.', "'$slug'" ) );
	}

	/**
	 * Process WP-CLI config data.
	 *
	 * @param string $uri        URI to process.
	 * @param array  $assoc_args Args to process.
	 *
	 * @return array $cli_config
	 */
	private function process_args( $uri, $assoc_args ) {
		$token                 = isset( $assoc_args['token'] ) ? $assoc_args['token'] : false;
		$bitbucket_private     = isset( $assoc_args['bitbucket-private'] ) ? $assoc_args['bitbucket-private'] : false;
		$cli_config            = [];
		$cli_config['uri']     = $uri;
		$cli_config['private'] = $token ?: $bitbucket_private;
		$cli_config['branch']  = isset( $assoc_args['branch'] ) ? $assoc_args['branch'] : 'master';
		$cli_config['slug']    = isset( $assoc_args['slug'] ) ? $assoc_args['slug'] : null;

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
			case isset( $assoc_args['gitea'] ):
				$cli_config['git'] = 'gitea';
				break;
			case isset( $assoc_args['zipfile'] ):
				$cli_config['git'] = 'zipfile';
				break;
		}

		return $cli_config;
	}

	/**
	 * Process branch setting for WP-CLI.
	 *
	 * @param array  $cli_config Config args.
	 * @param string $slug       Repository slug.
	 */
	private function process_branch( $cli_config, $slug ) {
		$branch_data['github_updater_branch'] = $cli_config['branch'];
		$branch_data['repo']                  = $slug;

		Singleton::get_instance( 'Branch', $this )->set_branch_on_install( $branch_data );
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

	/** Skin feeback. */
	public function header() {
	}

	/** Skin footer. */
	public function footer() {
	}

	/**
	 * Skin error.
	 *
	 * @param \stdClass $errors Error object.
	 *
	 * @return void
	 */
	public function error( $errors ) {
		if ( is_wp_error( $errors ) ) {
			WP_CLI::error( $errors->get_error_message() . "\n" . $errors->get_error_data() );
		}
	}

	/**
	 * Skin feedback.
	 *
	 * @param string $string  Feedback message.
	 * @param array  ...$args Feedback args.
	 *
	 * @return void
	 */
	public function feedback( $string, ...$args ) {
	}
}

/**
 * Class GitHub_Upgrader_CLI_Theme_Installer_Skin
 */
class CLI_Theme_Installer_Skin extends \Theme_Installer_Skin {

	/** Skin header. */
	public function header() {
	}

	/** Skin footer. */
	public function footer() {
	}

	/**
	 * Skin error.
	 *
	 * @param \stdClass $errors Error object.
	 *
	 * @return void
	 */
	public function error( $errors ) {
		if ( is_wp_error( $errors ) ) {
			WP_CLI::error( $errors->get_error_message() . "\n" . $errors->get_error_data() );
		}
	}

	/**
	 * Skin feedback.
	 *
	 * @param string $string  Feedback message.
	 * @param array  ...$args Feedback args.
	 *
	 * @return void
	 */
	public function feedback( $string, ...$args ) {
	}
}
