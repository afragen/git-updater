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
use Fragen\Git_Updater\Branch;
use Fragen\Git_Updater\Install;

// Add WP-CLI commands.
$class = new CLI_Integration();
WP_CLI::add_command( 'plugin install-git', [ $class, 'install_plugin' ] );
WP_CLI::add_command( 'theme install-git', [ $class, 'install_theme' ] );
WP_CLI::add_command( 'plugin branch-switch', [ $class, 'branch_switch' ] );
WP_CLI::add_command( 'theme branch-switch', [ $class, 'branch_switch' ] );

/**
 * Class CLI_Integration
 */
class CLI_Integration extends WP_CLI_Command {
	/**
	 * Install plugin from GitHub, Bitbucket, GitLab, Gitea, Gist, or Zipfile using Git Updater PRO. Appropriate API plugin is required.
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
	 * : GitHub, Bitbucket, GitLab, or Gitea access token if not already saved
	 * Bitbucket pseudo-token in format `username:password`
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
	 * [--gist]
	 * : Optional switch to denote a GitHub Gist repository
	 * Required when installing from a GitHub Gist installation
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
	 *     wp plugin install-git https://bitbucket.org/afragen/my-private-plugin --token=username:password
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
		( new Install() )->install( 'plugin', $cli_config );

		$headers = parse_url( $uri, PHP_URL_PATH );
		$slug    = basename( $headers );
		$this->process_branch( $cli_config, $slug );
		WP_CLI::success( sprintf( 'Plugin %s installed.', "'{$slug}'" ) );
	}

	/**
	 * Install theme from GitHub, Bitbucket, GitLab, Gitea, Gist, or Zipfile using Git Updater PRO. Appropriate API plugin is required.
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
	 * : GitHub, Bitbucket, GitLab, or Gitea access token if not already saved
	 * Bitbucket pseudo-token in format `username:password`
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
	 * [--gist]
	 * : Optional switch to denote a GitHub Gist repository
	 * Required when installing from a GitHub Gist installation
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
	 *     wp theme install-git https://bitbucket.org/afragen/my-private-theme --token=username:password
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
		( new Install() )->install( 'theme', $cli_config );

		$headers = parse_url( $uri, PHP_URL_PATH );
		$slug    = basename( $headers );
		$this->process_branch( $cli_config, $slug );
		WP_CLI::success( sprintf( 'Theme %s installed.', "'$slug'" ) );
	}

	/**
	 * Branch switching via WP-CLI.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : Slug of the repo being installed
	 *
	 * <branch_name>
	 * : String indicating the branch name to be installed
	 * ---
	 * default: master
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp plugin branch-switch <slug> <branch>
	 *
	 *     wp theme branch-switch <slug> <branch>
	 *
	 * @param string $args       Repository slug.
	 *
	 * @subcommand branch-switch
	 */
	public function branch_switch( $args = null ) {
		list( $slug, $branch ) = $args;
		$plugins               = Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this )->get_plugin_configs();
		$themes                = Singleton::get_instance( 'Fragen\Git_Updater\Theme', $this )->get_theme_configs();
		$configs               = array_merge( $plugins, $themes );

		$repo = $configs[ $slug ] ?? false;
		if ( ! $repo ) {
			WP_CLI::error( sprintf( 'There is no repository with slug: %s installed.', "'{$slug}'" ) );
			exit;
		}

		$rest_api_key = Singleton::get_instance( 'Fragen\Git_Updater\Base', $this )->get_class_vars( 'Remote_Management', 'api_key' );
		$api_url      = add_query_arg(
			[
				'key'       => $rest_api_key,
				$repo->type => $repo->slug,
				'branch'    => $branch,
				'override'  => true,
			],
			home_url( 'wp-json/' . Singleton::get_instance( 'Fragen\Git_Updater\Base', $this )->get_class_vars( 'REST\REST_API', 'namespace' ) . '/update/' )
		);
		$response     = wp_remote_get( $api_url, [ 'timeout' => 10 ] );

		if ( is_wp_error( $response ) ) {
			WP_CLI::error( $response->errors['http_request_failed'][0] );
			exit;
		}
		if ( 200 === \wp_remote_retrieve_response_code( $response ) ) {
			WP_CLI::success( $response['body'] );
		} else {
			WP_CLI::warning( 'Branch switching resulted in an error.' );
			WP_CLI::warning( $response['body'] );
		}
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
		$token                 = $assoc_args['token'] ?? false;
		$cli_config            = [];
		$cli_config['uri']     = $uri;
		$cli_config['private'] = $token;
		$cli_config['branch']  = $assoc_args['branch'] ?? 'master';
		$cli_config['slug']    = $assoc_args['slug'] ?? null;

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
			case isset( $assoc_args['gist'] ):
				$cli_config['git'] = 'gist';
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
		$branch_data['git_updater_branch'] = $cli_config['branch'];
		$branch_data['repo']               = $slug;

		( new Branch() )->set_branch_on_install( $branch_data );
	}
}

/**
 * Use custom installer skins to display error messages.
 */
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

/**
 * Class GitHub_Upgrader_CLI_Plugin_Installer_Skin
 */
// phpcs:ignore
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
// phpcs:ignore
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
