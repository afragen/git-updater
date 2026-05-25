<?php
/**
 * Tests for Install class.
 *
 * Covers 100% line coverage of src/Git_Updater/Install.php:
 * - __construct()
 * - run()
 * - load_js()
 * - add_settings_tabs()
 * - add_admin_page()
 * - install()
 * - save_options_on_install()  (private, via install())
 * - get_upgrader()             (private, via install())
 * - create_form()
 * - register_settings()
 * - get_repo()
 * - branch()
 * - install_api()
 * - install_theme_complete_actions()
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\Install;
use Fragen\Git_Updater\Plugin;
use Fragen\Git_Updater\Theme;
use Fragen\Singleton;

// class-wp-upgrader.php is already loaded by plugin bootstrap (via REST_API → Rest_Update.php).
// This require_once is a no-op at runtime but ensures WP_Upgrader_Skin is available
// when Silent_Upgrader_Skin is defined below.
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

/**
 * Silent upgrader skin: suppresses all HTML output from the upgrader machinery.
 *
 * Used via the gu_get_upgrader_skin filter in Test_Install_Install so that
 * WP_Upgrader_Skin::feedback() — which calls show_message() → wp_ob_end_flush_all()
 * and cascades every PHP output buffer to stdout — is never invoked.
 */
if ( ! class_exists( 'Silent_Upgrader_Skin' ) ) {
	class Silent_Upgrader_Skin extends WP_Upgrader_Skin { // phpcs:ignore
		public function header() {}
		public function footer() {}
		public function error( $errors ) {}
		public function feedback( $feedback, ...$args ) {}
	}
}

// ---------------------------------------------------------------------------
// Shared helper trait
// ---------------------------------------------------------------------------

trait Install_Test_Helper {

	/**
	 * Inject Install::$install (protected static).
	 *
	 * @param array<string, mixed> $data
	 * @return void
	 */
	private function inject_install_static( array $data ): void {
		$rp = new ReflectionProperty( Install::class, 'install' );
		$rp->setAccessible( true );
		$rp->setValue( null, $data );
	}

	/**
	 * Read Install::$install (protected static).
	 *
	 * @return array<string, mixed>
	 */
	private function read_install_static(): array {
		$rp = new ReflectionProperty( Install::class, 'install' );
		$rp->setAccessible( true );
		return $rp->getValue( null ) ?? [];
	}

	/**
	 * Create a minimal valid plugin zip for upgrader success tests.
	 *
	 * @param string $slug Plugin slug.
	 * @return string Absolute path to the zip file.
	 */
	private function make_minimal_plugin_zip( string $slug ): string {
		$zip_path = sys_get_temp_dir() . "/{$slug}.zip";
		$zip      = new ZipArchive();
		$zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		$php = "<?php\n/**\n * Plugin Name: {$slug}\n * Version: 1.0.0\n * Description: Test plugin.\n * Author: Test\n */\n";
		$zip->addFromString( "{$slug}/{$slug}.php", $php );
		$zip->close();
		return $zip_path;
	}

	/**
	 * Remove an installed plugin directory.
	 *
	 * @param string $slug Plugin slug (directory name under WP_PLUGIN_DIR).
	 * @return void
	 */
	private function delete_installed_plugin( string $slug ): void {
		$plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
		if ( is_dir( $plugin_dir ) ) {
			$this->remove_directory_recursive( $plugin_dir );
		}
	}

	/**
	 * Recursively remove a directory.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private function remove_directory_recursive( string $dir ): void {
		$files = glob( $dir . '/*' );
		if ( $files ) {
			foreach ( $files as $file ) {
				if ( is_dir( $file ) ) {
					$this->remove_directory_recursive( $file );
				} else {
					unlink( $file );
				}
			}
		}
		rmdir( $dir );
	}

	/**
	 * Unregister settings registered by register_settings().
	 *
	 * @param string $type plugin|theme.
	 * @return void
	 */
	private function cleanup_registered_settings( string $type ): void {
		unregister_setting( 'git_updater_install', 'git_updater_install_' . $type );
		unset( $GLOBALS['wp_settings_sections'][ 'git_updater_install_' . $type ] );
		unset( $GLOBALS['wp_settings_fields'][ 'git_updater_install_' . $type ] );
	}
}

// ---------------------------------------------------------------------------
// Test_Install_Constructor
// ---------------------------------------------------------------------------

