<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
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
 * Update a WordPress theme from a GitHub repo.
 *
 * Class      Theme
 * @package   Fragen\GitHub_Updater
 * @author    Andy Fragen
 * @author    Seth Carstens
 * @link      https://github.com/WordPress-Phoenix/whitelabel-framework
 * @author    UCF Web Communications
 * @link      https://github.com/UCF/Theme-Updater
 */
class Theme extends Base {

	/**
	 * Rollback variable.
	 *
	 * @var number
	 */
	protected $tag = false;

	/**
	 * Constructor.
	 */
	public function __construct() {

		/*
		 * Get details of git sourced themes.
		 */
		$this->config = $this->get_theme_meta();
		if ( empty( $this->config ) ) {
			return false;
		}
		if ( isset( $_GET['force-check'] ) ) {
			$this->delete_all_transients( 'themes' );
		}

		foreach ( (array) $this->config as $theme ) {
			$this->repo_api = null;
			switch( $theme->type ) {
				case 'github_theme':
					$this->repo_api = new GitHub_API( $theme );
					break;
				case 'bitbucket_theme':
					$this->repo_api = new Bitbucket_API( $theme );
					break;
				case 'gitlab_theme':
					$this->repo_api = new GitLab_API( $theme );
					break;
			}

			if ( is_null( $this->repo_api ) ) {
				continue;
			}

			$this->{$theme->type} = $theme;
			$this->set_defaults( $theme->type );

			if ( $this->repo_api->get_remote_info( 'style.css' ) ) {
				$this->repo_api->get_repo_meta();
				$this->repo_api->get_remote_tag();
				$changelog = $this->get_changelog_filename( $theme->type );
				if ( $changelog ) {
					$this->repo_api->get_remote_changes( $changelog );
				}
				$theme->download_link = $this->repo_api->construct_download_link();
			}

			/*
			 * Update theme transient with rollback data.
			 */
			if ( ! empty( $_GET['rollback'] ) &&
			     ( isset( $_GET['theme'] ) && $_GET['theme'] === $theme->repo )
			) {
				$this->tag         = $_GET['rollback'];
				$updates_transient = get_site_transient('update_themes');
				$rollback          = array(
					'new_version' => $this->tag,
					'url'         => $theme->uri,
					'package'     => $this->repo_api->construct_download_link( $this->tag, false ),
				);
				$updates_transient->response[ $theme->repo ] = $rollback;
				set_site_transient( 'update_themes', $updates_transient );
			}

			/*
			 * Remove WordPress update row in theme row, only in multisite.
			 * Add update row to theme row, only in multisite.
			 */
			if ( is_multisite() ) {
				add_action( 'after_theme_row', array( &$this, 'remove_after_theme_row' ), 10, 2 );
				if ( ! $this->tag ) {
					add_action( "after_theme_row_$theme->repo", array( &$this, 'wp_theme_update_row' ), 10, 2 );
				}
			}

		}

		$this->make_force_check_transient( 'themes' );

		if ( ! is_multisite() ) {
			add_filter( 'wp_prepare_themes_for_js', array( &$this, 'customize_theme_update_html' ) );
		}

		$update = array( 'do-core-reinstall', 'do-core-upgrade' );
		if ( empty( $_GET['action'] ) || ! in_array( $_GET['action'], $update, true ) ) {
			add_filter( 'pre_set_site_transient_update_themes', array( &$this, 'pre_set_site_transient_update_themes' ) );
		}

		add_filter( 'themes_api', array( &$this, 'themes_api' ), 99, 3 );
		add_filter( 'upgrader_source_selection', array( &$this, 'upgrader_source_selection' ), 10, 3 );
		add_filter( 'http_request_args', array( 'Fragen\\GitHub_Updater\\API', 'http_request_args' ), 10, 2 );

		Settings::$ghu_themes = $this->config;
	}


	/**
	 * Put changelog in plugins_api, return WP.org data as appropriate.
	 *
	 * @param $false
	 * @param $action
	 * @param $response
	 *
	 * @return mixed
	 */
	public function themes_api( $false, $action, $response ) {
		if ( ! ( 'theme_information' === $action ) ) {
			return $false;
		}

		/*
		 * Early return $false for adding themes from repo
		 */
		if ( isset( $response->fields ) && ! $response->fields['sections'] ) {
			return $false;
		}

		foreach ( (array) $this->config as $theme ) {
			if ( $response->slug === $theme->repo ) {
				$response->slug         = $theme->repo;
				$response->name         = $theme->name;
				$response->homepage     = $theme->uri;
				$response->version      = $theme->remote_version;
				$response->sections     = $theme->sections;
				$response->description  = implode( "\n", $theme->sections );
				$response->author       = $theme->author;
				$response->preview_url  = $theme->theme_uri;
				$response->requires     = $theme->requires;
				$response->tested       = $theme->tested;
				$response->downloaded   = $theme->downloaded;
				$response->last_updated = $theme->last_updated;
				$response->rating       = $theme->rating;
				$response->num_ratings  = $theme->num_ratings;
				if ( $theme->private ) {
					add_action( 'admin_head', array( $this, 'remove_rating_in_private_repo' ) );
				}
			}
		}
		add_action( 'admin_head', array( $this, 'fix_display_none_in_themes_api' ) );

		return $response;
	}

