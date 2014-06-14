<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

/**
 * Get remote data from a Bitbucket repo.
 *
 * @package GitHub_Updater_Bitbucket_API
 * @author  Andy Fragen
 */
class GitHub_Updater_BitBucket_API extends GitHub_Updater {

	/**
	 * Constructor.
	 *
	 * @since 2.1.0
	 *
	 * @param string $type
	 */
	public function __construct( $type ) {
		$this->type  = $type;
		self::$hours = 12;

		add_filter( 'http_request_args', array( $this, 'maybe_authenticate_http' ), 10, 2 );
	}

	/**
	 * Call the API and return a json decoded body.
	 *
	 * @since 1.0.0
	 *
	 * @see http://developer.github.com/v3/
	 *
	 * @param string $url
	 *
	 * @return boolean|object
	 */
	protected function api( $url ) {
		$response      = wp_remote_get( $this->get_api_url( $url ) );
		$code          = wp_remote_retrieve_response_code( $response );
		$allowed_codes = array( 200, 404 );

		if ( is_wp_error( $response ) ) { return false; }
		if ( ! in_array( $code, $allowed_codes, true ) ) { return false; }

		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Return API url.
	 *
	 * @since 1.0.0
	 *
	 * @param string $endpoint
	 *
	 * @return string
	 */
	protected function get_api_url( $endpoint ) {
		$segments = array(
			'owner' => $this->type->owner,
			'repo'  => $this->type->repo,
		);

		/**
		 * Add or filter the available segments that are used to replace placeholders.
		 *
		 * @since 1.5.0
		 *
		 * @param array $segments List of segments.
		 */
		$segments = apply_filters( 'github_updater_api_segments', $segments );

		foreach ( $segments as $segment => $value ) {
			$endpoint = str_replace( '/:' . $segment, '/' . $value, $endpoint );
		}

		/*
		if ( ! empty( $this->type->access_token ) ) {
			$endpoint = add_query_arg( 'access_token', $this->type->access_token, $endpoint );
		}

		// If a branch has been given, only check that for the remote info.
		// If it's not been given, GitHub will use the Default branch.
		if ( ! empty( $this->type->branch ) ) {
			$endpoint = add_query_arg( 'ref', $this->type->branch, $endpoint );
		}
		*/

		return 'https://bitbucket.org/api/' . $endpoint;
	}

	/**
	 * Read the remote file.
	 *
	 * Uses a transient to limit the calls to the API.
	 *
	 * @since 1.0.0
	 */
	public function get_remote_info( $file ) {
		$response = $this->get_transient( $file );

		if ( ! $response ) {
			if ( ! isset( $this->type->branch ) ) {
				$this->type->branch = $this->get_default_branch( $response );
			}
			$response = $this->api( '1.0/repositories/:owner/:repo/src/' . trailingslashit($this->type->branch) . $file );

			if ( $response ) {
				$this->set_transient( $file, $response );
			}
		}

		$this->type->branch = $this->get_default_branch( $response );

		if ( ! $response ) { return false; }
		if ( isset( $response->message ) ) { return false; }

		$this->type->transient = $response;
		preg_match( '/^[ \t\/*#@]*Version\:\s*(.*)$/im', $response->data, $matches );

		if ( ! empty( $matches[1] ) ) {
			$this->type->remote_version = trim( $matches[1] );
		}

		return true;
	}

	/**
	 * Parse the remote info to find what the default branch is.
	 *
	 * If we've had to call this method, we know that a branch header has not been provided.
	 * As such the remote info was retrieved with a ?ref=... query argument.
	 *
	 * @since 1.5.0
	 * @param array API object
	 *
	 * @return string Default branch name.
	 */
	protected function get_default_branch( $response ) {
		if ( ! empty( $this->type->branch ) ) {
			return $this->type->branch;
		}

		// If we can't contact BitBucket API, then assume a sensible default in case the non-API part of BitBucket is working.
		if ( ! $response ) { return 'master'; }

	}

	/**
	 * Parse the remote info to find most recent tag if tags exist
	 *
	 * Uses a transient to limit the calls to the API.
	 *
	 * @since 1.7.0
	 *
	 * @return string latest tag.
	 */
	public function get_remote_tag() {
		$download_link_base = 'https://bitbucket.org/' . trailingslashit( $this->type->owner ) . $this->type->repo . '/get/';
		$response           = $this->get_transient( 'tags' );

		if ( ! $response ) {
			$response = $this->api( '1.0/repositories/:owner/:repo/tags' );
			$arr_resp = (array) $response;

			if ( ! $response || ! $arr_resp ) {
				$response->message = 'No tags found';
			}

			if ( $response ) {
				$this->set_transient( 'tags', $response );
			}
		}

		if ( ! $response ) { return false; }
		if ( isset( $response->message ) ) { return false; }

		// Sort and get newest tag
		$tags     = array();
		$rollback = array();
		if ( false !== $response ) {
			foreach ( (array) $response as $num => $tag ) {
				if ( isset( $num ) ) {
					$tags[]           = $num;
					$rollback[ $num ] = $download_link_base . $num . '.zip';
				}
			}
		}

		if ( empty( $tags ) ) { return false; }  // no tags are present, exit early

		usort( $tags, 'version_compare' );
		krsort( $rollback );

		$newest_tag             = null;
		$newest_tag_key         = key( array_slice( $tags, -1, 1, true ) );
		$newest_tag             = $tags[ $newest_tag_key ];

		$this->type->newest_tag = $newest_tag;
		$this->type->tags       = $tags;
		$this->type->rollback   = $rollback;
	}

	/**
	 * Construct $download_link
	 *
	 * @since 1.9.0
	 *
	 * @param boolean $rollback for theme rollback
	 * 
	 * @return URI
	 */
	public function construct_download_link( $rollback = false ) {
		$download_link_base = 'https://bitbucket.org/' . trailingslashit( $this->type->owner ) . $this->type->repo . '/get/';
		$endpoint           = '';

		// check for rollback
		if ( ! empty( $_GET['rollback'] ) && 'upgrade-theme' === $_GET['action'] && $_GET['theme'] === $this->type->repo ) {
			$endpoint .= $rollback . '.zip';
		
		// for users wanting to update against branch other than master or not using tags, else use newest_tag
		} else if ( ( 'master' != $this->type->branch && ( -1 != version_compare( $this->type->remote_version, $this->type->local_version ) ) || ( '0.0.0' === $this->type->newest_tag ) ) ) {
			$endpoint .= $this->type->branch . '.zip';
		} else {
			$endpoint .= $this->type->newest_tag . '.zip';
		}

		return $download_link_base . $endpoint;
	}

	/**
	 * Read the remote CHANGES.md file
	 *
	 * Uses a transient to limit calls to the API.
	 *
	 * @since 1.9.0
	 * @param $changes
	 *
	 * @return bool
	 */
	public function get_remote_changes( $changes ) {
		// early exit if $changes file doesn't exist locally. Saves an API call.
		if ( ! file_exists( $this->type->local_path . $changes ) ) { return false; }

		if ( ! class_exists( 'MarkdownExtra_Parser' ) && ! function_exists( 'Markdown' ) ) {
			require_once 'markdown.php';
		}

		$response = $this->get_transient( 'changes' );

		if ( ! $response ) {
			$response = $this->api( '1.0/repositories/:owner/:repo/src/' . trailingslashit($this->type->branch) . $changes  );

			if ( ! $response ) {
				$response['message'] = 'No CHANGES.md found';
				$response = (object) $response;
			}

			if ( $response ) {
				$this->set_transient( 'changes', $response );
			}
		}

		if ( ! $response ) { return false; }
		if ( isset( $response->message ) ) { return false; }

		$changelog = $this->get_transient( 'changelog' );

		if ( ! $changelog ) {
			if ( function_exists( 'Markdown' ) ) {
				$changelog = Markdown( $response->data );
			} else {
				$changelog = '<pre>' . $response->data . '</pre>';
			}
			$this->set_transient( 'changelog', $changelog );
		}

		$this->type->sections['changelog'] = $changelog;
	}
	
	/**
	 * Read the repository meta from API
	 *
	 * Uses a transient to limit calls to the API
	 * @since 2.2.0
	 * @return base64 decoded repository meta data
	 */
	public function get_repo_meta() {
		$response = $this->get_transient( 'meta' );

		if ( ! $response ) {
			$response = $this->api( '2.0/repositories/:owner/:repo' );

			if ( $response ) {
				$this->set_transient( 'meta', $response );
			}
		}

		if ( ! $response ) { return false; }
		if ( isset( $response->message ) ) { return false; }

		$this->type->repo_meta = $response;
		$this->add_meta_repo_object();
	}

	/**
	 * Add remote data to type object
	 *
	 * @since 2.2.0
	 */
	private function add_meta_repo_object() {
		$this->type->rating       = $this->make_rating( $this->type->repo_meta );
		$this->type->last_updated = $this->type->repo_meta->updated_on;
		$this->type->num_ratings  = $this->type->watchers;
	}

}