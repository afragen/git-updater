<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  GPL-3.0-or-later
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 *
 * @phpcs:disable WordPress.Security.NonceVerification.Missing
 * @phpcs:disable Squiz.PHP.DisallowMultipleAssignments.Found
 */

namespace Fragen\Git_Updater;

use Fragen\Singleton;
use Fragen\Git_Updater\OAuth\OAuth_Connect;
use Fragen\Git_Updater\Traits\GU_Trait;
use Fragen\Git_Updater\Traits\Basic_Auth_Loader;
use Fragen\Git_Updater\WP_CLI\CLI_Plugin_Installer_Skin;
use Fragen\Git_Updater\WP_CLI\CLI_Theme_Installer_Skin;
use Plugin_Installer_Skin;
use Plugin_Upgrader;
use Theme_Installer_Skin;
use Theme_Upgrader;
use WP_Upgrader_Skin;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die; // @codeCoverageIgnore
}

/**
 * Class Install
 *
 * Install <author>/<repo> directly from Git Updater.
 */
class Install {
	use GU_Trait;
	use Basic_Auth_Loader;

	/**
	 * Class options.
	 *
	 * @var array<string, mixed>
	 */
	protected static $install = [];

	/**
	 * Hold local copy of Git Updater options.
	 *
	 * @var mixed
	 */
	private static $options;

	/**
	 * Hold local copy of installed APIs.
	 *
	 * @var mixed
	 */
	private static $installed_apis;

	/**
	 * Hold local copy of git servers.
	 *
	 * @var mixed
	 */
	private static $git_servers;

	/**
	 * Constructor.
	 */
	public function __construct() {
		self::$options        = $this->get_class_vars( 'Fragen\Git_Updater\Base', 'options' );
		self::$installed_apis = $this->get_class_vars( 'Fragen\Git_Updater\Base', 'installed_apis' );
		self::$git_servers    = $this->get_class_vars( 'Fragen\Git_Updater\Base', 'git_servers' );
	}

	/**
	 * Let's set up the Install tabs.
	 * Need class-wp-upgrader.php for upgrade classes.
	 *
	 * @return void
	 */
	public function run() {
		$this->load_js();
		$this->add_settings_tabs();
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	}

	/**
	 * Load javascript for Install.
	 *
	 * @return void
	 */
	public function load_js() {
		add_action(
			'admin_enqueue_scripts',
			function () {
				wp_register_script( 'gu-install', plugins_url( basename( dirname( __DIR__, 2 ) ) . '/js/gu-install-vanilla.js' ), [], $this->get_plugin_version(), true );
				wp_enqueue_script( 'gu-install' );

				$oauth           = Singleton::get_instance( 'Fragen\Git_Updater\OAuth\OAuth_Connect', $this );
				$github_oauth    = $oauth->is_oauth_token( 'github' );
				$github_username = $github_oauth ? $this->get_github_username() : '';
				$github_orgs     = $github_oauth ? $this->get_github_org_logins() : [];

				wp_localize_script(
					'gu-install',
					'guInstallData',
					[
						'ajaxurl'         => admin_url( 'admin-ajax.php' ),
						'nonce'           => wp_create_nonce( 'gu_github_install_autocomplete' ),
						'github_oauth'    => $github_oauth ? '1' : '0',
						'github_username' => $github_username,
						'github_orgs'     => $github_orgs,
					]
				);
			}
		);
	}

	/**
	 * Adds Install tabs to Settings page.
	 *
	 * @return void
	 */
	public function add_settings_tabs() {
		$install_tabs = [];
		if ( current_user_can( 'install_plugins' ) ) {
			$install_tabs['git_updater_install_plugin'] = esc_html__( 'Install Plugin', 'git-updater' );
		}
		if ( current_user_can( 'install_themes' ) ) {
			$install_tabs['git_updater_install_theme'] = esc_html__( 'Install Theme', 'git-updater' );
		}
		add_filter(
			'gu_add_settings_tabs',
			function ( $tabs ) use ( $install_tabs ) {
				return array_merge( $tabs, $install_tabs );
			}
		);
		add_action(
			'gu_add_admin_page',
			function ( $tab ) {
				$this->add_admin_page( $tab );
			}
		);
	}

