<?php
/**
 * Tests for Basic_Auth_Loader trait changes:
 * 1. Bug fix: isset($headers['host']) === $api_domain (bool vs string) corrected
 *    to isset($headers['host']) && $headers['host'] === $api_domain.
 * 2. Slug extraction restructured to check for array BEFORE sanitize_text_field,
 *    restoring TGMPA compatibility that was accidentally broken.
 *
 * @package Git_Updater
 */

use Fragen\Git_Updater\API\GitHub_API;
use Fragen\Git_Updater\Base;

class Test_Basic_Auth_Loader extends WP_UnitTestCase {

	/**
	 * @var GitHub_API
	 */
	private GitHub_API $api;

	public function set_up(): void {
		parent::set_up();
		new Base();
		$this->api = new GitHub_API( $this->make_type() );
	}

	public function tear_down(): void {
		unset( $_REQUEST['slug'], $_REQUEST['plugin'], $_REQUEST['plugins'], $_REQUEST['themes'] );
		parent::tear_down();
	}

	private function make_type(): stdClass {
		$type                 = new stdClass();
		$type->slug           = 'test-plugin';
		$type->git            = 'github';
		$type->type           = 'plugin';
		$type->owner          = 'test-owner';
		$type->branch         = 'master';
		$type->primary_branch = 'master';
		$type->enterprise     = false;
		$type->enterprise_api = null;
		$type->gist_id        = null;
		return $type;
	}

	/**
	 * Invoke the private get_credentials() method via reflection.
	 * In PHP 8.1+, setAccessible() is a no-op so invoke() works directly.
	 *
	 * @param string $url
	 * @return array<string, mixed>
	 */
	private function get_credentials( string $url ): array {
		$rm = new ReflectionMethod( $this->api, 'get_credentials' );
		return $rm->invoke( $this->api, $url );
	}

	/**
	 * Invoke the private get_slug_for_credentials() method via reflection.
	 *
	 * @return string|false
	 */
	private function get_slug_for_credentials( array $headers, array $repos, string $url, array $options ) {
		$rm = new ReflectionMethod( $this->api, 'get_slug_for_credentials' );
		return $rm->invoke( $this->api, $headers, $repos, $url, $options );
	}

	// -------------------------------------------------------------------------
	// Bug fix: isset($headers['host']) === $api_domain
	// -------------------------------------------------------------------------

	/**
	 * After the bug fix, when the URL host matches the configured api_domain
	 * (default: api.wordpress.org), credentials['api.wordpress'] must be the
	 * host string (truthy), triggering the early return path.
	 *
	 * Before the fix: isset(...) returned bool true, which was compared with
	 * === to the string 'api.wordpress.org', always yielding false — the early
	 * return was never reached.
	 */
	public function test_credentials_for_wordpress_org_host_are_truthy(): void {
		$credentials = $this->get_credentials( 'https://api.wordpress.org/plugins/info/1.0/' );

		$this->assertSame(
			'api.wordpress.org',
			$credentials['api.wordpress'],
			'credentials[api.wordpress] must equal the host string for api.wordpress.org URLs.'
		);
	}

	/**
	 * For any non-WordPress.org URL, credentials['api.wordpress'] must be
	 * false so that normal auth-header processing continues.
	 */
	public function test_credentials_for_github_api_host_are_false(): void {
		$credentials = $this->get_credentials( 'https://api.github.com/repos/owner/repo' );

		$this->assertFalse(
			$credentials['api.wordpress'],
			'credentials[api.wordpress] must be false for non-WordPress.org URLs.'
		);
	}

	/**
	 * add_auth_header() must return the original $args array unchanged for
	 * WordPress.org URLs — no auth header should be injected.
	 */
	public function test_add_auth_header_unchanged_for_wordpress_org_url(): void {
		$args   = [ 'headers' => [] ];
		$result = $this->api->add_auth_header( $args, 'https://api.wordpress.org/plugins/info/1.0/' );

		$this->assertSame( $args, $result );
	}

	// -------------------------------------------------------------------------
	// Slug extraction: array before sanitize (TGMPA fix)
	// -------------------------------------------------------------------------

	/**
	 * When $_REQUEST['slug'] is an array (as TGMPA sends it), the restructured
	 * code must detect the array BEFORE sanitize_text_field converts it to an
	 * empty string, and extract the last element.
	 */
	public function test_slug_extraction_handles_array_slug_request(): void {
		$_REQUEST['slug'] = [ 'my-plugin/my-plugin.php' ];

		$headers = [ 'host' => 'api.github.com' ];
		$slug    = $this->get_slug_for_credentials( $headers, [], 'https://api.github.com/', [] );

		$this->assertSame(
			'my-plugin',
			$slug,
			'Array slug should be popped and dirname extracted (TGMPA compatibility).'
		);
	}

	/**
	 * When $_REQUEST['slug'] is a plain string, it is sanitized normally.
	 */
	public function test_slug_extraction_handles_string_slug_request(): void {
		$_REQUEST['slug'] = 'my-plugin/my-plugin.php';

		$headers = [ 'host' => 'api.github.com' ];
		$slug    = $this->get_slug_for_credentials( $headers, [], 'https://api.github.com/', [] );

		$this->assertSame( 'my-plugin', $slug );
	}

	/**
	 * When $_REQUEST['slug'] is absent but $_REQUEST['plugin'] is set,
	 * the plugin key is used as the fallback.
	 */
	public function test_slug_extraction_falls_back_to_plugin_request_key(): void {
		unset( $_REQUEST['slug'] );
		$_REQUEST['plugin'] = 'my-plugin/my-plugin.php';

		$headers = [ 'host' => 'api.github.com' ];
		$slug    = $this->get_slug_for_credentials( $headers, [], 'https://api.github.com/', [] );

		$this->assertSame( 'my-plugin', $slug );
	}

	/**
	 * When neither slug nor plugin is in the request, slug returns false.
	 */
	public function test_slug_extraction_returns_false_when_no_request_keys(): void {
		unset( $_REQUEST['slug'], $_REQUEST['plugin'], $_REQUEST['plugins'], $_REQUEST['themes'] );

		$headers = [ 'host' => 'api.github.com' ];
		$slug    = $this->get_slug_for_credentials( $headers, [], 'https://api.github.com/', [] );

		$this->assertFalse( $slug );
	}
}