/**
 * Class Test_Install_Constructor
 */
class Test_Install_Constructor extends WP_UnitTestCase {
	use Install_Test_Helper;

	/**
	 * @return void
	 */
	public function test_constructor_sets_options(): void {
		$install = new Install();

		$rp_options = new ReflectionProperty( Install::class, 'options' );
		$rp_options->setAccessible( true );
		$this->assertNotNull( $rp_options->getValue( null ) );

		$rp_apis = new ReflectionProperty( Install::class, 'installed_apis' );
		$rp_apis->setAccessible( true );
		$this->assertNotNull( $rp_apis->getValue( null ) );

		$rp_servers = new ReflectionProperty( Install::class, 'git_servers' );
		$rp_servers->setAccessible( true );
		$this->assertIsArray( $rp_servers->getValue( null ) );
	}
}

// ---------------------------------------------------------------------------
// Test_Install_Run
// ---------------------------------------------------------------------------

/**
 * Class Test_Install_Run
 */
class Test_Install_Run extends WP_UnitTestCase {
	use Install_Test_Helper;

	/** @var Install */
	private Install $install;

	/**
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->install = new Install();
	}

	/**
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_actions( 'admin_enqueue_scripts' );
		remove_all_filters( 'gu_add_settings_tabs' );
		remove_all_actions( 'gu_add_admin_page' );
		$GLOBALS['current_screen'] = null;
		parent::tear_down();
	}

	/**
	 * @return void
	 */
	public function test_run_registers_hooks_and_loads_upgrader(): void {
		$this->install->run();

		$this->assertGreaterThan( 0, has_action( 'admin_enqueue_scripts' ) );
		$this->assertGreaterThan( 0, has_filter( 'gu_add_settings_tabs' ) );
		$this->assertGreaterThan( 0, has_action( 'gu_add_admin_page' ) );
		$this->assertTrue( class_exists( 'Plugin_Upgrader' ) );
	}
}

// ---------------------------------------------------------------------------
// Test_Install_LoadJs
// ---------------------------------------------------------------------------

/**
 * Class Test_Install_LoadJs
 */
class Test_Install_LoadJs extends WP_UnitTestCase {
	use Install_Test_Helper;

	/** @var Install */
	private Install $install;

	/**
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->install = new Install();
		wp_deregister_script( 'gu-install' );
	}

	/**
	 * @return void
	 */
	public function tear_down(): void {
		wp_deregister_script( 'gu-install' );
		remove_all_actions( 'admin_enqueue_scripts' );
		$GLOBALS['current_screen'] = null;
		parent::tear_down();
	}

	/**
	 * @return void
	 */
	public function test_load_js_enqueues_script(): void {
		$this->install->load_js();

		set_current_screen( 'plugins' );
		do_action( 'admin_enqueue_scripts' );

		$this->assertTrue( wp_script_is( 'gu-install', 'registered' ) );
	}
}

// ---------------------------------------------------------------------------
// Test_Install_AddSettingsTabs
// ---------------------------------------------------------------------------

/**
 * Class Test_Install_AddSettingsTabs
 */
class Test_Install_AddSettingsTabs extends WP_UnitTestCase {
	use Install_Test_Helper;

	/** @var Install */
	private Install $install;

	/** @var array<string, mixed> */
	private array $saved_install = [];

	/** @var int */
	private int $admin_id = 0;

	/**
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_id );
		// grant_super_admin() is a no-op on single-site; on multisite it grants install_plugins/themes.
		grant_super_admin( $this->admin_id );
		$this->install        = new Install();
		$this->saved_install  = $this->read_install_static();
	}

	/**
	 * @return void
	 */
	public function tear_down(): void {
		revoke_super_admin( $this->admin_id );
		wp_set_current_user( 0 );
		$this->inject_install_static( $this->saved_install );
		remove_all_filters( 'gu_add_settings_tabs' );
		remove_all_actions( 'gu_add_admin_page' );
		$this->cleanup_registered_settings( 'plugin' );
		$this->cleanup_registered_settings( 'theme' );
		parent::tear_down();
	}

	/**
	 * Filter closure body: merges install tabs into the tabs array.
	 *
	 * @return void
	 */
	public function test_filter_closure_adds_install_tabs(): void {
		$this->install->add_settings_tabs();

		$tabs = apply_filters( 'gu_add_settings_tabs', [] );

		$this->assertArrayHasKey( 'git_updater_install_plugin', $tabs );
		$this->assertArrayHasKey( 'git_updater_install_theme', $tabs );
	}

