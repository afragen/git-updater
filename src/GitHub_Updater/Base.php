<?php
/**
 * GitHub Updater
 *
 * @package   Fragen\GitHub_Updater
 * @author    Andy Fragen
 * @author    Gary Jones
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

namespace Fragen\GitHub_Updater;

use Fragen\Singleton,
	Fragen\GitHub_Updater\API\GitHub_API,
	Fragen\GitHub_Updater\API\Bitbucket_API,
	Fragen\GitHub_Updater\API\Bitbucket_Server_API,
	Fragen\GitHub_Updater\API\GitLab_API,
	Fragen\GitHub_Updater\API\Gitea_API,
	Fragen\GitHub_Updater\API\Language_Pack_API;


/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Base
 *
 * Update a WordPress plugin or theme from a Git-based repo.
 *
 * @package Fragen\GitHub_Updater
 * @author  Andy Fragen
 * @author  Gary Jones
 */
class Base {

	/**
	 * Store details of all repositories that are installed.
	 *
	 * @var \stdClass
	 */
	protected $config;

	/**
	 * Class Object for API.
	 *
	 * @var GitHub_API|Bitbucket_API|Bitbucket_Server_API|GitLab_API
	 */
	protected $repo_api;

	/**
	 * Variable for holding extra theme and plugin headers.
	 *
	 * @var array
	 */
	public static $extra_headers = array();

	/**
	 * Holds the values to be used in the fields callbacks.
	 *
	 * @var array
	 */
	public static $options;

	/**
	 * Holds the values for remote management settings.
	 *
	 * @var mixed
	 */
	protected static $options_remote;

	/**
	 * Holds the value for the Remote Management API key.
	 *
	 * @var
	 */
	protected static $api_key;

	/**
	 * Holds git server types.
	 *
	 * @var array
	 */
	protected static $git_servers = array(
		'github' => 'GitHub',
	);

	/**
	 * Holds extra repo header types.
	 *
	 * @var array
	 */
	protected static $extra_repo_headers = array(
		'languages' => 'Languages',
		'ci_job'    => 'CI Job',
	);

	/**
	 * Holds an array of installed git APIs.
	 *
	 * @var array
	 */
	protected static $installed_apis = array(
		'github_api' => true,
	);

	/**
	 * Holds boolean on whether or not the repo requires authentication.
	 * Used by class Settings and class Messages.
	 *
	 * @var array
	 */
	public static $auth_required = array(
		'github_private'    => false,
		'github_enterprise' => false,
		'bitbucket_private' => false,
		'bitbucket_server'  => false,
		'gitlab'            => false,
		'gitlab_private'    => false,
		'gitlab_enterprise' => false,
		'gitea'             => false,
		'gitea_private'     => false,
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->set_installed_apis();
	}

	/**
	 * Set boolean for installed API classes.
	 */
	protected function set_installed_apis() {
		if ( file_exists( __DIR__ . '/API/Bitbucket_API.php' ) ) {
			self::$installed_apis['bitbucket_api'] = true;
			self::$git_servers['bitbucket']        = 'Bitbucket';
		} else {
			self::$installed_apis['bitbucket_api'] = false;
		}

		self::$installed_apis['bitbucket_server_api'] = file_exists( __DIR__ . '/API/Bitbucket_Server_API.php' );

		if ( file_exists( __DIR__ . '/API/GitLab_API.php' ) ) {
			self::$installed_apis['gitlab_api'] = true;
			self::$git_servers['gitlab']        = 'GitLab';
		} else {
			self::$installed_apis['gitlab_api'] = false;
		}
		if ( file_exists( __DIR__ . '/API/Gitea_API.php' ) ) {
			self::$installed_apis['gitea_api'] = true;
			self::$git_servers['gitea']        = 'Gitea';
		} else {
			self::$installed_apis['gitea_api'] = false;
		}
	}

	/**
	 * Load site options.
	 */
	public function load_options() {
		self::$options        = get_site_option( 'github_updater', array() );
		self::$options_remote = get_site_option( 'github_updater_remote_management', array() );
		self::$api_key        = get_site_option( 'github_updater_api_key' );
	}

	/**
	 * Remove hooks after use.
	 */
	public function remove_hooks() {
		remove_filter( 'extra_theme_headers', array( &$this, 'add_headers' ) );
		remove_filter( 'extra_plugin_headers', array( &$this, 'add_headers' ) );
		remove_filter( 'http_request_args', array( 'Fragen\\GitHub_Updater\\API', 'http_request_args' ) );
		remove_filter( 'http_response', array( 'Fragen\\GitHub_Updater\\API', 'wp_update_response' ) );

		if ( $this->repo_api instanceof Bitbucket_API ) {
			Singleton::get_instance( 'Basic_Auth_Loader', $this, self::$options )->remove_authentication_hooks();
		}
	}

	/**
	 * Ensure api key is set.
	 */
	public function ensure_api_key_is_set() {
		if ( ! self::$api_key ) {
			update_site_option( 'github_updater_api_key', md5( uniqid( mt_rand(), true ) ) );
		}
	}

