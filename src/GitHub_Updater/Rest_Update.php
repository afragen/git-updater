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
	 * Process request.
	 *
	 * Relies on data in $_REQUEST, prints out json and exits.
	 * If the request came through a webhook, and if the branch in the
	 * webhook matches the branch specified by the url, use the latest
	 * update available as specified in the webhook payload.
	 */
	public function process_request() {
		$start = microtime( true );
		try {
			if ( ! isset( $_REQUEST['key'] ) ||
			     $_REQUEST['key'] !== get_site_option( 'github_updater_api_key' )
			) {
				throw new \UnexpectedValueException( 'Bad API key.' );
			}

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

			$current_branch = $this->get_local_branch();
			$this->get_webhook_source();
			if ( $tag !== $current_branch ) {
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
			$http_response = array(
				'success'      => false,
				'messages'     => $e->getMessage(),
				'webhook'      => $_GET,
				'elapsed_time' => round( ( microtime( true ) - $start ) * 1000, 2 ) . ' ms',
			);
			$this->log_exit( $http_response, 417 );
		}

		$response = array(
			'success'      => true,
			'messages'     => $this->get_messages(),
			'webhook'      => $_GET,
			'elapsed_time' => round( ( microtime( true ) - $start ) * 1000, 2 ) . ' ms',
		);

		if ( $this->is_error() ) {
			$response['success'] = false;
			$this->log_exit( $response, 417 );
		}
		$this->log_exit( $response, 200 );
	}

	/**
	 * Returns the current branch of the local repository referenced in the webhook.
	 *
	 * @return string $current_branch Default return is 'master'.
	 */
	private function get_local_branch() {
		$repo = false;
		if ( isset( $_REQUEST['plugin'] ) ) {
			$repos = Singleton::get_instance( 'Plugin', $this )->get_plugin_configs();
			$repo  = isset( $repos[ $_REQUEST['plugin'] ] ) ? $repos[ $_REQUEST['plugin'] ] : false;
		}
		if ( isset( $_REQUEST['theme'] ) ) {
			$repos = Singleton::get_instance( 'Theme', $this )->get_theme_configs();
			$repo  = isset( $repos[ $_REQUEST['theme'] ] ) ? $repos[ $_REQUEST['theme'] ] : false;
		}
		$current_branch = $repo ?
			Singleton::get_instance( 'Branch', $this )->get_current_branch( $repo ) :
			'master';

		return $current_branch;
	}

	/**
	 * Sets the source of the webhook to $_GET variable.
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
			case isset( $_SERVER['HTTP_X_GITEA_EVENT'] ):
				$webhook_source = 'Gitea webhook';
				break;
			default:
				$webhook_source = 'browser';
				break;
		}
		$_GET['webhook_source'] = $webhook_source;
	}

	/**
	 * Append $response to debug.log and wp_die().
	 *
	 * @param array $response
	 * @param int   $code
	 *
	 * 128 == JSON_PRETTY_PRINT
	 * 64 == JSON_UNESCAPED_SLASHES
	 */
	private function log_exit( $response, $code ) {
		$json_encode_flags = 128 | 64;

		error_log( json_encode( $response, $json_encode_flags ) );
		unset( $response['success'] );
		if ( 200 === $code ) {
			wp_die( wp_send_json_success( $response, $code ) );
		} else {
			wp_die( wp_send_json_error( $response, $code ) );
		}
	}
}
