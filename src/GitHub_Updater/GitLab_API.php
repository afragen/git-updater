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
	 * @param \stdClass $type
	 */
	public function __construct( $type ) {
		$this->type     = $type;
		$this->response = $this->get_repo_cache();
		$branch         = new Branch( $this->response );
		if ( ! empty( $type->branch ) ) {
			$this->type->branch = ! empty( $branch->cache['current_branch'] )
				? $branch->cache['current_branch']
				: $type->branch;
		}
		$this->set_default_credentials();
	}

	/**
	 * Set default credentials if option not set.
	 */
	protected function set_default_credentials() {
		$set_credentials = false;
		if ( ! isset( self::$options['gitlab_access_token'] ) ) {
			self::$options['gitlab_access_token'] = null;
			$set_credentials                      = true;
		}
		if ( ! isset( self::$options['gitlab_enterprise_token'] ) ) {
			self::$options['gitlab_enterprise_token'] = null;
			$set_credentials                          = true;
		}
		if ( empty( self::$options['gitlab_access_token'] ) ||
		     ( empty( self::$options['gitlab_enterprise_token'] ) && ! empty( $this->type->enterprise ) )
		) {
			Singleton::get_instance( 'Messages' )->create_error_message( 'gitlab' );
		}
		if ( $set_credentials ) {
			add_site_option( 'github_updater', self::$options );
		}
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
				$this->set_repo_cache( 'repo', $this->type->repo );
			}
		}

		if ( ! is_array( $response ) || $this->validate_response( $response ) ) {
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
		if ( ! $this->local_file_exists( 'readme.txt' ) ) {
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
	 * @param GitLab_API|API $git
	 * @param string         $endpoint
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
	 * @param \stdClass|array $response Response from API call for tags.
	 *
	 * @return \stdClass|array Array of tag numbers, object is error.
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
	 * @param \stdClass|array $response Response from API call.
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
	 * @param \stdClass|array $response Response from API call.
	 *
	 * @return array|\stdClass $arr Array of changes in base64, object if error.
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

	/**
	 * Add settings for GitLab.com, GitLab Community Edition.
	 * or GitLab Enterprise Access Token.
	 */
	public function add_settings() {
		if ( parent::$auth_required['gitlab'] || parent::$auth_required['gitlab_enterprise'] ) {
			add_settings_section(
				'gitlab_settings',
				esc_html__( 'GitLab Personal Access Token', 'github-updater' ),
				array( &$this, 'print_section_gitlab_token' ),
				'github_updater_gitlab_install_settings'
			);
		}

		if ( parent::$auth_required['gitlab_private'] ) {
			add_settings_section(
				'gitlab_id',
				esc_html__( 'GitLab Private Settings', 'github-updater' ),
				array( &$this, 'print_section_gitlab_info' ),
				'github_updater_gitlab_install_settings'
			);
		}

		if ( parent::$auth_required['gitlab'] ) {
			add_settings_field(
				'gitlab_access_token',
				esc_html__( 'GitLab.com Access Token', 'github-updater' ),
				array( Singleton::get_instance( 'Settings' ), 'token_callback_text' ),
				'github_updater_gitlab_install_settings',
				'gitlab_settings',
				array( 'id' => 'gitlab_access_token', 'token' => true )
			);
		}

		if ( parent::$auth_required['gitlab_enterprise'] ) {
			add_settings_field(
				'gitlab_enterprise_token',
				esc_html__( 'GitLab CE or GitLab Enterprise Personal Access Token', 'github-updater' ),
				array( Singleton::get_instance( 'Settings' ), 'token_callback_text' ),
				'github_updater_gitlab_install_settings',
				'gitlab_settings',
				array( 'id' => 'gitlab_enterprise_token', 'token' => true )
			);
		}
	}

	/**
	 * Add values for individual repo add_setting_field().
	 *
	 * @return mixed
	 */
	public function add_repo_setting_field() {
		$setting_field['page']            = 'github_updater_gitlab_install_settings';
		$setting_field['section']         = 'gitlab_id';
		$setting_field['callback_method'] = array( Singleton::get_instance( 'Settings' ), 'token_callback_text' );

		return $setting_field;
	}

	/**
	 * Print the GitLab Settings text.
	 */
	public function print_section_gitlab_info() {
		esc_html_e( 'Enter your GitLab Access Token.', 'github-updater' );
	}

	/**
	 * Print the GitLab Access Token Settings text.
	 */
	public function print_section_gitlab_token() {
		esc_html_e( 'Enter your GitLab.com, GitLab CE, or GitLab Enterprise Access Token.', 'github-updater' );
	}

	/**
	 * Add remote install settings fields.
	 *
	 * @param string $type
	 */
	public function add_install_settings_fields( $type ) {
		add_settings_field(
			'gitlab_access_token',
			esc_html__( 'GitLab Access Token', 'github-updater' ),
			array( &$this, 'gitlab_access_token' ),
			'github_updater_install_' . $type,
			$type
		);
	}

	/**
	 * GitLab Access Token for remote install.
	 */
	public function gitlab_access_token() {
		?>
		<label for="gitlab_access_token">
			<input class="gitlab_setting" type="text" style="width:50%;" name="gitlab_access_token" value="">
			<br>
			<span class="description">
				<?php esc_html_e( 'Enter GitLab Access Token for private GitLab repositories.', 'github-updater' ) ?>
			</span>
		</label>
		<?php
	}

	/**
	 * Add remote install feature, create endpoint.
	 *
	 * @param array $headers
	 * @param array $install
	 *
	 * @return mixed $install
	 */
	public function remote_install( $headers, $install ) {
		if ( 'gitlab.com' === $headers['host'] || empty( $headers['host'] ) ) {
			$base            = 'https://gitlab.com';
			$headers['host'] = 'gitlab.com';
		} else {
			$base = $headers['base_uri'];
		}

		$install['download_link'] = implode( '/', array(
			$base,
			$install['github_updater_repo'],
			'repository/archive.zip',
		) );
		$install['download_link'] = add_query_arg( 'ref', $install['github_updater_branch'], $install['download_link'] );

		/*
		 * Add access token if present.
		 */
		if ( ! empty( $install['gitlab_access_token'] ) ) {
			$install['download_link']            = add_query_arg( 'private_token', $install['gitlab_access_token'], $install['download_link'] );
			parent::$options[ $install['repo'] ] = $install['gitlab_access_token'];
			if ( 'gitlab.com' === $headers['host'] ) {
				parent::$options['gitlab_access_token'] = empty( parent::$options['gitlab_access_token'] ) ? $install['gitlab_access_token'] : parent::$options['gitlab_access_token'];
			} else {
				parent::$options['gitlab_enterprise_token'] = empty( parent::$options['gitlab_enterprise_token'] ) ? $install['gitlab_access_token'] : parent::$options['gitlab_enterprise_token'];
			}
		} else {
			if ( 'gitlab.com' === $headers['host'] ) {
				$install['download_link'] = add_query_arg( 'private_token', parent::$options['gitlab_access_token'], $install['download_link'] );
			} else {
				$install['download_link'] = add_query_arg( 'private_token', parent::$options['gitlab_enterprise_token'], $install['download_link'] );
			}
		}

		return $install;
	}

}
