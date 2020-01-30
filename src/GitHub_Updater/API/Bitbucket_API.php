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
 * Class Bitbucket_API
 *
 * Get remote data from a Bitbucket repo.
 *
 * @author  Andy Fragen
 */
class Bitbucket_API extends API implements API_Interface {
	/**
	 * Constructor.
	 *
	 * @access public
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
		if ( $this instanceof Bitbucket_API ) {
			$username = 'bitbucket_username';
			$password = 'bitbucket_password';
		}
		if ( $this instanceof Bitbucket_Server_API ) {
			$username = 'bitbucket_server_username';
			$password = 'bitbucket_server_password';
		}
		if ( ! isset( static::$options[ $username ] ) ) {
			static::$options[ $username ] = null;
			$set_credentials              = true;
		}
		if ( ! isset( static::$options[ $password ] ) ) {
			static::$options[ $password ] = null;
			$set_credentials              = true;
		}
		if ( ( empty( static::$options[ $username ] ) || empty( static::$options[ $password ] ) ) &&
			( ( 'bitbucket_username' === $username &&
				in_array( 'bitbucket', $running_servers, true ) ) ||
			( 'bitbucket_server_username' === $username &&
				in_array( 'bbserver', $running_servers, true ) ) )
		) {
			Singleton::get_instance( 'Messages', $this )->create_error_message( 'bitbucket' );
			static::$error_code['bitbucket'] = [ 'code' => 401 ];
		}
		if ( $set_credentials ) {
			add_site_option( 'github_updater', static::$options );
		}
	}

	/**
	 * Read the remote file and parse headers.
	 *
	 * @access public
	 *
	 * @param string $file The file.
	 *
	 * @return bool
	 */
	public function get_remote_info( $file ) {
		return $this->get_remote_api_info( 'bitbucket', $file, "/2.0/repositories/:owner/:repo/src/:branch/{$file}" );
	}

	/**
	 * Get the remote info for tags.
	 *
	 * @access public
	 *
	 * @return bool
	 */
	public function get_remote_tag() {
		return $this->get_remote_api_tag( 'bitbucket', '/2.0/repositories/:owner/:repo/refs/tags' );
	}

	/**
	 * Read the remote CHANGES.md file.
	 *
	 * @access public
	 *
	 * @param string $changes The changelog filename.
	 *
	 * @return bool
	 */
	public function get_remote_changes( $changes ) {
		return $this->get_remote_api_changes( 'bitbucket', $changes, "/2.0/repositories/:owner/:repo/src/:branch/{$changes}" );
	}

	/**
	 * Read and parse remote readme.txt.
	 *
	 * @return bool
	 */
	public function get_remote_readme() {
		return $this->get_remote_api_readme( 'bitbucket', '/2.0/repositories/:owner/:repo/src/:branch/readme.txt' );
	}

	/**
	 * Read the repository meta from API
	 *
	 * @return bool
	 */
	public function get_repo_meta() {
		return $this->get_remote_api_repo_meta( 'bitbucket', '/2.0/repositories/:owner/:repo' );
	}

	/**
	 * Create array of branches and download links as array.
	 *
	 * @return bool
	 */
	public function get_remote_branches() {
		return $this->get_remote_api_branches( 'bitbucket', '/2.0/repositories/:owner/:repo/refs/branches' );
	}

	/**
	 * Return the Bitbucket release asset URL.
	 *
	 * @return string
	 */
	public function get_release_asset() {
		return $this->get_api_release_asset( 'bitbucket', '/2.0/repositories/:owner/:repo/downloads' );
	}

