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
	 * Class Object for API
	 *
	 * @var object
	 */
 	protected $repo_api;

	/**
	 * Variable for setting update transient hours
	 *
	 * @var integer
	 */
	protected static $hours;

	/**
	 * Variable for holding transient ids
	 *
	 * @var array
	 */
	protected static $transients = array();

	/**
	 * Variable for holding extra theme and plugin headers
	 *
	 * @var array
	 */
	protected static $extra_headers = array();

	/**
	 * Holds the values to be used in the fields callbacks
	 * @var array
	 */
	protected static $options;

	/**
	 * Holds HTTP error code from API call.
	 * @var array ( $this->type-repo => $code )
	 */
	protected static $error_code = array();

	/**
	 * Constructor
	 *
	 * Loads options to private static variable.
	 */
	public function __construct() {
		self::$options = get_site_option( 'github_updater', array() );
		$this->add_headers();
	}

	/**
	 * Instantiate Fragen\GitHub_Updater\Plugin and Fragen\GitHub_Updater\Theme
	 * for proper user capabilities.
	 */
	public static function init() {
		if ( current_user_can( 'update_plugins' ) ) {
			new Plugin;
		}
		if ( current_user_can( 'update_themes' ) ) {
			new Theme;
		}
		if ( is_admin() && ( current_user_can( 'update_plugins' ) || current_user_can( 'update_themes' ) ) ) {
			new Settings;
		}
	}

	/**
	 * Add extra headers via filter hooks
	 */
	public static function add_headers() {
		add_filter( 'extra_plugin_headers', array( __CLASS__, 'add_plugin_headers' ) );
		add_filter( 'extra_theme_headers', array( __CLASS__, 'add_theme_headers' ) );
	}

	/**
	 * Add extra headers to get_plugins();
	 *
	 * @param $extra_headers
	 * @return array
	 */
	public static function add_plugin_headers( $extra_headers ) {
		$ghu_extra_headers   = array(
			'GitHub Plugin URI'    => 'GitHub Plugin URI',
			'GitHub Branch'        => 'GitHub Branch',
			'GitHub Enterprise'    => 'GitHub Enterprise',
			'Bitbucket Plugin URI' => 'Bitbucket Plugin URI',
			'Bitbucket Branch'     => 'Bitbucket Branch',
			'GitLab Plugin URI'    => 'GitLab Plugin URI',
			'GitLab Branch'        => 'GitLab Branch',
			'Requires WP'          => 'Requires WP',
			'Requires PHP'         => 'Requires PHP',
		);
		self::$extra_headers = array_unique( array_merge( self::$extra_headers, $ghu_extra_headers ) );
		$extra_headers       = array_merge( (array) $extra_headers, (array) $ghu_extra_headers );

		return $extra_headers;
	}

	/**
	 * Add extra headers to wp_get_themes()
	 *
	 * @param $extra_headers
	 * @return array
	 */
	public static function add_theme_headers( $extra_headers ) {
		$ghu_extra_headers   = array(
			'GitHub Theme URI'    => 'GitHub Theme URI',
			'GitHub Branch'       => 'GitHub Branch',
			'GitHub Enterprise'   => 'GitHub Enterprise',
			'Bitbucket Theme URI' => 'Bitbucket Theme URI',
			'Bitbucket Branch'    => 'Bitbucket Branch',
			'GitLab Theme URI'    => 'GitLab Theme URI',
			'GitLab Branch'       => 'GitLab Branch',
			'Requires WP'         => 'Requires WP',
			'Requires PHP'        => 'Requires PHP',
		);
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
		/**
		 * Ensure get_plugins() function is available.
		 */
		include_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		$plugins     = get_plugins();
		$git_plugins = array();

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

				if ( array_key_exists( $repo_parts['enterprise'], $headers ) &&
				     ! empty( $headers[ $repo_parts['enterprise'] ] )
				) {
					$repo_enterprise_uri = $headers[ $repo_parts['enterprise'] ];
					$repo_enterprise_uri = trim( $repo_enterprise_uri, '/' );
					switch( $header_parts[0] ) {
						case 'GitHub':
							$repo_enterprise_uri = $repo_enterprise_uri . '/api/v3';
							break;
						case 'GitLab':
							break;
					}
				}

				$git_plugin['type']                    = $repo_parts['type'];
				$git_plugin['uri']                     = $repo_parts['base_uri'] . $header['owner_repo'];
				$git_plugin['enterprise']              = $repo_enterprise_uri;
				$git_plugin['owner']                   = $header['owner'];
				$git_plugin['repo']                    = $header['repo'];
				$git_plugin['local_path']              = WP_PLUGIN_DIR . '/' . $header['repo'] . '/';
				$git_plugin['branch']                  = $headers[ $repo_parts['branch'] ];
				$git_plugin['slug']                    = $plugin;

				$plugin_data                           = get_plugin_data( WP_PLUGIN_DIR . '/' . $git_plugin['slug'] );
				$git_plugin['author']                  = $plugin_data['AuthorName'];
				$git_plugin['name']                    = $plugin_data['Name'];
				$git_plugin['local_version']           = strtolower( $plugin_data['Version'] );
				$git_plugin['sections']['description'] = $plugin_data['Description'];
			}

			$git_plugins[ $git_plugin['repo'] ] = (object) $git_plugin;
		}

		return $git_plugins;
	}

	/**
	 * Reads in WP_Theme class of each theme.
	 * Populates variable array
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

				$repo_enterprise_uri = $theme->get( $repo_parts['enterprise'] );
				if ( ! empty( $repo_enterprise_uri ) ) {
					$repo_enterprise_uri = trim( $repo_enterprise_uri, '/' );
					switch( $header_parts[0] ) {
						case 'GitHub':
							$repo_enterprise_uri = $repo_enterprise_uri . '/api/v3';
							break;
						case 'GitLab':
							break;
					}
				}

				$git_theme['type']                    = $repo_parts['type'];
				$git_theme['uri']                     = $repo_parts['base_uri'] . $header['owner_repo'];
				$git_theme['enterprise']              = $repo_enterprise_uri;
				$git_theme['owner']                   = $header['owner'];
				$git_theme['repo']                    = $header['repo'];
				$git_theme['name']                    = $theme->get( 'Name' );
				$git_theme['theme_uri']               = $theme->get( 'ThemeURI' );
				$git_theme['author']                  = $theme->get( 'Author' );
				$git_theme['local_version']           = strtolower( $theme->get( 'Version' ) );
				$git_theme['sections']['description'] = $theme->get( 'Description' );
				$git_theme['local_path']              = get_theme_root() . '/' . $git_theme['repo'] .'/';
				$git_theme['branch']                  = $theme->get( $repo_parts['branch'] );
			}

			/**
			 * Exit if not git hosted theme.
			 */
			if ( empty( $git_theme ) ) {
				continue;
			}

			$git_themes[ $theme->stylesheet ] = (object) $git_theme;
		}

		return $git_themes;
	}

	/**
	 * Set default values for plugin/theme
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
	 * @param string $remote_source Optional.
	 * @param object $upgrader      Optional.
	 *
	 * @return string
	 */
	public function upgrader_source_selection( $source, $remote_source , $upgrader ) {

		global $wp_filesystem;
		$repo = null;

		/**
		 * Check for upgrade process, return if both are false.
		 */
		if ( ( ! $upgrader instanceof \Plugin_Upgrader ) && ( ! $upgrader instanceof \Theme_Upgrader ) ) {
			return $source;
		}

		/**
		 * Return $source if name already corrected.
		 */
		foreach ( (array) $this->config as $git_repo ) {
			if ( basename( $source ) === $git_repo->repo ) {
				return $source;
			}
		}
		if (
			( ! empty( $upgrader->skin->options['plugin' ] ) &&
			  ( basename( $source ) === $upgrader->skin->options['plugin'] ) ) ||
			( ! empty( $upgrader->skin->options['theme'] ) &&
			  ( basename( $source ) === $upgrader->skin->options['theme'] ) )
		) {
			return $source;
		}

		/**
		 * Get correct repo name based upon $upgrader instance if present.
		 */
		if ( $upgrader instanceof \Plugin_Upgrader ) {
			if ( ! empty( $upgrader->skin->options['plugin'] ) &&
			     stristr( basename( $source ), $upgrader->skin->options['plugin'] ) ) {
				$repo = $upgrader->skin->options['plugin'];
			}
		}
		if ( $upgrader instanceof \Theme_Upgrader ) {
			if ( ! empty( $upgrader->skin->options['theme'] ) &&
			     stristr( basename( $source ), $upgrader->skin->options['theme'] ) ) {
				$repo = $upgrader->skin->options['theme'];
			}
		}

		/**
		 * Get repo for automatic update process.
		 */
		if ( empty( $repo ) ) {
			foreach ( (array) $this->config as $git_repo ) {
				if ( $upgrader instanceof \Plugin_Upgrader && ( false !== stristr( $git_repo->type, 'plugin' ) ) ) {
					if ( stristr( basename( $source ), $git_repo->repo ) ) {
						$repo = $git_repo->repo;
						break;
					}
				}
				if ( $upgrader instanceof \Theme_Upgrader && ( false !== stristr( $git_repo->type, 'theme' ) ) ) {
					if ( stristr( basename( $source ), $git_repo->repo ) ) {
						$repo = $git_repo->repo;
						break;
					}
				}
			}

			/**
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
				'<span class="code">' . basename( $source ) . '</span>',
				'<span class="code">' . basename( $corrected_source ) . '</span>'
			)
		);

		/**
		 * If we can rename, do so and return the new name.
		 */
		if ( $wp_filesystem->move( $source, $corrected_source, true ) ) {
			$upgrader->skin->feedback( __( 'Rename successful', 'github-updater' ) . '&#8230;' );
			return $corrected_source;
		}

		/**
		 * Otherwise, return an error.
		 */
		$upgrader->skin->feedback( __( 'Unable to rename downloaded repository.', 'github-updater' ) );
		return new \WP_Error();
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

		/**
		 * Make sure we catch CR-only line endings.
		 */
		$file_data = str_replace( "\r", "\n", $contents );

		/**
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
	 * Get filename of changelog and return
	 *
	 * @param $type
	 *
	 * @return bool or variable
	 */
	protected function get_changelog_filename( $type ) {
		$changelogs = array( 'CHANGES.md', 'CHANGELOG.md', 'changes.md', 'changelog.md' );
		$changes    = null;

		if ( is_dir( $this->$type->local_path ) ) {
			$local_files = scandir( $this->$type->local_path );
			$changes = array_intersect( (array) $local_files, $changelogs );
			$changes = array_pop( $changes );
		}

		if ( ! empty( $changes ) ) {
			return $changes;
		}

			return false;
	}

	/**
	 * Fixes {@link https://github.com/UCF/Theme-Updater/issues/3}.
	 * Adds custom user agent for GitHub Updater.
	 *
	 * @param  array $args Existing HTTP Request arguments.
	 *
	 * @return array Amended HTTP Request arguments.
	 */
	public function http_request_args( $args, $url ) {
		$args['sslverify'] = false;
		if ( false === stristr( $args['user-agent'], 'GitHub Updater' ) ) {
			$args['user-agent'] = $args['user-agent'] . '; GitHub Updater - https://github.com/afragen/github-updater';
		}

		return $args;
	}

	/**
	 * Used to set_site_transient and checks/stores transient id in array
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
	 * Returns site_transient and checks/stores transient id in array
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
	 * Delete all transients from array of transient ids
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
	 * Create transient of $type transients for force-check
	 *
	 * @param $type
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
	 * Create some sort of rating from 0 to 100 for use in star ratings
	 * I'm really just making this up, more based upon popularity
	 *
	 * @param $repo_meta
	 *
	 * @return float|int
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

		return $rating;
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
		$php_version_ok  = version_compare( phpversion(), $type->requires_php_version, '>=' );

		return $remote_is_newer && $wp_version_ok && $php_version_ok;
	}

	/**
	 * Display message when API returns other than 200 or 404.
	 * Usually 403 as API rate limit max out or private repo with no token set.
	 *
	 * @return bool
	 */
	protected function create_error_message() {
		global $pagenow;
		$update_pages   = array( 'update-core.php', 'plugins.php', 'themes.php' );
		$settings_pages = array( 'settings.php', 'options-general.php' );

		if (
			! in_array( $pagenow, array_merge( $update_pages, $settings_pages ) ) ||
			( in_array( $pagenow, $settings_pages ) && 'github-updater' !== $_GET['page'] )
		) {
			return false;
		}

		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
				add_action( 'admin_notices', array( $this, 'show_error_message' ) );
				add_action( 'network_admin_notices', array( $this, 'show_error_message' ) );
		}
	}

	/**
	 * Display error message.
	 */
	public function show_error_message() {
		?>
		<div class="error notice is-dismissible">
			<p>
				<?php
					printf( __( '%s was not checked. GitHub Updater Error Code:', 'github-updater' ),
						'<strong>' . $this->type->name . '</strong>'
					);
					echo ' ' . self::$error_code[ $this->type->repo ];
				?>
				<?php if ( 403 === self::$error_code[ $this->type->repo ] && false !== stristr( $this->type->type, 'github' ) ): ?>
					<br>
					<?php
						printf( __( 'GitHub API\'s rate limit will reset in %s minutes.', 'github-updater' ),
							self::$error_code[ $this->type->repo . '-wait' ]
						);
						echo '<br>';
						printf(
							__( 'It looks like you are running into GitHub API rate limits. Be sure and configure a %sPersonal Access Token%s to avoid this issue.', 'github-updater' ),
							'<a href="https://help.github.com/articles/creating-an-access-token-for-command-line-use/">',
							'</a>'
						);
					?>
				<?php endif; ?>
				<?php if ( 401 === self::$error_code[ $this->type->repo ] ) : ?>
					<br>
					<?php _e( 'There is probably an error on the GitHub Updater Settings page.', 'github-updater' ); ?>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Parse URI param returning array of parts.
	 *
	 * @param $repo_header
	 *
	 * @return array
	 */
	protected static function parse_header_uri( $repo_header ) {
		$header_parts         = parse_url( $repo_header );
		$header['scheme']     = isset( $header_parts['scheme'] ) ? $header_parts['scheme'] : null;
		$header['host']       = isset( $header_parts['host'] ) ? $header_parts['host'] : null;
		$owner_repo           = trim( $header_parts['path'], '/' );  // strip surrounding slashes
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
			$arr['base_uri']   = $repo_base_uris[ $repo ];
			$arr['branch']     = $repo . ' Branch';
			$arr['enterprise'] = $repo . ' Enterprise';
			$arr['bool']       = true;
		}

		return $arr;
	}
}
