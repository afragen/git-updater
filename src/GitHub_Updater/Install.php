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
use Fragen\GitHub_Updater\Traits\Basic_Auth_Loader;
use Fragen\GitHub_Updater\WP_CLI\CLI_Plugin_Installer_Skin;
use Fragen\GitHub_Updater\WP_CLI\CLI_Theme_Installer_Skin;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Install
 *
 * Install <author>/<repo> directly from GitHub Updater.
 */
class Install {
	use GHU_Trait, Basic_Auth_Loader;

	/**
	 * Class options.
	 *
	 * @var array
	 */
	protected static $install = [];

	/**
	 * Hold local copy of GitHub Updater options.
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
		self::$options        = $this->get_class_vars( 'Base', 'options' );
		self::$installed_apis = $this->get_class_vars( 'Base', 'installed_apis' );
		self::$git_servers    = $this->get_class_vars( 'Base', 'git_servers' );
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
				wp_register_script( 'ghu-install', plugins_url( basename( GITHUB_UPDATER_DIR ) . '/js/ghu-install-vanilla.js' ), [], $this->get_plugin_version(), true );
				wp_enqueue_script( 'ghu-install' );
			}
		);
	}

	/**
	 * Adds Install tabs to Settings page.
	 */
	public function add_settings_tabs() {
		$install_tabs = [];
		if ( current_user_can( 'install_plugins' ) ) {
			$install_tabs['github_updater_install_plugin'] = esc_html__( 'Install Plugin', 'github-updater' );
		}
		if ( current_user_can( 'install_themes' ) ) {
			$install_tabs['github_updater_install_theme'] = esc_html__( 'Install Theme', 'github-updater' );
		}
		add_filter(
			'github_updater_add_settings_tabs',
			function ( $tabs ) use ( $install_tabs ) {
				return array_merge( $tabs, $install_tabs );
			}
		);
		add_action(
			'github_updater_add_admin_page',
			function ( $tab ) {
				$this->add_admin_page( $tab );
			}
		);
	}

	/**
	 * Add Settings page data via action hook.
	 *
	 * @uses 'github_updater_add_admin_page' action hook
	 *
	 * @param string $tab Name of tab.
	 */
	public function add_admin_page( $tab ) {
		if ( 'github_updater_install_plugin' === $tab ) {
			$this->install( 'plugin' );
			$this->create_form( 'plugin' );
		}
		if ( 'github_updater_install_theme' === $tab ) {
			$this->install( 'theme' );
			$this->create_form( 'theme' );
		}
	}

