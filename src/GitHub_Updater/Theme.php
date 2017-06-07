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
 * Class Theme
 *
 * Update a WordPress theme from a GitHub repo.
 *
 * @package   Fragen\GitHub_Updater
 * @author    Andy Fragen
 * @author    Seth Carstens
 * @link      https://github.com/WordPress-Phoenix/whitelabel-framework
 * @author    UCF Web Communications
 * @link      https://github.com/UCF/Theme-Updater
 */
class Theme extends Base {

	/**
	 * Theme object.
	 *
	 * @var bool|Theme
	 */
	private static $instance = false;

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
		 * Get details of installed git sourced themes.
		 */
		$this->config = $this->get_theme_meta();

		if ( empty( $this->config ) ) {
			return;
		}
	}

	/**
	 * The Theme object can be created/obtained via this
	 * method - this prevents unnecessary work in rebuilding the object and
	 * querying to construct a list of categories, etc.
	 *
	 * @return object $instance Theme
	 */
	public static function instance() {
		if ( false === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Returns an array of configurations for the known themes.
	 *
	 * @return array
	 */
	public function get_theme_configs() {
		return $this->config;
	}

	/**
	 * Reads in WP_Theme class of each theme.
	 * Populates variable array.
	 *
	 * @return array Indexed array of associative arrays of theme details.
	 */
	protected function get_theme_meta() {
		$git_themes = array();
		$themes     = wp_get_themes( array( 'errors' => null ) );

		/**
		 * Filter to add themes not containing appropriate header line.
		 *
		 * @since   5.4.0
		 * @access  public
		 *
		 * @param   array $additions    Listing of themes to add.
		 *                              Default null.
		 * @param   array $themes       Listing of all themes.
		 * @param         string        'theme'    Type being passed.
		 */
		$additions = apply_filters( 'github_updater_additions', null, $themes, 'theme' );

		foreach ( (array) $themes as $theme ) {
			$git_theme = array();

			foreach ( (array) self::$extra_headers as $value ) {
				$header   = null;
				$repo_uri = $theme->get( $value );

				/**
				 * Get $repo_uri from themes added to GitHub Updater via hook.
				 */
				foreach ( (array) $additions as $addition ) {
					if ( $theme->stylesheet === $addition['slug'] ) {
						if ( ! empty( $addition[ $value ] ) ) {
							$repo_uri = $addition[ $value ];
							break;
						}
					}
				}

				if ( empty( $repo_uri ) || false === stripos( $value, 'Theme' ) ) {
					continue;
				}

				$header_parts = explode( ' ', $value );
				$repo_parts   = $this->get_repo_parts( $header_parts[0], 'theme' );

				if ( $repo_parts['bool'] ) {
					$header = $this->parse_header_uri( $repo_uri );
					if ( $theme->stylesheet !== $header['repo'] || empty( $header ) ) {
						continue;
					}
				}

				$header = $this->parse_extra_headers( $header, $theme, $header_parts, $repo_parts );

				$git_theme['type']                    = $repo_parts['type'];
				$git_theme['uri']                     = $header['base_uri'] . '/' . $header['owner_repo'];
				$git_theme['enterprise']              = $header['enterprise_uri'];
				$git_theme['enterprise_api']          = $header['enterprise_api'];
				$git_theme['owner']                   = $header['owner'];
				$git_theme['repo']                    = $header['repo'];
				$git_theme['extended_repo']           = $header['repo'];
				$git_theme['name']                    = $theme->get( 'Name' );
				$git_theme['theme_uri']               = $theme->get( 'ThemeURI' );
				$git_theme['author']                  = $theme->get( 'Author' );
				$git_theme['local_version']           = strtolower( $theme->get( 'Version' ) );
				$git_theme['sections']['description'] = $theme->get( 'Description' );
				$git_theme['local_path']              = get_theme_root() . '/' . $git_theme['repo'] . '/';
				$git_theme['local_path_extended']     = null;
				$git_theme['branch']                  = $theme->get( $repo_parts['branch'] );
				$git_theme['branch']                  = ! empty( $git_theme['branch'] ) ? $git_theme['branch'] : 'master';
				$git_theme['languages']               = ! empty( $header['languages'] ) ? $header['languages'] : null;
				$git_theme['ci_job']                  = ! empty( $header['ci_job'] ) ? $header['ci_job'] : null;
				$git_theme['release_asset']           = true === $theme->get( 'Release Asset' );
				$git_theme['broken']                  = ( empty( $header['owner'] ) || empty( $header['repo'] ) );

				break;
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
	 * Get remote theme meta to populate $config theme objects.
	 * Calls to remote APIs to get data.
	 */
	public function get_remote_theme_meta() {
		foreach ( (array) $this->config as $theme ) {

			if ( ! $this->get_remote_repo_meta( $theme ) ) {
				continue;
			}

			// Update theme transient with rollback (branch switching) data.
			add_filter( 'wp_get_update_data', array( &$this, 'set_rollback' ) );

			/*
			 * Add update row to theme row, only in multisite.
			 */
			if ( is_multisite() ) {
				add_action( 'after_theme_row', array( &$this, 'remove_after_theme_row' ), 10, 2 );
				if ( ! $this->tag ) {
					add_action( "after_theme_row_$theme->repo", array( &$this, 'wp_theme_update_row' ), 10, 2 );
					if ( ! $theme->release_asset ) {
						add_action( "after_theme_row_$theme->repo", array(
							&$this,
							'multisite_branch_switcher',
						), 15, 2 );
					}
				}
			}
		}

		if ( ! static::is_wp_cli() ) {
			$this->load_pre_filters();
		}
	}

	/**
	 * Load pre-update filters.
	 */
	public function load_pre_filters() {
		if ( ! is_multisite() ) {
			add_filter( 'wp_prepare_themes_for_js', array( &$this, 'customize_theme_update_html' ) );
		}
		add_filter( 'themes_api', array( &$this, 'themes_api' ), 99, 3 );
		add_filter( 'pre_set_site_transient_update_themes', array( &$this, 'pre_set_site_transient_update_themes' ) );
	}

	/**
	 * Put changelog in themes_api, return WP.org data as appropriate.
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

		$theme = isset( $this->config[ $response->slug ] ) ? $this->config[ $response->slug ] : false;

		// wp.org theme.
		if ( ! $theme ) {
			return $false;
		}

		$response->slug         = $theme->repo;
		$response->name         = $theme->name;
		$response->homepage     = $theme->uri;
		$response->donate_link  = $theme->donate_link;
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

		return $response;
	}

	/**
	 * Add custom theme update row, from /wp-admin/includes/update.php
	 * Display update details or rollback links for multisite installation.
	 *
	 * @param $theme_key
	 * @param $theme
	 *
	 * @author Seth Carstens
	 */
	public function wp_theme_update_row( $theme_key, $theme ) {
		$current = get_site_transient( 'update_themes' );

		$themes_allowedtags = array(
			'a'       => array( 'href' => array(), 'title' => array() ),
			'abbr'    => array( 'title' => array() ),
			'acronym' => array( 'title' => array() ),
			'code'    => array(),
			'em'      => array(),
			'strong'  => array(),
		);
		$theme_name         = wp_kses( $theme['Name'], $themes_allowedtags );
		$wp_list_table      = _get_list_table( 'WP_MS_Themes_List_Table' );
		$details_url        = esc_attr( add_query_arg(
			array(
				'tab'       => 'theme-information',
				'theme'     => $theme_key,
				'TB_iframe' => 'true',
				'width'     => 270,
				'height'    => 400,
			),
			self_admin_url( 'theme-install.php' ) ) );
		$nonced_update_url  = wp_nonce_url(
			$this->get_update_url( 'theme', 'upgrade-theme', $theme_key ),
			'upgrade-theme_' . $theme_key
		);
		$enclosure          = $this->update_row_enclosure( $theme_key, 'theme' );

		/*
		 * Update transient if necessary.
		 */
		if ( empty( $current->response ) && empty( $current->up_to_date ) ) {
			$this->pre_set_site_transient_update_themes( $current );
		}

		if ( isset( $current->response[ $theme_key ] ) ) {
			$response = $current->response[ $theme_key ];
			echo $enclosure['open'];

			printf( esc_html__( 'There is a new version of %s available.', 'github-updater' ),
				$theme_name
			);
			printf( ' <a href="%s" class="thickbox" title="%s"> ',
				$details_url,
				$theme_name
			);
			if ( empty( $response['package'] ) ) {
				printf( esc_html__( 'View version %s details.', 'github-updater' ),
					$response['new_version']
				);
				echo '</a><em>';
				esc_html_e( 'Automatic update is unavailable for this theme.', 'github-updater' );
				echo '</em>';
			} else {
				printf( esc_html__( 'View version %1$s details%2$s or %3$supdate now%4$s.', 'github-updater' ),
					$response['new_version'],
					'</a>',
					sprintf( '<a href="' . $nonced_update_url . '" class="update-link" aria-label="' . esc_html__( 'Update %s now', 'github-updater' ) . '">',
						$theme_name
					),
					'</a>'
				);
			}
			echo $enclosure['close'];

			do_action( "in_theme_update_message-$theme_key", $theme, $response );
		}
	}

	/**
	 * Create branch switcher row for multisite installation.
	 *
	 * @param $theme_key
	 * @param $theme
	 *
	 * @return bool
	 */
	public function multisite_branch_switcher( $theme_key, $theme ) {
		$options = get_site_option( 'github_updater' );
		if ( empty( $options['branch_switch'] ) ) {
			return false;
		}

		$enclosure         = $this->update_row_enclosure( $theme_key, 'theme', true );
		$id                = $theme_key . '-id';
		$branches          = isset( $this->config[ $theme_key ] ) ? $this->config[ $theme_key ]->branches : null;
		$nonced_update_url = wp_nonce_url(
			$this->get_update_url( 'theme', 'upgrade-theme', $theme_key ),
			'upgrade-theme_' . $theme_key
		);

		/*
		 * Get current branch.
		 */
		foreach ( parent::$git_servers as $server ) {
			$branch_key = $server . ' Branch';
			$branch     = $theme->get( $branch_key ) ?: 'master';
			if ( 'master' !== $branch ) {
				break;
			}
		}

		$branch_switch_data                      = array();
		$branch_switch_data['slug']              = $theme_key;
		$branch_switch_data['nonced_update_url'] = $nonced_update_url;
		$branch_switch_data['id']                = $id;
		$branch_switch_data['branch']            = $branch;
		$branch_switch_data['branches']          = $branches;

		/*
		 * Create after_theme_row_
		 */
		echo $enclosure['open'];
		$this->make_branch_switch_row( $branch_switch_data );
		echo $enclosure['close'];

		return true;
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
			$themes      = $this->get_theme_configs();

			/**
			 * Filter to add themes not containing appropriate header line.
			 *
			 * @since   5.4.0
			 * @access  public
			 *
			 * @param   array $additions    Listing of themes to add.
			 *                              Default null.
			 * @param   array $themes       Listing of all themes.
			 * @param         string        'theme'    Type being passed.
			 */
			$additions = apply_filters( 'github_updater_additions', null, $themes, 'theme' );
			foreach ( (array) $additions as $addition ) {
				if ( $theme_key === $addition['slug'] ) {
					if ( ! empty( $addition[ $server . ' Theme URI' ] ) ) {
						$repo_uri = $addition[ $server . ' Theme URI' ];
						break;
					}
				}
			}
			if ( empty( $repo_uri ) ) {
				continue;
			}

			remove_action( "after_theme_row_$theme_key", 'wp_theme_update_row', 10 );
			break;
		}
	}

	/**
	 * Call theme messaging for single site installation.
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
				$prepared_themes[ $theme->repo ]['update'] = $this->append_theme_actions_content( $theme );
			} else {
				$prepared_themes[ $theme->repo ]['description'] .= $this->append_theme_actions_content( $theme );
			}
			if ( ! $theme->release_asset ) {
				$prepared_themes[ $theme->repo ]['description'] .= $this->single_install_switcher( $theme );
			}
		}

		return $prepared_themes;
	}

	/**
	 * Create theme update messaging for single site installation.
	 *
	 * @author Seth Carstens
	 *
	 * @access protected
	 *
	 * @param object $theme
	 *
	 * @return string (content buffer)
	 */
	protected function append_theme_actions_content( $theme ) {
		$details_url       = esc_attr( add_query_arg(
			array(
				'tab'       => 'theme-information',
				'theme'     => $theme->repo,
				'TB_iframe' => 'true',
				'width'     => 270,
				'height'    => 400,
			),
			self_admin_url( 'theme-install.php' ) ) );
		$nonced_update_url = wp_nonce_url(
			$this->get_update_url( 'theme', 'upgrade-theme', $theme->repo ),
			'upgrade-theme_' . $theme->repo
		);

		$current = get_site_transient( 'update_themes' );

		/**
		 * Display theme update links.
		 */
		ob_start();
		if ( isset( $current->response[ $theme->repo ] ) ) {
			?>
			<p>
				<strong>
					<?php
					printf( esc_html__( 'There is a new version of %s available.', 'github-updater' ),
						$theme->name
					);
					printf( ' <a href="%s" class="thickbox open-plugin-details-modal" title="%s">',
						$details_url,
						esc_attr( $theme->name )
					);
					printf( esc_html__( 'View version %1$s details%2$s or %3$supdate now%4$s.', 'github-updater' ),
						$theme->remote_version,
						'</a>',
						sprintf( '<a aria-label="' . esc_html__( 'Update %s now', 'github-updater' ) . '" id="update-theme" data-slug="' . $theme->repo . '" href="' . $nonced_update_url . '">',
							$theme->name
						),
						'</a>'
					);
					?>
				</strong>
			</p>
			<?php
		}

		return trim( ob_get_clean(), '1' );
	}

	/**
	 * Display rollback/branch switcher for single site installation.
	 *
	 * @access protected
	 *
	 * @param object $theme
	 *
	 * @return string
	 */
	protected function single_install_switcher( $theme ) {
		$show_button       = true;
		$options           = get_site_option( 'github_updater' );
		$nonced_update_url = wp_nonce_url(
			$this->get_update_url( 'theme', 'upgrade-theme', $theme->repo ),
			'upgrade-theme_' . $theme->repo
		);
		$rollback_url      = sprintf( '%s%s', $nonced_update_url, '&rollback=' );

		ob_start();
		if ( ! empty( $options['branch_switch'] ) ) {
			printf( '<p>' . esc_html__( 'Current branch is `%1$s`, try %2$sanother version%3$s', 'github-updater' ),
				$theme->branch,
				'<a href="#" onclick="jQuery(\'#ghu_versions\').toggle();return false;">',
				'</a>.</p>'
			);
			?>
			<div id="ghu_versions" style="display:none; width: 100%;">
				<label><select style="width: 60%;"
							   onchange="if(jQuery(this).val() != '') {
								   jQuery(this).parent().next().show();
								   jQuery(this).parent().next().attr('href','<?php echo esc_url( $rollback_url ) ?>'+jQuery(this).val());
								   }
								   else jQuery(this).parent().next().hide();
								   ">
						<option value=""><?php esc_html_e( 'Choose a Version', 'github-updater' ); ?>&#8230;</option>
						<?php
						foreach ( array_keys( $theme->branches ) as $branch ) {
							echo '<option>' . $branch . '</option>';
						}
						if ( ! empty( $theme->rollback ) ) {
							$rollback = array_keys( $theme->rollback );
							usort( $rollback, 'version_compare' );
							krsort( $rollback );
							$rollback = array_splice( $rollback, 0, 4, true );
							array_shift( $rollback ); // Dump current tag.
							foreach ( $rollback as $tag ) {
								echo '<option>' . $tag . '</option>';
							}
						}
						if ( empty( $options['branch_switch'] ) &&
						     empty( $theme->rollback )
						) {
							echo '<option>' . esc_html__( 'No previous tags to rollback to.', 'github-updater' ) . '</option></select></label>';
							$show_button = false;
						}
						?>
					</select></label>
				<?php if ( $show_button ) : ?>
					<a style="display: none;" class="button-primary" href="?"><?php esc_html_e( 'Install', 'github-updater' ); ?></a>
				<?php endif; ?>
			</div>
			<?php

		}

		return trim( ob_get_clean(), '1' );
	}

	/**
	 * Hook into pre_set_site_transient_update_themes to update.
	 * Finds newest tag and compares to current tag.
	 *
	 * @param array $transient
	 *
	 * @return array|object
	 */
	public function pre_set_site_transient_update_themes( $transient ) {

		foreach ( (array) $this->config as $theme ) {

			if ( $this->can_update( $theme ) ) {
				$response = array(
					'theme'       => $theme->repo,
					'new_version' => $theme->remote_version,
					'url'         => $theme->uri,
					'package'     => $theme->download_link,
					'branch'      => $theme->branch,
					'branches'    => array_keys( $theme->branches ),
				);

				/*
				 * Skip on branch switching or rollback.
				 */
				if ( $this->tag &&
				     ( isset( $_GET['theme'] ) && $theme->repo === $_GET['theme'] )
				) {
					continue;
				}

				/*
				 * Skip on RESTful updating.
				 */
				if ( isset( $_GET['action'] ) && 'github-updater-update' === $_GET['action'] &&
				     $response['theme'] === $_GET['theme']
				) {
					continue;
				}

				$transient->response[ $theme->repo ] = $response;
			}
		}

		return $transient;
	}

}
