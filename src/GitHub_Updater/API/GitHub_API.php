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
 * Class GitHub_API
 *
 * Get remote data from a GitHub repo.
 *
 * @package Fragen\GitHub_Updater
 * @author  Andy Fragen
 */
class GitHub_API extends API implements API_Interface {

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
			$response     = $this->api( '/repos/:owner/:repo/contents/' . $file );
			if ( ! isset( $response->content ) ) {
				return false;
			}

			if ( $response ) {
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
			self::$method = 'tags';
			$response     = $this->api( '/repos/:owner/:repo/tags' );

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
			$response     = $this->api( '/repos/:owner/:repo/contents/' . $changes );

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
			$response     = $this->api( '/repos/:owner/:repo/contents/readme.txt' );
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
	 * Construct $this->type->download_link using Repository Contents API.
	 * @url http://developer.github.com/v3/repos/contents/#get-archive-link
	 *
	 * @param boolean $rollback      for theme rollback
	 * @param boolean $branch_switch for direct branch changing
	 *
	 * @return string $endpoint
	 */
	public function construct_download_link( $rollback = false, $branch_switch = false ) {
		$download_link_base = $this->get_api_url( '/repos/:owner/:repo/zipball/', true );
		$endpoint           = '';

		/*
		 * If release asset.
		 */
		if ( $this->type->release_asset && '0.0.0' !== $this->type->newest_tag ) {
			return $this->get_github_release_asset_url();
		}

		/*
		 * Check for rollback.
		 */
		if ( ! empty( $_GET['rollback'] ) &&
		     ( isset( $_GET['action'], $_GET['theme'] ) &&
		       'upgrade-theme' === $_GET['action'] &&
		       $this->type->repo === $_GET['theme'] )
		) {
			$endpoint .= $rollback;

			/*
			 * For users wanting to update against branch other than master
			 * or if not using tags, else use newest_tag.
			 */
		} elseif ( 'master' !== $this->type->branch || empty( $this->type->tags ) ) {
			$endpoint .= $this->type->branch;
		} else {
			$endpoint .= $this->type->newest_tag;
		}

		/*
		 * Create endpoint for branch switching.
		 */
		if ( $branch_switch ) {
			$endpoint = $branch_switch;
		}

		$endpoint = $this->add_access_token_endpoint( $this, $endpoint );

		return $download_link_base . $endpoint;
	}

	/**
	 * Create GitHub API endpoints.
	 *
	 * @param GitHub_API|API $git
	 * @param string         $endpoint
	 *
	 * @return string $endpoint
	 */
	public function add_endpoints( $git, $endpoint ) {
		switch ( $git::$method ) {
			case 'file':
			case 'readme':
				$endpoint = add_query_arg( 'ref', $git->type->branch, $endpoint );
				break;
			case 'meta':
			case 'tags':
			case 'changes':
			case 'download_link':
			case 'translation':
				break;
			case 'branches':
				$endpoint = add_query_arg( 'per_page', '100', $endpoint );
				break;
			default:
				break;
		}

		$endpoint = $this->add_access_token_endpoint( $git, $endpoint );

		/*
		 * If GitHub Enterprise return this endpoint.
		 */
		if ( ! empty( $git->type->enterprise_api ) ) {
			return $git->type->enterprise_api . $endpoint;
		}

		return $endpoint;
	}

	/**
	 * Calculate and store time until rate limit reset.
	 *
	 * @param $response
	 * @param $repo
	 */
	public static function ratelimit_reset( $response, $repo ) {
		if ( isset( $response['headers']['x-ratelimit-reset'] ) ) {
			$reset                       = (integer) $response['headers']['x-ratelimit-reset'];
			$wait                        = date( 'i', $reset - time() );
			static::$error_code[ $repo ] = array_merge( static::$error_code[ $repo ], array(
				'git'  => 'github',
				'wait' => $wait,
			) );
		}
	}

	/**
	 * Parse API response call and return only array of tag numbers.
	 *
	 * @param \stdClass|array $response Response from API call.
	 *
	 * @return \stdClass|array $arr Array of tag numbers, object is error.
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
			$arr['private']      = $e->private;
			$arr['last_updated'] = $e->pushed_at;
			$arr['watchers']     = $e->watchers;
			$arr['forks']        = $e->forks;
			$arr['open_issues']  = $e->open_issues;
		} );

		return $arr;
	}

	/**
	 * Parse API response and return array with changelog in base64.
	 *
	 * @param \stdClass|array $response Response from API call.
	 *
	 * @return array $arr Array of changes in base64.
	 */
	public function parse_changelog_response( $response ) {
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
			$download_base    = implode( '/', array(
				$repo_type['base_uri'],
				'repos',
				$this->type->owner,
				$this->type->repo,
				'zipball/',
			) );
			$tags[]           = $tag;
			$rollback[ $tag ] = $download_base . $tag;

		}

		return array( $tags, $rollback );
	}

