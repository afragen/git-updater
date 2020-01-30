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
 * @author    Andy Fragen
 * @author    Seth Carstens
 * @link      https://github.com/WordPress-Phoenix/whitelabel-framework
 * @author    UCF Web Communications
 * @link      https://github.com/UCF/Theme-Updater
 */
class Theme {
	use GHU_Trait;

	/**
	 * Holds Class Base object.
	 *
	 * @var Base
	 */
	protected $base;

	/**
	 * Hold config array.
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Holds extra headers.
	 *
	 * @var array
	 */
	private static $extra_headers;

	/**
	 * Holds options.
	 *
	 * @var array
	 */
	private static $options;

	/**
	 * Rollback variable.
	 *
	 * @var string|bool
	 */
	protected $tag = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->base          = Singleton::get_instance( 'Base', $this );
		self::$extra_headers = $this->get_class_vars( 'Base', 'extra_headers' );
		self::$options       = $this->get_class_vars( 'Base', 'options' );
		$this->load_options();

		// Get details of installed git sourced themes.
		$this->config = $this->get_theme_meta();

		if ( null === $this->config ) {
			return;
		}
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
	 * Delete cache of current theme.
	 * This is needed in case `wp_get_theme()` is called in earlier or in a mu-plugin.
	 * This action results in the extra headers not being added.
	 *
	 * @link https://github.com/afragen/github-updater/issues/586
	 */
	private function delete_current_theme_cache() {
		$cache_hash = md5( get_stylesheet_directory() );
		wp_cache_delete( 'theme-' . $cache_hash, 'themes' );
	}

	/**
	 * Get details of Git-sourced themes from those that are installed.
	 * Populates variable array.
	 *
	 * @return array Indexed array of associative arrays of theme details.
	 */
	protected function get_theme_meta() {
		$this->delete_current_theme_cache();
		$git_themes = [];
		$themes     = wp_get_themes( [ 'errors' => null ] );

		$paths = array_map(
			function ( $theme ) {
				return "$theme->theme_root/$theme->stylesheet/style.css";
			},
			$themes
		);

		$repos_arr = [];
		foreach ( $paths as $slug => $path ) {
			$all_headers        = $this->get_headers( 'theme' );
			$repos_arr[ $slug ] = get_file_data( $path, $all_headers );
		}

		$themes = array_filter(
			$repos_arr,
			function ( $repo ) {
				foreach ( $repo as $key => $value ) {
					if ( in_array( $key, array_keys( self::$extra_headers ), true ) && false !== stripos( $key, 'theme' ) && ! empty( $value ) ) {
						return $this->get_file_headers( $repo, 'theme' );
					}
				}
			}
		);

		/**
		 * Filter to add themes not containing appropriate header line.
		 *
		 * @since   5.4.0
		 * @access  public
		 *
		 * @param array $additions Listing of themes to add.
		 *                         Default null.
		 * @param array $themes    Listing of all themes.
		 * @param string 'theme'    Type being passed.
		 */
		$additions = apply_filters( 'github_updater_additions', null, $themes, 'theme' );
		$themes    = array_merge( $themes, (array) $additions );

		foreach ( (array) $themes as $theme ) {
			$git_theme = [];
			$header    = null;
			$key       = array_filter(
				array_keys( $theme ),
				function ( $key ) use ( $theme ) {
					if ( false !== stripos( $key, 'themeuri' ) && ! empty( $theme[ $key ] ) & 'ThemeURI' !== $key ) {
						return $key;
					}
				}
			);

			$key = array_pop( $key );
			if ( null === $key ) {
				continue;
			}
			$repo_uri = $theme[ $key ];

			$header_parts = explode( ' ', self::$extra_headers[ $key ] );
			$repo_parts   = $this->get_repo_parts( $header_parts[0], 'theme' );

			if ( $repo_parts['bool'] ) {
				$header = $this->parse_header_uri( $repo_uri );
			}

			$header                               = $this->parse_extra_headers( $header, $theme, $header_parts, $repo_parts );
			$current_branch                       = "current_branch_{$header['repo']}";
			$branch                               = isset( self::$options[ $current_branch ] )
				? self::$options[ $current_branch ]
				: false;
			$git_theme['type']                    = 'theme';
			$git_theme['git']                     = $repo_parts['git_server'];
			$git_theme['uri']                     = "{$header['base_uri']}/{$header['owner_repo']}";
			$git_theme['enterprise']              = $header['enterprise_uri'];
			$git_theme['enterprise_api']          = $header['enterprise_api'];
			$git_theme['owner']                   = $header['owner'];
			$git_theme['slug']                    = $header['repo'];
			$git_theme['file']                    = "{$header['repo']}/style.css";
			$git_theme['name']                    = $theme['Name'];
			$git_theme['theme_uri']               = $theme['ThemeURI'];
			$git_theme['homepage']                = $theme['ThemeURI'];
			$git_theme['author']                  = $theme['Author'];
			$git_theme['local_version']           = strtolower( $theme['Version'] );
			$git_theme['sections']['description'] = $theme['Description'];
			$git_theme['local_path']              = get_theme_root() . '/' . $git_theme['slug'] . '/';
			$git_theme['branch']                  = $branch ?: 'master';
			$git_theme['languages']               = $header['languages'];
			$git_theme['ci_job']                  = $header['ci_job'];
			$git_theme['release_asset']           = $header['release_asset'];
			$git_theme['broken']                  = ( empty( $header['owner'] ) || empty( $header['repo'] ) );

			$git_themes[ $git_theme['slug'] ] = (object) $git_theme;
		}

		return $git_themes;
	}

