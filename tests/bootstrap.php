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
function wp_doing_ajax() { return (bool) $GLOBALS['dsk_stub_doing_ajax']; }
$GLOBALS['dsk_stub_doing_ajax'] = false;

/* ---- responses ---- */

function status_header( $code ) { $GLOBALS['dsk_stub_status'] = (int) $code; }
function nocache_headers() { $GLOBALS['dsk_stub_nocache'] = true; }
// Dies the way a test can catch.
function wp_die( $message = '', $title = '', $args = array() ) { throw new Exception( (string) $message ); }

/* ---- site ---- */

$GLOBALS['dsk_stub_wp_version'] = '6.9';
$GLOBALS['dsk_stub_multisite']  = false;
$GLOBALS['dsk_stub_sites']      = array();
$GLOBALS['dsk_stub_blog_stack'] = array();
function get_bloginfo( $show = '' ) { return 'version' === $show ? $GLOBALS['dsk_stub_wp_version'] : 'Example Site'; }
function get_site_icon_url( $size = 512 ) { return ''; }
function is_rtl() { return true; }
function language_attributes() { echo 'lang="he-IL" dir="rtl"'; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' ); }
function plugin_basename( $file ) { return 'digitizer-shabbat-keeper/' . basename( (string) $file ); }
function is_multisite() { return (bool) $GLOBALS['dsk_stub_multisite']; }
function get_current_blog_id() { return isset( $GLOBALS['dsk_stub_current_blog'] ) ? (int) $GLOBALS['dsk_stub_current_blog'] : 1; }
function get_sites( $args = array() ) {
	// Core stops at 100 sites unless number is 0; a caller that forgets is
	// handed a short list here too.
	$all = $GLOBALS['dsk_stub_sites'];
	return ( isset( $args['number'] ) && 0 === $args['number'] ) ? $all : array_slice( $all, 0, 100 );
}
// Options are per site: switching swaps the store the option stubs read.
$GLOBALS['dsk_stub_site_options'] = array();
function switch_to_blog( $id ) {
	$GLOBALS['dsk_stub_blog_stack'][] = $GLOBALS['dsk_stub_options'];
	$GLOBALS['dsk_stub_current_blog'] = (int) $id;
	$GLOBALS['dsk_stub_options']      = isset( $GLOBALS['dsk_stub_site_options'][ $id ] ) ? $GLOBALS['dsk_stub_site_options'][ $id ] : array();
}
function restore_current_blog() {
	$GLOBALS['dsk_stub_site_options'][ $GLOBALS['dsk_stub_current_blog'] ] = $GLOBALS['dsk_stub_options'];
	$GLOBALS['dsk_stub_options']                                           = array_pop( $GLOBALS['dsk_stub_blog_stack'] );
}

/* ---- dates ---- */

function wp_date( $format, $ts, $tz = null ) {
	$d = new DateTime( '@' . $ts );
	$d->setTimezone( $tz ? $tz : new DateTimeZone( 'Asia/Jerusalem' ) );
	return $d->format( $format );
}

/* ---- output helpers ---- */

function esc_html_e( $t, $d = null ) { echo esc_html( $t ); }
function esc_attr_e( $t, $d = null ) { echo esc_attr( $t ); }
// Enough of kses for a string with one allowed element: every other tag goes.
function wp_kses( $s, $allowed ) {
	$names = implode( '|', array_map( 'preg_quote', array_keys( (array) $allowed ) ) );
	return preg_replace( '#</?(?!(?:' . $names . ')\b)[a-z0-9]+\b[^>]*>#i', '', (string) $s );
}
// Core's signature and shape: a script element with the attributes given.
function wp_get_inline_script_tag( $js, $attributes = array() ) {
	$attrs = '';
	foreach ( (array) $attributes as $k => $v ) {
		$attrs .= ' ' . $k . '="' . esc_attr( $v ) . '"';
	}
	return '<script' . $attrs . ">\n" . $js . "\n</script>\n";
}
function wp_print_inline_script_tag( $js, $attributes = array() ) { echo wp_get_inline_script_tag( $js, $attributes ); }

/* ---- styles ---- */

$GLOBALS['dsk_stub_styles']        = array();
$GLOBALS['dsk_stub_inline_styles'] = array();
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) {
	$GLOBALS['dsk_stub_styles'][ $handle ] = array( 'src' => $src, 'ver' => $ver );
}
// Prints what core would for one enqueued handle: a link to its source.
function wp_print_styles( $handles = false ) {
	foreach ( (array) $handles as $handle ) {
		if ( isset( $GLOBALS['dsk_stub_styles'][ $handle ] ) ) {
			$s = $GLOBALS['dsk_stub_styles'][ $handle ];
			echo "<link rel='stylesheet' id='" . $handle . "-css' href='" . $s['src'] . '?ver=' . $s['ver'] . "' media='all' />\n";
		}
	}
}
function wp_add_inline_style( $handle, $css ) {
	$GLOBALS['dsk_stub_inline_styles'][ $handle ][] = $css;
	return true;
}

/* ---- urls and nonces ---- */

function add_query_arg( $key, $value = null, $url = '' ) {
	$pairs = is_array( $key ) ? $key : array( $key => $value );
	$url   = is_array( $key ) ? (string) $value : (string) $url;
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $pairs );
}
// A nonce here is the action behind a fixed prefix: enough to tell the right
// one from a wrong one and from none.
function wp_create_nonce( $action ) { return 'nonce-' . $action; }
function wp_verify_nonce( $nonce, $action ) { return ( 'nonce-' . $action === $nonce ) ? 1 : false; }
function wp_nonce_url( $url, $action ) { return add_query_arg( '_wpnonce', wp_create_nonce( $action ), $url ); }

/* ---- cron ---- */

$GLOBALS['dsk_stub_cron'] = array();
function wp_next_scheduled( $hook ) { return isset( $GLOBALS['dsk_stub_cron'][ $hook ] ) ? $GLOBALS['dsk_stub_cron'][ $hook ] : false; }
function wp_schedule_single_event( $ts, $hook ) {
	$GLOBALS['dsk_stub_cron'][ $hook ] = $ts;
	return true;
}
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['dsk_stub_cron'][ $hook ] ); }
