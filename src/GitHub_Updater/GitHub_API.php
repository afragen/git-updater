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

/**
 * Get remote data from a GitHub repo.
 *
 * Class    GitHub_API
 * @package Fragen\GitHub_Updater
 * @author  Andy Fragen
 */
class GitHub_API extends Base {

	/**
	 * Constructor.
	 *
	 * @param object $type
	 */
	public function __construct( $type ) {
		$this->type  = $type;
		parent::$hours = 12;
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
		$code          = (integer) wp_remote_retrieve_response_code( $response );
		$allowed_codes = array( 200, 404 );

		if ( is_wp_error( $response ) ) {
			return false;
		}
		if ( ! in_array( $code, $allowed_codes, false ) ) {
			parent::$error_code = array_merge( parent::$error_code, array( $this->type->repo => $code ) );
			$this->_ratelimit_reset( $response );
			$this->create_error_message();
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
			$endpoint = str_replace( '/:' . sanitize_key( $segment ), '/' . sanitize_text_field( $value ), $endpoint );
		}

		if ( ! empty( parent::$options[ $this->type->repo ] ) ) {
			$endpoint = add_query_arg( 'access_token', parent::$options[ $this->type->repo ], $endpoint );
		} elseif ( ! empty( parent::$options['github_access_token'] ) ) {
			$endpoint = add_query_arg( 'access_token', parent::$options['github_access_token'], $endpoint );
		}

		/**
		 * If a branch has been given, only check that for the remote info.
		 * If it's not been given, GitHub will use the Default branch.
		 */
		if ( ! empty( $this->type->branch ) ) {
			$endpoint = add_query_arg( 'ref', $this->type->branch, $endpoint );
		}

		/**
		 * If using GitHub Enterprise header return this endpoint.
		 */
		if ( ! empty( $this->type->enterprise ) ) {
			return $this->type->enterprise . remove_query_arg( 'access_token', $endpoint );
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
		$this->type->branch               = ! empty( $response['GitHub Branch'] ) ? $response['GitHub Branch'] : 'master';
		$this->type->requires_php_version = ! empty( $response['Requires PHP'] ) ? $response['Requires PHP'] : $this->type->requires_php_version;
		$this->type->requires_wp_version  = ! empty( $response['Requires WP'] ) ? $response['Requires WP'] : $this->type->requires_wp_version;

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

		/**
		 * Sort and get newest tag.
		 */
		$tags     = array();
		$rollback = array();
		if ( false !== $response ) {
			foreach ( (array) $response as $tag ) {
				if ( isset( $tag->name ) ) {
					$tags[]                 = $tag->name;
					$rollback[ $tag->name ] = $tag->zipball_url;
				}
			}
		}

		/**
		 * No tags are present, exit early.
		 */
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
	 * @param boolean $branch_switch for direct branch changing
	 * 
	 * @return URI
	 */
	public function construct_download_link( $rollback = false, $branch_switch = false ) {
		/**
		 * Check if using GitHub Enterprise.
		 */
		if ( ! empty( $this->type->enterprise ) ) {
			$github_base = $this->type->enterprise;
		} else {
			$github_base = 'https://api.github.com';
		}

		$download_link_base = $github_base . '/repos/' . trailingslashit( $this->type->owner ) . $this->type->repo . '/zipball/';

		$endpoint           = '';

		/**
		 * Check for rollback.
		 */
		if ( ! empty( $_GET['rollback'] ) && 'upgrade-theme' === $_GET['action'] && $_GET['theme'] === $this->type->repo ) {
			$endpoint .= $rollback;

			/**
			 * For users wanting to update against branch other than master
			 * or if not using tags, else use newest_tag.
			 */
		} elseif ( 'master' != $this->type->branch || empty( $this->type->tags ) ) {
			$endpoint .= $this->type->branch;
		} else {
			$endpoint .= $this->type->newest_tag;
		}

		/**
		 * Create endpoint for branch switching.
		 */
		if ( $branch_switch ) {
			$endpoint = $branch_switch;
		}

		if ( ! empty( parent::$options[ $this->type->repo ] ) ) {
			$endpoint = add_query_arg( 'access_token', parent::$options[ $this->type->repo ], $endpoint );
			return $download_link_base . $endpoint;
		} elseif ( ! empty( parent::$options['github_access_token'] ) && empty( $this->type->enterprise ) ) {
			$endpoint = add_query_arg( 'access_token', parent::$options['github_access_token'], $endpoint );
			return $download_link_base . $endpoint;
		}

		return $download_link_base . $endpoint;
	}

	/**
	 * Read the remote CHANGES.md file.
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
		if ( ! file_exists( $this->type->local_path . 'readme.txt' ) ) {
			return false;
		}

		$response = $this->get_transient( 'readme' );

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/contents/readme.txt' );
		}

		if ( ! $response || isset( $response->message ) ) {
			return false;
		}

		if ( $response && isset( $response->content ) ) {
			$parser   = new Readme_Parser;
			$response = $parser->parse_readme( base64_decode( $response->content ) );
			$this->set_transient( 'readme', $response );
		}

		/**
		 * Set plugin data from readme.txt.
		 * Prefer changelog from CHANGES.md.
		 */
		$readme = array();
		foreach ( $this->type->sections as $section => $value ) {
			if ( 'description' === $section ) {
				continue;
			}
			$readme['sections/' . $section ] = $value;
		}
		foreach ( $readme as $key => $value ) {
			$key = explode( '/', $key );
			if ( ! empty( $value ) && 'sections' === $key[0] ) {
				unset( $response['sections'][ $key[1] ] );
			}
		}

		unset( $response['sections']['screenshots'] );
		unset( $response['sections']['installation'] );
		$this->type->sections     = array_merge( (array) $this->type->sections, (array) $response['sections'] );
		$this->type->tested       = $response['tested_up_to'];
		$this->type->requires     = $response['requires_at_least'];
		$this->type->donate       = $response['donate_link'];
		$this->type->contributors = $response['contributors'];

		return true;
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
		$this->_add_meta_repo_object();
		$this->get_branches();
	}

	/**
	 * Add remote data to type object
	 */
	private function _add_meta_repo_object() {
		$this->type->rating       = $this->make_rating( $this->type->repo_meta );
		$this->type->last_updated = $this->type->repo_meta->pushed_at;
		$this->type->num_ratings  = $this->type->repo_meta->watchers;
		$this->type->private      = $this->type->repo_meta->private;
	}

	/**
	 * Create array of branches and download links as array.
	 * @return bool
	 */
	public function get_branches() {
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

		if ( ! $response || isset( $response->message ) ) {
			return false;
		}

		$this->type->branches = $response;

		return true;
	}

	/**
	 * Calculate and store time until rate limit reset.
	 *
	 * @param $response
	 */
	private function _ratelimit_reset( $response ) {
		if ( isset( $response['headers']['x-ratelimit-reset'] ) ) {
			$reset              = (integer) $response['headers']['x-ratelimit-reset'];
			$wait               = date( 'i', $reset - time() );
			parent::$error_code = array_merge( parent::$error_code, array( $this->type->repo . '-wait' => $wait ) );
		}
	}

}
