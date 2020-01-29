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
	 * Add remote install settings fields.
	 *
	 * @param string $type plugin|theme.
	 */
	public function add_install_settings_fields( $type ) {
		add_settings_field(
			'zipfile_slug',
			esc_html__( 'Zipfile Slug', 'github-updater' ),
			[ $this, 'zipfile_slug' ],
			'github_updater_install_' . $type,
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
				<?php esc_html_e( 'Enter plugin or theme slug.', 'github-updater' ); ?>
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
		$install['download_link']               = ! empty( $headers['uri'] ) ? $headers['uri'] : $headers['original'];
		$install['github_updater_install_repo'] = $install['zipfile_slug'];

		return $install;
	}
}