	/**
	 * Get remote theme meta to populate $config theme objects.
	 * Calls to remote APIs to get data.
	 */
	public function get_remote_theme_meta() {
		$themes = [];
		foreach ( (array) $this->config as $theme ) {
			/**
			 * Filter to set if WP-Cron is disabled or if user wants to return to old way.
			 *
			 * @since  7.4.0
			 * @access public
			 *
			 * @param bool
			 */
			if ( ! $this->waiting_for_background_update( $theme ) || static::is_wp_cli()
				|| apply_filters( 'github_updater_disable_wpcron', false )
			) {
				$this->base->get_remote_repo_meta( $theme );
			} else {
				$themes[ $theme->slug ] = $theme;
			}

			/*
			 * Add update row to theme row, only in multisite.
			 */
			if ( is_multisite() ) {
				add_action( 'after_theme_row', [ $this, 'remove_after_theme_row' ], 10, 2 );
				if ( ! $this->tag ) {
					add_action( "after_theme_row_{$theme->slug}", [ $this, 'wp_theme_update_row' ], 10, 2 );
					if ( ! $theme->release_asset ) {
						add_action( "after_theme_row_{$theme->slug}", [ $this, 'multisite_branch_switcher' ], 15, 2 );
					}
				}
			}
		}

		$schedule_event = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? is_main_site() : true;

		if ( $schedule_event && ! empty( $themes ) ) {
			if ( ! wp_next_scheduled( 'ghu_get_remote_theme' ) &&
			! $this->is_duplicate_wp_cron_event( 'ghu_get_remote_theme' ) &&
			! apply_filters( 'github_updater_disable_wpcron', false )
			) {
				wp_schedule_single_event( time(), 'ghu_get_remote_theme', [ $themes ] );
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
			add_filter( 'wp_prepare_themes_for_js', [ $this, 'customize_theme_update_html' ] );
		}
		add_filter( 'themes_api', [ $this, 'themes_api' ], 99, 3 );
		add_filter( 'site_transient_update_themes', [ $this, 'update_site_transient' ], 15, 1 );
	}

	/**
	 * Put changelog in themes_api, return WP.org data as appropriate.
	 *
	 * @param bool      $false
	 * @param string    $action
	 * @param \stdClass $response
	 *
	 * @return mixed
	 */
	public function themes_api( $false, $action, $response ) {
		if ( ! ( 'theme_information' === $action ) ) {
			return $false;
		}

		$theme = isset( $this->config[ $response->slug ] ) ? $this->config[ $response->slug ] : false;

		// Skip if waiting for background update.
		if ( $this->waiting_for_background_update( $theme ) ) {
			return $false;
		}

		// wp.org theme.
		if ( ! $theme ) {
			return $false;
		}

		$response->slug         = $theme->slug;
		$response->name         = $theme->name;
		$response->homepage     = $theme->homepage;
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
	 * @param string $theme_key
	 * @param array  $theme
	 *
	 * @author Seth Carstens
	 */
	public function wp_theme_update_row( $theme_key, $theme ) {
		$current = get_site_transient( 'update_themes' );

		$themes_allowedtags = [
			'a'       => [
				'href'  => [],
				'title' => [],
			],
			'abbr'    => [ 'title' => [] ],
			'acronym' => [ 'title' => [] ],
			'code'    => [],
			'em'      => [],
			'strong'  => [],
		];
		$theme_name         = wp_kses( $theme['Name'], $themes_allowedtags );
		// $wp_list_table      = _get_list_table( 'WP_MS_Themes_List_Table' );
		$details_url       = esc_attr(
			add_query_arg(
				[
					'tab'       => 'theme-information',
					'theme'     => $theme_key,
					'TB_iframe' => 'true',
					'width'     => 270,
					'height'    => 400,
				],
				self_admin_url( 'theme-install.php' )
			)
		);
		$nonced_update_url = wp_nonce_url(
			$this->base->get_update_url( 'theme', 'upgrade-theme', $theme_key ),
			'upgrade-theme_' . $theme_key
		);
		$enclosure         = $this->base->update_row_enclosure( $theme_key, 'theme' );

		if ( isset( $current->response[ $theme_key ] ) ) {
			$response = $current->response[ $theme_key ];
			echo $enclosure['open'];

			printf(
				/* translators: %s: theme name */
				esc_html__( 'There is a new version of %s available.', 'github-updater' ),
				$theme_name
			);
			printf(
				/* translators: %s: details URL, theme name */
				' <a href="%s" class="thickbox" title="%s"> ',
				$details_url,
				$theme_name
			);
			if ( empty( $response['package'] ) ) {
				printf(
					/* translators: %s: theme version */
					esc_html__( 'View version %s details.', 'github-updater' ),
					$response['new_version']
				);
				echo '</a>&nbsp;<em>';
				esc_html_e( 'Automatic update is unavailable for this theme.', 'github-updater' );
				echo '</em>';
			} else {
				printf(
					/* translators: 1: version number, 2: closing anchor tag, 3: update URL */
					esc_html__( 'View version %1$s details%2$s or %3$supdate now%2$s.', 'github-updater' ),
					$response['new_version'],
					'</a>',
					sprintf(
						/* translators: %s: theme name */
						'<a href="' . $nonced_update_url . '" class="update-link" aria-label="' . esc_html__( 'Update %s now', 'github-updater' ) . '">',
						$theme_name
					)
				);
			}
			echo $enclosure['close'];

			do_action( "in_theme_update_message-$theme_key", $theme, $response );
		}
	}

	/**
	 * Create branch switcher row for multisite installation.
	 *
	 * @param string $theme_key
	 * @param array  $theme
	 *
	 * @return bool
	 */
	public function multisite_branch_switcher( $theme_key, $theme ) {
		if ( empty( self::$options['branch_switch'] ) ) {
			return false;
		}

		$enclosure         = $this->base->update_row_enclosure( $theme_key, 'theme', true );
		$id                = $theme_key . '-id';
		$branches          = isset( $this->config[ $theme_key ]->branches )
			? $this->config[ $theme_key ]->branches
			: null;
		$nonced_update_url = wp_nonce_url(
			$this->base->get_update_url( 'theme', 'upgrade-theme', $theme_key ),
			'upgrade-theme_' . $theme_key
		);

		// Get current branch.
		$repo   = $this->config[ $theme_key ];
		$branch = Singleton::get_instance( 'Branch', $this )->get_current_branch( $repo );

		$branch_switch_data                      = [];
		$branch_switch_data['slug']              = $theme_key;
		$branch_switch_data['nonced_update_url'] = $nonced_update_url;
		$branch_switch_data['id']                = $id;
		$branch_switch_data['branch']            = $branch;
		$branch_switch_data['branches']          = $branches;

		/*
		 * Create after_theme_row_
		 */
		echo $enclosure['open'];
		$this->base->make_branch_switch_row( $branch_switch_data, $this->config );
		echo $enclosure['close'];

		return true;
	}

	/**
	 * Remove default after_theme_row_$stylesheet.
	 *
	 * @author @grappler
	 *
	 * @param string $theme_key
	 * @param array  $theme
	 */
	public function remove_after_theme_row( $theme_key, $theme ) {
		$themes = $this->get_theme_configs();

		if ( array_key_exists( $theme_key, $themes ) ) {
			remove_action( "after_theme_row_$theme_key", 'wp_theme_update_row' );
		}
	}

	/**
	 * Call theme messaging for single site installation.
	 *
	 * @author Seth Carstens
	 *
	 * @param array $prepared_themes
	 *
	 * @return mixed
	 */
	public function customize_theme_update_html( $prepared_themes ) {
		foreach ( (array) $this->config as $theme ) {
			if ( empty( $prepared_themes[ $theme->slug ] ) ) {
				continue;
			}

			if ( ! empty( $prepared_themes[ $theme->slug ]['hasUpdate'] ) ) {
				$prepared_themes[ $theme->slug ]['update'] = $this->append_theme_actions_content( $theme );
			} else {
				$prepared_themes[ $theme->slug ]['description'] .= $this->append_theme_actions_content( $theme );
			}
			if ( ! $theme->release_asset ) {
				$prepared_themes[ $theme->slug ]['description'] .= $this->single_install_switcher( $theme );
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
	 * @param \stdClass $theme
	 *
	 * @return string (content buffer)
	 */
	protected function append_theme_actions_content( $theme ) {
		$details_url       = esc_attr(
			add_query_arg(
				[
					'tab'       => 'theme-information',
					'theme'     => $theme->slug,
					'TB_iframe' => 'true',
					'width'     => 270,
					'height'    => 400,
				],
				self_admin_url( 'theme-install.php' )
			)
		);
		$nonced_update_url = wp_nonce_url(
			$this->base->get_update_url( 'theme', 'upgrade-theme', $theme->slug ),
			'upgrade-theme_' . $theme->slug
		);

		$current = get_site_transient( 'update_themes' );

		/**
		 * Display theme update links.
		 */
		ob_start();
		if ( isset( $current->response[ $theme->slug ] ) ) {
			?>
			<p>
				<strong>
					<?php
					printf(
						/* translators: %s: theme name */
						esc_html__( 'There is a new version of %s available.', 'github-updater' ),
						$theme->name
					);
					printf(
						' <a href="%s" class="thickbox open-plugin-details-modal" title="%s">',
						$details_url,
						esc_attr( $theme->name )
					);
					printf(
						/* translators: 1: version number, 2: closing anchor tag, 3: update URL */
						esc_html__( 'View version %1$s details%2$s or %3$supdate now%2$s.', 'github-updater' ),
						$theme->remote_version = isset( $theme->remote_version ) ? $theme->remote_version : null,
						'</a>',
						sprintf(
							/* translators: %s: theme name */
							'<a aria-label="' . esc_html__( 'Update %s now', 'github-updater' ) . '" id="update-theme" data-slug="' . $theme->slug . '" href="' . $nonced_update_url . '">',
							$theme->name
						)
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
	 * @param \stdClass $theme
	 *
	 * @return string
	 */
	protected function single_install_switcher( $theme ) {
		$nonced_update_url = wp_nonce_url(
			$this->base->get_update_url( 'theme', 'upgrade-theme', $theme->slug ),
			'upgrade-theme_' . $theme->slug
		);
		$rollback_url      = sprintf( '%s%s', $nonced_update_url, '&rollback=' );

		if ( ! isset( self::$options['branch_switch'] ) ) {
			return;
		}

		ob_start();
		if ( '1' === self::$options['branch_switch'] ) {
			printf(
				/* translators: 1: branch name, 2: jQuery dropdown, 3: closing tag */
				'<p>' . esc_html__( 'Current branch is `%1$s`, try %2$sanother version%3$s', 'github-updater' ),
				$theme->branch,
				'<a href="#" onclick="jQuery(\'#ghu_versions\').toggle();return false;">',
				'</a>.</p>'
			);
			?>
			<div id="ghu_versions" style="display:none; width: 100%;">
				<label><select style="width: 60%;" onchange="if(jQuery(this).val() != '') { jQuery(this).parent().next().show(); jQuery(this).parent().next().attr('href','<?php echo esc_url( $rollback_url ); ?>'+jQuery(this).val()); } else jQuery(this).parent().next().hide();">
				<option value=""><?php esc_html_e( 'Choose a Version', 'github-updater' ); ?>&#8230;</option>
			<?php
			if ( isset( $theme->branches ) ) {
				foreach ( array_keys( $theme->branches ) as $branch ) {
					echo '<option>' . $branch . '</option>';
				}
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
			if ( empty( $theme->rollback ) ) {
				echo '<option>' . esc_html__( 'No previous tags to rollback to.', 'github-updater' ) . '</option></select></label>';
			}
			?>
					</select></label>
				<a style="display: none;" class="button-primary" href="?"><?php esc_html_e( 'Install', 'github-updater' ); ?></a>
			</div>
			<?php
		}

		return trim( ob_get_clean(), '1' );
	}

	/**
	 * Hook into site_transient_update_themes to update.
	 * Finds newest tag and compares to current tag.
	 *
	 * @param array $transient
	 *
	 * @return array|\stdClass
	 */
	public function update_site_transient( $transient ) {
		// needed to fix PHP 7.4 warning.
		if ( ! \is_object( $transient ) ) {
			$transient           = new \stdClass();
			$transient->response = null;
		} elseif ( ! \property_exists( $transient, 'response' ) ) {
			$transient->response = null;
		}

		foreach ( (array) $this->config as $theme ) {
			if ( $this->can_update_repo( $theme ) ) {
				$response = [
					'theme'       => $theme->slug,
					'new_version' => $theme->remote_version,
					'url'         => $theme->uri,
					'package'     => $theme->download_link,
					'branch'      => $theme->branch,
					'branches'    => array_keys( $theme->branches ),
					'type'        => "{$theme->git}-{$theme->type}",
				];

				// Skip on RESTful updating.
				if ( isset( $_GET['action'], $_GET['theme'] ) &&
					'github-updater-update' === $_GET['action'] &&
					$response['theme'] === $_GET['theme']
				) {
					continue;
				}

				// Pull update from dot org if not overriding.
				if ( ! $this->override_dot_org( 'theme', $theme ) ) {
					continue;
				}

				$transient->response[ $theme->slug ] = $response;
			} else {
				/**
				 * Filter to return array of overrides to dot org.
				 *
				 * @since 8.5.0
				 * @return array
				 */
				$overrides = apply_filters( 'github_updater_override_dot_org', [] );
				if ( isset( $transient->response[ $theme->slug ] ) && in_array( $theme->slug, $overrides, true ) ) {
					unset( $transient->response[ $theme->slug ] );
				}
			}

			// Set transient for rollback.
			if ( isset( $_GET['theme'], $_GET['rollback'] ) && $theme->slug === $_GET['theme']
			) {
				$transient->response[ $theme->slug ] = $this->base->set_rollback_transient( 'theme', $theme );
			}
		}

		return $transient;
	}
}
