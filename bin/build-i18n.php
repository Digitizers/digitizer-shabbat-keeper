<?php
/**
 * Rebuild the translation catalogs from the source.
 *
 * Collects every gettext call in the shipped PHP and writes:
 *
 *   languages/digitizer-shabbat-keeper.pot
 *   languages/digitizer-shabbat-keeper-he_IL.po
 *   languages/digitizer-shabbat-keeper-he_IL.l10n.php
 *
 * The Hebrew comes from the existing he_IL.po, which is the record of the
 * translations: add a msgid/msgstr pair there for a new string, then run this
 * to put everything back in source order. A string with no Hebrew fails the
 * build, so a release cannot ship half translated. Compile the .mo afterwards:
 *
 *   php bin/build-i18n.php && msgfmt -o languages/digitizer-shabbat-keeper-he_IL.mo languages/digitizer-shabbat-keeper-he_IL.po
 *
 * Development only - not shipped (.distignore).
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root   = dirname( __DIR__ );
$domain = 'digitizer-shabbat-keeper';
$dir    = $root . '/languages';
$funcs  = array( '__', 'esc_html__', 'esc_attr__', 'esc_html_e', 'esc_attr_e', '_e' );

$files = array_merge(
	array( $root . '/digitizer-shabbat-keeper.php', $root . '/uninstall.php' ),
	glob( $root . '/includes/*.php' ),
	glob( $root . '/views/*.php' )
);

/** The PHP value of a single- or double-quoted string literal token. */
function dsk_i18n_literal( $token ) {
	$quote = $token[0];
	$body  = substr( $token, 1, -1 );
	return "'" === $quote ? str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $body ) : stripcslashes( $body );
}

$entries = array(); // msgid => array( refs, comment ).
foreach ( $files as $file ) {
	$tokens  = token_get_all( file_get_contents( $file ) );
	$comment = null; // The last translators comment, and the line it ended on.
	$count   = count( $tokens );
	for ( $i = 0; $i < $count; $i++ ) {
		$t = $tokens[ $i ];
		if ( ! is_array( $t ) ) {
			continue;
		}
		if ( ( T_COMMENT === $t[0] || T_DOC_COMMENT === $t[0] ) && false !== stripos( $t[1], 'translators:' ) ) {
			$text    = trim( preg_replace( '#^/\*+|\*+/$|^//#', '', trim( $t[1] ) ) );
			$comment = array( preg_replace( '/\s+/', ' ', $text ), $t[2] + substr_count( $t[1], "\n" ) );
			continue;
		}
		if ( T_STRING !== $t[0] || ! in_array( $t[1], $funcs, true ) ) {
			continue;
		}
		// name ( 'literal' , 'domain' )
		$j = $i + 1;
		while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			$j++;
		}
		if ( $j >= $count || '(' !== $tokens[ $j ] ) {
			continue;
		}
		$j++;
		while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			$j++;
		}
		if ( $j >= $count || ! is_array( $tokens[ $j ] ) || T_CONSTANT_ENCAPSED_STRING !== $tokens[ $j ][0] ) {
			fwrite( STDERR, sprintf( "%s:%d: %s() is not called with a string literal\n", basename( $file ), $t[2], $t[1] ) );
			exit( 1 );
		}
		$msgid = dsk_i18n_literal( $tokens[ $j ][1] );
		$ref   = str_replace( $root . '/', '', $file ) . ':' . $t[2];
		if ( ! isset( $entries[ $msgid ] ) ) {
			$entries[ $msgid ] = array( 'refs' => array(), 'comment' => '' );
		}
		$entries[ $msgid ]['refs'][] = $ref;
		// A translators comment belongs to the first call after it, and only
		// when that call is within a few lines.
		if ( $comment && $t[2] >= $comment[1] && $t[2] - $comment[1] <= 4 ) {
			$entries[ $msgid ]['comment'] = $comment[0];
		}
		$comment = null;
	}
}

