<?php
/**
 * Git Updater
 *
 * @author    Andy Fragen
 * @license   MIT
 * @link      https://github.com/afragen/git-updater
 * @package   git-updater
 */

namespace Fragen\Git_Updater;

use Fragen\Singleton;
use Fragen\Git_Updater\Traits\GU_Trait;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Branch
 */
class Branch {
	use GU_Trait;

	/**
	 * Holds Git Updater options
	 *
	 * @var array
	 */
	protected static $options;

	/**
	 * Holds Git Updater Base class object.
	 *
	 * @var Fragen\Git_Updater\Base
	 */
	protected $base;

	/**
	 * Holds rollback tag.
	 *
	 * @var string|bool
	 */
	protected $tag;

	/**
	 * Holds current repo cache.
	 *
	 * @var array|bool
	 */
	protected $cache;

	/**
	 * Holds data to be stored.
	 *
	 * @var string[]
	 */
	protected $response;

	/**
	 * Constructor.
	 */
	public function __construct() {
		self::$options = $this->get_class_vars( 'Fragen\Git_Updater\Base', 'options' );
		$this->base    = Singleton::get_instance( 'Fragen\Git_Updater\Base', $this );
	}

	/**
	 * Get the current repo branch.
	 *
	 * @access public
	 *
	 * @param \stdClass $repo Repository object.
	 *
	 * @return mixed
	 */
	public function get_current_branch( $repo ) {
		$cache          = $this->get_repo_cache( $repo->slug );
		$current_branch = ! empty( $cache['current_branch'] )
			? $cache['current_branch']
			: $repo->branch;

		return $current_branch;
	}

	/**
	 * Update transient for rollback or branch switch.
	 *
	 * @param string    $type plugin|theme.
	 * @param \stdClass $repo Repo object.
	 *
	 * @return array $rollback Rollback transient.
	 */
	public function set_rollback_transient( $type, $repo ) {
		$repo_api = Singleton::get_instance( 'API\API', $this )->get_repo_api( $repo->git, $repo );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->tag     = isset( $_GET['rollback'] ) ? sanitize_text_field( wp_unslash( $_GET['rollback'] ) ) : false;
		$slug          = 'plugin' === $type ? $repo->file : $repo->slug;
		$download_link = $repo_api->construct_download_link( $this->tag );

		/**
		 * Filter download link so developers can point to specific ZipFile
		 * to use as a download link during a branch switch.
		 *
		 * @since 8.6.0
		 *
		 * @param string    $download_link Download URL.
		 * @param /stdClass $repo
		 * @param string    $this->tag     Branch or tag for rollback.
		 */
		$download_link = apply_filters_deprecated( 'github_updater_post_construct_download_link', [ $download_link, $repo, $this->tag ], '10.0.0', 'gu_post_construct_download_link' );

		/**
		 * Filter download link so developers can point to specific ZipFile
		 * to use as a download link during a branch switch.
		 *
		 * @since 10.0.0
		 *
		 * @param string    $download_link Download URL.
		 * @param /stdClass $repo
		 * @param string    $this->tag     Branch or tag for rollback.
		 */
		$download_link = apply_filters( 'gu_post_construct_download_link', $download_link, $repo, $this->tag );

		$repo->download_link = $download_link;
		$rollback            = [
			$type         => $slug,
			'new_version' => $this->tag,
			'url'         => $repo->uri,
			'package'     => $repo->download_link,
			'branch'      => $repo->branch,
			'branches'    => $repo->branches,
			'type'        => $repo->type,
		];

		if ( 'plugin' === $type ) {
			$rollback['slug'] = $repo->slug;
			$rollback         = (object) $rollback;
		}

		return $rollback;
	}

	/**
	 * Set current branch on branch switch.
	 * Exit early if not a rollback.
	 *
	 * @access public
	 *
	 * @param string $repo Repository slug.
	 * @return void
	 */
	public function set_branch_on_switch( $repo ) {
		$this->cache = $this->get_repo_cache( $repo );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$rollback = isset( $_GET['rollback'] ) ? wp_unslash( $_GET['rollback'] ) : false;
		// Exit early if not a rollback, ie normal update.
		if ( ! $rollback ) {
			return;
		}

		$tag_array    = isset( $this->cache['tags'] ) && is_array( $this->cache['tags'] );
		$in_tag_array = $tag_array && in_array( $rollback, $this->cache['tags'], true );
		if ( $in_tag_array ) {
			$current_branch = $this->cache[ $repo ]['PrimaryBranch'] ?? 'master';
		}

		if ( ! $in_tag_array && isset( $_GET['action'], $this->cache['branches'] )
			&& in_array( $_GET['action'], [ 'upgrade-plugin', 'upgrade-theme' ], true )
		) {
			// phpcs:enable
			$current_branch = array_key_exists( $rollback, $this->cache['branches'] )
				? sanitize_text_field( $rollback )
				: 'master';
		}
		if ( isset( $current_branch ) ) {
			$this->set_repo_cache( 'current_branch', $current_branch, $repo );
			self::$options[ 'current_branch_' . $repo ] = $current_branch;
			update_site_option( 'git_updater', self::$options );
		}
	}