	/**
	 * Construct $this->type->download_link using Bitbucket API
	 *
	 * @param boolean $branch_switch For direct branch changing. Defaults to false.
	 *
	 * @return string $endpoint
	 */
	public function construct_download_link( $branch_switch = false ) {
		self::$method       = 'download_link';
		$download_link_base = $this->get_api_url( '/:owner/:repo/get/', true );
		$endpoint           = '';

		// Release asset.
		if ( $this->type->release_asset && '0.0.0' !== $this->type->newest_tag ) {
			$release_asset = $this->get_release_asset();

			return $this->get_release_asset_redirect( $release_asset, true );
		}

		/*
		 * If a branch has been given, use branch.
		 * If branch is master (default) and tags are used, use newest tag.
		 */
		if ( 'master' !== $this->type->branch || empty( $this->type->tags ) ) {
			if ( ! empty( $this->type->enterprise_api ) ) {
				$endpoint = add_query_arg( 'at', $this->type->branch, $endpoint );
			} else {
				$endpoint .= $this->type->branch . '.zip';
			}
		} else {
			if ( ! empty( $this->type->enterprise_api ) ) {
				$endpoint = add_query_arg( 'at', $this->type->newest_tag, $endpoint );
			} else {
				$endpoint .= $this->type->newest_tag . '.zip';
			}
		}

		/*
		 * Create endpoint for branch switching.
		 */
		if ( $branch_switch ) {
			if ( ! empty( $this->type->enterprise_api ) ) {
				$endpoint = add_query_arg( 'at', $branch_switch, $endpoint );
			} else {
				$endpoint = $branch_switch . '.zip';
			}
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
	 * Create Bitbucket API endpoints.
	 *
	 * @param Bitbucket_API|API $git      Git host API.
	 * @param string            $endpoint Endpoint.
	 *
	 * @return string|void $endpoint
	 */
	public function add_endpoints( $git, $endpoint ) {
		switch ( $git::$method ) {
			case 'file':
			case 'readme':
			case 'meta':
			case 'changes':
			case 'translation':
			case 'release_asset':
			case 'download_link':
				break;
			case 'tags':
			case 'branches':
				$endpoint = add_query_arg(
					[
						'pagelen' => '100',
						'sort'    => '-name',
					],
					$endpoint
				);
				break;
			default:
				break;
		}

		$endpoint = $this->add_access_token_endpoint( $git, $endpoint );

		return $endpoint;
	}

	/**
	 * Parse API response call and return only array of tag numbers.
	 *
	 * @param \stdClass $response Response from API call.
	 *
	 * @return array|\stdClass Array of tag numbers, object is error.
	 */
	public function parse_tag_response( $response ) {
		if ( ! isset( $response->values ) || $this->validate_response( $response ) ) {
			return $response;
		}

		$arr = [];
		array_map(
			function ( $e ) use ( &$arr ) {
				$arr[] = $e->name;

				return $arr;
			},
			(array) $response->values
		);

		if ( ! $arr ) {
			$arr          = new \stdClass();
			$arr->message = 'No tags found';
		}

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
				$arr['private']      = $e->is_private;
				$arr['last_updated'] = $e->updated_on;
				$arr['watchers']     = 0;
				$arr['forks']        = 0;
				$arr['open_issues']  = 0;
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
			$branches[ $branch->name ]['commit_hash']      = $branch->target->hash;
			$branches[ $branch->name ]['commit_timestamp'] = $branch->target->date;
		}

		return $branches;
	}

	/**
	 * Parse tags and create download links.
	 *
	 * @param \stdClass|array $response  Response from API call.
	 * @param string          $repo_type Repo type.
	 *
	 * @return array
	 */
	protected function parse_tags( $response, $repo_type ) {
		$tags     = [];
		$rollback = [];

		foreach ( (array) $response as $tag ) {
			$download_base    = "{$repo_type['base_download']}/{$this->type->owner}/{$this->type->owner}/get/";
			$tags[]           = $tag;
			$rollback[ $tag ] = $download_base . $tag . '.zip';
		}

		return [ $tags, $rollback ];
	}

	/**
	 * Add settings for Bitbucket Username and Password.
	 *
	 * @param array $auth_required Array of authorization data.
	 *
	 * @return void
	 */
	public function add_settings( $auth_required ) {
		add_settings_section(
			'bitbucket_user',
			esc_html__( 'Bitbucket Private Settings', 'github-updater' ),
			[ $this, 'print_section_bitbucket_username' ],
			'github_updater_bitbucket_install_settings'
		);

		add_settings_field(
			'bitbucket_username',
			esc_html__( 'Bitbucket Username', 'github-updater' ),
			[ Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ],
			'github_updater_bitbucket_install_settings',
			'bitbucket_user',
			[ 'id' => 'bitbucket_username' ]
		);

		add_settings_field(
			'bitbucket_password',
			esc_html__( 'Bitbucket Password', 'github-updater' ),
			[ Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ],
			'github_updater_bitbucket_install_settings',
			'bitbucket_user',
			[
				'id'    => 'bitbucket_password',
				'token' => true,
			]
		);

		/*
		 * Show section for private Bitbucket repositories.
		 */
		if ( $auth_required['bitbucket_private'] ) {
			add_settings_section(
				'bitbucket_id',
				esc_html__( 'Bitbucket Private Repositories', 'github-updater' ),
				[ $this, 'print_section_bitbucket_info' ],
				'github_updater_bitbucket_install_settings'
			);
		}
	}

	/**
	 * Add values for individual repo add_setting_field().
	 *
	 * @return mixed
	 */
	public function add_repo_setting_field() {
		$setting_field['page']            = 'github_updater_bitbucket_install_settings';
		$setting_field['section']         = 'bitbucket_id';
		$setting_field['callback_method'] = [
			Singleton::get_instance( 'Settings', $this ),
			'token_callback_checkbox',
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
				return array_merge( $subtabs, [ 'bitbucket' => esc_html__( 'Bitbucket', 'github-updater' ) ] );
			}
		);
	}

