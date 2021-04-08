<?php

use Fragen\Git_Updater\Base;

class RunningTest extends WP_UnitTestCase {
	public function test_installed_apis() {
		$installed_apis = [ 'github_api' => true ];
		$git_servers    = [ 'github' => 'GitHub' ];

		$base = new Base();
		//echo "\n" . var_export($base::$installed_apis, true);
		$this->assertEqualSets($installed_apis, $base::$installed_apis);
		$this->assertEqualSets($git_servers, $base::$git_servers);
	}
}
