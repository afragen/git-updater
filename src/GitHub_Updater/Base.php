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
		$this->add_extra_headers();
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
		if ( file_exists( __DIR__ . '/API/Gist_API.php' ) ) {
			self::$installed_apis['gist_api'] = true;
			self::$git_servers['gist']        = 'Gist';
		} else {
			self::$installed_apis = false;
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
		if ( ! apply_filters( 'github_updater_hide_settings', false )
			&& Singleton::get_instance( 'Init', $this )->can_update()
		) {
			Singleton::get_instance( 'Settings', $this )->run();
		}

		// Run GitHub Updater upgrade functions.
		$upgrade = new GHU_Upgrade();
		$upgrade->run();

		if ( $this->is_current_page( [ 'themes.php', 'theme-install.php' ] ) ) {
			// Load plugin stylesheet.
			add_action(
				'admin_enqueue_scripts',
				function () {
					wp_register_style( 'github-updater', plugins_url( basename( constant( __NAMESPACE__ . '\DIR' ) ) ) . '/css/github-updater.css', [], $this->get_plugin_version() );
					wp_enqueue_style( 'github-updater' );
				}
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
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
		add_action( 'ghu_get_remote_plugin', [ $this, 'run_cron_batch' ], 10, 1 );
		add_action( 'ghu_get_remote_theme', [ $this, 'run_cron_batch' ], 10, 1 );
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
	 * Make and return extra headers.
	 *
	 * @return array
	 */
	public function add_extra_headers() {
		$ghu_extra_headers = [
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
				$ghu_extra_headers[ $server . $uri_key ] = $server . $uri_value;
			}
			foreach ( self::$extra_repo_headers as $header_key => $header_value ) {
				$ghu_extra_headers[ $server . $header_key ] = $server . ' ' . $header_value;
			}
		}

		self::$extra_headers = array_unique( array_merge( self::$extra_headers, $ghu_extra_headers ) );
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
	 * @return bool
	 */
	public function get_remote_repo_meta( $repo ) {
		// Exit if non-privileged user and bypassing wp-cron.
		if ( apply_filters( 'github_updater_disable_wpcron', false ) && ! Singleton::get_instance( 'Init', $this )->can_update() ) {
			return;
		}

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
	 * @param \stdClass $repo Repo object.
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
	 * Used for renaming of sources to ensure correct directory name.
	 *
	 * @since WordPress 4.4.0 The $hook_extra parameter became available.
	 *
	 * @param string                           $source        File path of $source.
	 * @param string                           $remote_source File path of $remote_source.
	 * @param \Plugin_Upgrader|\Theme_Upgrader $upgrader      An Upgrader object.
	 * @param array                            $hook_extra    Array of hook data.
	 *
	 * @return string
	 */
	public function upgrader_source_selection( $source, $remote_source, $upgrader, $hook_extra = null ) {
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

		// Not GitHub Updater plugin/theme.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['github_updater_repo'] ) && empty( $repo ) ) {
			return $source;
		}

		Singleton::get_instance( 'Branch', $this )->set_branch_on_switch( $slug );

		/*
		 * Remote install source.
		 */
		$install_options = $this->get_class_vars( 'Install', 'install' );
		if ( empty( $repo ) && isset( $install_options['github_updater_install_repo'] ) ) {
			$slug                            = $install_options['github_updater_install_repo'];
			$new_source                      = trailingslashit( $remote_source ) . $slug;
			self::$options['remote_install'] = true;
		}

		$new_source = $this->fix_misnamed_directory( $new_source, $remote_source, $upgrader_object, $slug );

		if ( $source !== $new_source ) {
			$this->move( $source, $new_source );
		}

		return trailingslashit( $new_source );
	}

	/**
	 * Correctly rename an initially misnamed directory.
	 * This usually occurs when initial installation not using GitHub Updater.
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
	 * Update transient for rollback or branch switch.
	 *
	 * @param string    $type          plugin|theme.
	 * @param \stdClass $repo          Repo object.
	 * @param bool      $set_transient Default false, if true then set update transient.
	 *
	 * @return array $rollback Rollback transient.
	 */
	public function set_rollback_transient( $type, $repo, $set_transient = false ) {
		$repo_api = Singleton::get_instance( 'API', $this )->get_repo_api( $repo->git, $repo );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->tag     = isset( $_GET['rollback'] ) ? sanitize_text_field( wp_unslash( $_GET['rollback'] ) ) : false;
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

		$open = '<tr class="plugin-update-tr' . $active . '">
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
	 * Make branch switch row.
	 *
	 * @param array $data   Parameters for creating branch switching row.
	 * @param array $config Array of repo objects.
	 *
	 * @return void
	 */
	public function make_branch_switch_row( $data, $config ) {
		$rollback = empty( $config[ $data['slug'] ]->rollback ) ? [] : $config[ $data['slug'] ]->rollback;

		// Make the branch switch row visually appear as if it is contained with the plugin/theme's row.
		// We have to use JS for this because of the way:
		// 1) the @class of the list table row is not filterabled; and
		// 2) the list table CSS is written.
		$data_attr = $config[ $data['slug'] ]->type ? 'data-plugin' : 'data-slug';
		$file      = $config[ $data['slug'] ]->type ? $config[ $data['slug'] ]->file : $config[ $data['slug'] ]->slug;
		echo '<script>';
		// Remove the bottom "line" for the plugin's row.
		printf(
			"jQuery( 'tr:not([id])[{$data_attr}=\"%s\"]' ).addClass( 'update' );",
			$file
		);
		// Removes the bottom "line" for the shiny update row (if any).
		printf(
			"jQuery( 'tr[id][{$data_attr}=\"%s\"] td' ).css( 'box-shadow', 'none' );",
			$file
		);
		echo '</script>';

		echo '<p>';
		echo $this->get_git_icon( $file, true );
		printf(
			/* translators: 1: branch name, 2: jQuery dropdown, 3: closing tag */
			esc_html__( 'Current branch is `%1$s`, try %2$sanother version%3$s', 'github-updater' ),
			esc_attr( $data['branch'] ),
			'<a href="#" onclick="jQuery(\'#' . esc_attr( $data['id'] ) . '\').toggle();return false;">',
			'</a>.'
		);
		echo '</p>';

		print '<ul id="' . esc_attr( $data['id'] ) . '" style="display:none; width: 100%;">';

		// Disable branch switching to primary branch for release assets.
		if ( $data['release_asset'] ) {
			unset( $data['branches'][ $data['primary_branch'] ] );
		}
		if ( null !== $data['branches'] ) {
			foreach ( array_keys( $data['branches'] ) as $branch ) {
				printf(
					'<li><a href="%s%s" aria-label="' . esc_html__( 'Switch to branch ', 'github-updater' ) . esc_attr( $branch ) . '">%s</a></li>',
					esc_url( $data['nonced_update_url'] ),
					'&rollback=' . rawurlencode( $branch ),
					esc_attr( $branch )
				);
			}
		}

		if ( ! empty( $rollback ) ) {
			$rollback = array_keys( $rollback );
			usort( $rollback, 'version_compare' );
			krsort( $rollback );

			/**
			 * Filter to return the number of tagged releases (rollbacks) in branch switching.
			 *
			 * @since 9.6.0
			 * @param int Number of rollbacks. Zero implies value not set.
			 */
			$num_rollbacks = absint( apply_filters( 'github_updater_number_rollbacks', 0 ) );

			// Still only return last tag if using release assets.
			$rollback = 0 === $num_rollbacks || $data['release_asset']
				? array_slice( $rollback, 0, 1 )
				: array_splice( $rollback, 0, $num_rollbacks, true );

			foreach ( $rollback as $tag ) {
				printf(
					'<li><a href="%s%s" aria-label="' . esc_html__( 'Switch to release ', 'github-updater' ) . esc_attr( $tag ) . '">%s</a></li>',
					esc_url( $data['nonced_update_url'] ),
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
	 * @param string $file        Plugin or theme file.
	 * @param bool   $add_padding Whether or not to adding padding to the icon.
	 *                            When used in row meta, icon should not have padding;
	 *                            when used in branch switching row, icon should have padding.
	 * @return string
	 */
	public function get_git_icon( $file, $add_padding ) {
		$type     = false !== strpos( current_filter(), 'plugin' ) ? 'plugin' : 'theme';
		$type_cap = ucfirst( $type );
		$filepath = 'plugin' === $type ? WP_PLUGIN_DIR . "/$file" : get_theme_root() . "/$file/style.css";

		$git_headers = [
			"GitHub{$type_cap}URI"    => "GitHub {$type_cap} URI",
			"GitLab{$type_cap}URI"    => "GitLab {$type_cap} URI",
			"Bitbucket{$type_cap}URI" => "Bitbucket {$type_cap} URI",
			"Gitea{$type_cap}URI"     => "Gitea {$type_cap} URI",
			"Gist{$type_cap}URI"      => "Gist {$type_cap} URI",
		];
		$git_icons   = [
			'github'    => 'github-logo.svg',
			'gitlab'    => 'gitlab-logo.svg',
			'bitbucket' => 'bitbucket-logo.svg',
			'gitea'     => 'gitea-logo.svg',
			'gist'      => 'github-logo.svg',
		];

		// Skip on mu-plugins or drop-ins.
		$file_data = file_exists( $filepath ) ? get_file_data( $filepath, $git_headers ) : [];

		/**
		 * Insert repositories added via GitHub Updater Additions plugin.
		 *
		 * @see GitHub Updater's Plugin or Theme class for definition.
		 * @link https://github.com/afragen/github-updater-additions
		 */
		$additions = apply_filters( 'github_updater_additions', null, [], $type );
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
					plugins_url( basename( constant( __NAMESPACE__ . '\DIR' ) ) . '/assets/' . $git_icons[ strtolower( $githost ) ] ),
					$add_padding ? $padding : '',
					$githost
				);
				break;
			}
		}

		return isset( $icon ) ? $icon : null;
	}
}
