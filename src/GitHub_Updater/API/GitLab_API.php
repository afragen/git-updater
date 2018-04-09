<?php
/**
 * GitHub Updater
 *
 * @package   GitHub_Updater
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

namespace Fragen\GitHub_Updater\API;

use Fragen\Singleton,
	Fragen\GitHub_Updater\API,
	Fragen\GitHub_Updater\Branch,
	Fragen\GitHub_Updater\Readme_Parser;


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
		parent::__construct();
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
		$running_servers = Singleton::get_instance( 'Base', $this )->get_running_git_servers();
		$set_credentials = false;
		if ( ! isset( static::$options['gitlab_access_token'] ) ) {
			static::$options['gitlab_access_token'] = null;
			$set_credentials                        = true;
		}
		if ( ! isset( static::$options['gitlab_enterprise_token'] ) ) {
			static::$options['gitlab_enterprise_token'] = null;
			$set_credentials                            = true;
		}
		if ( ( empty( static::$options['gitlab_enterprise_token'] ) &&
		       ! empty( $this->type->enterprise ) ) ||
		     ( empty( static::$options['gitlab_access_token'] ) &&
		       in_array( 'gitlab', $running_servers, true ) )
		) {
			$this->gitlab_error_notices();
		}
		if ( $set_credentials ) {
			add_site_option( 'github_updater', static::$options );
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
				$response = $this->base->get_file_headers( $contents, $this->type->type );
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

		$tags = $this->parse_tags( $response, $repo_type );
		$this->sort_tags( $tags );

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
		if ( ! $response && ! $this->base->can_update_repo( $this->type ) ) {
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
		if ( ! $response && ! $this->base->can_update_repo( $this->type ) ) {
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
		     ( isset( $_GET['action'], $_GET['theme'] ) &&
		       'upgrade-theme' === $_GET['action'] &&
		       $this->type->repo === $_GET['theme'] )
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
	 * Create release asset download link.
	 * Filename must be `{$slug}-{$newest_tag}.zip`
	 *
	 * @access private
	 *
	 * @return string $download_link
	 */
	private function make_release_asset_download_link() {
		$download_link = implode( '/', array(
			'https://gitlab.com/api/v3/projects',
			urlencode( $this->type->owner . '/' . $this->type->repo ),
			'builds/artifacts',
			$this->type->newest_tag,
			'download',
		) );
		$download_link = add_query_arg( 'job', $this->type->ci_job, $download_link );

		return $download_link;
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
	 * Parse tags and create download links.
	 *
	 * @param $response
	 * @param $repo_type
	 *
	 * @return array
	 */
	private function parse_tags( $response, $repo_type ) {
		$tags     = array();
		$rollback = array();

		foreach ( (array) $response as $tag ) {
			$download_link    = implode( '/', array(
				$repo_type['base_download'],
				$this->type->owner,
				$this->type->repo,
				'repository/archive.zip',
			) );
			$download_link    = add_query_arg( 'ref', $tag, $download_link );
			$tags[]           = $tag;
			$rollback[ $tag ] = $download_link;
		}

		return array( $tags, $rollback );
	}

	/**
	 * Add settings for GitLab.com, GitLab Community Edition.
	 * or GitLab Enterprise Access Token.
	 *
	 * @param array $auth_required
	 *
	 * @return void
	 */
	public function add_settings( $auth_required ) {
		if ( $auth_required['gitlab'] || $auth_required['gitlab_enterprise'] ) {
			add_settings_section(
				'gitlab_settings',
				esc_html__( 'GitLab Personal Access Token', 'github-updater' ),
				array( &$this, 'print_section_gitlab_token' ),
				'github_updater_gitlab_install_settings'
			);
		}

		if ( $auth_required['gitlab_private'] ) {
			add_settings_section(
				'gitlab_id',
				esc_html__( 'GitLab Private Settings', 'github-updater' ),
				array( &$this, 'print_section_gitlab_info' ),
				'github_updater_gitlab_install_settings'
			);
		}

		if ( $auth_required['gitlab'] ) {
			add_settings_field(
				'gitlab_access_token',
				esc_html__( 'GitLab.com Access Token', 'github-updater' ),
				array( Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ),
				'github_updater_gitlab_install_settings',
				'gitlab_settings',
				array( 'id' => 'gitlab_access_token', 'token' => true )
			);
		}

		if ( $auth_required['gitlab_enterprise'] ) {
			add_settings_field(
				'gitlab_enterprise_token',
				esc_html__( 'GitLab CE or GitLab Enterprise Personal Access Token', 'github-updater' ),
				array( Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ),
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
		$setting_field['callback_method'] = array(
			Singleton::get_instance( 'Settings', $this ),
			'token_callback_text',
		);

		return $setting_field;
	}

	/**
	 * Print the GitLab Settings text.
	 */
	public function print_section_gitlab_info() {
		esc_html_e( 'Enter your repository specific GitLab Access Token.', 'github-updater' );
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
			<input class="gitlab_setting" type="password" style="width:50%;" name="gitlab_access_token" value="">
			<br>
			<span class="description">
				<?php esc_html_e( 'Enter GitLab Access Token for private GitLab repositories.', 'github-updater' ) ?>
			</span>
		</label>
		<?php
	}

	/**
	 * Display GitLab error admin notices.
	 */
	public function gitlab_error_notices() {
		add_action( 'admin_notices', array( &$this, 'gitlab_error' ) );
		add_action( 'network_admin_notices', array( &$this, 'gitlab_error', ) );
	}

	/**
	 * Generate error message for missing GitLab Private Token.
	 */
	public function gitlab_error() {
		$base       = Singleton::get_instance( 'Base', $this );
		$error_code = Singleton::get_instance( 'API_PseudoTrait', $this )->get_error_codes();

		if ( ! isset( $error_code['gitlab'] ) &&
		     ( ( empty( static::$options['gitlab_enterprise_token'] ) &&
		         $base::$auth_required['gitlab_enterprise'] ) ||
		       ( empty( static::$options['gitlab_access_token'] ) &&
		         $base::$auth_required['gitlab'] ) )

		) {
			self::$error_code['gitlab'] = array( 'error' => true );
			if ( ! \PAnD::is_admin_notice_active( 'gitlab-error-1' ) ) {
				return;
			}
			?>
			<div data-dismissible="gitlab-error-1" class="error notice is-dismissible">
				<p>
					<?php esc_html_e( 'You must set a GitLab.com, GitLab CE, or GitLab Enterprise Access Token.', 'github-updater' ); ?>
				</p>
			</div>
			<?php
		}
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
		$gitlab_com = true;

		if ( 'gitlab.com' === $headers['host'] || empty( $headers['host'] ) ) {
			$base            = 'https://gitlab.com';
			$headers['host'] = 'gitlab.com';
		} else {
			$base       = $headers['base_uri'];
			$gitlab_com = false;
		}

		$install['download_link'] = implode( '/', array(
			$base,
			$install['github_updater_repo'],
			'repository/archive.zip',
		) );
		$install['download_link'] = add_query_arg( 'ref', $install['github_updater_branch'], $install['download_link'] );

		/*
		 * Add/Save access token if present.
		 */
		if ( ! empty( $install['gitlab_access_token'] ) ) {
			$install['options'][ $install['repo'] ] = $install['gitlab_access_token'];
			if ( $gitlab_com ) {
				$install['options']['gitlab_access_token'] = $install['gitlab_access_token'];
			} else {
				$install['options']['gitlab_enterprise_token'] = $install['gitlab_access_token'];
			}
		}
		if ( $gitlab_com ) {
			$token = ! empty( $install['options']['gitlab_access_token'] )
				? $install['options']['gitlab_access_token']
				: static::$options['gitlab_access_token'];
		} else {
			$token = ! empty( $install['options']['gitlab_enterprise_token'] )
				? $install['options']['gitlab_enterprise_token']
				: static::$options['gitlab_enterprise_token'];
		}

		if ( ! empty( $token ) ) {
			$install['download_link'] = add_query_arg( 'private_token', $token, $install['download_link'] );
		}

		if ( ! empty( static::$options['gitlab_access_token'] ) ) {
			unset( $install['options']['gitlab_access_token'] );
		}
		if ( ! empty( static::$options['gitlab_enterprise_token'] ) ) {
			unset( $install['options']['gitlab_enterprise_token'] );
		}

		return $install;
	}

}