	/**
	 * Return the AWS download link for a GitHub release asset.
	 * AWS download link sets a link expiration of ONLY 5 minutes.
	 *
	 * @since 6.1.0
	 * @uses  Requests, requires WP 4.6
	 *
	 * @return array|bool|\stdClass
	 */
	private function get_github_release_asset_url() {
		// Unset release asset url if older than 5 min to account for AWS expiration.
		if ( ( time() - strtotime( '-12 hours', $this->response['timeout'] ) ) >= 300 ) {
			unset( $this->response['release_asset_url'] );
		}

		$response = isset( $this->response['release_asset_url'] ) ? $this->response['release_asset_url'] : false;

		if ( $this->exit_no_update( $response ) ) {
			return false;
		}

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/releases/latest' );

			if ( ! $response ) {
				$response          = new \stdClass();
				$response->message = 'No release asset found';
			}

			if ( is_wp_error( $response ) ) {
				Singleton::get_instance( 'Messages', $this )->create_error_message( $response );

				return false;
			}

			if ( $response ) {
				add_filter( 'http_request_args', array( &$this, 'set_github_release_asset_header' ) );

				$url          = $this->add_access_token_endpoint( $this, $response->assets[0]->url );
				$response_new = wp_remote_get( $url );

				remove_filter( 'http_request_args', array( &$this, 'set_github_release_asset_header' ) );

				if ( is_wp_error( $response_new ) ) {
					Singleton::get_instance( 'Messages', $this )->create_error_message( $response_new );

					return false;
				}

				if ( $response_new['http_response'] instanceof \WP_HTTP_Requests_Response ) {
					$response_object = $response_new['http_response']->get_response_object();
					if ( ! $response_object->success ) {
						return false;
					}
					$response_headers = $response_object->history[0]->headers;
					$download_link    = $response_headers->getValues( 'location' );
				} else {
					return false;
				}

				$response = $download_link[0];
				$this->set_repo_cache( 'release_asset_url', $response );
			}
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		return $response;
	}

	/**
	 * Set HTTP header for following GitHub release assets.
	 *
	 * @since 6.1.0
	 *
	 * @param        $args
	 * @param string $url
	 *
	 * @return mixed $args
	 */
	public function set_github_release_asset_header( $args, $url = '' ) {
		$args['headers']['accept'] = 'application/octet-stream';

		return $args;
	}

	/**
	 * Add settings for GitHub Personal Access Token.
	 *
	 * @param array $auth_required
	 *
	 * @return void
	 */
	public function add_settings( $auth_required ) {
		add_settings_section(
			'github_access_token',
			esc_html__( 'GitHub Personal Access Token', 'github-updater' ),
			array( &$this, 'print_section_github_access_token' ),
			'github_updater_github_install_settings'
		);

		add_settings_field(
			'github_access_token',
			esc_html__( 'GitHub.com Access Token', 'github-updater' ),
			array( Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ),
			'github_updater_github_install_settings',
			'github_access_token',
			array( 'id' => 'github_access_token', 'token' => true )
		);

		if ( $auth_required['github_enterprise'] ) {
			add_settings_field(
				'github_enterprise_token',
				esc_html__( 'GitHub Enterprise Access Token', 'github-updater' ),
				array( Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ),
				'github_updater_github_install_settings',
				'github_access_token',
				array( 'id' => 'github_enterprise_token', 'token' => true )
			);
		}

		/*
		 * Show section for private GitHub repositories.
		 */
		if ( $auth_required['github_private'] || $auth_required['github_enterprise'] ) {
			add_settings_section(
				'github_id',
				esc_html__( 'GitHub Private Settings', 'github-updater' ),
				array( &$this, 'print_section_github_info' ),
				'github_updater_github_install_settings'
			);
		}
	}