	/**
	 * Action closure body: calls add_admin_page() when gu_add_admin_page fires.
	 *
	 * @return void
	 */
	public function test_action_closure_calls_add_admin_page(): void {
		$this->install->add_settings_tabs();

		// Fire with a tab that matches no if-block so we only cover the closure body.
		// Pass 2 args — Additions\Settings::load_hooks() registers the same action with accepted_args=2.
		ob_start();
		do_action( 'gu_add_admin_page', 'unknown_tab', '' );
		ob_end_clean();

		// Reaching here means the closure ran without error.
		$this->assertTrue( true );
	}
}

// ---------------------------------------------------------------------------
// Test_Install_AddAdminPage
// ---------------------------------------------------------------------------

/**
 * Class Test_Install_AddAdminPage
 */
class Test_Install_AddAdminPage extends WP_UnitTestCase {
	use Install_Test_Helper;

	/** @var Install */
	private Install $install;

	/** @var array<string, mixed> */
	private array $saved_install = [];

	/**
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$this->install       = new Install();
		$this->saved_install = $this->read_install_static();
		unset( $_POST['option_page'] );
	}

	/**
	 * @return void
	 */
	public function tear_down(): void {
		$this->inject_install_static( $this->saved_install );
		$this->cleanup_registered_settings( 'plugin' );
		$this->cleanup_registered_settings( 'theme' );
		remove_all_filters( 'gu_running_git_servers' );
		unset( $_POST['option_page'] );
		parent::tear_down();
	}

	/**
	 * Plugin tab branch: install() returns true (no POST) and create_form() outputs form.
	 *
	 * @return void
	 */
	public function test_plugin_tab_outputs_form(): void {
		ob_start();
		$this->install->add_admin_page( 'git_updater_install_plugin' );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<form', $output );
		$this->assertStringContainsString( 'Install Plugin', $output );
	}

	/**
	 * Theme tab branch: install() returns true (no POST) and create_form() outputs form.
	 *
	 * @return void
	 */
	public function test_theme_tab_outputs_form(): void {
		ob_start();
		$this->install->add_admin_page( 'git_updater_install_theme' );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<form', $output );
		$this->assertStringContainsString( 'Install Theme', $output );
	}
}

// ---------------------------------------------------------------------------
// Test_Install_CreateForm
// ---------------------------------------------------------------------------

/**
 * Class Test_Install_CreateForm
 */
class Test_Install_CreateForm extends WP_UnitTestCase {
	use Install_Test_Helper;

	/** @var Install */
	private Install $install;

	/**
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/template.php';
		$this->install = new Install();
		unset( $_POST['option_page'] );
	}

	/**
	 * @return void
	 */
	public function tear_down(): void {
		$this->cleanup_registered_settings( 'plugin' );
		$this->cleanup_registered_settings( 'theme' );
		remove_all_filters( 'gu_running_git_servers' );
		unset( $_POST['option_page'] );
		parent::tear_down();
	}

	/**
	 * When no POST, form HTML is rendered for plugin type.
	 *
	 * @return void
	 */
	public function test_create_form_plugin_outputs_form(): void {
		ob_start();
		$this->install->create_form( 'plugin' );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<form', $output );
		$this->assertStringContainsString( 'Install Plugin', $output );
	}

	/**
	 * When no POST, form HTML is rendered for theme type.
	 *
	 * @return void
	 */
	public function test_create_form_theme_outputs_form(): void {
		ob_start();
		$this->install->create_form( 'theme' );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<form', $output );
		$this->assertStringContainsString( 'Install Theme', $output );
	}

	/**
	 * When POST option_page = git_updater_install, bail early (no output).
	 *
	 * @return void
	 */
	public function test_create_form_bails_when_post_installing(): void {
		$_POST['option_page'] = 'git_updater_install';

		ob_start();
		$this->install->create_form( 'plugin' );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}
}

// ---------------------------------------------------------------------------
// Test_Install_RegisterSettings
// ---------------------------------------------------------------------------

/**
 * Class Test_Install_RegisterSettings
 */
class Test_Install_RegisterSettings extends WP_UnitTestCase {
	use Install_Test_Helper;

