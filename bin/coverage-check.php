<?php
/**
 * Concise coverage gate. Parses clover.xml and reports only the gaps.
 *
 * Usage: php bin/coverage-check.php [--multisite] [path/to/clover.xml]
 *   - prints one line per file with <100% line coverage (path:uncovered-line ...)
 *   - exits 0 when every reachable line is covered, 1 otherwise.
 *
 * Some lines are reachable only in one environment (guarded by is_multisite()).
 * They are covered by skip-guarded tests in the environment where they run,
 * and are structurally unreachable in the other. When run under multisite
 * (--multisite), those single-site-only lines are excluded from the gap
 * check instead of being blanket-ignored in the source.
 *
 * @package Git_Updater
 */

$env        = in_array( '--multisite', $argv, true ) ? 'multisite' : 'singlesite';
$clover    = $argv[1] ?? __DIR__ . '/../clover.xml';
$clover    = in_array( $clover, [ '--multisite', '--singlesite' ], true ) ? __DIR__ . '/../clover.xml' : $clover;

// Lines reachable only on single-site: covered by skip-guarded tests
// there, unreachable under multisite (is_multisite() === true).
$single_site_only = [
	'src/Git_Updater/Theme.php'            => [ 315 ],
	'src/Git_Updater/OAuth/OAuth_Connect.php' => [ 339 ],
];

if ( ! is_file( $clover ) ) {
	fwrite( STDERR, "coverage-check: clover.xml not found at '$clover'\n" );
	exit( 2 );
}

$xml  = simplexml_load_file( $clover );
// clover.xml is written inside the container but read on the host, so paths may
// be container paths (/var/www/html/wp-content/plugins/<slug>/...) or host
// paths. Normalize both to a repo-relative path.
$root        = dirname( __DIR__ ) . '/';
$container_root = '/var/www/html/wp-content/plugins/git-updater/';

$xpath = $xml->xpath( '//file' );
$files  = $xpath ? $xpath : [];
$gaps      = [];
$file_count = 0;

foreach ( $files as $file ) {
	$path = (string) $file['name'];
	if ( str_starts_with( $path, $container_root ) ) {
		$path = substr( $path, strlen( $container_root ) );
	} elseif ( str_starts_with( $path, $root ) ) {
		$path = substr( $path, strlen( $root ) );
	}
	++$file_count;

	$excluded = ( 'multisite' === $env && isset( $single_site_only[ $path ] ) )
		? $single_site_only[ $path ]
		: [];

	$lines = [];
	foreach ( $file->line as $line ) {
		if ( (string) $line['type'] === 'stmt' && (int) $line['count'] === 0 ) {
			$num = (int) $line['num'];
			if ( ! in_array( $num, $excluded, true ) ) {
				$lines[] = $num;
			}
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