	/**
	 * Set current branch on install and update options.
	 *
	 * @access public
	 *
	 * @param array $install Array of install data.
	 */
	public function set_branch_on_install( $install ) {
		$this->set_repo_cache( 'current_branch', $install['git_updater_branch'], $install['repo'] );
		self::$options[ 'current_branch_' . $install['repo'] ] = $install['git_updater_branch'];
		self::$options = isset( $install['options'] ) && is_array( $install['options'] )
			? array_merge( self::$options, $install['options'] )
			: self::$options;
		update_site_option( 'git_updater', self::$options );
	}

	/**
	 * Add branch switch row to plugins page.
	 *
	 * @param string    $plugin_file Plugin file.
	 * @param \stdClass $plugin_data Plugin repo data.
	 *
	 * @return bool
	 */
	public function plugin_branch_switcher( $plugin_file, $plugin_data ) {
		if ( empty( self::$options['branch_switch'] ) ) {
			return false;
		}
		$plugin_obj = Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $this );
		$config     = $this->get_class_vars( 'Fragen\Git_Updater\Plugin', 'config' );
		$plugin     = $this->get_repo_slugs( dirname( $plugin_file ), $plugin_obj );

		$this->base->get_remote_repo_meta( $config[ $plugin['slug'] ] );

		$enclosure         = $this->base->update_row_enclosure( $plugin_file, 'plugin', true );
		$nonced_update_url = wp_nonce_url(
			$this->base->get_update_url( 'plugin', 'upgrade-plugin', $plugin_file ),
			'upgrade-plugin_' . $plugin_file
		);

		if ( ! empty( $plugin ) ) {
			$id       = $plugin['slug'] . '-id';
			$branches = $config[ $plugin['slug'] ]->branches ?? null;
		} else {
			return false;
		}

		// Get current branch.
		$repo   = $config[ $plugin['slug'] ];
		$branch = $this->get_current_branch( $repo );

		$branch_switch_data                      = [];
		$branch_switch_data['slug']              = $plugin['slug'];
		$branch_switch_data['nonced_update_url'] = $nonced_update_url;
		$branch_switch_data['id']                = $id;
		$branch_switch_data['branch']            = $branch;
		$branch_switch_data['branches']          = $branches;
		$branch_switch_data['release_asset']     = $repo->release_asset;
		$branch_switch_data['primary_branch']    = $repo->primary_branch;

		/*
		 * Create after_plugin_row_
		 */
		echo wp_kses_post( $enclosure['open'] );
		$this->make_branch_switch_row( $branch_switch_data, $config );
		echo wp_kses_post( $enclosure['close'] );

