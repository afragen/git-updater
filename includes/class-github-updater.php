<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @author    Gary Jones
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

/**
 * Update a WordPress plugin or theme from a Git-based repo.
 *
 * @package GitHub_Updater
 * @author  Andy Fragen
 * @author  Gary Jones
 */
class GitHub_Updater {

	/**
	 * Store details of all repositories that are installed.
	 *
	 * @var stdClass
	 */
	protected $config;

	/**
	 * Class Object for API
	 *
	 * @var stdClass
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
	 * Constructor
	 *
	 * Calls $this->init() in init hook so other remote upgrader apps like
	 * InfiniteWP, ManageWP, MainWP, and iThemes Sync will load and use all
	 * of GitHub_Updater's methods, especially renaming.
	 */
	public function __construct() {
		//add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Instantiate GitHub_Plugin_Updater and GitHub_Theme_Updater
	 */
	public static function init() {
		if ( current_user_can( 'update_plugins' ) ) {
			new GitHub_Plugin_Updater;
		}
		if ( current_user_can( 'update_themes' ) ) {
			new GitHub_Theme_Updater;
		}
	}

	/**
	 * Get details of Git-sourced plugins from those that are installed.
	 *
	 * @return array Indexed array of associative arrays of plugin details.
	 */
	protected function get_plugin_meta() {
		// Ensure get_plugins() function is available.
		include_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		$plugins     = get_plugins();
		$git_plugins = array();

		foreach ( (array) $plugins as $plugin => $headers ) {
			if ( empty( $headers['GitHub Plugin URI'] ) &&
				empty( $headers['Bitbucket Plugin URI'] ) ) {
				continue;
			}

			$git_repo = $this->get_local_plugin_meta( $headers );

			$git_repo['slug']                    = $plugin;
			$plugin_data                         = get_plugin_data( WP_PLUGIN_DIR . '/' . $git_repo['slug'] );
			$git_repo['author']                  = $plugin_data['AuthorName'];
			$git_repo['name']                    = $plugin_data['Name'];
			$git_repo['local_version']           = $plugin_data['Version'];
			$git_repo['sections']['description'] = $plugin_data['Description'];
			$git_plugins[ $git_repo['repo'] ]    = (object) $git_repo;
		}

		return $git_plugins;
	}

	/**
	* Parse extra headers to determine repo type and populate info
	*
	* @param array of extra headers
	* @return array of repo information
	*
	* parse_url( ..., PHP_URL_PATH ) is either clever enough to handle the short url format
	* (in addition to the long url format), or it's coincidentally returning all of the short
	* URL string, which is what we want anyway.
	*
	*/
	protected function get_local_plugin_meta( $headers ) {
		$git_repo      = array();

		foreach ( (array) self::$extra_headers as $key => $value ) {
			if ( ! empty( $git_repo['type'] ) && 'github_plugin' !== $git_repo['type'] ) {
				continue;
			}
			switch( $value ) {
				case 'GitHub Plugin URI':
					if ( empty( $headers['GitHub Plugin URI'] ) ) {
						break;
					}
					$git_repo['type']         = 'github_plugin';

					$owner_repo               = parse_url( $headers['GitHub Plugin URI'], PHP_URL_PATH );
					$owner_repo               = trim( $owner_repo, '/' );  // strip surrounding slashes
					$git_repo['uri']          = 'https://github.com/' . $owner_repo;
					$owner_repo               = explode( '/', $owner_repo );
					$git_repo['owner']        = $owner_repo[0];
					$git_repo['repo']         = $owner_repo[1];
					$git_repo['local_path']   = WP_PLUGIN_DIR . '/' . $git_repo['repo'] . '/';
					break;
				case 'GitHub Branch':
					if ( empty( $headers['GitHub Branch'] ) ) {
						break;
					}
					$git_repo['branch']       = $headers['GitHub Branch'];
					break;
				case 'GitHub Access Token':
					if ( empty( $headers['GitHub Access Token'] ) ) {
						break;
					}
					$git_repo['access_token'] = $headers['GitHub Access Token'];
					break;
			}
		}

		foreach ( (array) self::$extra_headers as $key => $value ) {
			if ( ! empty( $git_repo['type'] ) && 'bitbucket_plugin' !== $git_repo['type'] ) {
				continue;
			}
			switch( $value ) {
				case 'Bitbucket Plugin URI':
					if ( empty( $headers['Bitbucket Plugin URI'] ) ) {
						break;
					}
					$git_repo['type']       = 'bitbucket_plugin';

					$git_repo['user']       = parse_url( $headers['Bitbucket Plugin URI'], PHP_URL_USER );
					$git_repo['pass']       = parse_url( $headers['Bitbucket Plugin URI'], PHP_URL_PASS );
					$owner_repo             = parse_url( $headers['Bitbucket Plugin URI'], PHP_URL_PATH );
					$owner_repo             = trim( $owner_repo, '/' );  // strip surrounding slashes
					$git_repo['uri']        = 'https://bitbucket.org/' . $owner_repo;
					$owner_repo             = explode( '/', $owner_repo );
					$git_repo['owner']      = $owner_repo[0];
					$git_repo['repo']       = $owner_repo[1];
					$git_repo['local_path'] = WP_PLUGIN_DIR . '/' . $git_repo['repo'] .'/';
					break;
				case 'Bitbucket Branch':
					if ( empty( $headers['Bitbucket Branch'] ) ) {
						break;
					}
					$git_repo['branch']     = $headers['Bitbucket Branch'];
					break;
			}
		}

		return $git_repo;
	}

	/**
	* Get array of all themes in multisite
	*
	* wp_get_themes does not seem to work under network activation in the same way as in a single install.
	* http://core.trac.wordpress.org/changeset/20152
	*
	* @return array
	*/
	private function multisite_get_themes() {
		$themes     = array();
		$theme_dirs = scandir( get_theme_root() );
		$theme_dirs = array_diff( $theme_dirs, array( '.', '..', '.DS_Store' ) );

		foreach ( (array) $theme_dirs as $theme_dir ) {
			$themes[] = wp_get_theme( $theme_dir );
		}

		return $themes;
	}

	/**
	 * Reads in WP_Theme class of each theme.
	 * Populates variable array
	 */
	protected function get_theme_meta() {
		$git_themes    = array();
		$themes        = wp_get_themes();

		if ( is_multisite() ) {
			$themes = $this->multisite_get_themes();
		}

		foreach ( (array) $themes as $theme ) {
			$git_theme         = array();
			$github_uri        = $theme->get( 'GitHub Theme URI' );
			$github_branch     = $theme->get( 'GitHub Branch' );
			$github_token      = $theme->get( 'GitHub Access Token' );
			$bitbucket_uri     = $theme->get( 'Bitbucket Theme URI' );
			$bitbucket_branch  = $theme->get( 'Bitbucket Branch' );

			if ( empty( $github_uri ) && empty( $bitbucket_uri ) ) {
				continue;
			}

			foreach ( (array) self::$extra_headers as $key => $value ) {
				if ( ! empty( $git_theme['type'] ) && 'github_theme' !== $git_theme['type'] ) {
					continue;
				}
				switch( $value ) {
					case 'GitHub Theme URI':
						if ( empty( $github_uri ) ) {
							break;
						}
						$git_theme['type']                    = 'github_theme';

						$owner_repo                           = parse_url( $github_uri, PHP_URL_PATH );
						$owner_repo                           = trim( $owner_repo, '/' );
						$git_theme['uri']                     = 'https://github.com/' . $owner_repo;
						$owner_repo                           = explode( '/', $owner_repo );
						$git_theme['owner']                   = $owner_repo[0];
						$git_theme['repo']                    = $owner_repo[1];
						$git_theme['name']                    = $theme->get( 'Name' );
						$git_theme['theme_uri']               = $theme->get( 'ThemeURI' );
						$git_theme['author']                  = $theme->get( 'Author' );
						$git_theme['local_version']           = $theme->get( 'Version' );
						$git_theme['sections']['description'] = $theme->get( 'Description' );
						$git_theme['local_path']              = get_theme_root() . '/' . $git_theme['repo'] .'/';
						break;
					case 'GitHub Branch':
						if ( empty( $github_branch ) ) {
							break;
						}
						$git_theme['branch']                  = $github_branch;
						break;
					case 'GitHub Access Token':
						if ( empty( $github_token ) ) {
							break;
						}
						$git_theme['access_token']            = $github_token;
						break;
				}
			}

			foreach ( (array) self::$extra_headers as $key => $value ) {
				if ( ! empty( $git_theme['type'] ) && 'bitbucket_theme' !== $git_theme['type'] ) {
					continue;
				}
				switch( $value ) {
					case 'Bitbucket Theme URI':
						if ( empty( $bitbucket_uri ) ) {
							break;
						}
						$git_theme['type']                    = 'bitbucket_theme';

						$git_theme['user']                    = parse_url( $bitbucket_uri, PHP_URL_USER );
						$git_theme['pass']                    = parse_url( $bitbucket_uri, PHP_URL_PASS );
						$owner_repo                           = parse_url( $bitbucket_uri, PHP_URL_PATH );
						$owner_repo                           = trim( $owner_repo, '/' );
						$git_theme['uri']                     = 'https://bitbucket.org/' . $owner_repo;
						$owner_repo                           = explode( '/', $owner_repo );
						$git_theme['owner']                   = $owner_repo[0];
						$git_theme['repo']                    = $owner_repo[1];
						$git_theme['name']                    = $theme->get( 'Name' );
						$git_theme['theme_uri']               = $theme->get( 'ThemeURI' );
						$git_theme['author']                  = $theme->get( 'Author' );
						$git_theme['local_version']           = $theme->get( 'Version' );
						$git_theme['sections']['description'] = $theme->get( 'Description' );
						$git_theme['local_path']              = get_theme_root() . '/' . $git_theme['repo'] .'/';
						break;
					case 'Bitbucket Branch':
						if ( empty( $bitbucket_branch ) ) {
							break;
						}
						$git_theme['branch']                  = $bitbucket_branch;
						break;
				}
			}

			$git_themes[ $theme->stylesheet ] = (object) $git_theme;
		}

		return $git_themes;
	}

	/**
	 * Set default values for plugin/theme
	 */
	protected function set_defaults( $type ) {
		$this->$type->remote_version        = '0.0.0';
		$this->$type->newest_tag            = '0.0.0';
		$this->$type->download_link         = null;
		$this->$type->tags                  = array();
		$this->$type->rollback              = array();
		$this->$type->sections['changelog'] = 'No changelog is available via GitHub Updater. Create a file <code>CHANGES.md</code> in your repository.';
		$this->$type->requires              = null;
		$this->$type->tested                = null;
		$this->$type->downloaded            = 0;
		$this->$type->last_updated          = null;
		$this->$type->rating                = 0;
		$this->$type->num_ratings           = 0;
		$this->$type->transient             = array();
		$this->$type->repo_meta             = array();
		$this->$type->watchers              = 0;
		$this->$type->forks                 = 0;
		$this->$type->open_issues           = 0;
		$this->$type->score                 = 0;
	}

	/**
	 * Rename the zip folder to be the same as the existing repository folder.
	 *
	 * Github delivers zip files as <Repo>-<Branch>.zip
	 *
	 * @global WP_Filesystem $wp_filesystem
	 *
	 * @param string $source
	 * @param string $remote_source Optional.
	 * @param object $upgrader      Optional.
	 *
	 * @return string
	 */
	public function upgrader_source_selection( $source, $remote_source , $upgrader ) {

		global $wp_filesystem;
		//$update = array( 'update-selected', 'update-selected-themes', 'upgrade-theme', 'upgrade-plugin' );

		if ( isset( $source ) ) {
			foreach ( (array) $this->config as $git_repo ) {
				if ( stristr( basename( $source ), $git_repo->repo ) ) {
					$repo = $git_repo->repo;
				}
			}
		}

		// Check for upgrade process, return if both are false
		if ( ! is_a( $upgrader, 'Plugin_Upgrader' ) && ! is_a( $upgrader, 'Theme_Upgrader' ) ) {
			return $source;
		}

		// If the values aren't set, or it's not GitHub-sourced, abort
		if ( ! isset( $source, $remote_source, $repo ) || false === stristr( basename( $source ), $repo ) ) {
			return $source;
		}

		$corrected_source = trailingslashit( $remote_source ) . trailingslashit( $repo );
		$upgrader->skin->feedback(
			sprintf(
				__( 'Renaming %s to %s&#8230;', 'github-updater' ),
				'<span class="code">' . basename( $source ) . '</span>',
				'<span class="code">' . basename( $corrected_source ) . '</span>'
			)
		);

		// If we can rename, do so and return the new name
		if ( $wp_filesystem->move( $source, $corrected_source, true ) ) {
			$upgrader->skin->feedback( __( 'Rename successful&#8230;', 'github-updater' ) );
			return $corrected_source;
		}

		// Otherwise, return an error
		$upgrader->skin->feedback( __( 'Unable to rename downloaded repository.', 'github-updater' ) );
		return new WP_Error();
	}

	/**
	 * Fixes {@link https://github.com/UCF/Theme-Updater/issues/3}.
	 *
	 * @param  array $args Existing HTTP Request arguments.
	 *
	 * @return array Amended HTTP Request arguments.
	 */
	public function no_ssl_http_request_args( $args ) {
		$args['sslverify'] = false;

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
	 * @return bool
	 */
	protected function delete_all_transients( $type ) {
		$transients = get_site_transient( 'ghu-' . $type );
		if ( ! $transients ) {
			return false;
		}

		foreach ( $transients as $transient ) {
			delete_site_transient( $transient );
			$key = array_search( $transient, $transients );
			unset( $transients[ $key ] );
		}

		return true;
	}


	/**
	 * Create transient of $type transients for force-check
	 *
	 * @param $type
	 */
	protected function make_force_check_transient( $type ) {
		delete_site_transient( 'ghu-' . $type );
		set_site_transient( 'ghu-' . $type , self::$transients, 12 * HOUR_IN_SECONDS );
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
		$watchers    = ( empty( $repo_meta->watchers ) ? $this->type->watchers : $repo_meta->watchers );
		$forks       = ( empty( $repo_meta->forks ) ? $this->type->forks : $repo_meta->forks );
		$open_issues = ( empty( $repo_meta->open_issues ) ? $this->type->open_issues : $repo_meta->open_issues );
		$score       = ( empty( $repo_meta->score ) ? $this->type->score : $repo_meta->score ); //what is this anyway?

		$rating = round( $watchers + ( $forks * 1.5 ) - $open_issues + $score );

		if ( 100 < $rating ) {
			return 100;
		}

		return $rating;
	}

}