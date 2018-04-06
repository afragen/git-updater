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

use Fragen\Singleton;


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
		parent::__construct();
		$this->load_options();
		$this->upgrader_skin = new Rest_Upgrader_Skin();
	}

	/**
	 * Update plugin.
	 *
	 * @param string $plugin_slug
	 * @param string $tag
	 *
	 * @throws \Exception
	 */
	public function update_plugin( $plugin_slug, $tag = 'master' ) {
		$plugin           = null;
		$is_plugin_active = false;

		foreach ( (array) Singleton::get_instance( 'Plugin', $this )->get_plugin_configs() as $config_entry ) {
			if ( $config_entry->repo === $plugin_slug ) {
				$plugin = $config_entry;
				break;
			}
		}

		if ( ! $plugin ) {
			throw new \UnexpectedValueException( 'Plugin not found or not updatable with GitHub Updater: ' . $plugin_slug );
		}

		if ( is_plugin_active( $plugin->slug ) ) {
			$is_plugin_active = true;
		}

		$this->get_remote_repo_meta( $plugin );

		$update = array(
			'slug'        => $plugin->repo,
			'plugin'      => $plugin->slug,
			'new_version' => null,
			'url'         => $plugin->uri,
			'package'     => $this->repo_api->construct_download_link( false, $tag ),
		);

		add_filter( 'site_transient_update_plugins', function( $value ) use ( $plugin, $update ) {
			$value->response[ $plugin->slug ] = (object) $update;

			return $value;
		} );

		$upgrader = new \Plugin_Upgrader( $this->upgrader_skin );
		$upgrader->upgrade( $plugin->slug );

		if ( $is_plugin_active ) {
			$activate = is_multisite() ? activate_plugin( $plugin->slug, null, true ) : activate_plugin( $plugin->slug );
			if ( ! $activate ) {
				$this->upgrader_skin->messages[] = 'Plugin reactivated successfully.';
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

		foreach ( (array) Singleton::get_instance( 'Theme', $this )->get_theme_configs() as $config_entry ) {
			if ( $config_entry->repo === $theme_slug ) {
				$theme = $config_entry;
				break;
			}
		}

		if ( ! $theme ) {
			throw new \UnexpectedValueException( 'Theme not found or not updatable with GitHub Updater: ' . $theme_slug );
		}

		$this->get_remote_repo_meta( $theme );

		$update = array(
			'theme'       => $theme->repo,
			'new_version' => null,
			'url'         => $theme->uri,
			'package'     => $this->repo_api->construct_download_link( false, $tag ),
		);

		add_filter( 'site_transient_update_themes', function( $value ) use ( $theme, $update ) {
			$value->response[ $theme->repo ] = $update;

			return $value;
		} );

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
	 * Log messages during update
	 */
	public function log($message, $obj=""){

		if(!defined('GHU_DEBUG'))
			return;

		$current_time = microtime(true);

		if(!isset($this->start)) {
			$this->start = $current_time;
		}

		$time_lapse = $current_time - $this->start;
		$millis = round( $time_lapse * 1000 );
		error_log( $millis . " ms : ". $message . " " . print_r( $obj, true ) );
	}

	/**
	 * Process request.
	 *
	 * Relies on data in $_REQUEST, prints out json and exits.
	 * If the request came through a webhook, and if the branch in the
	 * webhook matches the branch specified by the url, use the latest
	 * update available as specified in the webhook payload.
	 */
	public function process_request() {
		try {

			// DEBUG
			$this->log("ENTERING IN \"process_request\", \$_REQUEST = ", $_REQUEST);
			//

			/*
			 * 128 == JSON_PRETTY_PRINT
			 * 64 == JSON_UNESCAPED_SLASHES
			 */
			$json_encode_flags = 128 | 64;

			if ( ! isset( $_REQUEST['key'] ) ||
			     $_REQUEST['key'] !== get_site_option( 'github_updater_api_key' )
			) {
				throw new \UnexpectedValueException( 'Bad API key.' );
			}

			// DEBUG
			$this->log("doing action: \"github_updater_pre_rest_process_request\"");
			//

			/**
			 * Allow access into the REST Update process.
			 *
			 * @since  7.6.0
			 * @access public
			 */
			do_action( 'github_updater_pre_rest_process_request' );

			$tag = 'master';
			if ( isset( $_REQUEST['tag'] ) ) {
				$tag = $_REQUEST['tag'];
			} elseif ( isset( $_REQUEST['committish'] ) ) {
				$tag = $_REQUEST['committish'];
			}

			$current_branch   = $this->get_local_branch();
			$webhook_response = $this->get_webhook_source();

			// DEBUG
			 $this->log("\$current_branch = ", $current_branch);
			 $this->log("\$webhook_response = ", $webhook_response);
			//

			if ( null !== $current_branch && $tag !== $current_branch ) {
				throw new \UnexpectedValueException( 'Request tag and webhook are not matching.' );
			}

			if ( isset( $_REQUEST['plugin'] ) ) {
				$this->update_plugin( $_REQUEST['plugin'], $tag );
			} elseif ( isset( $_REQUEST['theme'] ) ) {
				$this->update_theme( $_REQUEST['theme'], $tag );
			} else {
				throw new \UnexpectedValueException( 'No plugin or theme specified for update.' );
			}
		} catch ( \Exception $e ) {
			//http_response_code( 417 ); //@TODO PHP 5.4
			header( 'HTTP/1.1 417 Expectation Failed' );
			header( 'Content-Type: application/json' );

			$http_response = json_encode( array(
				'message' => $e->getMessage(),
				'error'   => true,
			), $json_encode_flags );

			// DEBUG
			$this->log("ENDING \"\\Exception\", \$http_response = ", $http_response);
			//

			echo $http_response;
			exit;
		}

		header( 'Content-Type: application/json' );

		$response = array(
			'messages' => $this->get_messages(),
			'response' => $webhook_response ?: $_GET,
		);

		if ( $this->is_error() ) {
			$response['error'] = true;
			//http_response_code( 417 ); //@TODO PHP 5.4
			header( 'HTTP/1.1 417 Expectation Failed' );
		} else {
			$response['success'] = true;
		}

		$http_response = json_encode( $response, $json_encode_flags ) . "\n";

		// DEBUG
		$this->log("ENDING \"process_request\", \$http_response = ", $http_response);
		//

		echo $http_response;
		exit;
	}

	/**
	 * Returns the current branch of the local repository referenced in the webhook.
	 *
	 * @return string $current_branch
	 */
	private function get_local_branch() {
		if ( isset( $_REQUEST['plugin'] ) ) {
			$repos = Singleton::get_instance( 'Plugin', $this )->get_plugin_configs();
			$repo  = isset($repos[ $_REQUEST['plugin'] ]) ? $repos[ $_REQUEST['plugin'] ] : null;
		}
		if ( isset( $_REQUEST['theme'] ) ) {
			$repos = Singleton::get_instance( 'Theme', $this )->get_theme_configs();
			$repo  = isset($repos[ $_REQUEST['theme'] ]) ? $repos[ $_REQUEST['theme'] ] : null;
		}

		if ( isset($repo) ) {
			$current_branch = Singleton::get_instance( 'Branch', $this )->get_current_branch( $repo );
			return $current_branch;
		}
	}

	/**
	 * Sets the source of the webhook in upgrader skin.
	 */
	private function get_webhook_source() {
		switch ( $_SERVER ) {
			case isset( $_SERVER['HTTP_X_GITHUB_EVENT'] ):
				$webhook_source = 'GitHub webhook';
				break;
			case isset( $_SERVER['HTTP_X_EVENT_KEY'] ):
				$webhook_source = 'Bitbucket webhook';
				break;
			case isset( $_SERVER['HTTP_X_GITLAB_EVENT'] ):
				$webhook_source = 'GitLab webhook';
				break;
			default:
				$webhook_source = 'browser';
				break;
		}
		$this->upgrader_skin->messages[] = $webhook_source;
		return $webhook_source;
	}
}
