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
 * Class GitHub_API
 *
 * Get remote data from a GitHub repo.
 *
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
		parent::$hours  = 12;
		$this->type     = $type;
		$this->response = $this->get_transient();
	}

	/**
	 * Read the remote file and parse headers.
	 *
	 * @param $file
	 *
	 * @return bool
	 */
	public function get_remote_info( $file ) {
		$response = isset( $this->response[ $file ] ) ? $this->response[ $file ] : false;

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

		$this->set_file_info( $response );

		return true;
	}

	/**
	 * Get remote info for tags.
	 *
	 * @return bool
	 */
	public function get_remote_tag() {
		$repo_type = $this->return_repo_type();
		$response  = isset( $this->response['tags'] ) ? $this->response['tags'] : false;

		if ( $this->exit_no_update( $response ) && 'theme' !== $repo_type['type'] ) {
			return false;
		}

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/tags' );

			if ( ! $response ) {
				$response          = new \stdClass();
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
		$response = isset( $this->response['changes'] ) ? $this->response['changes'] : false;

		/*
		 * Set response from local file if no update available.
		 */
		if ( ! $response && ! $this->can_update( $this->type ) ) {
			$response = new \stdClass();
			$content  = $this->get_local_info( $this->type, $changes );
			if ( $content ) {
				$response->content = $content;
				$this->set_transient( 'changes', $response );
			} else {
				$response = false;
			}
		}

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/contents/' . $changes );

			if ( $response ) {
				$this->set_transient( 'changes', $response );
			}
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		$changelog = isset( $this->response['changelog'] ) ? $this->response['changelog'] : false;

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

		$response = isset( $this->response['readme'] ) ? $this->response['readme'] : false;

		/*
		 * Set $response from local file if no update available.
		 */
		if ( ! $response && ! $this->can_update( $this->type ) ) {
			$response = new \stdClass();
			$content  = $this->get_local_info( $this->type, 'readme.txt' );
			if ( $content ) {
				$response->content = $content;
			} else {
				$response = false;
			}
		}

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
		$response   = isset( $this->response['meta'] ) ? $this->response['meta'] : false;
		$response   = ! isset( $response->items ) ? $response : false;
		$repos      = isset( $this->response[ $this->type->owner ] ) ? $this->response[ $this->type->owner ] : false;
		$meta_query = '?q=' . $this->type->repo . '+user:' . $this->type->owner;

		if ( $this->exit_no_update( $response ) ) {
			return false;
		}

		if ( ! $response ) {
			$response = $this->api( '/search/repositories' . $meta_query );
			$response = ! empty( $response->items[0] ) ? $response->items[0] : false;

			if ( ! $repos ) {
				$repos = $this->api( '/users/' . $this->type->owner . '/repos' );
				$this->set_transient( $this->type->owner, $response );
			}

			if ( ! $response ) {
				foreach ( $repos as $repo ) {
					if ( $this->type->repo === $repo->name ) {
						$response = $repo;
						break;
					}
				}
			}

			if ( $response ) {
				$this->set_transient( 'meta', $response );
			}
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		$this->type->repo_meta = $response;
		$this->_add_meta_repo_object();

		return true;
	}

	/**
	 * Create array of branches and download links as array.
	 *
	 * @return bool
	 */
	public function get_remote_branches() {
		$branches = array();
		$response = isset( $this->response['branches'] ) ? $this->response['branches'] : false;

		if ( $this->exit_no_update( $response, true ) ) {
			return false;
		}

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
	 * @param boolean $rollback      for theme rollback
	 * @param boolean $branch_switch for direct branch changing
	 *
	 * @return string $endpoint
	 */
	public function construct_download_link( $rollback = false, $branch_switch = false ) {
		/*
		 * Check if using GitHub Self-Hosted.
		 */
		if ( ! empty( $this->type->enterprise_api ) ) {
			$github_base = $this->type->enterprise_api;
		} else {
			$github_base = 'https://api.github.com';
		}

		$download_link_base = implode( '/', array(
			$github_base,
			'repos',
			$this->type->owner,
			$this->type->repo,
			'zipball/',
		) );
		$endpoint           = '';

		if ( $this->type->release_asset && '0.0.0' !== $this->type->newest_tag ) {
			return $this->make_release_asset_download_link();
		}

		/*
		 * Check for rollback.
		 */
		if ( ! empty( $_GET['rollback'] ) &&
		     ( isset( $_GET['action'] ) && 'upgrade-theme' === $_GET['action'] ) &&
		     ( isset( $_GET['theme'] ) && $this->type->repo === $_GET['theme'] )
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

		$endpoint = $this->_add_access_token_endpoint( $this, $endpoint );

		return $download_link_base . $endpoint;
	}

	/**
	 * Add appropriate access token to endpoint.
	 *
	 * @param $git
	 * @param $endpoint
	 *
	 * @return string
	 */
	private function _add_access_token_endpoint( $git, $endpoint ) {
		// Add GitHub.com access token.
		if ( ! empty( parent::$options['github_access_token'] ) ) {
			$endpoint = add_query_arg( 'access_token', parent::$options['github_access_token'], $endpoint );
		}

		// Add GitHub Enterprise access token.
		if ( ! empty( $git->type->enterprise ) &&
		     ! empty( parent::$options['github_enterprise_token'] )
		) {
			$endpoint = remove_query_arg( 'access_token', $endpoint );
			$endpoint = add_query_arg( 'access_token', parent::$options['github_enterprise_token'], $endpoint );
		}

		// Add repo access token.
		if ( ! empty( parent::$options[ $git->type->repo ] ) ) {
			$endpoint = remove_query_arg( 'access_token', $endpoint );
			$endpoint = add_query_arg( 'access_token', parent::$options[ $git->type->repo ], $endpoint );
		}

		return $endpoint;
	}

	/**
	 * Create GitHub API endpoints.
	 *
	 * @param $git      object
	 * @param $endpoint string
	 *
	 * @return string $endpoint
	 */
	protected function add_endpoints( $git, $endpoint ) {

		/*
		 * If a branch has been given, only check that for the remote info.
		 * If it's not been given, GitHub will use the Default branch.
		 */
		if ( ! empty( $git->type->branch ) ) {
			$endpoint = add_query_arg( 'ref', $git->type->branch, $endpoint );
		}

		$endpoint = $this->_add_access_token_endpoint( $git, $endpoint );

		/*
		 * If using GitHub Enterprise header return this endpoint.
		 */
		if ( ! empty( $git->type->enterprise_api ) ) {
			return $git->type->enterprise_api . $endpoint;
		}

		return $endpoint;
	}

	/**
	 * Add remote data to type object.
	 *
	 * @access private
	 */
	private function _add_meta_repo_object() {
		$this->type->rating       = $this->make_rating( $this->type->repo_meta );
		$this->type->last_updated = $this->type->repo_meta->pushed_at;
		$this->type->num_ratings  = $this->type->repo_meta->watchers;
		$this->type->private      = $this->type->repo_meta->private;
	}

	/**
	 * Calculate and store time until rate limit reset.
	 *
	 * @param $response
	 * @param $repo
	 */
	protected static function ratelimit_reset( $response, $repo ) {
		if ( isset( $response['headers']['x-ratelimit-reset'] ) ) {
			$reset                       = (integer) $response['headers']['x-ratelimit-reset'];
			$wait                        = date( 'i', $reset - time() );
			parent::$error_code[ $repo ] = array_merge( parent::$error_code[ $repo ], array(
				'git'  => 'github',
				'wait' => $wait,
			) );
		}
	}

}
