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
 * @package GitHub_Updater_GitHub_API
 * @author  Andy Fragen
 */
class GitHub_Updater_GitHub_API extends GitHub_Updater {

	/**
	 * Constructor.
	 *
	 * @param string $type
	 */
	public function __construct( $type ) {
		$this->type  = $type;
		self::$hours = 12;

		add_filter( 'http_request_args', array( $this, 'never_authenticate_http' ), 10, 2 );
	}

	/**
	 * Add extra headers via filter hooks
	 */
	public static function add_headers() {
		add_filter( 'extra_plugin_headers', array( __CLASS__, 'add_plugin_headers' ) );
		add_filter( 'extra_theme_headers', array( __CLASS__, 'add_theme_headers' ) );
	}

	/**
	 * Add extra headers to get_plugins();
	 *
	 * @param $extra_headers
	 * @return array
	 */
	public static function add_plugin_headers( $extra_headers ) {
		$ghu_extra_headers     = array(
			'GitHub Plugin URI'   => 'GitHub Plugin URI',
			'GitHub Branch'       => 'GitHub Branch',
			'GitHub Access Token' => 'GitHub Access Token',
			'Requires WP'         => 'Requires WP',
			'Requires PHP'        => 'Requires PHP',
		);
		parent::$extra_headers = array_unique( array_merge( parent::$extra_headers, $ghu_extra_headers ) );
		$extra_headers         = array_merge( (array) $extra_headers, (array) $ghu_extra_headers );

		return $extra_headers;
	}

	/**
	 * Add extra headers to wp_get_themes()
	 *
	 * @param $extra_headers
	 * @return array
	 */
	public static function add_theme_headers( $extra_headers ) {
		$ghu_extra_headers     = array(
			'GitHub Theme URI'    => 'GitHub Theme URI',
			'GitHub Branch'       => 'GitHub Branch',
			'GitHub Access Token' => 'GitHub Access Token',
			'Requires WP'         => 'Requires WP',
			'Requires PHP'        => 'Requires PHP',
		);
		parent::$extra_headers = array_unique( array_merge( parent::$extra_headers, $ghu_extra_headers ) );
		$extra_headers         = array_merge( (array) $extra_headers, (array) $ghu_extra_headers );

		return $extra_headers;
	}

	/**
	 * Call the API and return a json decoded body.
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

		if ( is_wp_error( $response ) ) {
			return false;
		}
		if ( ! in_array( $code, $allowed_codes, false ) ) {
			return false;
		}

		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Return API url.
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
		 * @param array $segments List of segments.
		 */
		$segments = apply_filters( 'github_updater_api_segments', $segments );

		foreach ( $segments as $segment => $value ) {
			$endpoint = str_replace( '/:' . $segment, '/' . $value, $endpoint );
		}

		if ( ! empty( $this->type->access_token ) ) {
			$endpoint = add_query_arg( 'access_token', $this->type->access_token, $endpoint );
		}


		// If a branch has been given, only check that for the remote info.
		// If it's not been given, GitHub will use the Default branch.
		if ( ! empty( $this->type->branch ) ) {
			$endpoint = add_query_arg( 'ref', $this->type->branch, $endpoint );
		}

