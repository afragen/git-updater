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

use Fragen\Singleton;


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
 *
 * @package Fragen\GitHub_Updater
 */
class Install extends Base {

	/**
	 * Class options.
	 *
	 * @var array
	 */
	protected static $install = array();

	/**
	 * Constructor.
	 * Need class-wp-upgrader.php for upgrade classes.
	 *
	 * @param string $type
	 * @param array  $wp_cli_config
	 */
	public function __construct( $type, $wp_cli_config = array() ) {
		parent::__construct();
		$this->load_options();
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		wp_enqueue_script( 'ghu-install', plugins_url( basename( dirname( dirname( __DIR__ ) ) ) . '/js/ghu-install.js' ), array(), false, true );
	}

	/**
	 * Install remote plugin or theme.
	 *
	 * @param string $type
	 * @param array  $wp_cli_config
	 *
	 * @return bool
	 */
	public function install( $type, $wp_cli_config = null ) {
		$wp_cli = false;

		if ( ! empty( $wp_cli_config['uri'] ) ) {
			$wp_cli  = true;
			$headers = $this->parse_header_uri( $wp_cli_config['uri'] );
			$api     = false !== strpos( $headers['host'], '.com' )
				? rtrim( $headers['host'], '.com' )
				: rtrim( $headers['host'], '.org' );

			$api = isset( $wp_cli_config['git'] ) ? $wp_cli_config['git'] : $api;

			$_POST['github_updater_repo']   = $wp_cli_config['uri'];
			$_POST['github_updater_branch'] = $wp_cli_config['branch'];
			$_POST['github_updater_api']    = $api;
			$_POST['option_page']           = 'github_updater_install';

			switch ( $api ) {
				case 'github':
					$_POST['github_access_token'] = $wp_cli_config['private'] ?: null;
					break;
				case 'bitbucket':
					$_POST['is_private'] = $wp_cli_config['private'] ? '1' : null;
					break;
				case 'gitlab':
					$_POST['gitlab_access_token'] = $wp_cli_config['private'] ?: null;
					break;
				case 'gitea':
					$_POST['gitea_access_token'] = $wp_cli_config['private'] ?: null;
					break;
			}
		}

		if ( isset( $_POST['option_page'] ) && 'github_updater_install' === $_POST['option_page'] ) {
			if ( empty( $_POST['github_updater_branch'] ) ) {
				$_POST['github_updater_branch'] = 'master';
			}

			/*
			 * Exit early if no repo entered.
			 */
			if ( empty( $_POST['github_updater_repo'] ) ) {
				echo '<h3>';
				esc_html_e( 'A repository URI is required.', 'github-updater' );
				echo '</h3>';

				return false;
			}

			/*
			 * Transform URI to owner/repo
			 */
			$headers                      = $this->parse_header_uri( $_POST['github_updater_repo'] );
			$_POST['github_updater_repo'] = $headers['owner_repo'];

			self::$install         = Settings::sanitize( $_POST );
			self::$install['repo'] = $headers['repo'];

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
				Singleton::get_instance( 'Basic_Auth_Loader', $this, static::$options )->load_authentication_hooks();
				if ( static::$installed_apis['bitbucket_api'] ) {
					self::$install = Singleton::get_instance( 'API\Bitbucket_API', $this, new \stdClass() )->remote_install( $headers, self::$install );
				}

				if ( static::$installed_apis['bitbucket_server_api'] ) {
					self::$install = Singleton::get_instance( 'API\Bitbucket_Server_API', $this, new \stdClass() )->remote_install( $headers, self::$install );
				}
			}

			/*
			 * Create GitLab endpoint.
			 * Save Access Token if present.
			 * Check for GitLab Self-Hosted.
			 */
			if ( 'gitlab' === self::$install['github_updater_api'] ) {
				if ( static::$installed_apis['gitlab_api'] ) {
					self::$install = Singleton::get_instance( 'API\GitLab_API', $this, new \stdClass() )->remote_install( $headers, self::$install );
				}
			}

			/*
			 * Create Gitea endpoint.
			 * Save Access Token if present.
			 */
			if ( 'gitea' === self::$install['github_updater_api'] ) {
				if ( static::$installed_apis['gitea_api'] ) {
					self::$install = Singleton::get_instance( 'API\Gitea_API', $this, new \stdClass() )->remote_install( $headers, self::$install );
				}
			}

			static::$options = isset( self::$install['options'] )
				? array_merge( static::$options, self::$install['options'] )
				: static::$options;

			static::$options['github_updater_install_repo'] = self::$install['repo'];

			$url      = self::$install['download_link'];
			$nonce    = wp_nonce_url( $url );
			$upgrader = null;

			if ( 'plugin' === $type ) {
				$plugin = self::$install['repo'];

				/*
				 * Create a new instance of Plugin_Upgrader.
				 */
				$skin     = $wp_cli
					? new CLI_Plugin_Installer_Skin()
					: new \Plugin_Installer_Skin( compact( 'type', 'url', 'nonce', 'plugin', 'api' ) );
				$upgrader = new \Plugin_Upgrader( $skin );
				add_filter( 'install_plugin_complete_actions', array(
					&$this,
					'install_plugin_complete_actions',
				), 10, 3 );
			}

			if ( 'theme' === $type ) {
				$theme = self::$install['repo'];

				/*
				 * Create a new instance of Theme_Upgrader.
				 */
				$skin     = $wp_cli
					? new CLI_Theme_Installer_Skin()
					: new \Theme_Installer_Skin( compact( 'type', 'url', 'nonce', 'theme', 'api' ) );
				$upgrader = new \Theme_Upgrader( $skin );
				add_filter( 'install_theme_complete_actions', array(
					&$this,
					'install_theme_complete_actions',
				), 10, 3 );
			}

			// Perform the action and install the repo from the $source urldecode().
			if ( $upgrader->install( $url ) ) {
				update_site_option( 'github_updater', Settings::sanitize( static::$options ) );

				// Save branch setting.
				Singleton::get_instance( 'Branch', $this )->set_branch_on_install( self::$install );

				// Delete get_plugins() and wp_get_themes() cache.
				delete_site_option( 'ghu-' . md5( 'repos' ) );
			}

		}