	/**
	 * Print the Bitbucket repo Settings text.
	 */
	public function print_section_bitbucket_info() {
		esc_html_e( 'Check box if private repository. Leave unchecked for public repositories.', 'github-updater' );
	}

	/**
	 * Print the Bitbucket user/pass Settings text.
	 */
	public function print_section_bitbucket_username() {
		esc_html_e( 'Enter your personal Bitbucket username and password.', 'github-updater' );
	}

	/**
	 * Add remote install settings fields.
	 *
	 * @param string $type Plugin|theme.
	 */
	public function add_install_settings_fields( $type ) {
		if ( ( empty( static::$options['bitbucket_username'] ) ||
			empty( static::$options['bitbucket_password'] ) ) ||

			( empty( static::$options['bitbucket_server_username'] ) ||
			empty( static::$options['bitbucket_server_password'] ) )
		) {
			add_settings_field(
				'bitbucket_username',
				esc_html__( 'Bitbucket Username', 'github-updater' ),
				[ $this, 'bitbucket_username' ],
				'github_updater_install_' . $type,
				$type
			);

			add_settings_field(
				'bitbucket_password',
				esc_html__( 'Bitbucket Password', 'github-updater' ),
				[ $this, 'bitbucket_password' ],
				'github_updater_install_' . $type,
				$type
			);
		}

		add_settings_field(
			'is_private',
			esc_html__( 'Private Bitbucket Repository', 'github-updater' ),
			[ $this, 'is_private_repo' ],
			'github_updater_install_' . $type,
			$type
		);
	}

	/**
	 * Setting for private repo for remote install.
	 */
	public function is_private_repo() {
		?>
		<label for="is_private">
			<input class="bitbucket_setting" type="checkbox" id="is_private" name="is_private" <?php checked( '1', false ); ?> >
			<br>
			<span class="description">
				<?php esc_html_e( 'Check for private Bitbucket repositories.', 'github-updater' ); ?>
			</span>
		</label>
		<?php
	}

	/**
	 * Bitbucket username for remote install.
	 */
	public function bitbucket_username() {
		?>
		<label for="bitbucket_username">
			<input class="bitbucket_setting" type="text" style="width:50%;" id="bitbucket_username" name="bitbucket_username" value="">
			<br>
			<span class="description">
				<?php esc_html_e( 'Enter Bitbucket username.', 'github-updater' ); ?>
			</span>
		</label>
		<?php
	}

	/**
	 * Bitbucket password for remote install.
	 */
	public function bitbucket_password() {
		?>
		<label for="bitbucket_password">
			<input class="bitbucket_setting" type="password" style="width:50%;" id="bitbucket_password" name="bitbucket_password" value="" autocomplete="new-password">
			<br>
			<span class="description">
				<?php esc_html_e( 'Enter Bitbucket password.', 'github-updater' ); ?>
			</span>
		</label>
		<?php
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
		$bitbucket_org = true;

		if ( 'bitbucket.org' === $headers['host'] || empty( $headers['host'] ) ) {
			$base            = 'https://bitbucket.org';
			$headers['host'] = 'bitbucket.org';
		} else {
			$base          = $headers['base_uri'];
			$bitbucket_org = false;
		}

		if ( $bitbucket_org ) {
			$install['download_link'] = "{$base}/{$install['github_updater_repo']}/get/{$install['github_updater_branch']}.zip";
			if ( isset( $install['is_private'] ) ) {
				$install['options'][ $install['repo'] ] = 1;
			}
			if ( ! empty( $install['bitbucket_username'] ) ) {
				$install['options']['bitbucket_username'] = $install['bitbucket_username'];
			}
			if ( ! empty( $install['bitbucket_password'] ) ) {
				$install['options']['bitbucket_password'] = $install['bitbucket_password'];
			}
		}

		return $install;
	}
}
