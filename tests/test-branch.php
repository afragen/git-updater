<?php
/**
 * Tests for Branch class.
 *
 * Covers 100% line coverage of src/Git_Updater/Branch.php:
 * - __construct()
 * - get_current_branch()
 * - set_rollback_transient()
 * - set_branch_on_switch()
 * - set_branch_on_install()
 * - plugin_branch_switcher()
 * - multisite_branch_switcher()
 * - single_install_switcher()
 * - make_branch_switch_row()
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\Base;
use Fragen\Git_Updater\Branch;
use Fragen\Git_Updater\Plugin;
use Fragen\Git_Updater\Theme;

// ---------------------------------------------------------------------------
// Shared helper trait
// ---------------------------------------------------------------------------

trait Branch_Mock_Helper {

	/**
	 * Inject value into Branch::$options (protected static).
	 *
	 * @param array<string, mixed> $options
	 */
	private function inject_branch_options( array $options ): void {
		$rp = new ReflectionProperty( Branch::class, 'options' );
		$rp->setAccessible( true );
		$rp->setValue( null, $options );
	}

	/**
	 * Read Branch::$options (protected static).
	 *
	 * @return array<string, mixed>
	 */
	private function read_branch_options(): array {
		$rp = new ReflectionProperty( Branch::class, 'options' );
		$rp->setAccessible( true );
		return $rp->getValue( null ) ?? [];
	}

	/**
	 * Inject config into the Plugin singleton.
	 *
	 * @param object               $caller Branch instance (used to get the same singleton).
	 * @param array<string, stdClass> $config
	 */
	private function inject_plugin_config( object $caller, array $config ): void {
		$singleton = Fragen\Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $caller );
		$rp        = new ReflectionProperty( Plugin::class, 'config' );
		$rp->setAccessible( true );
		$rp->setValue( $singleton, $config );
	}

	/**
	 * Inject config into the Theme singleton.
	 *
	 * @param object               $caller Branch instance.
	 * @param array<string, stdClass> $config
	 */
	private function inject_theme_config( object $caller, array $config ): void {
		$singleton = Fragen\Singleton::get_instance( 'Fragen\Git_Updater\Theme', $caller );
		$rp        = new ReflectionProperty( Theme::class, 'config' );
		$rp->setAccessible( true );
		$rp->setValue( $singleton, $config );
	}

	/**
	 * Read current config from the Plugin singleton.
	 *
	 * @param object $caller
	 * @return array<string, stdClass>
	 */
	private function read_plugin_config( object $caller ): array {
		$singleton = Fragen\Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $caller );
		$rp        = new ReflectionProperty( Plugin::class, 'config' );
		$rp->setAccessible( true );
		return $rp->getValue( $singleton ) ?? [];
	}

	/**
	 * Read current config from the Theme singleton.
	 *
	 * @param object $caller
	 * @return array<string, stdClass>
	 */
	private function read_theme_config( object $caller ): array {
		$singleton = Fragen\Singleton::get_instance( 'Fragen\Git_Updater\Theme', $caller );
		$rp        = new ReflectionProperty( Theme::class, 'config' );
		$rp->setAccessible( true );
		return $rp->getValue( $singleton ) ?? [];
	}

	/**
	 * Build a minimal repo stdClass for Branch tests.
	 *
	 * @param array<string, mixed> $overrides
	 * @return stdClass
	 */
	private function make_repo_obj( array $overrides = [] ): stdClass {
		return (object) array_merge(
			[
				'slug'           => 'test-repo',
				'file'           => 'test-repo/test-repo.php',
				'git'            => 'github',
				'type'           => 'plugin',
				'branch'         => 'main',
				'primary_branch' => 'main',
				'owner'          => 'test-owner',
				'uri'            => 'https://github.com/test-owner/test-repo',
				'branches'       => [ 'main' => [], 'develop' => [] ],
				'tags'           => [],
				'release_asset'  => false,
				'newest_tag'     => '0.0.0',
				'enterprise'     => null,
				'enterprise_api' => null,
			],
			$overrides
		);
	}

	/**
	 * Cache key helper.
	 *
	 * @param string $slug
	 * @return string
	 */
	private function branch_cache_key( string $slug ): string {
		return 'ghu-' . md5( $slug );
	}
}

