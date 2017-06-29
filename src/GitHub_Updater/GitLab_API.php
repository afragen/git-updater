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
 * Class GitLab_API
 *
 * Get remote data from a GitLab repo.
 *
 * @package Fragen\GitHub_Updater
 * @author  Andy Fragen
 */
class GitLab_API extends API implements API_Interface {

	/**
	 * Holds loose class method name.
	 *
	 * @var null
	 */
	private static $method;

	/**
	 * Constructor.
	 *
	 * @param object $type
	 */
	public function __construct( $type ) {
		$this->type     = $type;
		$this->response = $this->get_repo_cache();

		if ( ! isset( self::$options['gitlab_access_token'] ) ) {
			self::$options['gitlab_access_token'] = null;
		}
		if ( ! isset( self::$options['gitlab_enterprise_token'] ) ) {
			self::$options['gitlab_enterprise_token'] = null;
		}
		if (
			empty( self::$options['gitlab_access_token'] ) ||
			( empty( self::$options['gitlab_enterprise_token'] ) && ! empty( $type->enterprise ) )
		) {
			Messages::instance()->create_error_message( 'gitlab' );
		}
		add_site_option( 'github_updater', self::$options );
	}

	/**
	 * Read the remote file and parse headers.
	 *
	 * @param string $file Filename.
	 *
	 * @return bool
	 */
	public function get_remote_info( $file ) {
		$response = isset( $this->response[ $file ] ) ? $this->response[ $file ] : false;

		if ( ! $response ) {
			$id           = $this->get_gitlab_id();
			self::$method = 'file';

			$response = $this->api( '/projects/' . $id . '/repository/files?file_path=' . $file );

			if ( empty( $response ) || ! isset( $response->content ) ) {
				return false;
			}

			if ( $response && isset( $response->content ) ) {
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
			$id           = $this->get_gitlab_id();
			self::$method = 'tags';
			$response     = $this->api( '/projects/' . $id . '/repository/tags' );

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
	 * @param string $changes Changelog filename.
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
			$id           = $this->get_gitlab_id();
			self::$method = 'changes';
			$response     = $this->api( '/projects/' . $id . '/repository/files?file_path=' . $changes );

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
		if ( ! $this->exists_local_file( 'readme.txt' ) ) {
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
			$id           = $this->get_gitlab_id();
			self::$method = 'readme';
			$response     = $this->api( '/projects/' . $id . '/repository/files?file_path=readme.txt' );

		}
		if ( $response && isset( $response->content ) ) {
			$file     = base64_decode( $response->content );
			$parser   = new Readme_Parser( $file );
			$response = $parser->parse_data( $this );
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
			self::$method = 'meta';
			$project      = isset( $this->response['project'] ) ? $this->response['project'] : false;

			// exit if transient is empty
			if ( ! $project ) {
				return false;
			}

			$response = ( $this->type->repo === $project->path ) ? $project : false;

			if ( $response ) {
				$response = $this->parse_meta_response( $response );
				$this->set_repo_cache( 'meta', $response );
				$this->set_repo_cache( 'project', null );
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
			$id           = $this->get_gitlab_id();
			self::$method = 'branches';
			$response     = $this->api( '/projects/' . $id . '/repository/branches' );

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
	 * Construct $this->type->download_link using GitLab API.
	 *
	 * @param boolean $rollback      for theme rollback
	 * @param boolean $branch_switch for direct branch changing
	 *
	 * @return string $endpoint
	 */
	public function construct_download_link( $rollback = false, $branch_switch = false ) {
		$download_link_base = $this->get_api_url( '/:owner/:repo/repository/archive.zip', true );
		$endpoint           = '';

		/*
		 * If release asset.
		 */
		if ( $this->type->release_asset && '0.0.0' !== $this->type->newest_tag ) {
			$download_link_base = $this->make_release_asset_download_link();

			return $this->add_access_token_endpoint( $this, $download_link_base );
		}

		/*
		 * If a branch has been given, only check that for the remote info.
		 * If branch is master (default) and tags are used, use newest tag.
		 */
		if ( 'master' === $this->type->branch && ! empty( $this->type->tags ) ) {
			$endpoint = remove_query_arg( 'ref', $endpoint );
			$endpoint = add_query_arg( 'ref', $this->type->newest_tag, $endpoint );
		} elseif ( ! empty( $this->type->branch ) ) {
			$endpoint = remove_query_arg( 'ref', $endpoint );
			$endpoint = add_query_arg( 'ref', $this->type->branch, $endpoint );
		}

		/*
		 * Check for rollback.
		 */
		if ( ! empty( $_GET['rollback'] ) &&
		     ( isset( $_GET['action'] ) && 'upgrade-theme' === $_GET['action'] ) &&
		     ( isset( $_GET['theme'] ) && $this->type->repo === $_GET['theme'] )
		) {
			$endpoint = remove_query_arg( 'ref', $endpoint );
			$endpoint = add_query_arg( 'ref', esc_attr( $_GET['rollback'] ), $endpoint );
		}

		/*
		 * Create endpoint for branch switching.
		 */
		if ( $branch_switch ) {
			$endpoint = remove_query_arg( 'ref', $endpoint );
			$endpoint = add_query_arg( 'ref', $branch_switch, $endpoint );
		}

		$endpoint = $this->add_access_token_endpoint( $this, $endpoint );

		return $download_link_base . $endpoint;
	}

	/**
	 * Create GitLab API endpoints.
	 *
	 * @param object $git
	 * @param string $endpoint
	 *
	 * @return string $endpoint
	 */
	public function add_endpoints( $git, $endpoint ) {

		switch ( $git::$method ) {
			case 'projects':
				$endpoint = add_query_arg( 'per_page', '100', $endpoint );
				break;
			case 'meta':
			case 'tags':
			case 'branches':
				break;
			case 'file':
			case 'changes':
			case 'readme':
				$endpoint = add_query_arg( 'ref', $git->type->branch, $endpoint );
				break;
			case 'translation':
				$endpoint = add_query_arg( 'ref', 'master', $endpoint );
				break;
			default:
				break;
		}

		$endpoint = $this->add_access_token_endpoint( $git, $endpoint );

		/*
		 * If GitLab CE/Enterprise return this endpoint.
		 */
		if ( ! empty( $git->type->enterprise_api ) ) {
			return $git->type->enterprise_api . $endpoint;
		}

		return $endpoint;
	}

	/**
	 * Get GitLab project ID and project meta.
	 *
	 * @return string|int
	 */
	public function get_gitlab_id() {
		$id       = null;
		$response = isset( $this->response['project_id'] ) ? $this->response['project_id'] : false;

		if ( ! $response ) {
			self::$method = 'projects';
			$response     = $this->api( '/projects' );

			if ( empty( $response ) ) {
				$id = implode( '/', array( $this->type->owner, $this->type->repo ) );
				$id = urlencode( $id );

				return $id;
			}

			foreach ( (array) $response as $project ) {
				if ( $this->type->repo === $project->path ) {
					$id = $project->id;
					$this->set_repo_cache( 'project_id', $id );
					$this->set_repo_cache( 'project', $project );

					return $id;
				}
			}

		}

		return $response;
	}

	/**
	 * Parse API response call and return only array of tag numbers.
	 *
	 * @param object|array $response Response from API call for tags.
	 *
	 * @return object|array Array of tag numbers, object is error.
	 */
	public function parse_tag_response( $response ) {
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
	public function parse_meta_response( $response ) {
		$arr      = array();
		$response = array( $response );

		array_filter( $response, function( $e ) use ( &$arr ) {
			$arr['private']      = ! $e->public;
			$arr['last_updated'] = $e->last_activity_at;
			$arr['watchers']     = 0;
			$arr['forks']        = $e->forks_count;
			$arr['open_issues']  = isset( $e->open_issues_count ) ? $e->open_issues_count : 0;
		} );

		return $arr;
	}

	/**
	 * Parse API response and return array with changelog in base64.
	 *
	 * @param object $response Response from API call.
	 *
	 * @return array|object $arr Array of changes in base64, object if error.
	 */
	public function parse_changelog_response( $response ) {
		if ( isset( $response->messages ) ) {
			return $response;
		}

		$arr      = array();
		$response = array( $response );

		array_filter( $response, function( $e ) use ( &$arr ) {
			$arr['changes'] = $e->content;
		} );

		return $arr;
	}

}
