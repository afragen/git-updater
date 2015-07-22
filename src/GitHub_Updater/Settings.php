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
 * Add a settings page.
 *
 * Class    Settings
 * @package Fragen\GitHub_Updater
 * @author  Andy Fragen
 */
class Settings extends Base {

	/**
	 * Holds the plugin basename.
	 *
	 * @var string
	 */
	private $ghu_plugin_name = 'github-updater/github-updater.php';

	/**
	 * Listing of plugins.
	 *
	 * @var array
	 */
	static $ghu_plugins = array();

	/**
	 * Listing of themes.
	 *
	 * @var array
	 */
	static $ghu_themes = array();

	/**
	 * Holds boolean on whether or not the repo is private.
	 *
	 * @var bool
	 */
	private static $github_private    = false;
	private static $bitbucket_private = false;
	private static $gitlab            = false;
	private static $gitlab_enterprise = false;

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
	 * Define tabs for Settings page.
	 * By defining in a method, strings can be translated.
	 *
	 * @return array
	 */
	private function _settings_tabs() {
		return array(
				'github_updater_settings'       => __( 'Settings', 'github-updater' ),
				'github_updater_install_plugin' => __( 'Install Plugin', 'github-updater' ),
				'github_updater_install_theme'  => __( 'Install Theme', 'github-updater' ),
			);
	}