	/**
	 * Add Settings page data via action hook.
	 *
	 * @uses 'gu_add_admin_page' action hook
	 *
	 * @param string $tab Name of tab.
	 * @return void
	 */
	public function add_admin_page( $tab ) {
		if ( 'git_updater_install_plugin' === $tab ) {
			$this->install( 'plugin' );
			$this->create_form( 'plugin' );
		}
		if ( 'git_updater_install_theme' === $tab ) {
			$this->install( 'theme' );
			$this->create_form( 'theme' );
		}
	}

	/**
	 * Install remote plugin or theme.
	 *
	 * @param string                    $type   plugin|theme.
	 * @param array<string, mixed>|null $config Array of data.
	 *
	 * @return bool
	 */
	public function install( $type, $config = null ) {
		if ( self::is_wp_cli() ) {
			$this->set_install_post_data( $config ); // @codeCoverageIgnore
		}

		if ( isset( $_POST['option_page'] ) && 'git_updater_install' === $_POST['option_page'] ) {
			if ( empty( $_POST['git_updater_branch'] ) ) {
				$_POST['git_updater_branch'] = 'master';
			}

			// Exit early if no repo entered.
			if ( empty( $_POST['git_updater_repo'] ) ) {
				echo '<h3>';
				esc_html_e( 'A repository URI is required.', 'git-updater' );
				echo '</h3>';

				return false;
			}

			// Transform URI to owner/repo.
			$headers                   = $this->parse_header_uri( sanitize_text_field( wp_unslash( $_POST['git_updater_repo'] ) ) );
			$_POST['git_updater_repo'] = $headers['owner_repo'];

			self::$install         = $this->sanitize( $_POST );
			self::$install['repo'] = self::$install['git_updater_install_repo'] = $headers['repo'];

			/*
			 * Create GitHub endpoint.
			 * Save Access Token if present.
			 * Check for GitHub Self-Hosted.
			 */
			if ( 'github' === self::$install['git_updater_api'] ) {
				self::$install = Singleton::get_instance( 'Fragen\Git_Updater\API\GitHub_API', $this )->remote_install( $headers, self::$install );
			}

			/**
			 * Filter to create git host specific endpoint.
			 *
			 * @since 10.0.0
			 * @param array<string, mixed> $install Array of installation data.
			 * @param array $headers Array of repo header data.
			 */
			self::$install = apply_filters( 'gu_install_remote_install', self::$install, $headers );

			if ( isset( self::$install['options'] ) ) {
				$this->save_options_on_install( self::$install['options'] );
			}

			$url      = self::$install['download_link'];
			$upgrader = $this->get_upgrader( $type, $url );

			// Load hook for adding authentication headers for download packages.
			add_filter(
				'upgrader_pre_download',
				function () {
					add_filter( 'http_request_args', [ $this, 'download_package' ], 15, 2 );
					return false; // upgrader_pre_download filter default return value.
				}
			);

			// Install the repo from the $source urldecode() and save branch setting.
			if ( $upgrader && $upgrader->install( $url ) ) {
				( new Branch() )->set_branch_on_install( self::$install );
			} else {
				return false;
			}
		}

		return true;
	}

	/**
	 * Save options set during installation.
	 *
	 * @param  array<string, mixed> $install_options Array of options from remote install process.
	 * @return void
	 */
	private function save_options_on_install( $install_options ) {
		self::$options = array_merge( self::$options, $install_options );
		update_site_option( 'git_updater', self::$options );
	}