	/**
	 * Load Plugin, Theme, and Settings with correct capabiltiies and on selective admin pages.
	 *
	 * @return bool
	 */
	public function load() {
		if ( ! Singleton::get_instance( 'Init', $this )->can_update() ) {
			return false;
		}

		// Run GitHub Updater upgrade functions.
		$upgrade = new GHU_Upgrade();
		$upgrade->run();

		// Load plugin stylesheet.
		add_action( 'admin_enqueue_scripts', function() {
			wp_register_style( 'github-updater', plugins_url( basename( dirname( dirname( __DIR__ ) ) ) ) . '/css/github-updater.css' );
			wp_enqueue_style( 'github-updater' );
		} );

		// Ensure transient updated on plugins.php and themes.php pages.
		add_action( 'admin_init', array( &$this, 'admin_pages_update_transient' ) );

		if ( isset( $_POST['ghu_refresh_cache'] ) ) {
			/**
			 * Fires later in cycle when Refreshing Cache.
			 *
			 * @since 6.0.0
			 */
			do_action( 'ghu_refresh_transients' );
		}

		$this->get_meta_plugins();
		$this->get_meta_themes();
		if ( is_admin() && ! apply_filters( 'github_updater_hide_settings', false ) ) {
			Singleton::get_instance( 'Settings', $this )->run();
		}

		return true;
	}

	/**
	 * AJAX endpoint for REST updates.
	 */
	public function ajax_update() {
		Singleton::get_instance( 'Rest_Update', $this )->process_request();
	}

	/**
	 * Piggyback on built-in update function to get metadata.
	 */
	public function background_update() {
		add_action( 'wp_update_plugins', array( &$this, 'get_meta_plugins' ) );
		add_action( 'wp_update_themes', array( &$this, 'get_meta_themes' ) );
		add_action( 'wp_ajax_nopriv_ithemes_sync_request', array( &$this, 'get_meta_remote_management' ) );
		add_action( 'update_option_auto_updater.lock', array( &$this, 'get_meta_remote_management' ) );
		add_action( 'ghu_get_remote_plugin', array( &$this, 'run_cron_batch' ), 10, 1 );
		add_action( 'ghu_get_remote_theme', array( &$this, 'run_cron_batch' ), 10, 1 );
	}

	/**
	 * Performs actual plugin metadata fetching.
	 */
	public function get_meta_plugins() {
		if ( Singleton::get_instance( 'Init', $this )->can_update() ) {
			Singleton::get_instance( 'Plugin', $this )->get_remote_plugin_meta();
		}
	}

	/**
	 * Performs actual theme metadata fetching.
	 */
	public function get_meta_themes() {
		if ( Singleton::get_instance( 'Init', $this )->can_update() ) {
			Singleton::get_instance( 'Theme', $this )->get_remote_theme_meta();
		}
	}

	/**
	 * Calls $this->get_meta_plugins() and $this->get_meta_themes()
	 * for remote management services.
	 */
	public function get_meta_remote_management() {
		$this->get_meta_plugins();
		$this->get_meta_themes();
	}

	/**
	 * Allows developers to use 'github_updater_set_options' hook to set access tokens or other settings.
	 * Saves results of filter hook to self::$options.
	 *
	 * Hook requires return of associative element array.
	 * $key === repo-name and $value === token
	 * e.g.  array( 'repo-name' => 'access_token' );
	 *
	 */
	public function set_options_filter() {
		// Single plugin/theme should not be using both hooks.
		$config = apply_filters( 'github_updater_set_options', array() );
		if ( empty( $config ) ) {
			$config = function_exists( 'apply_filters_deprecated' ) ?
				apply_filters_deprecated( 'github_updater_token_distribution', array( null ), '6.1.0', 'github_updater_set_options' ) :
				apply_filters( 'github_updater_token_distribution', array() );
		}

		if ( ! empty( $config ) ) {
			$config        = Settings::sanitize( $config );
			self::$options = array_merge( get_site_option( 'github_updater' ), $config );
			update_site_option( 'github_updater', self::$options );
		}
	}

	/**
	 * Add extra headers to get_plugins() or wp_get_themes().
	 *
	 * @param $extra_headers
	 *
	 * @return array
	 */
	public function add_headers( $extra_headers ) {
		$ghu_extra_headers = array(
			'Requires WP'   => 'Requires WP',
			'Requires PHP'  => 'Requires PHP',
			'Release Asset' => 'Release Asset',
		);

		$uri_types = array( 'plugin' => ' Plugin URI', 'theme' => ' Theme URI' );

		foreach ( self::$git_servers as $server ) {
			foreach ( $uri_types as $uri_type ) {
				$ghu_extra_headers[ $server . $uri_type ] = $server . $uri_type;
			}
			foreach ( self::$extra_repo_headers as $header ) {
				$ghu_extra_headers[ $server . ' ' . $header ] = $server . ' ' . $header;
			}
		}

		self::$extra_headers = array_unique( array_merge( self::$extra_headers, $ghu_extra_headers ) );
		$extra_headers       = array_merge( (array) $extra_headers, $ghu_extra_headers );
		ksort( self::$extra_headers );

		return $extra_headers;
	}

