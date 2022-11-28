<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.1.1
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * @var array $VARS
	 */
	$sections = $VARS['sections'];
?>
<table>
	<?php
	foreach ( $sections as $section_id => $section ) {
		?>
		<thead>
			<tr><th colspan="2" style="text-align: left; background: #333; color: #fff; padding: 5px;"><?php echo esc_html($section['title']) ?></th></tr>
		</thead>
		<tbody>
		<?php
		foreach ( $section['rows'] as $row_id => $row ) {
			$col_count = count( $row );
			?>
			<tr>
				<?php
				if ( 1 === $col_count ) { ?>
					<td style="vertical-align: top;" colspan="2"><?php echo $row[0] ?></td>
					<?php
				} else { ?>
					<td style="vertical-align: top;"><b><?php echo esc_html($row[0]) ?>:</b></td>
					<td><?php echo $row[1]; ?></td>
					<?php
				}
				?>
			</tr>
			<?php
		}
		?>
		</tbody>
		<?php
	}
	?>
</table>