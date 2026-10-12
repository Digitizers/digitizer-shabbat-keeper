<?php
require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-hebrew-calendar.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-sun.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-cities.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-zmanim.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-enforce.php';

/* The slice of WordPress this class touches and the harness does not stub. */
if ( ! function_exists( 'add_option' ) ) { function add_option( $k, $v ) { return update_option( $k, $v ); } }
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $tag ) { $args = func_get_args(); $GLOBALS['dsk_stub_actions_fired'][] = $args; call_user_func_array( 'apply_filters', array_merge( array( $tag, null ), array_slice( $args, 1 ) ) ); }
}
$GLOBALS['dsk_stub_actions_fired'] = array();
function wp_doing_ajax() { return (bool) $GLOBALS['dsk_stub_doing_ajax']; }
$GLOBALS['dsk_stub_doing_ajax'] = false;
function status_header( $code ) { $GLOBALS['dsk_stub_status'] = (int) $code; }
function nocache_headers() { $GLOBALS['dsk_stub_nocache'] = true; }
function wp_date( $format, $ts, $tz = null ) { $d = new DateTime( '@' . $ts ); $d->setTimezone( $tz ? $tz : new DateTimeZone( 'Asia/Jerusalem' ) ); return $d->format( $format ); }
function is_rtl() { return true; }
function get_site_icon_url( $size = 512 ) { return ''; }
function get_bloginfo( $show = '' ) { return 'Example Site'; }
function language_attributes() { echo 'lang="he-IL" dir="rtl"'; }
function esc_html_e( $t, $d = null ) { echo esc_html( $t ); }
function esc_attr_e( $t, $d = null ) { echo esc_attr( $t ); }
function wp_next_scheduled( $hook ) { return isset( $GLOBALS['dsk_stub_cron'][ $hook ] ) ? $GLOBALS['dsk_stub_cron'][ $hook ] : false; }
function wp_schedule_single_event( $ts, $hook ) { $GLOBALS['dsk_stub_cron'][ $hook ] = $ts; return true; }
$GLOBALS['dsk_stub_cron'] = array();
$GLOBALS['dsk_stub_is_admin'] = false;
if ( ! function_exists( "__return_false" ) ) { function __return_false() { return false; } }

$tz = new DateTimeZone( 'Asia/Jerusalem' );
$at = function ( $local ) use ( $tz ) { return ( new DateTime( $local, $tz ) )->getTimestamp(); };
$clock = $at( '2026-09-02 12:00' ); // Wednesday.
add_filter( 'digitizer_shabbat_keeper_now', function () use ( &$clock ) { return $clock; } );

function sk_set( $pairs ) {
	Digitizer_Shabbat_Keeper_Settings::save( $pairs );
	Digitizer_Shabbat_Keeper_Enforce::reset();
}
function sk_anon() { $GLOBALS['dsk_stub_no_user'] = true; $GLOBALS['dsk_stub_denied_caps'] = array(); unset( $_GET['digitizer_shabbat'] ); Digitizer_Shabbat_Keeper_Enforce::reset(); }
function sk_admin() { $GLOBALS['dsk_stub_no_user'] = false; $GLOBALS['dsk_stub_denied_caps'] = array(); unset( $_GET['digitizer_shabbat'] ); Digitizer_Shabbat_Keeper_Enforce::reset(); }
function sk_editor() { $GLOBALS['dsk_stub_no_user'] = false; $GLOBALS['dsk_stub_denied_caps'] = array( 'manage_options' ); unset( $_GET['digitizer_shabbat'] ); Digitizer_Shabbat_Keeper_Enforce::reset(); }

Digitizer_Shabbat_Keeper_Settings::install_defaults();
$z = Digitizer_Shabbat_Keeper_Enforce::zmanim();
$shabbat = $z->windows( $at( '2026-09-04 00:00' ), $at( '2026-09-06 00:00' ) )[0];

/* ---- Wednesday, anonymous: open ---- */
sk_anon();
dsk_test_ok( ! Digitizer_Shabbat_Keeper_Enforce::is_closed(), 'Wednesday is open' );
dsk_test_ok( ! Digitizer_Shabbat_Keeper_Enforce::should_block(), 'nothing to block on Wednesday' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Enforce::banner_html(), '', 'no banner when open' );
$clock = $shabbat['start'] - 600;
$clock = $at( '2026-09-02 12:00' );

sk_anon();
$GLOBALS['dsk_stub_no_user'] = false;
$GLOBALS['dsk_stub_no_user'] = true;
$GLOBALS['dsk_stub_is_admin'] = true;
$GLOBALS['dsk_stub_is_admin'] = false;

