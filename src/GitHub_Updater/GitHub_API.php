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
		$this->type     = $type;
		$this->response = $this->get_repo_cache();
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
				$this->set_repo_cache( $file, $response );
			}
		}

		if ( $this->validate_response( $response ) || ! is_array( $response ) ) {
			return false;
		}

		$response['dot_org'] = $this->get_dot_org_data();
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

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/tags' );

			if ( ! $response ) {
				$response          = new \stdClass();
				$response->message = 'No tags found';
			}

			if ( $response ) {
				$response = $this->parse_tag_response( $response );
				$this->set_repo_cache( 'tags', $response );
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
			$response = array();
			$content  = $this->get_local_info( $this->type, $changes );
			if ( $content ) {
				$response['changes'] = $content;
				$this->set_repo_cache( 'changes', $response );
			} else {
				$response = false;
			}
		}

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/contents/' . $changes );

			if ( $response ) {
				$response = $this->parse_changelog_response( $response );
				$this->set_repo_cache( 'changes', $response );
			}
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		$parser    = new \Parsedown;
		$changelog = $parser->text( base64_decode( $response['changes'] ) );

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
			$file     = base64_decode( $response->content );
			$parser   = new Readme_Parser( $file );
			$response = $parser->parse_data();
			$this->set_repo_cache( 'readme', $response );
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
		$response = isset( $this->response['meta'] ) ? $this->response['meta'] : false;

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo' );

			if ( $response ) {
				$response = $this->parse_meta_response( $response );
				$this->set_repo_cache( 'meta', $response );
			}
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		$this->type->repo_meta = $response;
		$this->add_meta_repo_object();

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
				$this->set_repo_cache( 'branches', $branches );

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

		/*
		 * If release asset.
		 */
		if ( $this->type->release_asset && '0.0.0' !== $this->type->newest_tag ) {
			return $this->get_github_release_asset_url();
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

		$endpoint = $this->add_access_token_endpoint( $this, $endpoint );

		return $download_link_base . $endpoint;
	}

	/**
	 * Get/process Language Packs.
	 * Language Packs cannot reside on GitHub Enterprise.
	 *
	 * @TODO Figure out how to serve raw file from GitHub Enterprise.
	 *
	 * @param array $headers Array of headers of Language Pack.
	 *
	 * @return bool When invalid response.
	 */
	public function get_language_pack( $headers ) {
		$response = ! empty( $this->response['languages'] ) ? $this->response['languages'] : false;
		$type     = explode( '_', $this->type->type );

		if ( ! $response ) {
			$response = $this->api( '/repos/' . $headers['owner'] . '/' . $headers['repo'] . '/contents/language-pack.json' );

			if ( $this->validate_response( $response ) ) {
				return false;
			}

			if ( $response ) {
				$contents = base64_decode( $response->content );
				$response = json_decode( $contents );

				foreach ( $response as $locale ) {
					$package = array( 'https://github.com', $headers['owner'], $headers['repo'], 'blob/master' );
					$package = implode( '/', $package ) . $locale->package;
					$package = add_query_arg( array( 'raw' => 'true' ), $package );

					$response->{$locale->language}->package = $package;
					$response->{$locale->language}->type    = $type[1];
					$response->{$locale->language}->version = $this->type->remote_version;
				}

				$this->set_repo_cache( 'languages', $response );
			}
		}
		$this->type->language_packs = $response;
	}

	/**
	 * Add appropriate access token to endpoint.
	 *
	 * @param $git
	 * @param $endpoint
	 *
	 * @access private
	 *
	 * @return string
	 */
	private function add_access_token_endpoint( $git, $endpoint ) {
		// This will return if checking during shiny updates.
		if ( ! isset( parent::$options ) ) {
			return $endpoint;
		}

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

		$endpoint = $this->add_access_token_endpoint( $git, $endpoint );

		/*
		 * Remove branch endpoint if a translation file.
		 */
		$repo = explode( '/', $endpoint );
		if ( isset( $repo[3] ) && $repo[3] !== $git->type->repo ) {
			$endpoint = remove_query_arg( 'ref', $endpoint );
		}

		/*
		 * If using GitHub Enterprise header return this endpoint.
		 */
		if ( ! empty( $git->type->enterprise_api ) ) {
			return $git->type->enterprise_api . $endpoint;
		}

		return $endpoint;
	}

	/**
	 * Calculate and store time until rate limit reset.
	 *
	 * @param $response
	 * @param $repo
	 */
	public static function ratelimit_reset( $response, $repo ) {
		if ( isset( $response['headers']['x-ratelimit-reset'] ) ) {
			$reset                       = (integer) $response['headers']['x-ratelimit-reset'];
			$wait                        = date( 'i', $reset - time() );
			parent::$error_code[ $repo ] = array_merge( parent::$error_code[ $repo ], array(
				'git'  => 'github',
				'wait' => $wait,
			) );
		}
	}

	/**
	 * Parse API response call and return only array of tag numbers.
	 *
	 * @param object|array $response Response from API call.
	 *
	 * @return object|array $arr Array of tag numbers, object is error.
	 */
	protected function parse_tag_response( $response ) {
		if ( isset( $response->message ) ) {
			return $response;
		}

		$arr = array();
		array_map( function( $e ) use ( &$arr ) {
			$arr[] = $e->name;

			return $arr;
		}, (array) $response );

		return $arr;
	}

	/**
	 * Parse API response and return array of meta variables.
	 *
	 * @param object $response Response from API call.
	 *
	 * @return array $arr Array of meta variables.
	 */
	protected function parse_meta_response( $response ) {
		$arr      = array();
		$response = array( $response );

		array_filter( $response, function( $e ) use ( &$arr ) {
			$arr['private']      = $e->private;
			$arr['last_updated'] = $e->pushed_at;
			$arr['watchers']     = $e->watchers;
			$arr['forks']        = $e->forks;
			$arr['open_issues']  = $e->open_issues;
		} );

		return $arr;
	}

	/**
	 * Parse API response and return array with changelog in base64.
	 *
	 * @param object $response Response from API call.
	 *
	 * @return array $arr Array of changes in base64.
	 */
	protected function parse_changelog_response( $response ) {
		$arr      = array();
		$response = array( $response );

		array_filter( $response, function( $e ) use ( &$arr ) {
			$arr['changes'] = $e->content;
		} );

		return $arr;
	}

	/**
	 * Return the AWS download link for a GitHub release asset.
	 *
	 * @since 6.1.0
	 * @uses  Requests, requires WP 4.6
	 *
	 * @return array|bool|object|\stdClass
	 */
	private function get_github_release_asset_url() {
		$response = isset( $this->response['release_asset_url'] ) ? $this->response['release_asset_url'] : false;

		if ( $this->exit_no_update( $response ) ) {
			return false;
		}

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/releases/latest' );

			if ( ! $response ) {
				$response          = new \stdClass();
				$response->message = 'No release asset found';
			}

			if ( $response ) {
				add_filter( 'http_request_args', array( &$this, 'set_github_release_asset_header' ) );

				$url          = $this->add_access_token_endpoint( $this, $response->assets[0]->url );
				$response_new = wp_remote_get( $url );

				remove_filter( 'http_request_args', array( &$this, 'set_github_release_asset_header' ) );

				if ( is_wp_error( $response_new ) ) {
					Messages::instance()->create_error_message( $response_new );

					return false;
				}

				if ( $response_new['http_response'] instanceof \WP_HTTP_Requests_Response ) {
					$response_object  = $response_new['http_response']->get_response_object();
					$response_headers = $response_object->history[0]->headers;
					$download_link    = $response_headers->getValues( 'location' );
				} else {
					return false;
				}

				$response = array();
				$response = $download_link[0];
				$this->set_repo_cache( 'release_asset_url', $response );
			}
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		return $response;
	}

	/**
	 * Set HTTP header for following GitHub release assets.
	 *
	 * @since 6.1.0
	 *
	 * @param        $args
	 * @param string $url
	 *
	 * @return mixed $args
	 */
	public function set_github_release_asset_header( $args, $url = '' ) {
		$args['headers']['accept'] = 'application/octet-stream';

		return $args;
	}
}
