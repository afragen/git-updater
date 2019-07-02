<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

namespace Fragen\GitHub_Updater;

use Fragen\Singleton;
use Fragen\GitHub_Updater\Traits\GHU_Trait;
use Fragen\GitHub_Updater\Traits\Basic_Auth_Loader;
use Fragen\GitHub_Updater\API\Bitbucket_API;
use Fragen\GitHub_Updater\API\Language_Pack_API;

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
 * @author  Andy Fragen
 */
class Base {
	use GHU_Trait, Basic_Auth_Loader;

	/**
	 * Variable for holding extra theme and plugin headers.
	 *
	 * @var array
	 */
	public static $extra_headers = [];

	/**
	 * Holds the values to be used in the fields callbacks.
	 *
	 * @var array
	 */
	public static $options;

	/**
	 * Holds git server types.
	 *
	 * @var array
	 */
	public static $git_servers = [ 'github' => 'GitHub' ];

	/**
	 * Holds extra repo header types.
	 *
	 * @var array
	 */
	protected static $extra_repo_headers = [
		'Languages' => 'Languages',
		'CIJob'     => 'CI Job',
	];

	/**
	 * Holds an array of installed git APIs.
	 *
	 * @var array
	 */
	public static $installed_apis = [ 'github_api' => true ];

	/**
	 * Stores the object calling Basic_Auth_Loader.
	 *
	 * @access public
	 * @var \stdClass
	 */
	public $caller;

