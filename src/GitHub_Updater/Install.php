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
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$this->install( $type, $wp_cli_config );

		wp_enqueue_script( 'ghu-install', plugins_url( basename( dirname( dirname( __DIR__ ) ) ) . '/js/ghu_install.js' ), array(), false, true );
	}

	/**
	 * Install remote plugin or theme.
	 *
	 * @param string $type
	 * @param array  $wp_cli_config
	 *
	 * @return bool
	 */
	public function install( $type, $wp_cli_config ) {
		$wp_cli = false;

		if ( ! empty( $wp_cli_config['uri'] ) ) {
			$wp_cli  = true;
			$headers = Base::parse_header_uri( $wp_cli_config['uri'] );
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
			}

			$this->load_options();
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
			$headers                      = Base::parse_header_uri( $_POST['github_updater_repo'] );
			$_POST['github_updater_repo'] = $headers['owner_repo'];

			self::$install         = Settings::sanitize( $_POST );
			self::$install['repo'] = $headers['repo'];

			/*
			 * Create GitHub endpoint.
			 * Save Access Token if present.
			 * Check for GitHub Self-Hosted.
			 */
			if ( 'github' === self::$install['github_updater_api'] ) {

				if ( 'github.com' === $headers['host'] || empty( $headers['host'] ) ) {
					$base            = 'https://api.github.com';
					$headers['host'] = 'github.com';
				} else {
					$base = $headers['base_uri'] . '/api/v3';
				}

				self::$install['download_link'] = implode( '/', array(
					$base,
					'repos',
					self::$install['github_updater_repo'],
					'zipball',
					self::$install['github_updater_branch'],
				) );
				/*
				 * If asset is entered install it.
				 */
				if ( false !== stripos( $headers['path'], 'releases/download' ) ) {
					self::$install['download_link'] = $headers['uri'];
				}

				/*
				 * Add access token if present.
				 */
				if ( ! empty( self::$install['github_access_token'] ) ) {
					self::$install['download_link']            = add_query_arg( 'access_token', self::$install['github_access_token'], self::$install['download_link'] );
					parent::$options[ self::$install['repo'] ] = self::$install['github_access_token'];
				} elseif ( ! empty( parent::$options['github_access_token'] ) &&
				           ( 'github.com' === $headers['host'] || empty( $headers['host'] ) )
				) {
					self::$install['download_link'] = add_query_arg( 'access_token', parent::$options['github_access_token'], self::$install['download_link'] );
				} elseif ( ! empty( parent::$options['github_enterprise_token'] ) ) {
					self::$install['download_link'] = add_query_arg( 'access_token', parent::$options['github_enterprise_token'], self::$install['download_link'] );
				}
			}

			/*
			 * Create Bitbucket endpoint and instantiate class Bitbucket_API.
			 * Save private setting if present.
			 * Ensures `maybe_authenticate_http()` is available.
			 */
			if ( 'bitbucket' === self::$install['github_updater_api'] ) {
				$bitbucket_org = true;

				if ( 'bitbucket.org' === $headers['host'] || empty( $headers['host'] ) ) {
					$base            = 'https://bitbucket.org';
					$headers['host'] = 'bitbucket.org';
				} else {
					$base          = $headers['base_uri'];
					$bitbucket_org = false;
				}

				if ( $bitbucket_org ) {
					self::$install['download_link'] = implode( '/', array(
						$base,
						self::$install['github_updater_repo'],
						'get',
						self::$install['github_updater_branch'] . '.zip',
					) );
					if ( isset( self::$install['is_private'] ) ) {
						parent::$options[ self::$install['repo'] ] = 1;
					}
					if ( isset( self::$install['bitbucket_username'] ) ) {
						parent::$options['bitbucket_username'] = self::$install['bitbucket_username'];
					}
					if ( isset( self::$install['bitbucket_password'] ) ) {
						parent::$options['bitbucket_password'] = self::$install['bitbucket_password'];
					}

					new Bitbucket_API( (object) $type );
				}

				if ( ! $bitbucket_org ) {
					self::$install['download_link'] = implode( '/', array(
						$base,
						'rest/archive/1.0/projects',
						$headers['owner'],
						'repos',
						$headers['repo'],
						'archive',
					) );

					self::$install['download_link'] = add_query_arg( array(
						'prefix' => $headers['repo'] . '/',
						'at'     => self::$install['github_updater_branch'],
					), self::$install['download_link'] );

					if ( isset( self::$install['is_private'] ) ) {
						parent::$options[ self::$install['repo'] ] = 1;
					}
					if ( ! empty( self::$install['bitbucket_username'] ) ) {
						parent::$options['bitbucket_server_username'] = self::$install['bitbucket_username'];
					}
					if ( ! empty( self::$install['bitbucket_password'] ) ) {
						parent::$options['bitbucket_server_password'] = self::$install['bitbucket_password'];
					}

					new Bitbucket_Server_API( (object) $type );
				}
			}

			/*
			 * Create GitLab endpoint.
			 * Check for GitLab Self-Hosted.
			 */
			if ( 'gitlab' === self::$install['github_updater_api'] ) {

				if ( 'gitlab.com' === $headers['host'] || empty( $headers['host'] ) ) {
					$base            = 'https://gitlab.com';
					$headers['host'] = 'gitlab.com';
				} else {
					$base = $headers['base_uri'];
				}

				self::$install['download_link'] = implode( '/', array(
					$base,
					self::$install['github_updater_repo'],
					'repository/archive.zip',
				) );
				self::$install['download_link'] = add_query_arg( 'ref', self::$install['github_updater_branch'], self::$install['download_link'] );

				/*
				 * Add access token if present.
				 */
				if ( ! empty( self::$install['gitlab_access_token'] ) ) {
					self::$install['download_link']            = add_query_arg( 'private_token', self::$install['gitlab_access_token'], self::$install['download_link'] );
					parent::$options[ self::$install['repo'] ] = self::$install['gitlab_access_token'];
					if ( 'gitlab.com' === $headers['host'] ) {
						parent::$options['gitlab_access_token'] = empty( parent::$options['gitlab_access_token'] ) ? self::$install['gitlab_access_token'] : parent::$options['gitlab_access_token'];
					} else {
						parent::$options['gitlab_enterprise_token'] = empty( parent::$options['gitlab_enterprise_token'] ) ? self::$install['gitlab_access_token'] : parent::$options['gitlab_enterprise_token'];
					}
				} else {
					if ( 'gitlab.com' === $headers['host'] ) {
						self::$install['download_link'] = add_query_arg( 'private_token', parent::$options['gitlab_access_token'], self::$install['download_link'] );
					} else {
						self::$install['download_link'] = add_query_arg( 'private_token', parent::$options['gitlab_enterprise_token'], self::$install['download_link'] );
					}
				}
			}

			parent::$options['github_updater_install_repo'] = self::$install['repo'];

			if ( ( defined( 'GITHUB_UPDATER_EXTENDED_NAMING' ) && GITHUB_UPDATER_EXTENDED_NAMING ) &&
			     'plugin' === $type
			) {
				parent::$options['github_updater_install_repo'] = implode( '-', array(
					self::$install['github_updater_api'],
					$headers['owner'],
					self::$install['repo'],
				) );
			}

			update_site_option( 'github_updater', Settings::sanitize( parent::$options ) );
			$url   = self::$install['download_link'];
			$nonce = wp_nonce_url( $url );

			if ( 'plugin' === $type ) {
				$plugin = self::$install['repo'];

				/*
				 * Create a new instance of Plugin_Upgrader.
				 */
				$skin     = $wp_cli
					? new CLI_Plugin_Installer_Skin()
					: new \Plugin_Installer_Skin( compact( 'type', 'title', 'url', 'nonce', 'plugin', 'api' ) );
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
					: new \Theme_Installer_Skin( compact( 'type', 'title', 'url', 'nonce', 'theme', 'api' ) );
				$upgrader = new \Theme_Upgrader( $skin );
				add_filter( 'install_theme_complete_actions', array(
					&$this,
					'install_theme_complete_actions',
				), 10, 3 );
			}

			/*
			 * Perform the action and install the repo from the $source urldecode().
			 */
			$upgrader->install( $url );
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

		/*
		 * Place translatable strings into variables.
		 */
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

		add_settings_field(
			'github_access_token',
			esc_html__( 'GitHub Access Token', 'github-updater' ),
			array( &$this, 'github_access_token' ),
			'github_updater_install_' . $type,
			$type
		);

		if ( ( empty( parent::$options['bitbucket_username'] ) ||
		       empty( parent::$options['bitbucket_password'] ) ) ||

		     ( empty( parent::$options['bitbucket_server_username'] ) ||
		       empty( parent::$options['bitbucket_server_password'] ) )
		) {
			add_settings_field(
				'bitbucket_username',
				esc_html__( 'Bitbucket Username', 'github-updater' ),
				array( &$this, 'bitbucket_username' ),
				'github_updater_install_' . $type,
				$type
			);

			add_settings_field(
				'bitbucket_password',
				esc_html__( 'Bitbucket Password', 'github-updater' ),
				array( &$this, 'bitbucket_password' ),
				'github_updater_install_' . $type,
				$type
			);
		}

		add_settings_field(
			'is_private',
			esc_html__( 'Private Bitbucket Repository', 'github-updater' ),
			array( &$this, 'is_private_repo' ),
			'github_updater_install_' . $type,
			$type
		);

		add_settings_field(
			'gitlab_access_token',
			esc_html__( 'GitLab Access Token', 'github-updater' ),
			array( &$this, 'gitlab_access_token' ),
			'github_updater_install_' . $type,
			$type
		);

	}

	/**
	 * Repo setting.
	 */
	public function get_repo() {
		?>
		<label for="github_updater_repo">
			<input type="text" style="width:50%;" name="github_updater_repo" value="" autofocus>
			<p class="description">
				<?php esc_html_e( 'URI is case sensitive.', 'github-updater' ) ?>
			</p>
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
			<p class="description">
				<?php esc_html_e( 'Enter branch name or leave empty for `master`', 'github-updater' ) ?>
			</p>
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
				<?php foreach ( parent::$git_servers as $key => $value ): ?>
					<option value="<?php esc_attr_e( $key ) ?>" <?php selected( $key, true, true ) ?> >
						<?php esc_html_e( $value ) ?>
					</option>
				<?php endforeach ?>
			</select>
		</label>
		<?php
	}

	/**
	 * Setting for private repo.
	 */
	public function is_private_repo() {
		?>
		<label for="is_private">
			<input class="bitbucket_setting" type="checkbox" name="is_private" <?php checked( '1', false, true ) ?> >
			<p class="description">
				<?php esc_html_e( 'Check for private Bitbucket repositories.', 'github-updater' ) ?>
			</p>
		</label>
		<?php
	}

	/**
	 * GitHub Access Token for remote install.
	 */
	public function github_access_token() {
		?>
		<label for="github_access_token">
			<input class="github_setting" type="text" style="width:50%;" name="github_access_token" value="">
			<p class="description">
				<?php esc_html_e( 'Enter GitHub Access Token for private GitHub repositories.', 'github-updater' ) ?>
			</p>
		</label>
		<?php
	}

	/**
	 * Bitbucket username for remote install.
	 */
	public function bitbucket_username() {
		?>
		<label for="bitbucket_username">
			<input class="bitbucket_setting" type="text" style="width:50%;" name="bitbucket_username" value="">
			<p class="description">
				<?php esc_html_e( 'Enter Bitbucket username.', 'github-updater' ) ?>
			</p>
		</label>
		<?php
	}

	/**
	 * Bitbucket password for remote install.
	 */
	public function bitbucket_password() {
		?>
		<label for="bitbucket_password">
			<input class="bitbucket_setting" type="text" style="width:50%;" name="bitbucket_password" value="">
			<p class="description">
				<?php esc_html_e( 'Enter Bitbucket password.', 'github-updater' ) ?>
			</p>
		</label>
		<?php
	}

	/**
	 * GitLab Access Token for remote install.
	 */
	public function gitlab_access_token() {
		?>
		<label for="gitlab_access_token">
			<input class="gitlab_setting" type="text" style="width:50%;" name="gitlab_access_token" value="">
			<p class="description">
				<?php esc_html_e( 'Enter GitLab Access Token for private GitLab repositories.', 'github-updater' ) ?>
			</p>
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