// ---------------------------------------------------------------------------
// Test_Branch_Constructor
// ---------------------------------------------------------------------------

/**
 * Class Test_Branch_Constructor
 */
class Test_Branch_Constructor extends WP_UnitTestCase {
	use Branch_Mock_Helper;

	public function set_up(): void {
		parent::set_up();
		delete_site_option( 'git_updater' );
		new Base();
	}

	public function tear_down(): void {
		delete_site_option( 'git_updater' );
		parent::tear_down();
	}

	public function test_constructor_sets_options_array(): void {
		$branch = new Branch();
		$rp     = new ReflectionProperty( Branch::class, 'options' );
		$rp->setAccessible( true );
		$this->assertIsArray( $rp->getValue( null ) );
	}

	public function test_constructor_sets_base_instance(): void {
		$branch = new Branch();
		$rp     = new ReflectionProperty( Branch::class, 'base' );
		$rp->setAccessible( true );
		$this->assertInstanceOf( Base::class, $rp->getValue( $branch ) );
	}
}

// ---------------------------------------------------------------------------
// Test_Branch_GetCurrentBranch
// ---------------------------------------------------------------------------

/**
 * Class Test_Branch_GetCurrentBranch
 */
class Test_Branch_GetCurrentBranch extends WP_UnitTestCase {
	use Branch_Mock_Helper;

	private Branch $branch;
	private string $slug      = 'test-gcb-repo';
	private string $cache_key;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->branch    = new Branch();
		$this->cache_key = $this->branch_cache_key( $this->slug );
		delete_site_option( $this->cache_key );
	}

	public function tear_down(): void {
		delete_site_option( $this->cache_key );
		parent::tear_down();
	}

	public function test_returns_cached_current_branch(): void {
		update_site_option( $this->cache_key, [ 'current_branch' => 'develop' ] );
		$repo = (object) [ 'slug' => $this->slug, 'branch' => 'main' ];

		$this->assertSame( 'develop', $this->branch->get_current_branch( $repo ) );
	}

	public function test_falls_back_to_repo_branch_on_cache_miss(): void {
		// No cache seeded — get_site_option returns [] (default), empty() on missing key is safe.
		$repo = (object) [ 'slug' => $this->slug, 'branch' => 'main' ];

		$this->assertSame( 'main', $this->branch->get_current_branch( $repo ) );
	}
}

// ---------------------------------------------------------------------------
// Test_Branch_SetRollbackTransient
// ---------------------------------------------------------------------------

/**
 * Class Test_Branch_SetRollbackTransient
 */
class Test_Branch_SetRollbackTransient extends WP_UnitTestCase {
	use Branch_Mock_Helper;

	private Branch  $branch;
	private stdClass $repo;
	/** @var array<string, mixed> */
	private array $saved_options = [];

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->branch        = new Branch();
		$this->saved_options = $this->read_branch_options();
		$this->repo          = $this->make_repo_obj();

