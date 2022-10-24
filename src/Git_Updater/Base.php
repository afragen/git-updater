<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater;

use Fragen\Singleton;
use Fragen\Git_Updater\Traits\GU_Trait;
use Fragen\Git_Updater\Traits\Basic_Auth_Loader;
use Fragen\Git_Updater\API\Language_Pack_API;
use Fragen\Git_Updater\PRO\Branch;

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

	use GU_Trait, Basic_Auth_Loader;

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
		static::$options = get_site_option( 'git_updater', [] );
		$this->set_installed_apis();
		$this->add_extra_headers();
	}

	/**
	 * Set boolean for installed API classes.
	 */
	protected function set_installed_apis() {
		/**
		 * Filter to add active git servers.
		 *
		 * @since 10.0.0
		 * @param array static::$git_servers Array of git servers.
		 */
		static::$git_servers = \apply_filters( 'gu_git_servers', static::$git_servers );

		/**
		 * Filter to add installed APIs.
		 *
		 * @since 10.0.0
		 * @param array static::$installed_apis Array of installed APIs.
		 */
		static::$installed_apis = \apply_filters( 'gu_installed_apis', static::$installed_apis );
	}

	/**
	 * Load Plugin, Theme, and Settings with correct capabiltiies and on selective admin pages.
	 *
	 * @return bool
	 */
	public function load() {
		/**
		 * Filters whether to hide settings.
		 *
		 * @since 10.0.0
		 * @param bool
		 */
		$hide_settings = (bool) apply_filters( 'gu_hide_settings', false );

		/**
		 * Filters whether to hide settings.
		 *
		 * @return bool
		 */
		$hide_settings = $hide_settings ?: (bool) apply_filters_deprecated( 'github_updater_hide_settings', [ false ], '10.0.0', 'gu_hide_settings' );

		if ( ! $hide_settings && Singleton::get_instance( 'Init', $this )->can_update() ) {
			Singleton::get_instance( 'Settings', $this )->run();
			Singleton::get_instance( 'Add_Ons', $this )->load_hooks();
		}

		// Run Git Updater upgrade functions.
		( new GU_Upgrade() )->run();

		if ( $this->is_current_page( [ 'plugins.php', 'themes.php', 'theme-install.php' ] ) ) {
			// Load plugin stylesheet.
			add_action(
				'admin_enqueue_scripts',
				function () {
					wp_register_style( 'git-updater', plugins_url( basename( dirname( __DIR__, 2 ) ) ) . '/css/git-updater.css', [], $this->get_plugin_version() );
					wp_enqueue_style( 'git-updater' );
				}
			);
		}

		if ( isset( $_POST['_wpnonce'], $_POST['gu_refresh_cache'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'gu_refresh_cache' ) ) {
			/**
			 * Fires later in cycle when Refreshing Cache.
			 *
			 * @since 6.0.0
			 */
			do_action_deprecated( 'ghu_refresh_transients', [], '10.0.0', 'gu_refresh_transients' );

			/**
			 * Fires later in cycle when Refreshing Cache.
			 *
			 * @since 10.0.0
			 */
			do_action( 'gu_refresh_transients' );
		}

		$this->get_meta_plugins();
		$this->get_meta_themes();

		return true;
	}

	/**
	 * Performs actual plugin metadata fetching.
	 */
	public function get_meta_plugins() {
		Singleton::get_instance( 'Plugin', $this )->get_remote_plugin_meta();
	}

	/**
	 * Performs actual theme metadata fetching.
	 */
	public function get_meta_themes() {
		Singleton::get_instance( 'Theme', $this )->get_remote_theme_meta();
	}

	/**
	 * Run background processes.
	 * Piggyback on built-in update function to get metadata.
	 * Set update transients for remote management.
	 */
	public function background_update() {
		add_action( 'wp_update_plugins', [ $this, 'get_meta_plugins' ] );
		add_action( 'wp_update_themes', [ $this, 'get_meta_themes' ] );
		add_action( 'gu_get_remote_plugin', [ $this, 'run_cron_batch' ], 10, 1 );
		add_action( 'gu_get_remote_theme', [ $this, 'run_cron_batch' ], 10, 1 );
	}

	/**
	 * Allows developers to use 'gu_set_options' hook to set access tokens or other settings.
	 * Saves results of filter hook to self::$options.
	 * Single plugin/theme should not be using both hooks.
	 *
	 * Hook requires return of associative element array.
	 * $key === repo-name and $value === token
	 * e.g.  array( 'repo-name' => 'access_token' );
	 */
	public function set_options_filter() {
		/**
		 * Filter the plugin options.
		 *
		 * @since 10.0.0
		 *
		 * @return null|array
		 */
		$config = apply_filters( 'gu_set_options', null );

		/**
		 * Filter the plugin options.
		 *
		 * @return null|array
		 */
		$config = null === $config ? apply_filters_deprecated( 'github_updater_set_options', [ null ], '6.1.0', 'gu_set_options' ) : $config;

		if ( ! empty( $config ) ) {
			$config        = $this->sanitize( $config );
			self::$options = array_merge( get_site_option( 'git_updater' ), $config );
			update_site_option( 'git_updater', self::$options );
		}
	}

	/**
	 * Make and return extra headers.
	 *
	 * @return array
	 */
	public function add_extra_headers() {
		$gu_extra_headers = [
			'RequiresWP'    => 'Requires WP',
			'ReleaseAsset'  => 'Release Asset',
			'PrimaryBranch' => 'Primary Branch',
		];

		$uri_types = [
			'PluginURI' => ' Plugin URI',
			'ThemeURI'  => ' Theme URI',
		];

		foreach ( self::$git_servers as $server ) {
			foreach ( $uri_types as $uri_key => $uri_value ) {
				$gu_extra_headers[ $server . $uri_key ] = $server . $uri_value;
			}
			foreach ( self::$extra_repo_headers as $header_key => $header_value ) {
				$gu_extra_headers[ $server . $header_key ] = $server . ' ' . $header_value;
			}
		}

		self::$extra_headers = array_unique( array_merge( self::$extra_headers, $gu_extra_headers ) );
		ksort( self::$extra_headers );

		return self::$extra_headers;
	}

	/**
	 * Runs on wp-cron job to get remote repo meta in background.
	 *
	 * @param array $batches Cron event args, array of repo objects.
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
	 * @param \stdClass $repo Repo object.
	 *
	 * @return bool|\stdClass
	 */
	public function get_remote_repo_meta( $repo ) {
		// Exit if non-privileged user and bypassing wp-cron.

		/**
		 * Exit if bypassing wp-cron.
		 *
		 * @since 10.0.0
		 *
		 * @param bool
		 */
		$disable_wp_cron = (bool) apply_filters( 'gu_disable_wpcron', false );

		/**
		 * Exit if bypassing wp-cron.
		 *
		 * @return bool
		 */
		$disable_wp_cron = $disable_wp_cron ?: (bool) apply_filters_deprecated( 'github_updater_disable_wpcron', [ false ], '10.0.0', 'gu_disable_wpcron' );

		if ( $disable_wp_cron && ! Singleton::get_instance( 'Init', $this )->can_update() ) {
			return;
		}

		$file = 'style.css';
		if ( false !== stripos( $repo->type, 'plugin' ) ) {
			$file = basename( $repo->file );
		}

		$repo_api = Singleton::get_instance( 'API\API', $this )->get_repo_api( $repo->git, $repo );
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

		// Return data if being called from Git Updater PRO REST API.
		if ( class_exists( 'Fragen\Git_Updater\PRO\REST\REST_API' )
			&& $this->caller instanceof \Fragen\Git_Updater\PRO\REST\REST_API
		) {
			return $repo;
		}

		return true;
	}

	/**
	 * Set default values for plugin/theme.
	 *
	 * @param string $type (plugin|theme).
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
			add_site_option( 'git_updater', self::$options );
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
	 * @param \stdClass $repo Repo object.
	 *
	 * @return bool|string
	 */
	public function get_changelog_filename( $repo ) {
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
	 * Used for renaming of sources to ensure correct directory name.
	 *
	 * @since WordPress 4.4.0 The $hook_extra parameter became available.
	 *
	 * @param string                           $source        File path of $source.
	 * @param string                           $remote_source File path of $remote_source.
	 * @param \Plugin_Upgrader|\Theme_Upgrader $upgrader      An Upgrader object.
	 * @param array                            $hook_extra    Array of hook data.
	 *
	 * @return string|\WP_Error
	 */
	public function upgrader_source_selection( $source, $remote_source, $upgrader, $hook_extra = null ) {
		global $wp_filesystem;

		$slug            = null;
		$repo            = null;
		$new_source      = null;
		$upgrader_object = null;
		$remote_source   = $wp_filesystem->wp_content_dir() . 'upgrade/';

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

		// Not Git Updater plugin/theme.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['git_updater_repo'] ) && empty( $repo ) ) {
			return $source;
		}

		// Skip if Git Updater PRO being updated for new rollback update failure.
		if ( 'git-updater-pro' !== $slug && $this->is_premium_only() ) {
			( new Branch() )->set_branch_on_switch( $slug );

			/*
			* Remote install source.
			*/
			$install_options = $this->get_class_vars( 'Fragen\Git_Updater\PRO\Install', 'install' );
			if ( empty( $repo ) && isset( $install_options['git_updater_install_repo'] ) ) {
				$slug                            = $install_options['git_updater_install_repo'];
				$new_source                      = trailingslashit( $remote_source ) . $slug;
				self::$options['remote_install'] = true;
			}
		}

		$new_source = $this->fix_misnamed_directory( $new_source, $remote_source, $upgrader_object, $slug );

		if ( $source !== $new_source ) {
			$result = move_dir( $source, $new_source );
			if ( \is_wp_error( $result ) ) {
				return $result;
			}
		}
		// Clean up $new_source directory.
		add_action( 'upgrader_install_package_result', [ $this, 'delete_upgrade_source' ], 10, 2 );

		return trailingslashit( $new_source );
	}

	/**
	 * Correctly rename an initially misnamed directory.
	 * This usually occurs when initial installation not using Git Updater.
	 * May cause plugin/theme deactivation.
	 *
	 * @param string       $new_source      File path of $new_source.
	 * @param string       $remote_source   File path of $remote_source.
	 * @param Plugin|Theme $upgrader_object An Upgrader object.
	 * @param string       $slug            Repository slug.
	 *
	 * @return string $new_source
	 */
	private function fix_misnamed_directory( $new_source, $remote_source, $upgrader_object, $slug ) {
		$config = $this->get_class_vars( ( new \ReflectionClass( $upgrader_object ) )->getShortName(), 'config' );

		if ( ! array_key_exists( $slug, (array) $config ) && ! isset( self::$options['remote_install'] ) ) {
			$repo         = $this->get_repo_slugs( $slug, $upgrader_object );
			$repo['slug'] = isset( $repo['slug'] ) ? $repo['slug'] : $slug;
			$slug         = $slug === $repo['slug'] ? $slug : $repo['slug'];
			$new_source   = trailingslashit( $remote_source ) . $slug;
		}

		return $new_source;
	}


	/**
	 * Return correct update row opening and closing tags for Shiny Updates.
	 *
	 * @param string $repo_name       Repo name.
	 * @param string $type            plugin|theme.
	 * @param bool   $branch_switcher Boolean for using branch switcher, default is false.
	 *
	 * @return array
	 */
	public function update_row_enclosure( $repo_name, $type, $branch_switcher = false ) {
		global $wp_version;
		$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
		$repo_base     = $repo_name;
		$shiny_classes = 'notice inline notice-warning notice-alt';

		$active = '';
		if ( 'plugin' === $type ) {
			$repo_base = dirname( $repo_name );
			if ( is_plugin_active( $repo_name ) ) {
				$active = ' active';
			}
		} else {
			$theme = wp_get_theme( $repo_name );
			if ( $theme->is_allowed( 'network' ) ) {
				$active = ' active';
			}
		}

		$multisite_theme_open = ! $branch_switcher && 'theme' === $type ? " id='{$repo_name}' data-slug='{$repo_name}'" : null;

		$open = '<tr class="git-updater plugin-update-tr' . $active . '"' . $multisite_theme_open . '>
		<td colspan="' . $wp_list_table->get_column_count() . '" class="plugin-update colspanchange">
		<div class="">';

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
	 * Generate update URL.
	 *
	 * @param string $type      ( plugin or theme ).
	 * @param string $action    Query action.
	 * @param string $repo_name Repo name.
	 *
	 * @return string
	 */
	public function get_update_url( $type, $action, $repo_name ) {
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
	 * Add git host based icons.
	 *
	 * @param array  $links Row meta action links.
	 * @param string $file  Plugin or theme file.
	 *
	 * @return array $links
	 */
	public function row_meta_icons( $links, $file ) {
		$icon = $this->get_git_icon( $file, false );
		if ( ! is_null( $icon ) ) {
			$links[] = $icon;
		}

		return $links;
	}

	/**
	 * Get git host based icon as an HTML img element.
	 *
	 * @param  string $file        Plugin or theme file.
	 * @param  bool   $add_padding Whether or not to adding padding to the icon.
	 *                             When used in row meta, icon should not have padding;
	 *                             when used in branch switching row, icon should have padding.
	 * @return string
	 */
	public function get_git_icon( $file, $add_padding ) {
		$type     = str_contains( current_filter(), 'plugin' ) ? 'plugin' : 'theme';
		$type_cap = ucfirst( $type );
		$filepath = 'plugin' === $type ? WP_PLUGIN_DIR . "/$file" : get_theme_root() . "/$file/style.css";

		$git['headers'] = [ "GitHub{$type_cap}URI" => "GitHub {$type_cap} URI" ];
		$git['icons']   = [ 'github' => basename( dirname( __DIR__, 2 ) ) . '/assets/github-logo.svg' ];

		$git = apply_filters( 'gu_get_git_icon_data', $git, $type_cap );

		// Skip on mu-plugins or drop-ins.
		$file_data = file_exists( $filepath ) ? get_file_data( $filepath, $git['headers'] ) : [];

		/**
		 * Filter to add plugins not containing appropriate header line.
		 * Insert repositories added via Git Updater Additions plugin.
		 *
		 * @since   10.0.0
		 * @access  public
		 * @link https://github.com/afragen/git-updater-additions
		 *
		 * @param array        Listing of plugins/themes to add.
		 *                     Default null.
		 * @param array        Listing of all plugins/themes.
		 * @param string $type Type being passed, plugin|theme'.
		 */
		$additions = apply_filters( 'gu_additions', null, [], $type );

		/**
		 * Filter to add plugins not containing appropriate header line.
		 * Insert repositories added via Git Updater Additions plugin.
		 *
		 * @since   5.4.0
		 * @access  public
		 * @link https://github.com/afragen/git-updater-additions
		 *
		 * @param array        Listing of plugins/themes to add.
		 *                     Default null.
		 * @param array        Listing of all plugins/themes.
		 * @param string $type Type being passed, plugin|theme'.
		 */
		$additions = null === $additions ? apply_filters_deprecated( 'github_updater_additions', [ null, [], $type ], '10.0.0', 'gu_additions' ) : $additions;

		foreach ( (array) $additions as $slug => $headers ) {
			if ( $slug === $file ) {
				$file_data = array_merge( $file_data, $headers );
				break;
			}
		}

		foreach ( $file_data as $key => $value ) {
			if ( ! empty( $value ) ) {
				$githost = str_replace( "{$type_cap}URI", '', $key );
				$padding = is_rtl() ? 'padding-left: 6px;' : 'padding-right: 6px;';
				$icon    = sprintf(
					'<img src="%1$s" style="vertical-align:text-bottom;%2$s" height="16" width="16" alt="%3$s" />',
					plugins_url( $git['icons'][ strtolower( $githost ) ] ),
					$add_padding ? $padding : '',
					$githost
				);
				break;
			}
		}

		return isset( $icon ) ? $icon : null;
	}
}