		return true;
	}

	/**
	 * Create branch switcher row for theme multisite installation.
	 *
	 * @param string $theme_key Theme slug.
	 * @param array  $theme     Array of theme data.
	 *
	 * @return bool
	 */
	public function multisite_branch_switcher( $theme_key, $theme ) {
		if ( empty( self::$options['branch_switch'] ) ) {
			return false;
		}

		$config = $this->get_class_vars( 'Fragen\Git_Updater\Theme', 'config' );

		$this->base->get_remote_repo_meta( $config[ $theme_key ] );

		$enclosure         = $this->base->update_row_enclosure( $theme_key, 'theme', true );
		$id                = $theme_key . '-id';
		$branches          = $config[ $theme_key ]->branches ?? null;
		$nonced_update_url = wp_nonce_url(
			$this->base->get_update_url( 'theme', 'upgrade-theme', $theme_key ),
			'upgrade-theme_' . $theme_key
		);

		// Get current branch.
		$repo   = $config[ $theme_key ];
		$branch = $this->get_current_branch( $repo );

		$branch_switch_data                      = [];
		$branch_switch_data['slug']              = $theme_key;
		$branch_switch_data['nonced_update_url'] = $nonced_update_url;
		$branch_switch_data['id']                = $id;
		$branch_switch_data['branch']            = $branch;
		$branch_switch_data['branches']          = $branches;
		$branch_switch_data['release_asset']     = $repo->release_asset;
		$branch_switch_data['primary_branch']    = $repo->primary_branch;

		/*
		 * Create after_theme_row_
		 */
		echo wp_kses_post( $enclosure['open'] );
		$this->make_branch_switch_row( $branch_switch_data, $config );
		echo wp_kses_post( $enclosure['close'] );

		return true;
	}

	/**
	 * Display rollback/branch switcher for theme single site installation.
	 *
	 * @param \stdClass $theme Theme object.
	 *
	 * @return string
	 */
	public function single_install_switcher( $theme ) {
		$nonced_update_url = wp_nonce_url(
			$this->base->get_update_url( 'theme', 'upgrade-theme', $theme->slug ),
			'upgrade-theme_' . $theme->slug
		);
		$rollback_url      = sprintf( '%s%s', $nonced_update_url, '&rollback=' );

		ob_start();
		if ( '1' === self::$options['branch_switch'] ) {
			printf(
				/* translators: 1: branch name, 2: jQuery dropdown, 3: closing tag */
				'<p>' . esc_html__( 'Current branch is `%1$s`, try %2$sanother version%3$s', 'git-updater-pro' ),
				esc_attr( $theme->branch ),
				'<a href="#" onclick="jQuery(\'#gu_versions\').toggle();return false;">',
				'</a>.</p>'
			);
			?>
			<div id="gu_versions" style="display:none; width: 100%;">
				<label><select style="width: 60%;" onchange="if(jQuery(this).val() != '') { jQuery(this).parent().next().show(); jQuery(this).parent().next().attr('href','<?php echo esc_url( $rollback_url ); ?>'+jQuery(this).val()); } else jQuery(this).parent().next().hide();">
				<option value=""><?php esc_html_e( 'Choose a Version', 'git-updater-pro' ); ?>&#8230;</option>
			<?php

			// Disable branch switching to primary branch for release assets.
			if ( $theme->release_asset ) {
				unset( $theme->branches[ $theme->primary_branch ] );
			}
			if ( isset( $theme->branches ) ) {
				foreach ( array_keys( $theme->branches ) as $branch ) {
					echo '<option>' . esc_attr( $branch ) . '</option>';
				}
			}
			if ( ! empty( $theme->rollback ) ) {
				$rollback = array_keys( $theme->rollback );
				usort( $rollback, 'version_compare' );
				krsort( $rollback );

				/**
				 * Filter to return the number of tagged releases (rollbacks) in branch switching.
				 *
				 * @since 10.0.0
				 * @param int Number of rollbacks. Zero implies value not set.
				 */
				$num_rollbacks = absint( apply_filters( 'gu_number_rollbacks', 0 ) );

				/**
				 * Filter to return the number of tagged releases (rollbacks) in branch switching.
				 *
				 * @since 9.6.0
				 * @param int Number of rollbacks. Zero implies value not set.
				 */
				$num_rollbacks = 0 === $num_rollbacks ? apply_filters_deprecated( 'github_updater_number_rollbacks', [ 0 ], '10.0.0', 'gu_number_rollbacks' ) : $num_rollbacks;

				// Still only return last tag if using release assets.
				$rollback = 0 === $num_rollbacks || $theme->release_asset
					? array_slice( $rollback, 0, 1 )
					: array_splice( $rollback, 0, $num_rollbacks, true );

				foreach ( $rollback as $tag ) {
					echo '<option>' . esc_attr( $tag ) . '</option>';
				}
			}
			if ( empty( $theme->rollback ) ) {
				echo '<option>' . esc_html__( 'No previous tags to rollback to.', 'git-updater-pro' ) . '</option></select></label>';
			}
			?>
					</select></label>
				<a style="display: none;" class="button-primary" href="?"><?php esc_html_e( 'Install', 'git-updater-pro' ); ?></a>
			</div>
			<?php
		}

		return trim( ob_get_clean(), '1' );
	}

	/**
	 * Make branch switch row.
	 *
	 * @param array $data   Parameters for creating branch switching row.
	 * @param array $config Array of repo objects.
	 *
	 * @return void
	 */
	public function make_branch_switch_row( $data, $config ) {
		$rollback = empty( $config[ $data['slug'] ]->rollback ) ? [] : $config[ $data['slug'] ]->rollback;

		// Make the branch switch row visually appear as if it is contained with the plugin/theme's row.
		// We have to use JS for this because of the way:
		// 1) the @class of the list table row is not filterabled; and
		// 2) the list table CSS is written.
		if ( 'plugin' === $config[ $data['slug'] ]->type ) {
			$data_attr = 'data-plugin';
			$file      = $config[ $data['slug'] ]->file;
		} else {
			$data_attr = 'data-slug';
			$file      = $config[ $data['slug'] ]->slug;
		}
		echo '<script>';
		// Remove the bottom "line" for the plugin's row.
		printf(
			"jQuery( 'tr:not([id])[" . esc_attr( $data_attr ) . "=\"%s\"]' ).addClass( 'update' );",
			esc_attr( $file )
		);
		// Removes the bottom "line" for the shiny update row (if any).
		printf(
			"jQuery( 'tr[id][" . esc_attr( $data_attr ) . "=\"%s\"] td' ).css( 'box-shadow', 'none' );",
			esc_attr( $file )
		);
		echo '</script>';

		echo '<p>';
		echo wp_kses_post( $this->base->get_git_icon( $file, true ) );
		printf(
			/* translators: 1: branch name, 2: jQuery dropdown, 3: closing tag */
			esc_html__( 'Current branch is `%1$s`, try %2$sanother version%3$s', 'git-updater-pro' ),
			esc_attr( $data['branch'] ),
			'<a href="#" onclick="jQuery(\'#' . esc_attr( $data['id'] ) . '\').toggle();return false;">',
			'</a>.'
		);
		echo '</p>';

		print '<ul id="' . esc_attr( $data['id'] ) . '" style="display:none; width: 100%;">';

		// Disable branch switching to primary branch for release assets.
		if ( $data['release_asset'] ) {
			unset( $data['branches'][ $data['primary_branch'] ] );
		}

		/**
		 * Filter out branches for release assets if desired.
		 * Removes all branches from the branch switcher leaving only the tags.
		 *
		 * @since 10.0.0
		 *
		 * @return bool
		 */
		$no_release_asset_branches = (bool) apply_filters( 'gu_no_release_asset_branches', false );

		/**
		 * Filter out branches for release assets if desired.
		 * Removes all branches from the branch switcher leaving only the tags.
		 *
		 * @since 9.9.1
		 *
		 * @return bool
		 */
		$no_release_asset_branches = $no_release_asset_branches ?: (bool) apply_filters_deprecated( 'github_updater_no_release_asset_branches', [ false ], '10.0.0', 'gu_no_release_asset_branches' );

		$data['branches'] = $data['release_asset'] && $no_release_asset_branches ? [] : $data['branches'];

		if ( null !== $data['branches'] ) {
			foreach ( array_keys( $data['branches'] ) as $branch ) {
				printf(
					'<li><a href="%s%s" aria-label="' . esc_html__( 'Switch to branch ', 'git-updater-pro' ) . esc_attr( $branch ) . '">%s</a></li>',
					esc_url( $data['nonced_update_url'] ),
					'&rollback=' . rawurlencode( $branch ),
					esc_attr( $branch )
				);
			}
		}

		if ( ! empty( $rollback ) ) {
			$rollback = array_keys( $rollback );
			usort( $rollback, 'version_compare' );
			krsort( $rollback );

			/**
			 * Filter to return the number of tagged releases (rollbacks) in branch switching.
			 *
			 * @since 10.0.0
			 * @param int Number of rollbacks. Zero implies value not set.
			 */
			$num_rollbacks = absint( apply_filters( 'gu_number_rollbacks', 0 ) );

			/**
			 * Filter to return the number of tagged releases (rollbacks) in branch switching.
			 *
			 * @since 9.6.0
			 * @param int Number of rollbacks. Zero implies value not set.
			 */
			$num_rollbacks = 0 === $num_rollbacks ? absint( apply_filters_deprecated( 'github_updater_number_rollbacks', [ 0 ], '10.0.0', 'gu_number_rollbacks' ) ) : $num_rollbacks;

			// Still only return last tag if using release assets.
			$rollback = 0 === $num_rollbacks || $data['release_asset']
				? array_slice( $rollback, 0, 1 )
				: array_splice( $rollback, 0, $num_rollbacks, true );

			if ( $data['release_asset'] ) {
				/**
				 * Filter release asset rollbacks.
				 *
				 * @since 10.0.0
				 *
				 * @return array
				 */
				$release_asset_rollback = apply_filters( 'gu_release_asset_rollback', $rollback, $file );

				/**
				 * Filter release asset rollbacks.
				 *
				 * @since 9.9.2
				 *
				 * @return array
				 */
				$release_asset_rollback = apply_filters_deprecated( 'github_updater_release_asset_rollback', [ $rollback, $file ], '10.0.0', 'gu_release_asset_rollback' );

				if ( ! empty( $release_asset_rollback ) && is_array( $release_asset_rollback ) ) {
					$rollback = $release_asset_rollback;
				}
			}

			foreach ( $rollback as $tag ) {
				printf(
					'<li><a href="%s%s" aria-label="' . esc_html__( 'Switch to release ', 'git-updater-pro' ) . esc_attr( $tag ) . '">%s</a></li>',
					esc_url( $data['nonced_update_url'] ),
					'&rollback=' . rawurlencode( $tag ),
					esc_attr( $tag )
				);
			}
		}
		if ( empty( $rollback ) ) {
			echo '<li>' . esc_html__( 'No previous tags to rollback to.', 'git-updater-pro' ) . '</li>';
		}

		print '</ul>';
	}

}