		// Override download link to avoid HTTP; filter runs after construct_download_link().
		add_filter(
			'gu_post_construct_download_link',
			function () {
				return 'https://example.com/test.zip';
			}
		);
	}

	public function tear_down(): void {
		unset( $_GET['rollback'] );
		remove_all_filters( 'gu_post_construct_download_link' );
		delete_site_option( $this->branch_cache_key( 'test-repo' ) );
		$this->inject_branch_options( $this->saved_options );
		parent::tear_down();
	}

	public function test_tag_set_from_get_rollback(): void {
		$_GET['rollback'] = '1.2.3';

		$this->branch->set_rollback_transient( 'plugin', $this->repo );

		$rp = new ReflectionProperty( Branch::class, 'tag' );
		$rp->setAccessible( true );
		$this->assertSame( '1.2.3', $rp->getValue( $this->branch ) );
	}

	public function test_tag_is_false_when_no_get_rollback(): void {
		unset( $_GET['rollback'] );

		$this->branch->set_rollback_transient( 'plugin', $this->repo );

		$rp = new ReflectionProperty( Branch::class, 'tag' );
		$rp->setAccessible( true );
		$this->assertFalse( $rp->getValue( $this->branch ) );
	}

	public function test_returns_stdclass_for_plugin_type(): void {
		$_GET['rollback'] = 'v1.0.0';

		$result = $this->branch->set_rollback_transient( 'plugin', $this->repo );

		$this->assertInstanceOf( stdClass::class, $result );
		$this->assertSame( 'v1.0.0', $result->new_version );
		$this->assertSame( 'test-repo/test-repo.php', $result->plugin );
		$this->assertSame( 'test-repo', $result->slug );
		$this->assertSame( 'https://example.com/test.zip', $result->package );
	}

	public function test_returns_array_for_theme_type(): void {
		$_GET['rollback'] = 'v1.0.0';
		$repo             = $this->make_repo_obj( [ 'type' => 'theme' ] );

		$result = $this->branch->set_rollback_transient( 'theme', $repo );

		$this->assertIsArray( $result );
		$this->assertSame( 'v1.0.0', $result['new_version'] );
		$this->assertSame( 'test-repo', $result['theme'] );
		$this->assertSame( 'https://example.com/test.zip', $result['package'] );
	}
}

// ---------------------------------------------------------------------------
// Test_Branch_SetBranchOnSwitch
// ---------------------------------------------------------------------------

/**
 * Class Test_Branch_SetBranchOnSwitch
 */
class Test_Branch_SetBranchOnSwitch extends WP_UnitTestCase {
	use Branch_Mock_Helper;

	private Branch $branch;
	private string $slug      = 'test-sbos-repo';
	private string $cache_key;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->branch    = new Branch();
		$this->cache_key = $this->branch_cache_key( $this->slug );
		delete_site_option( $this->cache_key );
		delete_site_option( 'git_updater' );
	}

	public function tear_down(): void {
		unset( $_GET['rollback'], $_GET['action'] );
		delete_site_option( $this->cache_key );
		delete_site_option( 'git_updater' );
		parent::tear_down();
	}

	public function test_exits_early_when_no_rollback_param(): void {
		unset( $_GET['rollback'] );
		// Seed cache so we can verify it wasn't modified.
		update_site_option( $this->cache_key, [ 'branches' => [ 'main' => [] ] ] );

		$this->branch->set_branch_on_switch( $this->slug );

		$cache = get_site_option( $this->cache_key, [] );
		$this->assertArrayNotHasKey( 'current_branch', $cache );
	}

	public function test_sets_primary_branch_when_rollback_in_tags(): void {
		$_GET['rollback'] = 'v1.0.0';
		update_site_option(
			$this->cache_key,
			[
				'tags'          => [ 'v1.0.0', 'v0.9.0' ],
				$this->slug     => [ 'PrimaryBranch' => 'main' ],
			]
		);

		$this->branch->set_branch_on_switch( $this->slug );

		$cache = get_site_option( $this->cache_key, [] );
		$this->assertSame( 'main', $cache['current_branch'] );
	}

	public function test_falls_back_to_master_when_no_primary_branch_key_in_cache(): void {
		$_GET['rollback'] = 'v1.0.0';
		// Tags contain the rollback but no nested slug sub-array — hits the ?? 'master' path.
		update_site_option( $this->cache_key, [ 'tags' => [ 'v1.0.0' ] ] );

		$this->branch->set_branch_on_switch( $this->slug );

		$cache = get_site_option( $this->cache_key, [] );
		$this->assertSame( 'master', $cache['current_branch'] );
	}

	public function test_sets_branch_from_branches_cache(): void {
		$_GET['rollback'] = 'develop';
		$_GET['action']   = 'upgrade-plugin';
		// rollback is NOT in tags, so falls through to the branches path.
		update_site_option(
			$this->cache_key,
			[
				'tags'     => [ 'v1.0.0' ],
				'branches' => [ 'develop' => [], 'main' => [] ],
			]
		);

		$this->branch->set_branch_on_switch( $this->slug );

		$cache = get_site_option( $this->cache_key, [] );
		$this->assertSame( 'develop', $cache['current_branch'] );
	}

	public function test_defaults_to_master_when_branch_not_in_cache_branches(): void {
		$_GET['rollback'] = 'nonexistent';
		$_GET['action']   = 'upgrade-plugin';
		update_site_option(
			$this->cache_key,
			[
				'tags'     => [],
				'branches' => [ 'main' => [] ],
			]
		);

		$this->branch->set_branch_on_switch( $this->slug );

		$cache = get_site_option( $this->cache_key, [] );
		$this->assertSame( 'master', $cache['current_branch'] );
	}

	public function test_does_not_update_when_neither_branch_condition_matches(): void {
		$_GET['rollback'] = 'v1.0.0';
		// Not in tags, and no $_GET['action'] — neither if block sets $current_branch.
		update_site_option(
			$this->cache_key,
			[
				'tags'     => [ 'other-tag' ],
				'branches' => [ 'main' => [] ],
			]
		);

		$this->branch->set_branch_on_switch( $this->slug );

		$cache = get_site_option( $this->cache_key, [] );
		$this->assertArrayNotHasKey( 'current_branch', $cache );
	}
}

