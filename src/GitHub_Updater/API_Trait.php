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

use Fragen\Singleton,
	Fragen\GitHub_Updater\API\GitHub_API,
	Fragen\GitHub_Updater\API\Bitbucket_API,
	Fragen\GitHub_Updater\API\Bitbucket_Server_API,
	Fragen\GitHub_Updater\API\GitLab_API,
	Fragen\GitHub_Updater\API\Gitea_API;


trait API_Trait {

	/**
	 * Get repo's API.
	 *
	 * @param string         $type
	 * @param bool|\stdClass $repo
	 *
	 * @return \Fragen\GitHub_Updater\API\Bitbucket_API|
	 * \Fragen\GitHub_Updater\API\Bitbucket_Server_API|
	 * \Fragen\GitHub_Updater\API\Gitea_API|
	 * \Fragen\GitHub_Updater\API\GitHub_API|
	 * \Fragen\GitHub_Updater\API\GitLab_API $repo_api
	 */
	public function get_repo_api( $type, $repo = false ) {
		$repo_api = null;
		$repo     = $repo ?: new \stdClass();
		switch ( $type ) {
			case 'github_plugin':
			case 'github_theme':
				$repo_api = new GitHub_API( $repo );
				break;
			case 'bitbucket_plugin':
			case 'bitbucket_theme':
				if ( ! empty( $repo->enterprise ) ) {
					$repo_api = new Bitbucket_Server_API( $repo );
				} else {
					$repo_api = new Bitbucket_API( $repo );
				}
				break;
			case 'gitlab_plugin':
			case 'gitlab_theme':
				$repo_api = new GitLab_API( $repo );
				break;
			case 'gitea_plugin':
			case 'gitea_theme':
				$repo_api = new Gitea_API( $repo );
				break;
		}

		return $repo_api;
	}

	/**
	 * Returns repo cached data.
	 *
	 * @access protected
	 *
	 * @param string|bool $repo Repo name or false.
	 *
	 * @return array|bool The repo cache. False if expired.
	 */
	public function get_repo_cache( $repo = false ) {
		if ( ! $repo ) {
			$repo = isset( $this->type->repo ) ? $this->type->repo : 'ghu';
		}
		$cache_key = 'ghu-' . md5( $repo );
		$cache     = get_site_option( $cache_key );

		if ( empty( $cache['timeout'] ) || time() > $cache['timeout'] ) {
			return false;
		}

		return $cache;
	}

	/**
	 * Sets repo data for cache in site option.
	 *
	 * @access protected
	 *
	 * @param string      $id       Data Identifier.
	 * @param mixed       $response Data to be stored.
	 * @param string|bool $repo     Repo name or false.
	 * @param string|bool $timeout  Timeout for cache.
	 *                              Default is static::$hours (12 hours).
	 *
	 * @return bool
	 */
	public function set_repo_cache( $id, $response, $repo = false, $timeout = false ) {
		if ( ! $repo ) {
			$repo = isset( $this->type->repo ) ? $this->type->repo : 'ghu';
		}
		$cache_key = 'ghu-' . md5( $repo );
		$timeout   = $timeout ? $timeout : '+' . static::$hours . ' hours';

		$this->response['timeout'] = strtotime( $timeout );
		$this->response[ $id ]     = $response;

		update_site_option( $cache_key, $this->response );

		return true;
	}

	/**
	 * Returns static class variable $error_code.
	 *
	 * @return array self::$error_code
	 */
	public function get_error_codes() {
		$api = Singleton::get_instance('API', $this);
		return $api::$error_code;
	}

}
