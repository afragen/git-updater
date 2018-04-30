<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

namespace Fragen\GitHub_Updater\Traits;

use Fragen\GitHub_Updater\API\GitHub_API,
	Fragen\GitHub_Updater\API\Bitbucket_API,
	Fragen\GitHub_Updater\API\Bitbucket_Server_API,
	Fragen\GitHub_Updater\API\GitLab_API,
	Fragen\GitHub_Updater\API\Gitea_API;


/**
 * Trait API_Trait
 *
 * @package Fragen\GitHub_Updater
 */
trait API_Trait {
	use GHU_Trait;

	/**
	 * Adds custom user agent for GitHub Updater.
	 *
	 * @access public
	 *
	 * @param array  $args Existing HTTP Request arguments.
	 * @param string $url  URL being passed.
	 *
	 * @return array Amended HTTP Request arguments.
	 */
	public static function http_request_args( $args, $url ) {
		$args['sslverify'] = true;
		if ( false === stripos( $args['user-agent'], 'GitHub Updater' ) ) {
			$args['user-agent']    .= '; GitHub Updater - https://github.com/afragen/github-updater';
			$args['wp-rest-cache'] = array( 'tag' => 'github-updater' );
		}

		return $args;
	}

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
	 * Validate wp_remote_get response.
	 *
	 * @access protected
	 *
	 * @param \stdClass $response The response.
	 *
	 * @return bool true if invalid
	 */
	protected function validate_response( $response ) {
		return empty( $response ) || isset( $response->message );
	}

	/**
	 * Check if a local file for the repository exists.
	 * Only checks the root directory of the repository.
	 *
	 * @access protected
	 *
	 * @param string $filename The filename to check for.
	 *
	 * @return bool
	 */
	protected function local_file_exists( $filename ) {
		return file_exists( $this->type->local_path . $filename );
	}

	/**
	 * Sort tags and set object data.
	 *
	 * @param array $parsed_tags
	 *
	 * @return bool
	 */
	protected function sort_tags( $parsed_tags ) {
		if ( empty( $parsed_tags ) ) {
			return false;
		}

		list( $tags, $rollback ) = $parsed_tags;
		usort( $tags, 'version_compare' );
		krsort( $rollback );

		$newest_tag     = array_slice( $tags, - 1, 1, true );
		$newest_tag_key = key( $newest_tag );
		$newest_tag     = $tags[ $newest_tag_key ];

		$this->type->newest_tag = $newest_tag;
		$this->type->tags       = $tags;
		$this->type->rollback   = $rollback;

		return true;
	}

	/**
	 * Get local file info if no update available. Save API calls.
	 *
	 * @param $repo
	 * @param $file
	 *
	 * @return null|string
	 */
	protected function get_local_info( $repo, $file ) {
		$response = false;

		if ( isset( $_POST['ghu_refresh_cache'] ) ) {
			return $response;
		}

		if ( is_dir( $repo->local_path ) &&
		     file_exists( $repo->local_path . $file )
		) {
			$response = file_get_contents( $repo->local_path . $file );
		}

		switch ( $repo->type ) {
			case 'bitbucket_plugin':
			case 'bitbucket_theme':
				break;
			default:
				$response = base64_encode( $response );
				break;
		}

		return $response;
	}

	/**
	 * Set repo object file info.
	 *
	 * @param $response
	 */
	protected function set_file_info( $response ) {
		$this->type->transient            = $response;
		$this->type->remote_version       = strtolower( $response['Version'] );
		$this->type->requires_php_version = ! empty( $response['Requires PHP'] ) ? $response['Requires PHP'] : $this->type->requires_php_version;
		$this->type->requires_wp_version  = ! empty( $response['Requires WP'] ) ? $response['Requires WP'] : $this->type->requires_wp_version;
		$this->type->dot_org              = $response['dot_org'];
	}

	/**
	 * Add remote data to type object.
	 *
	 * @access protected
	 */
	protected function add_meta_repo_object() {
		$this->type->rating       = $this->make_rating( $this->type->repo_meta );
		$this->type->last_updated = $this->type->repo_meta['last_updated'];
		$this->type->num_ratings  = $this->type->repo_meta['watchers'];
		$this->type->is_private   = $this->type->repo_meta['private'];
	}

	/**
	 * Create some sort of rating from 0 to 100 for use in star ratings.
	 * I'm really just making this up, more based upon popularity.
	 *
	 * @param $repo_meta
	 *
	 * @return integer
	 */
	protected function make_rating( $repo_meta ) {
		$watchers    = empty( $repo_meta['watchers'] ) ? $this->type->watchers : $repo_meta['watchers'];
		$forks       = empty( $repo_meta['forks'] ) ? $this->type->forks : $repo_meta['forks'];
		$open_issues = empty( $repo_meta['open_issues'] ) ? $this->type->open_issues : $repo_meta['open_issues'];

		$rating = abs( (int) round( $watchers + ( $forks * 1.5 ) - ( $open_issues * 0.1 ) ) );

		if ( 100 < $rating ) {
			return 100;
		}

		return $rating;
	}

	/**
	 * Set data from readme.txt.
	 * Prefer changelog from CHANGES.md.
	 *
	 * @param array $readme Array of parsed readme.txt data
	 *
	 * @return bool
	 */
	protected function set_readme_info( $readme ) {
		foreach ( (array) $this->type->sections as $section => $value ) {
			if ( 'description' === $section ) {
				continue;
			}
			$readme['sections'][ $section ] = $value;
		}

		$readme['remaining_content'] = ! empty( $readme['remaining_content'] ) ? $readme['remaining_content'] : null;
		if ( empty( $readme['sections']['other_notes'] ) ) {
			unset( $readme['sections']['other_notes'] );
		} else {
			$readme['sections']['other_notes'] .= $readme['remaining_content'];
		}
		unset( $readme['sections']['screenshots'], $readme['sections']['installation'] );
		$readme['sections']       = ! empty( $readme['sections'] ) ? $readme['sections'] : array();
		$this->type->sections     = array_merge( (array) $this->type->sections, (array) $readme['sections'] );
		$this->type->tested       = isset( $readme['tested'] ) ? $readme['tested'] : null;
		$this->type->requires     = isset( $readme['requires'] ) ? $readme['requires'] : null;
		$this->type->donate_link  = isset( $readme['donate_link'] ) ? $readme['donate_link'] : null;
		$this->type->contributors = isset( $readme['contributors'] ) ? $readme['contributors'] : null;

		return true;
	}

}
