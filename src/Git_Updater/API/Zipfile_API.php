<?php
/**
 * Git Updater
 *
 * Only active with license.
 *
 * @author   Andy Fragen
 * @license  GPL-3.0-or-later
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace Fragen\Git_Updater\API;

use Fragen\Singleton;
use WP_Error;

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
	 * @param array<string, string> $git_servers Array of git servers.
	 *
	 * @return array<string, string>
	 */
	public function set_git_servers( $git_servers ) {
		return array_merge( $git_servers, [ 'zipfile' => 'Zipfile' ] );
	}

	/**
	 * Add API data to $installed_apis.
	 *
	 * @param array<string, bool> $installed_apis Array of installed APIs.
	 *
	 * @return array<string, bool>
	 */
	public function set_installed_apis( $installed_apis ) {
		return array_merge( $installed_apis, [ 'zipfile_api' => true ] );
	}

	/**
	 * Set remote installation data for specific API.
	 *
	 * @param array<string, mixed>  $install Array of remote installation data.
	 * @param array<string, string> $headers Array of repository header data.
	 *
	 * @return array<string, mixed>
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
	 * @return void
	 */
	public function add_install_settings_fields( $type ) {
		add_settings_field(
			'zipfile_slug',
			esc_html__( 'Zipfile Slug', 'git-updater' ),
			[ $this, 'zipfile_slug' ],
			'git_updater_install_' . $type,
			$type
		);
	}

	/**
	 * Set repo slug for remote install.
	 *
	 * @return void
	 */
	public function zipfile_slug() {
		?>
		<label for="zipfile_slug">
			<input class="zipfile_setting" type="text" style="width:50%;" id="zipfile_slug" name="zipfile_slug" value="" placeholder="my-repo-slug">
			<br>
			<span class="description">
				<?php esc_html_e( 'Enter plugin or theme slug.', 'git-updater' ); ?>
			</span>
		</label>
		<?php
	}

	/**
	 *  Add remote install feature, create endpoint.
	 *
	 * @param array<string, string> $headers Array of headers.
	 * @param array<string, mixed>  $install Array of install data.
	 *
	 * @return array<string, mixed>
	 */
	public function remote_install( $headers, $install ) {
		$url = ! empty( $headers['uri'] ) ? $headers['uri'] : $headers['original'];

		// The posted URI becomes the upgrader's download_link, so it must not be
		// able to point at an arbitrary host.
		if ( ! $this->is_allowed_install_url( (string) $url ) ) {
			$install['error']         = new WP_Error(
				'gu_install_host_not_allowed',
				esc_html__( 'Zipfile install requires an https URL from an allowed git host.', 'git-updater' )
			);
			$install['download_link'] = '';

			return $install;
		}

		$install['download_link']            = $url;
		$install['git_updater_install_repo'] = $install['zipfile_slug'];

		return $install;
	}

	/**
	 * Whether a zipfile install URL is https and on an allowed host.
	 *
	 * @param string $url The posted repository URI.
	 *
	 * @return bool
	 */
	private function is_allowed_install_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}

		$host = strtolower( $parts['host'] );
		foreach ( $this->get_allowed_install_hosts() as $allowed ) {
			if ( $host === $allowed || str_ends_with( $host, '.' . $allowed ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Hosts allowed as a zipfile install source.
	 *
	 * Derived from the credential-host map contributed by the active API
	 * add-ons (which already covers public, enterprise, and self-hosted
	 * domains), plus the site's own host. Still filterable for back-compat.
	 *
	 * @return array<int, string>
	 */
	private function get_allowed_install_hosts(): array {
		$api   = Singleton::get_instance( 'Fragen\Git_Updater\API\API', $this );
		$hosts = [];
		foreach ( $api->get_credential_hosts() as $provider_hosts ) {
			$hosts = array_merge( $hosts, (array) $provider_hosts );
		}

		$hosts[] = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		/**
		 * Filter the hosts allowed as a zipfile remote install source.
		 *
		 * @since 14.4.3
		 *
		 * @param array<int, string> $hosts Allowed hostnames.
		 */
		return array_values( array_unique( array_filter( array_map( 'strtolower', (array) apply_filters( 'gu_install_allowed_hosts', $hosts ) ) ) ) );
	}
}
