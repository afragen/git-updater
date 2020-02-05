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
 * @author  Andy Fragen
 */
class Settings {
	use GHU_Trait;

	/**
	 * Holds the plugin basename.
	 *
	 * @var string
	 */
	private $ghu_plugin_name = 'github-updater/github-updater.php';

	/**
	 * Holds boolean on whether or not the repo requires authentication.
	 *
	 * @var array
	 */
	public static $auth_required = [
		'github_private'    => false,
		'github_enterprise' => false,
		'bitbucket_private' => false,
		'bitbucket_server'  => false,
		'gitlab_private'    => false,
		'gitlab_enterprise' => false,
		'gitea_private'     => false,
	];

	/**
	 * Holds site options.
	 *
	 * @var array $options
	 */
	private static $options;

	/**
	 * Constructor.
	 */
	public function __construct() {
		self::$options = $this->get_class_vars( 'Base', 'options' );
		$this->refresh_caches();
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

		// Need to ensure these classes are activated here for hooks to fire.
		if ( $this->is_current_page( [ 'options.php', 'options-general.php', 'settings.php', 'edit.php' ] ) ) {
			Singleton::get_instance( 'Install', $this )->run();
			Singleton::get_instance( 'Remote_Management', $this )->load_hooks();
		}
	}

	/**
	 * Load relevant action/filter hooks.
	 */
	protected function load_hooks() {
		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', [ $this, 'add_plugin_page' ] );
		add_action( 'network_admin_edit_github-updater', [ $this, 'update_settings' ] );

		add_filter(
			is_multisite()
			? 'network_admin_plugin_action_links_' . $this->ghu_plugin_name
			: 'plugin_action_links_' . $this->ghu_plugin_name,
			[ $this, 'plugin_action_links' ]
		);

		if ( $this->is_current_page( [ 'options.php', 'options-general.php', 'settings.php', 'edit.php' ] ) ) {
			add_action( 'admin_init', [ $this, 'update_settings' ] );
			add_action( 'admin_init', [ $this, 'page_init' ] );

			// Load settings stylesheet.
			add_action(
				'admin_enqueue_scripts',
				function () {
					wp_register_style( 'github-updater-settings', plugins_url( basename( GITHUB_UPDATER_DIR ) ) . '/css/github-updater-settings.css', [], $this->get_plugin_version() );
					wp_enqueue_style( 'github-updater-settings' );
				}
			);
		}

		if ( isset( self::$options['bypass_background_processing'] ) ) {
			add_filter( 'github_updater_disable_wpcron', '__return_true' );
		}

		// Check if filter set elsewhere.
		if ( apply_filters( 'github_updater_disable_wpcron', false ) ) {
			self::$options['bypass_background_processing'] = '1';
		}
	}

	/**
	 * Define tabs for Settings page.
	 * By defining in a method, strings can be translated.
	 *
	 * @access private
	 * @return array
	 */
	private function settings_tabs() {
		$tabs = [ 'github_updater_settings' => esc_html__( 'Settings', 'github-updater' ) ];

		/**
		 * Filter settings tabs.
		 *
		 * @since 8.0.0
		 *
		 * @param array $tabs Array of default tabs.
		 */
		return apply_filters( 'github_updater_add_settings_tabs', $tabs );
	}

