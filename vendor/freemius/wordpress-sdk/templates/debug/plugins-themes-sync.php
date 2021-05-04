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

	$fs_options  = FS_Options::instance( WP_FS__ACCOUNTS_OPTION_NAME, true );
	$all_plugins = $fs_options->get_option( 'all_plugins' );
	$all_themes  = $fs_options->get_option( 'all_themes' );

    /* translators: %s: time period (e.g. In "2 hours") */
	$in_x_text = fs_text_inline( 'In %s', 'in-x' );
    /* translators: %s: time period (e.g. "2 hours" ago) */
	$x_ago_text = fs_text_inline( '%s ago', 'x-ago' );
	$sec_text   = fs_text_x_inline( 'sec', 'seconds' );
?>
<h1><?php fs_esc_html_echo_inline( 'Plugins & Themes Sync', 'plugins-themes-sync' ) ?></h1>
<table class="widefat">
	<thead>
	<tr>
		<th></th>
		<th><?php fs_esc_html_echo_inline( 'Total', 'total' ) ?></th>
		<th><?php fs_esc_html_echo_inline( 'Last', 'last' ) ?></th>
	</tr>
	</thead>
	<tbody>
	<?php if ( is_object( $all_plugins ) ) : ?>
		<tr>
			<td><?php fs_esc_html_echo_inline( 'Plugins', 'plugins' ) ?></td>
			<td><?php echo count( $all_plugins->plugins ) ?></td>
			<td><?php
					if ( isset( $all_plugins->timestamp ) && is_numeric( $all_plugins->timestamp ) ) {
						$diff       = abs( WP_FS__SCRIPT_START_TIME - $all_plugins->timestamp );
						$human_diff = ( $diff < MINUTE_IN_SECONDS ) ?
							$diff . ' ' . $sec_text :
							human_time_diff( WP_FS__SCRIPT_START_TIME, $all_plugins->timestamp );

                        echo esc_html( sprintf(
                            ( ( WP_FS__SCRIPT_START_TIME < $all_plugins->timestamp ) ?
                                $in_x_text :
                                $x_ago_text ),
                            $human_diff
                        ) );
					}
				?></td>
		</tr>
	<?php endif ?>
	<?php if ( is_object( $all_themes ) ) : ?>
		<tr>
			<td><?php fs_esc_html_echo_inline( 'Themes', 'themes' ) ?></td>
			<td><?php echo count( $all_themes->themes ) ?></td>
			<td><?php
					if ( isset( $all_themes->timestamp ) && is_numeric( $all_themes->timestamp ) ) {
						$diff       = abs( WP_FS__SCRIPT_START_TIME - $all_themes->timestamp );
						$human_diff = ( $diff < MINUTE_IN_SECONDS ) ?
							$diff . ' ' . $sec_text :
							human_time_diff( WP_FS__SCRIPT_START_TIME, $all_themes->timestamp );

                        echo esc_html( sprintf(
                            ( ( WP_FS__SCRIPT_START_TIME < $all_themes->timestamp ) ?
                                $in_x_text :
                                $x_ago_text ),
                            $human_diff
                        ) );
					}
				?></td>
		</tr>
	<?php endif ?>
	</tbody>
</table>
