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


/**
 * Install <author>/<repo> directly from GitHub Updater.
 *
 * Class    Install
 * @package Fragen\GitHub_Updater
 */
class Install extends Base {

	/**
	 * Remote Host APIs.
	 * @var array
	 */
	private static $api = array(
		'github'    => 'GitHub',
		'bitbucket' => 'Bitbucket'
	);

	/**
	 * Class options.
	 * @var array
	 */
	protected static $install = array();

	/**
	 * Constructor
	 * Need class-wp-upgrader.php for upgrade classes.
	 * @param $type
	 */
	public function __construct( $type ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$this->install( $type );
	}

	/**
	 * Install remote plugin or theme.
	 * @param $type
	 */
	public function install( $type ) {

		if ( isset( $_POST['option_page'] ) && 'github_updater_install' == $_POST['option_page'] ) {
			if ( empty( $_POST['github_updater_branch'] ) ) {
				$_POST['github_updater_branch'] = 'master';
			}

			/**
			 * Transform URI to owner/repo
			 */
			$_POST['github_updater_repo'] = parse_url( $_POST['github_updater_repo'], PHP_URL_PATH );
			$_POST['github_updater_repo'] = trim( $_POST['github_updater_repo'], '/' );

			self::$install = Settings::sanitize( $_POST );
			self::$install['repo'] = explode( '/', self::$install['github_updater_repo'] );

			/**
			 * Create GitHub endpoint.
			 * Save Access Token if present.
			 */
			if ( 'github' === self::$install['github_updater_api'] ) {
				self::$install['download_link'] = 'https://api.github.com/repos/' . self::$install['github_updater_repo'] . '/zipball/' . self::$install['github_updater_branch'];

				if ( ! empty( self::$install['github_access_token'] ) ) {
					self::$install['download_link'] = add_query_arg( 'access_token', self::$install['github_access_token'], self::$install['download_link'] );
					parent::$options[ self::$install['repo'][1] ] = self::$install['github_access_token'];
				}
			}

			/**
			 * Create Bitbucket endpoint and instantiate class Bitbucket_API.
			 * Save private setting if present.
			 * Ensures `maybe_authenticate_http()` is available.
			 */
			if ( 'bitbucket' === self::$install['github_updater_api'] ) {
				self::$install['download_link'] = 'https://bitbucket.org/' . self::$install['github_updater_repo'] . '/get/' . self::$install['github_updater_branch'] . '.zip';
				if ( isset( self::$install['is_private'] ) ) {
					parent::$options[ self::$install['repo'][1] ] = 1;
				}

				new Bitbucket_API( (object) $type );
			}

			update_site_option( 'github_updater', parent::$options );
			$url   = self::$install['download_link'];
			$nonce = wp_nonce_url( $url );

			if ( 'plugin' === $type ) {
				$plugin = self::$install['repo'][1];

				/**
				 * Create a new instance of Plugin_Upgrader.
				 */
				$upgrader = new \Plugin_Upgrader( $skin = new \Plugin_Installer_Skin( compact( 'type', 'title', 'url', 'nonce', 'plugin', 'api' ) ) );
			}

			if ( 'theme' === $type ) {
				$theme = self::$install['repo'][1];

				/**
				 * Create a new instance of Theme_Upgrader.
				 */
				$upgrader = new \Theme_Upgrader( $skin = new \Theme_Installer_Skin( compact( 'type', 'title', 'url', 'nonce', 'theme', 'api' ) ) );
			}

			/**
			 * Perform the action and install the plugin from the $source urldecode().
			 * Flush cache so we can make sure that the installed plugins/themes list is always up to date.
			 */
			$upgrader->install( $url );
			wp_cache_flush();
		}

		if ( ! isset( $_POST['option_page'] ) || ! ( 'github_updater_install' === $_POST['option_page'] ) ) {
			$this->create_form( $type );
		}
	}

	/**
	 * Create Install Plugin or Install Theme page.
	 * @param $type
	 */
	public function create_form( $type ) {
		$this->register_settings( $type );
		?>
		<form method="post">
			<?php
			settings_fields( 'github_updater_install' );
			do_settings_sections( 'github_updater_install_' . $type );
			if ( 'plugin' === $type ) {
				submit_button( __( 'Install Plugin', 'github-updater' ) );
			}
			if ( 'theme' === $type ) {
				submit_button( __( 'Install Theme', 'github-updater' ) );
			}
			?>
		</form>
		<?php
	}

	/**
	 * Add settings sections.
	 * @param $type
	 */
	public function register_settings( $type ) {

		/**
		 * Place translatable strings into variables.
		 */
		if ( 'plugin' === $type ) {
			$repo_type = __( 'Plugin', 'github-updater' );
		}
		if ( 'theme' === $type ) {
			$repo_type = __( 'Theme', 'github-updater' );
		}

		register_setting(
			'github_updater_install',
			'github_updater_install_' . $type,
			array( 'Settings', 'sanitize' )
			);

		add_settings_section(
			$type,
			sprintf(__( 'GitHub Updater Install %s', 'github-updater' ), $repo_type ),
			array(),
			'github_updater_install_' . $type
		);

		add_settings_field(
			$type . '_repo',
			sprintf( __( '%s URI', 'github-updater' ), $repo_type ),
			array( $this, 'get_repo' ),
			'github_updater_install_' . $type,
			$type
		);

		add_settings_field(
			$type . '_api',
			__( 'Remote Repository Host', 'github-updater' ),
			array( $this, 'api' ),
			'github_updater_install_' . $type,
			$type
		);

		add_settings_field(
			$type . '_branch',
			__( 'Repository Branch', 'github-updater' ),
			array( $this, 'branch' ),
			'github_updater_install_' . $type,
			$type
		);

		add_settings_field(
			'is_private',
			__( 'Private Bitbucket Repository', 'github-updater' ),
			array( $this, 'is_private' ),
			'github_updater_install_' . $type,
			$type
		);

		add_settings_field(
			'github_access_token',
			__( 'GitHub Access Token', 'github-updater' ),
			array( $this, 'access_token' ),
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
			<input type="text" style="width:50%;" name="github_updater_repo" value="" autofocus >
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
				<?php _e( 'Enter branch name or leave empty for `master`', 'github-updater' ) ?>
			</p>
		</label>
	<?php
	}

	/**
	 * API setting.
	 */
	public function api() {
		?>
		<label for="github_updater_api">
			<select name="github_updater_api">
				<?php foreach ( self::$api as $key => $value ): ?>
					<option value="<?php echo $key ?>" <?php selected( $key, true, true ) ?> >
						<?php echo $value ?>
					</option>
				<?php endforeach ?>
			</select>
		</label>
	<?php
	}

	/**
	 * Setting for private repo
	 */
	public function is_private() {
		?>
		<label for="is_private">
			<input type="checkbox" name="is_private" <?php checked( '1', false, true ) ?> >
		</label>
		<?php
	}

	/**
	 * GitHub Access Token for remote install
	 */
	public function access_token() {
		?>
		<label for="github_access_token">
			<input type="text" style="width:50%;" name="github_access_token" value="" >
			<p class="description">
				<?php _e( 'Enter GitHub Access Token for private GitHub repositories.', 'github-updater' ) ?>
			</p>
		</label>
		<?php
	}

}