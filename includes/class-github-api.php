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
class GitHub_Updater_GitHub_API extends GitHub_Updater {

	/**
	 * Constructor.
	 *
	 * @since 2.1.0
	 *
	 * @param string $type
	 */
	public function __construct( $type ) {
		$this->type = $type;
	}

	/**
	 * Call the GitHub API and return a json decoded body.
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

		if ( is_wp_error( $response ) || ( '200' || '404' ) != wp_remote_retrieve_response_code( $response ) ) {
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

		if ( ! empty( $this->type->access_token ) )
			$endpoint = add_query_arg( 'access_token', $this->type->access_token, $endpoint );

		// If a branch has been given, only check that for the remote info.
		// If it's not been given, GitHub will use the Default branch.
		if ( ! empty( $this->type->branch ) )
			$endpoint = add_query_arg( 'ref', $this->type->branch, $endpoint );

		return 'https://api.github.com' . $endpoint;
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
		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/contents/' . $file );

			if ( $response ) {
				set_site_transient( 'ghu-' . md5( $this->type->repo . $file ), $response, ( GitHub_Updater::$hours * HOUR_IN_SECONDS ) );
			}
		}

		$this->type->branch = $this->get_default_branch( $response );

		if ( ! $response ) return;
		if ( isset( $response->message ) ) return false;

		$this->type->transient = $response;
		preg_match( '/^[ \t\/*#@]*Version\:\s*(.*)$/im', base64_decode( $response->content ), $matches );

		if ( ! empty( $matches[1] ) )
			$this->type->remote_version = trim( $matches[1] );
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
		if ( ! empty( $this->type->branch ) )
			return $this->type->branch;

		// If we can't contact GitHub API, then assume a sensible default in case the non-API part of GitHub is working.
		if ( ! $response || isset( $response->message ) )
			return 'master';

		// Assuming we've got some remote info, parse the 'url' field to get the last bit of the ref query string
		$components = parse_url( $response->url, PHP_URL_QUERY );
		parse_str( $components );
		return $ref;
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
		$response = get_site_transient( 'ghu-' . md5( $this->type->repo . 'tags' ) );

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/tags' );

			if ( $response ) {
				set_site_transient( 'ghu-' . md5( $this->type->repo . 'tags' ), $response, ( GitHub_Updater::$hours * HOUR_IN_SECONDS ) );
			}
		}

		if ( ! $response ) return false;
		if ( isset( $response->message ) ) return false;

		// Sort and get latest tag
		$tags = array();
		if ( false !== $response )
			foreach ( (array) $response as $num => $tag ) {
				if ( isset( $tag->name ) ) $tags[] = $tag->name;
			}

		if ( empty( $tags ) ) return;  // no tags are present, exit early

		usort( $tags, 'version_compare' );
		
		// check and generate download link
		$newest_tag     = null;
		$newest_tag_key = key( array_slice( $tags, -1, 1, true ) );
		$newest_tag     = $tags[ $newest_tag_key ];

		$this->type->newest_tag    = $newest_tag;
		$this->type->tags          = $tags;
	}

	/**
	 * Construct $download_link using Repository Contents API
	 * @url http://developer.github.com/v3/repos/contents/#get-archive-link
	 *
	 * @since 1.9.0
	 *
	 * @param stdClass plugin data
	 * 
	 * @return URI
	 */
	public function construct_download_link() {

		$endpoint = '';
		// for users wanting to update against branch other than master
		if ( ( 'master' != $this->type->branch ) && ( -1 != version_compare( $this->type->remote_version, $this->type->local_version ) ) ) {
			$endpoint .= $this->type->branch;
		} else {
			// just in case user started using tags then stopped.
			if ( ( 1 != version_compare( $this->type->remote_version, $this->type->newest_tag ) ) && ! ( '0.0.0' === $this->type->newest_tag ) ) {							
				$endpoint .= $this->type->newest_tag;
			} else {
				$endpoint .= $this->type->branch;
			}
		}

		if ( ! empty( $this->type->access_token ) ) {
			$endpoint .= '?access_token=' . $this->type->access_token;
		}

		if ( ! empty( $this->type->access_token ) ) {
			$private_uri = 'https://api.github.com/repos/';
			return $private_uri . $this->type->owner . '/' . $this->type->repo . '/zipball/' . $endpoint;
		} else {
			return $this->type->uri . '/zipball/' . $endpoint;
		}
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
		if ( ! class_exists( 'Markdown_Parser' ) )
			require_once 'markdown.php';

		$response = get_site_transient( 'ghu-' . md5( $this->type->repo . 'changes' ) );

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/contents/' . $changes  );

			if ( $response ) {
				set_site_transient( 'ghu-' . md5( $this->type->repo . 'changes' ), $response, ( GitHub_Updater::$hours * HOUR_IN_SECONDS ) );				
			}
		}

		if ( ! $response ) return false;
		if ( isset( $response->message ) ) return false;

		if ( function_exists( 'Markdown' ) ) {
			$changelog = Markdown( base64_decode( $response->content ) );
		} else {
			$changelog = '<pre>' . base64_decode( $response->content ) . '</pre>';
		}

		$this->type->sections['changelog'] = $changelog;
	}
	
	/**
	 * Read the repository meta from GitHub API
	 *
	 * Uses a transient to limit calls to the API
	 * @since 2.2.0
	 * @return base64 decoded repository meta data
	 */
	public function get_repo_meta() {
		$response = get_site_transient( 'ghu-' . md5( $this->type->repo . 'meta' ) );
		$meta_query = '?q=' . $this->type->repo . '+user:' . $this->type->owner;

		if ( ! $response ) {
			$response = $this->api( '/search/repositories' . $meta_query );

			if ( $response ) {
				set_site_transient( 'ghu-' . md5( $this->type->repo . 'meta' ), $response, ( GitHub_Updater::$hours * HOUR_IN_SECONDS ) );				
			}
		}

		if ( ! $response ) return false;
		if ( isset( $response->message ) ) return false;

		$this->type->repo_meta = $response->items[0];
		$this->add_meta_repo_object();
	}

	/**
	 * Add remote data to type object
	 *
	 * @since 2.2.0
	 */
	private function add_meta_repo_object() {
		$this->type->last_updated = $this->type->repo_meta->pushed_at;
		$this->type->rating = $this->make_rating();
		$this->type->num_ratings = $this->type->repo_meta->watchers;
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

		if ( 100 < $rating ) {
			return 100;
		}

		return $rating;
	}

}
