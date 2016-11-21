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
	 * @var
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
		'branch'     => 'Branch',
		'enterprise' => 'Enterprise',
		'gitlab_ce'  => 'CE',
		'languages'  => 'Languages',
		'ci_job'     => 'CI Job',
	);

	/**
	 * Holds boolean on whether or not the repo requires authentication.
	 * Used by class Settings and class Messages.
	 *
	 * @var bool
	 */
	protected static $auth_required = array(
		'github_private'    => false,
		'github_enterprise' => false,
		'bitbucket_private' => false,
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
	 * @var string[] Option keys refer to option names, option names are used to save value on database
	 */
	//TODO: these should be used on any other part where setting options are used (i.e. the Settings class)
	protected static $options_names = array(
		'github_access_token' => 'github_access_token',
		'bitbucket_username' => 'bitbucket_username',
		'bitbucket_password' => 'bitbucket_password',
		'gitlab_access_token' => 'gitlab_access_token',
		'gitlab_enterprise_token' => 'gitlab_enterprise_token',
		'branch_switch' => 'branch_switch',
	);
	
	/**
	 * @var string[] Option keys refer to option names, option names are used to save value on database
	 */
	//TODO: these should be used on any other part where setting options are used (i.e. the Settings class)
	protected static $options_remote_names = array(
		'ithemes_sync' => 'ithemes_sync',
		'infinitewp'   => 'infinitewp',
		'managewp'     => 'managewp',
		'mainwp'       => 'mainwp',
	);

	/**
	 * Constructor.
	 * Loads options to private static variable.
	 */
	public function __construct() {
		if ( isset( $_GET['refresh_transients'] ) ) {
			$this->delete_all_transients();
		}

		$this->load_hooks();
	}

	/**
	 * Load site options.
	 */
	protected function load_options() {
		self::$options        = get_site_option( 'github_updater', array() );
		self::$options_remote = get_site_option( 'github_updater_remote_management', array() );
		
		/*
		 * Filter singular options for better control over time
		 * (i.e. if we need structure of our option array)
		 */
		//TODO: move theme and plugins options inside an array with keys 'themes' and 'plugins', now they are at the same level of global options
		//TODO: some checks on functions returned values
		foreach(self::$options_names as $key => $name){
			self::$options[$key] = $this->filter_option($name);
		}
		
		foreach(self::$options_names as $key => $name){
			self::$options_remote[$key] = $this->filter_option_remote($name);
		}
	}
	
	/**
	 * Create a filter for current $name option
	 * @param $name string The filtered option name
	 * @return string The filtered option value
	 */
	protected function filter_option($name){
		return ( array_key_exists( $name, self::$options ) ) ?
			apply_filters('github_updater_filter_option_'.$name, self::$options[$name]) :
			apply_filters('github_updater_filter_option_'.$name, '');
	}
	
	/**
	 * Create a filter for current $name option
	 * @param $name string The filtered option name
	 * @return string The filtered option value
	 */
	protected function filter_option_remote($name){
		return ( array_key_exists( $name, self::$options_remote ) ) ?
			apply_filters('github_updater_filter_option_remote_'.$name, self::$options_remote[$name]) :
			apply_filters('github_updater_filter_option_remote_'.$name, '');
	}

	/**
	 * Load relevant action/filter hooks.
	 * Use 'init' hook for user capabilities.
	 */
	public function load_hooks() {
		add_action( 'init', array( &$this, 'init' ) );
		add_action( 'init', array( &$this, 'background_update' ) );
		add_action( 'init', array( &$this, 'token_distribution' ) );
		add_action( 'wp_ajax_github-updater-update', array( &$this, 'ajax_update' ) );
		add_action( 'wp_ajax_nopriv_github-updater-update', array( &$this, 'ajax_update' ) );

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

		if ( $this->repo_api instanceof Bitbucket_API ) {
			$this->repo_api->remove_hooks();
		}
	}

	/**
	 * Ensure api key is set.
	 */
	protected function ensure_api_key_is_set() {
		$api_key = get_site_option( 'github_updater_api_key' );
		if ( ! $api_key ) {
			update_site_option( 'github_updater_api_key', md5( uniqid( rand(), true ) ) );
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

		if ( in_array( $pagenow, array_unique( $admin_pages ) ) ) {
			$force_meta_update = true;
		}

		if ( isset( $_GET['refresh_transients'] ) ) {
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
			new Settings();
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
	 */
	public function forced_meta_update_plugins() {
		if ( self::$load_repo_meta ) {
			$this->load_options();
			Plugin::instance()->get_remote_plugin_meta();
		}
	}

	/**
	 * Performs actual theme metadata fetching.
	 */
	public function forced_meta_update_themes() {
		if ( self::$load_repo_meta ) {
			$this->load_options();
			Theme::instance()->get_remote_theme_meta();
		}
	}

	/**
	 * Calls $this->forced_meta_update_plugins() and $this->forced_meta_update_themes()
	 * for remote management services.
	 */
	public function forced_meta_update_remote_management() {
		$this->forced_meta_update_plugins();
		$this->forced_meta_update_themes();
	}

	/**
	 * Allows developers to use 'github_updater_token_distribution' hook to set GitHub Access Tokens.
	 * Saves results of filter hook to self::$options.
	 *
	 * Hook requires return of associative element array.
	 * $key === repo-name and $value === token
	 * e.g.  array( 'repo-name' => 'access_token' );
	 */
	public function token_distribution() {
		$config = apply_filters( 'github_updater_token_distribution', array() );
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
		$this->$type->score                = 0;
		$this->$type->requires_wp_version  = '4.0';
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
		$this->repo_api = null;
		$file           = 'style.css';
		if ( false !== stristr( $repo->type, 'plugin' ) ) {
			$file = basename( $repo->slug );
		}

		switch ( $repo->type ) {
			case 'github_plugin':
			case 'github_theme':
				$this->repo_api = new GitHub_API( $repo );
				break;
			case 'bitbucket_plugin':
			case 'bitbucket_theme':
				$this->repo_api = new Bitbucket_API( $repo );
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
				$this->repo_api->get_remote_tag();
			}
			$repo->download_link = $this->repo_api->construct_download_link();
			$this->languages     = new Language_Pack( $repo, $this->repo_api );
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
		global $wp_filesystem, $plugins, $themes;
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

			/*
			 * Pre-WordPress 4.4
			 */
			if ( $plugins && empty( $hook_extra ) ) {
				foreach ( array_reverse( $plugins ) as $plugin ) {
					$slug = dirname( $plugin );
					if ( false !== stristr( basename( $source ), dirname( $plugin ) ) ) {
						$new_source = trailingslashit( $remote_source ) . dirname( $plugin );
						break;
					}
				}
			}
			if ( ! $plugins && empty( $hook_extra ) ) {
				if ( isset( $upgrader->skin->plugin ) ) {
					$slug = dirname( $upgrader->skin->plugin );
				}
				if ( empty( $slug ) && isset( $_POST['slug'] ) ) {
					$slug = sanitize_text_field( $_POST['slug'] );
				}
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

			/*
			 * Pre-WordPress 4.4
			 */
			if ( $themes && empty( $hook_extra ) ) {
				foreach ( $themes as $theme ) {
					$slug = $theme;
					if ( false !== stristr( basename( $source ), $theme ) ) {
						$new_source = trailingslashit( $remote_source ) . $theme;
						break;
					}
				}
			}
			if ( ! $themes && empty( $hook_extra ) ) {
				if ( isset( $upgrader->skin->theme ) ) {
					$slug = $upgrader->skin->theme;
				}
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
		if ( isset( self::$options['github_updater_install_repo'] ) ) {
			$repo['repo'] = $repo['extended_repo'] = self::$options['github_updater_install_repo'];
			$new_source   = trailingslashit( $remote_source ) . self::$options['github_updater_install_repo'];
		}

		/*
		 * Directory is misnamed to start.
		 * May cause deactivation.
		 */
		if ( ! array_key_exists( $slug, (array) $upgrader_object->config ) &&
		     ! isset( self::$options['github_updater_install_repo'] )
		) {
			if ( $upgrader instanceof \Plugin_Upgrader ) {
				foreach ( $upgrader_object->config as $plugin ) {
					if ( $slug === dirname( $plugin->slug ) ) {
						$slug       = $plugin->repo;
						$new_source = trailingslashit( $remote_source ) . $slug;
						break;
					}
				}
			}
			if ( $upgrader instanceof \Theme_Upgrader ) {
				foreach ( $upgrader_object->config as $theme ) {
					if ( $slug === $theme->repo ) {
						$new_source = trailingslashit( $remote_source ) . $slug;
						break;
					}
				}
			}
		}

		/*
		 * Revert extended naming if previously present.
		 */
		if ( $upgrader_object instanceof Plugin &&
		     ( ! defined( 'GITHUB_UPDATER_EXTENDED_NAMING' ) || ! GITHUB_UPDATER_EXTENDED_NAMING ) &&
		     $slug !== $repo['repo']
		) {
			$new_source = trailingslashit( $remote_source ) . $repo['repo'];
		}

		/*
		 * Extended naming.
		 * Only for plugins and not for 'master' === branch && .org hosted.
		 */
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

		/*
		 * Renaming if GitLab Release Asset.
		 * It has a different download directory structure.
		 */
		if ( ( isset( $upgrader_object->config[ $slug ]->release_asset ) &&
		       $upgrader_object->config[ $slug ]->release_asset ) &&
		     ! empty( $upgrader_object->config[ $slug ]->ci_job )
		) {
			$new_source = trailingslashit( dirname( $remote_source ) ) . $slug;
			add_filter( 'upgrader_post_install', array( &$this, 'upgrader_post_install' ), 10, 3 );
		}

		$wp_filesystem->move( $source, $new_source );

		// Delete transients after update of this plugin.
		if ( 'github-updater' === $slug ) {
			add_action( 'upgrader_process_complete', array( &$this, 'delete_transients_ghu_update' ), 15 );
		}

		return trailingslashit( $new_source );
	}

	/**
	 * Delete transients after upgrade for GHU.
	 *
	 * Run on `upgrader_process_complete` filter hook so rebuilt transients
	 * are from updated plugin code.
	 */
	public function delete_transients_ghu_update() {
		$this->delete_all_transients();
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

		if ( is_null( $upgrader_object ) ) {
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
	 * @param $repo_header
	 *
	 * @return array
	 */
	protected function parse_header_uri( $repo_header ) {
		$header_parts     = parse_url( $repo_header );
		$header['scheme'] = isset( $header_parts['scheme'] ) ? $header_parts['scheme'] : null;
		$header['host']   = isset( $header_parts['host'] ) ? $header_parts['host'] : null;
		$owner_repo       = trim( $header_parts['path'], '/' );  // strip surrounding slashes
		$owner_repo       = str_replace( '.git', '', $owner_repo ); //strip incorrect URI ending
		$header['path']   = $owner_repo;
		list( $header['owner'], $header['repo'] ) = explode( '/', $owner_repo );
		$header['owner_repo'] = isset( $header['owner'] ) ? $header['owner'] . '/' . $header['repo'] : null;
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
	 * Delete all `_ghu-` transients from database table.
	 *
	 * @return bool
	 */
	public function delete_all_transients() {
		global $wpdb;

		$table         = is_multisite() ? $wpdb->base_prefix . 'sitemeta' : $wpdb->base_prefix . 'options';
		$column        = is_multisite() ? 'meta_key' : 'option_name';
		$delete_string = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s LIMIT 1000';

		$wpdb->query( $wpdb->prepare( $delete_string, array( '%_ghu-%' ) ) );

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
		$this->type->release_asset        = ! empty( $response['Release Asset'] ) && true == $response['Release Asset'] ? true : false;
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
						$download_base          = implode( '/', array(
							$repo_type['base_uri'],
							'repos',
							$this->type->owner,
							$this->type->repo,
							'zipball/',
						) );
						$tags[]                 = $tag->name;
						$rollback[ $tag->name ] = $download_base . $tag->name;
					}
					break;
				case 'bitbucket':
					foreach ( (array) $response as $num => $tag ) {
						$download_base    = implode( '/', array(
							$repo_type['base_download'],
							$this->type->owner,
							$this->type->repo,
							'get/',
						) );
						$tags[]           = $num;
						$rollback[ $num ] = $download_base . $num . '.zip';
					}
					break;
				case 'gitlab':
					foreach ( (array) $response as $tag ) {
						$download_link          = implode( '/', array(
							$repo_type['base_download'],
							$this->type->owner,
							$this->type->repo,
							'repository/archive.zip',
						) );
						$download_link          = add_query_arg( 'ref', $tag, $download_link );
						$tags[]                 = $tag->name;
						$rollback[ $tag->name ] = $download_link;
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
		unset( $response['sections']['screenshots'] );
		unset( $response['sections']['installation'] );
		$this->type->sections     = array_merge( (array) $this->type->sections, (array) $response['sections'] );
		$this->type->tested       = isset( $response['tested'] ) ? $response['tested'] : null;
		$this->type->requires     = isset( $response['requires'] ) ? $response['requires'] : null;
		$this->type->donate_link  = isset( $response['donate_link'] ) ? $response['donate_link'] : null;
		$this->type->contributors = isset( $response['contributors'] ) ? $response['contributors'] : null;

		return true;
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
		$watchers    = empty( $repo_meta->watchers ) ? $this->type->watchers : $repo_meta->watchers;
		$forks       = empty( $repo_meta->forks ) ? $this->type->forks : $repo_meta->forks;
		$open_issues = empty( $repo_meta->open_issues ) ? $this->type->open_issues : $repo_meta->open_issues;
		$score       = empty( $repo_meta->score ) ? $this->type->score : $repo_meta->score; //what is this anyway?

		$rating = round( $watchers + ( $forks * 1.5 ) - $open_issues + $score );

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

		return ( ! isset( $_GET['refresh_transients'] ) && ! $response && ! $this->can_update( $this->type ) );
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
		$response = null;

		if ( isset( $_GET['refresh_transients'] ) ) {
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
			'<a href="javascript:jQuery(\'#' . $data['id'] . '\').toggle()">',
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
	 * @return string|void
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
	 * Is this a private repo?
	 * Test for whether remote_version is set ( default = 0.0.0 ) or
	 * a repo option is set/not empty.
	 *
	 * @param object $repo
	 *
	 * @return bool
	 */
	protected function is_private( $repo ) {
		if ( ! $this->is_doing_ajax() && isset( $repo->remote_version ) ) {
			return ( '0.0.0' === $repo->remote_version ) || ! empty( self::$options[ $repo->repo ] );
		}
	}

}