	/**
	 * Set default values for plugin/theme.
	 *
	 * @param $type
	 */
	protected function set_defaults( $type ) {
		if ( ! isset( self::$options['branch_switch'] ) ) {
			self::$options['branch_switch'] = null;
		}

		if ( ! isset( $this->$type->repo ) ) {
			$this->$type       = new \stdClass();
			$this->$type->repo = null;
		} elseif ( ! isset( self::$options[ $this->$type->repo ] ) ) {
			self::$options[ $this->$type->repo ] = null;
			add_site_option( 'github_updater', self::$options );
		}

		$this->$type->remote_version       = '0.0.0';
		$this->$type->newest_tag           = '0.0.0';
		$this->$type->download_link        = null;
		$this->$type->tags                 = array();
		$this->$type->rollback             = array();
		$this->$type->branches             = array();
		$this->$type->requires             = null;
		$this->$type->tested               = null;
		$this->$type->donate_link          = null;
		$this->$type->contributors         = array();
		$this->$type->downloaded           = 0;
		$this->$type->last_updated         = null;
		$this->$type->rating               = 0;
		$this->$type->num_ratings          = 0;
		$this->$type->transient            = array();
		$this->$type->repo_meta            = array();
		$this->$type->watchers             = 0;
		$this->$type->forks                = 0;
		$this->$type->open_issues          = 0;
		$this->$type->requires_wp_version  = '4.6';
		$this->$type->requires_php_version = '5.3';
	}

	/**
	 * Runs on wp-cron job to get remote repo meta in background.
	 *
	 * @param array $batches
	 */
	public function run_cron_batch( array $batches ) {
		foreach ( $batches as $repo ) {
			$this->get_remote_repo_meta( $repo );
		}
	}

	/**
	 * Check to see if wp-cron/background updating has finished.
	 *
	 * @param null $repo
	 *
	 * @return bool true when waiting for background job to finish.
	 */
	protected function waiting_for_background_update( $repo = null ) {
		$caches = array();
		if ( null !== $repo ) {
			$cache = Singleton::get_instance( 'API_PseudoTrait', $this )->get_repo_cache( $repo->repo );

			return empty( $cache );
		}
		$repos = array_merge(
			Singleton::get_instance( 'Plugin', $this )->get_plugin_configs(),
			Singleton::get_instance( 'Theme', $this )->get_theme_configs()
		);
		foreach ( $repos as $git_repo ) {
			$caches[ $git_repo->repo ] = Singleton::get_instance( 'API_PseudoTrait', $this )->get_repo_cache( $git_repo->repo );
		}
		$waiting = array_filter( $caches, function( $e ) {
			return empty( $e );
		} );

		return ! empty( $waiting );
	}

	/**
	 * Checks if dupicate wp-cron event exists.
	 *
	 * @param string $event Name of wp-cron event.
	 *
	 * @return bool
	 */
	protected function is_duplicate_wp_cron_event( $event ) {
		$cron = _get_cron_array();
		foreach ( $cron as $timestamp => $cronhooks ) {
			if ( $event === key( $cronhooks ) ) {
				$this->is_cron_overdue( $cron, $timestamp );

				return true;
			}
		}

		return false;
	}

	/**
	 * Check to see if wp-cron event is overdue by 24 hours and report error message.
	 *
	 * @param $cron
	 * @param $timestamp
	 */
	private function is_cron_overdue( $cron, $timestamp ) {
		$overdue = ( ( time() - $timestamp ) / HOUR_IN_SECONDS ) > 24;
		if ( $overdue ) {
			$error_msg = esc_html__( 'There may be a problem with WP-Cron. A GitHub Updater WP-Cron event is overdue.', 'github-updater' );
			$error     = new \WP_Error( 'github_updater_cron_error', $error_msg );
			Singleton::get_instance( 'Messages', $this )->create_error_message( $error );
		}
	}

	/**
	 * Get remote repo meta data for plugins or themes.
	 * Calls remote APIs for data.
	 *
	 * @param $repo
	 *
	 * @return bool
	 */
	public function get_remote_repo_meta( $repo ) {
		$this->repo_api = null;
		$file           = 'style.css';
		if ( false !== stripos( $repo->type, 'plugin' ) ) {
			$file = basename( $repo->slug );
		}

		switch ( $repo->type ) {
			case 'github_plugin':
			case 'github_theme':
				$this->repo_api = new GitHub_API( $repo );
				break;
			case 'bitbucket_plugin':
			case 'bitbucket_theme':
				if ( $repo->enterprise_api ) {
					if ( self::$installed_apis['bitbucket_server_api'] ) {
						$this->repo_api = new Bitbucket_Server_API( $repo );
					}
				} elseif ( self::$installed_apis['bitbucket_api'] ) {
					$this->repo_api = new Bitbucket_API( $repo );
				}
				break;
			case 'gitlab_plugin':
			case 'gitlab_theme':
				if ( self::$installed_apis['gitlab_api'] ) {
					$this->repo_api = new GitLab_API( $repo );
				}
				break;
			case 'gitea_plugin':
			case 'gitea_theme':
				if ( self::$installed_apis['gitea_api'] ) {
					$this->repo_api = new Gitea_API( $repo );
				}
				break;
		}

		if ( null === $this->repo_api ) {
			return false;
		}

		$this->{$repo->type} = $repo;
		$this->set_defaults( $repo->type );

		if ( $this->repo_api->get_remote_info( $file ) ) {
			if ( ! self::is_wp_cli() ) {
				if ( ! apply_filters( 'github_updater_run_at_scale', false ) ) {
					$this->repo_api->get_repo_meta();
					$changelog = $this->get_changelog_filename( $repo->type );
					if ( $changelog ) {
						$this->repo_api->get_remote_changes( $changelog );
					}
					$this->repo_api->get_remote_readme();
				}
				if ( ! empty( self::$options['branch_switch'] ) ) {
					$this->repo_api->get_remote_branches();
				}
			}
			$this->repo_api->get_remote_tag();
			$repo->download_link = $this->repo_api->construct_download_link();
			$language_pack       = new Language_Pack( $repo, new Language_Pack_API( $repo ) );
			$language_pack->run();
		}

		$this->remove_hooks();

		return true;
	}

