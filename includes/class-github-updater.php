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
 * Update a WordPress plugin or theme from a GitHub repo.
 *
 * @package GitHub_Updater
 * @author  Andy Fragen
 * @author  Gary Jones
 */
class GitHub_Updater {

	/**
	 * Store details of all GitHub-sourced repos that are installed.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * Define as either 'plugin' or 'theme'
	 *
	 * @since 1.9.0
	 * @var string
	 */
	protected $type;

	/**
	 * Class Object for API
	 *
	 * @since 2.1.0
	 * @var class object
	 */
 	protected $repo_api;

	/**
	 * Variable for setting update transient hours
	 *
	 * @since 2.x.x
	 * @var integer
	 */
	protected static $hours = 1;
	 
	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init_hooks' ) );
	}

	/**
	 * Method to set hooks, called in GitHub_Plugin_Updater::__construct via add_action( 'init'...)
	 *
	 * @since 2.3.0
	 *
	 * @return integer
	 */
	public static function init_hooks() {
		self::$hours = apply_filters( 'github_updater_set_transient_hours', self::$hours );
		return self::$hours;
	}

	/**
	 * Add extra header to get_plugins();
	 *
	 * @since 1.0.0
	 */
	public function add_plugin_headers( $extra_headers ) {
		$gtu_extra_headers = array( 'GitHub Plugin URI', 'GitHub Access Token', 'GitHub Branch' );
		$extra_headers     = array_merge( (array) $extra_headers, (array) $gtu_extra_headers );

		return $extra_headers;
	}

	/**
	 * Add extra headers to wp_get_themes()
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function add_theme_headers( $extra_headers ) {
		$gtu_extra_headers = array( 'GitHub Theme URI', 'GitHub Access Token', 'GitHub Branch' );
		$extra_headers     = array_merge( (array) $extra_headers, (array) $gtu_extra_headers );

		return $extra_headers;
	}

	/**
	 * Get details of GitHub-sourced plugins from those that are installed.
	 *
	 * @since 1.0.0
	 *
	 * @return array Indexed array of associative arrays of plugin details.
	 */
	protected function get_plugin_meta() {
		// Ensure get_plugins() function is available.
		include_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		$plugins     = get_plugins();
		$git_plugins = array();

		foreach ( (array) $plugins as $plugin => $headers ) {
			$git_repo = $this->get_local_plugin_meta( $headers );
			if ( empty( $git_repo['owner'] ) )
				continue;

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
	* @since 1.6.0
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
		$extra_headers = $this->add_plugin_headers( null );

		foreach ( (array) $extra_headers as $key => $value ) {
			switch( $value ) {
				case 'GitHub Plugin URI':
					if ( empty( $headers['GitHub Plugin URI'] ) ) return;
					$this->type = 'github_plugin';

					$owner_repo        = parse_url( $headers['GitHub Plugin URI'], PHP_URL_PATH );
					$owner_repo        = trim( $owner_repo, '/' );  // strip surrounding slashes
					$git_repo['uri']   = 'https://github.com/' . $owner_repo;
					$owner_repo        = explode( '/', $owner_repo );
					$git_repo['owner'] = $owner_repo[0];
					$git_repo['repo']  = $owner_repo[1];
					break;
				case 'GitHub Access Token':
					$git_repo['access_token'] = $headers['GitHub Access Token'];
					break;
				case 'GitHub Branch':
					$git_repo['branch'] = $headers['GitHub Branch'];
					break;
			}
		}
		return $git_repo;
	}

	/**
	* Get array of all themes in multisite
	*
	* wp_get_themes doesn't seem to work under network activation in the same way as in a single install.
	* http://core.trac.wordpress.org/changeset/20152
	*
	* @since 1.7.0
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
	 *
	 * @since 1.0.0
	 */
	protected function get_theme_meta() {
		$git_themes    = array();
		$git_theme     = array();
		$themes        = wp_get_themes();
		$extra_headers = $this->add_theme_headers( null );

		if ( is_multisite() )
			$themes = $this->multisite_get_themes();

		foreach ( (array) $themes as $theme ) {
			$github_uri = $theme->get( 'GitHub Theme URI' );
			if ( empty( $github_uri ) ) continue;
			$this->type = 'github_theme';

			foreach ( (array) $extra_headers as $key => $value ) {
				switch( $value ) {
					case 'GitHub Theme URI':
						$owner_repo                              = parse_url( $github_uri, PHP_URL_PATH );
						$owner_repo                              = trim( $owner_repo, '/' );
						$github_theme['uri']                     = 'https://github.com/' . $owner_repo;
						$owner_repo                              = explode( '/', $owner_repo );
						$github_theme['owner']                   = $owner_repo[0];
						$github_theme['repo']                    = $owner_repo[1];
						$github_theme['name']                    = $theme->get( 'Name' );
						$github_theme['author']                  = $theme->get( 'Author' );
						$github_theme['local_version']           = $theme->get( 'Version' );
						$github_theme['sections']['description'] = $theme->get( 'Description' );
						break;
					case 'GitHub Access Token':
						$github_theme['access_token']  = $theme->get( 'GitHub Access Token' );
						break;
					case 'GitHub Branch':
						$github_theme['branch']        = $theme->get( 'GitHub Branch' );
						break;
				}
			}

			$git_themes[ $theme->stylesheet ] = (object) $github_theme;
		}
		return $git_themes;
	}

	/**
	 * Set default values for plugin/theme
	 *
	 * @since 1.9.0
	 */
	protected function set_defaults() {
		$this->{$this->type}->remote_version        = '0.0.0';
		$this->{$this->type}->newest_tag            = '0.0.0';
		$this->{$this->type}->download_link         = '';
		$this->{$this->type}->tags                  = array();
		$this->{$this->type}->rollback              = array();
		$this->{$this->type}->sections['changelog'] = 'No changelog is available via GitHub Updater. Create a file <code>CHANGES.md</code> in your repository. Please consider helping out with a pull request to fix <a href="https://github.com/afragen/github-updater/issues/8">issue #8</a>.';
		$this->{$this->type}->requires              = null;
		$this->{$this->type}->tested                = null;
		$this->{$this->type}->downloaded            = 0;
		$this->{$this->type}->last_updated          = null;
		$this->{$this->type}->rating                = 0;
		$this->{$this->type}->num_ratings           = 0;
		$this->{$this->type}->transient             = array();
		$this->{$this->type}->repo_meta             = array();

	}

	/**
	 * Rename the zip folder to be the same as the existing repository folder.
	 *
	 * Github delivers zip files as <Repo>-<Branch>.zip
	 *
	 * @since 1.0.0
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
		$update = array( 'update-selected', 'update-selected-themes', 'upgrade-theme', 'upgrade-plugin' );

		if ( isset( $source ) ) {
			foreach ( (array) $this->config as $github_repo ) {
				if ( stristr( basename( $source ), $github_repo->repo ) )
					$repo = $github_repo->repo;
			}
		}

		// If there's no action set, or not one we recognise, abort
		if ( ! isset( $_GET['action'] ) || ! in_array( $_GET['action'], $update, true ) )
			return $source;

		// If the values aren't set, or it's not GitHub-sourced, abort
		if ( ! isset( $source, $remote_source, $repo ) || false === stristr( basename( $source ), $repo ) )
			return $source;

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
	 * @since 1.0.0
	 *
	 * @param  array $args Existing HTTP Request arguments.
	 *
	 * @return array Amended HTTP Request arguments.
	 */
	public function no_ssl_http_request_args( $args ) {
		$args['sslverify'] = false;
		return $args;
	}

}