<?php
/**
 * Git Updater
 *
 * Only active with license.
 *
 * @author   Andy Fragen
 * @license  MIT
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater\API;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Zipfile_API
 *
 * Remote install from a Zipfile.
 *
 * @author Andy Fragen
 */
class Zipfile_API {

	/**
	 * Load hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_filter( 'gu_git_servers', [ $this, 'set_git_servers' ], 10, 1 );
		add_filter( 'gu_installed_apis', [ $this, 'set_installed_apis' ], 10, 1 );
		add_filter( 'gu_install_remote_install', [ $this, 'set_remote_install_data' ], 10, 2 );
	}

	/**
	 * Add API as git server.
	 *
	 * @param array $git_servers Array of git servers.
	 *
	 * @return array
	 */
	public function set_git_servers( $git_servers ) {
		return array_merge( $git_servers, [ 'zipfile' => 'Zipfile' ] );
	}

	/**
	 * Add API data to $installed_apis.
	 *
	 * @param array $installed_apis Array of installed APIs.
	 *
	 * @return array
	 */
	public function set_installed_apis( $installed_apis ) {
		return array_merge( $installed_apis, [ 'zipfile_api' => true ] );
	}

	/**
	 * Set remote installation data for specific API.
	 *
	 * @param array $install Array of remote installation data.
	 * @param array $headers Array of repository header data.
	 *
	 * @return array
	 */
	public function set_remote_install_data( $install, $headers ) {
		if ( 'zipfile' === $install['git_updater_api'] ) {
			$install = ( new Zipfile_API() )->remote_install( $headers, $install );
		}

		return $install;
	}

	/**
	 * Add remote install settings fields.
	 *
	 * @param string $type plugin|theme.
	 */
	public function add_install_settings_fields( $type ) {
		add_settings_field(
			'zipfile_slug',
			esc_html__( 'Zipfile Slug', 'gi-updater' ),
			[ $this, 'zipfile_slug' ],
			'git_updater_install_' . $type,
			$type
		);
	}

	/**
	 * Set repo slug for remote install.
	 */
	public function zipfile_slug() {
		?>
		<label for="zipfile_slug">
			<input class="zipfile_setting" type="text" style="width:50%;" id="zipfile_slug" name="zipfile_slug" value="" placeholder="my-repo-slug">
			<br>
			<span class="description">
				<?php esc_html_e( 'Enter plugin or theme slug.', 'gi-updater' ); ?>
			</span>
		</label>
		<?php
	}

	/**
	 *  Add remote install feature, create endpoint.
	 *
	 * @param array $headers Array of headers.
	 * @param array $install Array of install data.
	 *
	 * @return mixed $install
	 */
	public function remote_install( $headers, $install ) {
		$install['download_link']            = ! empty( $headers['uri'] ) ? $headers['uri'] : $headers['original'];
		$install['git_updater_install_repo'] = $install['zipfile_slug'];

		return $install;
	}
}
