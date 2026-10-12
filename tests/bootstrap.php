<?php
/**
 * Shared harness for the Digitizer Shabbat Keeper tests.
 *
 * There is no WordPress in this environment, so the harness defines the small
 * slice of WordPress the plugin touches and each test requires the real class
 * files. Stub state lives in globals so a test can rearrange the "site"
 * between assertions.
 *
 * Run one:  php tests/zmanim-test.php
 * Run all:  for f in tests/*-test.php; do php "$f" || exit 1; done
 */

define( 'ABSPATH', '/tmp/' );
define( 'DIGITIZER_SHABBAT_KEEPER_VERSION', '0.0.0-test' );
define( 'DIGITIZER_SHABBAT_KEEPER_FILE', dirname( __DIR__ ) . '/digitizer-shabbat-keeper.php' );
define( 'DIGITIZER_SHABBAT_KEEPER_PATH', dirname( __DIR__ ) . '/' );
define( 'DIGITIZER_SHABBAT_KEEPER_URL', 'https://example.test/wp-content/plugins/digitizer-shabbat-keeper/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['dsk_test_pass']          = 0;
$GLOBALS['dsk_test_fail']          = 0;
$GLOBALS['dsk_stub_options']       = array();
$GLOBALS['dsk_stub_filters']       = array();
$GLOBALS['dsk_stub_filter_seq']    = 0;
$GLOBALS['dsk_stub_actions_fired'] = array();
$GLOBALS['dsk_stub_no_user']       = false;
$GLOBALS['dsk_stub_denied_caps']   = array();
$GLOBALS['dsk_stub_is_admin']      = false;
$GLOBALS['dsk_stub_doing_cron']    = false;

function dsk_test_ok( $cond, $label ) {
	if ( $cond ) {
		$GLOBALS['dsk_test_pass']++;
		return;
	}
	$GLOBALS['dsk_test_fail']++;
	echo "FAIL: $label\n";
}

function dsk_test_eq( $actual, $expected, $label ) {
	if ( $actual === $expected ) {
		$GLOBALS['dsk_test_pass']++;
		return;
	}
	$GLOBALS['dsk_test_fail']++;
	echo "FAIL: $label\n";
	echo '  expected: ' . var_export( $expected, true ) . "\n";
	echo '  actual:   ' . var_export( $actual, true ) . "\n";
}

function dsk_test_summary() {
	printf( "\n%d passed, %d failed\n", $GLOBALS['dsk_test_pass'], $GLOBALS['dsk_test_fail'] );
	return $GLOBALS['dsk_test_fail'];
}

/* ---- errors ---- */

class WP_Error {
	private $errors = array();
	public function __construct( $code = '', $message = '', $data = '' ) {
		if ( '' !== $code ) {
			$this->errors[ $code ][] = $message;
		}
	}
	public function add( $code, $message, $data = '' ) {
		$this->errors[ $code ][] = $message;
	}
	public function get_error_codes() {
		return array_keys( $this->errors );
	}
}
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

/* ---- text ---- */

function __( $text, $domain = null ) { return $text; }
function esc_html__( $text, $domain = null ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr__( $text, $domain = null ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return (string) $s; }

// Core hands its argument straight to strtolower(); PHP 8 makes that a
// TypeError for an array, and a stub that cast first would hide the fatal.
function sanitize_key( $s ) {
	if ( is_array( $s ) || is_object( $s ) ) {
		throw new TypeError( 'strtolower(): Argument #1 ($string) must be of type string, ' . gettype( $s ) . ' given' );
	}
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) );
}
function sanitize_text_field( $s ) { return is_scalar( $s ) ? trim( strip_tags( (string) $s ) ) : ''; }
function absint( $v ) { return abs( (int) $v ); }

// stripslashes_deep(), which is all core's wp_unslash() is.
function wp_unslash( $value ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $k => $v ) {
			$value[ $k ] = wp_unslash( $v );
		}
		return $value;
	}
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

// Three of the things core's kses really does: an element whose content it
// drops takes the content with it, an element the post context does not allow
// is removed and its text kept, and an event handler is stripped.
function wp_kses_post( $s ) {
	if ( ! is_scalar( $s ) ) {
		return '';
	}
	$s       = (string) $s;
	$s       = preg_replace( '#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1\s*>#is', '', $s );
	$allowed = 'a|abbr|b|blockquote|br|code|div|em|h1|h2|h3|h4|h5|h6|i|img|li|ol|p|pre|small|span|strong|sub|sup|table|tbody|td|th|thead|tr|u|ul';
	$s       = preg_replace( '#</?(?!(?:' . $allowed . ')\b)[a-z0-9:_-]+\b[^>]*/?>#i', '', $s );
	$s       = preg_replace( '#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)#i', '', $s );
	return $s;
}

