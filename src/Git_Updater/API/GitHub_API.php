<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  GPL-3.0-or-later
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 *
 * @phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
 */

namespace Fragen\Git_Updater\API;

use Fragen\Singleton;
use stdClass;

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
	 * @param stdClass $type plugin|theme.
	 */
	public function __construct( $type = null ) {
		parent::__construct();
		$this->type     = $type;
		$this->response = [];
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
		return $this->get_remote_api_info( 'github', "/repos/:owner/:repo/contents/{$file}" );
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
	 * @param string $changes The changelog filename - deprecated.
	 *
	 * @return bool
	 */
	public function get_remote_changes( $changes ) {
		return $this->get_remote_api_changes( 'github', $changes, '/repos/:owner/:repo/contents/:changelog' );
	}

	/**
	 * Read and parse remote readme.txt.
	 *
	 * @return bool|void
	 */
	public function get_remote_readme() {
		$this->get_remote_api_readme( 'github', '/repos/:owner/:repo/contents/:readme' );
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
	 * Return the latest GitHub release asset URL.
	 *
	 * @return string|bool|void
	 */
	public function get_release_asset() {
		// return $this->get_api_release_asset( 'github', '/repos/:owner/:repo/releases/latest' );
	}

	/**
	 * Return array of release assets.
	 *
	 * @return array
	 */
	public function get_release_assets() {
		return $this->get_api_release_assets( 'github', '/repos/:owner/:repo/releases' );
	}

	/**
	 * Return list of repository assets.
	 *
	 * @return array
	 */
	public function get_repo_assets() {
		return $this->get_remote_api_assets( 'github', '/repos/:owner/:repo/contents/:path' );
	}

	/**
	 * Return list of files at repo root.
	 *
	 * @return array
	 */
	public function get_repo_contents() {
		return $this->get_remote_api_contents( 'github', '/repos/:owner/:repo/contents' );
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
		self::$method       = 'download_link';
		$download_link_base = $this->get_api_url( '/repos/:owner/:repo/zipball/', true );
		$endpoint           = '';

		// Release asset.
		if ( $this->use_release_asset( $branch_switch ) ) {
			$release_assets = $this->get_release_assets();
			if ( ! $release_assets ) {
				return '';
			}
			$release_assets['assets'] = $release_assets['assets'] ?? [];
			$release_asset            = reset( $release_assets['assets'] );

			/*
			 * Check if dev release asset is newer than latest release asset.
			 *
			 * @param bool
			 * @param $this->type Repo type object.
			 */
			if ( apply_filters( 'gu_dev_release_asset', false, $this->type ) ) {
				$current_asset_version     = array_key_first( $release_assets['assets'] ) ?? '';
				$current_dev_asset_version = array_key_first( $release_assets['dev_assets'] ) ?? '';
				if ( version_compare( $current_asset_version, $current_dev_asset_version, '<' ) ) {
					$release_asset = reset( $release_assets['dev_assets'] );
				}
			}

			if ( empty( $this->response['release_asset_download'] ) ) {
				$this->set_repo_cache( 'release_asset_download', $release_asset );
			}
			if ( ! empty( $this->response['release_asset_download'] ) ) {
				return $this->response['release_asset_download'];
			}

			return $this->get_release_asset_redirect( $release_asset, true );
		}

		/*
		 * If a branch has been given, use branch.
		 * If branch is primary branch (default) and tags are used, use newest tag.
		 */
		if ( $this->type->primary_branch !== $this->type->branch || empty( $this->type->tags ) ) {
			$endpoint .= $this->type->branch;
		} else {
			$endpoint .= $this->type->newest_tag;
		}

		// Create endpoint for branch switching.
		if ( $branch_switch ) {
			$endpoint = $branch_switch;
		}

		$download_link = $download_link_base . $endpoint;
		$download_link = apply_filters( 'gu_post_construct_download_link', $download_link, $this->type, $branch_switch );

		return $download_link;
	}

	/**
	 * Create GitHub API endpoints.
	 *
	 * @param GitHub_API|API $git Git host specific API object.
	 * @param string         $endpoint Endpoint.
	 *
	 * @return string $endpoint
	 */
	public function add_endpoints( $git, $endpoint ) {
		switch ( $git::$method ) {
			case 'file':
			case 'readme':
			case 'assets':
			case 'changes':
				$endpoint = add_query_arg( 'ref', $git->type->branch, $endpoint );
				break;
			case 'meta':
			case 'tags':
			case 'download_link':
			case 'release_asset':
			case 'translation':
				break;
			case 'branches':
				$endpoint = add_query_arg( 'per_page', '100', $endpoint );
				break;
			default:
				break;
		}

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
	 * @param array  $response HTTP headers.
	 * @param string $repo     Repo name.
	 *
	 * @return int
	 */
	public static function ratelimit_reset( $response, $repo ) {
		$headers = wp_remote_retrieve_headers( $response );
		if ( empty( $headers ) ) {
			return 60;
		}
		$data = $headers->getAll();
		if ( isset( $data['x-ratelimit-reset'] ) ) {
			$reset = (int) $data['x-ratelimit-reset'];
			//phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			$wait                        = date( 'i', $reset - time() );
			static::$error_code[ $repo ] = static::$error_code[ $repo ] ?? [];
			static::$error_code[ $repo ] = array_merge( static::$error_code[ $repo ], [ 'wait' => $wait ] );

			return $wait;
		}
	}

	/**
	 * Parse API response call and return only array of tag numbers.
	 *
	 * @param stdClass|array $response Response from API call.
	 *
	 * @return stdClass|array $arr Array of tag numbers, object is error.
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
	 * @param stdClass|array $response Response from API call.
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
				$arr['private']      = $e->private ?? false;
				$arr['last_updated'] = $e->pushed_at ?? '';
				$arr['added']        = $e->created_at ?? '';
				$arr['watchers']     = $e->watchers ?? 0;
				$arr['forks']        = $e->forks ?? 0;
				$arr['open_issues']  = $e->open_issues ?? 0;
			}
		);

		return $arr;
	}

	/**
	 * Parse API response and return array with changelog in base64.
	 *
	 * @param stdClass|array $response Response from API call.
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
	 * Parse API response and return array of branch data.
	 *
	 * @param stdClass $response API response.
	 *
	 * @return array Array of branch data.
	 */
	public function parse_branch_response( $response ) {
		if ( $this->validate_response( $response ) ) {
			return $response;
		}
		$branches = [];
		foreach ( $response as $branch ) {
			$branches[ $branch->name ]['download']    = $this->construct_download_link( $branch->name );
			$branches[ $branch->name ]['commit_hash'] = $branch->commit->sha;
			$branches[ $branch->name ]['commit_api']  = $branch->commit->url;
		}

		return $branches;
	}

	/**
	 * Parse release asset API response.
	 *
	 * @param stdClass $response API response.
	 *
	 * @return void
	 */
	public function parse_release_asset_response( $response ) {
		if ( $this->validate_response( $response ) ) {
			return;
		}
		if ( property_exists( $response, 'url' ) ) {
			$this->set_repo_cache( 'release_asset_download', $response->url );
		}
	}

	/**
	 * Parse tags and create download links.
	 *
	 * @param stdClass|array $response  Response from API call.
	 * @param array          $repo_type Array of repo data.
	 *
	 * @return array
	 */
	protected function parse_tags( $response, $repo_type ) {
		$tags = [];

		foreach ( (array) $response as $tag ) {
			$download_base = implode(
				'/',
				[
					$repo_type['base_uri'],
					'repos',
					$this->type->owner,
					$this->type->slug,
					'zipball/',
				]
			);

			// Ignore leading 'v' and skip anything with dash or words.
			if ( ! preg_match( '/[^v]+[-a-z]+/', $tag ) ) {
				$tags[ $tag ] = $download_base . $tag;
			}
		}
		uksort( $tags, fn ( $a, $b ) => version_compare( ltrim( $b, 'v' ), ltrim( $a, 'v' ) ) );

		return $tags;
	}

	/**
	 * Parse remote root files/dirs.
	 *
	 * @param stdClass|array $response Response from API call.
	 *
	 * @return array
	 */
	protected function parse_contents_response( $response ) {
		$files = [];
		$dirs  = [];
		foreach ( $response as $content ) {
			$content = (object) $content;
			if ( property_exists( $content, 'type' ) && 'file' === $content->type ) {
				$files[] = $content->name;
			}
			if ( property_exists( $content, 'type' ) && 'dir' === $content->type ) {
				$dirs[] = $content->name;
			}
		}

		return [
			'files' => $files,
			'dirs'  => $dirs,
		];
	}

	/**
	 * Parse remote assets directory.
	 *
	 * @param stdClass|array $response Response from API call.
	 *
	 * @return stdClass|array
	 */
	protected function parse_asset_dir_response( $response ) {
		$assets = [];

		if ( isset( $response->message ) || is_wp_error( $response ) ) {
			return $response;
		}

		foreach ( $response as $asset ) {
			if ( 'file' === $asset->type ) {
				$assets[ $asset->name ] = $asset->download_url;
			}
		}

		if ( empty( $assets ) ) {
			$assets['message'] = 'No assets found';
			$assets            = (object) $assets;
		}

		return $assets;
	}

	/**
	 * Add settings for GitHub Personal Access Token.
	 *
	 * @param array $auth_required Array of authentication data.
	 *
	 * @return void
	 */
	public function add_settings( $auth_required ) {
		add_settings_section(
			'github_access_token',
			esc_html__( 'GitHub Personal Access Token', 'git-updater' ),
			[ $this, 'print_section_github_access_token' ],
			'git_updater_github_install_settings'
		);

		add_settings_field(
			'github_access_token',
			esc_html__( 'GitHub.com Access Token', 'git-updater' ),
			[ Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ],
			'git_updater_github_install_settings',
			'github_access_token',
			[
				'id'    => 'github_access_token',
				'token' => true,
			]
		);

		/*
		 * Show section for private GitHub repositories.
		 */
		if ( $auth_required['github_private'] || $auth_required['github_enterprise'] ) {
			add_settings_section(
				'github_id',
				esc_html__( 'GitHub Private Settings', 'git-updater' ),
				[ $this, 'print_section_github_info' ],
				'git_updater_github_install_settings'
			);
		}
	}

	/**
	 * Add values for individual repo add_setting_field().
	 *
	 * @return mixed
	 */
	public function add_repo_setting_field() {
		$setting_field['page']            = 'git_updater_github_install_settings';
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
		esc_html_e( 'Enter your GitHub Access Token. Leave empty for public repositories.', 'git-updater' );
	}

	/**
	 * Print the GitHub Personal Access Token text.
	 */
	public function print_section_github_access_token() {
		esc_html_e( 'Enter your personal GitHub.com or GitHub Enterprise Access Token to avoid API access limits.', 'git-updater' );
		$icon = plugin_dir_url( dirname( __DIR__, 2 ) ) . 'assets/github-logo.svg';
		printf( '<img class="git-oauth-icon" src="%s" alt="GitHub logo" />', esc_attr( $icon ) );
	}

	/**
	 * Add remote install settings fields.
	 *
	 * @param string $type plugin|theme.
	 */
	public function add_install_settings_fields( $type ) {
		add_settings_field(
			'github_access_token',
			esc_html__( 'GitHub Access Token', 'git-updater' ),
			[ $this, 'github_access_token' ],
			'git_updater_install_' . $type,
			$type
		);
	}

	/**
	 * Add subtab to Settings page.
	 */
	private function add_settings_subtab() {
		add_filter(
			'gu_add_settings_subtabs',
			function ( $subtabs ) {
				return array_merge( $subtabs, [ 'github' => esc_html__( 'GitHub', 'git-updater' ) ] );
			}
		);
	}

	/**
	 * GitHub Access Token for remote install.
	 */
	public function github_access_token() {
		?>
		<label for="github_access_token">
			<input class="github_setting" type="password" style="width:50%;" id="github_access_token" name="github_access_token" value="" autocomplete="new-password">
			<br>
			<span class="description">
			<?php esc_html_e( 'Enter GitHub Access Token for private GitHub repositories.', 'git-updater' ); ?>
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
	 * @return mixed
	 */
	public function remote_install( $headers, $install ) {
		$options['github_access_token'] = static::$options['github_access_token'] ?? null;

		if ( 'github.com' === $headers['host'] || empty( $headers['host'] ) ) {
			$base            = 'https://api.github.com';
			$headers['host'] = 'github.com';
		} else {
			$base = $headers['base_uri'] . '/api/v3';
		}

		$install['download_link'] = "{$base}/repos/{$install['git_updater_repo']}/zipball/{$install['git_updater_branch']}";

		// If asset is entered install it.
		if ( false !== stripos( $headers['uri'], 'releases/download' ) ) {
			$install['download_link'] = $headers['uri'];
		}

		/*
		 * Add/Save access token if present.
		 */
		if ( ! empty( $install['github_access_token'] ) ) {
			$install['options'][ $install['repo'] ] = $install['github_access_token'];
		}

		return $install;
	}
}
