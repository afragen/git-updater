<?php
/**
 * WP Dependency Installer
 *
 * A lightweight class to add to WordPress plugins or themes to automatically install
 * required plugin dependencies. Uses a JSON config file to declare plugin dependencies.
 * It can install a plugin from w.org, GitHub, Bitbucket, GitLab, Gitea or direct URL.
 *
 * @package   WP_Dependency_Installer
 * @author    Andy Fragen, Matt Gibbs, Raruto
 * @license   MIT
 * @link      https://github.com/afragen/wp-dependency-installer
 */

/**
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'WP_Dependency_Installer' ) ) {
	/**
	 * Class WP_Dependency_Installer
	 */
	class WP_Dependency_Installer {
		/**
		 * Holds the JSON file contents.
		 *
		 * @var array $config
		 */
		private $config;

		/**
		 * Holds the current dependency's slug.
		 *
		 * @var string $current_slug
		 */
		private $current_slug;

		/**
		 * Holds the calling plugin/theme file path.
		 *
		 * @var string $source
		 */
		private static $caller;

		/**
		 * Holds the calling plugin/theme slug.
		 *
		 * @var string $source
		 */
		private static $source;

		/**
		 * Holds names of installed dependencies for admin notices.
		 *
		 * @var array $notices
		 */
		private $notices;

		/**
		 * Factory.
		 *
		 * @param string $caller File path to calling plugin/theme.
		 */
		public static function instance( $caller = false ) {
			static $instance = null;
			if ( null === $instance ) {
				$instance = new self();
			}
			self::$caller = $caller;
			self::$source = ! $caller ? false : basename( $caller );

			return $instance;
		}

		/**
		 * Private constructor.
		 */
		private function __construct() {
			$this->config  = [];
			$this->notices = [];
		}

		/**
		 * Load hooks.
		 *
		 * @return void
		 */
		public function load_hooks() {
			add_action( 'admin_init', [ $this, 'admin_init' ] );
			add_action( 'admin_footer', [ $this, 'admin_footer' ] );
			add_action( 'admin_notices', [ $this, 'admin_notices' ] );
			add_action( 'network_admin_notices', [ $this, 'admin_notices' ] );
			add_action( 'wp_ajax_dependency_installer', [ $this, 'ajax_router' ] );
			add_filter( 'http_request_args', [ $this, 'add_basic_auth_headers' ], 15, 2 );

			// Initialize Persist admin Notices Dismissal dependency.
			add_action( 'admin_init', [ 'PAnD', 'init' ] );
		}

		/**
		 * Let's get going.
		 * First load data from wp-dependencies.json if present.
		 * Then load hooks needed to run.
		 *
		 * @param string $caller Path to plugin or theme calling the framework.
		 *
		 * @return self
		 */
		public function run( $caller = false ) {
			$caller = ! $caller ? self::$caller : $caller;
			$config = $this->json_file_decode( $caller . '/wp-dependencies.json' );
			if ( ! empty( $config ) ) {
				$this->register( $config, $caller );
			}
			if ( ! empty( $this->config ) ) {
				$this->load_hooks();
			}

			return $this;
		}

		/**
		 * Decode JSON config data from a file.
		 *
		 * @param string $json_path File path to JSON config file.
		 *
		 * @return bool|array $config
		 */
		public function json_file_decode( $json_path ) {
			$config = [];
			if ( file_exists( $json_path ) ) {
				$config = file_get_contents( $json_path );
				$config = json_decode( $config, true );
			}

			return $config;
		}

		/**
		 * Register dependencies (supports multiple instances).
		 *
		 * @param array  $config JSON config as array.
		 * @param string $caller Path to plugin or theme calling the framework.
		 *
		 * @return self
		 */
		public function register( $config, $caller = false ) {
			$source = ! self::$source ? basename( $caller ) : self::$source;
			foreach ( $config as $dependency ) {
				// Save a reference of current dependent plugin.
				$dependency['source']    = $source;
				$dependency['sources'][] = $source;
				$slug                    = $dependency['slug'];
				// Keep a reference of all dependent plugins.
				if ( isset( $this->config[ $slug ] ) ) {
					$dependency['sources'] = array_merge( $this->config[ $slug ]['sources'], $dependency['sources'] );
				}
				// Update config.
				if ( ! isset( $this->config[ $slug ] ) || $this->is_required( $dependency ) ) {
					$this->config[ $slug ] = $dependency;
				}
			}

			return $this;
		}

		/**
		 * Process the registered dependencies.
		 */
		private function apply_config() {
			foreach ( $this->config as $dependency ) {
				$download_link = null;
				$base          = null;
				$uri           = $dependency['uri'];
				$slug          = $dependency['slug'];
				$uri_args      = parse_url( $uri ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				$port          = isset( $uri_args['port'] ) ? $uri_args['port'] : null;
				$api           = isset( $uri_args['host'] ) ? $uri_args['host'] : null;
				$api           = ! $port ? $api : "{$api}:{$port}";
				$scheme        = isset( $uri_args['scheme'] ) ? $uri_args['scheme'] : null;
				$scheme        = null !== $scheme ? $scheme . '://' : 'https://';
				$path          = isset( $uri_args['path'] ) ? $uri_args['path'] : null;
				$owner_repo    = str_replace( '.git', '', trim( $path, '/' ) );

				switch ( $dependency['host'] ) {
					case 'github':
						$base          = null === $api || 'github.com' === $api ? 'api.github.com' : $api;
						$download_link = "{$scheme}{$base}/repos/{$owner_repo}/zipball/{$dependency['branch']}";
						break;
					case 'bitbucket':
						$base          = null === $api || 'bitbucket.org' === $api ? 'bitbucket.org' : $api;
						$download_link = "{$scheme}{$base}/{$owner_repo}/get/{$dependency['branch']}.zip";
						break;
					case 'gitlab':
						$base          = null === $api || 'gitlab.com' === $api ? 'gitlab.com' : $api;
						$project_id    = rawurlencode( $owner_repo );
						$download_link = "{$scheme}{$base}/api/v4/projects/{$project_id}/repository/archive.zip";
						$download_link = add_query_arg( 'sha', $dependency['branch'], $download_link );
						break;
					case 'gitea':
						$download_link = "{$scheme}{$api}/api/v1/repos/{$owner_repo}/archive/{$dependency['branch']}.zip";
						break;
					case 'wordpress':  // phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled
						$download_link = $this->get_dot_org_latest_download( basename( $owner_repo ) );
						break;
					case 'direct':
						$download_link = filter_var( $uri, FILTER_VALIDATE_URL );
						break;
				}

				/**
				 * Allow filtering of download link for dependency configuration.
				 *
				 * @since 1.4.11
				 *
				 * @param string $download_link Download link.
				 * @param array  $dependency    Dependency configuration.
				 */
				$dependency['download_link'] = apply_filters( 'wp_dependency_download_link', $download_link, $dependency );

				/**
				 * Allow filtering of individual dependency config.
				 *
				 * @since 3.0.0
				 *
				 * @param array  $dependency    Dependency configuration.
				 */
				$this->config[ $slug ] = apply_filters( 'wp_dependency_config', $dependency );
			}
		}

		/**
		 * Get lastest download link from WordPress API.
		 *
		 * @param  string $slug Plugin slug.
		 * @return string $download_link
		 */
		private function get_dot_org_latest_download( $slug ) {
			$download_link = get_site_transient( 'wpdi-' . md5( $slug ) );

			if ( ! $download_link ) {
				$url           = 'https://api.wordpress.org/plugins/info/1.1/';
				$url           = add_query_arg(
					[
						'action'                        => 'plugin_information',
						rawurlencode( 'request[slug]' ) => $slug,
					],
					$url
				);
				$response      = wp_remote_get( $url );
				$response      = json_decode( wp_remote_retrieve_body( $response ) );
				$download_link = empty( $response )
					? "https://downloads.wordpress.org/plugin/{$slug}.zip"
					: $response->download_link;

				set_site_transient( 'wpdi-' . md5( $slug ), $download_link, DAY_IN_SECONDS );
			}

			return $download_link;
		}

		/**
		 * Determine if dependency is active or installed.
		 */
		public function admin_init() {
			// Get the gears turning.
			$this->apply_config();

			// Generate admin notices.
			foreach ( $this->config as $slug => $dependency ) {
				$is_required = $this->is_required( $dependency );

				if ( $is_required ) {
					$this->modify_plugin_row( $slug );
				}

				// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
				if ( $this->is_active( $slug ) ) {
					// Do nothing.
				} elseif ( $this->is_installed( $slug ) ) {
					if ( $is_required ) {
						$this->notices[] = $this->activate( $slug );
					} else {
						$this->notices[] = $this->activate_notice( $slug );
					}
				} else {
					if ( $is_required ) {
						$this->notices[] = $this->install( $slug );
					} else {
						$this->notices[] = $this->install_notice( $slug );
					}
				}

				/**
				 * Allow filtering of admin notices.
				 *
				 * @since 3.0.0
				 *
				 * @param array  $notices admin notices.
				 * @param string $slug    plugin slug.
				 */
				$this->notices = apply_filters( 'wp_dependency_notices', $this->notices, $slug );
			}
		}

		/**
		 * Register jQuery AJAX.
		 */
		public function admin_footer() {
			?>
			<script>
				(function ($) {
					$(function () {
						$(document).on('click', '.wpdi-button', function () {
							var $this = $(this);
							var $parent = $(this).closest('p');
							$parent.html('Running...');
							$.post(ajaxurl, {
								action: 'dependency_installer',
								method: $this.attr('data-action'),
								slug  : $this.attr('data-slug')
							}, function (response) {
								$parent.html(response);
							});
						});
						$(document).on('click', '.dependency-installer .notice-dismiss', function () {
							var $this = $(this);
							$.post(ajaxurl, {
								action: 'dependency_installer',
								method: 'dismiss',
								slug  : $this.attr('data-slug')
							});
						});
					});
				})(jQuery);
			</script>
			<?php
		}

		/**
		 * AJAX router.
		 */
		public function ajax_router() {
			// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$method = isset( $_POST['method'] ) ? wp_unslash( $_POST['method'] ) : '';
			$slug   = isset( $_POST['slug'] ) ? wp_unslash( $_POST['slug'] ) : '';
			// phpcs:enable
			$whitelist = [ 'install', 'activate', 'dismiss' ];

			if ( in_array( $method, $whitelist, true ) ) {
				$response = $this->$method( $slug );
				esc_html_e( $response['message'] );
			}
			wp_die();
		}

		/**
		 * Check if a dependency is currently required.
		 *
		 * @param string|array $plugin Plugin dependency slug or config.
		 *
		 * @return boolean True if required. Default: False
		 */
		public function is_required( &$plugin ) {
			if ( empty( $this->config ) ) {
				return false;
			}
			if ( is_string( $plugin ) && isset( $this->config[ $plugin ] ) ) {
				$dependency = &$this->config[ $plugin ];
			} else {
				$dependency = &$plugin;
			}
			if ( isset( $dependency['required'] ) ) {
				return true === $dependency['required'] || 'true' === $dependency['required'];
			}
			if ( isset( $dependency['optional'] ) ) {
				return false === $dependency['optional'] || 'false' === $dependency['optional'];
			}

			return false;
		}

		/**
		 * Is dependency installed?
		 *
		 * @param string $slug Plugin slug.
		 *
		 * @return boolean
		 */
		public function is_installed( $slug ) {
			$plugins = get_plugins();

			return isset( $plugins[ $slug ] );
		}

		/**
		 * Is dependency active?
		 *
		 * @param string $slug Plugin slug.
		 *
		 * @return boolean
		 */
		public function is_active( $slug ) {
			return is_plugin_active( $slug );
		}

		/**
		 * Install and activate dependency.
		 *
		 * @param string $slug Plugin slug.
		 *
		 * @return bool|array false or Message.
		 */
		public function install( $slug ) {
			if ( $this->is_installed( $slug ) || ! current_user_can( 'update_plugins' ) ) {
				return false;
			}

			$this->current_slug = $slug;
			add_filter( 'upgrader_source_selection', [ $this, 'upgrader_source_selection' ], 10, 2 );

			$skin     = new WPDI_Plugin_Installer_Skin(
				[
					'type'  => 'plugin',
					'nonce' => wp_nonce_url( $this->config[ $slug ]['download_link'] ),
				]
			);
			$upgrader = new Plugin_Upgrader( $skin );
			$result   = $upgrader->install( $this->config[ $slug ]['download_link'] );

			if ( is_wp_error( $result ) ) {
				return [
					'status'  => 'error',
					'message' => $result->get_error_message(),
				];
			}

			if ( null === $result ) {
				return [
					'status'  => 'error',
					'message' => esc_html__( 'Plugin download failed' ),
				];
			}

			wp_cache_flush();
			if ( $this->is_required( $slug ) ) {
				$this->activate( $slug );

				return [
					'status'  => 'updated',
					'slug'    => $slug,
					/* translators: %s: Plugin name */
					'message' => sprintf( esc_html__( '%s has been installed and activated.' ), $this->config[ $slug ]['name'] ),
					'source'  => $this->config[ $slug ]['source'],
				];
			}

			if ( true !== $result && 'error' === $result['status'] ) {
				return $result;
			}

			return [
				'status'  => 'updated',
				/* translators: %s: Plugin name */
				'message' => sprintf( esc_html__( '%s has been installed.' ), $this->config[ $slug ]['name'] ),
				'source'  => $this->config[ $slug ]['source'],
			];
		}

		/**
		 * Get install plugin notice.
		 *
		 * @param string $slug Plugin slug.
		 *
		 * @return array Admin notice.
		 */
		public function install_notice( $slug ) {
			$dependency = $this->config[ $slug ];

			return [
				'action'  => 'install',
				'slug'    => $slug,
				/* translators: %s: Plugin name */
				'message' => sprintf( esc_html__( 'The %s plugin is recommended.' ), $dependency['name'] ),
				'source'  => $dependency['source'],
			];
		}

		/**
		 * Activate dependency.
		 *
		 * @param string $slug Plugin slug.
		 *
		 * @return array Message.
		 */
		public function activate( $slug ) {
			// network activate only if on network admin pages.
			$result = is_network_admin() ? activate_plugin( $slug, null, true ) : activate_plugin( $slug );

			if ( is_wp_error( $result ) ) {
				return [
					'status'  => 'error',
					'message' => $result->get_error_message(),
				];
			}

			return [
				'status'  => 'updated',
				/* translators: %s: Plugin name */
				'message' => sprintf( esc_html__( '%s has been activated.' ), $this->config[ $slug ]['name'] ),
				'source'  => $this->config[ $slug ]['source'],
			];
		}

		/**
		 * Get activate plugin notice.
		 *
		 * @param string $slug Plugin slug.
		 *
		 * @return array Admin notice.
		 */
		public function activate_notice( $slug ) {
			$dependency = $this->config[ $slug ];

			return [
				'action'  => 'activate',
				'slug'    => $slug,
				/* translators: %s: Plugin name */
				'message' => sprintf( esc_html__( 'Please activate the %s plugin.' ), $dependency['name'] ),
				'source'  => $dependency['source'],
			];
		}

		/**
		 * Dismiss admin notice for a week.
		 *
		 * @return array Empty Message.
		 */
		public function dismiss() {
			return [
				'status'  => 'updated',
				'message' => '',
			];
		}

		/**
		 * Correctly rename dependency for activation.
		 *
		 * @param string $source        Path fo $source.
		 * @param string $remote_source Path of $remote_source.
		 *
		 * @return string $new_source
		 */
		public function upgrader_source_selection( $source, $remote_source ) {
			$new_source = trailingslashit( $remote_source ) . dirname( $this->current_slug );
			$this->move( $source, $new_source );

			return trailingslashit( $new_source );
		}

		/**
		 * Rename or recursive file copy and delete.
		 *
		 * This is more versatile than `$wp_filesystem->move()`.
		 * It moves/renames directories as well as files.
		 * Fix for https://github.com/afragen/github-updater/issues/826,
		 * strange failure of `rename()`.
		 *
		 * @param string $source      File path of source.
		 * @param string $destination File path of destination.
		 *
		 * @return bool|void
		 */
		private function move( $source, $destination ) {
			if ( $this->filesystem_move( $source, $destination ) ) {
				return true;
			}
			if ( is_dir( $destination ) && rename( $source, $destination ) ) {
				return true;
			}
			// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
			if ( $dir = opendir( $source ) ) {
				if ( ! file_exists( $destination ) ) {
					mkdir( $destination );
				}
				$source = untrailingslashit( $source );
				// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
				while ( false !== ( $file = readdir( $dir ) ) ) {
					if ( ( '.' !== $file ) && ( '..' !== $file ) && "{$source}/{$file}" !== $destination ) {
						if ( is_dir( "{$source}/{$file}" ) ) {
							$this->move( "{$source}/{$file}", "{$destination}/{$file}" );
						} else {
							copy( "{$source}/{$file}", "{$destination}/{$file}" );
							unlink( "{$source}/{$file}" );
						}
					}
				}
				$iterator = new \FilesystemIterator( $source );
				if ( ! $iterator->valid() ) { // True if directory is empty.
					rmdir( $source );
				}
				closedir( $dir );

				return true;
			}

			return false;
		}

		/**
		 * Non-direct filesystem move.
		 *
		 * @uses $wp_filesystem->move() when FS_METHOD is not 'direct'
		 *
		 * @param string $source      File path of source.
		 * @param string $destination File path of destination.
		 *
		 * @return bool|void True on success, false on failure.
		 */
		public function filesystem_move( $source, $destination ) {
			global $wp_filesystem;
			if ( 'direct' !== $wp_filesystem->method ) {
				return $wp_filesystem->move( $source, $destination );
			}

			return false;
		}

		/**
		 * Display admin notices / action links.
		 *
		 * @return bool/string false or Admin notice.
		 */
		public function admin_notices() {
			if ( ! current_user_can( 'update_plugins' ) ) {
				return false;
			}
			foreach ( $this->notices as $notice ) {
				$status      = isset( $notice['status'] ) ? $notice['status'] : 'updated';
				$source      = isset( $notice['source'] ) ? $notice['source'] : __( 'Dependency' );
				$class       = esc_attr( $status ) . ' notice is-dismissible dependency-installer';
				$label       = esc_html( $this->get_dismiss_label( $source ) );
				$message     = '';
				$action      = '';
				$dismissible = '';

				if ( isset( $notice['message'] ) ) {
					$message = esc_html( $notice['message'] );
				}

				if ( isset( $notice['action'] ) ) {
					$action = sprintf(
						' <a href="javascript:;" class="wpdi-button" data-action="%1$s" data-slug="%2$s">%3$s Now &raquo;</a> ',
						esc_attr( $notice['action'] ),
						esc_attr( $notice['slug'] ),
						esc_html( ucfirst( $notice['action'] ) )
					);
				}
				if ( isset( $notice['slug'] ) ) {
					/**
					 * Filters the dismissal timeout.
					 *
					 * @since 1.4.1
					 *
					 * @param string|int '7'           Default dismissal in days.
					 * @param  string     $notice['source'] Plugin slug of calling plugin.
					 * @return string|int Dismissal timeout in days.
					 */
					$timeout     = apply_filters( 'wp_dependency_timeout', '7', $source );
					$dependency  = dirname( $notice['slug'] );
					$dismissible = empty( $timeout ) ? '' : sprintf( 'dependency-installer-%1$s-%2$s', esc_attr( $dependency ), esc_attr( $timeout ) );
				}
				if ( class_exists( '\PAnD' ) && \PAnD::is_admin_notice_active( $dismissible ) ) {
					printf(
						'<div class="%1$s" data-dismissible="%2$s"><p><strong>[%3$s]</strong> %4$s%5$s</p></div>',
						esc_attr( $class ),
						esc_attr( $dismissible ),
						esc_html( $label ),
						esc_html( $message ),
						$action // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					);
				}
			}
		}

		/**
		 * Make modifications to plugin row.
		 *
		 * @param string $plugin_file Plugin file.
		 */
		private function modify_plugin_row( $plugin_file ) {
			add_filter( 'network_admin_plugin_action_links_' . $plugin_file, [ $this, 'unset_action_links' ], 10, 2 );
			add_filter( 'plugin_action_links_' . $plugin_file, [ $this, 'unset_action_links' ], 10, 2 );
			add_action( 'after_plugin_row_' . $plugin_file, [ $this, 'modify_plugin_row_elements' ] );
		}

		/**
		 * Unset plugin action links so required plugins can't be removed or deactivated.
		 *
		 * @param array  $actions     Action links.
		 * @param string $plugin_file Plugin file.
		 *
		 * @return mixed
		 */
		public function unset_action_links( $actions, $plugin_file ) {
			/**
			 * Allow to remove required plugin action links.
			 *
			 * @since 3.0.0
			 *
			 * @param bool $unset remove default action links.
			 */
			if ( apply_filters( 'wp_dependency_unset_action_links', true ) ) {
				if ( isset( $actions['delete'] ) ) {
					unset( $actions['delete'] );
				}

				if ( isset( $actions['deactivate'] ) ) {
					unset( $actions['deactivate'] );
				}
			}

			/**
			 * Allow to display of requied plugin label.
			 *
			 * @since 3.0.0
			 *
			 * @param bool $display show required plugin label.
			 */
			if ( apply_filters( 'wp_dependency_required_label', true ) ) {
				/* translators: %s: opening and closing span tags */
				$actions = array_merge( [ 'required-plugin' => sprintf( esc_html__( '%1$sRequired Plugin%2$s' ), '<span class="network_active" style="font-variant-caps: small-caps;">', '</span>' ) ], $actions );
			}

			return $actions;
		}

		/**
		 * Modify the plugin row elements.
		 *
		 * @param string $plugin_file Plugin file.
		 *
		 * @return void
		 */
		public function modify_plugin_row_elements( $plugin_file ) {
			print '<script>';
			/**
			 * Allow to display additional row meta info of required plugin.
			 *
			 * @since 3.0.0
			 *
			 * @param bool $display show plugin row meta.
			 */
			if ( apply_filters( 'wp_dependency_required_row_meta', true ) ) {
				print 'jQuery("tr[data-plugin=\'' . esc_attr( $plugin_file ) . '\'] .plugin-version-author-uri").append("<br><br><strong>' . esc_html__( 'Required by:' ) . '</strong> ' . esc_html( $this->get_dependency_sources( $plugin_file ) ) . '");';
			}
			print 'jQuery(".inactive[data-plugin=\'' . esc_attr( $plugin_file ) . '\']").attr("class", "active");';
			print 'jQuery(".active[data-plugin=\'' . esc_attr( $plugin_file ) . '\'] .check-column input").remove();';
			print '</script>';
		}

		/**
		 * Get formatted string of dependent plugins.
		 *
		 * @param string $plugin_file Plugin file.
		 *
		 * @return string $dependents
		 */
		private function get_dependency_sources( $plugin_file ) {
			// Remove empty values from $sources.
			$sources = array_filter( $this->config[ $plugin_file ]['sources'] );
			$sources = array_map( [ $this, 'get_dismiss_label' ], $sources );
			$sources = implode( ', ', $sources );

			return $sources;
		}

		/**
		 * Get formatted source string for text usage.
		 *
		 * @param string $source plugin source.
		 *
		 * @return string friendly plugin name.
		 */
		private function get_dismiss_label( $source ) {
			$label = str_replace( '-', ' ', $source );
			$label = ucwords( $label );
			$label = str_ireplace( 'wp ', 'WP ', $label );

			/**
			 * Filters the dismissal notice label
			 *
			 * @since 3.0.0
			 *
			 * @param  string $label  Default dismissal notice string.
			 * @param  string $source Plugin slug of calling plugin.
			 * @return string Dismissal notice string.
			 */
			return apply_filters( 'wp_dependency_dismiss_label', $label, $source );
		}

		/**
		 * Get the configuration.
		 *
		 * @since 1.4.11
		 *
		 * @param string $slug Plugin slug.
		 * @param string $key Dependency key.
		 *
		 * @return mixed|array The configuration.
		 */
		public function get_config( $slug = '', $key = '' ) {
			if ( empty( $slug ) && empty( $key ) ) {
				return $this->config;
			} elseif ( empty( $key ) ) {
				return isset( $this->config[ $slug ] ) ? $this->config[ $slug ] : null;
			} else {
				return isset( $this->config[ $slug ][ $key ] ) ? $this->config[ $slug ][ $key ] : null;
			}
		}

		/**
		 * Add Basic Auth headers for authentication.
		 *
		 * @param array  $args HTTP header args.
		 * @param string $url  URL.
		 *
		 * @return array $args
		 */
		public function add_basic_auth_headers( $args, $url ) {
			if ( null === $this->current_slug ) {
				return $args;
			}
			$package = $this->config[ $this->current_slug ];
			$host    = $package['host'];
			$token   = empty( $package['token'] ) ? false : $package['token'];

			if ( $token && $url === $package['download_link'] ) {
				if ( 'bitbucket' === $host ) {
					// Bitbucket token must be in the form of 'username:password'.
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					$args['headers']['Authorization'] = 'Basic ' . base64_encode( $token );
				}
				if ( 'github' === $host || 'gitea' === $host ) {
					$args['headers']['Authorization'] = 'token ' . $token;
				}
				if ( 'gitlab' === $host ) {
					$args['headers']['Authorization'] = 'Bearer ' . $token;
				}
			}

			// dot org should not have auth header.
			// phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled
			if ( 'wordpress' === $host ) {
				unset( $args['headers']['Authorization'] );
			}
			remove_filter( 'http_request_args', [ $this, 'add_basic_auth_headers' ] );

			return $args;
		}
	}

	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	/**
	 * Class WPDI_Plugin_Installer_Skin
	 */
	class WPDI_Plugin_Installer_Skin extends Plugin_Installer_Skin {
		// phpcs:disable Squiz.Commenting.FunctionComment.Missing
		public function header() {
		}

		public function footer() {
		}

		public function error( $errors ) {
		}

		public function feedback( $string, ...$args ) {
		}
		// phpcs:enable
	}
}