	/**
	 * Add values for individual repo add_setting_field().
	 *
	 * @return mixed
	 */
	public function add_repo_setting_field() {
		$setting_field['page']            = 'github_updater_github_install_settings';
		$setting_field['section']         = 'github_id';
		$setting_field['callback_method'] = array(
			Singleton::get_instance( 'Settings', $this ),
			'token_callback_text',
		);

		return $setting_field;
	}

	/**
	 * Print the GitHub text.
	 */
	public function print_section_github_info() {
		esc_html_e( 'Enter your GitHub Access Token. Leave empty for public repositories.', 'github-updater' );
	}

	/**
	 * Print the GitHub Personal Access Token text.
	 */
	public function print_section_github_access_token() {
		esc_html_e( 'Enter your personal GitHub.com or GitHub Enterprise Access Token to avoid API access limits.', 'github-updater' );
	}

	/**
	 * Add remote install settings fields.
	 *
	 * @param $type
	 */
	public function add_install_settings_fields( $type ) {
		add_settings_field(
			'github_access_token',
			esc_html__( 'GitHub Access Token', 'github-updater' ),
			array( &$this, 'github_access_token' ),
			'github_updater_install_' . $type,
			$type
		);
	}

	/**
	 * GitHub Access Token for remote install.
	 */
	public function github_access_token() {
		?>
		<label for="github_access_token">
			<input class="github_setting" type="password" style="width:50%;" name="github_access_token" value="">
			<br>
			<span class="description">
				<?php esc_html_e( 'Enter GitHub Access Token for private GitHub repositories.', 'github-updater' ) ?>
			</span>
		</label>
		<?php
	}

	/**
	 * Add remote install feature, create endpoint.
	 *
	 * @param $headers
	 * @param $install
	 *
	 * @return mixed
	 */
	public function remote_install( $headers, $install ) {
		$github_com = true;

		if ( 'github.com' === $headers['host'] || empty( $headers['host'] ) ) {
			$base            = 'https://api.github.com';
			$headers['host'] = 'github.com';
		} else {
			$base       = $headers['base_uri'] . '/api/v3';
			$github_com = false;
		}

		$install['download_link'] = implode( '/', array(
			$base,
			'repos',
			$install['github_updater_repo'],
			'zipball',
			$install['github_updater_branch'],
		) );

		// If asset is entered install it.
		if ( false !== stripos( $headers['uri'], 'releases/download' ) ) {
			$install['download_link'] = $headers['uri'];
		}

		/*
		 * Add/Save access token if present.
		 */
		if ( ! empty( $install['github_access_token'] ) ) {
			$install['options'][ $install['repo'] ] = $install['github_access_token'];
			if ( $github_com ) {
				$install['options']['github_access_token'] = $install['github_access_token'];
			} else {
				$install['options']['github_enterprise_token'] = $install['github_access_token'];
			}
		}
		if ( $github_com ) {
			$token = ! empty( $install['options']['github_access_token'] )
				? $install['options']['github_access_token']
				: static::$options['github_access_token'];
		} else {
			$token = ! empty( $install['options']['github_enterprise_token'] )
				? $install['options']['github_enterprise_token']
				: static::$options['github_enterprise_token'];
		}

		if ( ! empty( $token ) ) {
			$install['download_link'] = add_query_arg( 'access_token', $token, $install['download_link'] );
		}

		if ( ! empty( static::$options['github_access_token'] ) ) {
			unset( $install['options']['github_access_token'] );
		}
		if ( ! empty( static::$options['github_enterprise_token'] ) ) {
			unset( $install['options']['github_enterprise_token'] );
		}

		return $install;
	}

}
