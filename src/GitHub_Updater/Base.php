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
	 * @var object
	 */
	protected $config;

	/**
	 * Class Object for API.
	 *
	 * @var object
	 */
	protected $repo_api;

	/**
	 * Class Object for Language Packs.
	 *
	 * @var object
	 */
	protected $languages;

	/**
	 * Variable for setting update transient hours.
	 *
	 * @var integer
	 */
	protected static $hours;

	/**
	 * Variable for holding extra theme and plugin headers.
	 *
	 * @var array
	 */
	protected static $extra_headers = array();

	/**
	 * Holds the values to be used in the fields callbacks.
	 *
	 * @var array
	 */
	protected static $options;

	/**
	 * Holds the values for remote management settings.
	 *
	 * @var mixed
	 */
	protected static $options_remote;

	/**
	 * Holds HTTP error code from API call.
	 *
	 * @var array ( $this->type-repo => $code )
	 */
	protected static $error_code = array();

	/**
	 * Holds git server types.
	 *
	 * @var array
	 */
	protected static $git_servers = array(
		'github'    => 'GitHub',
		'bitbucket' => 'Bitbucket',
		'gitlab'    => 'GitLab',
	);

	/**
	 * Holds extra repo header types.
	 *
	 * @var array
	 */
	protected static $extra_repo_headers = array(
		'branch'    => 'Branch',
		'languages' => 'Languages',
		'ci_job'    => 'CI Job',
	);

	/**
	 * Holds boolean on whether or not the repo requires authentication.
	 * Used by class Settings and class Messages.
	 *
	 * @var array
	 */
	protected static $auth_required = array(
		'github_private'    => false,
		'github_enterprise' => false,
		'bitbucket_private' => false,
		'bitbucket_server'  => false,
		'gitlab'            => false,
		'gitlab_private'    => false,
		'gitlab_enterprise' => false,
	);

	/**
	 * Variable to hold boolean to load remote meta.
	 * Checks user privileges and when to load.
	 *
	 * @var bool
	 */
	protected static $load_repo_meta;

	/**
	 * Constructor.
	 * Loads options to private static variable.
	 */
	public function __construct() {
		if ( isset( $_POST['ghu_refresh_cache'] ) && ! ( $this instanceof Messages ) ) {
			$this->delete_all_cached_data();
		}

		$this->load_hooks();

		if ( self::is_wp_cli() ) {
			include_once __DIR__ . '/CLI.php';
			include_once __DIR__ . '/CLI_Integration.php';
		}
	}

	/**
	 * Load site options.
	 */
	protected function load_options() {
		self::$options        = get_site_option( 'github_updater', array() );
		self::$options_remote = get_site_option( 'github_updater_remote_management', array() );
	}

	/**
	 * Load relevant action/filter hooks.
	 * Use 'init' hook for user capabilities.
	 */
	protected function load_hooks() {
		add_action( 'init', array( &$this, 'init' ) );
		add_action( 'init', array( &$this, 'background_update' ) );
		add_action( 'init', array( &$this, 'set_options_filter' ) );
		add_action( 'wp_ajax_github-updater-update', array( &$this, 'ajax_update' ) );
		add_action( 'wp_ajax_nopriv_github-updater-update', array( &$this, 'ajax_update' ) );

		/*
		 * Load hook for shiny updates Basic Authentication headers.
		 */
		if ( self::is_doing_ajax() ) {
			Basic_Auth_Loader::instance( self::$options )->load_authentication_hooks();
		}

		add_filter( 'extra_theme_headers', array( &$this, 'add_headers' ) );
		add_filter( 'extra_plugin_headers', array( &$this, 'add_headers' ) );
		add_filter( 'upgrader_source_selection', array( &$this, 'upgrader_source_selection' ), 10, 4 );

		/*
		 * The following hook needed to ensure transient is reset correctly after
		 * shiny updates.
		 */
		add_filter( 'http_response', array( 'Fragen\\GitHub_Updater\\API', 'wp_update_response' ), 10, 3 );
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
			Basic_Auth_Loader::instance( self::$options )->remove_authentication_hooks();
		}
	}

	/**
	 * Ensure api key is set.
	 */
	public function ensure_api_key_is_set() {
		$api_key = get_site_option( 'github_updater_api_key' );
		if ( ! $api_key ) {
			update_site_option( 'github_updater_api_key', md5( uniqid( mt_rand(), true ) ) );
		}
	}

	/**
	 * Instantiate Plugin, Theme, and Settings for proper user capabilities.
	 *
	 * @return bool
	 */
	public function init() {
		global $pagenow;

		$load_multisite       = ( is_network_admin() && current_user_can( 'manage_network' ) );
		$load_single_site     = ( ! is_multisite() && current_user_can( 'manage_options' ) );
		self::$load_repo_meta = $load_multisite || $load_single_site;
		$this->load_options();

		// Set $force_meta_update = true on appropriate admin pages.
		$force_meta_update = false;
		$admin_pages       = array(
			'plugins.php',
			'plugin-install.php',
			'themes.php',
			'theme-install.php',
			'update-core.php',
			'update.php',
			'options-general.php',
			'settings.php',
		);

		foreach ( array_keys( Settings::$remote_management ) as $key ) {
			// Remote management only needs to be active for admin pages.
			if ( is_admin() && ! empty( self::$options_remote[ $key ] ) ) {
				$admin_pages = array_merge( $admin_pages, array( 'index.php', 'admin-ajax.php' ) );
			}
		}

		if ( in_array( $pagenow, array_unique( $admin_pages ), true ) ) {
			$force_meta_update = true;

			// Load plugin stylesheet.
			add_action( 'admin_enqueue_scripts', function() {
				wp_register_style( 'github-updater', plugins_url( basename( dirname( dirname( __DIR__ ) ) ) ) . '/css/github-updater.css' );
				wp_enqueue_style( 'github-updater' );
			} );

			// Run GitHub Updater upgrade functions.
			$upgrade = new GHU_Upgrade();
			$upgrade->run();

			// Ensure transient updated on plugins.php and themes.php pages.
			add_action( 'admin_init', array( &$this, 'admin_pages_update_transient' ) );
		}

		if ( isset( $_POST['ghu_refresh_cache'] ) ) {
			/**
			 * Fires later in cycle when Refreshing Cache.
			 *
			 * @since 6.0.0
			 */
			do_action( 'ghu_refresh_transients' );
		}

		if ( $force_meta_update ) {
			$this->forced_meta_update_plugins();
		}
		if ( $force_meta_update ) {
			$this->forced_meta_update_themes();
		}
		if ( is_admin() && self::$load_repo_meta &&
		     ! apply_filters( 'github_updater_hide_settings', false )
		) {
			Settings::instance();
		}

		return true;
	}

	/**
	 * AJAX endpoint for REST updates.
	 */
	public function ajax_update() {
		$this->load_options();
		$rest_update = new Rest_Update();
		$rest_update->process_request();
	}

	/**
	 * Piggyback on built-in update function to get metadata.
	 */
	public function background_update() {
		add_action( 'wp_update_plugins', array( &$this, 'forced_meta_update_plugins' ) );
		add_action( 'wp_update_themes', array( &$this, 'forced_meta_update_themes' ) );
		add_action( 'wp_ajax_nopriv_ithemes_sync_request', array( &$this, 'forced_meta_update_remote_management' ) );
		add_action( 'update_option_auto_updater.lock', array( &$this, 'forced_meta_update_remote_management' ) );
	}

	/**
	 * Performs actual plugin metadata fetching.
	 *
	 * @param bool $true Only used from API::wp_update_response()
	 */
	public function forced_meta_update_plugins( $true = false ) {
		if ( self::$load_repo_meta || $true ) {
			$this->load_options();
			Plugin::instance()->get_remote_plugin_meta();
		}
	}

	/**
	 * Performs actual theme metadata fetching.
	 *
	 * @param bool $true Only used from API::wp_update_response()
	 */
	public function forced_meta_update_themes( $true = false ) {
		if ( self::$load_repo_meta || $true ) {
			$this->load_options();
			Theme::instance()->get_remote_theme_meta();
		}
	}

	/**
	 * Calls $this->forced_meta_update_plugins() and $this->forced_meta_update_themes()
	 * for remote management services.
	 */
	public function forced_meta_update_remote_management() {
		$this->forced_meta_update_plugins( true );
		$this->forced_meta_update_themes( true );
	}

	/**
	 * Allows developers to use 'github_updater_set_options' hook to set access tokens or other settings.
	 * Saves results of filter hook to self::$options.
	 *
	 * Hook requires return of associative element array.
	 * $key === repo-name and $value === token
	 * e.g.  array( 'repo-name' => 'access_token' );
	 *
	 * @TODO Set `Requires WP: 4.6` and only use current filter and apply_filters_deprecated
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

		$current_filter = current_filter();
		if ( 'extra_plugin_headers' === $current_filter ) {
			$uri_type = ' Plugin URI';
		} elseif ( 'extra_theme_headers' === $current_filter ) {
			$uri_type = ' Theme URI';
		}

		foreach ( self::$git_servers as $server ) {
			$ghu_extra_headers[ $server . $uri_type ] = $server . $uri_type;
			foreach ( self::$extra_repo_headers as $header ) {
				$ghu_extra_headers[ $server . ' ' . $header ] = $server . ' ' . $header;
			}
		}

		self::$extra_headers = array_unique( array_merge( self::$extra_headers, $ghu_extra_headers ) );
		$extra_headers       = array_merge( (array) $extra_headers, (array) $ghu_extra_headers );
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
		$this->$type->requires_wp_version  = '4.4';
		$this->$type->requires_php_version = '5.3';
		$this->$type->release_asset        = false;
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
		self::$hours    = 6 + mt_rand( 0, 12 );
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
					$this->repo_api = new Bitbucket_Server_API( $repo );
				} else {
					$this->repo_api = new Bitbucket_API( $repo );
				}
				break;
			case 'gitlab_plugin':
			case 'gitlab_theme':
				$this->repo_api = new GitLab_API( $repo );
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
			$this->languages     = new Language_Pack( $repo, new Language_Pack_API( $repo ) );
		}

		$this->remove_hooks();

		return true;
	}

	/**
	 * Used for renaming of sources to ensure correct directory name.
	 *
	 * @since WordPress 4.4.0 The $hook_extra parameter became available.
	 *
	 * @param string $source
	 * @param string $remote_source
	 * @param object $upgrader
	 * @param array  $hook_extra
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
			$upgrader_object = Plugin::instance();
			if ( isset( $hook_extra['plugin'] ) ) {
				$slug       = dirname( $hook_extra['plugin'] );
				$new_source = trailingslashit( $remote_source ) . $slug;
			}
		}

		/*
		 * Rename themes.
		 */
		if ( $upgrader instanceof \Theme_Upgrader ) {
			$upgrader_object = Theme::instance();
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
		if ( isset( self::$options['github_updater_install_repo'] ) && empty( $repo ) ) {
			$repo['repo'] = $repo['extended_repo'] = self::$options['github_updater_install_repo'];
			$new_source   = trailingslashit( $remote_source ) . self::$options['github_updater_install_repo'];
		}

		$new_source = $this->fix_misnamed_directory( $new_source, $remote_source, $upgrader_object, $slug );
		$new_source = $this->extended_naming( $new_source, $remote_source, $upgrader_object, $repo );
		$new_source = $this->fix_gitlab_release_asset_directory( $new_source, $remote_source, $upgrader_object, $slug );

		$wp_filesystem->move( $source, $new_source );

		return trailingslashit( $new_source );
	}

	/**
	 * Correctly rename an initially misnamed directory.
	 * This usually occurs when initial installation not using GitHub Updater.
	 * May cause plugin/theme deactivation.
	 *
	 * @param string $new_source
	 * @param string $remote_source
	 * @param object $upgrader_object
	 * @param string $slug
	 *
	 * @return string $new_source
	 */
	private function fix_misnamed_directory( $new_source, $remote_source, $upgrader_object, $slug ) {
		if ( ! array_key_exists( $slug, (array) $upgrader_object->config ) &&
		     ! isset( self::$options['github_updater_install_repo'] )
		) {
			if ( $upgrader_object instanceof Plugin ) {
				foreach ( $upgrader_object->config as $plugin ) {
					if ( $slug === dirname( $plugin->slug ) ) {
						$slug       = $plugin->repo;
						$new_source = trailingslashit( $remote_source ) . $slug;
						break;
					}
				}
			}
			if ( $upgrader_object instanceof Theme ) {
				foreach ( $upgrader_object->config as $theme ) {
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
	 * Extended naming.
	 * Only for plugins and not for 'master' === branch && .org hosted.
	 *
	 * @param string $new_source
	 * @param string $remote_source
	 * @param object $upgrader_object
	 * @param array  $repo
	 *
	 * @return string $new_source
	 */
	private function extended_naming( $new_source, $remote_source, $upgrader_object, $repo ) {
		if ( $upgrader_object instanceof Plugin &&
		     ( defined( 'GITHUB_UPDATER_EXTENDED_NAMING' ) && GITHUB_UPDATER_EXTENDED_NAMING ) &&
		     ( ( isset( $upgrader_object->config[ $repo['repo'] ] ) &&
		         ! $upgrader_object->config[ $repo['repo'] ]->dot_org ) ||
		       ( $upgrader_object->tag && 'master' !== $upgrader_object->tag ) ||
		       isset( self::$options['github_updater_install_repo'] ) )
		) {
			$new_source = trailingslashit( $remote_source ) . $repo['extended_repo'];
			printf( esc_html__( 'Rename successful using extended name to %1$s', 'github-updater' ) . '&#8230;<br>',
				'<strong>' . $repo['extended_repo'] . '</strong>'
			);
		}

		return $new_source;
	}

	/**
	 * Renaming if using a GitLab Release Asset.
	 * It has a different download directory structure.
	 *
	 * @param string $new_source
	 * @param string $remote_source
	 * @param object $upgrader_object
	 * @param string $slug
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
	 * Fix name even if installed without renaming originally.
	 *
	 * @param string $slug
	 * @param object $upgrader_object
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
		foreach ( $upgrader_object->config as $repo ) {
			if ( ( $slug === $repo->repo || $slug === $repo->extended_repo ) ||
			     ( $rename === $repo->owner . '-' . $repo->repo || $rename === $repo->repo )
			) {
				$arr['repo']          = $repo->repo;
				$arr['extended_repo'] = $repo->extended_repo;
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
	protected function get_file_headers( $contents, $type ) {

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
		$all_headers = array_merge( self::$extra_headers, (array) $all_headers );
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
			function( $e ) use ( &$all_headers ) {
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
			$local_files = scandir( $this->$type->local_path );
		} elseif ( is_dir( $this->$type->local_path_extended ) ) {
			$local_files = scandir( $this->$type->local_path_extended );
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
	public function can_update( $type ) {
		global $wp_version;

		$remote_is_newer = version_compare( $type->remote_version, $type->local_version, '>' );
		$wp_version_ok   = version_compare( $wp_version, $type->requires_wp_version, '>=' );
		$php_version_ok  = version_compare( PHP_VERSION, $type->requires_php_version, '>=' );

		if ( ( isset( $this->tag ) && $this->tag ) &&
		     ( isset( $_GET['plugin'] ) && $type->slug === $_GET['plugin'] )
		) {
			$remote_is_newer = true;
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
		$repo_header          = str_replace( '.git', '', $repo_header );
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
		);
		$repo_base_uris = array(
			'GitHub'    => 'https://github.com/',
			'Bitbucket' => 'https://bitbucket.org/',
			'GitLab'    => 'https://gitlab.com/',
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

		return true;
	}

	/**
	 * Set repo object file info.
	 *
	 * @param $response
	 */
	protected function set_file_info( $response ) {
		$this->type->transient            = $response;
		$this->type->remote_version       = strtolower( $response['Version'] );
		$this->type->requires_php_version = ! empty( $response['Requires PHP'] ) ? $response['Requires PHP'] : $this->type->requires_php_version;
		$this->type->requires_wp_version  = ! empty( $response['Requires WP'] ) ? $response['Requires WP'] : $this->type->requires_wp_version;
		$this->type->release_asset        = ( ! empty( $response['Release Asset'] ) && true === $response['Release Asset'] );
		$this->type->dot_org              = $response['dot_org'];
	}

	/**
	 * Parse tags and set object data.
	 *
	 * @param $response
	 * @param $repo_type
	 *
	 * @return bool
	 */
	protected function parse_tags( $response, $repo_type ) {
		$tags     = array();
		$rollback = array();
		if ( false !== $response ) {
			switch ( $repo_type['repo'] ) {
				case 'github':
					foreach ( (array) $response as $tag ) {
						$download_base    = implode( '/', array(
							$repo_type['base_uri'],
							'repos',
							$this->type->owner,
							$this->type->repo,
							'zipball/',
						) );
						$tags[]           = $tag;
						$rollback[ $tag ] = $download_base . $tag;
					}
					break;
				case 'bitbucket':
					foreach ( (array) $response as $tag ) {
						$download_base    = implode( '/', array(
							$repo_type['base_download'],
							$this->type->owner,
							$this->type->repo,
							'get/',
						) );
						$tags[]           = $tag;
						$rollback[ $tag ] = $download_base . $tag . '.zip';
					}
					break;
				case 'gitlab':
					foreach ( (array) $response as $tag ) {
						$download_link    = implode( '/', array(
							$repo_type['base_download'],
							$this->type->owner,
							$this->type->repo,
							'repository/archive.zip',
						) );
						$download_link    = add_query_arg( 'ref', $tag, $download_link );
						$tags[]           = $tag;
						$rollback[ $tag ] = $download_link;
					}
					break;
			}

		}
		if ( empty( $tags ) ) {
			return false;
		}

		usort( $tags, 'version_compare' );
		krsort( $rollback );

		$newest_tag     = null;
		$newest_tag_key = key( array_slice( $tags, - 1, 1, true ) );
		$newest_tag     = $tags[ $newest_tag_key ];

		$this->type->newest_tag = $newest_tag;
		$this->type->tags       = $tags;
		$this->type->rollback   = $rollback;

		return true;
	}

	/**
	 * Set data from readme.txt.
	 * Prefer changelog from CHANGES.md.
	 *
	 * @param $response
	 *
	 * @return bool
	 */
	protected function set_readme_info( $response ) {
		$readme = array();
		foreach ( $this->type->sections as $section => $value ) {
			if ( 'description' === $section ) {
				continue;
			}
			$readme[ $section ] = $value;
		}
		foreach ( $readme as $key => $value ) {
			if ( ! empty( $value ) ) {
				unset( $response['sections'][ $key ] );
			}
		}

		$response['remaining_content'] = ! empty( $response['remaining_content'] ) ? $response['remaining_content'] : null;
		if ( empty( $response['sections']['other_notes'] ) ) {
			unset( $response['sections']['other_notes'] );
		} else {
			$response['sections']['other_notes'] .= $response['remaining_content'];
		}
		unset( $response['sections']['screenshots'], $response['sections']['installation'] );
		$response['sections']     = ! empty( $response['sections'] ) ? $response['sections'] : array();
		$this->type->sections     = array_merge( (array) $this->type->sections, (array) $response['sections'] );
		$this->type->tested       = isset( $response['tested'] ) ? $response['tested'] : null;
		$this->type->requires     = isset( $response['requires'] ) ? $response['requires'] : null;
		$this->type->donate_link  = isset( $response['donate_link'] ) ? $response['donate_link'] : null;
		$this->type->contributors = isset( $response['contributors'] ) ? $response['contributors'] : null;

		return true;
	}

	/**
	 * Add remote data to type object.
	 *
	 * @access protected
	 */
	protected function add_meta_repo_object() {
		$this->type->rating       = $this->make_rating( $this->type->repo_meta );
		$this->type->last_updated = $this->type->repo_meta['last_updated'];
		$this->type->num_ratings  = $this->type->repo_meta['watchers'];
		$this->type->is_private   = $this->type->repo_meta['private'];
	}

	/**
	 * Create some sort of rating from 0 to 100 for use in star ratings.
	 * I'm really just making this up, more based upon popularity.
	 *
	 * @param $repo_meta
	 *
	 * @return integer
	 */
	protected function make_rating( $repo_meta ) {
		$watchers    = empty( $repo_meta['watchers'] ) ? $this->type->watchers : $repo_meta['watchers'];
		$forks       = empty( $repo_meta['forks'] ) ? $this->type->forks : $repo_meta['forks'];
		$open_issues = empty( $repo_meta['open_issues'] ) ? $this->type->open_issues : $repo_meta['open_issues'];

		$rating = round( $watchers + ( $forks * 1.5 ) - $open_issues );

		if ( 100 < $rating ) {
			return 100;
		}

		return (integer) $rating;
	}

	/**
	 * Test to exit early if no update available, saves API calls.
	 *
	 * @param $response array|bool
	 * @param $branch   bool
	 *
	 * @return bool
	 */
	protected function exit_no_update( $response, $branch = false ) {
		/**
		 * Filters the return value of exit_no_update.
		 *
		 * @since 6.0.0
		 * @return bool `true` will exit this function early, default will not.
		 */
		if ( apply_filters( 'ghu_always_fetch_update', false ) ) {
			return false;
		}

		if ( $branch ) {
			$options = get_site_option( 'github_updater' );

			return empty( $options['branch_switch'] );
		}

		return ( ! isset( $_POST['ghu_refresh_cache'] ) && ! $response && ! $this->can_update( $this->type ) );
	}

	/**
	 * Get local file info if no update available. Save API calls.
	 *
	 * @param $repo
	 * @param $file
	 *
	 * @return null|string
	 */
	protected function get_local_info( $repo, $file ) {
		$response = false;

		if ( isset( $_POST['ghu_refresh_cache'] ) ) {
			return $response;
		}

		if ( is_dir( $repo->local_path ) ) {
			if ( file_exists( $repo->local_path . $file ) ) {
				$response = file_get_contents( $repo->local_path . $file );
			}
		} elseif ( is_dir( $repo->local_path_extended ) ) {
			if ( file_exists( $repo->local_path_extended . $file ) ) {
				$response = file_get_contents( $repo->local_path_extended . $file );
			}
		}

		switch ( $repo->type ) {
			case 'github_plugin':
			case 'github_theme':
				$response = base64_encode( $response );
				break;
			case 'bitbucket_plugin':
			case 'bitbucket_theme':
				break;
			case 'gitlab_plugin':
			case 'gitlab_theme':
				$response = base64_encode( $response );
				break;
		}

		return $response;
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
	 * @return mixed
	 */
	protected function make_branch_switch_row( $data ) {
		$rollback = empty( $this->config[ $data['slug'] ]->rollback ) ? array() : $this->config[ $data['slug'] ]->rollback;

		printf( esc_html__( 'Current branch is `%1$s`, try %2$sanother version%3$s', 'github-updater' ),
			$data['branch'],
			'<a href="#" onclick="jQuery(\'#' . $data['id'] . '\').toggle();return false;">',
			'</a>.'
		);

		print( '<ul id="' . $data['id'] . '" style="display:none; width: 100%;">' );

		foreach ( array_keys( $data['branches'] ) as $branch ) {
			printf( '<li><a href="%s%s" aria-label="' . esc_html__( 'Switch to branch ', 'github-updater' ) . $branch . '">%s</a></li>',
				$data['nonced_update_url'],
				'&rollback=' . urlencode( $branch ),
				esc_attr( $branch )
			);
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
		} else {
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

			// For extended naming
			foreach ( $this->config as $repo ) {
				if ( $slug === $repo->repo || $slug === $repo->extended_repo ) {
					$slug = $repo->repo;
					break;
				}
			}
		}

		if ( isset( $_GET['theme'] ) && 'upgrade-theme' === $_GET['action'] ) {
			$slug = $_GET['theme'];
			$type = 'theme';
		}

		if ( ! empty( $slug ) && array_key_exists( $slug, (array) $this->config ) ) {
			$repo = $this->config[ $slug ];
			$this->set_rollback_transient( $type, $repo );
		}

		return $update_data;
	}

	/**
	 * Update transient for rollback or branch switch.
	 *
	 * @param string $type plugin|theme
	 * @param object $repo
	 */
	private function set_rollback_transient( $type, $repo ) {
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
		}

		$transient         = 'update_' . $type . 's';
		$this->tag         = isset( $_GET['rollback'] ) ? $_GET['rollback'] : null;
		$slug              = 'plugin' === $type ? $repo->slug : $repo->repo;
		$updates_transient = get_site_transient( $transient );
		$rollback          = array(
			$type         => $slug,
			'new_version' => $this->tag,
			'url'         => $repo->uri,
			'package'     => $this->repo_api->construct_download_link( false, $this->tag ),
			'branch'      => $repo->branch,
			'branches'    => $repo->branches,
		);

		if ( 'plugin' === $type ) {
			$rollback['slug']                     = $repo->repo;
			$updates_transient->response[ $slug ] = (object) $rollback;
		}
		if ( 'theme' === $type ) {
			$updates_transient->response[ $slug ] = (array) $rollback;
		}
		set_site_transient( $transient, $updates_transient );
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
					$this->forced_meta_update_plugins( true );
					$current = Plugin::instance()->pre_set_site_transient_update_plugins( $current );
					break;
				case 'update_themes':
					$this->forced_meta_update_themes( true );
					$current = Theme::instance()->pre_set_site_transient_update_themes( $current );
					break;
				case 'update_core':
					$this->forced_meta_update_plugins( true );
					$current = Plugin::instance()->pre_set_site_transient_update_plugins( $current );
					$this->forced_meta_update_themes( true );
					$current = Theme::instance()->pre_set_site_transient_update_themes( $current );
					break;
			}
			set_site_transient( $transient, $current );
		}
	}

	/**
	 * Parse Enterprise, Languages, and CI Job headers for plugins and themes.
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

		if ( ! in_array( $header['host'], $hosted_domains ) && ! empty( $header['host'] ) ) {
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
			$theme   = $headers;
			$headers = array();
		}

		$self_hosted_parts = array_diff( array_keys( self::$extra_repo_headers ), array( 'branch' ) );
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

		return $header;
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
	 * Is this a private repo?
	 * Test for whether remote_version is set ( default = 0.0.0 ) or
	 * a repo option is set/not empty.
	 *
	 * @param object $repo
	 *
	 * @return bool
	 */
	protected function is_private( $repo ) {
		if ( ! self::is_doing_ajax() && isset( $repo->remote_version ) ) {
			return ( '0.0.0' === $repo->remote_version ) || ! empty( self::$options[ $repo->repo ] );
		}
	}

}
