<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

namespace Fragen\GitHub_Updater\API;

use Fragen\Singleton;
use Fragen\GitHub_Updater\API;
use Fragen\GitHub_Updater\Branch;

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
 * @author  Andy Fragen
 */
class GitLab_API extends API implements API_Interface {
	/**
	 * Constructor.
	 *
	 * @param \stdClass $type plugin|theme.
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
		$this->settings_hook( $this );
		$this->add_settings_subtab();
		$this->add_install_fields( $this );
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
			in_array( 'gitlabce', $running_servers, true ) ) ||
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
		$id = $this->get_gitlab_id();

		return $this->get_remote_api_info( 'gitlab', $file, "/projects/{$id}/repository/files/{$file}" );
	}

	/**
	 * Get remote info for tags.
	 *
	 * @return bool
	 */
	public function get_remote_tag() {
		$id = $this->get_gitlab_id();

		return $this->get_remote_api_tag( 'gitlab', "/projects/{$id}/repository/tags" );
	}

	/**
	 * Read the remote CHANGES.md file.
	 *
	 * @param string $changes Changelog filename.
	 *
	 * @return bool
	 */
	public function get_remote_changes( $changes ) {
		$id = $this->get_gitlab_id();

		return $this->get_remote_api_changes( 'gitlab', $changes, "/projects/{$id}/repository/files/{$changes}" );
	}

	/**
	 * Read and parse remote readme.txt.
	 *
	 * @return bool
	 */
	public function get_remote_readme() {
		$id = $this->get_gitlab_id();

		return $this->get_remote_api_readme( 'gitlab', "/projects/{$id}/repository/files/readme.txt" );
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

			// exit if transient is empty.
			if ( ! $project ) {
				return false;
			}

			$response = ( $this->type->slug === $project->path ) ? $project : false;

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
		$id = $this->get_gitlab_id();

		return $this->get_remote_api_branches( 'gitlab', "/projects/{$id}/repository/branches" );
	}

	/**
	 * Get GitLab release asset download link.
	 *
	 * @return string|bool
	 */
	public function get_release_asset() {
		return $this->get_api_release_asset( 'gitlab', "/projects/{$this->response['project_id']}/jobs/artifacts/{$this->type->newest_tag}/download" );
	}

	/**
	 * Construct $this->type->download_link using GitLab API v4.
	 *
	 * @param boolean $branch_switch for direct branch changing.
	 *
	 * @return string $endpoint
	 */
	public function construct_download_link( $branch_switch = false ) {
		self::$method       = 'download_link';
		$download_link_base = $this->get_api_url( "/projects/{$this->get_gitlab_id()}/repository/archive.zip" );
		$download_link_base = remove_query_arg( 'private_token', $download_link_base );

		$endpoint = '';
		$endpoint = add_query_arg( 'sha', $this->type->branch, $endpoint );

		// Release asset.
		if ( $this->type->ci_job && '0.0.0' !== $this->type->newest_tag ) {
			$release_asset = $this->get_release_asset();

			return $release_asset;
		}

		// If branch is master (default) and tags are used, use newest tag.
		if ( 'master' === $this->type->branch && ! empty( $this->type->tags ) ) {
			$endpoint = add_query_arg( 'sha', $this->type->newest_tag, $endpoint );
		}

		// Create endpoint for branch switching.
		if ( $branch_switch ) {
			$endpoint = add_query_arg( 'sha', $branch_switch, $endpoint );
		}

		$endpoint      = $this->add_access_token_endpoint( $this, $endpoint );
		$download_link = $download_link_base . $endpoint;

		/**
		 * Filter download link so developers can point to specific ZipFile
		 * to use as a download link during a branch switch.
		 *
		 * @since 8.8.0
		 *
		 * @param string    $download_link Download URL.
		 * @param /stdClass $this->type    Repository object.
		 * @param string    $branch_switch Branch or tag for rollback or branch switching.
		 */
		return apply_filters( 'github_updater_post_construct_download_link', $download_link, $this->type, $branch_switch );
	}