// ---------------------------------------------------------------------------
// Test_Branch_SetBranchOnInstall
// ---------------------------------------------------------------------------

/**
 * Class Test_Branch_SetBranchOnInstall
 */
class Test_Branch_SetBranchOnInstall extends WP_UnitTestCase {
	use Branch_Mock_Helper;

	private Branch $branch;
	private string $slug      = 'test-sboi-repo';
	private string $cache_key;
	/** @var array<string, mixed> */
	private array $saved_options = [];

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->branch        = new Branch();
		$this->saved_options = $this->read_branch_options();
		$this->cache_key     = $this->branch_cache_key( $this->slug );
		delete_site_option( $this->cache_key );
		delete_site_option( 'git_updater' );
		$this->inject_branch_options( [] );
	}

	public function tear_down(): void {
		delete_site_option( $this->cache_key );
		delete_site_option( 'git_updater' );
		$this->inject_branch_options( $this->saved_options );
		parent::tear_down();
	}

	public function test_sets_cache_entry(): void {
		$install = [
			'repo'                => $this->slug,
			'git_updater_branch'  => 'develop',
		];

		$this->branch->set_branch_on_install( $install );

		$cache = get_site_option( $this->cache_key, [] );
		$this->assertSame( 'develop', $cache['current_branch'] );
	}

	public function test_updates_current_branch_option(): void {
		$install = [
			'repo'               => $this->slug,
			'git_updater_branch' => 'develop',
		];

		$this->branch->set_branch_on_install( $install );

		$opts = $this->read_branch_options();
		$this->assertSame( 'develop', $opts[ 'current_branch_' . $this->slug ] );
	}

	public function test_merges_install_options_when_array(): void {
		$install = [
			'repo'               => $this->slug,
			'git_updater_branch' => 'main',
			'options'            => [ 'extra_key' => 'extra_value' ],
		];

		$this->branch->set_branch_on_install( $install );

		$opts = $this->read_branch_options();
		$this->assertSame( 'extra_value', $opts['extra_key'] );
		$this->assertSame( 'main', $opts[ 'current_branch_' . $this->slug ] );
	}
}

// ---------------------------------------------------------------------------
// Test_Branch_PluginBranchSwitcher
// ---------------------------------------------------------------------------