	/** @var Install */
	private Install $install;

	/**
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->install = new Install();
	}

	/**
	 * @return void
	 */
	public function tear_down(): void {
		$this->cleanup_registered_settings( 'plugin' );
		$this->cleanup_registered_settings( 'theme' );
		remove_all_filters( 'gu_running_git_servers' );
		remove_all_actions( 'gu_add_install_settings_fields' );
		parent::tear_down();
	}

	/**
	 * Registers setting, section, and core fields for plugin type.
	 *
	 * @return void
	 */
	public function test_register_settings_plugin(): void {
		$this->install->register_settings( 'plugin' );

		$registered = get_registered_settings();
		$this->assertArrayHasKey( 'git_updater_install_plugin', $registered );

		$this->assertArrayHasKey( 'plugin', $GLOBALS['wp_settings_sections']['git_updater_install_plugin'] );

		$fields = $GLOBALS['wp_settings_fields']['git_updater_install_plugin']['plugin'];
		$this->assertArrayHasKey( 'plugin_repo', $fields );
		$this->assertArrayHasKey( 'plugin_branch', $fields );
		$this->assertArrayHasKey( 'plugin_api', $fields );
	}

	/**
	 * Registers setting, section, and core fields for theme type.
	 *
	 * @return void
	 */
	public function test_register_settings_theme(): void {
		$this->install->register_settings( 'theme' );

		$this->assertArrayHasKey( 'git_updater_install_theme', get_registered_settings() );
		$this->assertArrayHasKey( 'theme', $GLOBALS['wp_settings_sections']['git_updater_install_theme'] );
	}

	/**
	 * Non-running-server loop: force GitHub to appear not running so the loop body executes.
	 *
	 * @return void
	 */
	public function test_register_settings_non_running_servers_loop(): void {
		add_filter( 'gu_running_git_servers', '__return_empty_array' );

		$this->install->register_settings( 'plugin' );

		$fields = $GLOBALS['wp_settings_fields']['git_updater_install_plugin']['plugin'] ?? [];
		$this->assertArrayHasKey( 'github_access_token', $fields );
	}
}

// ---------------------------------------------------------------------------
// Test_Install_HtmlFields
// ---------------------------------------------------------------------------

/**
 * Class Test_Install_HtmlFields
 */
class Test_Install_HtmlFields extends WP_UnitTestCase {
	use Install_Test_Helper;

	/** @var Install */
	private Install $install;

	/**
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->install = new Install();
	}

	/**
	 * @return void
	 */
	public function test_get_repo_outputs_input(): void {
		ob_start();
		$this->install->get_repo();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'id="git_updater_repo"', $output );
		$this->assertStringContainsString( 'type="text"', $output );
	}

	/**
	 * @return void
	 */
	public function test_branch_outputs_input(): void {
		ob_start();
		$this->install->branch();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'id="git_updater_branch"', $output );
		$this->assertStringContainsString( 'placeholder="master"', $output );
	}

	/**
	 * @return void
	 */
	public function test_install_api_outputs_select(): void {
		ob_start();
		$this->install->install_api();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<select', $output );
		$this->assertStringContainsString( 'id="git_updater_api"', $output );
	}
}

// ---------------------------------------------------------------------------
// Test_Install_Install
// ---------------------------------------------------------------------------

/**
 * Class Test_Install_Install
 *
 * Covers all branches of install(): no-POST, empty branch, empty repo,
 * GitHub API path + save_options_on_install, plugin upgrader failure,
 * theme upgrader failure, and plugin upgrader success.
 */
class Test_Install_Install extends WP_UnitTestCase {
	use Install_Test_Helper;

	/** @var Install */
	private Install $install;

	/** @var array<string, mixed> */
	private array $saved_install = [];

	/** @var array<string, mixed> */
	private array $saved_post = [];

	/**
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->install       = new Install();
		$this->saved_install = $this->read_install_static();
		$this->saved_post    = $_POST;
		$_POST               = [];
		add_filter( 'gu_get_upgrader_skin', fn( $skin, $type ) => new Silent_Upgrader_Skin( [ 'type' => $type ] ), 10, 2 );
	}

	/**
	 * @return void
	 */
	public function tear_down(): void {
		$this->inject_install_static( $this->saved_install );
		$_POST = $this->saved_post;
		remove_all_filters( 'gu_get_upgrader_skin' );
		remove_all_filters( 'upgrader_pre_download' );
		remove_all_filters( 'http_request_args' );
		remove_all_filters( 'gu_install_remote_install' );
		remove_all_filters( 'install_theme_complete_actions' );
		remove_all_actions( 'upgrader_process_complete' );
		parent::tear_down();
	}