	/**
	 * Add options page.
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
	 * Renders setting tabs.
	 *
	 * Walks through the object's tabs array and prints them one by one.
	 * Provides the heading for the settings page.
	 *
	 * @access private
	 */
	private function _options_tabs() {
		$current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'github_updater_settings';
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $this->_settings_tabs() as $key => $name ) {
			$active = ( $current_tab == $key ) ? 'nav-tab-active' : '';
			echo '<a class="nav-tab ' . $active . '" href="?page=github-updater&tab=' . $key . '">' . $name . '</a>';
		}
		echo '</h2>';
	}

	/**
	 * Options page callback.
	 */
	public function create_admin_page() {
		$action = is_multisite() ? 'edit.php?action=github-updater' : 'options.php';
		$tab    = isset( $_GET['tab'] ) ? $_GET['tab'] : 'github_updater_settings';
		$logo   = plugins_url( basename( dirname( dirname( __DIR__ ) ) ) . '/assets/GitHub_Updater_logo_small.png' );
		?>
		<div class="wrap">
			<h2>
				<img src="<?php echo $logo; ?>" alt="GitHub Updater logo" >
				<div style="clear:both;"><?php _e( 'GitHub Updater', 'github-updater' ); ?></div>
			</h2>
			<?php $this->_options_tabs(); ?>
			<?php if ( isset( $_GET['updated'] ) && true == $_GET['updated'] ): ?>
				<div class="updated"><p><strong><?php _e( 'Saved.', 'github-updater' ); ?></strong></p></div>
			<?php endif; ?>
			<?php if ( 'github_updater_settings' === $tab ) : ?>
				<form method="post" action="<?php echo $action; ?>">
					<?php
						settings_fields( 'github_updater' );
						do_settings_sections( 'github_updater_install_settings' );
						submit_button();
					?>
				</form>
			<?php endif; ?>

			<?php
			if ( 'github_updater_install_plugin' === $tab ) {
				new Install( 'plugin' );
			}
			if ( 'github_updater_install_theme' === $tab ) {
				new Install( 'theme' );
			}
			?>
		</div>
		<?php

	}

	/**
	 * Register and add settings.
	 * Check to see if it's a private repo.
	 */
	public function page_init() {

		register_setting(
			'github_updater',           // Option group
			'github_updater',           // Option name
			array( $this, 'sanitize' )  // Sanitize
		);

		$this->ghu_tokens();

		/*
		 * Add basic plugin settings.
		 */
		add_settings_section(
			'github_updater_settings',
			__( 'GitHub Updater Settings', 'github-updater' ),
			array( $this, 'print_section_ghu_settings'),
			'github_updater_install_settings'
		);

		add_settings_field(
			'branch_switch',
			__( 'Enable Plugin Branch Switching', 'github-updater' ),
			array( $this, 'token_callback_checkbox' ),
			'github_updater_install_settings',
			'github_updater_settings',
			array( 'id' => 'branch_switch' )
		);

		/*
		 * Add settings for GitHub Personal Access Token.
		 */
		add_settings_section(
			'github_access_token',
			__( 'Personal GitHub Access Token', 'github-updater' ),
			array( $this, 'print_section_github_access_token' ),
			'github_updater_install_settings'
		);

		add_settings_field(
			'github_access_token',
			__( 'GitHub Access Token', 'github-updater' ),
			array( $this, 'token_callback_text' ),
			'github_updater_install_settings',
			'github_access_token',
			array( 'id' => 'github_access_token' )
		);

		/*
		 * Show section for private GitHub repositories.
		 */
		if ( self::$github_private ) {
			add_settings_section(
				'github_id',                                       // ID
				__( 'GitHub Private Settings', 'github-updater' ), // Title
				array( $this, 'print_section_github_info' ),
				'github_updater_install_settings'                  // Page
			);
		}

		/*
		 * Add setting for GitLab.com, GitLab Community Edition.
		 * or GitLab Enterprise Private Token.
		 */
		if ( self::$gitlab || self::$gitlab_enterprise ) {
			add_settings_section(
				'gitlab_settings',
				__( 'GitLab Private Settings', 'github-updater' ),
				array( $this, 'print_section_gitlab_token' ),
				'github_updater_install_settings'
			);
		}

		if ( self::$gitlab ) {
			add_settings_field(
				'gitlab_private_token',
				__( 'GitLab.com Private Token', 'github-updater' ),
				array( $this, 'token_callback_text' ),
				'github_updater_install_settings',
				'gitlab_settings',
				array( 'id' => 'gitlab_private_token' )
			);
		}

		if ( self::$gitlab_enterprise ) {
			add_settings_field(
				'gitlab_enterprise_token',
				__( 'GitLab CE or GitLab Enterprise Private Token', 'github-updater' ),
				array( $this, 'token_callback_text' ),
				'github_updater_install_settings',
				'gitlab_settings',
				array( 'id' => 'gitlab_enterprise_token' )
			);
		}

		/*
		 * Add settings for Bitbucket Username and Password.
		 */
		add_settings_section(
			'bitbucket_user',
			__( 'Bitbucket Private Settings', 'github-updater' ),
			array( $this, 'print_section_bitbucket_username' ),
			'github_updater_install_settings'
		);

		add_settings_field(
			'bitbucket_username',
			__( 'Bitbucket Username', 'github-updater' ),
			array( $this, 'token_callback_text' ),
			'github_updater_install_settings',
			'bitbucket_user',
			array( 'id' => 'bitbucket_username' )
		);

		add_settings_field(
			'bitbucket_password',
			__( 'Bitbucket Password', 'github-updater' ),
			array( $this, 'token_callback_text' ),
			'github_updater_install_settings',
			'bitbucket_user',
			array( 'id' => 'bitbucket_password' )
		);

		/*
		 * Show section for private Bitbucket repositories.
		 */
		if ( self::$bitbucket_private ) {
			add_settings_section(
				'bitbucket_id',
				__( 'Bitbucket Private Repositories', 'github-updater' ),
				array( $this, 'print_section_bitbucket_info' ),
				'github_updater_install_settings'
			);
		}

		/*
		 * Show if no private repositories are present.
		 */
		if ( ! self::$github_private && ! self::$bitbucket_private ) {
			add_settings_section(
				null,
				__( 'No private repositories are installed.', 'github-updater' ),
				array(),
				'github_updater_install_settings'
			);
		}

		if ( isset( $_POST['github_updater'] ) && ! is_multisite() ) {
			update_site_option( 'github_updater', self::sanitize( $_POST['github_updater'] ) );
		}
	}

	/**
	 * Create and return settings fields for private repositories.
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

			/*
			 * Check to see if it's a private repo and set variables.
			 */
			if ( $token->private ) {
				if ( false !== strpos( $token->type, 'github' ) && ! self::$github_private )  {
					self::$github_private = true;
				}
				if ( false !== strpos( $token->type, 'bitbucket' ) && ! self::$bitbucket_private ) {
					self::$bitbucket_private = true;
				}
			}

			/*
			 * Set boolean if GitLab header found.
			 */
			if ( false !== strpos( $token->type, 'gitlab' ) && ! self::$gitlab ) {
				self::$gitlab = true;
			}

			/*
			 * Set boolean if GitLab CE/Enterprise header found.
			 */
			if ( $token->enterprise && ! self::$gitlab_enterprise ) {
				self::$gitlab_enterprise = true;
			}

			/*
			 * Next if not a private repo.
			 */
			if ( ! $token->private ) {
				continue;
			}

			if ( false !== strpos( $token->type, 'theme') ) {
				$type = __( 'Theme:', 'github-updater' ) . '&nbsp;';
			}

			$setting_field['id']    = $token->repo;
			$setting_field['title'] = $type . $token->name;
			$setting_field['page']  = 'github_updater_install_settings';

			switch ( $token->type ) {
				case ( strpos( $token->type, 'github' ) ):
					$setting_field['section']         = 'github_id';
					$setting_field['callback_method'] = array( $this, 'token_callback_text' );
					$setting_field['callback']        = $token->repo;
					break;
				case( strpos( $token->type, 'bitbucket' ) ):
					$setting_field['section']         = 'bitbucket_id';
					$setting_field['callback_method'] = array( $this, 'token_callback_checkbox' );
					$setting_field['callback']        = $token->repo;
					break;
				case ( strpos( $token->type, 'gitlab' ) ):
					$setting_field['section']         = 'gitlab_id';
					$setting_field['callback_method'] = array( $this, 'token_callback_checkbox' );
					$setting_field['callback']        = $token->repo;
					break;
			}

			add_settings_field(
				$setting_field['id'],
				$setting_field['title'],
				$setting_field['callback_method'],
				$setting_field['page'],
				$setting_field['section'],
				array( 'id' => $setting_field['callback'] )
			);
		}

		/*
		 * Unset options that are no longer present and update options.
		 */
		$ghu_unset_keys = array_diff_key( parent::$options, $ghu_options_keys );
		unset( $ghu_unset_keys['github_access_token'] );
		unset( $ghu_unset_keys['branch_switch'] );
		unset( $ghu_unset_keys['bitbucket_username'] );
		unset( $ghu_unset_keys['bitbucket_password'] );
		if ( self::$gitlab ) {
			unset( $ghu_unset_keys['gitlab_private_token'] );
		}
		if ( self::$gitlab_enterprise ) {
			unset( $ghu_unset_keys['gitlab_enterprise_token'] );
		}
		if ( ! empty( $ghu_unset_keys ) ) {
			foreach ( $ghu_unset_keys as $key => $value ) {
				unset( parent::$options [ $key ] );
			}
			update_site_option( 'github_updater', parent::$options );
		}
	}

	/**
	 * Sanitize each setting field as needed.
	 *
	 * @param array $input Contains all settings fields as array keys
	 *
	 * @return array
	 */
	public static function sanitize( $input ) {
		$new_input = array();
		foreach ( (array) $input as $id => $value ) {
			$new_input[ sanitize_key( $id ) ] = sanitize_text_field( $input[ $id ] );
		}

		return $new_input;
	}

	/**
	 * Print the GitHub Updater text.
	 */
	public function print_section_ghu_settings() {
		if ( defined( 'GITHUB_UPDATER_EXTENDED_NAMING' ) && GITHUB_UPDATER_EXTENDED_NAMING ) {
			_e( 'Extended Naming is <strong>active</strong>.', 'github-updater' );
		}
		if ( ! defined( 'GITHUB_UPDATER_EXTENDED_NAMING' ) ||
		       ( defined( 'GITHUB_UPDATER_EXTENDED_NAMING' ) && ! GITHUB_UPDATER_EXTENDED_NAMING )
		) {
			_e( 'Extended Naming is <strong>not active</strong>.', 'github-updater' );
		}
		printf( '<br>' . __( 'Extended Naming renames plugin directories %s to prevent possible conflicts with WP.org plugins.', 'github-updater'), '<code>&lt;git&gt;-&lt;owner&gt;-&lt;repo&gt;</code>');
		printf( '<br>' . __( 'Activate Extended Naming by setting %s', 'github-updater' ), '<code>define( \'GITHUB_UPDATER_EXTENDED_NAMING\', true );</code>' );
		print( '<p>' . __( 'Check to enable branch switching from the Plugins page.', 'github-updater' ) . '</p>');
	}

	/**
	 * Print the GitHub text.
	 */
	public function print_section_github_info() {
		_e( 'Enter your GitHub Access Token. Leave empty for public repositories.', 'github-updater' );
	}

	/**
	 * Print the GitHub Personal Access Token text.
	 */
	public function print_section_github_access_token() {
		_e( 'Enter your personal GitHub Access Token to avoid API access limits.', 'github-updater' );
	}

	/**
	 * Print the Bitbucket repo text.
	 */
	public function print_section_bitbucket_info() {
		_e( 'Check box if private repository. Leave unchecked for public repositories.', 'github-updater' );
	}

	/**
	 * Print the Bitbucket user/pass text.
	 */
	public function print_section_bitbucket_username() {
		_e( 'Enter your personal Bitbucket username and password.', 'github-updater' );
	}

	/**
	 * Print the GitLab Private Token text.
	 */
	public function print_section_gitlab_token() {
		_e( 'Enter your GitLab.com, GitLab CE, or GitLab Enterprise Private Token.', 'github-updater' );
	}

	/**
	 * Get the settings option array and print one of its values.
	 *
	 * @param $args
	 */
	public function token_callback_text( $args ) {
		$name = isset( parent::$options[ $args['id' ] ] ) ? esc_attr( parent::$options[ $args['id'] ] ) : '';
		$type = stristr( $args['id'], 'password' ) ? 'password' : 'text';
		?>
		<label for="<?php echo $args['id']; ?>">
			<input type="<?php echo $type; ?>" style="width:50%;" name="github_updater[<?php echo $args['id']; ?>]" value="<?php echo $name; ?>" >
		</label>
		<?php
	}

	/**
	 * Get the settings option array and print one of its values.
	 *
	 * @param $args
	 */
	public function token_callback_checkbox( $args ) {
		?>
		<label for="<?php echo $args['id']; ?>">
			<input type="checkbox" name="github_updater[<?php echo $args['id']; ?>]" value="1" <?php checked('1', parent::$options[ $args['id'] ], true); ?> >
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
		update_site_option( 'github_updater', self::sanitize( $_POST['github_updater'] ) );
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
	 *
	 * @param $links
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$settings_page = is_multisite() ? 'settings.php' : 'options-general.php';
		$link          = array( '<a href="' . network_admin_url( $settings_page ) . '?page=github-updater">' . __( 'Settings', 'github-updater' ) . '</a>' );

		return array_merge( $links, $link );
	}

}
