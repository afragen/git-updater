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

	if ( class_exists( 'Freemius_Api_WordPress' ) ) {
		$logger = Freemius_Api_WordPress::GetLogger();
	} else {
		$logger = array();
	}

	$counters = array(
		'GET'    => 0,
		'POST'   => 0,
		'PUT'    => 0,
		'DELETE' => 0
	);

	$show_body = false;
	foreach ( $logger as $log ) {
		$counters[ $log['method'] ] ++;

		if ( ! is_null( $log['body'] ) ) {
			$show_body = true;
		}
	}

	$pretty_print = $show_body && defined( 'JSON_PRETTY_PRINT' ) && version_compare( phpversion(), '5.3', '>=' );

	/**
	 * This template is used for debugging, therefore, when possible
	 * we'd like to prettify the output of a JSON encoded variable.
	 * This will only be executed when $pretty_print is `true`, and
	 * the var is `true` only for PHP 5.3 and higher. Due to the
	 * limitations of the current Theme Check, it throws an error
	 * that using the "options" parameter (the 2nd param) is not
	 * supported in PHP 5.2 and lower. Thus, we added this alias
	 * variable to work around that false-positive.
	 *
	 * @author Vova Feldman (@svovaf)
	 * @since  1.2.2.7
	 */
	$encode = 'json_encode';

	$root_path_len = strlen( ABSPATH );

	$ms_text = fs_text_x_inline( 'ms', 'milliseconds' );
?>
<h1><?php fs_echo_inline( 'API' ) ?></h1>

<h2><span>Total Time:</span><?php echo Freemius_Debug_Bar_Panel::total_time() ?></h2>

<h2><span>Total Requests:</span><?php echo Freemius_Debug_Bar_Panel::requests_count() ?></h2>
<?php foreach ( $counters as $method => $count ) : ?>
	<h2><span><?php echo $method ?>:</span><?php echo number_format( $count ) ?></h2>
<?php endforeach ?>
<table class="widefat">
	<thead>
	<tr>
		<th>#</th>
		<th><?php fs_esc_html_echo_inline( 'Method' ) ?></th>
		<th><?php fs_esc_html_echo_inline( 'Code' ) ?></th>
		<th><?php fs_esc_html_echo_inline( 'Length' ) ?></th>
		<th><?php fs_esc_html_echo_x_inline( 'Path', 'as file/folder path' ) ?></th>
		<?php if ( $show_body ) : ?>
			<th><?php fs_esc_html_echo_inline( 'Body' ) ?></th>
		<?php endif ?>
		<th><?php fs_esc_html_echo_inline( 'Result' ) ?></th>
		<th><?php fs_esc_html_echo_inline( 'Start' ) ?></th>
		<th><?php fs_esc_html_echo_inline( 'End' ) ?></th>
	</tr>
	</thead>
	<tbody>
	<?php foreach ( $logger as $log ) : ?>
		<tr>
			<td><?php echo $log['id'] ?>.</td>
			<td><?php echo $log['method'] ?></td>
			<td><?php echo $log['code'] ?></td>
			<td><?php echo number_format( 100 * $log['total'], 2 ) . ' ' . $ms_text ?></td>
			<td>
				<?php
					printf( '<a href="#" onclick="jQuery(this).parent().find(\'table\').toggle(); return false;">%s</a>',
						$log['path']
					);
				?>
				<table class="widefat" style="display: none">
					<tbody>
					<?php for ( $i = 0, $bt = $log['backtrace'], $len = count( $bt ); $i < $len; $i ++ ) : ?>
						<tr>
							<td><?php echo( $len - $i ) ?></td>
							<td><?php if ( isset( $bt[ $i ]['function'] ) ) {
									echo ( isset( $bt[ $i ]['class'] ) ? $bt[ $i ]['class'] . $bt[ $i ]['type'] : '' ) . $bt[ $i ]['function'];
								} ?></td>
							<td><?php if ( isset( $bt[ $i ]['file'] ) ) {
									echo substr( $bt[ $i ]['file'], $root_path_len ) . ':' . $bt[ $i ]['line'];
								} ?></td>
						</tr>
					<?php endfor ?>
					</tbody>
				</table>
			</td>
			<?php if ( $show_body ) : ?>
				<td>
					<?php if ( 'GET' !== $log['method'] ) : ?>
						<?php
						$body = $log['body'];
						printf(
							'<a href="#" onclick="jQuery(this).parent().find(\'pre\').toggle(); return false;">%s</a>',
							substr( $body, 0, 32 ) . ( 32 < strlen( $body ) ? '...' : '' )
						);
						if ( $pretty_print ) {
							$body = $encode( json_decode( $log['body'] ), JSON_PRETTY_PRINT );
						}
						?>
						<pre style="display: none"><code><?php echo esc_html( $body ) ?></code></pre>
					<?php endif ?>
				</td>
			<?php endif ?>
			<td>
				<?php
					$result = $log['result'];

					$is_not_empty_result = ( is_string( $result ) && ! empty( $result ) );

					if ( $is_not_empty_result ) {
						printf(
							'<a href="#" onclick="jQuery(this).parent().find(\'pre\').toggle(); return false;">%s</a>',
							substr( $result, 0, 32 ) . ( 32 < strlen( $result ) ? '...' : '' )
						);
					}

					if ( $is_not_empty_result && $pretty_print ) {
						$decoded = json_decode( $result );
						if ( ! is_null( $decoded ) ) {
							$result = $encode( $decoded, JSON_PRETTY_PRINT );
						}
					} else {
						$result = is_string( $result ) ? $result : json_encode( $result );
					}
				?>
				<pre<?php if ( $is_not_empty_result ) : ?> style="display: none"<?php endif ?>><code><?php echo esc_html( $result ) ?></code></pre>
			</td>
			<td><?php echo number_format( 100 * ( $log['start'] - WP_FS__SCRIPT_START_TIME ), 2 ) . ' ' . $ms_text ?></td>
			<td><?php echo number_format( 100 * ( $log['end'] - WP_FS__SCRIPT_START_TIME ), 2 ) . ' ' . $ms_text ?></td>
		</tr>
	<?php endforeach ?>
	</tbody>
</table>