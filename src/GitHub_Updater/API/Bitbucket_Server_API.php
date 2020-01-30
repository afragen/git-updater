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

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Bitbucket_Server_API
 *
 * Get remote data from a self-hosted Bitbucket Server repo.
 * Assumes an owner == project_key
 * Group URI: https://bitbucket.example.com/projects/<owner>/<repo>
 * User URI: https://bitbucket.example.com/users/<owner>/<repo>
 *
 * @link https://docs.atlassian.com/bitbucket-server/rest/5.3.1/bitbucket-rest.html
 *
 * @author  Andy Fragen
 * @author  Bjorn Wijers
 */
class Bitbucket_Server_API extends Bitbucket_API {
	/**
	 * Constructor.
	 *
	 * @param \stdClass $type plugin|theme.
	 */
	public function __construct( $type ) {
		parent::__construct( $type );
		$this->add_settings_subtab();
	}

	/**
	 * Read the remote file and parse headers.
	 *
	 * @param string $file Filename.
	 *
	 * @return bool
	 */
	public function get_remote_info( $file ) {
		return $this->get_remote_api_info( 'bbserver', $file, "/1.0/:owner/repos/:repo/browse/{$file}" );
	}

	/**
	 * Read the repository meta from API
	 *
	 * @return bool
	 */
	public function get_repo_meta() {
		return $this->get_remote_api_repo_meta( 'bbserver', '/1.0/:owner/repos/:repo' );
	}

	/**
	 * Get the remote info for tags.
	 *
	 * @access public
	 *
	 * @return bool
	 */
	public function get_remote_tag() {
		return $this->get_remote_api_tag( 'bbserver', '/1.0/:owner/repos/:repo/tags' );
	}

	/**
	 * Read and parse remote readme.txt.
	 *
	 * @return bool
	 */
	public function get_remote_readme() {
		return $this->get_remote_api_readme( 'bbserver', '/1.0/:owner/repos/:repo/raw/readme.txt' );
	}

	/**
	 * Read the remote CHANGES.md file
	 *
	 * @param string $changes Changelog filename.
	 *
	 * @return bool
	 */
	public function get_remote_changes( $changes ) {
		return $this->get_remote_api_changes( 'bbserver', $changes, "/1.0/:owner/repos/:repo/raw/{$changes}" );
	}

	/**
	 * Create array of branches and download links as array.
	 *
	 * @return bool
	 */
	public function get_remote_branches() {
		return $this->get_remote_api_branches( 'bbserver', '/1.0/:owner/repos/:repo/branches' );
	}

	/**
	 * Return the Bitbucket Sever release asset URL.
	 *
	 * @return void|string
	 */
	public function get_release_asset() {
		// TODO: make this work.
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
		// return $this->get_api_release_asset( 'bbserver', '/1.0/:owner/:repo/downloads' );
	}

	/**
	 * Construct $this->type->download_link using Bitbucket Server API.
	 *
	 * Downloads requires the official stash-archive plugin which enables
	 * subdirectory support using the prefix query argument.
	 *
	 * @link https://bitbucket.org/atlassian/stash-archive
	 *
	 * @param boolean $branch_switch For direct branch changing.
	 *
	 * @return string $endpoint
	 */
	public function construct_download_link( $branch_switch = false ) {
		self::$method       = 'download_link';
		$download_link_base = $this->get_api_url( '/latest/:owner/repos/:repo/archive', true );
		$endpoint           = $this->add_endpoints( $this, '' );

		if ( $branch_switch ) {
			$endpoint = urldecode( add_query_arg( 'at', $branch_switch, $endpoint ) );
		}

		return $download_link_base . $endpoint;
	}

	/**
	 * Create Bitbucket Server API endpoints.
	 *
	 * @param Bitbucket_Server_API|API $git      Git host specific API object.
	 * @param string                   $endpoint Endpoint.
	 *
	 * @return string $endpoint
	 */
	public function add_endpoints( $git, $endpoint ) {
		switch ( self::$method ) {
			case 'meta':
			case 'translation':
			case 'branches':
				break;
			case 'file':
			case 'readme':
				$endpoint = add_query_arg( 'at', $git->type->branch, $endpoint );
				break;
			case 'changes':
				$endpoint = add_query_arg(
					[
						'at'  => $git->type->branch,
						'raw' => '',
					],
					$endpoint
				);
				break;
			case 'tags':
			case 'download_link':
				/*
				 * Add a prefix query argument to create a subdirectory with the same name
				 * as the repo, e.g. 'my-repo' becomes 'my-repo/'
				 * Required for using stash-archive.
				 */
				$defaults = [
					'prefix' => $git->type->slug . '/',
					'at'     => $git->type->branch,
					'format' => 'zip',
				];
				$endpoint = add_query_arg( $defaults, $endpoint );
				if ( ! empty( $git->type->tags ) ) {
					$endpoint = urldecode( add_query_arg( 'at', $git->type->newest_tag, $endpoint ) );
				}
				break;
			default:
				break;
		}

		return $endpoint;
	}

