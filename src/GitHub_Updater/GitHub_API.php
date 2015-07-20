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

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Get remote data from a GitHub repo.
 *
 * Class    GitHub_API
 * @package Fragen\GitHub_Updater
 * @author  Andy Fragen
 */
class GitHub_API extends API {

	/**
	 * Constructor.
	 *
	 * @param object $type
	 */
	public function __construct( $type ) {
		$this->type    = $type;
		parent::$hours = 12;
	}

	/**
	 * Read the remote file and parse headers.
	 *
	 * @param $file
	 *
	 * @return bool
	 */
	public function get_remote_info( $file ) {
		$response = $this->get_transient( $file );

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/contents/' . $file );
			if ( ! isset( $response->content ) ) {
				return false;
			}

			if ( $response ) {
				$contents = base64_decode( $response->content );
				$response = $this->get_file_headers( $contents, $this->type->type );
				$this->set_transient( $file, $response );
			}
		}

		if ( $this->validate_response( $response ) || ! is_array( $response ) ) {
			return false;
		}

		$this->set_file_info( $response, 'GitHub' );

		return true;
	}

	/**
	 * Get remote info for tags.
	 *
	 * @return bool
	 */
	public function get_remote_tag() {
		$repo_type = $this->return_repo_type();
		$response  = $this->get_transient( 'tags' );

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/tags' );

			if ( ! $response ) {
				$response = new \stdClass();
				$response->message = 'No tags found';
			}

			if ( $response ) {
				$this->set_transient( 'tags', $response );
			}
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		$this->parse_tags( $response, $repo_type );

		return true;
	}

	/**
	 * Read the remote CHANGES.md file.
	 *
	 * @param $changes
	 *
	 * @return bool
	 */
	public function get_remote_changes( $changes ) {
		$response = $this->get_transient( 'changes' );

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/contents/' . $changes  );

			if ( $response ) {
				$this->set_transient( 'changes', $response );
			}
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		$changelog = $this->get_transient( 'changelog' );

		if ( ! $changelog ) {
			$parser    = new \Parsedown;
			$changelog = $parser->text( base64_decode( $response->content ) );
			$this->set_transient( 'changelog', $changelog );
		}

		$this->type->sections['changelog'] = $changelog;

		return true;
	}

	/**
	 * Read and parse remote readme.txt.
	 *
	 * @return bool
	 */
	public function get_remote_readme() {
		if ( ! file_exists( $this->type->local_path . 'readme.txt' ) &&
		     ! file_exists( $this->type->local_path_extended . 'readme.txt' )
		) {
			return false;
		}

		$response = $this->get_transient( 'readme' );

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/contents/readme.txt' );
		}

		if ( $response && isset( $response->content ) ) {
			$parser   = new Readme_Parser;
			$response = $parser->parse_readme( base64_decode( $response->content ) );
			$this->set_transient( 'readme', $response );
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		$this->set_readme_info( $response );

		return true;
	}

	/**
	 * Read the repository meta from API.
	 *
	 * @return bool
	 */
	public function get_repo_meta() {
		$response   = $this->get_transient( 'meta' );
		$meta_query = '?q=' . $this->type->repo . '+user:' . $this->type->owner;

		if ( ! $response ) {
			$response = $this->api( '/search/repositories' . $meta_query );

			if ( $response ) {
				$this->set_transient( 'meta', $response );
			}
		}

		if ( $this->validate_response( $response ) || empty( $response->items ) ) {
			return false;
		}

		$this->type->repo_meta = $response->items[0];
		$this->_add_meta_repo_object();
		$this->get_remote_branches();

		return true;
	}

	/**
	 * Create array of branches and download links as array.
	 *
	 * @return bool
	 */
	public function get_remote_branches() {
		$branches = array();
		$response = $this->get_transient( 'branches' );

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/branches' );

			if ( $response ) {
				foreach ( $response as $branch ) {
					$branches[ $branch->name ] = $this->construct_download_link( false, $branch->name );
				}
				$this->type->branches = $branches;
				$this->set_transient( 'branches', $branches );
				return true;
			}
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		$this->type->branches = $response;

		return true;
	}

	/**
	 * Construct $this->type->download_link using Repository Contents API
	 * @url http://developer.github.com/v3/repos/contents/#get-archive-link
	 *
	 * @param boolean $rollback for theme rollback
	 * @param boolean $branch_switch for direct branch changing
	 *
	 * @return string $endpoint
	 */
	public function construct_download_link( $rollback = false, $branch_switch = false ) {
		/*
		 * Check if using GitHub Self-Hosted.
		 */
		if ( ! empty( $this->type->enterprise ) ) {
			$github_base = $this->type->enterprise;
		} else {
			$github_base = 'https://api.github.com';
		}

		$download_link_base = implode( '/', array( $github_base, 'repos', $this->type->owner, $this->type->repo, 'zipball/' ) );
		$endpoint           = '';

		/*
		 * Check for rollback.
		 */
		if ( ! empty( $_GET['rollback'] ) &&
		     ( isset( $_GET['action'] ) && 'upgrade-theme' === $_GET['action'] ) &&
		     ( isset( $_GET['theme'] ) && $_GET['theme'] === $this->type->repo )
		) {
			$endpoint .= $rollback;

			/*
			 * For users wanting to update against branch other than master
			 * or if not using tags, else use newest_tag.
			 */
		} elseif ( 'master' != $this->type->branch || empty( $this->type->tags ) ) {
			$endpoint .= $this->type->branch;
		} else {
			$endpoint .= $this->type->newest_tag;
		}

		/*
		 * Create endpoint for branch switching.
		 */
		if ( $branch_switch ) {
			$endpoint = $branch_switch;
		}

		$asset = $this->get_asset();
		if ( $asset && ! $branch_switch ) {
			return $asset;
		}

		if ( ! empty( parent::$options[ $this->type->repo ] ) ) {
			$endpoint = add_query_arg( 'access_token', parent::$options[ $this->type->repo ], $endpoint );
		} elseif ( ! empty( parent::$options['github_access_token'] ) && empty( $this->type->enterprise ) ) {
			$endpoint = add_query_arg( 'access_token', parent::$options['github_access_token'], $endpoint );
		}

		return $download_link_base . $endpoint;
	}

	/**
	 * Add remote data to type object.
	 */
	private function _add_meta_repo_object() {
		$this->type->rating       = $this->make_rating( $this->type->repo_meta );
		$this->type->last_updated = $this->type->repo_meta->pushed_at;
		$this->type->num_ratings  = $this->type->repo_meta->watchers;
		$this->type->private      = $this->type->repo_meta->private;
	}

	/**
	 * Create GitHub API endpoints.
	 *
	 * @param $git object
	 * @param $endpoint string
	 *
	 * @return string $endpoint
	 */
	protected function add_endpoints( $git, $endpoint ) {
		if ( ! empty( parent::$options[ $git->type->repo ] ) ) {
			$endpoint = add_query_arg( 'access_token', parent::$options[ $git->type->repo ], $endpoint );
		} elseif ( ! empty( parent::$options['github_access_token'] ) ) {
			$endpoint = add_query_arg( 'access_token', parent::$options['github_access_token'], $endpoint );
		}

		/*
		 * If a branch has been given, only check that for the remote info.
		 * If it's not been given, GitHub will use the Default branch.
		 */
		if ( ! empty( $git->type->branch ) ) {
			$endpoint = add_query_arg( 'ref', $git->type->branch, $endpoint );
		}

		/*
		 * If using GitHub Self-Hosted header return this endpoint.
		 */
		if ( ! empty( $git->type->enterprise ) ) {
			return $git->type->enterprise . remove_query_arg( 'access_token', $endpoint );
		}

		return $endpoint;
	}

	/**
	 * Calculate and store time until rate limit reset.
	 *
	 * @param $response
	 * @param $repo
	 */
	protected static function _ratelimit_reset( $response, $repo ) {
		if ( isset( $response['headers']['x-ratelimit-reset'] ) ) {
			$reset                       = (integer) $response['headers']['x-ratelimit-reset'];
			$wait                        = date( 'i', $reset - time() );
			parent::$error_code[ $repo ] = array_merge( parent::$error_code[ $repo ], array( 'git' => 'github', 'wait' => $wait ) );
		}
	}

	/**
	 * Get uploaded release asset to use in place of tagged release.
	 *
	 * @return bool|string
	 */
	protected function get_asset() {
		if ( empty( $this->type->newest_tag ) ) {
			return false;
		}
		$response = $this->get_transient( 'asset' );

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/releases/latest' );
			$this->set_transient( 'asset' , $response );
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		if ( $response instanceof \stdClass ) {

			if ( empty( $response->assets ) ) {
				$response          = new \stdClass();
				$response->message = false;
				$this->set_transient( 'asset', $response );
				return false;
			}
			foreach ( (array) $response->assets as $asset ) {
				if ( isset ( $asset->browser_download_url ) &&
				     false !== stristr( $asset->browser_download_url, $this->type->newest_tag )
				) {
					$this->set_transient( 'asset', $asset->browser_download_url );
					$response = $asset->browser_download_url;
				}
			}
		}

		if ( ! is_string( $response ) ) {
			return false;
		}

		if ( false !== stristr( $response, $this->type->newest_tag ) ) {
			if ( ! empty( parent::$options[ $this->type->repo ] ) ) {
				$response = add_query_arg( 'access_token', parent::$options[ $this->type->repo ], $response );
			} elseif ( ! empty( parent::$options['github_access_token'] ) && empty( $this->type->enterprise ) ) {
				$response = add_query_arg( 'access_token', parent::$options['github_access_token'], $response );
			}

			return $response;
		}
	}

}