/**
 * Class Test_Branch_PluginBranchSwitcher
 */
class Test_Branch_PluginBranchSwitcher extends WP_UnitTestCase {
	use Branch_Mock_Helper;

	private Branch $branch;
	/** @var array<string, mixed> */
	private array $saved_options = [];
	/** @var array<string, stdClass> */
	private array $saved_plugin_config = [];

	public function set_up(): void {
		parent::set_up();
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		if ( ! class_exists( 'WP_Plugins_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-plugins-list-table.php';
		}
		if ( ! function_exists( '_get_list_table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/template.php';
		}
		new Base();
		$this->branch              = new Branch();
		$this->saved_options       = $this->read_branch_options();
		$this->saved_plugin_config = $this->read_plugin_config( $this->branch );
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_number_rollbacks' );
		remove_all_filters( 'gu_no_release_asset_branches' );
		$this->inject_branch_options( $this->saved_options );
		$this->inject_plugin_config( $this->branch, $this->saved_plugin_config );
		parent::tear_down();
	}

	public function test_returns_false_when_branch_switch_disabled(): void {
		$this->inject_branch_options( [ 'branch_switch' => '' ] );

		$result = $this->branch->plugin_branch_switcher( 'any-plugin/any-plugin.php' );

		$this->assertFalse( $result );
	}

	public function test_outputs_html_and_returns_true(): void {
		if ( ! function_exists( '_get_list_table' ) ) {
			$this->markTestSkipped( '_get_list_table() not available outside admin context.' );
		}

		$this->inject_branch_options( [ 'branch_switch' => '1' ] );

		// Use git='bitbucket' so get_remote_repo_meta() returns false immediately
		// (null API → early return) — no HTTP calls.
		$repo_obj = $this->make_repo_obj(
			[
				'git'            => 'bitbucket',
				'type'           => 'plugin',
				'branches'       => [ 'main' => [] ],
				'release_asset'  => false,
			]
		);

		$this->inject_plugin_config( $this->branch, [ 'test-repo' => $repo_obj ] );

		ob_start();
		$result = $this->branch->plugin_branch_switcher( 'test-repo/test-repo.php' );
		$output = ob_get_clean();

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'test-repo-id', $output );
	}
}

// ---------------------------------------------------------------------------
// Test_Branch_MultiSiteBranchSwitcher
// ---------------------------------------------------------------------------

/**
 * Class Test_Branch_MultiSiteBranchSwitcher
 */
class Test_Branch_MultiSiteBranchSwitcher extends WP_UnitTestCase {
	use Branch_Mock_Helper;

	private Branch $branch;
	/** @var array<string, mixed> */
	private array $saved_options = [];
	/** @var array<string, stdClass> */
	private array $saved_theme_config = [];

	public function set_up(): void {
		parent::set_up();
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		if ( ! class_exists( 'WP_Plugins_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-plugins-list-table.php';
		}
		if ( ! function_exists( '_get_list_table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/template.php';
		}
		new Base();
		$this->branch             = new Branch();
		$this->saved_options      = $this->read_branch_options();
		$this->saved_theme_config = $this->read_theme_config( $this->branch );
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_number_rollbacks' );
		remove_all_filters( 'gu_no_release_asset_branches' );
		$this->inject_branch_options( $this->saved_options );
		$this->inject_theme_config( $this->branch, $this->saved_theme_config );
		parent::tear_down();
	}

	public function test_returns_false_when_branch_switch_disabled(): void {
		$this->inject_branch_options( [ 'branch_switch' => '' ] );

		$result = $this->branch->multisite_branch_switcher( 'test-theme' );

		$this->assertFalse( $result );
	}

	public function test_outputs_html_and_returns_true(): void {
		if ( ! function_exists( '_get_list_table' ) ) {
			$this->markTestSkipped( '_get_list_table() not available outside admin context.' );
		}

		$this->inject_branch_options( [ 'branch_switch' => '1' ] );

		$repo_obj = $this->make_repo_obj(
			[
				'slug'           => 'test-theme',
				'git'            => 'bitbucket',
				'type'           => 'theme',
				'branches'       => [ 'main' => [] ],
				'release_asset'  => false,
			]
		);

		$this->inject_theme_config( $this->branch, [ 'test-theme' => $repo_obj ] );

		ob_start();
		$result = $this->branch->multisite_branch_switcher( 'test-theme' );
		$output = ob_get_clean();

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'test-theme-id', $output );
	}
}

// ---------------------------------------------------------------------------
// Test_Branch_SingleInstallSwitcher
// ---------------------------------------------------------------------------

/**
 * Class Test_Branch_SingleInstallSwitcher
 */
class Test_Branch_SingleInstallSwitcher extends WP_UnitTestCase {
	use Branch_Mock_Helper;

