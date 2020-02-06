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
use Fragen\GitHub_Updater\Traits\GHU_Trait;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Gitea_API
 *
 * Get remote data from a Gitea repo.
 *
 * @author  Andy Fragen
 * @author  Marco Betschart
 */
class Gitea_API extends API implements API_Interface {
	use GHU_Trait;

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
		if ( ! isset( static::$options['gitea_access_token'] ) ) {
			static::$options['gitea_access_token'] = null;
			$set_credentials                       = true;
		}
		if ( empty( static::$options['gitea_access_token'] ) &&
			in_array( 'gitea', $running_servers, true )
		) {
			$this->gitea_error_notices();
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
		return $this->get_remote_api_info( 'gitea', $file, "/repos/:owner/:repo/raw/:branch/{$file}" );
	}

	/**
	 * Get remote info for tags.
	 *
	 * @return bool
	 */
	public function get_remote_tag() {
		return $this->get_remote_api_tag( 'gitea', '/repos/:owner/:repo/releases' );
	}

	/**
	 * Read the remote CHANGES.md file.
	 *
	 * @param string $changes Changelog filename.
	 *
	 * @return mixed
	 */
	public function get_remote_changes( $changes ) {
		return $this->get_remote_api_changes( 'gitea', $changes, "/repos/:owner/:repo/raw/:branch/{$changes}" );
	}

	/**
	 * Read and parse remote readme.txt.
	 *
	 * @return mixed
	 */
	public function get_remote_readme() {
		return $this->get_remote_api_readme( 'gitea', '/repos/:owner/:repo/raw/:branch/readme.txt' );
	}

	/**
	 * Read the repository meta from API.
	 *
	 * @return mixed
	 */
	public function get_repo_meta() {
		return $this->get_remote_api_repo_meta( 'gitea', '/repos/:owner/:repo' );
	}

	/**
	 * Create array of branches and download links as array.
	 *
	 * @return mixed
	 */
	public function get_remote_branches() {
		return $this->get_remote_api_branches( 'gitea', '/repos/:owner/:repo/branches' );
	}

	/**
	 * Get Gitea release asset.
	 *
	 * @return false
	 */
	public function get_release_asset() {
		// TODO: eventually figure this out.
		return false;
	}

