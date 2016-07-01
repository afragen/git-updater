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
		if ( isset( $_GET['force-check'] ) ) {
			$this->delete_all_transients( 'themes' );
		}

		/*
		 * Get details of installed git sourced themes.
		 */
		$this->config = $this->get_theme_meta();

		if ( empty( $this->config ) ) {
			return false;
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
			$git_theme           = array();
			$repo_uri            = null;
			$repo_enterprise_uri = null;
			$repo_enterprise_api = null;

			foreach ( (array) self::$extra_headers as $value ) {

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

				if ( empty( $repo_uri ) ||
				     false === stristr( $value, 'Theme' )
				) {
					continue;
				}

				$header_parts = explode( ' ', $value );
				$repo_parts   = $this->get_repo_parts( $header_parts[0], 'theme' );

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
					switch ( $header_parts[0] ) {
						case 'GitHub':
							$repo_enterprise_api = $repo_enterprise_uri . '/api/v3';
							break;
						case 'GitLab':
							$repo_enterprise_api = $repo_enterprise_uri . '/api/v3';
							break;
					}
				}

				$git_theme['type']                    = $repo_parts['type'];
				$git_theme['uri']                     = $repo_parts['base_uri'] . $header['owner_repo'];
				$git_theme['enterprise']              = $repo_enterprise_uri;
				$git_theme['enterprise_api']          = $repo_enterprise_api;
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

			/*
			 * Update theme transient with rollback data.
			 */
			if ( ! empty( $_GET['rollback'] ) &&
			     ( isset( $_GET['theme'] ) && $theme->repo === $_GET['theme'] )
			) {
				$this->tag         = $_GET['rollback'];
				$updates_transient = get_site_transient( 'update_themes' );
				$rollback          = array(
					'theme'       => $theme->repo,
					'new_version' => $this->tag,
					'url'         => $theme->uri,
					'package'     => $this->repo_api->construct_download_link( $this->tag, false ),
				);
				if ( array_key_exists( $this->tag, $theme->branches ) ) {
					$rollback['new_version'] = '0.0.0';
				}
				$updates_transient->response[ $theme->repo ] = $rollback;
				set_site_transient( 'update_themes', $updates_transient );
			}

			/*
			 * Add update row to theme row, only in multisite.
			 */
			if ( is_multisite() ) {
				add_action( 'after_theme_row', array( &$this, 'remove_after_theme_row' ), 10, 2 );
				if ( ! $this->tag ) {
					add_action( "after_theme_row_$theme->repo", array( &$this, 'wp_theme_update_row' ), 10, 2 );
					add_action( "after_theme_row_$theme->repo", array( &$this, 'multisite_branch_switcher' ), 15, 2 );
				}
			}
		}
		$this->make_force_check_transient( 'themes' );
		$this->load_pre_filters();
	}

	/**
	 * Load pre-update filters.
	 */
	public function load_pre_filters() {
		wp_enqueue_style( 'github-updater', plugins_url( basename( dirname( dirname( __DIR__ ) ) ) ) . '/css/github-updater.css' );

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

				break;
			}
		}

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
			self_admin_url( "theme-install.php" ) ) );
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

		if ( isset( $current->up_to_date[ $theme_key ] ) ) {
			$rollback = array_splice( $current->up_to_date[ $theme_key ]['rollback'], 0, 4, true );
			array_shift( $rollback ); // Dump current tag.

			echo $enclosure['open'];
			esc_html_e( 'Theme is up-to-date!', 'github-updater' );
			echo '&nbsp';
			if ( ! empty( $rollback ) ) {
				echo '<strong>';
				esc_html_e( 'Rollback to:', 'github-updater' );
				echo '</strong> ';
				foreach ( array_keys( $rollback ) as $version ) {
					printf( '<a href="%1$s%2$s" aria-label="%3$s">%4$s</a>',
						$nonced_update_url,
						'&rollback=' . urlencode( $version ),
						sprintf( '%1$s ' . $theme_name . ' %2$s',
							esc_html__( 'Rollback', 'github-updater' ),
							esc_html__( 'now', 'github-updater' )
						),
						$version
					);
					array_shift( $rollback );
					if ( ! empty( $rollback ) ) {
						echo ', ';
					}
				}
			} else {
				esc_html_e( 'No previous tags to rollback to.', 'github-updater' );
			}
			echo $enclosure['close'];
		}

		if ( isset( $current->response[ $theme_key ] ) ) {
			$response = $current->response[ $theme_key ];
			echo $enclosure['open'];

			printf( esc_html__( 'GitHub Updater shows a new version of %s available.', 'github-updater' ),
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
					sprintf( '<a href="' . $nonced_update_url . '" class="update-link" aria-label="%1$s ' . $theme_name . ' %2$s">',
						esc_html__( 'Update', 'github-updater' ),
						esc_html__( 'now', 'github-updater' )
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
	 * @return bool|void
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
			$branch     = $theme->get( $branch_key ) ? $theme->get( $branch_key ) : 'master';
			if ( 'master' !== $branch ) {
				break;
			}
		}

		/*
		 * Create after_theme_row_
		 */
		echo $enclosure['open'];
		printf( esc_html__( 'Current branch is `%1$s`, try %2$sanother branch%3$s.', 'github-updater' ),
			$branch,
			'<a href="#" onclick="jQuery(\'#' . $id . '\').toggle();return false;">',
			'</a>'
		);

		print( '<ul id="' . $id . '" style="display:none; width: 100%;">' );
		foreach ( $branches as $branch => $uri ) {
			printf( '<li><a href="%s%s" aria-label="' . esc_html__( 'Switch to branch ', 'github-updater' ) . $branch . '">%s</a></li>',
				$nonced_update_url,
				'&rollback=' . urlencode( $branch ),
				esc_attr( $branch )
			);
		}
		print( '</ul>' );
		echo $enclosure['close'];
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
			$prepared_themes[ $theme->repo ]['description'] .= $this->single_install_switcher( $theme );
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
			self_admin_url( "theme-install.php" ) ) );
		$nonced_update_url = wp_nonce_url(
			$this->get_update_url( 'theme', 'upgrade-theme', $theme->repo ),
			'upgrade-theme_' . $theme->repo
		);

		$theme_update_transient = get_site_transient( 'update_themes' );

		/**
		 * Display theme update links.
		 */
		ob_start();
		if ( empty( $theme_update_transient->up_to_date[ $theme->repo ] ) ) {
			?>
			<p>
				<strong>
					<?php
					printf( esc_html__( 'There is a new version of %s available now.', 'github-updater' ),
						$theme->name
					);
					printf( ' <a href="%s" class="thickbox open-plugin-details-modal" title="%s">',
						$details_url,
						esc_attr( $theme->name )
					);
					printf( esc_html__( 'View version %1$s details%2$s or %3$supdate now%4$s.', 'github-updater' ),
						$theme->remote_version,
						'</a>',
						sprintf( '<a aria-label="%1$s ' . $theme->name . ' %2$s" id="update-theme" data-slug="' . $theme->repo . '" href="' . $nonced_update_url . '">',
							esc_html__( 'Update', 'github-updater' ),
							esc_html__( 'now', 'github-updater' )
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
		$show_button            = true;
		$options                = get_site_option( 'github_updater' );
		$theme_update_transient = get_site_transient( 'update_themes' );
		$nonced_update_url      = wp_nonce_url(
			$this->get_update_url( 'theme', 'upgrade-theme', $theme->repo ),
			'upgrade-theme_' . $theme->repo
		);
		$rollback_url           = sprintf( '%s%s', $nonced_update_url, '&rollback=' );

		ob_start();
		printf( '<p>' . esc_html__( 'Current branch is `%s`. Try %sanother version%s', 'github-updater' ),
			$theme->branch,
			'<a href="#" onclick="jQuery(\'#ghu_versions\').toggle();return false;">',
			'</a></p>'
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
					if ( ! empty( $options['branch_switch'] ) ) {
						foreach ( array_keys( $theme->branches ) as $branch ) {
							echo '<option>' . $branch . '</option>';
						}
					}
					if ( isset( $theme_update_transient->up_to_date[ $theme->repo ] ) ) {
						$rollback = array_slice( $theme_update_transient->up_to_date[ $theme->repo ]['rollback'], 0, 4, true );
						array_shift( $rollback ); // Dump current tag.
						foreach ( array_keys( $rollback ) as $version ) {
							echo '<option>' . $version . '</option>';
						}
					}
					if ( empty( $options['branch_switch'] ) &&
					     empty( $theme_update_transient->up_to_date[ $theme->repo ]['rollback'] )
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
