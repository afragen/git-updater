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

/**
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Language_Pack
 *
 * @package Fragen\GitHub_Updater
 */
class Language_Pack extends Base {

	/**
	 * Variable containing the plugin/theme object.
	 *
	 * @var Plugin|Theme
	 */
	protected $repo;

	/**
	 * Language_Pack constructor.
	 *
	 * @param Plugin|Theme      $repo Plugin/Theme object
	 * @param Language_Pack_API $api  Language_Pack_API object.
	 */
	public function __construct( $repo, Language_Pack_API $api ) {
		parent::__construct();
		if ( null === $repo->languages ) {
			return;
		}

		$this->repo     = $repo;
		$this->repo_api = $api;
	}

	/**
	 * Do the Language Pack integration.
	 */
	public function run() {
		if ( null === $this->repo ) {
			return false;
		}

		$headers = $this->parse_header_uri( $this->repo->languages );
		$this->repo_api->get_language_pack( $headers );

		add_filter( 'pre_set_site_transient_update_plugins', array( &$this, 'pre_set_site_transient' ) );
		add_filter( 'pre_set_site_transient_update_themes', array( &$this, 'pre_set_site_transient' ) );
	}

	/**
	 * Add language translations to update_plugins or update_themes transients.
	 *
	 * @param $transient
	 *
	 * @return mixed
	 */
	public function pre_set_site_transient( $transient ) {
		$locales = get_available_languages();
		$locales = ! empty( $locales ) ? $locales : array( get_locale() );
		$repos   = array();

		if ( ! isset( $transient->translations ) ) {
			return $transient;
		}

		if ( 'pre_set_site_transient_update_plugins' === current_filter() ) {
			$repos        = Singleton::get_instance( 'Plugin' )->get_plugin_configs();
			$translations = wp_get_installed_translations( 'plugins' );
		}
		if ( 'pre_set_site_transient_update_themes' === current_filter() ) {
			$repos        = Singleton::get_instance( 'Theme' )->get_theme_configs();
			$translations = wp_get_installed_translations( 'themes' );
		}

		$repos = array_filter( $repos, function( $e ) {
			return isset( $e->language_packs );
		} );

		foreach ( $repos as $repo ) {
			foreach ( $locales as $locale ) {
				$lang_pack_mod   = isset( $repo->language_packs->$locale )
					? strtotime( $repo->language_packs->$locale->updated )
					: 0;
				$translation_mod = isset( $translations[ $repo->repo ][ $locale ] )
					? strtotime( $translations[ $repo->repo ][ $locale ]['PO-Revision-Date'] )
					: 0;
				if ( $lang_pack_mod > $translation_mod ) {
					$transient->translations[] = (array) $repo->language_packs->$locale;
				}
			}
		}

		$transient->translations = array_unique( $transient->translations, SORT_REGULAR );

		return $transient;
	}

}