	/**
	 * Create GitLab API endpoints.
	 *
	 * @param GitLab_API|API $git      Git host specific API object.
	 * @param string         $endpoint Endpoint.
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
			case 'download_link':
				break;
			case 'file':
			case 'changes':
			case 'readme':
				$endpoint = add_query_arg( 'ref', $git->type->branch, $endpoint );
				break;
			case 'translation':
				$endpoint = add_query_arg( 'ref', 'master', $endpoint );
				break;
			case 'release_asset':
				$endpoint = add_query_arg( 'job', $git->type->ci_job, $endpoint );
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
			$id           = implode( '/', [ $this->type->owner, $this->type->slug ] );
			$id           = rawurlencode( $id );
			$response     = $this->api( '/projects/' . $id );

			if ( $this->validate_response( $response ) ) {
				return $id;
			}

			if ( $response && $this->type->slug === $response->path ) {
				$id = $response->id;
				$this->set_repo_cache( 'project_id', $id );
				$this->set_repo_cache( 'project', $response );
			}

			return $id;
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
		if ( $this->validate_response( $response ) ) {
			return $response;
		}

		$arr = [];
		array_map(
			function ( $e ) use ( &$arr ) {
				$arr[] = $e->name;

				return $arr;
			},
			(array) $response
		);

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
		if ( $this->validate_response( $response ) ) {
			return $response;
		}
		$arr      = [];
		$response = [ $response ];

		array_filter(
			$response,
			function ( $e ) use ( &$arr ) {
				$arr['private']      = isset( $e->visibility ) && 'private' === $e->visibility ? true : false;
				$arr['private']      = isset( $e->public ) ? ! $e->public : $arr['private'];
				$arr['last_updated'] = $e->last_activity_at;
				$arr['watchers']     = 0;
				$arr['forks']        = $e->forks_count;
				$arr['open_issues']  = isset( $e->open_issues_count ) ? $e->open_issues_count : 0;
			}
		);

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
		if ( $this->validate_response( $response ) ) {
			return $response;
		}

		$arr      = [];
		$response = [ $response ];

		array_filter(
			$response,
			function ( $e ) use ( &$arr ) {
				$arr['changes'] = $e->content;
			}
		);

		return $arr;
	}

	/**
	 * Parse API response and return array of branch data.
	 *
	 * @param \stdClass $response API response.
	 *
	 * @return array Array of branch data.
	 */
	public function parse_branch_response( $response ) {
		if ( $this->validate_response( $response ) ) {
			return $response;
		}
		$branches = [];
		foreach ( $response as $branch ) {
			$branches[ $branch->name ]['download']         = $this->construct_download_link( $branch->name );
			$branches[ $branch->name ]['commit_hash']      = $branch->commit->id;
			$branches[ $branch->name ]['commit_timestamp'] = $branch->commit->committed_date;
		}

		return $branches;
	}

	/**
	 * Parse tags and create download links.
	 *
	 * @param \stdClass|array $response  Response from API call.
	 * @param array           $repo_type Array of repo data.
	 *
	 * @return array
	 */
	protected function parse_tags( $response, $repo_type ) {
		$tags     = [];
		$rollback = [];

		foreach ( (array) $response as $tag ) {
			$download_link    = "/projects/{$this->get_gitlab_id()}/repository/archive.zip";
			$download_link    = $this->get_api_url( $download_link );
			$download_link    = add_query_arg( 'sha', $tag, $download_link );
			$tags[]           = $tag;
			$rollback[ $tag ] = $download_link;
		}

		return [ $tags, $rollback ];
	}

