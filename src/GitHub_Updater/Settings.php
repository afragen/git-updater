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
 * Class Settings
 *
 * Add a settings page.
 *
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
	 * Supported remote management services.
	 *
	 * @var array
	 */
	public static $remote_management = array(
		'ithemes_sync' => 'iThemes Sync',
		'infinitewp'   => 'InfiniteWP',
		'managewp'     => 'ManageWP',
		'mainwp'       => 'MainWP',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->refresh_caches();
		$this->ensure_api_key_is_set();
		$this->load_options();
	}

	/**
	 * Check for cache refresh.
	 */
	protected function refresh_caches() {
		if ( isset( $_POST['ghu_refresh_cache'] ) && ! ( $this instanceof Messages ) ) {
			$this->delete_all_cached_data();
		}
	}

	/**
	 * Let's get going.
	 */
	public function run() {
		$this->load_hooks();
	}

	/**
	 * Load relevant action/filter hooks.
	 */
	protected function load_hooks() {
		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', array( &$this, 'add_plugin_page' ) );
		add_action( 'network_admin_edit_github-updater', array( &$this, 'update_settings' ) );
		add_action( 'admin_init', array( &$this, 'page_init' ) );
		add_action( 'admin_init', array( &$this, 'remote_management_page_init' ) );

		add_filter( is_multisite() ? 'network_admin_plugin_action_links_' . $this->ghu_plugin_name : 'plugin_action_links_' . $this->ghu_plugin_name, array(
			&$this,
			'plugin_action_links',
		) );
	}

	/**
	 * Define tabs for Settings page.
	 * By defining in a method, strings can be translated.
	 *
	 * @access private
	 * @return array
	 */
	private function settings_tabs() {
		return array(
			'github_updater_settings'          => esc_html__( 'Settings', 'github-updater' ),
			'github_updater_install_plugin'    => esc_html__( 'Install Plugin', 'github-updater' ),
			'github_updater_install_theme'     => esc_html__( 'Install Theme', 'github-updater' ),
			'github_updater_remote_management' => esc_html__( 'Remote Management', 'github-updater' ),
		);
	}

	/**
	 * Set up the Settings Sub-tabs.
	 *
	 * @access private
	 * @return array
	 */
	private function settings_sub_tabs() {
		$subtabs = array( 'github_updater' => esc_html__( 'GitHub Updater', 'github-updater' ) );
		$gits    = $this->installed_git_repos();

		$git_subtab  = array();
		$ghu_subtabs = array(
			'github' => esc_html__( 'GitHub', 'github-updater' ),
		);
		if ( parent::$installed_apis['bitbucket_api'] ) {
			$ghu_subtabs['bitbucket'] = esc_html__( 'Bitbucket', 'github-updater' );
		}
		if ( parent::$installed_apis['bitbucket_server_api'] ) {
			$ghu_subtabs['bbserver'] = esc_html__( 'Bitbucket Server', 'github-updater' );
		}
		if ( parent::$installed_apis['gitlab_api'] ) {
			$ghu_subtabs['gitlab'] = esc_html__( 'GitLab', 'github-updater' );
		}

		foreach ( $gits as $git ) {
			if ( array_key_exists( $git, $ghu_subtabs ) ) {
				$git_subtab[ $git ] = $ghu_subtabs[ $git ];
			}
		}
		$subtabs = array_merge( $subtabs, $git_subtab );

		return $subtabs;
	}

	/**
	 * Return an array of the installed repository types.
	 *
	 * @access private
	 * @return array $gits
	 */
	private function installed_git_repos() {
		$plugins = Singleton::get_instance( 'Plugin' )->get_plugin_configs();
		$themes  = Singleton::get_instance( 'Theme' )->get_theme_configs();

		$repos = array_merge( $plugins, $themes );
		$gits  = array_map( function( $e ) {
			if ( ! empty( $e->enterprise ) && false !== stripos( $e->type, 'bitbucket' ) ) {
				return 'bbserver';
			}

			return $e->type;
		}, $repos );

		$gits = array_unique( array_values( $gits ) );

		$gits = array_map( function( $e ) {
			$e = explode( '_', $e );

			return $e[0];
		}, $gits );


		return array_unique( $gits );
	}

	/**
	 * Add options page.
	 */
	public function add_plugin_page() {
		$parent     = is_multisite() ? 'settings.php' : 'options-general.php';
		$capability = is_multisite() ? 'manage_network' : 'manage_options';

		add_submenu_page(
			$parent,
			esc_html__( 'GitHub Updater Settings', 'github-updater' ),
			esc_html__( 'GitHub Updater', 'github-updater' ),
			$capability,
			'github-updater',
			array( &$this, 'create_admin_page' )
		);
	}

	/**
	 * Renders setting tabs.
	 *
	 * Walks through the object's tabs array and prints them one by one.
	 * Provides the heading for the settings page.
	 *
	 * @access private
	 */
	private function options_tabs() {
		$current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'github_updater_settings';
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $this->settings_tabs() as $key => $name ) {
			$active = ( $current_tab === $key ) ? 'nav-tab-active' : '';
			echo '<a class="nav-tab ' . $active . '" href="?page=github-updater&tab=' . $key . '">' . $name . '</a>';
		}
		echo '</h2>';
	}

	/**
	 * Render the settings sub-tabs.
	 *
	 * @access private
	 */
	private function options_sub_tabs() {
		$current_tab = isset( $_GET['subtab'] ) ? $_GET['subtab'] : 'github_updater';
		echo '<h3 class="nav-tab-wrapper">';
		foreach ( $this->settings_sub_tabs() as $key => $name ) {
			$active = ( $current_tab === $key ) ? 'nav-tab-active' : '';
			echo '<a class="nav-tab ' . $active . '" href="?page=github-updater&tab=github_updater_settings&subtab=' . $key . '">' . $name . '</a>';
		}
		echo '</h3>';

	}

	/**
	 * Options page callback.
	 */
	public function create_admin_page() {
		$action = is_multisite() ? 'edit.php?action=github-updater' : 'options.php';
		$tab    = isset( $_GET['tab'] ) ? $_GET['tab'] : 'github_updater_settings';
		$subtab = isset( $_GET['subtab'] ) ? $_GET['subtab'] : 'github_updater';
		$logo   = plugins_url( basename( dirname( dirname( __DIR__ ) ) ) . '/assets/GitHub_Updater_logo_small.png' );
		?>
		<div class="wrap github-updater-settings">
			<h1>
				<a href="https://github.com/afragen/github-updater" target="_blank"><img src="<?php esc_attr_e( $logo ); ?>" alt="GitHub Updater logo" /></a><br>
				<?php esc_html_e( 'GitHub Updater', 'github-updater' ); ?>
			</h1>
			<?php $this->options_tabs(); ?>
			<?php if ( ! isset( $_GET['settings-updated'] ) ): ?>
				<?php if ( ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) && is_multisite() ): ?>
					<div class="updated">
						<p><?php esc_html_e( 'Settings saved.', 'github-updater' ); ?></p>
					</div>
				<?php elseif ( isset( $_GET['reset'] ) && '1' === $_GET['reset'] ): ?>
					<div class="updated">
						<p><?php esc_html_e( 'RESTful key reset.', 'github-updater' ); ?></p>
					</div>
				<?php elseif ( isset( $_GET['refresh_transients'] ) && '1' === $_GET['refresh_transients'] ) : ?>
					<div class="updated">
						<p><?php esc_html_e( 'Cache refreshed.', 'github-updater' ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( 'github_updater_settings' === $tab ) : ?>
					<?php $this->options_sub_tabs(); ?>
					<form class="settings" method="post" action="<?php esc_attr_e( $action ); ?>">
						<?php
						settings_fields( 'github_updater' );
						switch ( $subtab ) {
							case 'github':
							case 'bitbucket':
							case 'bbserver':
							case 'gitlab':
								do_settings_sections( 'github_updater_' . $subtab . '_install_settings' );
								$this->display_ghu_repos( $subtab );
								$this->add_hidden_settings_sections( $subtab );
								break;
							default:
								do_settings_sections( 'github_updater_install_settings' );
								$this->add_hidden_settings_sections();
								break;
						}
						submit_button();
						?>
					</form>
					<?php $refresh_transients = add_query_arg( array( 'github_updater_refresh_transients' => true ), $action ); ?>
					<form class="settings" method="post" action="<?php esc_attr_e( $refresh_transients ); ?>">
						<?php submit_button( esc_html__( 'Refresh Cache', 'github-updater' ), 'primary', 'ghu_refresh_cache' ); ?>
					</form>
				<?php endif; ?>
			<?php endif; ?>

			<?php
			if ( 'github_updater_install_plugin' === $tab ) {
				Singleton::get_instance( 'Install' )->install( 'plugin' );
			}
			if ( 'github_updater_install_theme' === $tab ) {
				Singleton::get_instance( 'Install' )->install( 'theme' );
			}
			?>
			<?php if ( 'github_updater_remote_management' === $tab ) : ?>
				<?php $action = add_query_arg( 'tab', $tab, $action ); ?>

				<form class="settings" method="post" action="<?php esc_attr_e( $action ); ?>">
					<?php
					settings_fields( 'github_updater_remote_management' );
					do_settings_sections( 'github_updater_remote_settings' );
					submit_button();
					?>
				</form>
				<?php $reset_api_action = add_query_arg( array( 'github_updater_reset_api_key' => true ), $action ); ?>
				<form class="settings no-sub-tabs" method="post" action="<?php esc_attr_e( $reset_api_action ); ?>">
					<?php submit_button( esc_html__( 'Reset RESTful key', 'github-updater' ) ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Register and add settings.
	 * Check to see if it's a private repo.
	 */
	public function page_init() {

		if ( static::is_doing_ajax() ) {
			return;
		}

		register_setting(
			'github_updater',
			'github_updater',
			array( &$this, 'sanitize' )
		);

		$this->ghu_tokens();

		/*
		 * Add basic plugin settings.
		 */
		add_settings_section(
			'github_updater_settings',
			esc_html__( 'GitHub Updater Settings', 'github-updater' ),
			array( &$this, 'print_section_ghu_settings' ),
			'github_updater_install_settings'
		);

		add_settings_field(
			'branch_switch',
			null,
			array( &$this, 'token_callback_checkbox' ),
			'github_updater_install_settings',
			'github_updater_settings',
			array( 'id' => 'branch_switch', 'title' => esc_html__( 'Enable Branch Switching', 'github-updater' ) )
		);

		/*
		 * Add settings for GitHub Personal Access Token.
		 */
		add_settings_section(
			'github_access_token',
			esc_html__( 'GitHub Personal Access Token', 'github-updater' ),
			array( &$this, 'print_section_github_access_token' ),
			'github_updater_github_install_settings'
		);

		add_settings_field(
			'github_access_token',
			esc_html__( 'GitHub.com Access Token', 'github-updater' ),
			array( &$this, 'token_callback_text' ),
			'github_updater_github_install_settings',
			'github_access_token',
			array( 'id' => 'github_access_token', 'token' => true )
		);

		if ( parent::$auth_required['github_enterprise'] ) {
			add_settings_field(
				'github_enterprise_token',
				esc_html__( 'GitHub Enterprise Access Token', 'github-updater' ),
				array( &$this, 'token_callback_text' ),
				'github_updater_github_install_settings',
				'github_access_token',
				array( 'id' => 'github_enterprise_token', 'token' => true )
			);
		}

		/*
		 * Show section for private GitHub repositories.
		 */
		if ( parent::$auth_required['github_private'] || parent::$auth_required['github_enterprise'] ) {
			add_settings_section(
				'github_id',
				esc_html__( 'GitHub Private Settings', 'github-updater' ),
				array( &$this, 'print_section_github_info' ),
				'github_updater_github_install_settings'
			);
		}

		if ( parent::$installed_apis['gitlab_api'] ) {
			Singleton::get_instance( 'GitLab_API', new \stdClass() )->add_settings();
		}

		if ( parent::$installed_apis['bitbucket_api'] ) {
			Singleton::get_instance( 'Bitbucket_API', new \stdClass() )->add_settings();
		}

		if ( parent::$installed_apis['bitbucket_server_api'] ) {
			Singleton::get_instance( 'Bitbucket_Server_API', new \stdClass() )->add_settings();
		}

		$this->update_settings();
	}

	/**
	 * Create and return settings fields for private repositories.
	 */
	public function ghu_tokens() {
		$ghu_options_keys = array();
		$ghu_plugins      = Singleton::get_instance( 'Plugin' )->get_plugin_configs();
		$ghu_themes       = Singleton::get_instance( 'Theme' )->get_theme_configs();
		$ghu_tokens       = array_merge( $ghu_plugins, $ghu_themes );

		foreach ( $ghu_tokens as $token ) {
			$type                             = '<span class="dashicons dashicons-admin-plugins"></span>&nbsp;';
			$setting_field                    = array();
			$ghu_options_keys[ $token->repo ] = null;

			/*
			 * Check to see if it's a private repo or Enterprise and set variables.
			 */
			$this->set_auth_required( $token );

			/*
			 * Next if not a private repo or token field not empty.
			 */
			if ( ! $this->is_private( $token ) ) {
				continue;
			}

			if ( false !== strpos( $token->type, 'theme' ) ) {
				$type = '<span class="dashicons dashicons-admin-appearance"></span>&nbsp;';
			}

			$repo_setting_field     = array();
			$setting_field['id']    = $token->repo;
			$setting_field['title'] = $type . esc_html( $token->name );

			$token_type = explode( '_', $token->type );
			switch ( $token_type[0] ) {
				case 'github':
					$setting_field['page']            = 'github_updater_github_install_settings';
					$setting_field['section']         = 'github_id';
					$setting_field['callback_method'] = array( &$this, 'token_callback_text' );
					break;
				case 'bitbucket':
					if ( empty( $token->enterprise ) ) {
						if ( parent::$installed_apis['bitbucket_api'] ) {
							$repo_setting_field = Singleton::get_instance( 'Bitbucket_API', new \stdClass() )->add_repo_setting_field();
						}
					} else {
						if ( parent::$installed_apis['bitbucket_server_api'] ) {
							$repo_setting_field = Singleton::get_instance( 'Bitbucket_Server_API', new \stdClass() )->add_repo_setting_field();
						}
					}
					break;
				case 'gitlab':
					if ( parent::$installed_apis['gitlab_api'] ) {
						$repo_setting_field = Singleton::get_instance( 'GitLab_API', new \stdClass() )->add_repo_setting_field();
					}
					break;
			}

			$setting_field             = array_merge( $setting_field, $repo_setting_field );
			$setting_field['callback'] = $token->repo;

			$title = 'token_callback_checkbox' !== $setting_field['callback_method'][1] ? $setting_field['title'] : null;
			add_settings_field(
				$setting_field['id'],
				$title,
				$setting_field['callback_method'],
				$setting_field['page'],
				$setting_field['section'],
				array( 'id' => $setting_field['callback'], 'token' => true, 'title' => $setting_field['title'] )
			);
		}

		$this->unset_stale_options( $ghu_options_keys, $ghu_tokens );
	}

	/**
	 * Check current saved options and unset if repos not present.
	 *
	 * @param array $ghu_options_keys
	 * @param array $ghu_tokens
	 */
	public function unset_stale_options( $ghu_options_keys, $ghu_tokens ) {
		$ghu_unset_keys = array_diff_key( parent::$options, $ghu_options_keys );
		$always_unset   = array(
			'db_version',
			'branch_switch',
			'github_access_token',
			'github_enterprise_token',
			'bitbucket_username',
			'bitbucket_password',
			'bitbucket_server_username',
			'bitbucket_server_password',
		);

		array_map( function( $e ) use ( &$ghu_unset_keys ) {
			unset( $ghu_unset_keys[ $e ] );
		}, $always_unset );

		$auth_required       = parent::$auth_required;
		$auth_required_unset = array(
			'github_enterprise' => 'github_enterprise_token',
			'gitlab'            => 'gitlab_access_token',
			'gitlab_enterprise' => 'gitlab_enterprise_token',
		);

		array_map( function( $e ) use ( &$ghu_unset_keys, $auth_required, $auth_required_unset ) {
			$key = array_search( $e, $auth_required_unset, true );
			if ( $auth_required[ $key ] ) {
				unset( $ghu_unset_keys[ $e ] );
			}
		}, $auth_required_unset );

		// Unset if value set AND if associated with a repo.
		// Unset if current_branch AND if associated with repo.
		array_map( function( $e ) use ( &$ghu_unset_keys, $ghu_tokens ) {
			$key  = array_search( $e, $ghu_unset_keys, true );
			$repo = str_replace( 'current_branch_', '', $key );
			if ( ( array_key_exists( $key, $ghu_unset_keys ) &&
			       array_key_exists( $key, $ghu_tokens ) )
			     || ( array_key_exists( $repo, $ghu_tokens )
			          && false !== strpos( $key, 'current_branch' ) )
			) {
				unset( $ghu_unset_keys[ $key ] );
			}
		}, $ghu_unset_keys );

		if ( ! empty( $ghu_unset_keys ) ) {
			foreach ( $ghu_unset_keys as $key => $value ) {
				unset( parent::$options [ $key ] );
			}
			update_site_option( 'github_updater', parent::$options );
		}
	}

	/**
	 * Check to see if it's a private repo and set variables.
	 *
	 * @param $token
	 */
	private function set_auth_required( $token ) {

		// Set booleans for Enterprise headers.
		if ( $token->enterprise ) {
			if ( ! parent::$auth_required['github_enterprise'] &&
			     false !== strpos( $token->type, 'github' )

			) {
				parent::$auth_required['github_enterprise'] = true;
			}

			if ( ! parent::$auth_required['gitlab_enterprise'] &&
			     false !== strpos( $token->type, 'gitlab' )
			) {
				parent::$auth_required['gitlab_enterprise'] = true;
			}

			if ( ! parent::$auth_required['bitbucket_server'] &&
			     false !== strpos( $token->type, 'bitbucket' )
			) {
				parent::$auth_required['bitbucket_server'] = true;
			}
		}

		if ( $this->is_private( $token ) ) {
			if ( ! parent::$auth_required['github_private'] &&
			     false !== strpos( $token->type, 'github' )
			) {
				parent::$auth_required['github_private'] = true;
			}
			if ( ! parent::$auth_required['bitbucket_private'] &&
			     false !== strpos( $token->type, 'bitbucket' )
			) {
				parent::$auth_required['bitbucket_private'] = true;
			}
			if ( ! parent::$auth_required['gitlab_private'] &&
			     false !== strpos( $token->type, 'gitlab' )
			) {
				parent::$auth_required['gitlab_private'] = true;
			}
		}

		if ( empty( $token->enterprise ) &&
		     ! parent::$auth_required['gitlab'] &&
		     false !== strpos( $token->type, 'gitlab' )
		) {
			parent::$auth_required['gitlab'] = true;
		}
	}

	/**
	 * Settings for Remote Management.
	 */
	public function remote_management_page_init() {
		register_setting(
			'github_updater_remote_management',
			'github_updater_remote_settings',
			array( &$this, 'sanitize' )
		);

		add_settings_section(
			'remote_management',
			esc_html__( 'Remote Management', 'github-updater' ),
			array( &$this, 'print_section_remote_management' ),
			'github_updater_remote_settings'
		);

		foreach ( self::$remote_management as $id => $name ) {
			add_settings_field(
				$id,
				null,
				array( &$this, 'token_callback_checkbox_remote' ),
				'github_updater_remote_settings',
				'remote_management',
				array( 'id' => $id, 'title' => esc_html( $name ) )
			);
		}

		$this->update_settings();
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
		foreach ( array_keys( (array) $input ) as $id ) {
			$new_input[ sanitize_file_name( $id ) ] = sanitize_text_field( $input[ $id ] );
		}

		return $new_input;
	}

	/**
	 * Print the GitHub Updater text.
	 */
	public function print_section_ghu_settings() {
		if ( $this->is_override_dot_org() ) {
			printf( esc_html__( 'Override Dot Org is %sactive%s.', 'github-updater' ), '<strong>', '</strong>' );
		} else {
			printf( esc_html__( 'Override Dot Org is %snot active%s.', 'github-updater' ), '<strong>', '</strong>' );
		}
		print( '<br>' . esc_html__( 'Override Dot Org will skip any updates from wordpress.org for plugins with identical slugs.', 'github-updater' ) );
		printf( '<br>' . esc_html__( 'Activate Override Dot Org by setting %s', 'github-updater' ), '<code>define( \'GITHUB_UPDATER_OVERRIDE_DOT_ORG\', true );</code>' );

		print( '<p>' . esc_html__( 'Check to enable branch switching from the Plugins or Themes page.', 'github-updater' ) . '</p>' );
	}

	/**
	 * Print the GitHub text.
	 */
	public function print_section_github_info() {
		esc_html_e( 'Enter your GitHub Access Token. Leave empty for public repositories.', 'github-updater' );
	}

	/**
	 * Print the GitHub Personal Access Token text.
	 */
	public function print_section_github_access_token() {
		esc_html_e( 'Enter your personal GitHub.com or GitHub Enterprise Access Token to avoid API access limits.', 'github-updater' );
	}

	/**
	 * Print the Remote Management text.
	 */
	public function print_section_remote_management() {
		$api_url = add_query_arg( array(
			'action' => 'github-updater-update',
			'key'    => self::$api_key,
		), admin_url( 'admin-ajax.php' ) );

		?>
		<p>
			<?php esc_html_e( 'Please refer to README for complete list of attributes. RESTful endpoints begin at:', 'github-updater' ); ?>
			<br>
			<span style="font-family:monospace;"><?php echo $api_url ?></span>
		<p>
			<?php esc_html_e( 'Use of Remote Management services may result increase some page load speeds only for `admin` level users in the dashboard.', 'github-updater' ); ?>
		</p>
		<?php
	}

	/**
	 * Get the settings option array and print one of its values.
	 *
	 * @param $args
	 */
	public function token_callback_text( $args ) {
		$name = isset( parent::$options[ $args['id'] ] ) ? esc_attr( parent::$options[ $args['id'] ] ) : '';
		$type = isset( $args['token'] ) ? 'password' : 'text';
		?>
		<label for="<?php esc_attr( $args['id'] ); ?>">
			<input class="ghu-callback-text" type="<?php esc_attr_e( $type ); ?>" name="github_updater[<?php esc_attr_e( $args['id'] ); ?>]" value="<?php esc_attr_e( $name ); ?>">
		</label>
		<?php
	}

	/**
	 * Get the settings option array and print one of its values.
	 *
	 * @param $args
	 */
	public function token_callback_checkbox( $args ) {
		$checked = isset( parent::$options[ $args['id'] ] ) ? parent::$options[ $args['id'] ] : null;
		?>
		<label for="<?php esc_attr_e( $args['id'] ); ?>">
			<input type="checkbox" name="github_updater[<?php esc_attr_e( $args['id'] ); ?>]" value="1" <?php checked( '1', $checked ); ?> >
			<?php echo $args['title']; ?>
		</label>
		<?php
	}

	/**
	 * Get the settings option array and print one of its values.
	 * For remote management settings.
	 *
	 * @param $args
	 *
	 * @return bool|void
	 */
	public function token_callback_checkbox_remote( $args ) {
		$checked = isset( parent::$options_remote[ $args['id'] ] ) ? parent::$options_remote[ $args['id'] ] : null;
		?>
		<label for="<?php esc_attr_e( $args['id'] ); ?>">
			<input type="checkbox" name="github_updater_remote_management[<?php esc_attr_e( $args['id'] ); ?>]" value="1" <?php checked( '1', $checked ); ?> >
			<?php echo $args['title']; ?>
		</label>
		<?php
	}

	/**
	 * Update settings for single site or network activated.
	 *
	 * @link http://wordpress.stackexchange.com/questions/64968/settings-api-in-multisite-missing-update-message
	 * @link http://benohead.com/wordpress-network-wide-plugin-settings/
	 */
	public function update_settings() {
		if ( isset( $_POST['option_page'] ) ) {
			if ( 'github_updater' === $_POST['option_page'] ) {
				$options = $this->filter_options();
				update_site_option( 'github_updater', self::sanitize( $options ) );
			}
			if ( 'github_updater_remote_management' === $_POST['option_page'] ) {
				update_site_option( 'github_updater_remote_management', (array) self::sanitize( $_POST['github_updater_remote_management'] ) );
			}
		}
		$this->redirect_on_save();
	}

	/**
	 * Filter options to remove unchecked checkbox options.
	 *
	 * @access private
	 * @return array|mixed
	 */
	private function filter_options() {
		$options = parent::$options;

		// Remove checkbox options.
		$options = array_filter( $options, function( $e ) {
			return $e !== '1';
		} );

		$options = array_merge( $options, $_POST['github_updater'] );

		return $options;
	}

	/**
	 * Redirect to correct Settings tab on Save.
	 */
	protected function redirect_on_save() {
		$update             = false;
		$refresh_transients = $this->refresh_transients();
		$reset_api_key      = $this->reset_api_key();
		$option_page        = array( 'github_updater', 'github_updater_remote_management' );

		if ( ( isset( $_POST['action'] ) && 'update' === $_POST['action'] ) &&
		     ( isset( $_POST['option_page'] ) && in_array( $_POST['option_page'], $option_page, true ) )

		) {
			$update = true;
		}

		$redirect_url = is_multisite() ? network_admin_url( 'settings.php' ) : admin_url( 'options-general.php' );

		if ( $update || $refresh_transients || $reset_api_key ) {
			$query = isset( $_POST['_wp_http_referer'] ) ? parse_url( $_POST['_wp_http_referer'], PHP_URL_QUERY ) : null;
			parse_str( $query, $arr );
			$arr['tab']    = ! empty( $arr['tab'] ) ? $arr['tab'] : 'github_updater_settings';
			$arr['subtab'] = ! empty( $arr['subtab'] ) ? $arr['subtab'] : 'github_updater';

			$location = add_query_arg(
				array(
					'page'               => 'github-updater',
					'tab'                => $arr['tab'],
					'subtab'             => $arr['subtab'],
					'refresh_transients' => $refresh_transients,
					'reset'              => $reset_api_key,
					'updated'            => $update,
				),
				$redirect_url
			);
			wp_redirect( $location );
			exit;
		}
	}

	/**
	 * Reset RESTful API key.
	 * Deleting site option will cause it to be re-created.
	 *
	 * @return bool
	 */
	private function reset_api_key() {
		if ( isset( $_REQUEST['tab'], $_REQUEST['github_updater_reset_api_key'] ) &&
		     'github_updater_remote_management' === $_REQUEST['tab']
		) {
			$_POST                     = $_REQUEST;
			$_POST['_wp_http_referer'] = $_SERVER['HTTP_REFERER'];
			delete_site_option( 'github_updater_api_key' );

			return true;
		}

		return false;
	}

	/**
	 * Clear GitHub Updater transients.
	 *
	 * @return bool
	 */
	private function refresh_transients() {
		if ( isset( $_REQUEST['github_updater_refresh_transients'] ) ) {
			$_POST = $_REQUEST;

			return true;
		}

		return false;
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
		$link          = array( '<a href="' . esc_url( network_admin_url( $settings_page ) ) . '?page=github-updater">' . esc_html__( 'Settings', 'github-updater' ) . '</a>' );

		return array_merge( $links, $link );
	}

	/**
	 * Create settings sections that are hidden.
	 * Required to preserve subtab settings during saves.
	 *
	 * @param array $subtab Subtab to display
	 */
	private function add_hidden_settings_sections( $subtab = array() ) {
		$subtabs   = array_keys( $this->settings_sub_tabs() );
		$hide_tabs = array_diff( $subtabs, (array) $subtab, array( 'github_updater' ) );
		if ( ! empty( $subtab ) ) {
			echo '<div id="github_updater" class="hide-github-updater-settings">';
			do_settings_sections( 'github_updater_install_settings' );
			echo '</div>';
		}
		foreach ( $hide_tabs as $hide_tab ) {
			echo '<div id="' . $hide_tab . '" class="hide-github-updater-settings">';
			do_settings_sections( 'github_updater_' . $hide_tab . '_install_settings' );
			echo '</div>';
		}
	}

	/**
	 * Write out listing of installed plugins and themes using GitHub Updater.
	 * Places a lock dashicon after the repo name if it's a private repo.
	 * Places a WordPress dashicon after the repo name if it's in dot org.
	 *
	 * @param $type
	 */
	private function display_ghu_repos( $type ) {
		$plugins  = Singleton::get_instance( 'Plugin' )->get_plugin_configs();
		$themes   = Singleton::get_instance( 'Theme' )->get_theme_configs();
		$repos    = array_merge( $plugins, $themes );
		$bbserver = array( 'bitbucket', 'bbserver' );

		$type_repos = array_filter( $repos, function( $e ) use ( $type, $bbserver ) {
			if ( ! empty( $e->enterprise ) && in_array( $type, $bbserver, true ) ) {
				return ( false !== stripos( $e->type, 'bitbucket' ) && 'bbserver' === $type );
			}

			return ( false !== stripos( $e->type, $type ) );
		} );

		$display_data = array_map( function( $e ) {
			return array(
				'type'    => $e->type,
				'repo'    => $e->repo,
				'name'    => $e->name,
				'private' => isset( $e->is_private ) ? $e->is_private : false,
				'broken'  => $e->broken,
				'dot_org' => isset( $e->dot_org ) ? $e->dot_org : false,
			);
		}, $type_repos );

		$lock    = '&nbsp;<span class="dashicons dashicons-lock"></span>';
		$broken  = '&nbsp;<span style="color:#f00;" class="dashicons dashicons-warning"></span>';
		$dot_org = '&nbsp;<span class="dashicons dashicons-wordpress"></span></span>';
		printf( '<h2>' . esc_html__( 'Installed Plugins and Themes', 'github-updater' ) . '</h2>' );
		foreach ( $display_data as $data ) {
			$dashicon   = false !== strpos( $data['type'], 'theme' )
				? '<span class="dashicons dashicons-admin-appearance"></span>&nbsp;&nbsp;'
				: '<span class="dashicons dashicons-admin-plugins"></span>&nbsp;&nbsp;';
			$is_private = $data['private'] ? $lock : null;
			$is_broken  = $data['broken'] ? $broken : null;
			$is_dot_org = $data['dot_org'] ? $dot_org : null;
			printf( '<p>' . $dashicon . $data['name'] . $is_private . $is_broken . $is_dot_org . '</p>' );
		}
	}

}
