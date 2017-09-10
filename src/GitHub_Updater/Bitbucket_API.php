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
 * Class Bitbucket_API
 *
 * Get remote data from a Bitbucket repo.
 *
 * @package Fragen\GitHub_Updater
 * @author  Andy Fragen
 */
class Bitbucket_API extends API implements API_Interface {

	/**
	 * Constructor.
	 *
	 * @access public
	 *
	 * @param \stdClass $type The repo type.
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
		if ( $this instanceof Bitbucket_API ) {
			$username = 'bitbucket_username';
			$password = 'bitbucket_password';
		}
		if ( $this instanceof Bitbucket_Server_API ) {
			$username = 'bitbucket_server_username';
			$password = 'bitbucket_server_password';
		}
		if ( ! isset( self::$options[ $username ] ) ) {
			self::$options[ $username ] = null;
			$set_credentials            = true;
		}
		if ( ! isset( self::$options[ $password ] ) ) {
			self::$options[ $password ] = null;
			$set_credentials            = true;
		}
		if ( $set_credentials ) {
			add_site_option( 'github_updater', self::$options );
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
		$response = isset( $this->response[ $file ] ) ? $this->response[ $file ] : false;

		if ( ! $response ) {
			$response = $this->api( '/1.0/repositories/:owner/:repo/src/:branch/' . $file );

			if ( $response && isset( $response->data ) ) {
				$contents = $response->data;
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
	 * Get the remote info for tags.
	 *
	 * @access public
	 *
	 * @return bool
	 */
	public function get_remote_tag() {
		$repo_type = $this->return_repo_type();
		$response  = isset( $this->response['tags'] ) ? $this->response['tags'] : false;

		if ( ! $response ) {
			$response = $this->api( '/1.0/repositories/:owner/:repo/tags' );
			$arr_resp = (array) $response;

			if ( ! $response || ! $arr_resp ) {
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
	 * @access public
	 *
	 * @param string $changes The changelog filename.
	 *
	 * @return bool
	 */
	public function get_remote_changes( $changes ) {
		$response = isset( $this->response['changes'] ) ? $this->response['changes'] : false;

		/*
		 * Set $response from local file if no update available.
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
			$response = $this->api( '/1.0/repositories/:owner/:repo/src/:branch/' . $changes );

			if ( ! $response ) {
				$response          = new \stdClass();
				$response->message = 'No changelog found';
			}

			if ( $response ) {
				$response = $this->parse_changelog_response( $response );
				$this->set_repo_cache( 'changes', $response );
			}
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		$parser    = new \Parsedown;
		$changelog = $parser->text( $response['changes'] );

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
				$response->data = $content;
			} else {
				$response = false;
			}
		}

		if ( ! $response ) {
			$response = $this->api( '/1.0/repositories/:owner/:repo/src/:branch/' . 'readme.txt' );

			if ( ! $response ) {
				$response          = new \stdClass();
				$response->message = 'No readme found';
			}
		}

		if ( $response && isset( $response->data ) ) {
			$file     = $response->data;
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
	 * Read the repository meta from API
	 *
	 * @return bool
	 */
	public function get_repo_meta() {
		$response = isset( $this->response['meta'] ) ? $this->response['meta'] : false;

		if ( ! $response ) {
			$response = $this->api( '/2.0/repositories/:owner/:repo' );

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
			$response = $this->api( '/1.0/repositories/:owner/:repo/branches' );

			if ( $response ) {
				foreach ( $response as $branch => $api_response ) {
					$branches[ $branch ] = $this->construct_download_link( false, $branch );
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
	 * Construct $this->type->download_link using Bitbucket API
	 *
	 * @param boolean $rollback      For theme rollback. Defaults to false.
	 * @param boolean $branch_switch For direct branch changing. Defaults to false.
	 *
	 * @return string $endpoint
	 */
	public function construct_download_link( $rollback = false, $branch_switch = false ) {
		$download_link_base = $this->get_api_url( '/:owner/:repo/get/', true );

		$endpoint = '';

		if ( $this->type->release_asset && '0.0.0' !== $this->type->newest_tag ) {
			return $this->make_release_asset_download_link();
		}

		/*
		 * Check for rollback.
		 */
		if ( ! empty( $_GET['rollback'] ) &&
		     ( isset( $_GET['action'] ) && 'upgrade-theme' === $_GET['action'] ) &&
		     ( isset( $_GET['theme'] ) && $this->type->repo === $_GET['theme'] )
		) {
			$endpoint .= $rollback . '.zip';

			// For users wanting to update against branch other than master or not using tags, else use newest_tag.
		} elseif ( 'master' !== $this->type->branch || empty( $this->type->tags ) ) {
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

		return $download_link_base . $endpoint;
	}

	/**
	 * Added due to interface contract, not used for Bitbucket.
	 *
	 * @param Bitbucket_API|API $git
	 * @param string            $endpoint
	 *
	 * @return string|void $endpoint
	 */
	public function add_endpoints( $git, $endpoint ) {
	}

	/**
	 * Parse API response call and return only array of tag numbers.
	 *
	 * @param \stdClass $response Response from API call.
	 *
	 * @return array|\stdClass Array of tag numbers, object is error.
	 */
	public function parse_tag_response( $response ) {
		if ( isset( $response->message ) ) {
			return $response;
		}

		return array_keys( (array) $response );
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
			$arr['private']      = $e->is_private;
			$arr['last_updated'] = $e->updated_on;
			$arr['watchers']     = 0;
			$arr['forks']        = 0;
			$arr['open_issues']  = 0;
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
		if ( isset( $response->message ) ) {
			return $response;
		}

		$arr      = array();
		$response = array( $response );

		array_filter( $response, function( $e ) use ( &$arr ) {
			$arr['changes'] = $e->data;
		} );

		return $arr;
	}

	/**
	 * Add settings for Bitbucket Username and Password.
	 */
	public function add_settings() {
		add_settings_section(
			'bitbucket_user',
			esc_html__( 'Bitbucket Private Settings', 'github-updater' ),
			array( &$this, 'print_section_bitbucket_username' ),
			'github_updater_bitbucket_install_settings'
		);

		add_settings_field(
			'bitbucket_username',
			esc_html__( 'Bitbucket Username', 'github-updater' ),
			array( Singleton::get_instance( 'Settings' ), 'token_callback_text' ),
			'github_updater_bitbucket_install_settings',
			'bitbucket_user',
			array( 'id' => 'bitbucket_username' )
		);

		add_settings_field(
			'bitbucket_password',
			esc_html__( 'Bitbucket Password', 'github-updater' ),
			array( Singleton::get_instance( 'Settings' ), 'token_callback_text' ),
			'github_updater_bitbucket_install_settings',
			'bitbucket_user',
			array( 'id' => 'bitbucket_password', 'token' => true )
		);

		/*
		 * Show section for private Bitbucket repositories.
		 */
		if ( parent::$auth_required['bitbucket_private'] ) {
			add_settings_section(
				'bitbucket_id',
				esc_html__( 'Bitbucket Private Repositories', 'github-updater' ),
				array( &$this, 'print_section_bitbucket_info' ),
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
		$setting_field['callback_method'] = array(
			Singleton::get_instance( 'Settings' ),
			'token_callback_checkbox',
		);

		return $setting_field;
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
	 * @param string $type
	 */
	public function add_install_settings_fields( $type ) {
		if ( ( empty( parent::$options['bitbucket_username'] ) ||
		       empty( parent::$options['bitbucket_password'] ) ) ||

		     ( empty( parent::$options['bitbucket_server_username'] ) ||
		       empty( parent::$options['bitbucket_server_password'] ) )
		) {
			add_settings_field(
				'bitbucket_username',
				esc_html__( 'Bitbucket Username', 'github-updater' ),
				array( &$this, 'bitbucket_username' ),
				'github_updater_install_' . $type,
				$type
			);

			add_settings_field(
				'bitbucket_password',
				esc_html__( 'Bitbucket Password', 'github-updater' ),
				array( &$this, 'bitbucket_password' ),
				'github_updater_install_' . $type,
				$type
			);
		}

		add_settings_field(
			'is_private',
			esc_html__( 'Private Bitbucket Repository', 'github-updater' ),
			array( &$this, 'is_private_repo' ),
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
			<input class="bitbucket_setting" type="checkbox" name="is_private" <?php checked( '1', false ) ?> >
			<br>
			<span class="description">
				<?php esc_html_e( 'Check for private Bitbucket repositories.', 'github-updater' ) ?>
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
			<input class="bitbucket_setting" type="text" style="width:50%;" name="bitbucket_username" value="">
			<br>
			<span class="description">
				<?php esc_html_e( 'Enter Bitbucket username.', 'github-updater' ) ?>
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
			<input class="bitbucket_setting" type="text" style="width:50%;" name="bitbucket_password" value="">
			<br>
			<span class="description">
				<?php esc_html_e( 'Enter Bitbucket password.', 'github-updater' ) ?>
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
		$bitbucket_org = true;

		if ( 'bitbucket.org' === $headers['host'] || empty( $headers['host'] ) ) {
			$base            = 'https://bitbucket.org';
			$headers['host'] = 'bitbucket.org';
		} else {
			$base          = $headers['base_uri'];
			$bitbucket_org = false;
		}

		if ( $bitbucket_org ) {
			$install['download_link'] = implode( '/', array(
				$base,
				$install['github_updater_repo'],
				'get',
				$install['github_updater_branch'] . '.zip',
			) );
			if ( isset( $install['is_private'] ) ) {
				parent::$options[ $install['repo'] ] = 1;
			}
			if ( isset( $install['bitbucket_username'] ) ) {
				parent::$options['bitbucket_username'] = $install['bitbucket_username'];
			}
			if ( isset( $install['bitbucket_password'] ) ) {
				parent::$options['bitbucket_password'] = $install['bitbucket_password'];
			}
		}

		return $install;
	}

}
