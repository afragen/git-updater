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
 * Class Gist_API
 *
 * Get remote data from a Gist.
 *
 * @author  Andy Fragen
 */
class Gist_API extends API implements API_Interface {
	/**
	 * Constructor.
	 *
	 * @param \stdClass $type plugin|theme.
	 */
	public function __construct( $type ) {
		parent::__construct();
		$this->type     = null === $type ? $type : $this->parse_gist_meta( $type );
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
		return $this->get_remote_api_info( 'gist', $file, "/:owner/:gist_id/raw/{$file}" );
	}

	/**
	 * Get remote info for tags.
	 *
	 * @return bool|void
	 */
	public function get_remote_tag() {
		// phpcs:ignore
		// return $this->get_remote_api_tag( 'gist', '/repos/:owner/:repo/tags' );
	}

	/**
	 * Read the remote CHANGES.md file.
	 *
	 * @param string $changes Changelog filename.
	 *
	 * @return bool
	 */
	public function get_remote_changes( $changes ) {
		return $this->get_remote_api_changes( 'gist', $changes, "/:owner/:gist_id/raw/{$changes}" );
	}

	/**
	 * Read and parse remote readme.txt.
	 *
	 * @return bool|void
	 */
	public function get_remote_readme() {
		$this->get_remote_api_readme( 'gist', '/:owner/:gist_id/raw/readme.txt' );
	}

	/**
	 * Read the repository meta from API.
	 *
	 * @return bool
	 */
	public function get_repo_meta() {
		return $this->get_remote_api_repo_meta( 'gist', '/gists/:gist_id' );
	}

	/**
	 * Create array of branches and download links as array.
	 *
	 * @return bool|void
	 */
	public function get_remote_branches() {
		// phpcs:ignore
		// return $this->get_remote_api_branches( 'gist', '/repos/:owner/:repo/branches' );
	}

	/**
	 * Return the GitHub release asset URL.
	 *
	 * @return string|bool|void
	 */
	public function get_release_asset() {
		// phpcs:ignore
		// return $this->get_api_release_asset( 'gist', '/repos/:owner/:repo/releases/latest' );
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
		$download_link_base = $this->get_api_url( '/:owner/:gist_id/archive/', true );
		$endpoint           = "{$this->response['meta']['current_hash']}.zip";
		$download_link      = $download_link_base . $endpoint;

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
			case 'changes':
				$endpoint = $endpoint['base_raw'];
				break;
			case 'meta':
			case 'translation':
				$endpoint = $endpoint['base_uri'];
				break;
			case 'download_link':
				$endpoint = $endpoint['base_download'];
				break;
			case 'branches':
			case 'tags':
			case 'release_asset':
				break;
			default:
				break;
		}

		return $endpoint;
	}

	/**
	 * Parse gist data.
	 *
	 * @param \stdClass $repo Repository object.
	 *
	 * @return \stdClass
	 */
	public function parse_gist_meta( $repo ) {
		$repo->gist_id = property_exists( $repo, 'gist_id' ) ? $repo->gist_id : $repo->slug;
		$repo->slug    = property_exists( $repo, 'file' ) ? dirname( $repo->file ) : $repo->slug;

		return $repo;
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
				$arr['private']      = ! $e->public;
				$arr['last_updated'] = $e->updated_at;
				$arr['watchers']     = $e->comments;
				$arr['forks']        = count( $e->forks );
				$arr['open_issues']  = 0;
				$arr['current_hash'] = isset( $e->history[0]->version ) ? $e->history[0]->version : null;
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
		// foreach ( $response as $branch ) {
		// $branches[ $branch->name ]['download']    = $this->construct_download_link( $branch->name );
		// $branches[ $branch->name ]['commit_hash'] = $branch->commit->sha;
		// $branches[ $branch->name ]['commit_api']  = $branch->commit->url;
		// }

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
		return [];
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
			'gist_settings',
			esc_html__( 'GitHub Gist Settings', 'github-updater' ),
			null,
			'github_updater_gist_install_settings'
		);

		/*
		 * Show section for private GitHub Gists.
		 */
		if ( $auth_required['gist_private'] ) {
			add_settings_section(
				'gist_id',
				esc_html__( 'Gist Private Settings', 'github-updater' ),
				[ $this, 'print_section_gist_info' ],
				'github_updater_gist_install_settings'
			);
		}
	}

	/**
	 * Add values for individual repo add_setting_field().
	 *
	 * @return mixed
	 */
	public function add_repo_setting_field() {
		$setting_field['page']            = 'github_updater_gist_install_settings';
		$setting_field['section']         = 'gist_id';
		$setting_field['callback_method'] = [
			Singleton::get_instance( 'Settings', $this ),
			'token_callback_text',
		];

		return $setting_field;
	}

	/**
	 * Print the GitHub text.
	 */
	public function print_section_gist_info() {
		esc_html_e( 'Enter your GitHub Access Token. Leave empty for public repositories.', 'github-updater' );
	}

	/**
	 * Add remote install settings fields.
	 *
	 * @param string $type plugin|theme.
	 */
	public function add_install_settings_fields( $type ) {
	}

	/**
	 * Add subtab to Settings page.
	 */
	private function add_settings_subtab() {
		add_filter(
			'github_updater_add_settings_subtabs',
			function ( $subtabs ) {
				return array_merge( $subtabs, [ 'gist' => esc_html__( 'Gist', 'github-updater' ) ] );
			}
		);
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
		$remote                                 = $this->get_remote_gist_install( $headers );
		self::$method                           = 'download_link';
		$download_link_base                     = $this->get_api_url( '/:owner/:gist_id/archive/', true );
		$endpoint                               = "{$remote->meta['current_hash']}.zip";
		$install['download_link']               = $download_link_base . $endpoint;
		$install['github_updater_install_repo'] = property_exists( $remote, 'slug' ) ? $remote->slug : $install['github_updater_install_repo'];

		return $install;
	}

	/**
	 * Get and parse gist remote meta for Install.
	 *
	 * @param array $headers Array of headers.
	 *
	 * @return array $remote
	 */
	private function get_remote_gist_install( $headers ) {
		$remote              = new \stdClass();
		self::$method        = 'meta';
		$this->type          = new \stdClass();
		$this->type->type    = 'gist';
		$this->type->git     = 'gist';
		$this->type->owner   = $headers['owner'];
		$this->type->slug    = $headers['repo'];
		$this->type->gist_id = $headers['repo'];

		$response         = $this->api( '/gists/:gist_id' );
		$remote->meta     = $this->parse_meta_response( $response );
		$remote->is_theme = property_exists( $response->files, 'style.css' );
		$type             = $remote->is_theme ? 'theme' : 'plugin';
		foreach ( $response->files as $file ) {
			$file_headers = $this->get_file_headers( $file->content, $type );
			if ( ! empty( $file_headers ) && ! $remote->is_theme ) {
				$remote->slug = pathinfo( $file->filename )['filename'];
			}
		}

		return $remote;
	}
}