	private Branch $branch;
	/** @var array<string, mixed> */
	private array $saved_options = [];

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->branch        = new Branch();
		$this->saved_options = $this->read_branch_options();
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_number_rollbacks' );
		$this->inject_branch_options( $this->saved_options );
		parent::tear_down();
	}

	/**
	 * Build a theme-like stdClass for single_install_switcher().
	 *
	 * @param array<string, mixed> $overrides
	 * @return stdClass
	 */
	private function make_theme( array $overrides = [] ): stdClass {
		return (object) array_merge(
			[
				'slug'           => 'test-theme',
				'branch'         => 'main',
				'primary_branch' => 'main',
				'branches'       => [ 'main' => [], 'develop' => [] ],
				'tags'           => [],
				'release_asset'  => false,
			],
			$overrides
		);
	}

	public function test_returns_empty_string_when_branch_switch_not_1(): void {
		$this->inject_branch_options( [ 'branch_switch' => '0' ] );

		$result = $this->branch->single_install_switcher( $this->make_theme() );

		$this->assertSame( '', $result );
	}

	public function test_renders_current_branch_info(): void {
		$this->inject_branch_options( [ 'branch_switch' => '1' ] );

		$result = $this->branch->single_install_switcher( $this->make_theme( [ 'branch' => 'main', 'tags' => [] ] ) );

		$this->assertStringContainsString( 'main', $result );
		$this->assertStringContainsString( 'Choose a Version', $result );
	}

	public function test_renders_branch_options(): void {
		$this->inject_branch_options( [ 'branch_switch' => '1' ] );
		$theme = $this->make_theme(
			[
				'branches'      => [ 'main' => [], 'develop' => [] ],
				'tags'          => [],
				'release_asset' => false,
			]
		);

		$result = $this->branch->single_install_switcher( $theme );

		$this->assertStringContainsString( '<option>develop</option>', $result );
	}

	public function test_unsets_primary_branch_for_release_asset(): void {
		$this->inject_branch_options( [ 'branch_switch' => '1' ] );
		$theme = $this->make_theme(
			[
				'release_asset'  => true,
				'primary_branch' => 'main',
				'branches'       => [ 'main' => [], 'develop' => [] ],
				'tags'           => [],
			]
		);

		$result = $this->branch->single_install_switcher( $theme );

		$this->assertStringContainsString( '<option>develop</option>', $result );
		$this->assertStringNotContainsString( '<option>main</option>', $result );
	}

	public function test_shows_one_tag_when_num_rollbacks_zero(): void {
		$this->inject_branch_options( [ 'branch_switch' => '1' ] );
		// gu_number_rollbacks = 0 → array_slice to 1 entry.
		$theme = $this->make_theme(
			[
				'branches' => [],
				'tags'     => [ '2.0.0' => [], '1.0.0' => [] ],
			]
		);

		$result = $this->branch->single_install_switcher( $theme );

		$this->assertStringContainsString( '<option>2.0.0</option>', $result );
		$this->assertStringNotContainsString( '<option>1.0.0</option>', $result );
	}