/* ---- Shabbat noon, business mode, anonymous ---- */
$clock = $at( '2026-09-05 12:00' );
sk_anon();
dsk_test_ok( Digitizer_Shabbat_Keeper_Enforce::is_closed(), 'Shabbat noon is closed' );
dsk_test_ok( Digitizer_Shabbat_Keeper_Enforce::applies(), 'applies to a visitor' );
dsk_test_ok( ! Digitizer_Shabbat_Keeper_Enforce::should_block(), 'business mode does not block the page' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Enforce::reopens_at(), $shabbat['end'], 'reopens at Havdalah' );
$banner = Digitizer_Shabbat_Keeper_Enforce::banner_html();
dsk_test_ok( false !== strpos( $banner, 'digitizer-sk-banner' ), 'banner markup' );
dsk_test_ok( false !== strpos( $banner, wp_date( 'H:i', $shabbat['end'] ) ), 'banner names the reopening time' );
sk_set( array( 'banner_text' => '10% off when we reopen at %s' ) );
sk_anon();
$pct_banner = Digitizer_Shabbat_Keeper_Enforce::banner_html();
dsk_test_ok( false !== strpos( $pct_banner, '10% off when we reopen at ' ) && false !== strpos( $pct_banner, wp_date( 'H:i', $shabbat['end'] ) ), 'a literal % in banner_text does not fatal sprintf, and %s still gets the reopening time' );
sk_set( array( 'banner_text' => '' ) );
sk_anon();
ob_start(); Digitizer_Shabbat_Keeper_Enforce::print_banner(); Digitizer_Shabbat_Keeper_Enforce::print_banner_fallback(); $out = ob_get_clean();
dsk_test_eq( substr_count( $out, 'digitizer-sk-banner' ), 1, 'banner printed once even with the fallback' );

/* ---- banner off ---- */
sk_set( array( 'banner_on' => '0' ) );
sk_anon();
dsk_test_eq( Digitizer_Shabbat_Keeper_Enforce::banner_html(), '', 'banner switched off' );
ob_start(); Digitizer_Shabbat_Keeper_Enforce::print_head_css(); $head_no_banner = ob_get_clean();
dsk_test_ok( false !== strpos( $head_no_banner, 'digitizer-sk-card' ), 'closed-screen styles still print in business mode with the banner off' );
sk_set( array( 'banner_on' => '1' ) );

/* ---- contact CSS ---- */
sk_anon();
ob_start(); Digitizer_Shabbat_Keeper_Enforce::print_head_css(); $head = ob_get_clean();
dsk_test_ok( false === strpos( $head, 'tel:' ), 'contact links untouched by default' );
sk_set( array( 'hide_contact' => '1' ) );
sk_anon();
ob_start(); Digitizer_Shabbat_Keeper_Enforce::print_head_css(); $head = ob_get_clean();
dsk_test_ok( false !== strpos( $head, 'a[href^="tel:"]' ), 'contact links hidden when asked' );
sk_set( array( 'hide_contact' => '0' ) );

/* ---- Shabbat, closed mode, anonymous: 503 ---- */
sk_set( array( 'mode' => 'closed' ) );
sk_anon();
dsk_test_ok( Digitizer_Shabbat_Keeper_Enforce::should_block(), 'closed mode blocks the page' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Enforce::closed_headers(), array( 'Retry-After: ' . ( $shabbat['end'] - $clock ) ), 'Retry-After until Havdalah' );
$html = Digitizer_Shabbat_Keeper_Enforce::render_closed_screen();
dsk_test_ok( false !== strpos( $html, 'The site is closed for Shabbat' ), 'closed screen carries the title' );
dsk_test_ok( false !== strpos( $html, 'dir="rtl"' ), 'closed screen is RTL on an RTL site' );
dsk_test_ok( false !== strpos( $html, 'Jerusalem' ), 'closed screen names the city' );
dsk_test_ok( false !== strpos( $html, '<style' ), 'styles inlined' );
dsk_test_ok( false !== strpos( $html, 'digitizer-sk-transition' ) && false !== strpos( $html, 'var t=' . $shabbat['end'] . ',' ), 'closed screen carries the reopening timer (Codex round-12 P2)' );

/* ---- exemptions ---- */
sk_admin();
dsk_test_ok( ! Digitizer_Shabbat_Keeper_Enforce::applies(), 'administrator is exempt' );
dsk_test_ok( ! Digitizer_Shabbat_Keeper_Enforce::should_block(), 'administrator not blocked' );
sk_editor();
dsk_test_ok( Digitizer_Shabbat_Keeper_Enforce::applies(), 'editor is not exempt' );
sk_admin();
add_filter( 'digitizer_shabbat_keeper_exempt', '__return_false' );
dsk_test_ok( Digitizer_Shabbat_Keeper_Enforce::applies(), 'filter can revoke the exemption' );
remove_filter( 'digitizer_shabbat_keeper_exempt', '__return_false' );