	/**
	 * Fix for new issue in 3.9 :-(
	 */
	public function fix_display_none_in_themes_api() {
		echo '<style> #theme-installer div.install-theme-info { display: block !important; } </style>';
	}

	/**
	 * Remove star rating for private themes.
	 */
	public function remove_rating_in_private_repo() {
		echo '<style> #theme-installer div.install-theme-info div.star-rating { display: none; } </style>';
	}

	/**
	 * Add custom theme update row, from /wp-admin/includes/update.php
	 *
	 * @author Seth Carstens
	 */
	public function wp_theme_update_row( $theme_key, $theme ) {
		$current            = get_site_transient( 'update_themes' );
		$themes_allowedtags = array(
				'a'       => array( 'href' => array(), 'title' => array() ),
				'abbr'    => array( 'title' => array() ),
				'acronym' => array( 'title' => array() ),
				'code'    => array(),
				'em'      => array(),
				'strong'  => array(),
			);
		$theme_name    = wp_kses( $theme['Name'], $themes_allowedtags );
		$wp_list_table = _get_list_table( 'WP_MS_Themes_List_Table' );
		$install_url   = self_admin_url( "theme-install.php" );
		$details_url   = add_query_arg(
				array(
					'tab'       => 'theme-information',
					'theme'     => $theme_key,
					'TB_iframe' => 'true',
					'width'     => 270,
					'height'    => 400
				),
				$install_url );

		if ( isset( $current->up_to_date[ $theme_key ] ) ) {
			$rollback      = $current->up_to_date[ $theme_key ]['rollback'];
			$rollback_keys = array_keys( $rollback );
			echo '<tr class="plugin-update-tr"><td colspan="' . $wp_list_table->get_column_count() . '" class="plugin-update colspanchange"><div class="update-message update-ok">';
			_e( 'Theme is up-to-date!', 'github-updater' );
			echo '&nbsp';
			if ( count( $rollback ) > 0 ) {
				array_shift( $rollback_keys ); //don't show newest tag, it should be release version
				echo '<strong>';
				_e( 'Rollback to:', 'github-updater' );
				echo '</strong> ';
				// display last three tags
				for ( $i = 0; $i < 3 ; $i++ ) {
					$tag = array_shift( $rollback_keys );
					if ( empty( $tag ) ) {
						break;
					}
					if ( $i > 0 ) {
						echo ", ";
					}
					printf( '<a href="%s%s">%s</a>',
						wp_nonce_url( self_admin_url( 'update.php?action=upgrade-theme&theme=' ) . $theme_key, 'upgrade-theme_' . $theme_key ),
						'&rollback=' . urlencode( $tag ),
						$tag
					);
				}
			} else {
				_e( 'No previous tags to rollback to.', 'github-updater' );
			}
		}

		if ( isset( $current->response[ $theme_key ] ) ) {
			$r = $current->response[ $theme_key ];
			echo '<tr class="plugin-update-tr"><td colspan="' . $wp_list_table->get_column_count() . '" class="plugin-update colspanchange"><div class="update-message">';
			if ( empty( $r['package'] ) ) {
				printf( __( 'GitHub Updater shows a new version of %s available.', 'github-updater' ),
					$theme_name
				);
				printf( ' <a href="%s" class="thickbox" title="%s"> ',
					esc_url( $details_url ),
					esc_attr( $theme_name )
				);
				printf( __( 'View version %s details.', 'github-updater' ),
					$r['new_version']
				);
				echo '</a><em>';
				_e( 'Automatic update is unavailable for this theme.', 'github-updater' );
				echo '</em>';
			} else {
				printf( __( 'GitHub Updater shows a new version of %s available.', 'github-updater' ),
					$theme_name
				);
				printf( ' <a href="%s" class="thickbox" title="%s"> ',
					esc_url( $details_url ),
					esc_attr( $theme_name )
				);
				printf( __( 'View version %1$s details%2$s or %3$supdate now%4$s.', 'github-updater' ),
					$r['new_version'],
					'</a>',
					'<a href="' . wp_nonce_url( self_admin_url( 'update.php?action=upgrade-theme&theme=' ) . $theme_key, 'upgrade-theme_' . $theme_key ) . '">',
					'</a>'
				);
			}

			do_action( "in_theme_update_message-$theme_key", $theme, $r );
		}
		echo '</div></td></tr>';
	}

