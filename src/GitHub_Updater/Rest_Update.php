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
	 * Holds REST Upgrader Skin.
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
	 * Update plugin.
	 *
	 * @param  string $plugin_slug
	 * @param string  $tag
	 *
	 * @throws \Exception
	 */
	public function update_plugin( $plugin_slug, $tag = 'master' ) {
		$plugin           = null;
		$is_plugin_active = false;

		foreach ( (array) Plugin::instance()->get_plugin_configs() as $config_entry ) {
			if ( $config_entry->repo === $plugin_slug ) {
				$plugin = $config_entry;
				break;
			}
		}

		if ( ! $plugin ) {
			throw new \Exception( esc_html__( 'Plugin not found or not updatable with GitHub Updater: ', 'github-updater' ) . $plugin_slug );
		}

		if ( is_plugin_active( $plugin->slug ) ) {
			$is_plugin_active = true;
		}

		$this->get_remote_repo_meta( $plugin );

		$updates_transient = get_site_transient( 'update_plugins' );
		$update            = array(
			'slug'        => $plugin->repo,
			'plugin'      => $plugin->slug,
			'new_version' => null,
			'url'         => $plugin->uri,
			'package'     => $this->repo_api->construct_download_link( false, $tag ),
		);

		$updates_transient->response[ $plugin->slug ] = (object) $update;
		set_site_transient( 'update_plugins', $updates_transient );

		$upgrader = new \Plugin_Upgrader( $this->upgrader_skin );
		$upgrader->upgrade( $plugin->slug );

		if ( $is_plugin_active ) {
			$activate = is_multisite() ? activate_plugin( $plugin->slug, null, true ) : activate_plugin( $plugin->slug );
			if ( ! $activate ) {
				$this->upgrader_skin->messages[] = esc_html__( 'Plugin reactivated successfully.', 'github-updater' );
			}
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
	public function update_theme( $theme_slug, $tag = 'master' ) {
		$theme = null;

		foreach ( (array) Theme::instance()->get_theme_configs() as $config_entry ) {
			if ( $config_entry->repo === $theme_slug ) {
				$theme = $config_entry;
				break;
			}
		}

		if ( ! $theme ) {
			throw new \Exception( esc_html__( 'Theme not found or not updatable with GitHub Updater: ', 'github-updater' ) . $theme_slug );
		}

		$this->get_remote_repo_meta( $theme );

		$updates_transient = get_site_transient( 'update_themes' );
		$update            = array(
			'theme'       => $theme->repo,
			'new_version' => null,
			'url'         => $theme->uri,
			'package'     => $this->repo_api->construct_download_link( false, $tag ),
		);

		$updates_transient->response[ $theme->repo ] = $update;
		set_site_transient( 'update_themes', $updates_transient );

		$upgrader = new \Theme_Upgrader( $this->upgrader_skin );
		$upgrader->upgrade( $theme->repo );
	}

	/**
	 * Return listing of available updates.
	 *
	 * @param $response
	 *
	 * @return mixed
	 */
	public function show_updates( $response ) {
		$themes       = get_site_transient( 'update_themes' );
		$plugins      = get_site_transient( 'update_plugins' );
		$show_plugins = null;
		$show_themes  = null;

		/*
		 * Ensure update data is up to date.
		 */
		$this->forced_meta_update_remote_management();
		$themes  = Theme::instance()->pre_set_site_transient_update_themes( $themes );
		$plugins = Plugin::instance()->pre_set_site_transient_update_plugins( $plugins );

		foreach ( $plugins->response as $plugin ) {
			$plugin->plugin = $plugin->slug;
			unset( $plugin->slug );
			unset( $plugin->url );
			unset( $plugin->package );

			if ( isset( $plugin->id, $plugin->tested, $plugin->compatibility ) ) {
				unset( $plugin->id );
				unset( $plugin->tested );
				unset( $plugin->compatibility );
			}
			$show_plugins[] = $plugin;
		}

		foreach ( $themes->response as $theme ) {
			unset( $theme['url'] );
			unset( $theme['package'] );
			$show_themes[] = $theme;
		}

		$response['messages'] = esc_html__( 'Available Updates', 'github-updater' );
		$response['plugins']  = $show_plugins;
		$response['themes']   = $show_themes;

		return $response;
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
	 * See if a tag came in through a github webhook. If so, return an
	 * array containing the keys branch and hash related to the commit.
	 * It is good to use this latest commited hash from github and
	 * be explicit when specifying the tag we want to update to. If we don't
	 * do this there is a chance for a race condition, since the default
	 * zip file on github might not have been created yet.
	 */
	private function get_github_webhook_data() {
		$request_body = file_get_contents('php://input');
		$request_data = json_decode($request_body, TRUE);

		if (!$request_data) {
			return NULL;
		}

		if (!isset($request_data["ref"]) || !isset($request_data["after"])) {
			return NULL;
		}

		$res = array();
		$res["hash"] = $request_data["after"];
		$res["branch"] = substr(
			$request_data["ref"],
			strrpos($request_data["ref"], '/') + 1
		);

		return $res;
	}

	/**
	 * Process request.
	 * Relies on data in $_REQUEST, prints out json and exits.
	 */
	public function process_request() {
		try {
			$show_updates      = false;
			$json_encode_flags = 128; // 128 == JSON_PRETTY_PRINT
			if ( defined( 'JSON_PRETTY_PRINT' ) ) {
				$json_encode_flags = JSON_PRETTY_PRINT;
			}

			if ( ! isset( $_REQUEST['key'] ) ||
			     $_REQUEST['key'] != get_site_option( 'github_updater_api_key' )
			) {
				throw new \Exception( esc_html__( 'Bad api key.', 'github-updater' ) );
			}

			$tag = 'master';
			if ( isset( $_REQUEST['tag'] ) ) {
				$tag = $_REQUEST['tag'];
			} elseif ( isset( $_REQUEST['committish'] ) ) {
				$tag = $_REQUEST['committish'];
			}

			$hook_data = $this->get_github_webhook_data();
			if ($hook_data && $tag == $hook_data["branch"]) {
				$tag = $hook_data["hash"];
			}

			if ( isset( $_REQUEST['plugin'] ) ) {
				$this->update_plugin( $_REQUEST['plugin'], $tag );
			} elseif ( isset( $_REQUEST['theme'] ) ) {
				$this->update_theme( $_REQUEST['theme'], $tag );
			} elseif ( isset( $_REQUEST['updates'] ) ) {
				$show_updates = true;
			} else {
				throw new \Exception( esc_html__( 'No plugin or theme specified for update.', 'github-updater' ) );
			}
		} catch ( \Exception $e ) {
			http_response_code( 500 );
			header( 'Content-Type: application/json' );

			echo json_encode( array(
				'message' => $e->getMessage(),
				'error'   => true,
			), $json_encode_flags );
			exit;
		}

		header( 'Content-Type: application/json' );

		$response = array(
			'messages' => $this->get_messages(),
		);

		if ( $show_updates ) {
			$response = $this->show_updates( $response );
		}

		if ( $this->is_error() ) {
			$response['error'] = true;
			http_response_code( 500 );
		} else {
			$response['success'] = true;
		}

		echo json_encode( $response, $json_encode_flags ) . "\n";
		exit;
	}

}