	/**
	 * Set up the Settings Sub-tabs.
	 *
	 * @access private
	 * @return array
	 */
	private function settings_sub_tabs() {
		$subtabs = [ 'github_updater' => esc_html__( 'GitHub Updater', 'github-updater' ) ];
		$gits    = $this->get_running_git_servers();
		$gits[]  = in_array( 'gitlabce', $gits, true ) ? 'gitlab' : null;
		$gits    = array_unique( $gits );

		$git_subtab  = [];
		$ghu_subtabs = [];

		/**
		 * Filter subtabs to be able to add subtab from git API class.
		 *
		 * @since 8.0.0
		 *
		 * @param array $ghu_subtabs Array of added subtabs.
		 *
		 * @return array $subtabs Array of subtabs.
		 */
		$ghu_subtabs = apply_filters( 'github_updater_add_settings_subtabs', $ghu_subtabs );

		foreach ( $gits as $git ) {
			if ( array_key_exists( $git, $ghu_subtabs ) ) {
				$git_subtab[ $git ] = $ghu_subtabs[ $git ];
			}
		}
		$subtabs = array_merge( $subtabs, $git_subtab );

		return $subtabs;
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
			esc_html_x( 'GitHub Updater', 'Menu item', 'github-updater' ),
			$capability,
			'github-updater',
			[ $this, 'create_admin_page' ]
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
		$current_tab = isset( $_GET['tab'] ) ? esc_attr( $_GET['tab'] ) : 'github_updater_settings';
		echo '<nav class="nav-tab-wrapper" aria-label="Secondary menu">';
		foreach ( $this->settings_tabs() as $key => $name ) {
			$active = ( $current_tab === $key ) ? 'nav-tab-active' : '';
			echo '<a class="nav-tab ' . $active . '" href="?page=github-updater&tab=' . $key . '">' . $name . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * Render the settings sub-tabs.
	 *
	 * @access private
	 */
	private function options_sub_tabs() {
		$current_tab = isset( $_GET['subtab'] ) ? esc_attr( $_GET['subtab'] ) : 'github_updater';
		echo '<nav class="nav-tab-wrapper" aria-label="Tertiary menu">';
		foreach ( $this->settings_sub_tabs() as $key => $name ) {
			$active = ( $current_tab === $key ) ? 'nav-tab-active' : '';
			echo '<a class="nav-tab ' . $active . '" href="?page=github-updater&tab=github_updater_settings&subtab=' . $key . '">' . $name . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * Options page callback.
	 */
	public function create_admin_page() {
		$action = is_multisite() ? 'edit.php?action=github-updater' : 'options.php';
		$tab    = isset( $_GET['tab'] ) ? esc_attr( $_GET['tab'] ) : 'github_updater_settings';
		$subtab = isset( $_GET['subtab'] ) ? esc_attr( $_GET['subtab'] ) : 'github_updater';
		$logo   = plugins_url( basename( GITHUB_UPDATER_DIR ) . '/assets/GitHub_Updater_logo_small.png' ); ?>
		<div class="wrap github-updater-settings">
			<h1>
				<a href="https://github.com/afragen/github-updater" target="_blank"><img src="<?php esc_attr_e( $logo ); ?>" alt="GitHub Updater logo" /></a><br>
				<?php esc_html_e( 'GitHub Updater', 'github-updater' ); ?>
			</h1>
			<?php $this->options_tabs(); ?>
			<?php $this->admin_page_notices(); ?>
			<?php if ( 'github_updater_settings' === $tab ) : ?>
				<?php $this->options_sub_tabs(); ?>
				<form class="settings" method="post" action="<?php esc_attr_e( $action ); ?>">
					<?php
					settings_fields( 'github_updater' );
					if ( 'github_updater' === $subtab ) {
						do_settings_sections( 'github_updater_install_settings' );
						$this->add_hidden_settings_sections();
					} else {
						do_settings_sections( 'github_updater_' . $subtab . '_install_settings' );
						$this->display_ghu_repos( $subtab );
						$this->add_hidden_settings_sections( $subtab );
					}
					submit_button();
					?>
				</form>
				<?php $refresh_transients = add_query_arg( [ 'github_updater_refresh_transients' => true ], $action ); ?>
				<form class="settings" method="post" action="<?php esc_attr_e( $refresh_transients ); ?>">
					<?php submit_button( esc_html__( 'Refresh Cache', 'github-updater' ), 'primary', 'ghu_refresh_cache' ); ?>
				</form>
			<?php endif; ?>

			<?php
			/**
			 * Action hook to add admin page data to appropriate $tab.
			 *
			 * @since 8.0.0
			 *
			 * @param string $tab    Name of tab.
			 * @param string $action Save action for appropriate WordPress installation.
			 *                       Single site or Multisite.
			 */
			do_action( 'github_updater_add_admin_page', $tab, $action );
			?>
		</div>
		<?php
	}

	/**
	 * Display appropriate notice for Settings page actions.
	 */
	private function admin_page_notices() {
		$display = ( isset( $_GET['updated'] ) && is_multisite() )
				|| isset( $_GET['reset'] )
				|| isset( $_GET['refresh_transients'] );

		if ( $display ) {
			echo '<div class="updated"><p>';
		}
		if ( ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) && is_multisite() ) {
			esc_html_e( 'Settings saved.', 'github-updater' );
		} elseif ( isset( $_GET['reset'] ) && '1' === $_GET['reset'] ) {
			esc_html_e( 'RESTful key reset.', 'github-updater' );
		} elseif ( isset( $_GET['refresh_transients'] ) && '1' === $_GET['refresh_transients'] ) {
			esc_html_e( 'Cache refreshed.', 'github-updater' );
		}
		if ( $display ) {
			echo '</p></div>';
		}
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
			[ $this, 'sanitize' ]
		);

		$this->ghu_tokens();

		/*
		 * Add basic plugin settings.
		 */
		add_settings_section(
			'github_updater_settings',
			esc_html__( 'GitHub Updater Settings', 'github-updater' ),
			[ $this, 'print_section_ghu_settings' ],
			'github_updater_install_settings'
		);

		add_settings_field(
			'branch_switch',
			null,
			[ $this, 'token_callback_checkbox' ],
			'github_updater_install_settings',
			'github_updater_settings',
			[
				'id'    => 'branch_switch',
				'title' => esc_html__( 'Enable Branch Switching', 'github-updater' ),
			]
		);

		add_settings_field(
			'bypass_background_processing',
			null,
			[ $this, 'token_callback_checkbox' ],
			'github_updater_install_settings',
			'github_updater_settings',
			[
				'id'    => 'bypass_background_processing',
				'title' => esc_html__( 'Bypass WP-Cron Background Processing for Debugging', 'github-updater' ),
			]
		);

		/**
		 * Hook to add Git API settings.
		 *
		 * @since 8.0.0
		 *
		 * @param array $auth_required Array containing authorization needs of git APIs.
		 */
		do_action( 'github_updater_add_settings', static::$auth_required );
	}

	/**
	 * Create and return settings fields for private repositories.
	 */
	public function ghu_tokens() {
		$ghu_options_keys = [];
		$ghu_plugins      = Singleton::get_instance( 'Plugin', $this )->get_plugin_configs();
		$ghu_themes       = Singleton::get_instance( 'Theme', $this )->get_theme_configs();
		$ghu_tokens       = array_merge( $ghu_plugins, $ghu_themes );

		foreach ( $ghu_tokens as $token ) {
			$type                             = '<span class="dashicons dashicons-admin-plugins"></span>&nbsp;';
			$setting_field                    = [];
			$ghu_options_keys[ $token->slug ] = null;

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

			if ( 'theme' === $token->type ) {
				$type = '<span class="dashicons dashicons-admin-appearance"></span>&nbsp;';
			}

			$setting_field['id']    = $token->slug;
			$setting_field['title'] = $type . esc_html( $token->name );

			$repo_setting_field = apply_filters( 'github_updater_add_repo_setting_field', [], $token, $token->git );

			if ( empty( $repo_setting_field ) ) {
				continue;
			}

			$setting_field             = array_merge( $setting_field, $repo_setting_field );
			$setting_field['callback'] = $token->slug;

			$title = 'token_callback_checkbox' !== $setting_field['callback_method'][1] ? $setting_field['title'] : null;
			add_settings_field(
				$setting_field['id'],
				$title,
				$setting_field['callback_method'],
				$setting_field['page'],
				$setting_field['section'],
				[
					'id'    => $setting_field['callback'],
					'token' => true,
					'title' => $setting_field['title'],
				]
			);
		}

		if ( ! $this->waiting_for_background_update() ) {
			$this->unset_stale_options( $ghu_options_keys, $ghu_tokens );
		} else {
			Singleton::get_instance( 'Messages', $this )->create_error_message( 'waiting' );
		}
	}

	/**
	 * Check current saved options and unset if repos not present.
	 *
	 * @param array $ghu_options_keys
	 * @param array $ghu_tokens
	 */
	public function unset_stale_options( $ghu_options_keys, $ghu_tokens ) {
		$running_servers = $this->get_running_git_servers();
		$ghu_unset_keys  = array_diff_key( self::$options, $ghu_options_keys );
		$always_unset    = [
			'db_version',
			'branch_switch',
			'bypass_background_processing',
			'github_access_token',
			'github_enterprise_token',
		];

		if ( in_array( 'bitbucket', $running_servers, true ) ) {
			$always_unset = array_merge(
				$always_unset,
				[
					'bitbucket_username',
					'bitbucket_password',
				]
			);
		}

		if ( in_array( 'bbserver', $running_servers, true ) ) {
			$always_unset = array_merge(
				$always_unset,
				[
					'bitbucket_server_username',
					'bitbucket_server_password',
				]
			);
		}

		array_map(
			function ( $e ) use ( &$ghu_unset_keys ) {
				unset( $ghu_unset_keys[ $e ] );
			},
			$always_unset
		);

		$auth_required       = static::$auth_required;
		$auth_required_unset = [
			'github_enterprise' => 'github_enterprise_token',
			'gitlab'            => 'gitlab_access_token',
			'gitlab_enterprise' => 'gitlab_enterprise_token',
			'gitea'             => 'gitea_access_token',
		];

		array_map(
			function ( $e ) use ( &$ghu_unset_keys, $auth_required, $auth_required_unset ) {
				$key = array_search( $e, $auth_required_unset, true );
				if ( $auth_required[ $key ] ) {
					unset( $ghu_unset_keys[ $e ] );
				}
			},
			$auth_required_unset
		);

		// Unset if current_branch AND if associated with repo.
		array_map(
			function ( $e ) use ( &$ghu_unset_keys, $ghu_tokens, &$reset_keys ) {
				$key  = array_search( $e, $ghu_unset_keys, true );
				$repo = str_replace( 'current_branch_', '', $key );
				if ( array_key_exists( $key, $ghu_unset_keys )
				&& false !== strpos( $key, 'current_branch' )
				) {
					unset( $ghu_unset_keys[ $key ] );
				}
				if ( ! array_key_exists( $repo, $ghu_tokens ) ) {
					$reset_keys[ $key ] = $e;
				}
			},
			$ghu_unset_keys
		);
		$ghu_unset_keys = array_merge( $ghu_unset_keys, (array) $reset_keys );

		if ( ! empty( $ghu_unset_keys ) ) {
			foreach ( $ghu_unset_keys as $key => $value ) {
				unset( self::$options[ $key ] );
			}
			update_site_option( 'github_updater', self::$options );
		}
	}

	/**
	 * Check to see if it's an enterprise or private repo and set variables.
	 *
	 * @param \stdClass $token Repo data.
	 */
	private function set_auth_required( $token ) {
		// Set booleans for Enterprise repos.
		if ( $token->enterprise ) {
			static::$auth_required['github_enterprise'] = static::$auth_required['github_enterprise']
				?: 'github' === $token->git;
			static::$auth_required['gitlab_enterprise'] = static::$auth_required['gitlab_enterprise']
				?: 'gitlab' === $token->git;
			static::$auth_required['bitbucket_server']  = static::$auth_required['bitbucket_server']
				?: 'bitbucket' === $token->git;
		}

		// Set booleans for private repos.
		if ( $this->is_private( $token ) ) {
			static::$auth_required['github_private']    = static::$auth_required['github_private']
				?: 'github' === $token->git;
			static::$auth_required['bitbucket_private'] = static::$auth_required['bitbucket_private']
				?: 'bitbucket' === $token->git;
			static::$auth_required['gitlab_private']    = static::$auth_required['gitlab_private']
				?: 'gitlab' === $token->git;
			static::$auth_required['gitea_private']     = static::$auth_required['gitea_private']
				?: 'gitea' === $token->git;
		}

		// Always set to true.
		static::$auth_required['gitlab'] = true;
		static::$auth_required['gitea']  = true;
	}

	/**
	 * Print the GitHub Updater Settings text.
	 */
	public function print_section_ghu_settings() {
		$this->display_dot_org_overrides();
		echo '<p>' . esc_html__( 'Check to enable branch switching from the Plugins or Themes page or to bypass WP-Cron background processing.', 'github-updater' ) . '</p>';
	}

	/**
	 * Display plugins/themes that are overridden using the filter hook.
	 *
	 * @uses `github_updater_override_dot_org` filter hook
	 * @return void
	 */
	private function display_dot_org_overrides() {
		$plugins         = Singleton::get_instance( 'Plugin', $this )->get_plugin_configs();
		$themes          = Singleton::get_instance( 'Theme', $this )->get_theme_configs();
		$dashicon_plugin = '<span class="dashicons dashicons-admin-plugins"></span>&nbsp;&nbsp;';
		$dashicon_theme  = '<span class="dashicons dashicons-admin-appearance"></span>&nbsp;&nbsp;';

		/**
		 * Filter to return array of overrides to dot org.
		 *
		 * @since 8.5.0
		 * @return array
		 */
		$overrides = apply_filters( 'github_updater_override_dot_org', [] );

		if ( ! empty( $overrides ) ) {
			echo '<h4>' . esc_html__( 'Overridden Plugins and Themes', 'github-updater' ) . '</h4>';
			echo '<p>' . esc_html__( 'The following plugins or themes might exist on wp.org, but any updates will be downloaded from their respective git repositories.', 'github-updater' ) . '</p>';

			foreach ( $plugins as $plugin ) {
				if ( in_array( $plugin->file, $overrides, true ) ) {
					echo '<p>' . $dashicon_plugin . $plugin->name . '</p>';
				}
			}
			foreach ( $themes as $theme ) {
				if ( in_array( $theme->slug, $overrides, true ) ) {
					echo '<p>' . $dashicon_theme . $theme->name . '</p>';
				}
			}
			echo '<br>';
		}
	}

	/**
	 * Get the settings option array and print one of its values.
	 *
	 * @param array $args
	 */
	public function token_callback_text( $args ) {
		$name = isset( self::$options[ $args['id'] ] ) ? esc_attr( self::$options[ $args['id'] ] ) : '';
		$type = isset( $args['token'] ) ? 'password' : 'text';
		?>
		<label for="<?php esc_attr( $args['id'] ); ?>">
			<input class="ghu-callback-text" type="<?php esc_attr_e( $type ); ?>" id="<?php esc_attr( $args['id'] ); ?>" name="github_updater[<?php esc_attr_e( $args['id'] ); ?>]" value="<?php esc_attr_e( $name ); ?>">
		</label>
		<?php
	}

	/**
	 * Get the settings option array and print one of its values.
	 *
	 * @param array $args
	 */
	public function token_callback_checkbox( $args ) {
		$checked = isset( self::$options[ $args['id'] ] ) ? self::$options[ $args['id'] ] : null;
		?>
		<label for="<?php esc_attr_e( $args['id'] ); ?>">
			<input type="checkbox" id="<?php esc_attr_e( $args['id'] ); ?>" name="github_updater[<?php esc_attr_e( $args['id'] ); ?>]" value="1" <?php checked( '1', $checked ); ?> >
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
		if ( isset( $_POST['option_page'] ) &&
			'github_updater' === $_POST['option_page']
		) {
			$options = $this->filter_options();
			update_site_option( 'github_updater', $this->sanitize( $options ) );
		}

		/**
		 * Save $options in add-on classes.
		 *
		 * @since 8.0.0
		 */
		do_action( 'github_updater_update_settings', $_POST );

		$this->redirect_on_save();
	}

	/**
	 * Filter options to remove unchecked checkbox options.
	 *
	 * @access private
	 *
	 * @return array|mixed
	 */
	private function filter_options() {
		$options = self::$options;

		// Remove checkbox options, only after background update complete or when bypassing background processing.
		if ( ! $this->waiting_for_background_update() || isset( $options['bypass_background_processing'] ) ) {
			$options = array_filter(
				$options,
				function ( $e ) {
					return '1' !== $e;
				}
			);
		}

		$options = array_merge( $options, $_POST['github_updater'] );

		return $options;
	}

	/**
	 * Redirect to correct Settings tab on Save.
	 */
	protected function redirect_on_save() {
		$update             = false;
		$refresh_transients = $this->refresh_transients();
		$reset_api_key      = Singleton::get_instance( 'Remote_Management', $this )->reset_api_key();

		/**
		 * Filter to add to $option_page array.
		 *
		 * @since 8.0.0
		 * @return array
		 */
		$option_page = apply_filters( 'github_updater_save_redirect', [ 'github_updater' ] );

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
				[
					'page'               => 'github-updater',
					'tab'                => $arr['tab'],
					'subtab'             => $arr['subtab'],
					'refresh_transients' => $refresh_transients,
					'reset'              => $reset_api_key,
					'updated'            => $update,
				],
				$redirect_url
			);
			wp_safe_redirect( $location );
			exit;
		}
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
	 * @param array $links
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$settings_page = is_multisite() ? 'settings.php' : 'options-general.php';
		$link          = [ '<a href="' . esc_url( network_admin_url( $settings_page ) ) . '?page=github-updater">' . esc_html__( 'Settings', 'github-updater' ) . '</a>' ];

		return array_merge( $links, $link );
	}

