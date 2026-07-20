<?php
/**
 * Concise coverage gate. Parses clover.xml and reports only the gaps.
 *
 * Usage: php bin/coverage-check.php [path/to/clover.xml]
 *   - prints one line per file with <100% line coverage (path:uncovered-line ...)
 *   - exits 0 when every file is 100% lines covered, 1 otherwise.
 *
 * @package Git_Updater
 */

$clover = $argv[1] ?? __DIR__ . '/../clover.xml';

if ( ! is_file( $clover ) ) {
	fwrite( STDERR, "coverage-check: clover.xml not found at '$clover'\n" );
	exit( 2 );
}

$xml  = simplexml_load_file( $clover );
$root = dirname( __DIR__ ) . '/';

$xpath = $xml->xpath( '//file' );
$files  = $xpath ? $xpath : [];
$gaps      = [];
$file_count = 0;

foreach ( $files as $file ) {
	$path = (string) $file['name'];
	if ( str_starts_with( $path, $root ) ) {
		$path = substr( $path, strlen( $root ) );
	}
	++$file_count;

	$lines = [];
	foreach ( $file->line as $line ) {
		if ( (string) $line['type'] === 'stmt' && (int) $line['count'] === 0 ) {
			$lines[] = (int) $line['num'];
		}
	}

	if ( $lines ) {
		sort( $lines );
		$gaps[ $path ] = $lines;
	}
}

if ( ! $gaps ) {
	echo '100% line coverage — ' . $file_count . " files checked\n";
	exit( 0 );
}

foreach ( $gaps as $path => $lines ) {
	echo $path . ': ' . implode( ', ', $lines ) . "\n";
}
$total = array_sum( array_map( 'count', $gaps ) );
fwrite( STDERR, 'COVERAGE GAP: ' . $total . ' uncovered statement line(s) across ' . count( $gaps ) . " file(s)\n" );
exit( 1 );