	/**
	 * Store details of all repositories that are installed.
	 *
	 * @var \stdClass
	 */
	protected $config;

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
		if ( file_exists( __DIR__ . '/API/Zipfile_API.php' ) ) {
			self::$installed_apis['zipfile_api'] = true;
			self::$git_servers['zipfile']        = 'Zipfile';
		} else {
			self::$installed_apis['zipfile_api'] = false;
		}
	}

	/**
	 * Load Plugin, Theme, and Settings with correct capabiltiies and on selective admin pages.
	 *
	 * @return bool
	 */
	public function load() {
		if ( ! apply_filters( 'github_updater_hide_settings', false ) ) {
			Singleton::get_instance( 'Settings', $this )->run();
		}
		if ( ! Singleton::get_instance( 'Init', $this )->can_update() ) {
			return false;
		}

		// Run GitHub Updater upgrade functions.
		$upgrade = new GHU_Upgrade();
		$upgrade->run();

		// Load plugin stylesheet.
		add_action(
			'admin_enqueue_scripts',
			function () {
				wp_register_style( 'github-updater', plugins_url( basename( GITHUB_UPDATER_DIR ) ) . '/css/github-updater.css' );
				wp_enqueue_style( 'github-updater' );
			}
		);

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

		return true;
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
	 * AJAX endpoint for REST updates.
	 */
	public function ajax_update() {
		Singleton::get_instance( 'Rest_Update', $this )->process_request();
	}

	/**
	 * Run background processes.
	 * Piggyback on built-in update function to get metadata.
	 * Set update transients for remote management.
	 */
	public function background_update() {
		add_action( 'wp_update_plugins', [ $this, 'get_meta_plugins' ] );
		add_action( 'wp_update_themes', [ $this, 'get_meta_themes' ] );
		add_action( 'ghu_get_remote_plugin', [ $this, 'run_cron_batch' ], 10, 1 );
		add_action( 'ghu_get_remote_theme', [ $this, 'run_cron_batch' ], 10, 1 );
		add_action( 'wp_ajax_nopriv_ithemes_sync_request', [ $this, 'get_meta_remote_management' ] );
		add_action( 'update_option_auto_updater.lock', [ $this, 'get_meta_remote_management' ] );
		( new Remote_Management() )->set_update_transients();
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
	 * Single plugin/theme should not be using both hooks.
	 *
	 * Hook requires return of associative element array.
	 * $key === repo-name and $value === token
	 * e.g.  array( 'repo-name' => 'access_token' );
	 */
	public function set_options_filter() {
		$config = apply_filters( 'github_updater_set_options', [] );
		if ( empty( $config ) ) {
			$config = function_exists( 'apply_filters_deprecated' )
				? apply_filters_deprecated( 'github_updater_token_distribution', [ null ], '6.1.0', 'github_updater_set_options' )
				: apply_filters( 'github_updater_token_distribution', [] );
		}

		if ( ! empty( $config ) ) {
			$config        = $this->sanitize( $config );
			self::$options = array_merge( get_site_option( 'github_updater' ), $config );
			update_site_option( 'github_updater', self::$options );
		}
	}

	/**
	 * Add extra headers to get_plugins() or wp_get_themes().
	 *
	 * @param array $extra_headers
	 *
	 * @return array
	 */
	public function add_headers( $extra_headers ) {
		$ghu_extra_headers = [
			'RequiresWP'   => 'Requires WP',
			'RequiresPHP'  => 'Requires PHP',
			'ReleaseAsset' => 'Release Asset',
		];

		$uri_types = [
			'PluginURI' => ' Plugin URI',
			'ThemeURI'  => ' Theme URI',
		];

		foreach ( self::$git_servers as $server ) {
			foreach ( $uri_types as $uri_key => $uri_value ) {
				$ghu_extra_headers[ $server . $uri_key ] = $server . $uri_value;
			}
			foreach ( self::$extra_repo_headers as $header_key => $header_value ) {
				$ghu_extra_headers[ $server . $header_key ] = $server . ' ' . $header_value;
			}
		}

		self::$extra_headers = array_unique( array_merge( self::$extra_headers, $ghu_extra_headers ) );
		$extra_headers       = array_merge( (array) $extra_headers, $ghu_extra_headers );
		ksort( self::$extra_headers );

		return $extra_headers;
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
	 * Get remote repo meta data for plugins or themes.
	 * Calls remote APIs for data.
	 *
	 * @param \stdClass $repo
	 *
	 * @return bool
	 */
	public function get_remote_repo_meta( $repo ) {
		$file = 'style.css';
		if ( false !== stripos( $repo->type, 'plugin' ) ) {
			$file = basename( $repo->file );
		}

		$repo_api = Singleton::get_instance( 'API', $this )->get_repo_api( $repo->git, $repo );
		if ( null === $repo_api ) {
			return false;
		}

		$this->{$repo->type} = $repo;
		$this->set_defaults( $repo->type );

		if ( $repo_api->get_remote_info( $file ) ) {
			if ( ! self::is_wp_cli() ) {
				if ( ! apply_filters( 'github_updater_run_at_scale', false ) ) {
					$repo_api->get_repo_meta();
					$changelog = $this->get_changelog_filename( $repo );
					if ( $changelog ) {
						$repo_api->get_remote_changes( $changelog );
					}
					$repo_api->get_remote_readme();
				}
				if ( ! empty( self::$options['branch_switch'] ) ) {
					$repo_api->get_remote_branches();
				}
			}
			$repo_api->get_remote_tag();
			$repo->download_link = $repo_api->construct_download_link();
			$language_pack       = new Language_Pack( $repo, new Language_Pack_API( $repo ) );
			$language_pack->run();
		}

		$this->remove_hooks();

		return true;
	}

	/**
	 * Set default values for plugin/theme.
	 *
	 * @param string $type
	 */
	protected function set_defaults( $type ) {
		if ( ! isset( self::$options['branch_switch'] ) ) {
			self::$options['branch_switch'] = null;
		}

		if ( ! isset( $this->$type->slug ) ) {
			$this->$type       = new \stdClass();
			$this->$type->slug = null;
		} elseif ( ! isset( self::$options[ $this->$type->slug ] ) ) {
			self::$options[ $this->$type->slug ] = null;
			add_site_option( 'github_updater', self::$options );
		}

		$this->$type->remote_version = '0.0.0';
		$this->$type->newest_tag     = '0.0.0';
		$this->$type->download_link  = null;
		$this->$type->tags           = [];
		$this->$type->rollback       = [];
		$this->$type->branches       = [];
		$this->$type->requires       = null;
		$this->$type->tested         = null;
		$this->$type->donate_link    = null;
		$this->$type->contributors   = [];
		$this->$type->downloaded     = 0;
		$this->$type->last_updated   = null;
		$this->$type->rating         = 0;
		$this->$type->num_ratings    = 0;
		$this->$type->transient      = [];
		$this->$type->repo_meta      = [];
		$this->$type->watchers       = 0;
		$this->$type->forks          = 0;
		$this->$type->open_issues    = 0;
		$this->$type->requires       = false;
		$this->$type->requires_php   = false;
	}

	/**
	 * Get filename of changelog and return.
	 *
	 * @param \stdClass $repo
	 *
	 * @return bool|string
	 */
	protected function get_changelog_filename( $repo ) {
		$changelogs  = [ 'CHANGES.md', 'CHANGELOG.md', 'changes.md', 'changelog.md' ];
		$changes     = null;
		$local_files = null;

		if ( is_dir( $repo->local_path ) ) {
			$local_files = scandir( $repo->local_path, 0 );
		}

		$changes = array_intersect( (array) $local_files, $changelogs );
		$changes = array_pop( $changes );

		if ( ! empty( $changes ) ) {
			return $changes;
		}

		return false;
	}

	/**
	 * Remove hooks after use.
	 */
	public function remove_hooks() {
		remove_filter( 'extra_theme_headers', [ $this, 'add_headers' ] );
		remove_filter( 'extra_plugin_headers', [ $this, 'add_headers' ] );
	}

	/**
	 * Checks if dupicate wp-cron event exists.
	 *
	 * @param string $event Name of wp-cron event.
	 *
	 * @return bool
	 */
	public function is_duplicate_wp_cron_event( $event ) {
		$cron = _get_cron_array();
		foreach ( $cron as $timestamp => $cronhooks ) {
			if ( key( $cronhooks ) === $event ) {
				$this->is_cron_overdue( $cron, $timestamp );

				return true;
			}
		}

		return false;
	}

	/**
	 * Check to see if wp-cron event is overdue by 24 hours and report error message.
	 *
	 * @param array $cron
	 * @param int   $timestamp
	 */
	public function is_cron_overdue( $cron, $timestamp ) {
		$overdue = ( ( time() - $timestamp ) / HOUR_IN_SECONDS ) > 24;
		if ( $overdue ) {
			$error_msg = esc_html__( 'There may be a problem with WP-Cron. A GitHub Updater WP-Cron event is overdue.', 'github-updater' );
			$error     = new \WP_Error( 'github_updater_cron_error', $error_msg );
			Singleton::get_instance( 'Messages', $this )->create_error_message( $error );
		}
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
		$install_options = $this->get_class_vars( 'Install', 'install' );
		if ( empty( $repo ) && isset( $install_options['github_updater_install_repo'] ) ) {
			$slug                            = $install_options['github_updater_install_repo'];
			$new_source                      = trailingslashit( $remote_source ) . $slug;
			self::$options['remote_install'] = true;
		}

		Singleton::get_instance( 'Branch', $this )->set_branch_on_switch( $slug );

		$new_source = $this->fix_misnamed_directory( $new_source, $remote_source, $upgrader_object, $slug );
		$new_source = $this->fix_release_asset_directory( $new_source, $remote_source, $upgrader_object, $slug );

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
			! isset( self::$options['remote_install'] )
		) {
			if ( $upgrader_object instanceof Plugin ) {
				foreach ( (array) $upgrader_object->config as $plugin ) {
					if ( $slug === $plugin->slug ) {
						$new_source = trailingslashit( $remote_source ) . $slug;
						break;
					}
				}
			}
			if ( $upgrader_object instanceof Theme ) {
				foreach ( (array) $upgrader_object->config as $theme ) {
					if ( $slug === $theme->slug ) {
						$new_source = trailingslashit( $remote_source ) . $slug;
						break;
					}
				}
			}
		}

		return $new_source;
	}

	/**
	 * Fix the directory structure of certain release assests.
	 *
	 * GitLab release assets have a different download directory structure.
	 * Bitbucket release assets need to be copied into a containing directory.
	 *
	 * @param string       $new_source
	 * @param string       $remote_source
	 * @param Plugin|Theme $upgrader_object
	 * @param string       $slug
	 *
	 * @return string $new_source
	 */
	private function fix_release_asset_directory( $new_source, $remote_source, $upgrader_object, $slug ) {
		global $wp_filesystem;
		if ( isset( $upgrader_object->config[ $slug ]->release_asset ) &&
			$upgrader_object->config[ $slug ]->release_asset ) {
			if ( 'gitlab' === $upgrader_object->config[ $slug ]->git ) {
				$new_source = trailingslashit( dirname( $remote_source ) ) . $slug;
				add_filter( 'upgrader_post_install', [ $this, 'upgrader_post_install' ], 10, 3 );
			}
			if ( 'bitbucket' === $upgrader_object->config[ $slug ]->git ) {
				$temp_source = trailingslashit( dirname( $remote_source ) ) . $slug;
				$wp_filesystem->move( $remote_source, $temp_source );
				wp_mkdir_p( $new_source );
				copy_dir( $temp_source, $new_source );
				$wp_filesystem->delete( $temp_source, true );
			}
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
		remove_filter( 'upgrader_post_install', [ $this, 'upgrader_post_install' ] );

		return $result;
	}

	/**
	 * Set array with normal repo names.
	 * Fix name even if installed without renaming originally, eg <repo>-master
	 *
	 * @param string            $slug
	 * @param Base|Plugin|Theme $upgrader_object
	 *
	 * @return array
	 */
	protected function get_repo_slugs( $slug, $upgrader_object = null ) {
		$arr    = [];
		$rename = explode( '-', $slug );
		array_pop( $rename );
		$rename = implode( '-', $rename );

		if ( null === $upgrader_object ) {
			$upgrader_object = $this;
		}

		$rename = isset( $upgrader_object->config[ $slug ] ) ? $slug : $rename;

		foreach ( (array) $upgrader_object->config as $repo ) {
			// Check repo slug or directory name for match.
			$slug_check = [
				$repo->slug,
				dirname( $repo->file ),
			];

			// Exact match.
			if ( \in_array( $slug, $slug_check, true ) ) {
				$arr['slug'] = $repo->slug;
				break;
			}

			// Soft match, there may still be an exact $slug match.
			if ( \in_array( $rename, $slug_check, true ) ) {
				$arr['slug'] = $repo->slug;
			}
		}

		return $arr;
	}

	/**
	 * Update transient for rollback or branch switch.
	 *
	 * @param string    $type          plugin|theme.
	 * @param \stdClass $repo
	 * @param bool      $set_transient Default false, if true then set update transient.
	 *
	 * @return array $rollback Rollback transient.
	 */
	protected function set_rollback_transient( $type, $repo, $set_transient = false ) {
		$repo_api      = Singleton::get_instance( 'API', $this )->get_repo_api( $repo->git, $repo );
		$this->tag     = isset( $_GET['rollback'] ) ? $_GET['rollback'] : false;
		$slug          = 'plugin' === $type ? $repo->file : $repo->slug;
		$download_link = $repo_api->construct_download_link( $this->tag );

		/**
		 * Filter download link so developers can point to specific ZipFile
		 * to use as a download link during a branch switch.
		 *
		 * @since 8.6.0
		 *
		 * @param string    $download_link Download URL.
		 * @param /stdClass $repo
		 * @param string    $this->tag     Branch or tag for rollback.
		 */
		$download_link = apply_filters_deprecated(
			'github_updater_set_rollback_package',
			[ $download_link, $repo, $this->tag ],
			'8.8.0',
			'github_updater_post_construct_download_link'
		);

		$rollback = [
			$type         => $slug,
			'new_version' => $this->tag,
			'url'         => $repo->uri,
			'package'     => $download_link,
			'branch'      => $repo->branch,
			'branches'    => $repo->branches,
			'type'        => $repo->type,
		];

		if ( 'plugin' === $type ) {
			$rollback['slug'] = $repo->slug;
			$rollback         = (object) $rollback;
		}

		return $rollback;
	}

	/**
	 * Check to see if wp-cron/background updating has finished.
	 *
	 * @param null $repo
	 *
	 * @return bool true when waiting for background job to finish.
	 */
	protected function waiting_for_background_update( $repo = null ) {
		$caches = [];
		if ( null !== $repo ) {
			$cache = isset( $repo->slug ) ? $this->get_repo_cache( $repo->slug ) : null;

			return empty( $cache );
		}
		$repos = array_merge(
			Singleton::get_instance( 'Plugin', $this )->get_plugin_configs(),
			Singleton::get_instance( 'Theme', $this )->get_theme_configs()
		);
		foreach ( $repos as $git_repo ) {
			$caches[ $git_repo->slug ] = $this->get_repo_cache( $git_repo->slug );
		}
		$waiting = array_filter(
			$caches,
			function ( $e ) {
				return empty( $e );
			}
		);

		return ! empty( $waiting );
	}

	/**
	 * Create repo parts.
	 *
	 * @param string $repo
	 * @param string $type plugin|theme.
	 *
	 * @return mixed
	 */
	protected function get_repo_parts( $repo, $type ) {
		$arr['bool']    = false;
		$pattern        = '/' . strtolower( $repo ) . '_/';
		$type           = preg_replace( $pattern, '', $type );
		$repo_types     = [
			'GitHub'    => 'github_' . $type,
			'Bitbucket' => 'bitbucket_' . $type,
			'GitLab'    => 'gitlab_' . $type,
			'Gitea'     => 'gitea_' . $type,
		];
		$repo_base_uris = [
			'GitHub'    => 'https://github.com/',
			'Bitbucket' => 'https://bitbucket.org/',
			'GitLab'    => 'https://gitlab.com/',
			'Gitea'     => '',
		];

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
	 * Return correct update row opening and closing tags for Shiny Updates.
	 *
	 * @param string $repo_name
	 * @param string $type            plugin|theme.
	 * @param bool   $branch_switcher
	 *
	 * @return array
	 */
	protected function update_row_enclosure( $repo_name, $type, $branch_switcher = false ) {
		global $wp_version;
		$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
		$repo_base     = $repo_name;
		$shiny_classes = ' notice inline notice-warning notice-alt';

		if ( 'plugin' === $type ) {
			$repo_base = dirname( $repo_name );
		}

		$open = '<tr class="plugin-update-tr" data-slug="' . esc_attr( $repo_base ) . '" data-plugin="' . esc_attr( $repo_name ) . '">
		<td colspan="' . $wp_list_table->get_column_count() . '" class="plugin-update colspanchange">
		<div class="update-message">';

		$enclosure = [
			'open'  => $open,
			'close' => '</div></td></tr>',
		];

		if ( version_compare( $wp_version, '4.6', '>=' ) ) {
			$open_p  = '<p>';
			$close_p = '</p>';
			if ( $branch_switcher ) {
				$open_p  = '';
				$close_p = '';
			}
			$enclosure = [
				'open'  => substr_replace( $open, $shiny_classes, -2, 0 ) . $open_p,
				'close' => $close_p . '</div></td></tr>',
			];
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
		$rollback = empty( $this->config[ $data['slug'] ]->rollback ) ? [] : $this->config[ $data['slug'] ]->rollback;

		printf(
			/* translators: 1: branch name, 2: jQuery dropdown, 3: closing tag */
			esc_html__( 'Current branch is `%1$s`, try %2$sanother version%3$s', 'github-updater' ),
			$data['branch'],
			'<a href="#" onclick="jQuery(\'#' . $data['id'] . '\').toggle();return false;">',
			'</a>.'
		);

		print '<ul id="' . $data['id'] . '" style="display:none; width: 100%;">';

		if ( null !== $data['branches'] ) {
			foreach ( array_keys( $data['branches'] ) as $branch ) {
				printf(
					'<li><a href="%s%s" aria-label="' . esc_html__( 'Switch to branch ', 'github-updater' ) . $branch . '">%s</a></li>',
					$data['nonced_update_url'],
					'&rollback=' . rawurlencode( $branch ),
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
				printf(
					'<li><a href="%s%s" aria-label="' . esc_html__( 'Switch to release ', 'github-updater' ) . $tag . '">%s</a></li>',
					$data['nonced_update_url'],
					'&rollback=' . rawurlencode( $tag ),
					esc_attr( $tag )
				);
			}
		}
		if ( empty( $rollback ) ) {
			esc_html_e( 'No previous tags to rollback to.', 'github-updater' );
		}

		print '</ul>';
	}

	/**
	 * Generate update URL.
	 *
	 * @param string $type      ( plugin or theme ).
	 * @param string $action
	 * @param string $repo_name
	 *
	 * @return string
	 */
	protected function get_update_url( $type, $action, $repo_name ) {
		$update_url = esc_attr(
			add_query_arg(
				[
					'action' => $action,
					$type    => rawurlencode( $repo_name ),
				],
				self_admin_url( 'update.php' )
			)
		);

		return $update_url;
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
		$hosted_domains = [ 'github.com', 'bitbucket.org', 'gitlab.com' ];
		$theme          = null;

		$header['enterprise_uri'] = null;
		$header['enterprise_api'] = null;
		$header['languages']      = null;
		$header['ci_job']         = false;
		$header['release_asset']  = false;

		if ( ! empty( $header['host'] ) && ! in_array( $header['host'], $hosted_domains, true ) ) {
			$header['enterprise_uri'] = $header['base_uri'];
			$header['enterprise_api'] = trim( $header['enterprise_uri'], '/' );
			switch ( $header_parts[0] ) {
				case 'GitHub':
					$header['enterprise_api'] .= '/api/v3';
					break;
				case 'GitLab':
					$header['enterprise_api'] .= '/api/v4';
					break;
				case 'Bitbucket':
					$header['enterprise_api'] .= '/rest/api';
					break;
			}
		}

		if ( $headers instanceof \WP_Theme ) {
			$theme                    = $headers;
			$headers                  = [];
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
					case 'Languages':
						$header['languages'] = $headers[ $repo_parts[ $part ] ];
						break;
					case 'CIJob':
						$header['ci_job'] = $headers[ $repo_parts[ $part ] ];
						break;
				}
			}
		}
		$header['release_asset'] = ! $header['release_asset'] && isset( $headers['Release Asset'] ) ? 'true' === $headers['Release Asset'] : $header['release_asset'];

		return $header;
	}
}