	/**
	 * Used for renaming of sources to ensure correct directory name.
	 *
	 * @since WordPress 4.4.0 The $hook_extra parameter became available.
	 *
	 * @param string                           $source
	 * @param string                           $remote_source
	 * @param \Plugin_Upgrader|\Theme_Upgrader $upgrader
	 * @param array                            $hook_extra
	 *
	 * @return string
	 */
	public function upgrader_source_selection( $source, $remote_source, $upgrader, $hook_extra = null ) {
		global $wp_filesystem;
		$slug            = null;
		$repo            = null;
		$new_source      = null;
		$upgrader_object = null;

		/*
		 * Rename plugins.
		 */
		if ( $upgrader instanceof \Plugin_Upgrader ) {
			$upgrader_object = Singleton::get_instance( 'Plugin', $this );
			if ( isset( $hook_extra['plugin'] ) ) {
				$slug       = dirname( $hook_extra['plugin'] );
				$new_source = trailingslashit( $remote_source ) . $slug;
			}
		}

		/*
		 * Rename themes.
		 */
		if ( $upgrader instanceof \Theme_Upgrader ) {
			$upgrader_object = Singleton::get_instance( 'Theme', $this );
			if ( isset( $hook_extra['theme'] ) ) {
				$slug       = $hook_extra['theme'];
				$new_source = trailingslashit( $remote_source ) . $slug;
			}
		}

		$repo = $this->get_repo_slugs( $slug, $upgrader_object );

		/*
		 * Not GitHub Updater plugin/theme.
		 */
		if ( ! isset( $_POST['github_updater_repo'] ) && empty( $repo ) ) {
			return $source;
		}

		/*
		 * Remote install source.
		 */
		if ( empty( $repo ) && isset( self::$options['github_updater_install_repo'] ) ) {
			$repo['repo'] = self::$options['github_updater_install_repo'];
			$new_source   = trailingslashit( $remote_source ) . self::$options['github_updater_install_repo'];
		}

		Singleton::get_instance( 'Branch', $this )->set_branch_on_switch( $slug );

		// Delete get_plugins() and wp_get_themes() cache.
		delete_site_option( 'ghu-' . md5( 'repos' ) );

		$new_source = $this->fix_misnamed_directory( $new_source, $remote_source, $upgrader_object, $slug );
		$new_source = $this->fix_gitlab_release_asset_directory( $new_source, $remote_source, $upgrader_object, $slug );

		$wp_filesystem->move( $source, $new_source );

		return trailingslashit( $new_source );
	}

	/**
	 * Correctly rename an initially misnamed directory.
	 * This usually occurs when initial installation not using GitHub Updater.
	 * May cause plugin/theme deactivation.
	 *
	 * @param string       $new_source
	 * @param string       $remote_source
	 * @param Plugin|Theme $upgrader_object
	 * @param string       $slug
	 *
	 * @return string $new_source
	 */
	private function fix_misnamed_directory( $new_source, $remote_source, $upgrader_object, $slug ) {
		if ( ! array_key_exists( $slug, (array) $upgrader_object->config ) &&
		     ! isset( self::$options['github_updater_install_repo'] )
		) {
			if ( $upgrader_object instanceof Plugin ) {
				foreach ( (array) $upgrader_object->config as $plugin ) {
					if ( $slug === dirname( $plugin->slug ) ) {
						$slug       = $plugin->repo;
						$new_source = trailingslashit( $remote_source ) . $slug;
						break;
					}
				}
			}
			if ( $upgrader_object instanceof Theme ) {
				foreach ( (array) $upgrader_object->config as $theme ) {
					if ( $slug === $theme->repo ) {
						$new_source = trailingslashit( $remote_source ) . $slug;
						break;
					}
				}
			}
		}

		return $new_source;
	}

	/**
	 * Renaming if using a GitLab Release Asset.
	 * It has a different download directory structure.
	 *
	 * @param string       $new_source
	 * @param string       $remote_source
	 * @param Plugin|Theme $upgrader_object
	 * @param string       $slug
	 *
	 * @return string $new_source
	 */
	private function fix_gitlab_release_asset_directory( $new_source, $remote_source, $upgrader_object, $slug ) {
		if ( ( isset( $upgrader_object->config[ $slug ]->release_asset ) &&
		       $upgrader_object->config[ $slug ]->release_asset ) &&
		     ! empty( $upgrader_object->config[ $slug ]->ci_job )
		) {
			$new_source = trailingslashit( dirname( $remote_source ) ) . $slug;
			add_filter( 'upgrader_post_install', array( &$this, 'upgrader_post_install' ), 10, 3 );
		}

		return $new_source;
	}

	/**
	 * Delete $source when updating from GitLab Release Asset.
	 *
	 * @param bool  $true
	 * @param array $hook_extra
	 * @param array $result
	 *
	 * @return mixed
	 */
	public function upgrader_post_install( $true, $hook_extra, $result ) {
		global $wp_filesystem;

		$wp_filesystem->delete( $result['source'], true );
		remove_filter( 'upgrader_post_install', array( &$this, 'upgrader_post_install' ) );

		return $result;
	}

