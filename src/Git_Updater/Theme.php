<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater;

use Fragen\Singleton;
use Fragen\Git_Updater\Traits\GU_Trait;
use Fragen\Git_Updater\PRO\Branch;

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
	use GU_Trait;

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
				$filepath = \file_exists( "{$theme->theme_root}/{$theme->stylesheet}/style.css" )
					? "{$theme->theme_root}/{$theme->stylesheet}/style.css"
					: null;

				return $filepath;
			},
			$themes
		);
		$paths = array_filter( $paths );

		$repos_arr = [];
		foreach ( $paths as $slug => $path ) {
			$all_headers        = $this->get_headers( 'theme' );
			$repos_arr[ $slug ] = get_file_data( $path, $all_headers, 'theme' );
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

		$additions = apply_filters( 'gu_additions', null, $themes, 'theme' );
		$additions = null === $additions ? apply_filters_deprecated( 'github_updater_additions', [ null, $themes, 'theme' ], '10.0.0', 'gu_additions' ) : $additions;

		$themes = array_merge( $themes, (array) $additions );
		ksort( $themes );

		foreach ( (array) $themes as $slug => $theme ) {
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
			if ( null === $key || ! \array_key_exists( $key, $all_headers ) ) {
				continue;
			}
			$repo_uri = $theme[ $key ];

			$header_parts = explode( ' ', self::$extra_headers[ $key ] );
			$repo_parts   = $this->get_repo_parts( $header_parts[0], 'theme' );

			if ( $repo_parts['bool'] ) {
				$header = $this->parse_header_uri( $repo_uri );
			}

			$header         = $this->parse_extra_headers( $header, $theme, $header_parts );
			$current_branch = isset( $header['repo'] ) ? "current_branch_{$header['repo']}" : null;

			if ( isset( self::$options[ $current_branch ] )
			&& ( 'master' === self::$options[ $current_branch ] && 'master' !== $header['primary_branch'] )
			) {
				unset( self::$options[ $current_branch ] );
				update_site_option( 'git_updater', self::$options );
			}
			$branch = isset( self::$options[ $current_branch ] )
				? self::$options[ $current_branch ]
				: $header['primary_branch'];

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
			$git_theme['local_path']              = trailingslashit( dirname( $paths[ $slug ] ) );
			$git_theme['branch']                  = $branch;
			$git_theme['primary_branch']          = $header['primary_branch'];
			$git_theme['languages']               = $header['languages'];
			$git_theme['ci_job']                  = $header['ci_job'];
			$git_theme['release_asset']           = $header['release_asset'];
			$git_theme['broken']                  = ( empty( $header['owner'] ) || empty( $header['repo'] ) );

			// Fix branch for .git VCS.
			if ( file_exists( $git_theme['local_path'] . '.git/HEAD' ) ) {
				$git_branch           = implode( '/', array_slice( explode( '/', file_get_contents( $git_theme['local_path'] . '.git/HEAD' ) ), 2 ) );
				$git_plugin['branch'] = preg_replace( "/\r|\n/", '', $git_branch );
			}

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

		/**
		 * Filter repositories.
		 *
		 * @since 10.2.0
		 * @param array $this->config Array of repository objects.
		 */
		$config = apply_filters( 'gu_config_pre_process', $this->config );

		foreach ( (array) $config as $theme ) {
			$disable_wp_cron = (bool) apply_filters( 'gu_disable_wpcron', false );
			$disable_wp_cron = $disable_wp_cron ?: (bool) apply_filters_deprecated( 'github_updater_disable_wpcron', [ false ], '10.0.0', 'gu_disable_wpcron' );

			if ( ! $this->waiting_for_background_update( $theme ) || static::is_wp_cli() || $disable_wp_cron
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
					if ( $this->is_premium_only() ) {
						add_action( "after_theme_row_{$theme->slug}", [ new Branch(), 'multisite_branch_switcher' ], 15, 2 );
					}
				}
			}
		}

		$schedule_event = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? is_main_site() : true;

		$disable_wp_cron = (bool) apply_filters( 'gu_disable_wpcron', false );
		$disable_wp_cron = $disable_wp_cron ?: (bool) apply_filters_deprecated( 'github_updater_disable_wpcron', [ false ], '10.0.0', 'gu_disable_wpcron' );

		if ( $schedule_event && ! empty( $themes ) ) {
			if ( ! $disable_wp_cron && ! $this->is_cron_event_scheduled( 'gu_get_remote_theme' ) ) {
				wp_schedule_single_event( time(), 'gu_get_remote_theme', [ $themes ] );
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
	 * @param bool      $false    Default false.
	 * @param string    $action   The type of information being requested from the Theme Installation API.
	 * @param \stdClass $response Theme API arguments.
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
		if ( ! $theme || ( isset( $theme->dot_org ) && $theme->dot_org ) ) {
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
	 * @param string $theme_key Theme slug.
	 * @param array  $theme     Array of theme data.
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
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
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
			echo wp_kses_post( $enclosure['open'] );

			printf(
				/* translators: %s: theme name */
				esc_html__( 'There is a new version of %s available.', 'git-updater' ),
				esc_attr( $theme_name )
			);
			printf(
				/* translators: %s: details URL, theme name */
				' <a href="%s" class="thickbox" title="%s"> ',
				esc_url( $details_url ),
				esc_attr( $theme_name )
			);
			if ( empty( $response['package'] ) ) {
				printf(
					/* translators: %s: theme version */
					esc_html__( 'View version %s details.', 'git-updater' ),
					esc_attr( $response['new_version'] )
				);
				echo '</a>&nbsp;<em>';
				esc_html_e( 'Automatic update is unavailable for this theme.', 'git-updater' );
				echo '</em>';
			} else {
				printf(
					/* translators: 1: version number, 2: closing anchor tag, 3: update URL */
					esc_html__( 'View version %1$s details%2$s or %3$supdate now%2$s.', 'git-updater' ),
					esc_attr( $response['new_version'] ),
					'</a>',
					sprintf(
						/* translators: %s: theme name */
						'<a href="' . esc_url( $nonced_update_url ) . '" class="update-link" aria-label="' . esc_html__( 'Update %s now', 'git-updater' ) . '">',
						esc_attr( $theme_name )
					)
				);
			}
			echo wp_kses_post( $enclosure['close'] );

			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			do_action( "in_theme_update_message-$theme_key", $theme, $response );
		}
	}


	/**
	 * Remove default after_theme_row_$stylesheet.
	 *
	 * @author @grappler
	 *
	 * @param string $theme_key Theme slug.
	 * @param array  $theme     Array of theme data.
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
	 * @param array $prepared_themes Array of prepared themes.
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
			$ignore = $this->get_class_vars( 'Ignore', 'repos' );
			if ( $this->is_premium_only() && ! array_key_exists( $theme->slug, $ignore ) ) {
				$prepared_themes[ $theme->slug ]['description'] .= ( new Branch() )->single_install_switcher( $theme );
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
	 * @param \stdClass $theme Theme object.
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
						esc_html__( 'There is a new version of %s available.', 'git-updater' ),
						esc_attr( $theme->name )
					);
					printf(
						' <a href="%s" class="thickbox open-plugin-details-modal" title="%s">',
						esc_url( $details_url ),
						esc_attr( $theme->name )
					);
					if ( ! empty( $current->response[ $theme->slug ]['package'] ) ) {
						printf(
							/* translators: 1: version number, 2: closing anchor tag, 3: update URL */
							esc_html__( 'View version %1$s details%2$s or %3$supdate now%2$s.', 'git-updater' ),
							$theme->remote_version = isset( $theme->remote_version ) ? esc_attr( $theme->remote_version ) : null,
							'</a>',
							sprintf(
							/* translators: %s: theme name */
								'<a aria-label="' . esc_html__( 'Update %s now', 'git-updater' ) . '" id="update-theme" data-slug="' . esc_attr( $theme->slug ) . '" href="' . esc_url( $nonced_update_url ) . '">',
								esc_attr( $theme->name )
							)
						);
					} else {
						printf(
							/* translators: 1: version number, 2: closing anchor tag, 3: update URL */
							esc_html__( 'View version %1$s details%2$s.', 'git-updater' ),
							$theme->remote_version = isset( $theme->remote_version ) ? esc_attr( $theme->remote_version ) : null,
							'</a>'
						);
						printf(
							/* translators: %s: opening/closing paragraph and italic tags */
							esc_html__( '%1$sAutomatic update is unavailable for this theme.%2$s', 'git-updater' ),
							'<p><i>',
							'</i></p>'
						);
					}
					?>
				</strong>
			</p>
			<?php
		}

		return trim( ob_get_clean(), '1' );
	}

	/**
	 * Hook into site_transient_update_themes to update.
	 * Finds newest tag and compares to current tag.
	 *
	 * @param array $transient Theme update transient.
	 *
	 * @return array|\stdClass
	 */
	public function update_site_transient( $transient ) {
		// needed to fix PHP 7.4 warning.
		if ( ! \is_object( $transient ) ) {
			$transient = new \stdClass();
		}

		/**
		 * Filter repositories.
		 *
		 * @since 10.2.0
		 * @param array $this->config Array of repository objects.
		 */
		$config = apply_filters( 'gu_config_pre_process', $this->config );

		foreach ( (array) $config as $theme ) {
			$theme_requires = $this->get_repo_requirements( $theme );
			$response       = [
				'theme'            => $theme->slug,
				'url'              => $theme->uri,
				'branch'           => $theme->branch,
				'type'             => "{$theme->git}-{$theme->type}",
				'update-supported' => true,
				'requires'         => $theme_requires['RequiresWP'],
				'requires_php'     => $theme_requires['RequiresPHP'],
			];
			if ( property_exists( $theme, 'remote_version' ) && $theme->remote_version ) {
				$response_api_checked = [
					'new_version'  => $theme->remote_version,
					'package'      => $theme->download_link,
					'tested'       => $theme->tested,
					'requires'     => $theme->requires,
					'requires_php' => $theme->requires_php,
					'branches'     => array_keys( $theme->branches ),
				];
				$response             = array_merge( $response, $response_api_checked );
			}

			if ( $this->can_update_repo( $theme ) ) {
				// Skip on RESTful updating.
				// phpcs:disable WordPress.Security.NonceVerification.Recommended
				if ( isset( $_GET['action'], $_GET['theme'] )
					&& 'git-updater-update' === $_GET['action']
					&& $response['theme'] === $_GET['theme']
				) {
					continue;
				}
				// phpcs:enable

				// Pull update from dot org if not overriding.
				if ( ! $this->override_dot_org( 'theme', $theme ) ) {
					continue;
				}

				// Update download link for release_asset non-primary branches.
				if ( $theme->release_asset && $theme->primary_branch !== $theme->branch ) {
					$response['package'] = isset( $theme->branches[ $theme->branch ] )
					? $theme->branches[ $theme->branch ]['download']
					: null;
				}

				$transient->response[ $theme->slug ] = $response;
			} else {
				// Add repo without update to $transient->no_update for Auto-updates link.
				if ( ! isset( $transient->no_update[ $theme->slug ] ) ) {
					$transient->no_update[ $theme->slug ] = $response;
				}

				$overrides = apply_filters( 'gu_override_dot_org', [] );
				$overrides = empty( $overrides ) ? apply_filters_deprecated( 'github_updater_override_dot_org', [ [] ], '10.0.0', 'gu_override_dot_org' ) : $overrides;

				if ( isset( $transient->response[ $theme->slug ] ) && in_array( $theme->slug, $overrides, true ) ) {
					unset( $transient->response[ $theme->slug ] );
				}
			}

			// Set transient for rollback.
			if ( isset( $_GET['_wpnonce'], $_GET['theme'], $_GET['rollback'] )
				&& wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'upgrade-theme_' . $theme->slug )
			) {
				$transient->response[ $theme->slug ] = ( new Branch() )->set_rollback_transient( 'theme', $theme );
			}
		}
		if ( property_exists( $transient, 'response' ) ) {
			update_site_option( 'git_updater_theme_updates', $transient->response );
		}

		return $transient;
	}
}
