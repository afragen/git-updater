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


/**
 * Class Language_Pack
 *
 * @package Fragen\GitHub_Updater
 */
class Language_Pack extends Base {

	/**
	 * Variable containing the plugin/theme object.
	 *
	 * @var object
	 */
	protected $repo;

	/**
	 * Variable containing the git host API object.
	 *
	 * @var
	 */
	protected $repo_api;

	/**
	 * Language_Pack constructor.
	 *
	 * @param object $repo Plugin/Theme object.
	 * @param object $api  Git host API object.
	 */
	public function __construct( $repo, $api ) {
		if ( empty( $repo->languages ) ) {
			return false;
		}

		$this->repo     = $repo;
		$this->repo_api = $api;
		$this->run();
	}

	/**
	 * Do the Language Pack integration.
	 */
	protected function run() {
		$headers = $this->parse_header_uri( $this->repo->languages );
		$this->repo_api->get_language_pack( $headers );
	}

}
