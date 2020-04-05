<?php

use Fragen\GitHub_Updater\Base;

class RunningTest extends WP_UnitTestCase {
	public function test_installed_apis() {
		$installed_apis = [
			'github_api'           => true,
			'bitbucket_api'        => true,
			'bitbucket_server_api' => true,
			'gitlab_api'           => true,
			'gitea_api'            => true,
			'zipfile_api'          => true,
		];

		$base = new Base();
		//echo "\n" . var_export($base::$installed_apis, true);
		$this->assertEqualSets($installed_apis, $base::$installed_apis);
	}
}
