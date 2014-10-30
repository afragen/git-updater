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
	 * Holds the values to be used in the fields callbacks
	 */
	protected $options;

	/**
	 * Listing of plugins.
	 * @var
	 */
	static $ghu_plugins;

	/**
	 * Listing of themes.
	 * @var
	 */
	static $ghu_themes;


	/**
	 * Start up
	 */
	public function __construct() {
		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'network_admin_edit_github-updater', array( $this, 'update_network_setting' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
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
			<h2>GitHub Updater Settings</h2>
			<?php if ( isset( $_GET['updated'] ) && true == $_GET['updated'] ): ?>
				<div class="updated"><p><strong><?php _e( 'Saved.', 'github-updater' ); ?></strong></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo $action; ?>">
				<?php
				settings_fields( 'github_updater' );
				do_settings_sections( 'github-updater' );
				submit_button();
				?>
			</form>
		</div>
	<?php
	}

	/**
	 * Register and add settings
	 */
	public function page_init() {
		$this->ghu_tokens();

		add_settings_section(
			'github_id',                                 // ID
			'GitHub Private Settings',                   // Title
			array( $this, 'print_section_github_info' ),
			'github-updater'                             // Page
		);

		add_settings_section(
			'bitbucket_id',
			'Bitbucket Private Settings',
			array( $this, 'print_section_bitbucket_info' ),
			'github-updater'
		);

	}

	/**
	 * Create and return settings fields.
	 *
	 * @return void
	 */
	public function ghu_tokens() {
		$this->options = get_site_option( 'github_updater' );
		$setting_field = array();
		$ghu_tokens    = array( self::$ghu_plugins, self::$ghu_themes );

		foreach ( $ghu_tokens as $key => $tokens ) {
			foreach ( $tokens as $token) {
				$type = '';

				$setting_field[ $token->repo ]['id'] = $token->repo;
				$setting_field[ $token->repo ]['page'] = 'github-updater';
				if ( false !== strpos( $token->type, 'theme') ) {
					$type = 'Theme: ';
				}
				if ( false !== strpos( $token->type, 'github' ) ) {
					$setting_field[ $token->repo ]['title'] = $type . $token->name;
					$setting_field[ $token->repo ]['section'] = 'github_id';
				}
				if ( false !== strpos( $token->type, 'bitbucket' ) ) {
					$setting_field[ $token->repo ]['title'] = $type . $token->name;
					$setting_field[ $token->repo ]['section'] = 'bitbucket_id';
				}

				add_settings_field(
					$setting_field[ $token->repo ]['id'],
					$setting_field[ $token->repo ]['title'],
					array( $this, 'token_callback' ),
					$setting_field[ $token->repo ]['page'],
					$setting_field[ $token->repo ]['section'],
					$setting_field[ $token->repo ]['id']
				);

				register_setting(
					'github_updater',                     // Option group
					$setting_field[ $token->repo ]['id'], // Option name
					array( $this, 'sanitize' )            // Sanitize
				);

			}
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
			$new_input[$id] = sanitize_text_field( $input[ $id ] );
		}

		return $new_input;
	}

	/**
	 * Print the Section text
	 */
	public function print_section_github_info() {
		print __( 'Enter your GitHub Access Token', 'github-updater' );
	}

	/**
	 * Print the Section text
	 */
	public function print_section_bitbucket_info() {
		print __( 'Enter your Bitbucket password:', 'github-updater' );
	}


	/**
	 * Get the settings option array and print one of its values
	 *
	 * @param $id
	 */
	public function token_callback( $id ) {
		?>
		<label for="<?php echo $id; ?>">
			<input type="text"  name="github_updater[<?php echo $id; ?>]" value="<?php echo esc_attr( $this->options[ $id ] ); ?>">
		</label>
		<?php
	}

	/**
	 * Update network settings.
	 *
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

}
