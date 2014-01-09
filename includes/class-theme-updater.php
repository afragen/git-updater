<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

/**
 * Update a WordPress theme from a GitHub repo.
 *
 * @package   GitHub_Theme_Updater
 * @author    Andy Fragen
 * @author    Seth Carstens
 * @link      https://github.com/scarstens/Github-Theme-Updater
 * @author    UCF Web Communications
 * @link      https://github.com/UCF/Theme-Updater
 */
class GitHub_Theme_Updater extends GitHub_Updater {

	/**
	 * Define as either 'plugin' or 'theme'
	 *
	 * @since 1.9.0
	 *
	 * @var string
	 */
	protected $type;

	/**
	 * Class Object for API
	 *
	 * @since 2.1.0
	 *
	 * @var class object
	 */
 	protected $repo_api;


	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config
	 */
	public function __construct() {

		// This MUST come before we get details about the plugins so the headers are correctly retrieved
		add_filter( 'extra_theme_headers', array( $this, 'add_theme_headers' ) );

		// Get details of GitHub-sourced themes
		$this->config = $this->get_theme_meta();
		if ( empty( $this->config ) ) return;

		foreach ( (array) $this->config as $theme ) {

			switch( $this->type ) {
				case 'github_theme':
					$repo_api = new GitHub_Updater_GitHub_API( $theme );
					break;
			}

			$this->{$this->type} = $theme;
			$this->set_defaults();
			$repo_api->get_remote_info( 'style.css' );
			$repo_api->get_repo_meta();
			$repo_api->get_remote_tag();
			$rollback = false;
			if (  !empty( $_GET['rollback'] ) $rollback = $_GET['rollback'];
			$this->{$this->type}->download_link = $repo_api->construct_download_link($rollback);

			// Add update row to theme row, only in multisite for >= WP 3.8
			add_action( "after_theme_row_$theme->repo", array( $this, 'wp_theme_update_row' ), 10, 2 );

		}

		$update = array( 'do-core-reinstall', 'do-core-upgrade' );
		if (  empty( $_GET['action'] ) || ! in_array( $_GET['action'], $update, true ) )
			add_filter( 'pre_set_site_transient_update_themes', array( $this, 'pre_set_site_transient_update_themes' ) );

		add_filter( 'themes_api', array( $this, 'themes_api' ), 99, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'upgrader_source_selection' ), 10, 3 );
		add_action( 'http_request_args', array( $this, 'no_ssl_http_request_args' ) );
	}

	/**
	 * Put changelog in plugins_api, return WP.org data as appropriate
	 *
	 * @since 2.0.0
	 */
	public function themes_api( $false, $action, $response ) {
		if ( ! ( 'theme_information' == $action ) ) {
			return $false;
		}

		foreach ( (array) $this->config as $theme ) {
			if ($response->slug === $theme->repo) {
				$response->slug         = $theme->repo;
				$response->name         = $theme->name;
				$response->homepage     = $theme->uri;
				$response->version      = $theme->remote_version;
				$response->sections     = $theme->sections;
				$response->description  = $theme->sections['description'];
				$response->author       = $theme->author;
				$response->preview_url  = $theme->sections['changelog'];
				$response->requires     = $theme->requires;
				$response->tested       = $theme->tested;
				$response->downloaded   = $theme->downloaded;
				$response->last_updated = $theme->last_updated;
				$response->rating       = $theme->rating;
				$response->num_ratings  = $theme->num_ratings;
			}
		}
		return $response;  
	}

	/**
	 * Add custom theme update row, from /wp-admin/includes/update.php
	 *
	 * @since 2.2.0
	 */
	public function wp_theme_update_row( $theme_key, $theme ) {

		$current = get_site_transient( 'update_themes' );
		if ( !isset( $current->response[ $theme_key ] ) ) return false;
		$r = $current->response[ $theme_key ];
		$themes_allowedtags = array('a' => array('href' => array(),'title' => array()),'abbr' => array('title' => array()),'acronym' => array('title' => array()),'code' => array(),'em' => array(),'strong' => array());
		$theme_name = wp_kses( $theme['Name'], $themes_allowedtags );

		$details_url      = self_admin_url( "theme-install.php?tab=theme-information&theme=$theme_key&TB_iframe=true&width=270&height=400" );

		$wp_list_table = _get_list_table('WP_MS_Themes_List_Table');

		echo '<tr class="plugin-update-tr"><td colspan="' . $wp_list_table->get_column_count() . '" class="plugin-update colspanchange"><div class="update-message">';
		if ( ! current_user_can('update_themes') )
			printf( __('GitHub Updater shows a new version of %1$s available. <a href="%2$s" class="thickbox" title="%3$s">View version %4$s details</a>.'), $theme['Name'], esc_url($details_url), esc_attr($theme['Name']), $r->new_version );
		else if ( empty( $r['package'] ) )
			printf( __('GitHub Updater shows a new version of %1$s available. <a href="%2$s" class="thickbox" title="%3$s">View version %4$s details</a>. <em>Automatic update is unavailable for this theme.</em>'), $theme['Name'], esc_url($details_url), esc_attr($theme['Name']), $r['new_version'] );
		else
			printf( __('GitHub Updater shows a new version of %1$s available. <a href="%2$s" class="thickbox" title="%3$s">View version %4$s details</a> or <a href="%5$s">update now</a>.'), $theme['Name'], esc_url($details_url), esc_attr($theme['Name']), $r['new_version'], wp_nonce_url( self_admin_url('update.php?action=upgrade-theme&theme=') . $theme_key, 'upgrade-theme_' . $theme_key) );

		do_action( "in_theme_update_message-$theme_key", $theme, $r );

		echo '</div></td></tr>';
	}

	/**
	 * Remove default after_theme_row_$stylesheet
	 *
	 * @since 2.2.1
	 *
	 * @author @grappler
	 * @param string
	 */
	public static function remove_after_theme_row( $theme ) {
		remove_action( "after_theme_row_$theme", 'wp_theme_update_row', 10 );
	}

	/**
	 * Hook into pre_set_site_transient_update_themes to update from GitHub.
	 *
	 * Finds newest tag and compares to current tag
	 *
	 * @since 1.0.0
	 *
	 * @param array $data
	 * @return array|object
	 */
	public function pre_set_site_transient_update_themes( $data ){

		foreach ( (array) $this->config as $theme ) {
			if ( empty( $theme->uri ) ) continue;

			// setup update array to append version info
			$remote_is_newer = ( 1 === version_compare( $theme->remote_version, $theme->local_version ) );

			if ( $remote_is_newer ) {
				$update = array(
					'new_version' => $theme->remote_version,
					'url'         => $theme->uri,
					'package'     => $theme->download_link,
				);

				$data->response[ $theme->repo ] = $update;
			}
		}
		return $data;
	}

}