/* ---- requests never touched ---- */
sk_anon();
$GLOBALS['dsk_stub_is_admin'] = true;
dsk_test_ok( ! Digitizer_Shabbat_Keeper_Enforce::should_block(), 'wp-admin never blocked' );
$GLOBALS['dsk_stub_is_admin'] = false;
$GLOBALS['dsk_stub_doing_ajax'] = true;
dsk_test_ok( ! Digitizer_Shabbat_Keeper_Enforce::should_block(), 'ajax never blocked' );
$GLOBALS['dsk_stub_doing_ajax'] = false;
$GLOBALS['pagenow'] = 'wp-login.php';
dsk_test_ok( ! Digitizer_Shabbat_Keeper_Enforce::should_block(), 'login never blocked' );
unset( $GLOBALS['pagenow'] );
dsk_test_ok( Digitizer_Shabbat_Keeper_Enforce::should_block(), 'plain front request blocked again' );

/* ---- preview and overrides ---- */
$clock = $at( '2026-09-02 12:00' );
sk_admin();
$_GET['digitizer_shabbat'] = 'preview';
Digitizer_Shabbat_Keeper_Enforce::reset();
dsk_test_ok( Digitizer_Shabbat_Keeper_Enforce::is_preview(), 'administrator preview recognised' );
dsk_test_ok( Digitizer_Shabbat_Keeper_Enforce::applies(), 'preview overrides the exemption' );
dsk_test_ok( Digitizer_Shabbat_Keeper_Enforce::should_block(), 'preview shows the closed site on a Wednesday' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Enforce::reopens_at(), null, 'no reopening time outside a real window' );
dsk_test_ok( false !== strpos( Digitizer_Shabbat_Keeper_Enforce::render_closed_screen(), 'after Havdalah' ), 'closed screen says after Havdalah when there is no window' );
sk_editor();
$_GET['digitizer_shabbat'] = 'preview';
Digitizer_Shabbat_Keeper_Enforce::reset();
dsk_test_ok( ! Digitizer_Shabbat_Keeper_Enforce::is_preview(), 'preview is for administrators only' );
sk_anon();
sk_set( array( 'override' => 'force_closed' ) );
dsk_test_ok( Digitizer_Shabbat_Keeper_Enforce::is_closed(), 'force_closed closes a Wednesday' );
$clock = $at( '2026-09-05 12:00' );
sk_set( array( 'override' => 'force_open' ) );
dsk_test_ok( ! Digitizer_Shabbat_Keeper_Enforce::is_closed(), 'force_open opens Shabbat' );
sk_set( array( 'override' => 'force_closed', 'mode' => 'business' ) );
sk_anon();
dsk_test_eq( Digitizer_Shabbat_Keeper_Enforce::reopens_at(), null, 'force_closed during a real window promises no calendar reopening (Codex round-6 P2)' );
dsk_test_ok( false !== strpos( Digitizer_Shabbat_Keeper_Enforce::banner_html(), 'when the closure is lifted' ), 'banner promises no calendar time while force-closed (Codex round-7 P2)' );
sk_set( array( 'override' => 'force_closed', 'mode' => 'closed' ) );
sk_anon();
$html = Digitizer_Shabbat_Keeper_Enforce::render_closed_screen();
dsk_test_ok( false === strpos( $html, 'Havdalah' ), 'force-closed closed screen never mentions Havdalah, default message included (Codex round-8 P2)' );
dsk_test_ok( false !== strpos( $html, 'when the closure is lifted' ), 'force-closed closed screen says when the closure is lifted' );
dsk_test_ok( false === strpos( $html, 'digitizer-sk-transition' ), 'no timer on the force-closed screen' );
dsk_test_ok( false === strpos( Digitizer_Shabbat_Keeper_Enforce::banner_html(), 'Havdalah' ), 'force-closed banner does not mention Havdalah' );
sk_set( array( 'override' => 'auto', 'mode' => 'business' ) );

/* ---- browser-side transition fallback (Codex round-9 P2) ---- */
$clock = $at( '2026-09-02 12:00' );
sk_set( array( 'override' => 'auto', 'mode' => 'business' ) );
sk_anon();
ob_start(); Digitizer_Shabbat_Keeper_Enforce::print_transition_script(); $js = ob_get_clean();
dsk_test_ok( false !== strpos( $js, 'var t=' . $shabbat['start'] . ',' ), 'script carries the next transition timestamp' );
dsk_test_ok( false !== strpos( $js, 'digitizer_sk' ), 'script reloads with the cache-busting query' );
dsk_test_ok( false !== strpos( $js, 'setTimeout(go,Math.min(' ) && false === strpos( $js, '86400' ), 'timer is set however far the transition is, capped inside setTimeout range (Codex round-10 P2)' );
sk_set( array( 'override' => 'force_closed' ) );
sk_anon();
ob_start(); Digitizer_Shabbat_Keeper_Enforce::print_transition_script(); $js = ob_get_clean();
dsk_test_eq( $js, '', 'no script under a manual override' );
sk_set( array( 'override' => 'auto' ) );
$GLOBALS['dsk_stub_is_admin'] = true;
ob_start(); Digitizer_Shabbat_Keeper_Enforce::print_transition_script(); $js = ob_get_clean();
dsk_test_eq( $js, '', 'no script in wp-admin' );
$GLOBALS['dsk_stub_is_admin'] = false;