	/**
	 * Install remote plugin or theme.
	 *
	 * @param string $type   plugin|theme.
	 * @param array  $config Array of data.
	 *
	 * @return bool
	 */
	public function install( $type, $config = null ) {
		$this->set_install_post_data( $config );

		if ( isset( $_POST['option_page'] ) && 'github_updater_install' === $_POST['option_page'] ) {
			if ( empty( $_POST['github_updater_branch'] ) ) {
				$_POST['github_updater_branch'] = 'master';
			}

			// Exit early if no repo entered.
			if ( empty( $_POST['github_updater_repo'] ) ) {
				echo '<h3>';
				esc_html_e( 'A repository URI is required.', 'github-updater' );
				echo '</h3>';

				return false;
			}

			// Transform URI to owner/repo.
			$headers                      = $this->parse_header_uri( $_POST['github_updater_repo'] );
			$_POST['github_updater_repo'] = $headers['owner_repo'];

			self::$install         = $this->sanitize( $_POST );
			self::$install['repo'] = self::$install['github_updater_install_repo'] = $headers['repo'];

			/*
			 * Create GitHub endpoint.
			 * Save Access Token if present.
			 * Check for GitHub Self-Hosted.
			 */
			if ( 'github' === self::$install['github_updater_api'] ) {
				self::$install = Singleton::get_instance( 'API\GitHub_API', $this, new \stdClass() )->remote_install( $headers, self::$install );
			}

			/*
			 * Create Bitbucket endpoint and instantiate class Bitbucket_API.
			 * Save private setting if present.
			 * Ensures `maybe_authenticate_http()` is available.
			 */
			if ( 'bitbucket' === self::$install['github_updater_api'] ) {
				$this->load_authentication_hooks();
				if ( self::$installed_apis['bitbucket_api'] ) {
					self::$install = Singleton::get_instance( 'API\Bitbucket_API', $this, new \stdClass() )->remote_install( $headers, self::$install );
				}

				if ( self::$installed_apis['bitbucket_server_api'] ) {
					self::$install = Singleton::get_instance( 'API\Bitbucket_Server_API', $this, new \stdClass() )->remote_install( $headers, self::$install );
				}
			}

			/*
			 * Create GitLab endpoint.
			 * Save Access Token if present.
			 * Check for GitLab Self-Hosted.
			 */
			if ( 'gitlab' === self::$install['github_updater_api'] ) {
				if ( self::$installed_apis['gitlab_api'] ) {
					self::$install = Singleton::get_instance( 'API\GitLab_API', $this, new \stdClass() )->remote_install( $headers, self::$install );
				}
			}

			/*
			 * Create Gitea endpoint.
			 * Save Access Token if present.
			 */
			if ( 'gitea' === self::$install['github_updater_api'] ) {
				if ( self::$installed_apis['gitea_api'] ) {
					self::$install = Singleton::get_instance( 'API\Gitea_API', $this, new \stdClass() )->remote_install( $headers, self::$install );
				}
			}

			/*
			 * Install from Zipfile.
			 */
			if ( 'zipfile' === self::$install['github_updater_api'] ) {
				self::$install = Singleton::get_instance( 'API\Zipfile_API', $this )->remote_install( $headers, self::$install );
			}

			if ( isset( self::$install['options'] ) ) {
				$this->save_options_on_install( self::$install['options'] );
			}

			$url      = self::$install['download_link'];
			$upgrader = $this->get_upgrader( $type, $url );

			// Install the repo from the $source urldecode() and save branch setting.
			if ( $upgrader && $upgrader->install( $url ) ) {
				Singleton::get_instance( 'Branch', $this )->set_branch_on_install( self::$install );
			} else {
				return false;
			}
		}

		return true;
	}

	/**
	 * Save options set during installation.
	 *
	 * @param  array $install_options Array of options from remote install process.
	 * @return void
	 */
	private function save_options_on_install( $install_options ) {
		self::$options = array_merge( self::$options, $install_options );
		update_site_option( 'github_updater', self::$options );
	}

	/**
	 * Set remote install data into $_POST.
	 *
	 * @param array $config Data for a remote install.
	 */
	private function set_install_post_data( $config ) {
		if ( ! isset( $config['uri'] ) ) {
			return;
		}

		$headers = $this->parse_header_uri( $config['uri'] );
		$api     = false !== strpos( $headers['host'], '.com' )
			? rtrim( $headers['host'], '.com' )
			: rtrim( $headers['host'], '.org' );

		$api = isset( $config['git'] ) ? $config['git'] : $api;

		$_POST['github_updater_repo']   = $config['uri'];
		$_POST['github_updater_branch'] = $config['branch'];
		$_POST['github_updater_api']    = $api;
		$_POST['option_page']           = 'github_updater_install';

		switch ( $api ) {
			case 'github':
				$_POST['github_access_token'] = $config['private'] ?: null;
				break;
			case 'bitbucket':
				$_POST['is_private'] = $config['private'] ? '1' : null;
				break;
			case 'gitlab':
				$_POST['gitlab_access_token'] = $config['private'] ?: null;
				break;
			case 'gitea':
				$_POST['gitea_access_token'] = $config['private'] ?: null;
				break;
			case 'zipfile':
				$_POST['zipfile_slug'] = $config['slug'];
				break;
		}
	}