		if ( $wp_cli ) {
			return true;
		}

		if ( ! isset( $_POST['option_page'] ) || ! ( 'github_updater_install' === $_POST['option_page'] ) ) {
			$this->create_form( $type );
		}

		return true;
	}

	/**
	 * Create Install Plugin or Install Theme page.
	 *
	 * @param string $type
	 */
	public function create_form( $type ) {
		$this->register_settings( $type );
		?>
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
	 * @param string $type
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
			array( 'Fragen\\GitHub_Updater\\Settings', 'sanitize' )
		);

		add_settings_section(
			$type,
			sprintf( esc_html__( 'GitHub Updater Install %s', 'github-updater' ), $repo_type ),
			array(),
			'github_updater_install_' . $type
		);

		add_settings_field(
			$type . '_repo',
			sprintf( esc_html__( '%s URI', 'github-updater' ), $repo_type ),
			array( &$this, 'get_repo' ),
			'github_updater_install_' . $type,
			$type
		);

		add_settings_field(
			$type . '_branch',
			esc_html__( 'Repository Branch', 'github-updater' ),
			array( &$this, 'branch' ),
			'github_updater_install_' . $type,
			$type
		);

		add_settings_field(
			$type . '_api',
			esc_html__( 'Remote Repository Host', 'github-updater' ),
			array( &$this, 'install_api' ),
			'github_updater_install_' . $type,
			$type
		);

		Singleton::get_instance( 'API\GitHub_API', $this, new \stdClass() )->add_install_settings_fields( $type );

		if ( static::$installed_apis['bitbucket_api'] ) {
			Singleton::get_instance( 'API\Bitbucket_API', $this, new \stdClass() )->add_install_settings_fields( $type );
		}

		if ( static::$installed_apis['gitlab_api'] ) {
			Singleton::get_instance( 'API\GitLab_API', $this, new \stdClass() )->add_install_settings_fields( $type );
		}

		if ( static::$installed_apis['gitea_api'] ) {
			Singleton::get_instance( 'API\Gitea_API', $this, new \stdClass() )->add_install_settings_fields( $type );
		}
	}

	/**
	 * Repo setting.
	 */
	public function get_repo() {
		?>
		<label for="github_updater_repo">
			<input type="text" style="width:50%;" name="github_updater_repo" value="" autofocus>
			<br>
			<span class="description">
				<?php esc_html_e( 'URI is case sensitive.', 'github-updater' ) ?>
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
			<input type="text" style="width:50%;" name="github_updater_branch" value="" placeholder="master">
			<br>
			<span class="description">
				<?php esc_html_e( 'Enter branch name or leave empty for `master`', 'github-updater' ) ?>
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
			<select name="github_updater_api">
				<?php foreach ( static::$git_servers as $key => $value ): ?>
					<?php if ( static::$installed_apis[ $key . '_api' ] ): ?>
						<option value="<?php esc_attr_e( $key ) ?>" <?php selected( $key ) ?> >
							<?php esc_html_e( $value ) ?>
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
	 * @param $install_actions
	 * @param $api
	 * @param $plugin_file
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
	 * @param $install_actions
	 * @param $api
	 * @param $theme_info
	 *
	 * @return mixed
	 */
	public function install_theme_complete_actions( $install_actions, $api, $theme_info ) {
		if ( isset( $install_actions['preview'] ) ) {
			unset( $install_actions['preview'] );
		}

		$stylesheet    = self::$install['repo'];
		$activate_link = add_query_arg( array(
			'action'     => 'activate',
			//'template'   => urlencode( $template ),
			'stylesheet' => urlencode( $stylesheet ),
		), admin_url( 'themes.php' ) );
		$activate_link = esc_url( wp_nonce_url( $activate_link, 'switch-theme_' . $stylesheet ) );

		$install_actions['activate'] = '<a href="' . $activate_link . '" class="activatelink"><span aria-hidden="true">' . esc_attr__( 'Activate', 'github-updater' ) . '</span><span class="screen-reader-text">' . esc_attr__( 'Activate', 'github-updater' ) . ' &#8220;' . $stylesheet . '&#8221;</span></a>';

		if ( is_network_admin() && current_user_can( 'manage_network_themes' ) ) {
			$network_activate_link = add_query_arg( array(
				'action' => 'enable',
				'theme'  => urlencode( $stylesheet ),
			), network_admin_url( 'themes.php' ) );
			$network_activate_link = esc_url( wp_nonce_url( $network_activate_link, 'enable-theme_' . $stylesheet ) );

			$install_actions['network_enable'] = '<a href="' . $network_activate_link . '" target="_parent">' . esc_attr_x( 'Network Enable', 'This refers to a network activation in a multisite installation', 'github-updater' ) . '</a>';
			unset( $install_actions['activate'] );
		}
		ksort( $install_actions );

		return $install_actions;
	}

}
