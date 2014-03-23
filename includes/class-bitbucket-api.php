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
 * Get remote data from a GitHub repo.
 *
 * @package GitHub_Updater_API
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
		self::$hours = 4;

		if ( ! empty( $this->type->timeout ) ) {
			self::$hours = (float) $this->type->timeout;
		}

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
		$response = wp_remote_get( $this->get_api_url( $url ) );

		if ( is_wp_error( $response ) || ( ( '200' || '404' ) != wp_remote_retrieve_response_code( $response ) ) ) {
			return false;
		}

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
		$response = get_site_transient( 'ghu-' . md5( $this->type->repo . $file ) );

		if ( ! $response && isset( $this->type->branch ) ) {
			$response = $this->api( '1.0/repositories/:owner/:repo/src/' . trailingslashit($this->type->branch) . $file );

			if ( $response ) {
				set_site_transient( 'ghu-' . md5( $this->type->repo . $file ), $response, ( self::$hours * HOUR_IN_SECONDS ) );
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
		$response           = get_site_transient( 'ghu-' . md5( $this->type->repo . 'tags' ) );

		if ( ! $response ) {
			$response = $this->api( '1.0/repositories/:owner/:repo/tags' );
			$arr_resp = (array) $response;

			if ( ! $response || ! $arr_resp ) {
				$response->message = 'No tags found';
			}

			if ( $response ) {
				set_site_transient( 'ghu-' . md5( $this->type->repo . 'tags' ), $response, ( self::$hours * HOUR_IN_SECONDS ) );
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
	 * @return base64 decoded CHANGES.md or false
	 */
	public function get_remote_changes( $changes ) {
		if ( ! class_exists( 'MarkdownExtra_Parser' ) && ! function_exists( 'Markdown' ) ) {
			require_once 'markdown.php';
		}

		$response = get_site_transient( 'ghu-' . md5( $this->type->repo . 'changes' ) );

		if ( ! $response ) {
			$response = $this->api( '1.0/repositories/:owner/:repo/src/' . trailingslashit($this->type->branch) . $changes  );

			if ( ! $response ) {
				$response['message'] = 'No CHANGES.md found';
				$response = (object) $response;
			}

			if ( $response ) {
				set_site_transient( 'ghu-' . md5( $this->type->repo . 'changes' ), $response, ( self::$hours * HOUR_IN_SECONDS ) );
			}
		}

		if ( ! $response ) { return false; }
		if ( isset( $response->message ) ) { return false; }

		$changelog = '';
		$changelog = get_site_transient( 'ghu-' . md5( $this->type->repo . 'changelog' ), $changelog );

		if ( ! $changelog ) {
			if ( function_exists( 'Markdown' ) ) {
				$changelog = Markdown( $response->data );
			} else {
				$changelog = '<pre>' . $response->data . '</pre>';
			}
			set_site_transient( 'ghu-' . md5( $this->type->repo . 'changelog' ), $changelog, ( self::$hours * HOUR_IN_SECONDS ) );
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
		$response = get_site_transient( 'ghu-' . md5( $this->type->repo . 'meta' ) );

		if ( ! $response ) {
			$response = $this->api( '2.0/repositories/:owner/:repo' );

			if ( $response ) {
				set_site_transient( 'ghu-' . md5( $this->type->repo . 'meta' ), $response, ( self::$hours * HOUR_IN_SECONDS ) );
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
		$this->type->last_updated = $this->type->repo_meta->updated_on;
//		$this->type->rating = $this->make_rating();
//		$this->type->num_ratings = $this->type->repo_meta->watchers;
	}

	/**
	 * Create some sort of rating from 0 to 100 for use in star ratings
	 * I'm really just making this up, more based upon popularity
	 *
	 * @since 2.2.0
	 * @return integer
	 */
	private function make_rating() {
		$watchers    = $this->type->repo_meta->watchers;
		$forks       = $this->type->repo_meta->forks;
		$open_issues = $this->type->repo_meta->open_issues;
		$score       = $this->type->repo_meta->score; //what is this anyway?

		$rating = round( $watchers + ( $forks * 1.5 ) - $open_issues );

		if ( 100 < $rating ) { return 100; }

		return $rating;
	}

}