	/**
	 * No POST and no WP-CLI: returns true immediately.
	 *
	 * @return void
	 */
	public function test_no_post_returns_true(): void {
		$result = $this->install->install( 'plugin' );
		$this->assertTrue( $result );
	}

	/**
	 * POST with empty branch defaults to 'master' and empty repo returns false.
	 *
	 * @return void
	 */
	public function test_empty_branch_defaults_to_master_and_empty_repo_returns_false(): void {
		$_POST = [
			'option_page'        => 'git_updater_install',
			'git_updater_repo'   => '',
			'git_updater_branch' => '',
			'git_updater_api'    => 'github',
		];

		ob_start();
		$result = $this->install->install( 'plugin' );
		ob_end_clean();

		$this->assertSame( 'master', $_POST['git_updater_branch'] );
		$this->assertFalse( $result );
	}

	/**
	 * POST with empty repo echoes H3 message and returns false.
	 *
	 * @return void
	 */
	public function test_empty_repo_outputs_error_and_returns_false(): void {
		$_POST = [
			'option_page'        => 'git_updater_install',
			'git_updater_repo'   => '',
			'git_updater_branch' => 'main',
			'git_updater_api'    => 'github',
		];

		ob_start();
		$result = $this->install->install( 'plugin' );
		$output = ob_get_clean();

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'repository URI is required', $output );
	}

	/**
	 * GitHub API path + save_options_on_install + plugin upgrader failure.
	 *
	 * GitHub_API::remote_install() makes no HTTP calls — it just builds the URL.
	 * A priority-15 upgrader_pre_download filter returns WP_Error to fail the install
	 * without requiring a real network request, while still covering the
	 * upgrader_pre_download closure body (lines 214–216).
	 *
	 * @return void
	 */
	public function test_github_api_path_and_save_options_on_install(): void {
		$_POST = [
			'option_page'        => 'git_updater_install',
			'git_updater_repo'   => 'https://github.com/owner/test-repo',
			'git_updater_branch' => 'main',
			'git_updater_api'    => 'github',
		];

		// Inject options so save_options_on_install() is called.
		add_filter(
			'gu_install_remote_install',
			function ( $install ) {
				$install['options'] = [ 'gu_test_option_key' => 'test_val' ];
				return $install;
			},
			10,
			2
		);

		// Make the upgrader fail at download (priority 15 overrides Install's priority-10 false).
		add_filter( 'upgrader_pre_download', fn() => new WP_Error( 'download_failed', 'blocked' ), 15, 3 );

		ob_start();
		$result = $this->install->install( 'plugin' );
		ob_end_clean();

		$this->assertFalse( $result );

		// save_options_on_install ran: option was merged into site option.
		$saved = get_site_option( 'git_updater', [] );
		$this->assertSame( 'test_val', $saved['gu_test_option_key'] ?? null );
	}

	/**
	 * Theme upgrader: covers get_upgrader() theme branch (lines 293–310).
	 *
	 * @return void
	 */
	public function test_theme_upgrader_failure_covers_theme_get_upgrader(): void {
		$_POST = [
			'option_page'        => 'git_updater_install',
			'git_updater_repo'   => 'https://github.com/owner/test-theme',
			'git_updater_branch' => 'main',
			'git_updater_api'    => 'github',
		];

		add_filter( 'upgrader_pre_download', fn() => new WP_Error( 'download_failed', 'blocked' ), 15, 3 );

		ob_start();
		$result = $this->install->install( 'theme' );
		ob_end_clean();

		$this->assertFalse( $result );

		// The install_theme_complete_actions filter was registered inside get_upgrader('theme').
		$this->assertGreaterThan( 0, has_filter( 'install_theme_complete_actions', [ $this->install, 'install_theme_complete_actions' ] ) );
	}

	/**
	 * Plugin upgrader success: upgrader extracts a real zip and installs the plugin.
	 * Covers the truthy branch of $upgrader->install() (line 222) and
	 * Branch::set_branch_on_install() call (line 223).
	 *
	 * @return void
	 */
	public function test_plugin_upgrader_success(): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		$slug     = 'test-install-gu-coverage';
		$zip_path = $this->make_minimal_plugin_zip( $slug );

		// Prevent upgrader_process_complete callbacks (wp_update_themes etc.) from running
		// after the install — they make HTTP calls and access transient keys not set in tests.
		remove_all_actions( 'upgrader_process_complete' );

		// Priority-15: override Install's priority-10 false return with the zip path.
		add_filter( 'upgrader_pre_download', fn() => $zip_path, 15, 3 );

		// Provide download_link via filter (gitea → no GitHub API block runs).
		add_filter(
			'gu_install_remote_install',
			function ( $install ) {
				$install['download_link'] = 'https://example.com/' . $install['repo'] . '.zip';
				return $install;
			},
			10,
			2
		);

		$_POST = [
			'option_page'        => 'git_updater_install',
			'git_updater_repo'   => 'https://github.com/owner/' . $slug,
			'git_updater_branch' => 'main',
			'git_updater_api'    => 'gitea',
		];

		try {
			ob_start();
			$result = $this->install->install( 'plugin' );
			ob_end_clean();

			$this->assertTrue( $result );
		} finally {
			remove_all_filters( 'upgrader_pre_download' );
			remove_all_filters( 'gu_install_remote_install' );
			if ( file_exists( $zip_path ) ) {
				unlink( $zip_path );
			}
			$this->delete_installed_plugin( $slug );
		}
	}
}

