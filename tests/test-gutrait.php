<?php

use Fragen\Git_Updater\Base;

/**
 * Tests for GU_Trait methods changed in Tier 1 and Tier 2 PHPStan fixes.
 */
class Test_GUTrait extends \WP_UnitTestCase {

	use Fragen\Git_Updater\Traits\GU_Trait;

	/**
	 * GU_Trait::get_headers() references self::$extra_headers; declare it so
	 * the test class satisfies the trait's expectation.
	 *
	 * @var array<string, string>
	 */
	public static $extra_headers = [];

	// -------------------------------------------------------------------------
	// sanitize() – existing tests
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider data_sanitize
	 */
	public function test_sanitize( $input = [], $expected = [] ) {
		$this->assertSame( $expected, $this->sanitize( $input ) );
	}

	public function data_sanitize() {
		return [
			[ [], [] ],
			[ [ 0 => 'test' ], [ 0 => 'test' ] ],
			[ [ '0' => 'test' ], [ 0 => 'test' ] ],
			[ [ 'test' => 'test' ], [ 'test' => 'test' ] ],
			[ [ 'test' => '<test' ], [ 'test' => '&lt;test' ] ],
			[ [ '<test' => '<test' ], [ '' => '&lt;test' ] ],
			[ [ 'test_one' => 'test' ], [ 'test_one' => 'test' ] ],
			[ [ 'test-one' => 'test' ], [ 'test-one' => 'test' ] ],
		];
	}

	// -------------------------------------------------------------------------
	// get_reflection_method() — PHP_VERSION_ID < 80100 dead code removed
	// -------------------------------------------------------------------------

	/**
	 * get_reflection_method() must return a usable ReflectionMethod on PHP 8.1+
	 * without calling setAccessible(true), which was the removed dead code.
	 */
	public function test_get_reflection_method_returns_reflection_method(): void {
		$rm = $this->get_reflection_method( $this, 'sanitize' );
		$this->assertInstanceOf( ReflectionMethod::class, $rm );
	}

	/**
	 * The returned ReflectionMethod can be invoked without explicitly calling
	 * setAccessible — confirming the dead-code removal doesn't break functionality.
	 */
	public function test_get_reflection_method_can_invoke_public_method(): void {
		$rm     = $this->get_reflection_method( $this, 'sanitize' );
		$result = $rm->invoke( $this, [ 'test' => '<test' ] );
		$this->assertSame( [ 'test' => '&lt;test' ], $result );
	}

	// -------------------------------------------------------------------------
	// override_dot_org() — @param fixed to accept array|stdClass
	// -------------------------------------------------------------------------

	/**
	 * When $repo is an array (icon/dashicon context), override_dot_org() must
	 * cast it to object and treat dot_org_master as true.  With no filter
	 * overrides registered, the return value is false (do not override
	 * dot-org updates for the icon rendering path).
	 */
	public function test_override_dot_org_with_array_repo_returns_false(): void {
		$repo = [
			'slug' => 'my-plugin',
			'file' => 'my-plugin/my-plugin.php',
		];
		$result = $this->override_dot_org( 'plugin', $repo );
		$this->assertFalse( $result );
	}

	/**
	 * When $repo is a stdClass without dot_org set, the repo is not on dot.org,
	 * so override_dot_org() should return true (override / ignore dot-org).
	 */
	public function test_override_dot_org_with_stdclass_without_dot_org_returns_true(): void {
		$repo                 = new stdClass();
		$repo->slug           = 'my-plugin';
		$repo->file           = 'my-plugin/my-plugin.php';
		$repo->branch         = 'main';
		$repo->primary_branch = 'main';

		$result = $this->override_dot_org( 'plugin', $repo );
		$this->assertTrue( $result );
	}

	/**
	 * When $repo is a stdClass with dot_org=true and branch equals primary_branch,
	 * the repo is actively distributed via dot.org on its primary branch, so
	 * override_dot_org() should return false (don't override).
	 */
	public function test_override_dot_org_with_dot_org_on_primary_branch_returns_false(): void {
		$repo                 = new stdClass();
		$repo->slug           = 'my-plugin';
		$repo->file           = 'my-plugin/my-plugin.php';
		$repo->dot_org        = true;
		$repo->branch         = 'main';
		$repo->primary_branch = 'main';

		$result = $this->override_dot_org( 'plugin', $repo );
		$this->assertFalse( $result );
	}