// The plugin header, which WordPress translates on the Plugins screen.
$header = file_get_contents( $root . '/digitizer-shabbat-keeper.php' );
foreach ( array( 'Plugin Name', 'Description' ) as $field ) {
	if ( preg_match( '/^\s*\*\s*' . preg_quote( $field, '/' ) . ':\s*(.+)$/m', $header, $m ) ) {
		$msgid = trim( $m[1] );
		if ( ! isset( $entries[ $msgid ] ) ) {
			$entries[ $msgid ] = array( 'refs' => array( 'digitizer-shabbat-keeper.php' ), 'comment' => $field . ' of the plugin' );
		}
	}
}

/** msgid => msgstr from a .po file. */
function dsk_i18n_read_po( $path ) {
	$map = array();
	if ( ! is_file( $path ) ) {
		return $map;
	}
	preg_match_all( '/^msgid "((?:[^"\\\\]|\\\\.)*)"\nmsgstr "((?:[^"\\\\]|\\\\.)*)"$/m', file_get_contents( $path ), $m, PREG_SET_ORDER );
	foreach ( $m as $pair ) {
		if ( '' !== $pair[1] ) {
			$map[ stripcslashes( $pair[1] ) ] = stripcslashes( $pair[2] );
		}
	}
	return $map;
}
function dsk_i18n_po_string( $s ) {
	return '"' . addcslashes( $s, "\\\"\n" ) . '"';
}

$po_path = $dir . '/' . $domain . '-he_IL.po';
$hebrew  = dsk_i18n_read_po( $po_path );
$missing = array();
foreach ( array_keys( $entries ) as $msgid ) {
	if ( ! isset( $hebrew[ $msgid ] ) || '' === $hebrew[ $msgid ] ) {
		$missing[] = $msgid;
	}
}
if ( $missing ) {
	fwrite( STDERR, "No Hebrew for:\n  " . implode( "\n  ", $missing ) . "\nAdd msgid/msgstr pairs to languages/" . basename( $po_path ) . " and run again.\n" );
	exit( 1 );
}
$stale = array_diff( array_keys( $hebrew ), array_keys( $entries ) );

$version = preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $header, $m ) ? $m[1] : '0';
$head    = function ( $language ) use ( $version ) {
	$lines = array(
		'Project-Id-Version: Digitizer Shabbat Keeper ' . $version,
		'Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/digitizer-shabbat-keeper',
		'MIME-Version: 1.0',
		'Content-Type: text/plain; charset=UTF-8',
		'Content-Transfer-Encoding: 8bit',
		'X-Domain: digitizer-shabbat-keeper',
	);
	if ( $language ) {
		$lines[] = 'Language: ' . $language;
		$lines[] = 'Plural-Forms: nplurals=2; plural=(n != 1);';
	}
	$out = "msgid \"\"\nmsgstr \"\"\n";
	foreach ( $lines as $line ) {
		$out .= '"' . $line . "\\n\"\n";
	}
	return $out;
};

$pot = "# Copyright (C) Digitizer\n# This file is distributed under the GPLv2 or later.\n" . $head( '' );
$po  = "# Hebrew translation of Digitizer Shabbat Keeper.\n# This file is distributed under the GPLv2 or later.\n" . $head( 'he_IL' );
$php = array();
foreach ( $entries as $msgid => $entry ) {
	$block = "\n";
	if ( '' !== $entry['comment'] ) {
		$block .= '#. ' . $entry['comment'] . "\n";
	}
	$block .= '#: ' . implode( ' ', array_unique( $entry['refs'] ) ) . "\n";
	$block .= 'msgid ' . dsk_i18n_po_string( $msgid ) . "\n";
	$pot   .= $block . "msgstr \"\"\n";
	$po    .= $block . 'msgstr ' . dsk_i18n_po_string( $hebrew[ $msgid ] ) . "\n";
	$php[ $msgid ] = $hebrew[ $msgid ];
}

file_put_contents( $dir . '/' . $domain . '.pot', $pot );
file_put_contents( $po_path, $po );
file_put_contents(
	$dir . '/' . $domain . '-he_IL.l10n.php',
	"<?php\nreturn " . var_export(
		array(
			'x-generator'  => 'digitizer-shabbat-keeper bin/build-i18n.php',
			'plural-forms' => 'nplurals=2; plural=(n != 1);',
			'language'     => 'he_IL',
			'messages'     => $php,
		),
		true
	) . ";\n"
);

printf( "%d strings, %d translated, %d dropped from the old catalog\n", count( $entries ), count( $php ), count( $stale ) );
