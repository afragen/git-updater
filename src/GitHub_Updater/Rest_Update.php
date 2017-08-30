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
		parent::__construct();
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

		foreach ( (array) Singleton::get_instance( 'Plugin' )->get_plugin_configs() as $config_entry ) {
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

		foreach ( (array) Singleton::get_instance( 'Theme' )->get_theme_configs() as $config_entry ) {
			if ( $config_entry->repo === $theme_slug ) {
				$theme = $config_entry;
				break;
			}
		}

		if ( ! $theme ) {
			throw new \UnexpectedValueException( 'Theme not found or not updatable with GitHub Updater: ' . $theme_slug );
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
	 *
	 * Relies on data in $_REQUEST, prints out json and exits.
	 * If the request came through a webhook, and if the branch in the
	 * webhook matches the branch specified by the url, use the latest
	 * update available as specified in the webhook payload.
	 */
	public function process_request() {
		try {
			/*
			 * 128 == JSON_PRETTY_PRINT
			 * 64 == JSON_UNESCAPED_SLASHES
			 */
			$json_encode_flags = 128 | 64;

			if ( ! isset( $_REQUEST['key'] ) ||
			     $_REQUEST['key'] !== get_site_option( 'github_updater_api_key' )
			) {
				throw new \UnexpectedValueException( 'Bad api key.' );
			}

			$tag = 'master';
			if ( isset( $_REQUEST['tag'] ) ) {
				$tag = $_REQUEST['tag'];
			} elseif ( isset( $_REQUEST['committish'] ) ) {
				$tag = $_REQUEST['committish'];
			}

			/**
			 * Parse webhook response and convert 'tag' to 'committish'.
			 * This will avoid potential race conditions.
			 *
			 * Throw Exception if `$_REQUEST['tag'] !== webhook branch` this avoids
			 * unnecessary updates for PUSH to different branch.
			 */
			$webhook_response = $this->get_webhook_data();
			if ( $webhook_response ) {
				if ( $tag === $webhook_response['branch'] ) {
					$tag = $webhook_response['hash'];
				} else {
					throw new \UnexpectedValueException( 'Request tag and webhook are not matching. ' . 'Response: ' . http_build_query( $webhook_response, null, ', ' ) );
				}
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

			echo json_encode( array(
				'message' => $e->getMessage(),
				'error'   => true,
			), $json_encode_flags );
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

		echo json_encode( $response, $json_encode_flags ) . "\n";
		exit;
	}

	/**
	 * For compatibility with PHP 5.3
	 *
	 * @param string $name $_SERVER index.
	 *
	 * @return bool
	 */
	private function is_server_variable_set( $name ) {
		return isset( $_SERVER[ $name ] );
	}

	/**
	 * Checks the headers of the request and sends webhook data to be parsed.
	 * If the request did not come from a webhook, this function returns false.
	 *
	 * @return bool|array false if no data; array of parsed webhook response
	 */
	private function get_webhook_data() {
		$request_body = file_get_contents( 'php://input' );

		// GitHub
		if ( $this->is_server_variable_set( 'HTTP_X_GITHUB_EVENT' ) &&
		     ( 'push' === $_SERVER['HTTP_X_GITHUB_EVENT'] ||
		       'create' === $_SERVER['HTTP_X_GITHUB_EVENT'] )
		) {
			return $this->parse_github_webhook( $request_body );
		}

		// Bitbucket
		if ( $this->is_server_variable_set( 'HTTP_X_EVENT_KEY' ) &&
		     'repo:push' === $_SERVER['HTTP_X_EVENT_KEY']
		) {
			return $this->parse_bitbucket_webhook( $request_body );
		}

		// GitLab
		if ( $this->is_server_variable_set( 'HTTP_X_GITLAB_EVENT' ) &&
		     ( 'Push Hook' === $_SERVER['HTTP_X_GITLAB_EVENT'] ||
		       'Tag Push Hook' === $_SERVER['HTTP_X_GITLAB_EVENT'] )
		) {
			return $this->parse_gitlab_webhook( $request_body );
		}

		return false;
	}

	/**
	 * Parses GitHub webhook data.
	 *
	 * @link https://developer.github.com/v3/activity/events/types/#pushevent
	 *
	 * @param string|bool $request_body
	 *
	 * @return array $response
	 */
	private function parse_github_webhook( $request_body ) {
		$request_body = urldecode( $request_body );
		if ( false !== $pos = strpos( $request_body, '{' ) ) {
			$request_body = substr( $request_body, $pos );
		}

		if ( false !== $pos = strpos( $request_body, '}}' ) ) {
			$request_body = substr( $request_body, 0, $pos ) . '}}';
		}

		$request_data = json_decode( $request_body, true );
		$request_ref  = explode( '/', $request_data['ref'] );

		$response               = array();
		$response['hash']       = isset( $request_data['ref_type'] )
			? $request_data['ref']
			: $request_data['after'];
		$response['branch']     = isset( $request_data['ref_type'] )
			? 'master'
			: array_pop( $request_ref );
		$response['json_error'] = json_last_error_msg();

		//$response['payload'] = $request_data;

		return $response;
	}

	/**
	 * Parses GitLab webhook data.
	 *
	 * @link https://gitlab.com/gitlab-org/gitlab-ce/blob/master/doc/web_hooks/web_hooks.md
	 *
	 * @param string $request_body
	 *
	 * @return array $response
	 */
	private function parse_gitlab_webhook( $request_body ) {
		$request_data = json_decode( $request_body, true );
		$request_ref  = explode( '/', $request_data['ref'] );

		$response               = array();
		$response['hash']       = $request_data['after'];
		$response['branch']     = array_pop( $request_ref );
		$response['json_error'] = json_last_error_msg();

		//$response['payload'] = $request_data;

		return $response;
	}

	/**
	 * Parses Bitbucket webhook data.
	 *
	 * We assume here that changes contains one single entry and that first
	 * entry is the correct one.
	 *
	 * @link https://confluence.atlassian.com/bitbucket/event-payloads-740262817.html#EventPayloads-HTTPHeaders
	 *
	 * @param string $request_body
	 *
	 * @return bool|array $response
	 */
	private function parse_bitbucket_webhook( $request_body ) {
		Singleton::get_instance( 'Basic_Auth_Loader', parent::$options )->load_authentication_hooks();

		$request_data = json_decode( $request_body, true );

		$new = $request_data['push']['changes'][0]['new'];

		$response               = array();
		$response['hash']       = $new['target']['hash'];
		$response['branch']     = 'tag' === $new['type'] ? 'master' : $new['name'];
		$response['json_error'] = json_last_error_msg();

		//$response['payload'] = $new;

		Singleton::get_instance( 'Basic_Auth_Loader', parent::$options )->remove_authentication_hooks();

		return $response;
	}

}
