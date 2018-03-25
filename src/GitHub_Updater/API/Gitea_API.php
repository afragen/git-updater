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
 * Class Gitea_API
 *
 * Get remote data from a Gitea repo.
 *
 * @package Fragen\GitHub_Updater
 * @author  Andy Fragen
 * @author  Marco Betschart
 */
class Gitea_API extends API implements API_Interface {

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
		$response = isset( $this->response[ $file ] ) ? $this->response[ $file ] : false;

		if ( ! $response ) {
			self::$method = 'file';
			$response     = $this->api( '/repos/:owner/:repo/raw/:branch/' . $file );

			if ( $response ) {
				$contents = $response;
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
			self::$method = 'tags';
			$response     = $this->api( '/repos/:owner/:repo/releases' );

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
			self::$method = 'changes';
			$response     = $this->api( '/repos/:owner/:repo/raw/:branch/' . $changes );

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
			self::$method = 'readme';
			$response     = $this->api( '/repos/:owner/:repo/raw/:branch/readme.txt' );

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
			$response     = $this->api( '/repos/:owner/:repo' );

			if ( $response ) {
				$response = $this->parse_meta_response( $response );
				$this->set_repo_cache( 'meta', $response );
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
			self::$method = 'branches';
			$response     = $this->api( '/repos/:owner/:repo/branches' );

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
	 * Construct $this->type->download_link using Gitea API.
	 *
	 * @param boolean $rollback      for theme rollback
	 * @param boolean $branch_switch for direct branch changing
	 *
	 * @return string $endpoint
	 */
	public function construct_download_link( $rollback = false, $branch_switch = false ) {
		$download_link_base = $this->get_api_url( '/repos/:owner/:repo/archive/', true );
		$endpoint           = '';

		/*
		 * Check for rollback.
		 */
		if ( ! empty( $_GET['rollback'] ) &&
		     ( isset( $_GET['action'], $_GET['theme'] ) &&
		       'upgrade-theme' === $_GET['action'] &&
		       $this->type->repo === $_GET['theme'] )
		) {
			$endpoint .= $rollback . '.zip';

			/*
			 * For users wanting to update against branch other than master
			 * or if not using tags, else use newest_tag.
			 */
		} elseif ( 'master' !== $this->type->branch || empty( $this->type->tags ) ) {
			$endpoint .= $this->type->branch . '.zip';
		} else {
			$endpoint .= $this->type->newest_tag . '.zip';
		}

		/*
		 * Create endpoint for branch switching.
		 */
		if ( $branch_switch ) {
			$endpoint = $branch_switch . '.zip';
		}

		$endpoint = $this->add_access_token_endpoint( $this, $endpoint );

		return $download_link_base . $endpoint;
	}

	/**
	 * Create Gitea API endpoints.
	 *
	 * @param Gitea_API|API $git
	 * @param string        $endpoint
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
				break;
			case 'branches':
				$endpoint = add_query_arg( 'per_page', '100', $endpoint );
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
			$arr[] = $e->tag_name;

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
			$arr['private']      = $e->private;
			$arr['last_updated'] = $e->updated_at;
			$arr['watchers']     = $e->watchers_count;
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
			$arr['changes'] = base64_encode( $e );
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
				$repo_type['base_uri'],
				'repos',
				$this->type->owner,
				$this->type->repo,
				'archive/',
			) );
			$tags[]           = $tag;
			$rollback[ $tag ] = $download_link . $tag . '.zip';
		}

		return array( $tags, $rollback );
	}

	/**
	 * Add settings for Gitea Access Token.
	 *
	 * @param array $auth_required
	 *
	 * @return void
	 */
	public function add_settings( $auth_required ) {
		if ( $auth_required['gitea'] ) {
			add_settings_section(
				'gitea_settings',
				esc_html__( 'Gitea Access Token', 'github-updater' ),
				array( &$this, 'print_section_gitea_token' ),
				'github_updater_gitea_install_settings'
			);
		}

		if ( $auth_required['gitea_private'] ) {
			add_settings_section(
				'gitea_id',
				esc_html__( 'Gitea Private Settings', 'github-updater' ),
				array( &$this, 'print_section_gitea_info' ),
				'github_updater_gitea_install_settings'
			);
		}

		if ( $auth_required['gitea'] ) {
			add_settings_field(
				'gitea_access_token',
				esc_html__( 'Gitea Access Token', 'github-updater' ),
				array( Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ),
				'github_updater_gitea_install_settings',
				'gitea_settings',
				array( 'id' => 'gitea_access_token', 'token' => true )
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
		$setting_field['callback_method'] = array(
			Singleton::get_instance( 'Settings', $this ),
			'token_callback_text',
		);

		return $setting_field;
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
	 * @param string $type
	 */
	public function add_install_settings_fields( $type ) {
		add_settings_field(
			'gitea_access_token',
			esc_html__( 'Gitea Access Token', 'github-updater' ),
			array( &$this, 'gitea_access_token' ),
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
			<input class="gitea_setting" type="password" style="width:50%;" name="gitea_access_token" value="">
			<br>
			<span class="description">
				<?php esc_html_e( 'Enter Gitea Access Token for private Gitea repositories.', 'github-updater' ) ?>
			</span>
		</label>
		<?php
	}

	/**
	 * Display Gitea error admin notices.
	 */
	public function gitea_error_notices() {
		add_action( 'admin_notices', array( &$this, 'gitea_error' ) );
		add_action( 'network_admin_notices', array( &$this, 'gitea_error', ) );
	}

	/**
	 * Generate error message for missing Gitea Access Token.
	 */
	public function gitea_error() {
		$base       = Singleton::get_instance( 'Base', $this );
		$error_code = Singleton::get_instance( 'API_PseudoTrait', $this )->get_error_codes();

		if ( ! isset( $error_code['gitea'] ) &&
		     empty( static::$options['gitea_access_token'] ) &&
		     $base::$auth_required['gitea']
		) {
			self::$error_code['gitea'] = array( 'error' => true );
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
	 * @param array $headers
	 * @param array $install
	 *
	 * @return mixed $install
	 */
	public function remote_install( $headers, $install ) {
		$base = $headers['base_uri'] . '/api/v1';

		$install['download_link'] = implode( '/', array(
			$base,
			'repos',
			$install['github_updater_repo'],
			'archive',
			$install['github_updater_branch'] . '.zip',
		) );

		/*
		 * Add/Save access token if present.
		 */
		if ( ! empty( $install['gitea_access_token'] ) ) {
			$install['options'][ $install['repo'] ]   = $install['gitea_access_token'];
			$install['options']['gitea_access_token'] = $install['gitea_access_token'];
		}

		$token = ! empty( $install['options']['gitea_access_token'] )
			? $install['options']['gitea_access_token']
			: static::$options['gitea_access_token'];

		if ( ! empty( $token ) ) {
			$install['download_link'] = add_query_arg( 'access_token', $token, $install['download_link'] );
		}

		if ( ! empty( static::$options['gitea_access_token'] ) ) {
			unset( $install['options']['gitea_access_token'] );
		}

		return $install;
	}

}
