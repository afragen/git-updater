<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen, Mikael Lindqvist
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

namespace Fragen\GitHub_Updater;

use Fragen\Singleton;
use Fragen\GitHub_Updater\Traits\GHU_Trait;

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
 */
class Rest_Update {
	use GHU_Trait;

	/**
	 * Holds REST Upgrader Skin.
	 *
	 * @var Rest_Upgrader_Skin $upgrader_skin
	 */
	protected $upgrader_skin;

	/**
	 * Holds sanitized $_REQUEST.
	 *
	 * @var array
	 */
	protected static $request;

	/**
	 * Holds regex pattern for version number.
	 * Allows for leading 'v'.
	 *
	 * @var string
	 */
	protected static $version_number_regex = '@(?:v)?[0-9\.]+@i';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->load_options();
		$this->upgrader_skin = new Rest_Upgrader_Skin();

		// phpcs:ignore WordPress.Security.NonceVerification
		self::$request = $this->sanitize( $_REQUEST );
	}

	/**
	 * Update plugin.
	 *
	 * @param string $plugin_slug
	 * @param string $tag
	 *
	 * @throws \UnexpectedValueException Plugin not found or not updatable.
	 */
	public function update_plugin( $plugin_slug, $tag = 'master' ) {
		$plugin           = null;
		$is_plugin_active = false;

		foreach ( (array) Singleton::get_instance( 'Plugin', $this )->get_plugin_configs() as $config_entry ) {
			if ( $config_entry->slug === $plugin_slug ) {
				$plugin = $config_entry;
				break;
			}
		}

		if ( ! $plugin ) {
			throw new \UnexpectedValueException( 'Plugin not found or not updatable with GitHub Updater: ' . $plugin_slug );
		}

		if ( is_plugin_active( $plugin->file ) ) {
			$is_plugin_active = true;
		}

		Singleton::get_instance( 'Base', $this )->get_remote_repo_meta( $plugin );
		$repo_api = Singleton::get_instance( 'API', $this )->get_repo_api( $plugin->git, $plugin );

		$update = [
			'slug'        => $plugin->slug,
			'plugin'      => $plugin->file,
			'new_version' => null,
			'url'         => $plugin->uri,
			'package'     => $repo_api->construct_download_link( $tag ),
		];

		add_filter(
			'site_transient_update_plugins',
			function ( $current ) use ( $plugin, $update ) {
				// needed to fix PHP 7.4 warning.
				if ( ! \is_object( $current ) ) {
					$current           = new \stdClass();
					$current->response = null;
				} elseif ( ! \property_exists( $current, 'response' ) ) {
					$current->response = null;
				}

				$current->response[ $plugin->file ] = (object) $update;

				return $current;
			}
		);

		$upgrader = new \Plugin_Upgrader( $this->upgrader_skin );
		$upgrader->upgrade( $plugin->file );

		if ( $is_plugin_active ) {
			$activate = is_multisite() ? activate_plugin( $plugin->file, null, true ) : activate_plugin( $plugin->file );
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
	 * @throws \UnexpectedValueException Theme not found or not updatable.
	 */
	public function update_theme( $theme_slug, $tag = 'master' ) {
		$theme = null;

		foreach ( (array) Singleton::get_instance( 'Theme', $this )->get_theme_configs() as $config_entry ) {
			if ( $config_entry->slug === $theme_slug ) {
				$theme = $config_entry;
				break;
			}
		}

		if ( ! $theme ) {
			throw new \UnexpectedValueException( 'Theme not found or not updatable with GitHub Updater: ' . $theme_slug );
		}

		Singleton::get_instance( 'Base', $this )->get_remote_repo_meta( $theme );
		$repo_api = Singleton::get_instance( 'API', $this )->get_repo_api( $theme->git, $theme );

		$update = [
			'theme'       => $theme->slug,
			'new_version' => null,
			'url'         => $theme->uri,
			'package'     => $repo_api->construct_download_link( $tag ),
		];

		add_filter(
			'site_transient_update_themes',
			function ( $current ) use ( $theme, $update ) {
				// needed to fix PHP 7.4 warning.
				if ( ! \is_object( $current ) ) {
					$current           = new \stdClass();
					$current->response = null;
				} elseif ( ! \property_exists( $current, 'response' ) ) {
					$current->response = null;
				}

				$current->response[ $theme->slug ] = $update;

				return $current;
			}
		);

		$upgrader = new \Theme_Upgrader( $this->upgrader_skin );
		$upgrader->upgrade( $theme->slug );
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
	 *
	 * @param \WP_REST_Request|null $request Request data from update webhook.
	 *
	 * @throws \UnexpectedValueException Under multiple bad or missing params.
	 */
	public function process_request( $request = null ) {
		$args = $this->process_request_data( $request );
		extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		$start = microtime( true );
		try {
			if ( ! $key ||
				get_site_option( 'github_updater_api_key' ) !== $key
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

			$this->get_webhook_source();
			$tag            = $committish ? $committish : $tag;
			$current_branch = $this->get_local_branch( $plugin, $theme );

			if ( ! ( 0 === preg_match( self::$version_number_regex, $tag ) ) ) {
				$remote_branch = 'master';
			}
			if ( $branch ) {
				$tag           = $branch;
				$remote_branch = $branch;
			}
			$remote_branch  = isset( $remote_branch ) ? $remote_branch : $tag;
			$current_branch = $override ? $remote_branch : $current_branch;
			if ( $remote_branch !== $current_branch && ! $override ) {
				throw new \UnexpectedValueException( 'Webhook tag and current branch are not matching. Consider using `override` query arg.' );
			}

			if ( $plugin ) {
				$this->update_plugin( $plugin, $tag );
			} elseif ( $theme ) {
				$this->update_theme( $theme, $tag );
			} else {
				throw new \UnexpectedValueException( 'No plugin or theme specified for update.' );
			}
		} catch ( \Exception $e ) {
			$http_response = [
				'success'      => false,
				'messages'     => $e->getMessage(),
				'webhook'      => $_GET, // phpcs:ignore WordPress.Security.NonceVerification
				'elapsed_time' => round( ( microtime( true ) - $start ) * 1000, 2 ) . ' ms',
				'deprecated'   => $deprecated,
			];
			$this->log_exit( $http_response, 417 );
		}

		// Only set branch on successful update.
		if ( ! $this->is_error() ) {
			$slug      = $plugin ? $plugin : false;
			$slug      = $theme ? $theme : $slug;
			$file      = $plugin ? $plugin . '.php' : 'style.css';
			$options   = $this->get_class_vars( 'Base', 'options' );
			$cache     = $this->get_repo_cache( $slug );
			$cache_key = 'ghu-' . md5( $slug );

			$cache['current_branch'] = $current_branch;
			unset( $cache[ $file ] );
			update_site_option( $cache_key, $cache );

			$options[ 'current_branch_' . $slug ] = $current_branch;
			update_site_option( 'github_updater', $options );
		}

		$response = [
			'success'      => true,
			'messages'     => $this->get_messages(),
			'webhook'      => $_GET, // phpcs:ignore WordPress.Security.NonceVerification
			'elapsed_time' => round( ( microtime( true ) - $start ) * 1000, 2 ) . ' ms',
			'deprecated'   => $deprecated,
		];

		if ( $this->is_error() ) {
			$response['success'] = false;
			$this->log_exit( $response, 417 );
		}
		$this->log_exit( $response, 200 );
	}

	/**
	 * Process request data from REST API or RESTful endpoint.
	 *
	 * @param \WP_REST_Request|array $request Request data from update webhook.
	 *
	 * @return array
	 */
	public function process_request_data( $request = null ) {
		if ( $request instanceof \WP_REST_Request ) {
			$params = $request->get_params();
			extract( $params ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			$override   = false === $override ? false : true;
			$deprecated = false;
		} else { // call from admin-ajax.php.
			$key        = empty( self::$request['key'] ) ? false : self::$request['key'];
			$plugin     = empty( self::$request['plugin'] ) ? false : self::$request['plugin'];
			$theme      = empty( self::$request['theme'] ) ? false : self::$request['theme'];
			$tag        = empty( self::$request['tag'] ) ? 'master' : self::$request['tag'];
			$committish = empty( self::$request['committish'] ) ? false : self::$request['committish'];
			$branch     = empty( self::$request['branch'] ) ? false : self::$request['branch'];
			$override   = empty( self::$request['override'] ) ? false : self::$request['override'];
			$override   = false === $override ? false : true;
			$deprecated = 'Please update to using the new REST API endpoint. This is now deprecated.';
		}

		$args = compact( 'key', 'plugin', 'theme', 'tag', 'committish', 'branch', 'override', 'deprecated' );

		return $args;
	}

	/**
	 * Returns the current branch of the local repository referenced in the webhook.
	 *
	 * @param string|bool $plugin Plugin slug or false.
	 * @param string|bool $themes Theme slug or false.
	 *
	 * @return string $current_branch Default return is 'master'.
	 */
	private function get_local_branch( $plugin, $theme ) {
		$repo = false;
		if ( $plugin ) {
			$repos = Singleton::get_instance( 'Plugin', $this )->get_plugin_configs();
			$repo  = isset( $repos[ $plugin ] ) ? $repos[ $plugin ] : false;
		}
		if ( $theme ) {
			$repos = Singleton::get_instance( 'Theme', $this )->get_theme_configs();
			$repo  = isset( $repos[ $theme ] ) ? $repos[ $theme ] : false;
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

		/**
		 * Action hook after processing REST process.
		 *
		 * @since 8.6.0
		 *
		 * @param array $response
		 * @param int   $code     HTTP response.
		 */
		do_action( 'github_updater_post_rest_process_request', $response, $code );

		unset( $response['success'] );
		if ( 200 === $code ) {
			wp_die( wp_send_json_success( $response, $code ) );
		} else {
			wp_die( wp_send_json_error( $response, $code ) );
		}
	}
}
