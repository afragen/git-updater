<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.1.7.3
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	$fs_options      = FS_Options::instance( WP_FS__ACCOUNTS_OPTION_NAME, true );
	$scheduled_crons = array();

	$is_fs_debug_page = ( isset( $VARS['is_fs_debug_page'] ) && $VARS['is_fs_debug_page'] );

	$module_types = array(
		WP_FS__MODULE_TYPE_PLUGIN,
		WP_FS__MODULE_TYPE_THEME
	);

	foreach ( $module_types as $module_type ) {
		$modules = fs_get_entities( $fs_options->get_option( $module_type . 's' ), FS_Plugin::get_class_name() );
		if ( is_array( $modules ) && count( $modules ) > 0 ) {
			foreach ( $modules as $slug => $data ) {
				if ( WP_FS__MODULE_TYPE_THEME === $module_type ) {
					$current_theme = wp_get_theme();
					$is_active = ( $current_theme->stylesheet === $data->file );
				} else {
					$is_active = is_plugin_active( $data->file );
				}

				/**
				 * @author Vova Feldman
				 *
				 * @since 1.2.1 Don't load data from inactive modules.
				 */
				if ( $is_active ) {
					$fs = freemius( $data->id );

					$next_execution = $fs->next_sync_cron();
					$last_execution = $fs->last_sync_cron();

					if ( false !== $next_execution ) {
						$scheduled_crons[ $slug ][] = array(
							'name' => $fs->get_plugin_name(),
							'slug' => $slug,
							'module_type' => $fs->get_module_type(),
							'type' => 'sync_cron',
							'last' => $last_execution,
							'next' => $next_execution,
						);
					}

					$next_install_execution = $fs->next_install_sync();
					$last_install_execution = $fs->last_install_sync();

					if (false !== $next_install_execution ||
						false !== $last_install_execution
					) {
						$scheduled_crons[ $slug ][] = array(
							'name' => $fs->get_plugin_name(),
							'slug' => $slug,
							'module_type' => $fs->get_module_type(),
							'type' => 'install_sync',
							'last' => $last_install_execution,
							'next' => $next_install_execution,
						);
					}
				}
			}
		}
	}

	$sec_text = fs_text_x_inline( 'sec', 'seconds' );
?>
<?php if ( $is_fs_debug_page ) : ?>
<h2>
    <button class="fs-debug-table-toggle-button" aria-expanded="true">
        <span class="fs-debug-table-toggle-icon">▼</span>
    </button>
    <?php fs_esc_html_echo_inline( 'Scheduled Crons' ) ?>
</h2>
<?php else : ?>
<h1><?php fs_esc_html_echo_inline( 'Scheduled Crons' ) ?></h1>
<?php endif ?>
<table class="widefat fs-debug-table">
	<thead>
	<tr>
		<th><?php fs_esc_html_echo_inline( 'Slug' ) ?></th>
		<th><?php fs_esc_html_echo_inline( 'Module' ) ?></th>
		<th><?php fs_esc_html_echo_inline( 'Module Type' ) ?></th>
		<th><?php fs_esc_html_echo_inline( 'Cron Type' ) ?></th>
		<th><?php fs_esc_html_echo_inline( 'Last' ) ?></th>
		<th><?php fs_esc_html_echo_inline( 'Next' ) ?></th>
	</tr>
	</thead>
	<tbody>
	<?php
		/* translators: %s: time period (e.g. In "2 hours") */
		$in_x_text = fs_text_inline( 'In %s', 'in-x' );
		/* translators: %s: time period (e.g. "2 hours" ago) */
		$x_ago_text = fs_text_inline( '%s ago', 'x-ago' );
	?>
	<?php foreach ( $scheduled_crons as $slug => $crons ) : ?>
		<?php foreach ( $crons as $cron ) : ?>
			<tr>
				<td><?php echo $slug ?></td>
				<td><?php echo $cron['name'] ?></td>
				<td><?php echo $cron['module_type'] ?></td>
				<td><?php echo $cron['type'] ?></td>
				<td><?php
						if ( is_numeric( $cron['last'] ) ) {
							$diff       = abs( WP_FS__SCRIPT_START_TIME - $cron['last'] );
							$human_diff = ( $diff < MINUTE_IN_SECONDS ) ?
								$diff . ' ' . $sec_text :
								human_time_diff( WP_FS__SCRIPT_START_TIME, $cron['last'] );

							echo esc_html( sprintf(
								( ( WP_FS__SCRIPT_START_TIME < $cron['last'] ) ?
									$in_x_text :
									$x_ago_text ),
								$human_diff
							) );
						}
					?></td>
				<td><?php
						if ( is_numeric( $cron['next'] ) ) {
							$diff       = abs( WP_FS__SCRIPT_START_TIME - $cron['next'] );
							$human_diff = ( $diff < MINUTE_IN_SECONDS ) ?
								$diff . ' ' . $sec_text :
								human_time_diff( WP_FS__SCRIPT_START_TIME, $cron['next'] );

							echo esc_html( sprintf(
								( ( WP_FS__SCRIPT_START_TIME < $cron['next'] ) ?
									$in_x_text :
									$x_ago_text ),
								$human_diff
							) );
						}
					?></td>
			</tr>
		<?php endforeach ?>
	<?php endforeach ?>
	</tbody>
</table>
