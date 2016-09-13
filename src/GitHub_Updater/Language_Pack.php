<?php
/**
 * Created by PhpStorm.
 * User: afragen
 * Date: 9/12/16
 * Time: 4:12 PM
 */

namespace Fragen\GitHub_Updater;


class Language_Pack extends Base {

	protected $repo;

	protected $repo_api;

	public function __construct( $repo, $api ) {
		if ( empty( $repo->languages) ) {
			return false;
		}

		$this->repo = $repo;
		$this->repo_api = $api;
		$this->run();
	}

	protected function run() {
		$headers = $this->parse_header_uri( $this->repo->languages );
		$this->repo_api->get_language_pack( $headers);
	}

}