	/**
	 * When $repo is a stdClass with dot_org=true but on a non-primary branch,
	 * override_dot_org() returns true (override while on a feature branch).
	 */
	public function test_override_dot_org_with_dot_org_on_non_primary_branch_returns_true(): void {
		$repo                 = new stdClass();
		$repo->slug           = 'my-plugin';
		$repo->file           = 'my-plugin/my-plugin.php';
		$repo->dot_org        = true;
		$repo->branch         = 'develop';
		$repo->primary_branch = 'main';

		$result = $this->override_dot_org( 'plugin', $repo );
		$this->assertTrue( $result );
	}

	/**
	 * The gu_override_dot_org filter can force an override even for a
	 * dot.org repo on its primary branch.
	 */
	public function test_override_dot_org_respects_filter_override(): void {
		$repo                 = new stdClass();
		$repo->slug           = 'my-plugin';
		$repo->file           = 'my-plugin/my-plugin.php';
		$repo->dot_org        = true;
		$repo->branch         = 'main';
		$repo->primary_branch = 'main';

		add_filter( 'gu_override_dot_org', fn() => [ 'my-plugin/my-plugin.php' ] );
		$result = $this->override_dot_org( 'plugin', $repo );
		remove_all_filters( 'gu_override_dot_org' );

		$this->assertTrue( $result );
	}

	// -------------------------------------------------------------------------
	// get_file_headers() — parses string and passthrough for array
	// -------------------------------------------------------------------------

	/**
	 * Parsing a plugin header string must produce the expected Version/Name keys.
	 */
	public function test_get_file_headers_parses_plugin_header_string(): void {
		$contents = "<?php\n/**\n * Plugin Name: My Plugin\n * Version: 1.2.3\n */\n";
		$headers  = $this->get_file_headers( $contents, 'plugin' );
		$this->assertSame( '1.2.3', $headers['Version'] );
		$this->assertSame( 'My Plugin', $headers['Name'] );
	}

	/**
	 * When $contents is already a parsed array it is returned directly
	 * without attempting string parsing.
	 */
	public function test_get_file_headers_with_array_returns_array_unchanged(): void {
		$pre_parsed = [
			'Name'    => 'My Plugin',
			'Version' => '2.0.0',
		];
		$headers = $this->get_file_headers( $pre_parsed, 'plugin' );
		$this->assertSame( 'My Plugin', $headers['Name'] );
		$this->assertSame( '2.0.0', $headers['Version'] );
	}

	// -------------------------------------------------------------------------
	// override_dot_org() — Skip_Updates plugin paths (lines 468–474)
	// -------------------------------------------------------------------------

	private function ensure_skip_updates_stub(): void {
		if ( ! class_exists( '\Fragen\Skip_Updates\Bootstrap' ) ) {
			// phpcs:ignore Squiz.PHP.Eval.Discouraged
			eval( 'namespace Fragen\\Skip_Updates; class Bootstrap {}' );
		}
	}

	public function test_override_dot_org_skip_updates_returns_true_when_slug_matches(): void {
		$this->ensure_skip_updates_stub();
		update_site_option( 'skip_updates', [ [ 'slug' => 'my-plugin/my-plugin.php' ] ] );
		$repo                 = new stdClass();
		$repo->slug           = 'my-plugin';
		$repo->file           = 'my-plugin/my-plugin.php';
		$repo->dot_org        = true;
		$repo->branch         = 'main';
		$repo->primary_branch = 'main';
		$result               = $this->override_dot_org( 'plugin', $repo );
		delete_site_option( 'skip_updates' );
		$this->assertTrue( $result );
	}

	public function test_override_dot_org_skip_updates_returns_false_when_slug_unmatched(): void {
		$this->ensure_skip_updates_stub();
		update_site_option( 'skip_updates', [ [ 'slug' => 'other-plugin/other.php' ] ] );
		$repo                 = new stdClass();
		$repo->slug           = 'my-plugin';
		$repo->file           = 'my-plugin/my-plugin.php';
		$repo->dot_org        = true;
		$repo->branch         = 'main';
		$repo->primary_branch = 'main';
		$result               = $this->override_dot_org( 'plugin', $repo );
		delete_site_option( 'skip_updates' );
		$this->assertFalse( $result );
	}
}