	/**
	 * Combines separate text lines from API response into one string with \n line endings.
	 * Code relying on raw text can now parse it.
	 *
	 * @param string|\stdClass|mixed $response API response data.
	 *
	 * @return string Combined lines of text returned by API
	 */
	protected function bbserver_recombine_response( $response ) {
		if ( $this->validate_response( $response ) ) {
			return $response;
		}
		$remote_info_file = '';
		if ( isset( $response->lines ) ) {
			foreach ( (array) $response->lines as $line ) {
				$remote_info_file .= $line->text . "\n";
			}
		}

		return $remote_info_file;
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
				$arr['last_updated'] = null;
				$arr['watchers']     = 0;
				$arr['forks']        = 0;
				$arr['open_issues']  = 0;
			}
		);

		return $arr;
	}

	/**
	 * Parse API response and return array with changelog.
	 *
	 * @param string $response Response from API call.
	 *
	 * @return void
	 */
	public function parse_changelog_response( $response ) {
	}

	/**
	 * Parse API response and return object with readme body.
	 *
	 * @param string|\stdClass $response API response data.
	 *
	 * @return void
	 */
	protected function parse_readme_response( $response ) {
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
			$branches[ $branch->displayId ]['download']    = $this->construct_download_link( $branch->displayId );
			$branches[ $branch->displayId ]['commit_hash'] = $branch->latestCommit;
		}

		return $branches;
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
				$arr[] = $e->displayId;

				return $arr;
			},
			(array) $response->values
		);

		return $arr;
	}

	/**
	 * Parse tags and create download links.
	 *
	 * @param \stdClass|array $response  Response from API call.
	 * @param string          $repo_type plugin|theme.
	 *
	 * @return array
	 */
	protected function parse_tags( $response, $repo_type ) {
		$tags     = [];
		$rollback = [];

		foreach ( (array) $response as $tag ) {
			$download_base    = "{$repo_type['base_uri']}/latest/{$this->type->owner}/repos/{$this->type->slug}/archive";
			$download_base    = $this->add_endpoints( $this, $download_base );
			$tags[]           = $tag;
			$rollback[ $tag ] = add_query_arg( 'at', $tag, $download_base );
		}

		return [ $tags, $rollback ];
	}

	/**
	 * Add settings for Bitbucket Server Username and Password.
	 *
	 * @param array $auth_required Array of authentication data.
	 *
	 * @return void
	 */
	public function add_settings( $auth_required ) {
		add_settings_section(
			'bitbucket_server_user',
			esc_html__( 'Bitbucket Server Private Settings', 'github-updater' ),
			[ $this, 'print_section_bitbucket_username' ],
			'github_updater_bbserver_install_settings'
		);

		add_settings_field(
			'bitbucket_server_username',
			esc_html__( 'Bitbucket Server Username', 'github-updater' ),
			[ Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ],
			'github_updater_bbserver_install_settings',
			'bitbucket_server_user',
			[ 'id' => 'bitbucket_server_username' ]
		);

		add_settings_field(
			'bitbucket_server_password',
			esc_html__( 'Bitbucket Server Password', 'github-updater' ),
			[ Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ],
			'github_updater_bbserver_install_settings',
			'bitbucket_server_user',
			[
				'id'    => 'bitbucket_server_password',
				'token' => true,
			]
		);

		/*
		 * Show section for private Bitbucket Server repositories.
		 */
		if ( $auth_required['bitbucket_server'] ) {
			add_settings_section(
				'bitbucket_server_id',
				esc_html__( 'Bitbucket Server Private Repositories', 'github-updater' ),
				[ $this, 'print_section_bitbucket_info' ],
				'github_updater_bbserver_install_settings'
			);
		}
	}

	/**
	 * Add values for individual repo add_setting_field().
	 *
	 * @return mixed
	 */
	public function add_repo_setting_field() {
		$setting_field['page']            = 'github_updater_bbserver_install_settings';
		$setting_field['section']         = 'bitbucket_server_id';
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
				return array_merge( $subtabs, [ 'bbserver' => esc_html__( 'Bitbucket Server', 'github-updater' ) ] );
			}
		);
	}

	/**
	 * Add remote install feature, create endpoint.
	 *
	 * @param array $headers Array of headers.
	 * @param array $install Array of install data.
	 *
	 * @return array $install
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

		if ( ! $bitbucket_org ) {
			$install['download_link'] = "{$base}/rest/api/latest/{$headers['owner']}/repos/{$headers['repo']}/archive";

			$install['download_link'] = add_query_arg(
				[
					'prefix' => $headers['repo'] . '/',
					'at'     => $install['github_updater_branch'],
					'format' => 'zip',
				],
				$install['download_link']
			);

			if ( isset( $install['is_private'] ) ) {
				$install['options'][ $install['repo'] ] = 1;
			}
			if ( ! empty( $install['bitbucket_username'] ) ) {
				$install['options']['bitbucket_server_username'] = $install['bitbucket_username'];
			}
			if ( ! empty( $install['bitbucket_password'] ) ) {
				$install['options']['bitbucket_server_password'] = $install['bitbucket_password'];
			}
		}

		return $install;
	}
}