/* ---- hooks: actions and filters share one registry, as in WordPress ---- */

function add_filter( $tag = '', $callback = null, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['dsk_stub_filters'][ $tag ][] = array(
		'callback' => $callback,
		'priority' => (int) $priority,
		'order'    => $GLOBALS['dsk_stub_filter_seq']++,
	);
	return true;
}
function add_action( $tag = '', $callback = null, $priority = 10, $accepted_args = 1 ) {
	return add_filter( $tag, $callback, $priority, $accepted_args );
}
// Lower priority first, insertion order within a priority - WordPress's order.
function apply_filters( $tag, $value ) {
	$args = func_get_args();
	array_shift( $args );
	if ( empty( $GLOBALS['dsk_stub_filters'][ $tag ] ) ) {
		return $value;
	}
	$hooks = $GLOBALS['dsk_stub_filters'][ $tag ];
	usort(
		$hooks,
		function ( $a, $b ) {
			if ( $a['priority'] === $b['priority'] ) {
				return $a['order'] - $b['order'];
			}
			return ( $a['priority'] < $b['priority'] ) ? -1 : 1;
		}
	);
	foreach ( $hooks as $hook ) {
		if ( is_callable( $hook['callback'] ) ) {
			$args[0] = call_user_func_array( $hook['callback'], $args );
		}
	}
	return $args[0];
}
// Every action fired is recorded, then run through the shared registry.
function do_action( $tag ) {
	$args                                = func_get_args();
	$GLOBALS['dsk_stub_actions_fired'][] = $args;
	call_user_func_array( 'apply_filters', array_merge( array( $tag, null ), array_slice( $args, 1 ) ) );
}
// Harness shorthand: no callback clears the whole tag.
function remove_filter( $tag = '', $callback = null ) {
	if ( ! isset( $GLOBALS['dsk_stub_filters'][ $tag ] ) ) {
		return;
	}
	if ( null === $callback ) {
		unset( $GLOBALS['dsk_stub_filters'][ $tag ] );
		return;
	}
	foreach ( $GLOBALS['dsk_stub_filters'][ $tag ] as $i => $hook ) {
		if ( $hook['callback'] === $callback ) {
			unset( $GLOBALS['dsk_stub_filters'][ $tag ][ $i ] );
		}
	}
	if ( empty( $GLOBALS['dsk_stub_filters'][ $tag ] ) ) {
		unset( $GLOBALS['dsk_stub_filters'][ $tag ] );
	}
}
function dsk_stub_has_filter( $tag ) {
	return isset( $GLOBALS['dsk_stub_filters'][ $tag ] );
}
// The priority a callback was registered at, or null when it is not hooked.
function dsk_stub_filter_priority( $tag, $callback ) {
	if ( ! isset( $GLOBALS['dsk_stub_filters'][ $tag ] ) ) {
		return null;
	}
	foreach ( $GLOBALS['dsk_stub_filters'][ $tag ] as $hook ) {
		if ( $hook['callback'] === $callback ) {
			return $hook['priority'];
		}
	}
	return null;
}
function __return_false() { return false; }

/* ---- options ---- */

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['dsk_stub_options'] ) ? $GLOBALS['dsk_stub_options'][ $key ] : $default;
}
function update_option( $key, $value ) {
	$GLOBALS['dsk_stub_options'][ $key ] = $value;
	return true;
}
// Core's add_option() refuses a name that already exists.
function add_option( $key, $value ) {
	if ( array_key_exists( $key, $GLOBALS['dsk_stub_options'] ) ) {
		return false;
	}
	$GLOBALS['dsk_stub_options'][ $key ] = $value;
	return true;
}
function delete_option( $key ) {
	unset( $GLOBALS['dsk_stub_options'][ $key ] );
	return true;
}

/* ---- the current request ---- */

function is_user_logged_in() { return ! $GLOBALS['dsk_stub_no_user']; }
function current_user_can( $cap ) {
	if ( $GLOBALS['dsk_stub_no_user'] ) {
		return false;
	}
	return ! in_array( $cap, $GLOBALS['dsk_stub_denied_caps'], true );
}
function is_admin() { return (bool) $GLOBALS['dsk_stub_is_admin']; }
function wp_doing_cron() { return (bool) $GLOBALS['dsk_stub_doing_cron']; }