// ---------------------------------------------------------------------------
// Test_Install_ThemeCompleteActions
// ---------------------------------------------------------------------------

/**
 * Class Test_Install_ThemeCompleteActions
 *
 * Covers install_theme_complete_actions(): preview removal, activate link,
 * and network admin path (manage_network_themes).
 */
class Test_Install_ThemeCompleteActions extends WP_UnitTestCase {
	use Install_Test_Helper;

	/** @var Install */
	private Install $install;

	/** @var array<string, mixed> */
	private array $saved_install = [];

	/**
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->install       = new Install();
		$this->saved_install = $this->read_install_static();
		$this->inject_install_static( [ 'repo' => 'test-gu-theme' ] );
	}

	/**
	 * @return void
	 */
	public function tear_down(): void {
		$this->inject_install_static( $this->saved_install );
		$GLOBALS['current_screen'] = null;
		remove_all_filters( 'user_has_cap' );
		parent::tear_down();
	}

	/**
	 * Preview key is removed and activate link is set.
	 *
	 * @return void
	 */
	public function test_removes_preview_and_sets_activate_link(): void {
		$actions = $this->install->install_theme_complete_actions(
			[
				'preview'  => '<a href="#">Preview</a>',
				'activate' => '',
			]
		);

		$this->assertArrayNotHasKey( 'preview', $actions );
		$this->assertArrayHasKey( 'activate', $actions );
		$this->assertStringContainsString( 'test-gu-theme', $actions['activate'] );
	}

	/**
	 * Without preview key, activate link is still set.
	 *
	 * @return void
	 */
	public function test_sets_activate_link_without_preview(): void {
		$actions = $this->install->install_theme_complete_actions( [] );

		$this->assertArrayNotHasKey( 'preview', $actions );
		$this->assertArrayHasKey( 'activate', $actions );
	}

	/**
	 * Network admin path: network_enable added, activate removed.
	 *
	 * Uses set_current_screen('themes-network') so is_network_admin() returns true.
	 * Grants manage_network_themes via user_has_cap filter to avoid multisite requirement.
	 *
	 * @return void
	 */
	public function test_network_admin_adds_network_enable_and_removes_activate(): void {
		set_current_screen( 'themes-network' );

		add_filter(
			'user_has_cap',
			function ( $allcaps, $caps ) {
				if ( in_array( 'manage_network_themes', $caps, true ) ) {
					$allcaps['manage_network_themes'] = true;
				}
				return $allcaps;
			},
			10,
			2
		);

		$actions = $this->install->install_theme_complete_actions(
			[
				'preview'  => '<a>Preview</a>',
				'activate' => '<a>Activate</a>',
			]
		);

		$this->assertArrayHasKey( 'network_enable', $actions );
		$this->assertArrayNotHasKey( 'activate', $actions );
		$this->assertArrayNotHasKey( 'preview', $actions );
	}
}