		return 'https://api.github.com' . $endpoint;
	}

	/**
	 * Read the remote file and parse headers.
	 * Saves headers to transient.
	 *
	 * Uses a transient to limit the calls to the API.
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

		if ( ! $response || isset( $response->message ) ) {
			return false;
		}

		if ( ! is_array( $response ) ) {
			return false;
		}
		$this->type->transient            = $response;
		$this->type->remote_version       = strtolower( $response['Version'] );
		$this->type->branch               = ( ! empty( $response['GitHub Branch'] ) ? $response['GitHub Branch'] : 'master' );
		$this->type->requires_wp_version  = ( ! empty( $response['Requires WP'] ) ? $response['Requires WP'] : $this->type->requires_wp_version );
		$this->type->requires_php_version = ( ! empty( $response['Requires PHP'] ) ? $response['Requires PHP'] : $this->type->requires_php_version );

		return true;
	}

	/**
	 * Parse the remote info to find most recent tag if tags exist
	 *
	 * Uses a transient to limit the calls to the API.
	 *
	 * @return string latest tag.
	 */
	public function get_remote_tag() {
		$response = $this->get_transient( 'tags' );

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/tags' );

			if ( ! $response ) {
				$response['message'] = 'No tags found';
				$response = (object) $response;
			}

			if ( $response ) {
				$this->set_transient( 'tags', $response );
			}
		}

		if ( ! $response || isset( $response->message ) ) {
			return false;
		}

		// Sort and get newest tag
		$tags     = array();
		$rollback = array();
		if ( false !== $response ) {
			foreach ( (array) $response as $num => $tag ) {
				if ( isset( $tag->name ) ) {
					$tags[]                 = $tag->name;
					$rollback[ $tag->name ] = $tag->zipball_url;
				}
			}
		}

		// no tags are present, exit early
		if ( empty( $tags ) ) {
			return false;
		}

		usort( $tags, 'version_compare' );

		$newest_tag             = null;
		$newest_tag_key         = key( array_slice( $tags, -1, 1, true ) );
		$newest_tag             = $tags[ $newest_tag_key ];

		$this->type->newest_tag = $newest_tag;
		$this->type->tags       = $tags;
		$this->type->rollback   = $rollback;
	}

	/**
	 * Construct $this->type->download_link using Repository Contents API
	 * @url http://developer.github.com/v3/repos/contents/#get-archive-link
	 *
	 * @param boolean $rollback for theme rollback
	 * 
	 * @return URI
	 */
	public function construct_download_link( $rollback = false ) {
		$download_link_base = 'https://api.github.com/repos/' . trailingslashit( $this->type->owner ) . $this->type->repo . '/zipball/';
		$endpoint           = '';

		// check for rollback
		if ( ! empty( $_GET['rollback'] ) && 'upgrade-theme' === $_GET['action'] && $_GET['theme'] === $this->type->repo ) {
			$endpoint .= $rollback;
		
		// for users wanting to update against branch other than master or not using tags, else use newest_tag
		} else if ( ( 'master' != $this->type->branch && ( -1 != version_compare( $this->type->remote_version, $this->type->local_version ) ) || ( '0.0.0' === $this->type->newest_tag ) ) ) {
			$endpoint .= $this->type->branch;
		} else {
			$endpoint .= $this->type->newest_tag;
		}

		if ( ! empty( $this->type->access_token ) ) {
			$endpoint .= '?access_token=' . $this->type->access_token;
		}

		return $download_link_base . $endpoint;
	}

	/**
	 * Read the remote CHANGES.md file
	 *
	 * Uses a transient to limit calls to the API.
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

		if ( ! $response || isset( $response->message ) ) {
			return false;
		}

		$changelog = $this->get_transient( 'changelog' );

		if ( ! $changelog ) {
			$parser    = new Parsedown();
			$changelog = $parser->text( base64_decode( $response->content ) );
			$this->set_transient( 'changelog', $changelog );
		}

		$this->type->sections['changelog'] = $changelog;
	}

	/**
	 * Read the repository meta from API
	 * Uses a transient to limit calls to the API
	 *
	 * @return base64 decoded repository meta data
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

		if ( ! $response || empty( $response->items ) || isset( $response->message ) ) {
			return false;
		}

		$this->type->repo_meta = $response->items[0];
		$this->add_meta_repo_object();
	}

	/**
	 * Add remote data to type object
	 */
	private function add_meta_repo_object() {
		$this->type->rating       = $this->make_rating( $this->type->repo_meta );
		$this->type->last_updated = $this->type->repo_meta->pushed_at;
		$this->type->num_ratings  = $this->type->repo_meta->watchers;
	}

	/**
	 * Remove any Authorization headers.
	 * Prevent 401 error from GitHub API.
	 * Might be coming from added header for Bitbucket.
	 *
	 * @param $args
	 *
	 * @return mixed
	 */
	public function never_authenticate_http( $args, $url ) {
		// Exit if on other APIs use HTTP Authorization
		if ( isset( $args['headers']['Authorization'] ) ) {
			if ( false === strpos( $url, 'github.com' ) ) {
				return $args;
			}
			unset( $args['headers']['Authorization'] );
		}

		return $args;
	}

}