	/**
	 * Get the appropriate upgrader for remote installation.
	 *
	 * @param string $type 'plugin' | 'theme'.
	 * @param string $url  URL of the repository to be installed.
	 *
	 * @return bool|\Plugin_Upgrader|\Theme_Upgrader
	 */
	private function get_upgrader( $type, $url ) {
		$nonce    = wp_nonce_url( $url );
		$upgrader = false;

		if ( 'plugin' === $type ) {
			$plugin = self::$install['repo'];

			// Create a new instance of Plugin_Upgrader.
			$skin     = static::is_wp_cli()
				? new CLI_Plugin_Installer_Skin()
				: new \Plugin_Installer_Skin( compact( 'type', 'url', 'nonce', 'plugin' ) );
			$upgrader = new \Plugin_Upgrader( $skin );
			add_filter(
				'install_plugin_complete_actions',
				[
					$this,
					'install_plugin_complete_actions',
				],
				10,
				3
			);
		}

		if ( 'theme' === $type ) {
			$theme = self::$install['repo'];

			// Create a new instance of Theme_Upgrader.
			$skin     = static::is_wp_cli()
				? new CLI_Theme_Installer_Skin()
				: new \Theme_Installer_Skin( compact( 'type', 'url', 'nonce', 'theme' ) );
			$upgrader = new \Theme_Upgrader( $skin );
			add_filter(
				'install_theme_complete_actions',
				[
					$this,
					'install_theme_complete_actions',
				],
				10,
				3
			);
		}

		return $upgrader;
	}

	/**
	 * Create Install Plugin or Install Theme page.
	 *
	 * @param string $type
	 */
	public function create_form( $type ) {
		// Bail if installing.
		if ( isset( $_POST['option_page'] ) && 'github_updater_install' === $_POST['option_page'] ) {
			return;
		}

		$this->register_settings( $type ); ?>
		<form method="post">
			<?php
			settings_fields( 'github_updater_install' );
			do_settings_sections( 'github_updater_install_' . $type );
			if ( 'plugin' === $type ) {
				submit_button( esc_html__( 'Install Plugin', 'github-updater' ) );
			}
			if ( 'theme' === $type ) {
				submit_button( esc_html__( 'Install Theme', 'github-updater' ) );
			}
			?>
		</form>
		<?php
	}

	/**
	 * Add settings sections.
	 *
	 * @param string $type plugin|theme.
	 */
	public function register_settings( $type ) {
		$repo_type = null;

		// Place translatable strings into variables.
		if ( 'plugin' === $type ) {
			$repo_type = esc_html__( 'Plugin', 'github-updater' );
		}
		if ( 'theme' === $type ) {
			$repo_type = esc_html__( 'Theme', 'github-updater' );
		}

		register_setting(
			'github_updater_install',
			'github_updater_install_' . $type,
			[ $this, 'sanitize' ]
		);

		add_settings_section(
			$type,
			/* translators: variable is 'Plugin' or 'Theme' */
			sprintf( esc_html__( 'GitHub Updater Install %s', 'github-updater' ), $repo_type ),
			[],
			'github_updater_install_' . $type
		);

		add_settings_field(
			$type . '_repo',
			/* translators: variable is 'Plugin' or 'Theme' */
			sprintf( esc_html__( '%s URI', 'github-updater' ), $repo_type ),
			[ $this, 'get_repo' ],
			'github_updater_install_' . $type,
			$type
		);

		add_settings_field(
			$type . '_branch',
			esc_html__( 'Repository Branch', 'github-updater' ),
			[ $this, 'branch' ],
			'github_updater_install_' . $type,
			$type
		);

		add_settings_field(
			$type . '_api',
			esc_html__( 'Remote Repository Host', 'github-updater' ),
			[ $this, 'install_api' ],
			'github_updater_install_' . $type,
			$type
		);

		/**
		 * Action hook to add git API install settings fields.
		 *
		 * @since 8.0.0
		 *
		 * @param string $type 'plugin'|'theme'.
		 */
		do_action( 'github_updater_add_install_settings_fields', $type );

		// Load install settings fields for existing APIs that are not loaded.
		$running_servers     = $this->get_running_git_servers();
		$servers_not_running = array_diff( array_flip( self::$git_servers ), $running_servers );
		if ( ! empty( $servers_not_running ) ) {
			foreach ( array_keys( $servers_not_running ) as $server ) {
				$class = 'API\\' . $server . '_API';
				Singleton::get_instance( $class, $this )->add_install_settings_fields( $type );
			}
		}
	}

