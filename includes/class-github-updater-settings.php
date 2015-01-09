<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

/**
 * Add a settings page.
 *
 * @package GitHub_Updater_Settings
 * @author Andy Fragen
 */
class GitHub_Updater_Settings extends GitHub_Updater {

	/**
	 * Holds the plugin basename
	 * @var string
	 */
	private $ghu_plugin_name = 'github-updater/github-updater.php';

	/**
	 * Listing of plugins.
	 * @var array
	 */
	static $ghu_plugins = array();

	/**
	 * Listing of themes.
	 * @var array
	 */
	static $ghu_themes = array();

	/**
	 * Holds boolean on whether or not the repo is private
	 * @var bool
	 */
	private static $github_private    = false;
	private static $bitbucket_private = false;

	/**
	 * Start up
	 */
	public function __construct() {
		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'network_admin_edit_github-updater', array( $this, 'update_network_setting' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );

		add_filter( is_multisite() ? 'network_admin_plugin_action_links_' . $this->ghu_plugin_name : 'plugin_action_links_' . $this->ghu_plugin_name, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Add options page
	 */
	public function add_plugin_page() {
		if ( is_multisite() ) {
			add_submenu_page(
				'settings.php',
				__( 'GitHub Updater Settings', 'github-updater' ),
				__( 'GitHub Updater', 'github-updater' ),
				'manage_network',
				'github-updater',
				array( $this, 'create_admin_page' )
			);
		} else {
			add_options_page(
				__( 'GitHub Updater Settings', 'github-updater' ),
				__( 'GitHub Updater', 'github-updater' ),
				'manage_options',
				'github-updater',
				array( $this, 'create_admin_page' )
			);
		}
	}

	/**
	 * Options page callback
	 */
	public function create_admin_page() {
		$action = is_multisite() ? 'edit.php?action=github-updater' : 'options.php';
		?>
		<div class="wrap">
			<h2><?php _e( 'GitHub Updater Settings', 'github-updater' ); ?></h2>
			<?php if ( isset( $_GET['updated'] ) && true == $_GET['updated'] ): ?>
				<div class="updated"><p><strong><?php _e( 'Saved.', 'github-updater' ); ?></strong></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo $action; ?>">
				<?php
				settings_fields( 'github_updater' );
				do_settings_sections( 'github-updater' );
				if ( self::$github_private || self::$bitbucket_private ) {
					submit_button();
				}
				?>
			</form>
		</div>
	<?php
	}

	/**
	 * Register and add settings
	 * Check to see if it's a private repo
	 */
	public function page_init() {
		$this->ghu_tokens();

		if ( self::$github_private ) {
			add_settings_section(
				'github_id',                                       // ID
				__( 'GitHub Private Settings', 'github-updater' ), // Title
				array( $this, 'print_section_github_info' ),
				'github-updater'                                   // Page
			);
		}

		if ( self::$bitbucket_private ) {
			add_settings_section(
				'bitbucket_user',
				__( 'Bitbucket Private Settings', 'github-updater' ),
				array( $this, 'print_section_bitbucket_username' ),
				'github-updater'
			);

			add_settings_section(
				'bitbucket_id',
				__( 'Bitbucket Private Repositories', 'github-updater' ),
				array( $this, 'print_section_bitbucket_info' ),
				'github-updater'
			);
		}

		if ( ! self::$github_private && ! self::$bitbucket_private ) {
			add_settings_section(
				null,
				__( 'No private repositories are installed.', 'github-updater' ),
				array(),
				'github-updater'
			);
		}

		if ( isset( $_POST['github_updater'] ) && ! is_multisite() ) {
			update_site_option( 'github_updater', $this->sanitize( $_POST['github_updater'] ) );
		}
	}

	/**
	 * Create and return settings fields.
	 *
	 * @return void
	 */
	public function ghu_tokens() {
		$ghu_options_keys = array();
		$ghu_tokens       = array_merge( self::$ghu_plugins, self::$ghu_themes );

		foreach ( $ghu_tokens as $token ) {
			$type                             = '';
			$setting_field                    = array();
			$ghu_options_keys[ $token->repo ] = null;

			// Check to see if it's a private repo
			if ( $token->private ) {
				if ( false !== strpos( $token->type, 'github' ) && ! self::$github_private )  {
					self::$github_private = true;
				}
				if ( false !== strpos( $token->type, 'bitbucket' ) && ! self::$bitbucket_private ) {
					self::$bitbucket_private = true;

					add_settings_field(
						'bitbucket_username',
						'Bitbucket Username',
						array( $this, 'token_callback_text' ),
						'github-updater',
						'bitbucket_user',
						'bitbucket_username'
					);

					add_settings_field(
						'bitbucket_password',
						'Bitbucket Password',
						array( $this, 'token_callback_text' ),
						'github-updater',
						'bitbucket_user',
						'bitbucket_password'
					);
				}
			}

			// Next if not a private repo
			if ( ! $token->private ) {
				continue;
			}

			if ( false !== strpos( $token->type, 'theme') ) {
				$type = __( 'Theme: ', 'github-updater' );
			}

			$setting_field['id']    = $token->repo;
			$setting_field['title'] = $type . $token->name;
			$setting_field['page']  = 'github-updater';
			if ( false !== strpos( $token->type, 'github' ) ) {
				$setting_field['section']         = 'github_id';
				$setting_field['callback_method'] = array( $this, 'token_callback_text' );
				$setting_field['callback']        =  $token->repo;
			}
			if ( false !== strpos( $token->type, 'bitbucket' ) ) {
				$setting_field['section']         = 'bitbucket_id';
				$setting_field['callback_method'] = array( $this, 'token_callback_checkbox' );
				$setting_field['callback']        = $token->repo;
			}

			add_settings_field(
				$setting_field['id'],
				$setting_field['title'],
				$setting_field['callback_method'],
				$setting_field['page'],
				$setting_field['section'],
				$setting_field['callback']
			);

			register_setting(
				'github_updater',           // Option group
				$setting_field['id'],       // Option name
				array( $this, 'sanitize' )  // Sanitize
			);
		}

		// Unset options that are no longer present
		$ghu_unset_keys = array_diff_key( parent::$options, $ghu_options_keys );
		unset( $ghu_unset_keys['bitbucket_username'] );
		unset( $ghu_unset_keys['bitbucket_password'] );
		if ( ! empty( $ghu_unset_keys ) ) {
			foreach ( $ghu_unset_keys as $key => $value ) {
				unset( parent::$options [ $key ] );
			}
			update_site_option( 'github_updater', parent::$options );
		}
	}

	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $input Contains all settings fields as array keys
	 * @return array
	 */
	public function sanitize( $input ) {
		$new_input = array();
		foreach ( $input as $id => $value ) {
			$new_input[ $id ] = sanitize_text_field( $input[ $id ] );
		}

		return $new_input;
	}

	/**
	 * Print the GitHub text
	 */
	public function print_section_github_info() {
		print __( 'Enter your GitHub Access Token. Leave empty for public repositories.', 'github-updater' );
	}

	/**
	 * Print the Bitbucket repo text
	 */
	public function print_section_bitbucket_info() {
		print __( 'Check box if private repository. Leave unchecked for public repositories.', 'github-updater' );
	}


	/**
	 * Print the Bitbucket user/pass text
	 */
	public function print_section_bitbucket_username() {
		print __( 'Enter your personal Bitbucket username and password.', 'github-updater' );
	}

	/**
	 * Get the settings option array and print one of its values
	 *
	 * @param $id
	 */
	public function token_callback_text( $id ) {
		?>
		<label for="<?php echo $id; ?>">
			<input type="text" style="width:50%;" name="github_updater[<?php echo $id; ?>]" value="<?php echo esc_attr( parent::$options[ $id ] ); ?>">
		</label>
		<?php
	}

	/**
	 * Get the settings option array and print one of its values
	 *
	 * @param $id
	 */
	public function token_callback_checkbox( $id ) {
		?>
		<label for="<?php echo $id; ?>">
			<input type="checkbox" name="github_updater[<?php echo $id; ?>]" value="1" <?php checked('1', parent::$options[ $id ], true); ?> />
		</label>
		<?php
	}

	/**
	 * Update network settings.
	 * Used when plugin is network activated to save settings.
	 *
	 * @link http://wordpress.stackexchange.com/questions/64968/settings-api-in-multisite-missing-update-message
	 * @link http://benohead.com/wordpress-network-wide-plugin-settings/
	 */
	public function update_network_setting() {
		update_site_option( 'github_updater', $this->sanitize( $_POST['github_updater'] ) );
		wp_redirect( add_query_arg(
			array(
				'page'    => 'github-updater',
				'updated' => 'true',
			),
			network_admin_url( 'settings.php' )
		) );
		exit;
	}

	/**
	 * Add setting link to plugin page.
	 * Applied to the list of links to display on the plugins page (beside the activate/deactivate links).
	 *
	 * @link http://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
	 * @param $links
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$settings_page = is_multisite() ? 'settings.php' : 'options-general.php';
		$link          = array( '<a href="' . network_admin_url( $settings_page ) . '?page=github-updater">' . __( 'Settings', 'github-updater' ) . '</a>' );

		return array_merge( $links, $link );
	}

}
