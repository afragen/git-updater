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
 * Get remote data from a GitLab repo.
 *
 * Class    GitLab_API
 * @package Fragen\GitHub_Updater
 * @author  Andy Fragen
 */
class GitLab_API extends API {

	/**
	 * Holds loose class method name.
	 *
	 * @var null
	 */
	protected static $method = null;

	/**
	 * Constructor.
	 *
	 * @param object $type
	 */
	public function __construct( $type ) {
		$this->type    = $type;
		parent::$hours = 12;

		if ( ! isset( self::$options['gitlab_private_token'] ) ) {
			self::$options['gitlab_private_token'] = null;
		}
		if ( ! isset( self::$options['gitlab_enterprise_token'] ) ) {
			self::$options['gitlab_enterprise_token'] = null;
		}
		if (
			empty( self::$options['gitlab_private_token'] ) ||
			( empty( self::$options['gitlab_enterprise_token'] ) && ! empty( $type->enterprise ) )
		) {
			Messages::create_error_message( 'gitlab' );
		}
		add_site_option( 'github_updater', self::$options );
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
			$id           = $this->get_gitlab_id();
			self::$method = 'file';

			if ( empty( $this->type->branch ) ) {
				$this->type->branch = 'master';
			}

			$response = $this->api( '/projects/' . $id . '/repository/files?file_path=' . $file );

			if ( empty( $response ) ) {
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

		$this->set_file_info( $response, 'GitLab' );

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
			$id           = $this->get_gitlab_id();
			self::$method = 'tags';
			$response     = $this->api( '/projects/' . $id . '/repository/tags' );

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
			$id           = $this->get_gitlab_id();
			self::$method = 'changes';
			$response     = $this->api( '/projects/' . $id . '/repository/files?file_path=' . $changes );

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
			$id           = $this->get_gitlab_id();
			self::$method = 'readme';
			$response     = $this->api( '/projects/' . $id . '/repository/files?file_path=readme.txt' );

			if ( $response ) {
				$parser   = new Readme_Parser;
				$response = $parser->parse_readme( base64_decode( $response->content ) );
				$this->set_transient( 'readme', $response );
			}
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

		if ( ! $response ) {
			self::$method = 'meta';
			$projects     = $this->get_transient( 'projects' );

			// exit if transient is empty
			if ( ! $projects ) {
				return false;
			}

			foreach ( $projects as $project ) {
				if ( $this->type->repo === $project->name ) {
					$response = $project;
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
			$id           = $this->get_gitlab_id();
			self::$method = 'branches';
			$response     = $this->api( '/projects/' . $id . '/repository/branches' );

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
	 * Construct $this->type->download_link using GitLab API.
	 *
	 * @param boolean $rollback for theme rollback
	 * @param boolean $branch_switch for direct branch changing
	 *
	 * @return string $endpoint
	 */
	public function construct_download_link( $rollback = false, $branch_switch = false ) {
		/*
		 * Check if using GitLab CE/Enterprise.
		 */
		if ( ! empty( $this->type->enterprise ) ) {
			$gitlab_base = $this->type->enterprise;
		} else {
			$gitlab_base = 'https://gitlab.com';
		}

		$download_link_base = implode( '/', array( $gitlab_base, $this->type->owner, $this->type->repo, 'repository/archive.zip' ) );
		$endpoint           = '';

		/*
		 * Check for rollback.
		 */
		if ( ! empty( $_GET['rollback'] ) &&
		     ( isset( $_GET['action'] ) && 'upgrade-theme' === $_GET['action'] ) &&
		     ( isset( $_GET['theme'] ) && $_GET['theme'] === $this->type->repo )
		) {
			$endpoint .= $rollback;
		} elseif ( ! empty( $this->type->branch ) ) {
			$endpoint = add_query_arg( 'ref', $this->type->branch, $endpoint );
		}

		/*
		 * If a branch has been given, only check that for the remote info.
		 * If it's not been given, GitLab will use the Default branch.
		 * If branch is master and tags are used, use newest tag.
		 */
		if ( 'master' === $this->type->branch && ! empty( $this->type->tags ) ) {
			$endpoint = remove_query_arg( 'ref', $endpoint );
			$endpoint = add_query_arg( 'ref', $this->type->newest_tag, $endpoint );
		}

		/*
		 * Create endpoint for branch switching.
		 */
		if ( $branch_switch ) {
			$endpoint = remove_query_arg( 'ref', $endpoint );
			$endpoint = add_query_arg( 'ref', $branch_switch, $endpoint );
		}

		if ( ! empty( parent::$options[ 'gitlab_private_token' ] ) ) {
			$endpoint = add_query_arg( 'private_token', parent::$options['gitlab_private_token'], $endpoint );
		}

		/*
		 * If using GitLab CE/Enterprise header return this endpoint.
		 */
		if ( ! empty( $this->type->enterprise ) ) {
			$endpoint = remove_query_arg( 'private_token', $endpoint );
			if ( ! empty( parent::$options['gitlab_enterprise_token'] ) ) {
				$endpoint = add_query_arg( 'private_token', parent::$options['gitlab_enterprise_token'], $endpoint );
			}

			return $this->type->enterprise . $endpoint;
		}


		return $download_link_base . $endpoint;
	}

	/**
	 * Add remote data to type object.
	 */
	private function _add_meta_repo_object() {
		//$this->type->rating       = $this->make_rating( $this->type->repo_meta );
		$this->type->last_updated = $this->type->repo_meta->last_activity_at;
		//$this->type->num_ratings  = $this->type->repo_meta->watchers;
		$this->type->private      = ! $this->type->repo_meta->public;
	}

	/**
	 * Create GitLab API endpoints.
	 *
	 * @param $git object
	 * @param $endpoint string
	 *
	 * @return string
	 */
	protected function add_endpoints( $git, $endpoint ) {
		if ( ! empty( parent::$options['gitlab_private_token'] ) ) {
			$endpoint = add_query_arg( 'private_token', parent::$options['gitlab_private_token'], $endpoint );
		}

		switch ( self::$method ) {
			case 'projects':
			case 'meta':
			case 'tags':
				break;
			case 'file':
			case 'changes':
			case 'readme':
				$endpoint = add_query_arg( 'ref', $git->type->branch, $endpoint );
				break;
			default:
				break;
		}

		/*
		 * If using GitLab CE/Enterprise header return this endpoint.
		 */
		if ( ! empty( $git->type->enterprise ) ) {
			$endpoint = remove_query_arg( 'private_token', $endpoint );
			if ( ! empty( parent::$options['gitlab_enterprise_token'] ) ) {
				$endpoint = add_query_arg( 'private_token', parent::$options['gitlab_enterprise_token'], $endpoint );
			}

			return $git->type->enterprise . $endpoint;
		}

		return $endpoint;
	}

	/**
	 * Get GitLab project ID.
	 *
	 * @return bool|null
	 */
	public function get_gitlab_id() {
		$id       = null;
		$response = $this->get_transient( 'projects' );

		if ( ! $response ) {
			self::$method = 'projects';
			$response = $this->api( '/projects' );
			if ( empty( $response ) ) {
				$id = rtrim( urlencode( $this->type->slug ), '.php' );
				return $id;
			}
		}

		foreach ( $response as $project ) {
			if ( $this->type->repo === $project->name ) {
				$id = $project->id;
				$this->set_transient( 'projects', $response );
			}
		}

		return $id;
	}

}