	/**
	 * Set array with normal and extended repo names.
	 * Fix name even if installed without renaming originally, eg <repo>-master
	 *
	 * @TODO remove extended naming stuff
	 *
	 * @param string            $slug
	 * @param Base|Plugin|Theme $upgrader_object
	 *
	 * @return array
	 */
	protected function get_repo_slugs( $slug, $upgrader_object = null ) {
		$arr    = array();
		$rename = explode( '-', $slug );
		array_pop( $rename );
		$rename = implode( '-', $rename );

		if ( null === $upgrader_object ) {
			$upgrader_object = $this;
		}

		$rename = isset( $upgrader_object->config[ $slug ] ) ? $slug : $rename;
		foreach ( (array) $upgrader_object->config as $repo ) {
			if ( ( $slug === $repo->repo ||
			       ( isset( $repo->extended_repo ) && $slug === $repo->extended_repo ) ) ||
			     ( $rename === $repo->owner . '-' . $repo->repo || $rename === $repo->repo )
			) {
				$arr['repo']          = $repo->repo;
				$arr['extended_repo'] = isset( $repo->extended_repo ) ? $repo->extended_repo : null;
				break;
			}
		}

		return $arr;
	}

	/**
	 * Take remote file contents as string and parse headers.
	 *
	 * @param $contents
	 * @param $type
	 *
	 * @return array
	 */
	public function get_file_headers( $contents, $type ) {
		$all_headers            = array();
		$default_plugin_headers = array(
			'Name'        => 'Plugin Name',
			'PluginURI'   => 'Plugin URI',
			'Version'     => 'Version',
			'Description' => 'Description',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'TextDomain'  => 'Text Domain',
			'DomainPath'  => 'Domain Path',
			'Network'     => 'Network',
		);

		$default_theme_headers = array(
			'Name'        => 'Theme Name',
			'ThemeURI'    => 'Theme URI',
			'Description' => 'Description',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'Version'     => 'Version',
			'Template'    => 'Template',
			'Status'      => 'Status',
			'Tags'        => 'Tags',
			'TextDomain'  => 'Text Domain',
			'DomainPath'  => 'Domain Path',
		);

		if ( false !== strpos( $type, 'plugin' ) ) {
			$all_headers = $default_plugin_headers;
		}

		if ( false !== strpos( $type, 'theme' ) ) {
			$all_headers = $default_theme_headers;
		}

		/*
		 * Make sure we catch CR-only line endings.
		 */
		$file_data = str_replace( "\r", "\n", $contents );

		/*
		 * Merge extra headers and default headers.
		 */
		$all_headers = array_merge( self::$extra_headers, $all_headers );
		$all_headers = array_unique( $all_headers );

		foreach ( $all_headers as $field => $regex ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $file_data, $match ) && $match[1] ) {
				$all_headers[ $field ] = _cleanup_header_comment( $match[1] );
			} else {
				$all_headers[ $field ] = '';
			}
		}

		// Reduce array to only headers with data.
		$all_headers = array_filter( $all_headers,
			function( $e ) {
				return ! empty( $e );
			} );

		return $all_headers;
	}

	/**
	 * Get filename of changelog and return.
	 *
	 * @param $type
	 *
	 * @return bool|string
	 */
	protected function get_changelog_filename( $type ) {
		$changelogs  = array( 'CHANGES.md', 'CHANGELOG.md', 'changes.md', 'changelog.md' );
		$changes     = null;
		$local_files = null;

		if ( is_dir( $this->$type->local_path ) ) {
			$local_files = scandir( $this->$type->local_path, 0 );
		}

		$changes = array_intersect( (array) $local_files, $changelogs );
		$changes = array_pop( $changes );

		if ( ! empty( $changes ) ) {
			return $changes;
		}

		return false;
	}

	/**
	 * Function to check if plugin or theme object is able to be updated.
	 *
	 * @param $type
	 *
	 * @return bool
	 */
	public function can_update_repo( $type ) {
		global $wp_version;

		if ( isset( $type->remote_version, $type->requires_php_version, $type->requires_php_version ) ) {
			$remote_is_newer = version_compare( $type->remote_version, $type->local_version, '>' );
			$wp_version_ok   = version_compare( $wp_version, $type->requires_wp_version, '>=' );
			$php_version_ok  = version_compare( PHP_VERSION, $type->requires_php_version, '>=' );
		} else {
			return false;
		}

		return $remote_is_newer && $wp_version_ok && $php_version_ok;
	}

	/**
	 * Parse URI param returning array of parts.
	 *
	 * @param string $repo_header
	 *
	 * @return array $header
	 */
	protected function parse_header_uri( $repo_header ) {
		$header_parts         = parse_url( $repo_header );
		$header_path          = pathinfo( $header_parts['path'] );
		$header['scheme']     = isset( $header_parts['scheme'] ) ? $header_parts['scheme'] : null;
		$header['host']       = isset( $header_parts['host'] ) ? $header_parts['host'] : null;
		$header['owner']      = trim( $header_path['dirname'], '/' );
		$header['repo']       = $header_path['filename'];
		$header['owner_repo'] = implode( '/', array( $header['owner'], $header['repo'] ) );
		$header['base_uri']   = str_replace( $header_parts['path'], '', $repo_header );
		$header['uri']        = isset( $header['scheme'] ) ? trim( $repo_header, '/' ) : null;

		$header = Settings::sanitize( $header );

		return $header;
	}

	/**
	 * Create repo parts.
	 *
	 * @param $repo
	 * @param $type
	 *
	 * @return mixed
	 */
	protected function get_repo_parts( $repo, $type ) {
		$arr['bool']    = false;
		$pattern        = '/' . strtolower( $repo ) . '_/';
		$type           = preg_replace( $pattern, '', $type );
		$repo_types     = array(
			'GitHub'    => 'github_' . $type,
			'Bitbucket' => 'bitbucket_' . $type,
			'GitLab'    => 'gitlab_' . $type,
			'Gitea'     => 'gitea_' . $type,
		);
		$repo_base_uris = array(
			'GitHub'    => 'https://github.com/',
			'Bitbucket' => 'https://bitbucket.org/',
			'GitLab'    => 'https://gitlab.com/',
			'Gitea'     => '',
		);

		if ( array_key_exists( $repo, $repo_types ) ) {
			$arr['type']       = $repo_types[ $repo ];
			$arr['git_server'] = strtolower( $repo );
			$arr['base_uri']   = $repo_base_uris[ $repo ];
			$arr['bool']       = true;
			foreach ( self::$extra_repo_headers as $key => $value ) {
				$arr[ $key ] = $repo . ' ' . $value;
			}
		}

		return $arr;
	}

	/**
	 * Delete all `ghu-` prefixed data from options table.
	 *
	 * @return bool
	 */
	public function delete_all_cached_data() {
		global $wpdb;

		$table         = is_multisite() ? $wpdb->base_prefix . 'sitemeta' : $wpdb->base_prefix . 'options';
		$column        = is_multisite() ? 'meta_key' : 'option_name';
		$delete_string = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s LIMIT 1000';

		$wpdb->query( $wpdb->prepare( $delete_string, array( '%ghu-%' ) ) );

		$this->force_run_cron_job();

		return true;
	}

	/**
	 * Force wp-cron.php to run.
	 */
	private function force_run_cron_job() {
		$doing_wp_cron = sprintf( '%.22F', microtime( true ) );
		$cron_request  = array(
			'url'  => site_url( 'wp-cron.php?doing_wp_cron=' . $doing_wp_cron ),
			'args' => array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', true ),
			),
		);

		wp_remote_post( $cron_request['url'], $cron_request['args'] );
	}

	/**
	 * Return correct update row opening and closing tags for Shiny Updates.
	 *
	 * @param      $repo_name
	 * @param      $type
	 * @param bool $branch_switcher
	 *
	 * @return array
	 */
	protected function update_row_enclosure( $repo_name, $type, $branch_switcher = false ) {
		global $wp_version;
		$wp_list_table = _get_list_table( 'WP_MS_Themes_List_Table' );
		$repo_base     = $repo_name;
		$shiny_classes = ' notice inline notice-warning notice-alt';

		if ( 'plugin' === $type ) {
			$repo_base = dirname( $repo_name );
		}

		$open = '<tr class="plugin-update-tr" data-slug="' . esc_attr( $repo_base ) . '" data-plugin="' . esc_attr( $repo_name ) . '">
		<td colspan="' . $wp_list_table->get_column_count() . '" class="plugin-update colspanchange">
		<div class="update-message">';

		$enclosure = array(
			'open'  => $open,
			'close' => '</div></td></tr>',
		);

		if ( version_compare( $wp_version, '4.6', '>=' ) ) {
			$open_p  = '<p>';
			$close_p = '</p>';
			if ( $branch_switcher ) {
				$open_p  = '';
				$close_p = '';
			}
			$enclosure = array(
				'open'  => substr_replace( $open, $shiny_classes, - 2, 0 ) . $open_p,
				'close' => $close_p . '</div></td></tr>',
			);
		}

		return $enclosure;
	}

	/**
	 * Make branch switch row.
	 *
	 * @param array $data Parameters for creating branch switching row.
	 *
	 * @return void
	 */
	protected function make_branch_switch_row( $data ) {
		$rollback = empty( $this->config[ $data['slug'] ]->rollback ) ? array() : $this->config[ $data['slug'] ]->rollback;

		printf( esc_html__( 'Current branch is `%1$s`, try %2$sanother version%3$s', 'github-updater' ),
			$data['branch'],
			'<a href="#" onclick="jQuery(\'#' . $data['id'] . '\').toggle();return false;">',
			'</a>.'
		);

		print( '<ul id="' . $data['id'] . '" style="display:none; width: 100%;">' );

		if ( null !== $data['branches'] ) {
			foreach ( array_keys( $data['branches'] ) as $branch ) {
				printf( '<li><a href="%s%s" aria-label="' . esc_html__( 'Switch to branch ', 'github-updater' ) . $branch . '">%s</a></li>',
					$data['nonced_update_url'],
					'&rollback=' . urlencode( $branch ),
					esc_attr( $branch )
				);
			}
		}

		if ( ! empty( $rollback ) ) {
			$rollback = array_keys( $rollback );
			usort( $rollback, 'version_compare' );
			krsort( $rollback );
			$rollback = array_splice( $rollback, 0, 4, true );
			array_shift( $rollback ); // Dump current tag.
			foreach ( $rollback as $tag ) {
				printf( '<li><a href="%s%s" aria-label="' . esc_html__( 'Switch to release ', 'github-updater' ) . $tag . '">%s</a></li>',
					$data['nonced_update_url'],
					'&rollback=' . urlencode( $tag ),
					esc_attr( $tag )
				);
			}
		}
		if ( empty( $rollback ) ) {
			esc_html_e( 'No previous tags to rollback to.', 'github-updater' );
		}

		print( '</ul>' );
	}

	/**
	 * Generate update URL.
	 *
	 * @param string $type ( plugin or theme )
	 * @param string $action
	 * @param string $repo_name
	 *
	 * @return string
	 */
	protected function get_update_url( $type, $action, $repo_name ) {
		$update_url = esc_attr(
			add_query_arg(
				array(
					'action' => $action,
					$type    => urlencode( $repo_name ),
				),
				self_admin_url( 'update.php' )
			) );

		return $update_url;
	}

	/**
	 * Test if rollback and then run `set_rollback_transient`.
	 *
	 * @uses filter hook 'wp_get_update_data'
	 *
	 * @param mixed $update_data
	 *
	 * @return mixed $update_data
	 */
	public function set_rollback( $update_data ) {
		if ( empty( $_GET['rollback'] ) && ! isset( $_GET['action'] ) ) {
			return $update_data;
		}

		if ( isset( $_GET['plugin'] ) && 'upgrade-plugin' === $_GET['action'] ) {
			$slug = dirname( $_GET['plugin'] );
			$type = 'plugin';

			// For extended naming @TODO remove extended naming stuff
			$repo = $this->get_repo_slugs( $slug );
			$slug = ! empty( $repo ) ? $repo['repo'] : $slug;
		}

		if ( isset( $_GET['theme'] ) && 'upgrade-theme' === $_GET['action'] ) {
			$slug = $_GET['theme'];
			$type = 'theme';
		}

		if ( ! empty( $slug ) && array_key_exists( $slug, (array) $this->config ) ) {
			$repo = $this->config[ $slug ];
			$this->set_rollback_transient( $type, $repo, true );
		}

		return $update_data;
	}

	/**
	 * Update transient for rollback or branch switch.
	 *
	 * @param string    $type          plugin|theme
	 * @param \stdClass $repo
	 * @param bool      $set_transient Default false, if true then set update transient.
	 *
	 * @return array $rollback Rollback transient.
	 */
	protected function set_rollback_transient( $type, $repo, $set_transient = false ) {
		switch ( $repo->type ) {
			case 'github_plugin':
			case 'github_theme':
				$this->repo_api = new GitHub_API( $repo );
				break;
			case 'bitbucket_plugin':
			case 'bitbucket_theme':
				if ( ! empty( $repo->enterprise ) ) {
					$this->repo_api = new Bitbucket_Server_API( $repo );
				} else {
					$this->repo_api = new Bitbucket_API( $repo );
				}
				break;
			case 'gitlab_plugin':
			case 'gitlab_theme':
				$this->repo_api = new GitLab_API( $repo );
				break;
			case 'gitea_plugin':
			case 'gitea_theme':
				$this->repo_api = new Gitea_API( $repo );
				break;
		}

		$this->tag = isset( $_GET['rollback'] ) ? $_GET['rollback'] : null;
		$slug      = 'plugin' === $type ? $repo->slug : $repo->repo;
		$rollback  = array(
			$type         => $slug,
			'new_version' => $this->tag,
			'url'         => $repo->uri,
			'package'     => $this->repo_api->construct_download_link( false, $this->tag ),
			'branch'      => $repo->branch,
			'branches'    => $repo->branches,
			'type'        => $repo->type,
		);

		if ( 'plugin' === $type ) {
			$rollback['slug'] = $repo->repo;
			$rollback         = (object) $rollback;
		}

		if ( $set_transient ) {
			$transient                  = 'update_' . $type . 's';
			$current                    = get_site_transient( $transient );
			$current->response[ $slug ] = $rollback;
			set_site_transient( $transient, $current );
		}

		return $rollback;
	}

	/**
	 * Ensure update transient is update to date on admin pages.
	 */
	public function admin_pages_update_transient() {
		global $pagenow;

		$admin_pages   = array( 'plugins.php', 'themes.php', 'update-core.php' );
		$is_admin_page = in_array( $pagenow, $admin_pages, true ) ? true : false;
		$transient     = 'update_' . rtrim( $pagenow, '.php' );
		$transient     = 'update_update-core' === $transient ? 'update_core' : $transient;

		if ( $is_admin_page ) {
			$this->make_update_transient_current( $transient );
		}

		remove_filter( 'admin_init', array( &$this, 'admin_pages_update_transient' ) );
	}

	/**
	 * Checks user capabilities then updates the update transient to ensure
	 * our repositories display update notices correctly.
	 *
	 * @param string $transient ( 'update_plugins' | 'update_themes' | 'update_core' )
	 */
	public function make_update_transient_current( $transient ) {
		if ( ! in_array( $transient, array( 'update_plugins', 'update_themes', 'update_core' ), true ) ) {
			return;
		}

		if ( current_user_can( $transient ) ) {
			$current = get_site_transient( $transient );
			switch ( $transient ) {
				case 'update_plugins':
					$this->get_meta_plugins();
					$current = Singleton::get_instance( 'Plugin', $this )->pre_set_site_transient_update_plugins( $current );
					break;
				case 'update_themes':
					$this->get_meta_themes();
					$current = Singleton::get_instance( 'Theme', $this )->pre_set_site_transient_update_themes( $current );
					break;
				case 'update_core':
					$this->get_meta_plugins();
					$current = Singleton::get_instance( 'Plugin', $this )->pre_set_site_transient_update_plugins( $current );
					$this->get_meta_themes();
					$current = Singleton::get_instance( 'Theme', $this )->pre_set_site_transient_update_themes( $current );
					break;
			}
			set_site_transient( $transient, $current );
		}
	}

	/**
	 * Parse Enterprise, Languages, Release Asset, and CI Job headers for plugins and themes.
	 *
	 * @param array           $header
	 * @param array|\WP_Theme $headers
	 * @param array           $header_parts
	 * @param array           $repo_parts
	 *
	 * @return array $header
	 */
	protected function parse_extra_headers( $header, $headers, $header_parts, $repo_parts ) {
		$hosted_domains = array( 'github.com', 'bitbucket.org', 'gitlab.com' );
		$theme          = null;

		$header['enterprise_uri'] = null;
		$header['enterprise_api'] = null;
		$header['languages']      = null;
		$header['ci_job']         = false;
		$header['release_asset']  = false;

		if ( ! empty( $header['host'] ) && ! in_array( $header['host'], $hosted_domains, true ) ) {
			$header['enterprise_uri'] = $header['base_uri'];
			$header['enterprise_uri'] = trim( $header['enterprise_uri'], '/' );
			switch ( $header_parts[0] ) {
				case 'GitHub':
				case 'GitLab':
					$header['enterprise_api'] = $header['enterprise_uri'] . '/api/v3';
					break;
				case 'Bitbucket':
					$header['enterprise_api'] = $header['enterprise_uri'] . '/rest/api';
					break;
			}
		}

		if ( $headers instanceof \WP_Theme ) {
			$theme                    = $headers;
			$headers                  = array();
			$headers['Release Asset'] = '';
			$header['release_asset']  = 'true' === $theme->get( 'Release Asset' );
		}

		$self_hosted_parts = array_keys( self::$extra_repo_headers );
		foreach ( $self_hosted_parts as $part ) {
			if ( $theme instanceof \WP_Theme ) {
				$headers[ $repo_parts[ $part ] ] = $theme->get( $repo_parts[ $part ] );
			}
			if ( array_key_exists( $repo_parts[ $part ], $headers ) &&
			     ! empty( $headers[ $repo_parts[ $part ] ] )
			) {
				switch ( $part ) {
					case 'languages':
						$header['languages'] = $headers[ $repo_parts[ $part ] ];
						break;
					case 'ci_job':
						$header['ci_job'] = $headers[ $repo_parts[ $part ] ];
						break;
				}
			}
		}
		$header['release_asset'] = ! $header['release_asset'] ? 'true' === $headers['Release Asset'] : $header['release_asset'];

		return $header;
	}

	/**
	 * Return an array of the running git servers.
	 *
	 * @access public
	 * @return array $gits
	 */
	public function get_running_git_servers() {
		$plugins = Singleton::get_instance( 'Plugin', $this )->get_plugin_configs();
		$themes  = Singleton::get_instance( 'Theme', $this )->get_theme_configs();

		$repos = array_merge( $plugins, $themes );
		$gits  = array_map( function( $e ) {
			if ( ! empty( $e->enterprise ) && false !== stripos( $e->type, 'bitbucket' ) ) {
				return 'bbserver';
			}

			return $e->type;
		}, $repos );

		$gits = array_unique( array_values( $gits ) );

		$gits = array_map( function( $e ) {
			$e = explode( '_', $e );

			return $e[0];
		}, $gits );


		return array_unique( $gits );
	}

	/**
	 * Checks to see if a heartbeat is resulting in activity.
	 *
	 * @return bool
	 */
	protected static function is_heartbeat() {
		return ( isset( $_POST['action'] ) && 'heartbeat' === $_POST['action'] );
	}

	/**
	 * Checks to see if DOING_AJAX.
	 *
	 * @return bool
	 */
	protected static function is_doing_ajax() {
		return ( defined( 'DOING_AJAX' ) && DOING_AJAX );
	}

	/**
	 * Checks to see if WP_CLI.
	 *
	 * @return bool
	 */
	protected static function is_wp_cli() {
		return ( defined( 'WP_CLI' ) && WP_CLI );
	}

	/**
	 * Is this a private repo with a token/checked or needing token/checked?
	 * Test for whether remote_version is set ( default = 0.0.0 ) or
	 * a repo option is set/not empty.
	 *
	 * @param \stdClass $repo
	 *
	 * @return bool
	 */
	protected function is_private( $repo ) {
		if ( ! isset( $repo->remote_version ) && ! self::is_doing_ajax() ) {
			return true;
		}
		if ( isset( $repo->remote_version ) && ! self::is_doing_ajax() ) {
			return ( '0.0.0' === $repo->remote_version ) || ! empty( self::$options[ $repo->repo ] );
		}

		return false;
	}

	/**
	 * Is override dot org option active?
	 *
	 * @return bool
	 */
	public function is_override_dot_org() {
		return ( defined( 'GITHUB_UPDATER_OVERRIDE_DOT_ORG' ) && GITHUB_UPDATER_OVERRIDE_DOT_ORG )
		       || ( defined( 'GITHUB_UPDATER_EXTENDED_NAMING' ) && GITHUB_UPDATER_EXTENDED_NAMING );
	}

}