	/**
	 * Set remote install data into $_POST.
	 *
	 * @param array<string, mixed>|null $config Data for a remote install.
	 * @return void
	 */
	private function set_install_post_data( $config ) {
		// @codeCoverageIgnoreStart
		if ( ! isset( $config['uri'] ) ) {
			return;
		}

		$headers = $this->parse_header_uri( $config['uri'] );
		$api     = str_contains( $headers['host'], '.com' )
			? rtrim( $headers['host'], '.com' )
			: rtrim( $headers['host'], '.org' );

		$api = $config['git'] ?? $api;

		$_POST['git_updater_repo']      = $config['uri'];
		$_POST['git_updater_branch']    = $config['branch'];
		$_POST['git_updater_api']       = $api;
		$_POST['option_page']           = 'git_updater_install';
		$_POST[ "{$api}_access_token" ] = $config['private'] ?: null;

		if ( 'zipfile' === $config['git'] ) {
			$_POST['zipfile_slug'] = $config['slug'];
		}
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Get the appropriate upgrader for remote installation.
	 *
	 * @param string $type 'plugin' | 'theme'.
	 * @param string $url  URL of the repository to be installed.
	 *
	 * @return bool|Plugin_Upgrader|Theme_Upgrader
	 */
	private function get_upgrader( $type, $url ) {
		$nonce    = wp_nonce_url( $url );
		$upgrader = false;

		if ( 'plugin' === $type ) {
			$plugin = self::$install['repo'];

			// Create a new instance of Plugin_Upgrader.
			$skin = static::is_wp_cli()
				? new CLI_Plugin_Installer_Skin() // @codeCoverageIgnore
				: new Plugin_Installer_Skin( compact( 'type', 'url', 'nonce', 'plugin' ) );

			/**
			 * Filters the upgrader skin used during a Git Updater install.
			 *
			 * Allows replacing the default skin with a custom implementation.
			 * Primarily useful in test environments to suppress HTML output.
			 *
			 * @since 12.24.2
			 *
			 * @param WP_Upgrader_Skin $skin The skin instance.
			 * @param string           $type Installer type: 'plugin' or 'theme'.
			 */
			$skin     = apply_filters( 'gu_get_upgrader_skin', $skin, $type );
			$upgrader = new Plugin_Upgrader( $skin );
		}

		if ( 'theme' === $type ) {
			$theme = self::$install['repo'];

			// Create a new instance of Theme_Upgrader.
			$skin = static::is_wp_cli()
				? new CLI_Theme_Installer_Skin() // @codeCoverageIgnore
				: new Theme_Installer_Skin( compact( 'type', 'url', 'nonce', 'theme' ) );

			/** This filter is documented in src/Git_Updater/Install.php */
			$skin     = apply_filters( 'gu_get_upgrader_skin', $skin, $type );
			$upgrader = new Theme_Upgrader( $skin );
			add_filter(
				'install_theme_complete_actions',
				[
					$this,
					'install_theme_complete_actions',
				],
				10,
				1
			);
		}

		return $upgrader;
	}

	/**
	 * Create Install Plugin or Install Theme page.
	 *
	 * @param string $type (plugin|theme).
	 * @return void
	 */
	public function create_form( $type ) {
		// Bail if installing.
		if ( isset( $_POST['option_page'] ) && 'git_updater_install' === $_POST['option_page'] ) {
			return;
		}

		$this->register_settings( $type ); ?>
		<form method="post">
			<?php
			settings_fields( 'git_updater_install' );
			do_settings_sections( 'git_updater_install_' . $type );
			if ( 'plugin' === $type ) {
				submit_button( esc_html__( 'Install Plugin', 'git-updater' ) );
			}
			if ( 'theme' === $type ) {
				submit_button( esc_html__( 'Install Theme', 'git-updater' ) );
			}
			?>
		</form>
		<?php
	}

	/**
	 * Add settings sections.
	 *
	 * @param string $type plugin|theme.
	 * @return void
	 */
	public function register_settings( $type ) {
		$repo_type = null;

		// Place translatable strings into variables.
		if ( 'plugin' === $type ) {
			$repo_type = esc_html__( 'Plugin', 'git-updater' );
		}
		if ( 'theme' === $type ) {
			$repo_type = esc_html__( 'Theme', 'git-updater' );
		}

		register_setting(
			'git_updater_install',
			'git_updater_install_' . $type,
			[ $this, 'sanitize' ]
		);

		add_settings_section(
			$type,
			/* translators: variable is 'Plugin' or 'Theme' */
			sprintf( esc_html__( 'Git Updater Install %s', 'git-updater' ), $repo_type ),
			'__return_false',
			'git_updater_install_' . $type
		);

		add_settings_field(
			$type . '_repo',
			/* translators: variable is 'Plugin' or 'Theme' */
			sprintf( esc_html__( '%s URI', 'git-updater' ), $repo_type ),
			[ $this, 'get_repo' ],
			'git_updater_install_' . $type,
			$type
		);

		add_settings_field(
			$type . '_branch',
			esc_html__( 'Repository Branch', 'git-updater' ),
			[ $this, 'branch' ],
			'git_updater_install_' . $type,
			$type
		);

		add_settings_field(
			$type . '_api',
			esc_html__( 'Remote Repository Host', 'git-updater' ),
			[ $this, 'install_api' ],
			'git_updater_install_' . $type,
			$type
		);

		/**
		 * Action hook to add git API install settings fields.
		 *
		 * @since 8.0.0
		 *
		 * @param string $type 'plugin'|'theme'.
		 */
		do_action( 'gu_add_install_settings_fields', $type );

		// Load install settings fields for existing APIs that are not loaded.
		$running_servers     = $this->get_running_git_servers();
		$servers_not_running = array_diff( array_flip( self::$git_servers ), $running_servers );
		if ( ! empty( $servers_not_running ) ) {
			foreach ( array_keys( $servers_not_running ) as $server ) {
				$class = 'Fragen\\Git_Updater\\API\\' . $server . '_API';
				Singleton::get_instance( $class, $this )->add_install_settings_fields( $type );
			}
		}
	}

	/**
	 * Repo setting.
	 *
	 * @return void
	 */
	public function get_repo() {
		?>
		<label for="git_updater_repo">
			<input type="text" style="width:50%;" id="git_updater_repo" name="git_updater_repo" value="" autofocus>
			<br>
			<span class="description">
				<?php esc_html_e( 'URI is case sensitive.', 'git-updater' ); ?>
			</span>
		</label>
		<?php
	}

	/**
	 * Branch setting.
	 *
	 * @return void
	 */
	public function branch() {
		?>
		<label for="git_updater_branch">
			<input type="text" style="width:50%;" id="git_updater_branch" name="git_updater_branch" value="" placeholder="master">
			<br>
			<span class="description">
				<?php esc_html_e( 'Enter branch name or leave empty for `master`', 'git-updater' ); ?>
			</span>
		</label>
		<?php
	}

	/**
	 * API setting.
	 *
	 * @return void
	 */
	public function install_api() {
		?>
		<label for="git_updater_api">
			<select id="git_updater_api" name="git_updater_api">
				<?php foreach ( self::$git_servers as $key => $value ) : ?>
					<?php if ( self::$installed_apis[ $key . '_api' ] ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key ); ?> >
							<?php echo esc_html( $value ); ?>
						</option>
					<?php endif ?>
				<?php endforeach ?>
			</select>
		</label>
		<?php
	}

	/**
	 * Fix activation links after theme installation, no method to get proper theme name.
	 *
	 * @param array<string, mixed> $install_actions Array of theme actions.
	 *
	 * @return array<string, mixed>
	 */
	public function install_theme_complete_actions( $install_actions ) {
		if ( isset( $install_actions['preview'] ) ) {
			unset( $install_actions['preview'] );
		}

		$stylesheet    = self::$install['repo'];
		$activate_link = add_query_arg(
			[
				'action'     => 'activate',
				// 'template'   => rawurlencode( $template ),
				'stylesheet' => rawurlencode( $stylesheet ),
			],
			admin_url( 'themes.php' )
		);
		$activate_link = esc_url( wp_nonce_url( $activate_link, 'switch-theme_' . $stylesheet ) );

		$install_actions['activate'] = '<a href="' . $activate_link . '" class="activatelink"><span aria-hidden="true">' . esc_attr__( 'Activate', 'git-updater' ) . '</span><span class="screen-reader-text">' . esc_attr__( 'Activate', 'git-updater' ) . ' &#8220;' . $stylesheet . '&#8221;</span></a>';

		if ( is_network_admin() && current_user_can( 'manage_network_themes' ) ) {
			$network_activate_link = add_query_arg(
				[
					'action' => 'enable',
					'theme'  => rawurlencode( $stylesheet ),
				],
				network_admin_url( 'themes.php' )
			);
			$network_activate_link = esc_url( wp_nonce_url( $network_activate_link, 'enable-theme_' . $stylesheet ) );

			$install_actions['network_enable'] = '<a href="' . $network_activate_link . '" target="_parent">' . esc_attr_x( 'Network Enable', 'This refers to a network activation in a multisite installation', 'git-updater' ) . '</a>';
			unset( $install_actions['activate'] );
		}
		ksort( $install_actions );

		return $install_actions;
	}

	/**
	 * Register AJAX handlers for GitHub install autocomplete.
	 *
	 * @return void
	 */
	public function register_ajax_handlers(): void {
		add_action( 'wp_ajax_gu_github_repos', [ $this, 'ajax_github_repos' ] );
		add_action( 'wp_ajax_gu_github_branches', [ $this, 'ajax_github_branches' ] );
		add_action( 'wp_ajax_gu_github_repo_info', [ $this, 'ajax_github_repo_info' ] );
	}

	/**
	 * AJAX handler: return GitHub repos matching a search query.
	 *
	 * Returns repos from the connected GitHub account whose full_name contains
	 * the query string. Results are cached in a site transient for 5 minutes.
	 *
	 * @return void
	 */
	public function ajax_github_repos(): void {
		check_ajax_referer( 'gu_github_install_autocomplete', 'nonce' );

		if ( ! current_user_can( 'install_plugins' ) && ! current_user_can( 'install_themes' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$options = get_site_option( 'git_updater', [] );
		$token   = $options['github_access_token'] ?? '';

		if ( ! $token ) {
			wp_send_json_success( [] );
		}

		$search = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );

		// Attempt to serve from transient cache.
		$cache_key  = 'gu_github_repos_' . md5( $token );
		$all_repos  = get_site_transient( $cache_key );

		if ( false === $all_repos ) {
			$all_repos = $this->fetch_all_github_repos( $token );
			set_site_transient( $cache_key, $all_repos, 5 * MINUTE_IN_SECONDS );
		}

		if ( ! is_array( $all_repos ) ) {
			wp_send_json_success( [] );
		}

		$results = [];
		foreach ( $all_repos as $repo ) {
			if ( empty( $search ) || stripos( $repo['full_name'], $search ) !== false || stripos( $repo['name'], $search ) !== false ) {
				$results[] = [
					'full_name'      => $repo['full_name'],
					'html_url'       => $repo['html_url'],
					'default_branch' => $repo['default_branch'],
					'private'        => $repo['private'],
				];
				if ( count( $results ) >= 20 ) {
					break;
				}
			}
		}

		wp_send_json_success( $results );
	}

	/**
	 * AJAX handler: return branch list for a given owner/repo.
	 *
	 * @return void
	 */
	public function ajax_github_branches(): void {
		check_ajax_referer( 'gu_github_install_autocomplete', 'nonce' );

		if ( ! current_user_can( 'install_plugins' ) && ! current_user_can( 'install_themes' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$options    = get_site_option( 'git_updater', [] );
		$token      = $options['github_access_token'] ?? '';
		$owner_repo = sanitize_text_field( wp_unslash( $_GET['repo'] ?? '' ) );

		if ( ! $token || ! $owner_repo || ! preg_match( '/^[^\/]+\/[^\/]+$/', $owner_repo ) ) {
			wp_send_json_success( [] );
		}

		$cache_key = 'gu_github_branches_' . md5( $token . $owner_repo );
		$branches  = get_site_transient( $cache_key );

		if ( false === $branches ) {
			$url      = 'https://api.github.com/repos/' . $owner_repo . '/branches';
			$url      = add_query_arg( 'per_page', '100', $url );
			$response = wp_remote_get(
				$url,
				[
					'headers' => [
						'Authorization' => 'Bearer ' . $token,
						'Accept'        => 'application/vnd.github.v3+json',
					],
					'timeout' => 10,
				]
			);

			$branches = [];
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( is_array( $body ) ) {
					foreach ( $body as $branch ) {
						$branches[] = $branch['name'] ?? '';
					}
					$branches = array_filter( $branches );
				}
			}
			set_site_transient( $cache_key, $branches, 5 * MINUTE_IN_SECONDS );
		}

		wp_send_json_success( array_values( $branches ) );
	}

	/**
	 * AJAX handler: return default branch for a given owner/repo.
	 *
	 * Used to auto-populate the branch field when a connected-account repo
	 * is entered in the URI field.
	 *
	 * @return void
	 */
	public function ajax_github_repo_info(): void {
		check_ajax_referer( 'gu_github_install_autocomplete', 'nonce' );

		if ( ! current_user_can( 'install_plugins' ) && ! current_user_can( 'install_themes' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$options    = get_site_option( 'git_updater', [] );
		$token      = $options['github_access_token'] ?? '';
		$owner_repo = sanitize_text_field( wp_unslash( $_GET['repo'] ?? '' ) );

		if ( ! $token || ! $owner_repo || ! preg_match( '/^[^\/]+\/[^\/]+$/', $owner_repo ) ) {
			wp_send_json_error( 'Invalid input' );
		}

		$cache_key = 'gu_github_repo_info_' . md5( $token . $owner_repo );
		$info      = get_site_transient( $cache_key );

		if ( false === $info ) {
			$url      = 'https://api.github.com/repos/' . $owner_repo;
			$response = wp_remote_get(
				$url,
				[
					'headers' => [
						'Authorization' => 'Bearer ' . $token,
						'Accept'        => 'application/vnd.github.v3+json',
					],
					'timeout' => 10,
				]
			);

			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				wp_send_json_error( 'API error' );
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $body ) ) {
				wp_send_json_error( 'Invalid response' );
			}

			$info = [
				'default_branch' => $body['default_branch'] ?? 'master',
				'private'        => $body['private'] ?? false,
				'owner'          => $body['owner']['login'] ?? '',
			];
			set_site_transient( $cache_key, $info, 5 * MINUTE_IN_SECONDS );
		}

		wp_send_json_success( $info );
	}

	/**
	 * Fetch all repos for the authenticated GitHub user, including organization repos (paginated).
	 *
	 * Queries /user/repos for personal and directly-accessible repos, then /user/orgs
	 * to enumerate all organizations and fetches each org's repos separately.
	 * Results are deduplicated by full_name.
	 *
	 * @param string $token GitHub access token.
	 * @return array<int, array<string, mixed>>
	 */
	private function fetch_all_github_repos( string $token ): array {
		$headers = [
			'Authorization' => 'Bearer ' . $token,
			'Accept'        => 'application/vnd.github.v3+json',
		];

		// Fetch user repos (personal + repos the user has direct access to).
		$all_repos = $this->github_paginate( 'https://api.github.com/user/repos', [ 'type' => 'all', 'sort' => 'pushed' ], $headers );

		// Fetch all orgs the user belongs to.
		$orgs = $this->github_paginate( 'https://api.github.com/user/orgs', [], $headers );

		// For each org, fetch its repos and merge.
		foreach ( $orgs as $org ) {
			$org_login  = $org['login'] ?? '';
			if ( ! $org_login ) {
				continue;
			}
			$org_repos = $this->github_paginate(
				'https://api.github.com/orgs/' . rawurlencode( $org_login ) . '/repos',
				[ 'type' => 'all', 'sort' => 'pushed' ],
				$headers
			);
			$all_repos = array_merge( $all_repos, $org_repos );
		}

		// Deduplicate by full_name.
		$seen      = [];
		$unique    = [];
		foreach ( $all_repos as $repo ) {
			$key = $repo['full_name'] ?? '';
			if ( $key && ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = true;
				$unique[]     = $repo;
			}
		}

		return $unique;
	}

	/**
	 * Paginate a GitHub API endpoint and return all results (capped at 1000 items).
	 *
	 * @param string                         $base_url Base API URL without query args.
	 * @param array<string, mixed>           $params   Extra query parameters.
	 * @param array<string, string>          $headers  HTTP headers (including Authorization).
	 * @return array<int, array<string, mixed>>
	 */
	private function github_paginate( string $base_url, array $params, array $headers ): array {
		$results = [];
		$page    = 1;

		do {
			$url      = add_query_arg( array_merge( $params, [ 'per_page' => 100, 'page' => $page ] ), $base_url );
			$response = wp_remote_get( $url, [ 'headers' => $headers, 'timeout' => 10 ] );

			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				break;
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $body ) || empty( $body ) ) {
				break;
			}

			$results = array_merge( $results, $body );
			$page++;
		} while ( count( $body ) === 100 && count( $results ) < 1000 );

		return $results;
	}

	/**
	 * Get the GitHub username for the connected OAuth account.
	 *
	 * Result is cached in a site transient for 1 hour.
	 *
	 * @return string Username or empty string on failure.
	 */
	private function get_github_username(): string {
		$options = get_site_option( 'git_updater', [] );
		$token   = $options['github_access_token'] ?? '';

		if ( ! $token ) {
			return '';
		}

		$cache_key = 'gu_github_username_' . md5( $token );
		$username  = get_site_transient( $cache_key );

		if ( false !== $username ) {
			return (string) $username;
		}

		$response = wp_remote_get(
			'https://api.github.com/user',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/vnd.github.v3+json',
				],
				'timeout' => 5,
			]
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$body     = json_decode( wp_remote_retrieve_body( $response ), true );
		$username = is_array( $body ) ? ( $body['login'] ?? '' ) : '';

		set_site_transient( $cache_key, $username, HOUR_IN_SECONDS );

		return (string) $username;
	}

	/**
	 * Get the login slugs of all GitHub organizations the authenticated user belongs to.
	 *
	 * Result is cached in a site transient for 1 hour.
	 *
	 * @return list<string>
	 */
	private function get_github_org_logins(): array {
		$options = get_site_option( 'git_updater', [] );
		$token   = $options['github_access_token'] ?? '';

		if ( ! $token ) {
			return [];
		}

		$cache_key = 'gu_github_orgs_' . md5( $token );
		$orgs      = get_site_transient( $cache_key );

		if ( false !== $orgs ) {
			return is_array( $orgs ) ? $orgs : [];
		}

		$headers = [
			'Authorization' => 'Bearer ' . $token,
			'Accept'        => 'application/vnd.github.v3+json',
		];

		$raw_orgs = $this->github_paginate( 'https://api.github.com/user/orgs', [], $headers );

		$logins = [];
		foreach ( $raw_orgs as $org ) {
			if ( ! empty( $org['login'] ) ) {
				$logins[] = strtolower( $org['login'] );
			}
		}

		set_site_transient( $cache_key, $logins, HOUR_IN_SECONDS );

		return $logins;
	}
}
