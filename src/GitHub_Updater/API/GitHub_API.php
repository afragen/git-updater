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
 * Class GitHub_API
 *
 * Get remote data from a GitHub repo.
 *
 * @author  Andy Fragen
 */
class GitHub_API extends API implements API_Interface {

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
		$this->settings_hook( $this );
		$this->add_settings_subtab();
		$this->add_install_fields( $this );
	}

	/**
	 * Read the remote file and parse headers.
	 *
	 * @param string $file Filename.
	 *
	 * @return bool
	 */
	public function get_remote_info( $file ) {
		return $this->get_remote_api_info( 'github', $file, "/repos/:owner/:repo/contents/{$file}" );
	}

	/**
	 * Get remote info for tags.
	 *
	 * @return bool
	 */
	public function get_remote_tag() {
		return $this->get_remote_api_tag( 'github', '/repos/:owner/:repo/tags' );
	}

	/**
	 * Read the remote CHANGES.md file.
	 *
	 * @param string $changes Changelog filename.
	 *
	 * @return bool
	 */
	public function get_remote_changes( $changes ) {
		return $this->get_remote_api_changes( 'github', $changes, "/repos/:owner/:repo/contents/{$changes}" );
	}

	/**
	 * Read and parse remote readme.txt.
	 *
	 * @return bool
	 */
	public function get_remote_readme() {
		$this->get_remote_api_readme( 'github', '/repos/:owner/:repo/contents/readme.txt' );
	}

	/**
	 * Read the repository meta from API.
	 *
	 * @return bool
	 */
	public function get_repo_meta() {
		return $this->get_remote_api_repo_meta( 'github', '/repos/:owner/:repo' );
	}

	/**
	 * Create array of branches and download links as array.
	 *
	 * @return bool
	 */
	public function get_remote_branches() {
		return $this->get_remote_api_branches( 'github', '/repos/:owner/:repo/branches' );
	}

	/**
	 * Construct $this->type->download_link using Repository Contents API.
	 *
	 * @url http://developer.github.com/v3/repos/contents/#get-archive-link
	 *
	 * @param boolean $branch_switch for direct branch changing.
	 *
	 * @return string $endpoint
	 */
	public function construct_download_link( $branch_switch = false ) {
		$download_link_base = $this->get_api_url( '/repos/:owner/:repo/zipball/', true );
		$endpoint           = '';

		/*
		 * If release asset.
		 */
		if ( $this->type->release_asset && '0.0.0' !== $this->type->newest_tag ) {
			$release_asset = $this->get_github_release_asset_url();
			return $this->get_aws_release_asset_url( $release_asset );
		}

		/*
		 * If a branch has been given, use branch.
		 * If branch is master (default) and tags are used, use newest tag.
		 */
		if ( 'master' !== $this->type->branch || empty( $this->type->tags ) ) {
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
			$reset                       = (int) $response['headers']['x-ratelimit-reset'];
			$wait                        = date( 'i', $reset - time() );
			static::$error_code[ $repo ] = array_merge(
				static::$error_code[ $repo ],
				[
					'git'  => 'github',
					'wait' => $wait,
				]
			);
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
				$arr['private']      = $e->private;
				$arr['last_updated'] = $e->pushed_at;
				$arr['watchers']     = $e->watchers;
				$arr['forks']        = $e->forks;
				$arr['open_issues']  = $e->open_issues;
			}
		);

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
	 * Parse tags and create download links.
	 *
	 * @param $response
	 * @param $repo_type
	 *
	 * @return array
	 */
	protected function parse_tags( $response, $repo_type ) {
		$tags     = [];
		$rollback = [];

		foreach ( (array) $response as $tag ) {
			$download_base    = implode(
				'/',
				[
					$repo_type['base_uri'],
					'repos',
					$this->type->owner,
					$this->type->slug,
					'zipball/',
				]
			);
			$tags[]           = $tag;
			$rollback[ $tag ] = $download_base . $tag;
		}

		return [ $tags, $rollback ];
	}

	/**
	 * Return the GitHub release asset URL.
	 *
	 * @return string|bool|\stdClass
	 */
	private function get_github_release_asset_url() {
		$response = isset( $this->response['release_asset'] ) ? $this->response['release_asset'] : false;

		if ( $response && $this->exit_no_update( $response ) ) {
			return false;
		}

		if ( ! $response ) {
			$response = $this->api( '/repos/:owner/:repo/releases/latest' );
			$response = isset( $response->assets ) ? $response->assets[0]->url : $response;
		}

		if ( ! $response && ! is_wp_error( $response ) ) {
			$response          = new \stdClass();
			$response->message = 'No release asset found';
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		if ( $response && ! isset( $this->response['release_asset'] ) ) {
			$this->set_repo_cache( 'release_asset', $response );
		}

		return $response;
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
			[ $this, 'print_section_github_access_token' ],
			'github_updater_github_install_settings'
		);

		add_settings_field(
			'github_access_token',
			esc_html__( 'GitHub.com Access Token', 'github-updater' ),
			[ Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ],
			'github_updater_github_install_settings',
			'github_access_token',
			[
				'id'    => 'github_access_token',
				'token' => true,
			]
		);

		if ( $auth_required['github_enterprise'] ) {
			add_settings_field(
				'github_enterprise_token',
				esc_html__( 'GitHub Enterprise Access Token', 'github-updater' ),
				[ Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ],
				'github_updater_github_install_settings',
				'github_access_token',
				[
					'id'    => 'github_enterprise_token',
					'token' => true,
				]
			);
		}

		/*
		 * Show section for private GitHub repositories.
		 */
		if ( $auth_required['github_private'] || $auth_required['github_enterprise'] ) {
			add_settings_section(
				'github_id',
				esc_html__( 'GitHub Private Settings', 'github-updater' ),
				[ $this, 'print_section_github_info' ],
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
		$setting_field['callback_method'] = [
			Singleton::get_instance( 'Settings', $this ),
			'token_callback_text',
		];

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
			[ $this, 'github_access_token' ],
			'github_updater_install_' . $type,
			$type
		);
	}

	/**
	 * Add subtab to Settings page.
	 */
	private function add_settings_subtab() {
		add_filter(
			'github_updater_add_settings_subtabs',
			function ( $subtabs ) {
				return array_merge( $subtabs, [ 'github' => esc_html__( 'GitHub', 'github-updater' ) ] );
			}
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
				<?php esc_html_e( 'Enter GitHub Access Token for private GitHub repositories.', 'github-updater' ); ?>
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

		$install['download_link'] = implode(
			'/',
			[
				$base,
				'repos',
				$install['github_updater_repo'],
				'zipball',
				$install['github_updater_branch'],
			]
		);

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