	public function test_shows_multiple_tags_when_num_rollbacks_filter_set(): void {
		$this->inject_branch_options( [ 'branch_switch' => '1' ] );
		add_filter( 'gu_number_rollbacks', fn() => 2 );
		$theme = $this->make_theme(
			[
				'branches' => [],
				'tags'     => [ '3.0.0' => [], '2.0.0' => [], '1.0.0' => [] ],
			]
		);

		$result = $this->branch->single_install_switcher( $theme );

		$this->assertStringContainsString( '<option>3.0.0</option>', $result );
		$this->assertStringContainsString( '<option>2.0.0</option>', $result );
		$this->assertStringNotContainsString( '<option>1.0.0</option>', $result );
	}

	public function test_shows_no_tags_message_when_tags_empty(): void {
		$this->inject_branch_options( [ 'branch_switch' => '1' ] );
		$theme = $this->make_theme( [ 'branches' => [ 'main' => [] ], 'tags' => [] ] );

		$result = $this->branch->single_install_switcher( $theme );

		$this->assertStringContainsString( 'No previous tags to rollback to.', $result );
	}
}

// ---------------------------------------------------------------------------
// Test_Branch_MakeBranchSwitchRow
// ---------------------------------------------------------------------------

/**
 * Class Test_Branch_MakeBranchSwitchRow
 */
class Test_Branch_MakeBranchSwitchRow extends WP_UnitTestCase {
	use Branch_Mock_Helper;

	private Branch $branch;
	/** @var array<string, mixed> */
	private array $saved_options = [];

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->branch        = new Branch();
		$this->saved_options = $this->read_branch_options();
		$this->inject_branch_options( [ 'branch_switch' => '1' ] );
	}

	public function tear_down(): void {
		remove_all_filters( 'gu_number_rollbacks' );
		remove_all_filters( 'gu_no_release_asset_branches' );
		remove_all_filters( 'gu_release_asset_rollback' );
		$this->inject_branch_options( $this->saved_options );
		parent::tear_down();
	}

	/**
	 * Build data array for make_branch_switch_row().
	 *
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	private function make_data( array $overrides = [] ): array {
		return array_merge(
			[
				'slug'              => 'test-repo',
				'nonced_update_url' => 'https://example.com/update?nonce=abc',
				'id'                => 'test-repo-id',
				'branch'            => 'main',
				'branches'          => [ 'main' => [], 'develop' => [] ],
				'release_asset'     => false,
				'primary_branch'    => 'main',
			],
			$overrides
		);
	}

	/**
	 * Build config array for make_branch_switch_row().
	 *
	 * @param array<string, mixed> $obj_overrides
	 * @return array<string, stdClass>
	 */
	private function make_config( array $obj_overrides = [] ): array {
		return [
			'test-repo' => (object) array_merge(
				[
					'slug' => 'test-repo',
					'file' => 'test-repo/test-repo.php',
					'type' => 'plugin',
					'tags' => [],
				],
				$obj_overrides
			),
		];
	}

	/**
	 * Capture make_branch_switch_row() output.
	 *
	 * @param array<string, mixed>    $data
	 * @param array<string, stdClass> $config
	 * @return string
	 */
	private function get_row_output( array $data, array $config ): string {
		ob_start();
		$this->branch->make_branch_switch_row( $data, $config );
		return ob_get_clean();
	}

	public function test_plugin_type_outputs_data_plugin_attr(): void {
		$output = $this->get_row_output( $this->make_data(), $this->make_config( [ 'type' => 'plugin' ] ) );

		$this->assertStringContainsString( 'data-plugin', $output );
	}

	public function test_theme_type_outputs_data_slug_attr(): void {
		$config = $this->make_config( [ 'type' => 'theme', 'slug' => 'test-repo' ] );
		$output = $this->get_row_output( $this->make_data(), $config );

		$this->assertStringContainsString( 'data-slug', $output );
	}