	/**
	 * Repo setting.
	 */
	public function get_repo() {
		?>
		<label for="github_updater_repo">
			<input type="text" style="width:50%;" id="github_updater_repo" name="github_updater_repo" value="" autofocus>
			<br>
			<span class="description">
				<?php esc_html_e( 'URI is case sensitive.', 'github-updater' ); ?>
			</span>
		</label>
		<?php
	}

	/**
	 * Branch setting.
	 */
	public function branch() {
		?>
		<label for="github_updater_branch">
			<input type="text" style="width:50%;" id="github_updater_branch" name="github_updater_branch" value="" placeholder="master">
			<br>
			<span class="description">
				<?php esc_html_e( 'Enter branch name or leave empty for `master`', 'github-updater' ); ?>
			</span>
		</label>
		<?php
	}

	/**
	 * API setting.
	 */
	public function install_api() {
		?>
		<label for="github_updater_api">
			<select id="github_updater_api" name="github_updater_api">
				<?php foreach ( self::$git_servers as $key => $value ) : ?>
					<?php if ( self::$installed_apis[ $key . '_api' ] ) : ?>
						<option value="<?php esc_attr_e( $key ); ?>" <?php selected( $key ); ?> >
							<?php esc_html_e( $value ); ?>
						</option>
					<?php endif ?>
				<?php endforeach ?>
			</select>
		</label>
		<?php
	}

	/**
	 * Remove activation links after plugin installation as no method to get $plugin_file.
	 *
	 * @param array  $install_actions Array of plugin actions.
	 * @param mixed  $api             Unused.
	 * @param string $plugin_file     Plugin file/slug.
	 *
	 * @return mixed
	 */
	public function install_plugin_complete_actions( $install_actions, $api, $plugin_file ) {
		unset( $install_actions['activate_plugin'], $install_actions['network_activate'] );

		return $install_actions;
	}

	/**
	 * Fix activation links after theme installation, no method to get proper theme name.
	 *
	 * @param array $install_actions Array of theme actions.
	 * @param mixed $api             Unused.
	 * @param mixed $theme_info      Theme slug.
	 *
	 * @return mixed
	 */
	public function install_theme_complete_actions( $install_actions, $api, $theme_info ) {
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

		$install_actions['activate'] = '<a href="' . $activate_link . '" class="activatelink"><span aria-hidden="true">' . esc_attr__( 'Activate', 'github-updater' ) . '</span><span class="screen-reader-text">' . esc_attr__( 'Activate', 'github-updater' ) . ' &#8220;' . $stylesheet . '&#8221;</span></a>';

		if ( is_network_admin() && current_user_can( 'manage_network_themes' ) ) {
			$network_activate_link = add_query_arg(
				[
					'action' => 'enable',
					'theme'  => rawurlencode( $stylesheet ),
				],
				network_admin_url( 'themes.php' )
			);
			$network_activate_link = esc_url( wp_nonce_url( $network_activate_link, 'enable-theme_' . $stylesheet ) );

			$install_actions['network_enable'] = '<a href="' . $network_activate_link . '" target="_parent">' . esc_attr_x( 'Network Enable', 'This refers to a network activation in a multisite installation', 'github-updater' ) . '</a>';
			unset( $install_actions['activate'] );
		}
		ksort( $install_actions );

		return $install_actions;
	}
}
