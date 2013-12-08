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
	 * Store details of all GitHub-sourced themes that are installed.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * Add extra header to get_plugins();
	 *
	 * @since 1.0.0
	 */
	public function add_plugin_headers( $extra_headers ) {
		$gtu_extra_headers = array( 'GitHub Plugin URI', 'GitHub Access Token', 'GitHub Branch' );
		$extra_headers = array_merge( (array) $extra_headers, (array) $gtu_extra_headers );

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
		$gtu_extra_headers = array( 'GitHub Theme URI' );
		$extra_headers = array_merge( (array) $extra_headers, (array) $gtu_extra_headers );

		return $extra_headers;
	}

	/**
	 * Get details of GitHub-sourced plugins from those that are installed.
	 *
	 * @since 1.0.0
	 *
	 * @return array Indexed array of associative arrays of plugin details.
	 */
	public function get_plugin_meta() {
		// Ensure get_plugins() function is available.
		include_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		$plugins        = get_plugins();
		$github_plugins = array();

		foreach ( $plugins as $plugin => $headers ) {
			$git_repo = $this->get_repo_info( $headers );
			if ( empty( $git_repo['owner'] ) )
				continue;
			$git_repo['slug'] = $plugin;
			$github_plugins[] = $git_repo;
		}

		return $github_plugins;
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
	protected function get_repo_info( $headers ) {

		$git_repo      = array();
		$extra_headers = $this->add_plugin_headers( null );

		foreach ( $extra_headers as $key => $value ) {
			switch( $value ) {
				case 'GitHub Plugin URI':
					if ( empty( $headers['GitHub Plugin URI'] ) ) return;

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
	 * Retrieves the local version from the file header of the plugin
	 *
	 * @since 1.0.0
	 *
	 * @return string|boolean Version of installed plugin, false if not determined.
	 */
	public function get_local_version( $git_plugin ) {
		$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $git_plugin['slug'] );

		if ( ! empty( $data['Version'] ) )
			return $data['Version'];

		return false;
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

		foreach ( $theme_dirs as $theme_dir ) {
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
	public function get_themes_meta() {
		$config_themes = array();
		$themes        = wp_get_themes();

		if ( is_multisite() )
			$themes = $this->multisite_get_themes();

		foreach ( $themes as $theme ) {
			$github_uri = $theme->get( 'GitHub Theme URI' );
			if ( empty( $github_uri ) ) continue;

			$owner_repo = parse_url( $github_uri, PHP_URL_PATH );
			$owner_repo = trim( $owner_repo, '/' );  // strip surrounding slashes

			$config_themes['theme'][]                         = $theme->stylesheet;
			$config_themes[ $theme->stylesheet ]['theme_key'] = $theme->stylesheet;
			$config_themes[ $theme->stylesheet ]['uri']       = 'https://github.com/' . $owner_repo;
			$config_themes[ $theme->stylesheet ]['api']       = 'https://api.github.com/repos/' . $owner_repo;
			$config_themes[ $theme->stylesheet ]['version']   = $theme->get( 'Version' );
		}

		return $config_themes;
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