	/**
	 * Add settings for GitLab.com, GitLab Community Edition.
	 * or GitLab Enterprise Access Token.
	 *
	 * @param array $auth_required Array of authentication data.
	 *
	 * @return void
	 */
	public function add_settings( $auth_required ) {
		if ( $auth_required['gitlab'] || $auth_required['gitlab_enterprise'] ) {
			add_settings_section(
				'gitlab_settings',
				esc_html__( 'GitLab Personal Access Token', 'github-updater' ),
				[ $this, 'print_section_gitlab_token' ],
				'github_updater_gitlab_install_settings'
			);
		}

		if ( $auth_required['gitlab_private'] ) {
			add_settings_section(
				'gitlab_id',
				esc_html__( 'GitLab Private Settings', 'github-updater' ),
				[ $this, 'print_section_gitlab_info' ],
				'github_updater_gitlab_install_settings'
			);
		}

		if ( $auth_required['gitlab'] ) {
			add_settings_field(
				'gitlab_access_token',
				esc_html__( 'GitLab.com Access Token', 'github-updater' ),
				[ Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ],
				'github_updater_gitlab_install_settings',
				'gitlab_settings',
				[
					'id'    => 'gitlab_access_token',
					'token' => true,
				]
			);
		}

		if ( $auth_required['gitlab_enterprise'] ) {
			add_settings_field(
				'gitlab_enterprise_token',
				esc_html__( 'GitLab CE or GitLab Enterprise Personal Access Token', 'github-updater' ),
				[ Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ],
				'github_updater_gitlab_install_settings',
				'gitlab_settings',
				[
					'id'    => 'gitlab_enterprise_token',
					'token' => true,
				]
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
		$setting_field['callback_method'] = [
			Singleton::get_instance( 'Settings', $this ),
			'token_callback_text',
		];

		return $setting_field;
	}

	/**
	 * Add subtab to Settings page.
	 */
	private function add_settings_subtab() {
		add_filter(
			'github_updater_add_settings_subtabs',
			function ( $subtabs ) {
				return array_merge( $subtabs, [ 'gitlab' => esc_html__( 'GitLab', 'github-updater' ) ] );
			}
		);
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
	 * @param string $type Plugin|theme.
	 */
	public function add_install_settings_fields( $type ) {
		add_settings_field(
			'gitlab_access_token',
			esc_html__( 'GitLab Access Token', 'github-updater' ),
			[ $this, 'gitlab_access_token' ],
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
			<input class="gitlab_setting" type="password" style="width:50%;" id="gitlab_access_token" name="gitlab_access_token" value="" autocomplete="new-password">
			<br>
			<span class="description">
				<?php esc_html_e( 'Enter GitLab Access Token for private GitLab repositories.', 'github-updater' ); ?>
			</span>
		</label>
		<?php
	}

	/**
	 * Display GitLab error admin notices.
	 */
	public function gitlab_error_notices() {
		add_action( is_multisite() ? 'network_admin_notices' : 'admin_notices', [ $this, 'gitlab_error' ] );
	}

	/**
	 * Generate error message for missing GitLab Private Token.
	 */
	public function gitlab_error() {
		$auth_required = $this->get_class_vars( 'Settings', 'auth_required' );
		$error_code    = $this->get_error_codes();

		if ( ! isset( $error_code['gitlab'] ) &&
			( ( empty( static::$options['gitlab_enterprise_token'] ) &&
				$auth_required['gitlab_enterprise'] ) ||
			( empty( static::$options['gitlab_access_token'] ) &&
				$auth_required['gitlab'] ) )
		) {
			self::$error_code['gitlab'] = [ 'error' => true ];
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
	 * @param array $headers Array of headers.
	 * @param array $install Array of install data.
	 *
	 * @return mixed $install
	 */
	public function remote_install( $headers, $install ) {
		$gitlab_com                         = true;
		$options['gitlab_access_token']     = isset( static::$options['gitlab_access_token'] ) ? static::$options['gitlab_access_token'] : null;
		$options['gitlab_enterprise_token'] = isset( static::$options['gitlab_enterprise_token'] ) ? static::$options['gitlab_enterprise_token'] : null;

		if ( 'gitlab.com' === $headers['host'] || empty( $headers['host'] ) ) {
			$base            = 'https://gitlab.com';
			$headers['host'] = 'gitlab.com';
		} else {
			$base       = $headers['base_uri'];
			$gitlab_com = false;
		}

		$id                       = rawurlencode( $install['github_updater_repo'] );
		$install['download_link'] = "{$base}/api/v4/projects/{$id}/repository/archive.zip";
		$install['download_link'] = add_query_arg( 'sha', $install['github_updater_branch'], $install['download_link'] );

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
				: $options['gitlab_access_token'];
		} else {
			$token = ! empty( $install['options']['gitlab_enterprise_token'] )
				? $install['options']['gitlab_enterprise_token']
				: $options['gitlab_enterprise_token'];
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