	/**
	 * Remove default after_theme_row_$stylesheet.
	 *
	 * @author @grappler
	 *
	 * @param $theme_key
	 * @param $theme
	 */
	public function remove_after_theme_row( $theme_key, $theme ) {

		foreach ( parent::$git_servers as $server ) {
			$repo_header = $server . ' Theme URI';
			$repo_uri    = $theme->get( $repo_header );
			if ( empty( $repo_uri ) ) {
				continue;
			}

			remove_action( "after_theme_row_$theme_key", 'wp_theme_update_row', 10 );
		}
	}

	/**
	 * Call update theme messaging if needed for single site installation
	 *
	 * @author Seth Carstens
	 *
	 * @param $prepared_themes
	 *
	 * @return mixed
	 */
	public function customize_theme_update_html( $prepared_themes ) {

		foreach ( (array) $this->config as $theme ) {
			if ( empty( $prepared_themes[ $theme->repo ] ) ) {
				continue;
			}

			if ( ! empty( $prepared_themes[ $theme->repo ]['hasUpdate'] ) ) {
				$prepared_themes[ $theme->repo ]['update'] = $this->_append_theme_actions_content( $theme );
			} else {
				$prepared_themes[ $theme->repo ]['description'] .= $this->_append_theme_actions_content( $theme );
			}
		}

		return $prepared_themes;
	}

	/**
	 * Create theme update messaging
	 *
	 * @author Seth Carstens
	 *
	 * @param object $theme
	 *
	 * @return string (content buffer)
	 */
	private function _append_theme_actions_content( $theme ) {

		$details_url            = self_admin_url( "theme-install.php?tab=theme-information&theme=$theme->repo&TB_iframe=true&width=270&height=400" );
		$theme_update_transient = get_site_transient( 'update_themes' );

		/**
		 * If the theme is outdated, display the custom theme updater content.
		 * If theme is not present in theme_update transient response ( theme is not up to date )
		 */
		if ( empty( $theme_update_transient->up_to_date[$theme->repo] ) ) {
			$update_url = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-theme&theme=' ) . urlencode( $theme->repo ), 'upgrade-theme_' . $theme->repo );
			ob_start();
			?>
			<strong><br />
				<?php
					printf( __( 'There is a new version of %s available now.', 'github-updater' ),
						$theme->name
					);
					printf( ' <a href="%s" class="thickbox" title="%s">',
						esc_url( $details_url ),
						esc_attr( $theme->name )
					);
					printf( __( 'View version %1$s details%2$s or %3$supdate now%4$s.', 'github-updater' ),
						$theme->remote_version,
						'</a>',
						'<a href="' . $update_url . '">',
						'</a>'
					);
				?>
			</strong>
			<?php

			return trim( ob_get_clean(), '1' );
		} else {
			/*
			 * If the theme is up to date, display the custom rollback/beta version updater
			 */
			ob_start();
			$rollback_url = sprintf( '%s%s', wp_nonce_url( self_admin_url( 'update.php?action=upgrade-theme&theme=' ) . urlencode( $theme->repo ), 'upgrade-theme_' . $theme->repo ), '&rollback=' );

			?>
			<p><?php
				printf( __( 'Current version is up to date. Try %sanother version%s', 'github-updater' ),
					'<a href="#" onclick="jQuery(\'#ghu_versions\').toggle();return false;">',
					'</a>'
				);
				?>
			</p>
			<div id="ghu_versions" style="display:none; width: 100%;">
				<select style="width: 60%;"
					onchange="if(jQuery(this).val() != '') {
						jQuery(this).next().show();
						jQuery(this).next().attr('href','<?php echo $rollback_url ?>'+jQuery(this).val());
					}
					else jQuery(this).next().hide();
				">
				<option value=""><?php _e( 'Choose a Version', 'github-updater' ); ?>&#8230;</option>
				<option><?php echo $theme->branch; ?></option>
				<?php foreach ( array_keys( $theme_update_transient->up_to_date[ $theme->repo ]['rollback'] ) as $version ) { echo'<option>' . $version . '</option>'; }?></select>
				<a style="display: none;" class="button-primary" href="?"><?php _e( 'Install', 'github-updater' ); ?></a>
			</div>
			<?php

			return trim( ob_get_clean(), '1' );
		}
	}

	/**
	 * Hook into pre_set_site_transient_update_themes to update.
	 *
	 * Finds newest tag and compares to current tag.
	 *
	 * @param array $transient
	 *
	 * @return array|object
	 */
	public function pre_set_site_transient_update_themes( $transient ) {

		foreach ( (array) $this->config as $theme ) {
			if ( empty( $theme->uri ) ) {
				continue;
			}

			$update = array(
				'theme'       => $theme->repo,
				'new_version' => $theme->remote_version,
				'url'         => $theme->uri,
				'package'     => $theme->download_link,
			);

			if ( $this->can_update( $theme ) ) {
				$transient->response[ $theme->repo ] = $update;
			} else { // up-to-date!
				$transient->up_to_date[ $theme->repo ]['rollback'] = $theme->rollback;
				$transient->up_to_date[ $theme->repo ]['response'] = $update;
			}
		}

		return $transient;
	}

}