	/**
	 * Create settings sections that are hidden.
	 * Required to preserve subtab settings during saves.
	 *
	 * @param array $subtab Subtab to display.
	 */
	private function add_hidden_settings_sections( $subtab = [] ) {
		$subtabs   = array_keys( $this->settings_sub_tabs() );
		$hide_tabs = array_diff( $subtabs, (array) $subtab, [ 'github_updater' ] );
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
	 * @param string $git (github|bitbucket|bbserver|gitlab|gitea)
	 */
	private function display_ghu_repos( $git ) {
		$lock_title    = esc_html__( 'This is a private repository.', 'github-updater' );
		$broken_title  = esc_html__( 'This repository has not connected to the API or was unable to connect.', 'github-updater' );
		$dot_org_title = esc_html__( 'This repository is hosted on WordPress.org.', 'github-updater' );

		$plugins  = Singleton::get_instance( 'Plugin', $this )->get_plugin_configs();
		$themes   = Singleton::get_instance( 'Theme', $this )->get_theme_configs();
		$repos    = array_merge( $plugins, $themes );
		$bbserver = [ 'bitbucket', 'bbserver' ];

		$type_repos = array_filter(
			$repos,
			function ( $e ) use ( $git, $bbserver ) {
				if ( ! empty( $e->enterprise ) && in_array( $git, $bbserver, true ) ) {
					return false !== stripos( $e->git, 'bitbucket' ) && 'bbserver' === $git;
				}

				return false !== stripos( $e->git, $git );
			}
		);

		$display_data = array_map(
			function ( $e ) {
				return [
					'type'    => $e->type,
					'slug'    => $e->slug,
					'file'    => isset( $e->file ) ? $e->file : $e->slug,
					'branch'  => $e->branch,
					'name'    => $e->name,
					'private' => isset( $e->is_private ) ? $e->is_private : false,
					'broken'  => ! isset( $e->remote_version ) || '0.0.0' === $e->remote_version,
					'dot_org' => isset( $e->dot_org ) ? $e->dot_org : false,
				];
			},
			$type_repos
		);

		$lock    = '&nbsp;<span title="' . $lock_title . '" class="dashicons dashicons-lock"></span>';
		$broken  = '&nbsp;<span title="' . $broken_title . '" style="color:#f00;" class="dashicons dashicons-warning"></span>';
		$dot_org = '&nbsp;<span title="' . $dot_org_title . '" class="dashicons dashicons-wordpress"></span></span>';
		printf( '<h2>' . esc_html__( 'Installed Plugins and Themes', 'github-updater' ) . '</h2>' );
		foreach ( $display_data as $data ) {
			$dashicon   = false !== strpos( $data['type'], 'theme' )
				? '<span class="dashicons dashicons-admin-appearance"></span>&nbsp;&nbsp;'
				: '<span class="dashicons dashicons-admin-plugins"></span>&nbsp;&nbsp;';
			$is_private = $data['private'] ? $lock : null;
			$is_broken  = $data['broken'] ? $broken : null;
			$override   = $this->override_dot_org( $data['type'], $data );
			$is_dot_org = $data['dot_org'] && ! $override ? $dot_org : null;
			printf( '<p>' . $dashicon . $data['name'] . $is_private . $is_dot_org . $is_broken . '</p>' );
		}
	}
}
