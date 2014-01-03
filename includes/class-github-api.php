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
class GitHub_Updater_GitHub_API {

	/**
	 * Define as either 'plugin' or 'theme'
	 *
	 * @since 1.9.0
	 *
	 * @var string
	 */
	protected $type;

	/**
	 * Class Object for API
	 *
	 * @since 2.1.0
	 *
	 * @var class object
	 */
 	protected $repo_api;

	/**
	 * Variable for setting update transient hours
	 *
	 * @var integer
	 */
	 public static $hours = 1;
	 

	/**
	 * Constructor.
	 *
	 * @since 2.1.0
	 *
	 * @param string $type
	 */
	public function __construct( $type ) {
		$this->type = $type;
		self::$hours = apply_filters( 'github_updater_set_transient_hours', self::$hours );
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

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != '200' )
			return false;

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

		$remote = get_site_transient( md5( $this->type->repo . $file ) );
		if ( ! $remote ) {
			$remote = $this->api( '/repos/:owner/:repo/contents/' . $file );

			if ( $remote ) {
				set_site_transient( md5( $this->type->repo . $file ), $remote, ( self::$hours * HOUR_IN_SECONDS ) );
			}
		}

		$this->type->branch = $this->get_default_branch( $remote );

		if ( ! $remote ) return;
		$this->type->transient = $remote;
		preg_match( '/^[ \t\/*#@]*Version\:\s*(.*)$/im', base64_decode( $remote->content ), $matches );

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
		if ( ! $response )
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
		$response = get_site_transient( md5( $this->type->repo . 'tags' ) );

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/tags' );

			if ( $response ) {
				set_site_transient( md5( $this->type->repo . 'tags' ), $response, ( self::$hours * HOUR_IN_SECONDS ) );
			}
		}

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
	 * Construct $download_link
	 *
	 * @since 1.9.0
	 *
	 * @param stdClass plugin data
	 */
	public function construct_download_link() {

		// just in case user started using tags then stopped.
		if ( version_compare( $this->type->newest_tag, $this->type->remote_version, '>=' ) && ! ( '0.0.0' === $this->type->newest_tag ) ) {							
			$download_link = $this->type->uri . '/archive/' . $this->type->newest_tag . '.zip';
		} else {
			$download_link = $this->type->uri . '/archive/' . $this->type->branch . '.zip';
		}
		return $download_link;
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

		$remote = get_site_transient( md5( $this->type->repo . 'changes' ) );

		if ( ! $remote ) {
			$remote = $this->api( '/repos/:owner/:repo/contents/' . $changes  );

			if ( $remote ) {
				set_site_transient( md5( $this->type->repo . 'changes' ), $remote, ( self::$hours * HOUR_IN_SECONDS ) );				
			}
		}
		
		if ( false != $remote ) {
			if ( function_exists( 'Markdown' ) ) {
				$changelog = Markdown( base64_decode( $remote->content ) );
			} else {
				$changelog = '<pre>' . base64_decode( $remote->content ) . '</pre>';
			}
			$this->type->sections['changelog'] = $changelog;
		}

	}
	
	/**
	 * Read the repository meta from GitHub API
	 *
	 * Uses a transient to limit calls to the API
	 * @since 2.2.0
	 * @return base64 decoded repository meta data
	 */
	public function get_repo_meta() {
		$remote = get_site_transient( md5( $this->type->repo . 'meta' ) );
		$meta_query = '?q=' . $this->type->repo . '+user:' . $this->type->owner;

		if ( ! $remote ) {
			$remote = $this->api( '/search/repositories' . $meta_query );

			if ( $remote ) {
				set_site_transient( md5( $this->type->repo . 'meta' ), $remote, ( self::$hours * HOUR_IN_SECONDS ) );				
			}
		}

		$this->type->repo_meta = $remote->items[0];
		$this->add_meta_repo_object();
	}

	/**
	 * Add remote data to type object
	 *
	 * @since 2.2.0
	 */
	private function add_meta_repo_object() {
		$this->type->last_updated = $this->type->repo_meta->pushed_at;
		
		// I'm just making this up
		$rating = round( $this->type->repo_meta->stargazers_count + $this->type->repo_meta->score );
		if ( 100 < $rating ) {
			$this->type->rating = 100;
		} else {
			$this->type->rating = $rating;
		}

		$this->type->num_ratings = $this->type->repo_meta->stargazers_count;
	}

}