	public function test_outputs_script_tag_with_jquery(): void {
		$output = $this->get_row_output( $this->make_data(), $this->make_config() );

		$this->assertStringContainsString( '<script>', $output );
		$this->assertStringContainsString( 'jQuery', $output );
	}

	public function test_renders_branch_links_when_branches_not_null(): void {
		$data   = $this->make_data( [ 'branches' => [ 'main' => [], 'develop' => [] ], 'release_asset' => false ] );
		$output = $this->get_row_output( $data, $this->make_config() );

		$this->assertStringContainsString( 'rollback=main', $output );
		$this->assertStringContainsString( 'rollback=develop', $output );
	}

	public function test_null_branches_skips_branch_links(): void {
		$data   = $this->make_data( [ 'branches' => null ] );
		$output = $this->get_row_output( $data, $this->make_config() );

		// No branch links, only the "no tags" message since tags=[] too.
		$this->assertStringNotContainsString( '&rollback=', $output );
	}

	public function test_release_asset_removes_primary_branch_from_list(): void {
		$data = $this->make_data(
			[
				'release_asset'  => true,
				'primary_branch' => 'main',
				'branches'       => [ 'main' => [], 'develop' => [] ],
			]
		);
		$output = $this->get_row_output( $data, $this->make_config() );

		$this->assertStringContainsString( 'rollback=develop', $output );
		$this->assertStringNotContainsString( 'rollback=main', $output );
	}

	public function test_no_release_asset_branches_filter_empties_all_branches(): void {
		add_filter( 'gu_no_release_asset_branches', '__return_true' );
		$data = $this->make_data(
			[
				'release_asset' => true,
				'branches'      => [ 'main' => [], 'develop' => [] ],
			]
		);
		$output = $this->get_row_output( $data, $this->make_config() );

		// No rollback= links from branches; tags also empty → only "no tags" message.
		$this->assertStringNotContainsString( '&rollback=', $output );
	}

	public function test_shows_no_tags_message_when_rollback_empty(): void {
		$data   = $this->make_data( [ 'branches' => null ] );
		$output = $this->get_row_output( $data, $this->make_config( [ 'tags' => [] ] ) );

		$this->assertStringContainsString( 'No previous tags to rollback to.', $output );
	}

	public function test_shows_one_tag_when_num_rollbacks_zero(): void {
		// num_rollbacks = 0 → array_slice to 1 tag; uksort puts 2.0.0 first (descending).
		$config = $this->make_config( [ 'tags' => [ '2.0.0' => 'url', '1.0.0' => 'url' ] ] );
		$data   = $this->make_data( [ 'branches' => null ] );
		$output = $this->get_row_output( $data, $config );

		$this->assertStringContainsString( 'rollback=2.0.0', $output );
		$this->assertStringNotContainsString( 'rollback=1.0.0', $output );
	}

	public function test_shows_n_tags_when_num_rollbacks_filter_set(): void {
		add_filter( 'gu_number_rollbacks', fn() => 2 );
		$config = $this->make_config( [ 'tags' => [ '3.0.0' => 'url', '2.0.0' => 'url', '1.0.0' => 'url' ] ] );
		$data   = $this->make_data( [ 'branches' => null ] );
		$output = $this->get_row_output( $data, $config );

		$this->assertStringContainsString( 'rollback=3.0.0', $output );
		$this->assertStringContainsString( 'rollback=2.0.0', $output );
		$this->assertStringNotContainsString( 'rollback=1.0.0', $output );
	}

	public function test_release_asset_rollback_filter_applied(): void {
		add_filter( 'gu_release_asset_rollback', fn( $r, $f ) => [ 'custom-tag' ], 10, 2 );
		$config = $this->make_config( [ 'tags' => [ '2.0.0' => 'url' ] ] );
		$data   = $this->make_data( [ 'release_asset' => true, 'branches' => null ] );
		$output = $this->get_row_output( $data, $config );

		$this->assertStringContainsString( 'rollback=custom-tag', $output );
	}
}
