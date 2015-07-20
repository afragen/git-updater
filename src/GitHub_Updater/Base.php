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
 * Update a WordPress plugin or theme from a Git-based repo.
 *
 * Class    Base
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
	 * Variable for setting update transient hours.
	 *
	 * @var integer
	 */
	protected static $hours;

	/**
	 * Variable for holding transient ids.
	 *
	 * @var array
	 */
	protected static $transients = array();

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
	);

	/**
	 * Constructor.
	 * Loads options to private static variable.
	 */
	public function __construct() {
		self::$options = get_site_option( 'github_updater', array() );
		$this->add_headers();

		/*
		 * Calls in init hook for user capabilities.
		 */
		add_action( 'init', array( &$this, 'init' ) );
		add_action( 'init', array( &$this, 'remote_update' ) );
	}

	/**
	 * Instantiate Plugin, Theme, and Settings for proper user capabilities.
	 */
	public function init() {
		if ( current_user_can( 'update_plugins' ) ) {
			new Plugin();
		}
		if ( current_user_can( 'update_themes' ) ) {
			new Theme();
		}
		if ( is_admin() && ( current_user_can( 'update_plugins' ) || current_user_can( 'update_themes' ) ) ) {
			new Settings();
		}
	}

	/**
	 * Load class for remote updating compatibility.
	 *
	 * @return \Fragen\GitHub_Updater\Remote_Update
	 */
	public function remote_update() {
		if ( current_user_can( 'update_plugins' ) || current_user_can( 'update_themes' ) ) {
			return new Remote_Update();
		}
	}

	/**
	 * Add extra headers via filter hooks.
	 */
	public function add_headers() {
		add_filter( 'extra_plugin_headers', array( &$this, 'add_plugin_headers' ) );
		add_filter( 'extra_theme_headers', array( &$this, 'add_theme_headers' ) );
	}

	/**
	 * Add extra headers to get_plugins().
	 *
	 * @param $extra_headers
	 *
	 * @return array
	 */
	public function add_plugin_headers( $extra_headers ) {
		$ghu_extra_headers = array(
			'Requires WP'  => 'Requires WP',
			'Requires PHP' => 'Requires PHP',
		);

		foreach ( self::$git_servers as $server ) {
			$ghu_extra_headers[ $server . 'Plugin URI' ] = $server . ' Plugin URI';
			foreach ( self::$extra_repo_headers as $header ) {
				$ghu_extra_headers[ $server . ' ' . $header ] = $server . ' ' . $header;
			}
		}

		self::$extra_headers = array_unique( array_merge( self::$extra_headers, $ghu_extra_headers ) );
		$extra_headers       = array_merge( (array) $extra_headers, (array) $ghu_extra_headers );

		return $extra_headers;
	}

	/**
	 * Add extra headers to wp_get_themes().
	 *
	 * @param $extra_headers
	 *
	 * @return array
	 */
	public function add_theme_headers( $extra_headers ) {
		$ghu_extra_headers = array(
			'Requires WP'  => 'Requires WP',
			'Requires PHP' => 'Requires PHP',
		);

		foreach ( self::$git_servers as $server ) {
			$ghu_extra_headers[ $server . ' Theme URI' ] = $server . ' Theme URI';
			foreach ( self::$extra_repo_headers as $header ) {
				$ghu_extra_headers[ $server . ' ' . $header ] = $server . ' ' . $header;
			}
		}

		self::$extra_headers = array_unique( array_merge( self::$extra_headers, $ghu_extra_headers ) );
		$extra_headers       = array_merge( (array) $extra_headers, (array) $ghu_extra_headers );

		return $extra_headers;
	}

	/**
	 * Get details of Git-sourced plugins from those that are installed.
	 *
	 * @return array Indexed array of associative arrays of plugin details.
	 */
	protected function get_plugin_meta() {
		/*
		 * Ensure get_plugins() function is available.
		 */
		include_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		$plugins        = get_plugins();
		$git_plugins    = array();
		$update_plugins = get_site_transient( 'update_plugins' );
		if ( empty( $update_plugins) ) {
			wp_update_plugins();
			$update_plugins = get_site_transient( 'update_plugins' );
		}
		$all_plugins    = $update_plugins ? array_merge( (array) $update_plugins->response, (array) $update_plugins->no_update ) : array();

		foreach ( (array) $plugins as $plugin => $headers ) {
			$git_plugin = array();

			if ( empty( $headers['GitHub Plugin URI'] ) &&
			     empty( $headers['Bitbucket Plugin URI'] ) &&
			     empty( $headers['GitLab Plugin URI'] )
			) {
				continue;
			}

			foreach ( (array) self::$extra_headers as $value ) {
				$repo_enterprise_uri = null;

				if ( empty( $headers[ $value ] ) ||
				     false === stristr( $value, 'Plugin' )
				) {
					continue;
				}

				$header_parts = explode( ' ', $value );
				$repo_parts   = $this->_get_repo_parts( $header_parts[0], 'plugin' );

				if ( $repo_parts['bool'] ) {
					$header = $this->parse_header_uri( $headers[ $value ] );
				}

				$self_hosted_parts = array_diff( array_keys( self::$extra_repo_headers ), array( 'branch' ) );
				foreach ( $self_hosted_parts as $part ) {
					if ( array_key_exists( $repo_parts[ $part ], $headers ) &&
					     ! empty( $headers[ $repo_parts[ $part ] ] )
					) {
						$repo_enterprise_uri = $headers[ $repo_parts[ $part ] ];
					}
				}

				if ( ! empty( $repo_enterprise_uri ) ) {
					$repo_enterprise_uri = trim( $repo_enterprise_uri, '/' );
					switch( $header_parts[0] ) {
						case 'GitHub':
							$repo_enterprise_uri = $repo_enterprise_uri . '/api/v3';
							break;
						case 'GitLab':
							$repo_enterprise_uri = $repo_enterprise_uri . '/api/v3';
							break;
					}
				}

				$git_plugin['type']                    = $repo_parts['type'];
				$git_plugin['uri']                     = $repo_parts['base_uri'] . $header['owner_repo'];
				$git_plugin['enterprise']              = $repo_enterprise_uri;
				$git_plugin['owner']                   = $header['owner'];
				$git_plugin['repo']                    = $header['repo'];
				$git_plugin['extended_repo']           = implode( '-', array( $repo_parts['git_server'], $header['owner'], $header['repo'] ) );
				$git_plugin['branch']                  = $headers[ $repo_parts['branch'] ];
				$git_plugin['slug']                    = $plugin;
				$git_plugin['local_path']              = WP_PLUGIN_DIR . '/' . $header['repo'] . '/';
				$git_plugin['local_path_extended']     = WP_PLUGIN_DIR . '/' . $git_plugin['extended_repo'] . '/';

				$plugin_data                           = get_plugin_data( WP_PLUGIN_DIR . '/' . $git_plugin['slug'] );
				$git_plugin['author']                  = $plugin_data['AuthorName'];
				$git_plugin['name']                    = $plugin_data['Name'];
				$git_plugin['local_version']           = strtolower( $plugin_data['Version'] );
				$git_plugin['sections']['description'] = $plugin_data['Description'];
				$git_plugin['dot_org']                 = false;
			}
			if ( isset( $all_plugins[ $plugin ]->id) && 'master' === $git_plugin['branch'] ) {
				$git_plugin['dot_org']                 = true;
			}

			$git_plugins[ $git_plugin['repo'] ] = (object) $git_plugin;
		}

		return $git_plugins;
	}

	/**
	 * Reads in WP_Theme class of each theme.
	 * Populates variable array.
	 */
	protected function get_theme_meta() {
		$git_themes = array();
		$themes     = wp_get_themes( array( 'errors' => null ) );

		foreach ( (array) $themes as $theme ) {
			$git_theme           = array();
			$repo_uri            = null;
			$repo_enterprise_uri = null;

			foreach ( (array) self::$extra_headers as $value ) {

				$repo_uri = $theme->get( $value );
				if ( empty( $repo_uri ) ||
				     false === stristr( $value, 'Theme' )
				) {
					continue;
				}

				$header_parts = explode( ' ', $value );
				$repo_parts   = $this->_get_repo_parts( $header_parts[0], 'theme' );

				if ( $repo_parts['bool'] ) {
					$header = $this->parse_header_uri( $repo_uri );
				}

				$self_hosted_parts = array_diff( array_keys( self::$extra_repo_headers ), array( 'branch' ) );
				foreach ( $self_hosted_parts as $part ) {
					$self_hosted = $theme->get( $repo_parts[ $part ] );

					if ( ! empty( $self_hosted ) ) {
						$repo_enterprise_uri = $self_hosted;
					}
				}

				if ( ! empty( $repo_enterprise_uri ) ) {
					$repo_enterprise_uri = trim( $repo_enterprise_uri, '/' );
					switch( $header_parts[0] ) {
						case 'GitHub':
							$repo_enterprise_uri = $repo_enterprise_uri . '/api/v3';
							break;
						case 'GitLab':
							$repo_enterprise_uri = $repo_enterprise_uri . '/api/v3';
							break;
					}
				}

				$git_theme['type']                    = $repo_parts['type'];
				$git_theme['uri']                     = $repo_parts['base_uri'] . $header['owner_repo'];
				$git_theme['enterprise']              = $repo_enterprise_uri;
				$git_theme['owner']                   = $header['owner'];
				$git_theme['repo']                    = $header['repo'];
				$git_theme['extended_repo']           = $header['repo'];
				$git_theme['name']                    = $theme->get( 'Name' );
				$git_theme['theme_uri']               = $theme->get( 'ThemeURI' );
				$git_theme['author']                  = $theme->get( 'Author' );
				$git_theme['local_version']           = strtolower( $theme->get( 'Version' ) );
				$git_theme['sections']['description'] = $theme->get( 'Description' );
				$git_theme['local_path']              = get_theme_root() . '/' . $git_theme['repo'] .'/';
				$git_theme['local_path_extended']     = null;
				$git_theme['branch']                  = $theme->get( $repo_parts['branch'] );
			}

			/*
			 * Exit if not git hosted theme.
			 */
			if ( empty( $git_theme ) ) {
				continue;
			}

			$git_themes[ $git_theme['repo'] ] = (object) $git_theme;
		}

		return $git_themes;
	}

	/**
	 * Set default values for plugin/theme.
	 *
	 * @param $type
	 */
	protected function set_defaults( $type ) {
		if ( ! isset( self::$options['branch_switch'] ) ) {
			self::$options['branch_switch']      = null;
		}
		if ( ! isset( self::$options[ $this->$type->repo ] ) ) {
			self::$options[ $this->$type->repo ] = null;
			add_site_option( 'github_updater', self::$options );
		}

		$this->$type->remote_version        = '0.0.0';
		$this->$type->newest_tag            = '0.0.0';
		$this->$type->download_link         = null;
		$this->$type->tags                  = array();
		$this->$type->rollback              = array();
		$this->$type->branches              = array();
		$this->$type->requires              = null;
		$this->$type->tested                = null;
		$this->$type->donate                = null;
		$this->$type->contributors          = array();
		$this->$type->downloaded            = 0;
		$this->$type->last_updated          = null;
		$this->$type->rating                = 0;
		$this->$type->num_ratings           = 0;
		$this->$type->transient             = array();
		$this->$type->repo_meta             = array();
		$this->$type->private               = true;
		$this->$type->watchers              = 0;
		$this->$type->forks                 = 0;
		$this->$type->open_issues           = 0;
		$this->$type->score                 = 0;
		$this->$type->requires_wp_version   = '3.8.0';
		$this->$type->requires_php_version  = '5.3';
	}

	/**
	 * Rename the zip folder to be the same as the existing repository folder.
	 *
	 * Github delivers zip files as <User>-<Repo>-<Branch|Hash>.zip
	 *
	 * @global object $wp_filesystem
	 *
	 * @param string $source
	 * @param string $remote_source
	 * @param object $upgrader
	 *
	 * @return string $source|$corrected_source
	 */
	public function upgrader_source_selection( $source, $remote_source , $upgrader ) {

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		global $wp_filesystem;
		$repo        = null;
		$matched     = false;
		$source_base = basename( $source );

		/*
		 * Check for upgrade process, return if both are false or
		 * not of same updater.
		 */
		if ( ( ! $upgrader instanceof \Plugin_Upgrader && ! $upgrader instanceof \Theme_Upgrader ) ||
		     ( $upgrader instanceof \Plugin_Upgrader && ! $this instanceof Plugin ) ||
		     ( $upgrader instanceof \Theme_Upgrader  && ! $this instanceof Theme )
		) {
			return $source;
		}

		/*
		 * Re-create $upgrader object for iThemes Sync
		 * and possibly other remote upgrade services.
		 */
		if ( $upgrader instanceof \Plugin_Upgrader &&
		     isset( $upgrader->skin->plugin_info )
		) {
			$_upgrader = new \Plugin_Upgrader( $skin = new \Bulk_Plugin_Upgrader_Skin() );
			$_upgrader->skin->plugin_info = $upgrader->skin->plugin_info;
			$upgrader = new \Plugin_Upgrader( $skin = new \Bulk_Plugin_Upgrader_Skin() );
			$upgrader->skin->plugin_info = $_upgrader->skin->plugin_info;
		}
		if ( $upgrader instanceof \Theme_Upgrader &&
		     isset( $upgrader->skin->theme_info )
		) {
			$_upgrader = new \Theme_Upgrader( $skin = new \Bulk_Theme_Upgrader_Skin() );
			$_upgrader->skin->theme_info = $upgrader->skin->theme_info;
			$upgrader = new \Theme_Upgrader( $skin = new \Bulk_Theme_Upgrader_Skin() );
			$upgrader->skin->theme_info = $_upgrader->skin->theme_info;
		}

		/*
		 * Get repo for remote install update process.
		 */
		if ( ! empty( self::$options['github_updater_install_repo'] ) ) {
			$repo = self::$options['github_updater_install_repo'];
		}

		/*
		 * Get/set $repo for updating.
		 */
		if ( empty( $repo ) ) {
			$updates = $this->get_updating_repos();
			foreach ( $updates as $extended => $update ) {

				/*
				 * Plugin renaming.
				 */
				if ( $upgrader instanceof \Plugin_Upgrader ) {

					if ( $upgrader->skin instanceof \Plugin_Upgrader_Skin &&
					     $update === dirname( $upgrader->skin->plugin ) ||
					     $extended === dirname( $upgrader->skin->plugin )
					) {
						$matched = true;
					} else {
						foreach ( self::$git_servers as $git ) {
							$header = $this->parse_header_uri( $upgrader->skin->plugin_info[ $git . ' Plugin URI' ] );
							if ( $update === $header['repo'] ) {
								$matched = true;
								break;
							}
						}
					}

					if ( $matched ) {
						if ( ( ! defined( 'GITHUB_UPDATER_EXTENDED_NAMING' ) ||
						       ( defined( 'GITHUB_UPDATER_EXTENDED_NAMING' ) && ! GITHUB_UPDATER_EXTENDED_NAMING ) ) ||
						     ( $this->config[ $update ]->dot_org &&
						       ( ( ! $this->tag && 'master' === $this->config[ $update ]->branch ) ||
						         ( $this->tag && 'master' === $this->tag) ) )
						) {
							$repo = $update;
						} else {
							$repo = $extended;
						}
						break;
					}
				}

				/*
				 * Theme renaming.
				 */
				if ( $upgrader instanceof \Theme_Upgrader &&
				     ( ( $upgrader->skin instanceof \Bulk_Theme_Upgrader_Skin &&
				         $update === $upgrader->skin->theme_info->stylesheet ) ||
				       ( $upgrader->skin instanceof \Theme_Upgrader_Skin &&
				         $update === $upgrader->skin->theme ) )
				) {
					$repo = $update;
					break;
				}
			}

			/*
			 * Return already corrected $source or wp.org $source.
			 */
			if ( empty( $repo ) ) {
				return $source;
			}
		}

		$corrected_source = trailingslashit( $remote_source ) . trailingslashit( $repo );

		$upgrader->skin->feedback(
			sprintf(
				__( 'Renaming %1$s to %2$s', 'github-updater' ) . '&#8230;',
				'<span class="code">' . $source_base . '</span>',
				'<span class="code">' . basename( $corrected_source ) . '</span>'
			)
		);

		/*
		 * If we can rename, do so and return the new name.
		 */
		if ( $wp_filesystem->move( $source, $corrected_source, true ) ) {
			$upgrader->skin->feedback( __( 'Rename successful', 'github-updater' ) . '&#8230;' );
			return $corrected_source;
		}

		/*
		 * Otherwise, return an error.
		 */
		$upgrader->skin->feedback( __( 'Unable to rename downloaded repository.', 'github-updater' ) );
		return new \WP_Error();
	}

	/**
	 * Get dashboard update requested repos and return array of slugs.
	 * Really does need $_REQUEST for remote update services.
	 *
	 * @return array
	 */
	protected function get_updating_repos() {
		$updates            = array();
		$request            = array_map( 'wp_filter_kses', $_REQUEST );
		$request            = apply_filters( 'github_updater_remote_update_request', $request );

		$request['plugins'] = isset( $request['plugins'] ) ? $request['plugins'] : array();
		$request['plugin']  = isset( $request['plugin'] ) ? (array) $request['plugin'] : array();
		$request['themes']  = isset( $request['themes'] ) ? $request['themes'] : array();
		$request['theme']   = isset( $request['theme'] ) ? (array) $request['theme'] : array();

		if ( ! empty( $request['plugins'] ) ) {
			$request['plugins'] = explode( ',', $request['plugins'] );
		}
		if ( ! empty( $request['themes']) ) {
			$request['themes'] = explode( ',', $request['themes'] );
		}

		foreach ( array_merge( $request['plugin'], $request['plugins'] ) as $update ) {
			$plugin_repo = explode( '/', $update );
			$updates[] = $plugin_repo[0];
		}

		foreach ( array_merge( $request['theme'], $request['themes'] ) as $update ) {
			$updates[] = $update;
		}

		/*
		 * Add `git-owner-repo` to index for future renaming option.
		 */
		foreach ( $updates as $key => $value ) {
			$repo = $this->get_repo_slugs( $value );
			if ( $repo['repo'] === $value || $repo['extended_repo'] === $value ) {
				unset( $updates[ $key ] );
				$updates[ $repo['extended_repo'] ] = $repo['repo'];
			}
		}

		return $updates;
	}

	/**
	 * Set array with normal and extended repo names.
	 *
	 * @param $slug
	 *
	 * @return array
	 */
	protected function get_repo_slugs( $slug ) {
		$arr = array();
		foreach ( $this->config as $repo ) {
			if ( $slug === $repo->repo || $slug === $repo->extended_repo ) {
				$arr['repo']          = $repo->repo;
				$arr['extended_repo'] = $repo->extended_repo;
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

		return $all_headers;
	}

	/**
	 * Get filename of changelog and return.
	 *
	 * @param $type
	 *
	 * @return bool or variable
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
		$wp_version_ok   = version_compare( $wp_version, $type->requires_wp_version,'>=' );
		$php_version_ok  = version_compare( PHP_VERSION, $type->requires_php_version, '>=' );

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
		$header_parts         = parse_url( $repo_header );
		$header['scheme']     = isset( $header_parts['scheme'] ) ? $header_parts['scheme'] : null;
		$header['host']       = isset( $header_parts['host'] ) ? $header_parts['host'] : null;
		$owner_repo           = trim( $header_parts['path'], '/' );  // strip surrounding slashes
		$owner_repo           = str_replace( '.git', '', $owner_repo ); //strip incorrect URI ending
		$header['path']       = $owner_repo;
		$owner_repo           = explode( '/', $owner_repo );
		$header['owner']      = $owner_repo[0];
		$header['repo']       = $owner_repo[1];
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
	private function _get_repo_parts( $repo, $type ) {
		$arr['bool'] = false;
		$pattern     = '/' . strtolower( $repo ) . '_/';
		$type        = preg_replace( $pattern, '', $type );
		$repo_types  = array(
			'GitHub'    => 'github_' . $type,
			'Bitbucket' => 'bitbucket_'. $type,
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
	 * Used to set_site_transient and checks/stores transient id in array.
	 *
	 * @param $id
	 * @param $response
	 *
	 * @return bool
	 */
	protected function set_transient( $id, $response ) {
		$transient = 'ghu-' . md5( $this->type->repo . $id );
		if ( ! in_array( $transient, self::$transients, true ) ) {
			self::$transients[] = $transient;
		}
		set_site_transient( $transient, $response, ( self::$hours * HOUR_IN_SECONDS ) );

		return true;
	}

	/**
	 * Returns site_transient and checks/stores transient id in array.
	 *
	 * @param $id
	 *
	 * @return mixed
	 */
	protected function get_transient( $id ) {
		$transient = 'ghu-' . md5( $this->type->repo . $id );
		if ( ! in_array( $transient, self::$transients, true ) ) {
			self::$transients[] = $transient;
		}

		return get_site_transient( $transient );
	}

	/**
	 * Delete all transients from array of transient ids.
	 *
	 * @param $type
	 *
	 * @return bool|void
	 */
	protected function delete_all_transients( $type ) {
		$transients = get_site_transient( 'ghu-' . $type );
		if ( ! $transients ) {
			return false;
		}

		foreach ( $transients as $transient ) {
			delete_site_transient( $transient );
		}
		delete_site_transient( 'ghu-' . $type );
	}

	/**
	 * Create transient of $type transients for force-check.
	 *
	 * @param $type
	 *
	 * @return void|bool
	 */
	protected function make_force_check_transient( $type ) {
		$transient = get_site_transient( 'ghu-' . $type );
		if ( $transient ) {
			return false;
		}
		set_site_transient( 'ghu-' . $type , self::$transients, self::$hours * HOUR_IN_SECONDS );
		self::$transients = array();
	}

	/**
	 * Set repo object file info.
	 *
	 * @param $response
	 *
	 * @param $repo
	 */
	protected function set_file_info( $response, $repo ) {
		$repo_parts = $this->_get_repo_parts( $repo, $this->type->type );
		$this->type->transient            = $response;
		$this->type->remote_version       = strtolower( $response['Version'] );
		$this->type->branch               = ! empty( $response[ $repo_parts['branch'] ] ) ? $response[$repo_parts['branch'] ] : 'master';
		$this->type->requires_php_version = ! empty( $response['Requires PHP'] ) ? $response['Requires PHP'] : $this->type->requires_php_version;
		$this->type->requires_wp_version  = ! empty( $response['Requires WP'] ) ? $response['Requires WP'] : $this->type->requires_wp_version;
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
						if ( isset( $tag->name ) && isset( $tag->zipball_url ) ) {
							$tags[]                 = $tag->name;
							$rollback[ $tag->name ] = $tag->zipball_url;
						}
					}
					break;
				case 'bitbucket':
					foreach ( (array) $response as $num => $tag ) {
						$download_base = implode( '/', array( $repo_type['base_download'], $this->type->owner, $this->type->repo, 'get/' ) );
						if ( isset( $num ) ) {
							$tags[]           = $num;
							$rollback[ $num ] = $download_base . $num . '.zip';
						}
					}
					break;
				case 'gitlab':
					foreach ( (array) $response as $tag ) {
						$download_link = implode( '/', array( $repo_type['base_download'], $this->type->owner, $this->type->repo, 'repository/archive.zip' ) );
						$download_link = add_query_arg( 'ref', $tag->name, $download_link );
						if ( isset( $tag->name) ) {
							$tags[] = $tag->name;
							$rollback[ $tag->name ] = $download_link;
						}
					}
					break;
			}

		}
		if ( empty( $tags ) ) {
			return false;
		}

		usort( $tags, 'version_compare' );
		krsort( $rollback );

		$newest_tag             = null;
		$newest_tag_key         = key( array_slice( $tags, -1, 1, true ) );
		$newest_tag             = $tags[ $newest_tag_key ];

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
			$readme['sections/' . $section ] = $value;
		}
		foreach ( $readme as $key => $value ) {
			$key = explode( '/', $key );
			if ( ! empty( $value ) && 'sections' === $key[0] ) {
				unset( $response['sections'][ $key[1] ] );
			}
		}

		unset( $response['sections']['screenshots'] );
		unset( $response['sections']['installation'] );
		$this->type->sections     = array_merge( (array) $this->type->sections, (array) $response['sections'] );
		$this->type->tested       = $response['tested_up_to'];
		$this->type->requires     = $response['requires_at_least'];
		$this->type->donate       = $response['donate_link'];
		$this->type->contributors = $response['contributors'];

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

}