/* ---- transition cron ---- */
$clock = $at( '2026-09-02 12:00' );
sk_anon();
Digitizer_Shabbat_Keeper_Enforce::ensure_transition_event();
dsk_test_eq( $GLOBALS['dsk_stub_cron'][ Digitizer_Shabbat_Keeper_Enforce::CRON_HOOK ], $shabbat['start'] + 30, 'purge scheduled just after candle lighting' );
Digitizer_Shabbat_Keeper_Enforce::ensure_transition_event();
dsk_test_eq( count( $GLOBALS['dsk_stub_cron'] ), 1, 'not scheduled twice' );
$clock = $shabbat['start'] + 30;
$GLOBALS['dsk_stub_cron'] = array();
$GLOBALS['dsk_stub_actions_fired'] = array();
Digitizer_Shabbat_Keeper_Enforce::on_transition();
$fired = array_filter( $GLOBALS['dsk_stub_actions_fired'], function ( $a ) { return 'digitizer_shabbat_keeper_transition' === $a[0]; } );
dsk_test_eq( count( $fired ), 1, 'transition action fired' );
dsk_test_eq( array_values( $fired )[0][1], true, 'transition reports now closed' );
dsk_test_eq( $GLOBALS['dsk_stub_cron'][ Digitizer_Shabbat_Keeper_Enforce::CRON_HOOK ], $shabbat['end'] + 30, 'next purge scheduled for Havdalah' );

$GLOBALS['dsk_stub_cron'][ Digitizer_Shabbat_Keeper_Enforce::CRON_HOOK ] = 12345;
Digitizer_Shabbat_Keeper_Enforce::on_transition();
dsk_test_eq( $GLOBALS['dsk_stub_cron'][ Digitizer_Shabbat_Keeper_Enforce::CRON_HOOK ], 12345, 'on_transition respects an existing schedule, nothing double-booked' );

/* ---- schedule_next_transition() only under override auto ---- */
sk_set( array( 'override' => 'force_closed' ) );
sk_anon();
$GLOBALS['dsk_stub_cron'] = array();
Digitizer_Shabbat_Keeper_Enforce::ensure_transition_event();
dsk_test_eq( $GLOBALS['dsk_stub_cron'], array(), 'nothing scheduled under a manual override' );
sk_set( array( 'override' => 'auto' ) );

/* ---- settings save: memo reset, event dropped, caches purged (Codex round-1 P2) ---- */
if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) { function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['dsk_stub_cron'][ $hook ] ); } }
$GLOBALS['dsk_stub_purged'] = 0;
$GLOBALS['dsk_stub_cdn_purged'] = 0;
add_action( 'litespeed_purge_all', function () { $GLOBALS['dsk_stub_purged']++; } );
add_action( 'digitizer_shabbat_keeper_purge', function () { $GLOBALS['dsk_stub_cdn_purged']++; } );
$GLOBALS['dsk_stub_cron'][ Digitizer_Shabbat_Keeper_Enforce::CRON_HOOK ] = 777;
Digitizer_Shabbat_Keeper_Enforce::on_settings_saved();
dsk_test_ok( ! isset( $GLOBALS['dsk_stub_cron'][ Digitizer_Shabbat_Keeper_Enforce::CRON_HOOK ] ), 'saving settings drops the pending transition event' );
dsk_test_eq( $GLOBALS['dsk_stub_purged'], 1, 'saving settings purges page caches' );
dsk_test_eq( $GLOBALS['dsk_stub_cdn_purged'], 1, 'saving settings fires digitizer_shabbat_keeper_purge for CDNs (Codex round-8 P2)' );
$GLOBALS['dsk_stub_cron'] = array();
Digitizer_Shabbat_Keeper_Enforce::on_transition();
dsk_test_eq( $GLOBALS['dsk_stub_cdn_purged'], 2, 'a transition fires digitizer_shabbat_keeper_purge too' );

/* ---- the closed-site guard runs before Content Control's priority-1 exit (Codex round-1 P2) ---- */
Digitizer_Shabbat_Keeper_Enforce::register();
dsk_test_eq( dsk_stub_filter_priority( 'template_redirect', array( 'Digitizer_Shabbat_Keeper_Enforce', 'maybe_block' ) ), 0, 'closed-site guard registered at template_redirect priority 0' );

exit( dsk_test_summary() );