	/**
	 * Construct $this->type->download_link using Gitea API.
	 *
	 * @param boolean $branch_switch For direct branch changing.
	 *
	 * @return string $endpoint
	 */
	public function construct_download_link( $branch_switch = false ) {
		self::$method       = 'download_link';
		$download_link_base = $this->get_api_url( '/repos/:owner/:repo/archive/', true );
		$endpoint           = '';

		/*
		 * If a branch has been given, use branch.
		 * If branch is master (default) and tags are used, use newest tag.
		 */
		if ( 'master' !== $this->type->branch || empty( $this->type->tags ) ) {
			$endpoint .= $this->type->branch . '.zip';
		} else {
			$endpoint .= $this->type->newest_tag . '.zip';
		}

		// Create endpoint for branch switching.
		if ( $branch_switch ) {
			$endpoint = $branch_switch . '.zip';
		}

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
	 * Create Gitea API endpoints.
	 *
	 * @param Gitea_API|API $git      Git host API object.
	 * @param string        $endpoint Endpoint.
	 *
	 * @return string $endpoint
	 */
	public function add_endpoints( $git, $endpoint ) {
		switch ( $git::$method ) {
			case 'file':
			case 'readme':
			case 'meta':
			case 'tags':
			case 'changes':
			case 'translation':
			case 'download_link':
				break;
			case 'branches':
				$endpoint = add_query_arg( 'per_page', '100', $endpoint );
				break;
			default:
				break;
		}

		return $endpoint;
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
				$arr[] = $e->tag_name;

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
				$arr['private']      = $e->private;
				$arr['last_updated'] = $e->updated_at;
				$arr['watchers']     = $e->watchers_count;
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
	 * @return void|array|\stdClass $arr Array of changes in base64, object if error.
	 */
	public function parse_changelog_response( $response ) {
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
			$branches[ $branch->name ]['commit_timestamp'] = $branch->commit->timestamp;
		}

		return $branches;
	}

	/**
	 * Parse tags and create download links.
	 *
	 * @param \stdClass|array $response  Response from API call.
	 * @param array           $repo_type Array of repository data.
	 *
	 * @return array
	 */
	protected function parse_tags( $response, $repo_type ) {
		$tags     = [];
		$rollback = [];

		foreach ( (array) $response as $tag ) {
			$download_link    = implode(
				'/',
				[
					$repo_type['base_uri'],
					'repos',
					$this->type->owner,
					$this->type->slug,
					'archive/',
				]
			);
			$tags[]           = $tag;
			$rollback[ $tag ] = $download_link . $tag . '.zip';
		}

		return [ $tags, $rollback ];
	}

	/**
	 * Add settings for Gitea Access Token.
	 *
	 * @param array $auth_required Array of authentication data.
	 *
	 * @return void
	 */
	public function add_settings( $auth_required ) {
		if ( $auth_required['gitea'] ) {
			add_settings_section(
				'gitea_settings',
				esc_html__( 'Gitea Access Token', 'github-updater' ),
				[ $this, 'print_section_gitea_token' ],
				'github_updater_gitea_install_settings'
			);
		}

		if ( $auth_required['gitea_private'] ) {
			add_settings_section(
				'gitea_id',
				esc_html__( 'Gitea Private Settings', 'github-updater' ),
				[ $this, 'print_section_gitea_info' ],
				'github_updater_gitea_install_settings'
			);
		}

		if ( $auth_required['gitea'] ) {
			add_settings_field(
				'gitea_access_token',
				esc_html__( 'Gitea Access Token', 'github-updater' ),
				[ Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ],
				'github_updater_gitea_install_settings',
				'gitea_settings',
				[
					'id'    => 'gitea_access_token',
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
		$setting_field['page']            = 'github_updater_gitea_install_settings';
		$setting_field['section']         = 'gitea_id';
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
				return array_merge( $subtabs, [ 'gitea' => esc_html__( 'Gitea', 'github-updater' ) ] );
			}
		);
	}

	/**
	 * Print the Gitea Settings text.
	 */
	public function print_section_gitea_info() {
		esc_html_e( 'Enter your repository specific Gitea Access Token.', 'github-updater' );
	}

	/**
	 * Print the Gitea Access Token Settings text.
	 */
	public function print_section_gitea_token() {
		esc_html_e( 'Enter your Gitea Access Token.', 'github-updater' );
	}

	/**
	 * Add remote install settings fields.
	 *
	 * @param string $type Plugin|theme.
	 */
	public function add_install_settings_fields( $type ) {
		add_settings_field(
			'gitea_access_token',
			esc_html__( 'Gitea Access Token', 'github-updater' ),
			[ $this, 'gitea_access_token' ],
			'github_updater_install_' . $type,
			$type
		);
	}

	/**
	 * Gitea Access Token for remote install.
	 */
	public function gitea_access_token() {
		?>
		<label for="gitea_access_token">
			<input class="gitea_setting" type="password" style="width:50%;" id="gitea_access_token" name="gitea_access_token" value="" autocomplete="new-password">
			<br>
			<span class="description">
				<?php esc_html_e( 'Enter Gitea Access Token for private Gitea repositories.', 'github-updater' ); ?>
			</span>
		</label>
		<?php
	}

	/**
	 * Display Gitea error admin notices.
	 */
	public function gitea_error_notices() {
		add_action( is_multisite() ? 'network_admin_notices' : 'admin_notices', [ $this, 'gitea_error' ] );
	}

	/**
	 * Generate error message for missing Gitea Access Token.
	 */
	public function gitea_error() {
		$auth_required = $this->get_class_vars( 'Settings', 'auth_required' );
		$error_code    = $this->get_error_codes();

		if ( ! isset( $error_code['gitea'] ) &&
			empty( static::$options['gitea_access_token'] ) &&
			$auth_required['gitea']
		) {
			self::$error_code['gitea'] = [ 'error' => true ];
			if ( ! \PAnD::is_admin_notice_active( 'gitea-error-1' ) ) {
				return;
			}
			?>
			<div data-dismissible="gitea-error-1" class="error notice is-dismissible">
				<p>
					<?php esc_html_e( 'You must set a Gitea Access Token.', 'github-updater' ); ?>
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
		$options['gitea_access_token'] = isset( static::$options['gitea_access_token'] ) ? static::$options['gitea_access_token'] : null;

		$base = $headers['base_uri'] . '/api/v1';

		$install['download_link'] = "{$base}/repos/{$install['github_updater_repo']}/archive/{$install['github_updater_branch']}.zip";

		/*
		 * Add/Save access token if present.
		 */
		if ( ! empty( $install['gitea_access_token'] ) ) {
			$install['options'][ $install['repo'] ]   = $install['gitea_access_token'];
			$install['options']['gitea_access_token'] = $install['gitea_access_token'];
		}

		$token = ! empty( $install['options']['gitea_access_token'] )
			? $install['options']['gitea_access_token']
			: $options['gitea_access_token'];

		if ( ! empty( static::$options['gitea_access_token'] ) ) {
			unset( $install['options']['gitea_access_token'] );
		}

		return $install;
	}
}
