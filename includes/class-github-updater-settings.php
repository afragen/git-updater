<?php
/**
 * Created by PhpStorm.
 * User: afragen
 * Date: 10/25/14
 * Time: 3:39 PM
 */

class GitHub_Updater_Settings extends GitHub_Updater {
	/**
	 * Holds the values to be used in the fields callbacks
	 */
	protected $options;

	static $ghu_plugins;
	static $ghu_themes;


	/**
	 * Start up
	 */
	public function __construct() {
		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'network_admin_edit_github-updater', array( $this, 'update_network_setting' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );

		// Merge and update new changes
		if ( isset( $_POST['github_updater'] ) ) {
			update_site_option( 'github_updater', $_POST['github_updater'] );
		}
	}


/**
	 * Add options page
	 */
	public function add_plugin_page() {
		/*
		// This page will be under "Settings"
		add_options_page(
			'Settings Admin',
			'My Settings',
			'manage_options',
			'my-setting-admin',
			array( $this, 'create_admin_page' )
		);
*/
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
		// Set class property
		$this->options = get_site_option( 'github_updater' );
		$action = is_multisite() ? 'edit.php?action=github-updater' : 'options.php';
		?>
		<div class="wrap">
			<h2>GitHub Updater Settings</h2>
			<form method="post" action="<?php echo $action; ?>">
				<?php
				// This prints out all hidden setting fields
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
		$this->options = get_site_option( 'github_updater' );
		register_setting(
			'github_updater', // Option group
			'github_updater', // Option name
			array( $this, 'sanitize' ) // Sanitize
		);

		add_settings_section(
			'github_id', // ID
			'GitHub Private Settings', // Title
			array( $this, 'print_section_github_info' ), // Callback
			'github-updater' // Page
		);

		add_settings_section(
			'bitbucket_id',
			'Bitbucket Private Settings',
			array( $this, 'print_section_bitbucket_info' ), // Callback
			'github-updater'
		);

/*		add_settings_field(
			'id_number', // ID
			'ID Number', // Title
			array( $this, 'id_number_callback' ), // Callback
			'github-updater', // Page
			'github_id' // Section
		);

		add_settings_field(
			'token',
			'Access Token',
			array( $this, 'token_callback' ),
			'github-updater',
			'github_id'
		);

		add_settings_field(
			'password',
			'Password',
			array( $this, 'token_callback' ),
			'github-updater',
			'bitbucket_id'
		);*/

		$this->ghu_tokens();
	}

	/**
	 * Create and return settings fields.
	 *
	 * @return array
	 */
	public function ghu_tokens() {
		$setting_field = array();
		$ghu_tokens    = array( GitHub_Updater_Settings::$ghu_plugins, GitHub_Updater_Settings::$ghu_themes );

		foreach ( $ghu_tokens as $key => $tokens ) {
			foreach ( $tokens as $token) {
				$type = '';

				$setting_field[ $token->repo ]['id'] = $token->repo;
				$setting_field[ $token->repo ]['page'] = 'github-updater';
				if ( false !== strpos( $token->type, 'theme') ) {
					$type = ' Theme';
				}
				if ( false !== strpos( $token->type, 'github' ) ) {
					$setting_field[ $token->repo ]['title'] = $token->name . $type;
					$setting_field[ $token->repo ]['section'] = 'github_id';
				}
				if ( false !== strpos( $token->type, 'bitbucket' ) ) {
					$setting_field[ $token->repo ]['title'] = $token->name . $type;
					$setting_field[ $token->repo ]['section'] = 'bitbucket_id';
				}

				$setting_fields =  add_settings_field(
					$setting_field[ $token->repo ]['id'],
					$setting_field[ $token->repo ]['title'],
					array( $this, 'token_callback' ),
					$setting_field[ $token->repo ]['page'],
					$setting_field[ $token->repo ]['section'],
					$setting_field[ $token->repo ]['id']
				);
			}
		}

		return $setting_fields;
	}

	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $input Contains all settings fields as array keys
	 * @return array
	 */
	public function sanitize( $input ) {
		$new_input = array();
		/*
		if( isset( $input['id_number'] ) ) {
			$new_input['id_number'] = absint( $input['id_number'] );
		}*/
		foreach ( $input as $id => $value ) {
			$new_input[$id] = sanitize_text_field( $input[ $id ] );
		}
/*
		if( isset( $input['token'] ) ) {
			$new_input['token'] = sanitize_text_field( $input['token'] );
		}
*/
		return $new_input;
	}

	/**
	 * Print the Section text
	 */
	public function print_section_github_info() {
		//print 'Enter your settings below:';
		print 'Enter your GitHub Access Token';
	}

	/**
	 * Print the Section text
	 */
	public function print_section_bitbucket_info() {
		//print 'Enter your settings below:';
		print 'Enter your Bitbucket password:';
	}

	/**
	 * Get the settings option array and print one of its values
	public function id_number_callback() {
		printf(
			'<input type="text" id="id_number" name="github_updater[id_number]" value="%s" />',
			isset( $this->options['id_number'] ) ? esc_attr( $this->options['id_number']) : ''
		);
	}
*/

	/**
	 * Get the settings option array and print one of its values
	 */
	/*
	public function token_callback( $id ) {
		printf(
			'<input type="text" id="' . $id . '" name="github_updater[' . $id . ']" value="%s" />',
			isset( $this->options[ $id ] ) ? esc_attr( $this->options[ $id ]) : ''
		);
	}*/

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
