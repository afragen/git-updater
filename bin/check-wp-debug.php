<?php
require_once '/var/www/html/wp-load.php';
echo 'Defined: ' . ( defined( 'WP_DEBUG' ) ? 'yes' : 'no' ) . PHP_EOL;
if ( defined( 'WP_DEBUG' ) ) {
	echo 'Value: ' . ( WP_DEBUG ? 'true' : 'false' ) . PHP_EOL;
} else {
	echo 'Value: N/A' . PHP_EOL;
}
