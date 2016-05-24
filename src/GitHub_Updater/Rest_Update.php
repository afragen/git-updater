<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @author    Mikael Lindqvist
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

namespace Fragen\GitHub_Updater;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

/**
 * Class Rest_Update
 *
 * Updates a single plugin or theme, in a way suitable for rest requests.
 * This class inherits from Base in order to be able to call the
 * set_defaults function.
 *
 * @package Fragen\GitHub_Updater
 */
class Rest_Update extends Base {

	/**
	 * Holds REST API Upgrader Skin.
	 *
	 * @var \Fragen\GitHub_Updater\Rest_Upgrader_Skin
	 */
	protected $upgrader_skin;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->upgrader_skin = new Rest_Upgrader_Skin();
	}

	/**
	 * Fill in some more information about the plugin. The code for this
	 * function is more or less copy/pasted from the Plugin    class within
	 * the get_remote_plugin_meta function.
	 * Ideally this code should not exist in two places, so some some
	 * refactoring could improve things.
	 * 
	 * @param object $plugin
	 */
	protected function get_single_remote_plugin_meta( $plugin ) {
		$this->repo_api = null;
		switch ( $plugin->type ) {
			case 'github_plugin':
				$this->repo_api = new GitHub_API( $plugin );
				break;
			case 'bitbucket_plugin':
				$this->repo_api = new Bitbucket_API( $plugin );
				break;
			case 'gitlab_plugin';
				$this->repo_api = new GitLab_API( $plugin );
				break;
			default:
				return;
		}

		$this->{$plugin->type} = $plugin;
		$this->set_defaults( $plugin->type );

		if ( $this->repo_api->get_remote_info( basename( $plugin->slug ) ) ) {
			$this->repo_api->get_repo_meta();
			$this->repo_api->get_remote_tag();
			$changelog = $this->get_changelog_filename( $plugin->type );
			if ( $changelog ) {
				$this->repo_api->get_remote_changes( $changelog );
			}
			$this->repo_api->get_remote_readme();
			$plugin->download_link = $this->repo_api->construct_download_link();
		}
	}

	/**
	 * Update plugin.
	 *
	 * @param  string $plugin_slug
	 * @param string  $tag
	 *
	 * @throws \Exception
	 */
	public function update_plugin( $plugin_slug, $tag = "master" ) {
		$plugin = null;

		foreach ( (array) Plugin::instance()->get_plugin_configs() as $config_entry ) {
			if ( $config_entry->repo == $plugin_slug ) {
				$plugin = $config_entry;
			}
		}

		if ( ! $plugin ) {
			throw new \Exception( "Plugin not found: " . $plugin_slug );
		}

		$this->get_single_remote_plugin_meta( $plugin );

		$updates_transient = get_site_transient( 'update_plugins' );
		$update            = array(
			"slug"        => $plugin->repo,
			"plugin"      => $plugin->slug,
			"new_version" => null,
			"url"         => $plugin->uri,
			"package"     => $this->repo_api->construct_download_link( false, $tag ),
		);

		$updates_transient->response[ $plugin->slug ] = (object) $update;
		set_site_transient( 'update_plugins', $updates_transient );

		$upgrader = new \Plugin_Upgrader( $this->upgrader_skin );
		$upgrader->upgrade( $plugin->slug );
	}

	/**
	 * Fill in some more information about the plugin. The code is copy
	 * pasted from the Theme class, for further comments see the
	 * get_single_remote_plugin_meta function.
	 *
	 * @param $theme
	 */
	protected function get_single_remote_theme_meta( $theme ) {
		$this->repo_api = null;
		switch ( $theme->type ) {
			case 'github_theme':
				$this->repo_api = new GitHub_API( $theme );
				break;
			case 'bitbucket_theme':
				$this->repo_api = new Bitbucket_API( $theme );
				break;
			case 'gitlab_theme':
				$this->repo_api = new GitLab_API( $theme );
				break;
		}

		if ( is_null( $this->repo_api ) ) {
			return;
		}

		$this->{$theme->type} = $theme;
		$this->set_defaults( $theme->type );

		if ( $this->repo_api->get_remote_info( 'style.css' ) ) {
			$this->repo_api->get_repo_meta();
			$this->repo_api->get_remote_tag();
			$changelog = $this->get_changelog_filename( $theme->type );
			if ( $changelog ) {
				$this->repo_api->get_remote_changes( $changelog );
			}
			$theme->download_link = $this->repo_api->construct_download_link();
		}
	}

	/**
	 * Update a single theme.
	 *
	 * @param string $theme_slug
	 * @param string $tag
	 *
	 * @throws \Exception
	 */
	public function update_theme( $theme_slug, $tag = "master" ) {
		$theme = null;

		foreach ( (array) Theme::instance()->get_theme_configs() as $config_entry ) {
			if ( $config_entry->repo == $theme_slug ) {
				$theme = $config_entry;
			}
		}

		if ( ! $theme ) {
			throw new \Exception( "Theme not found: " . $theme_slug );
		}

		$this->get_single_remote_theme_meta( $theme );

		$updates_transient = get_site_transient( 'update_themes' );
		$update            = array(
			"theme"       => $theme->repo,
			"new_version" => null,
			"url"         => $theme->uri,
			"package"     => $this->repo_api->construct_download_link( false, $tag ),
		);

		$updates_transient->response[ $theme->repo ] = $update;
		set_site_transient( 'update_themes', $updates_transient );

		$upgrader = new \Theme_Upgrader( $this->upgrader_skin );
		$upgrader->upgrade( $theme->repo );
	}

	/**
	 * Is there an error?
	 */
	public function is_error() {
		return $this->upgrader_skin->error;
	}

	/**
	 * Get messages during update.
	 */
	public function get_messages() {
		return $this->upgrader_skin->messages;
	}

	/**
	 * Process request.
	 * Relies on data in $_REQUEST, prints out json and exits.
	 */
	public function process_request() {
		try {
			$json_encode_flags = 0;
			if ( defined( "JSON_PRETTY_PRINT" ) ) {
				$json_encode_flags = JSON_PRETTY_PRINT;
			}

			if ( ! isset( $_REQUEST["key"] ) || $_REQUEST["key"] != get_site_option( 'github_updater_api_key' ) ) {
				throw new \Exception( "Bad api key." );
			}

			$tag = "master";
			if ( isset( $_REQUEST["tag"] ) && $_REQUEST["tag"] ) {
				$tag = $_REQUEST["tag"];
			}

			if ( isset( $_REQUEST["committish"] ) && $_REQUEST["committish"] ) {
				$tag = $_REQUEST["committish"];
			}

			if ( isset( $_REQUEST["plugin"] ) && $_REQUEST["plugin"] ) {
				$this->update_plugin( $_REQUEST["plugin"], $tag );
			} else if ( isset( $_REQUEST["theme"] ) && $_REQUEST["theme"] ) {
				$this->update_theme( $_REQUEST["theme"], $tag );
			} else {
				throw new \Exception( "No plugin or theme specified for update." );
			}
		} catch ( \Exception $e ) {
			http_response_code( 500 );
			header( 'Content-Type: application/json' );

			echo json_encode( array(
				"message" => $e->getMessage(),
				"error"   => true,
			), $json_encode_flags );
			exit;
		}

		header( 'Content-Type: application/json' );

		$response = array(
			"messages" => $this->get_messages(),
		);

		if ( $this->is_error() ) {
			$response["error"] = true;
			http_response_code( 500 );
		} else {
			$response["success"] = true;
		}

		echo json_encode( $response, $json_encode_flags );
		exit;
	}
}
