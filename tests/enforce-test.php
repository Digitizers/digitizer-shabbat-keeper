<?php
require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-hebrew-calendar.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-sun.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-cities.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-zmanim.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-enforce.php';

$tz = new DateTimeZone( 'Asia/Jerusalem' );
$at = function ( $local ) use ( $tz ) { return ( new DateTime( $local, $tz ) )->getTimestamp(); };
$clock = $at( '2026-09-02 12:00' ); // Wednesday.
add_filter( 'digitizer_shabbat_keeper_now', function () use ( &$clock ) { return $clock; } );

function sk_set( $pairs ) {
	Digitizer_Shabbat_Keeper_Settings::save( $pairs );
	Digitizer_Shabbat_Keeper_Enforce::reset();
}
function sk_anon() { $GLOBALS['dsk_stub_no_user'] = true; $GLOBALS['dsk_stub_denied_caps'] = array(); unset( $_GET['digitizer_shabbat'], $_GET['_wpnonce'] ); Digitizer_Shabbat_Keeper_Enforce::reset(); }
function sk_admin() { $GLOBALS['dsk_stub_no_user'] = false; $GLOBALS['dsk_stub_denied_caps'] = array(); unset( $_GET['digitizer_shabbat'], $_GET['_wpnonce'] ); Digitizer_Shabbat_Keeper_Enforce::reset(); }
function sk_editor() { $GLOBALS['dsk_stub_no_user'] = false; $GLOBALS['dsk_stub_denied_caps'] = array( 'manage_options' ); unset( $_GET['digitizer_shabbat'], $_GET['_wpnonce'] ); Digitizer_Shabbat_Keeper_Enforce::reset(); }

Digitizer_Shabbat_Keeper_Settings::install_defaults();
$z = Digitizer_Shabbat_Keeper_Enforce::zmanim();
$shabbat = $z->windows( $at( '2026-09-04 00:00' ), $at( '2026-09-06 00:00' ) )[0];

/* ---- Wednesday, anonymous: open ---- */
sk_anon();
dsk_test_ok( ! Digitizer_Shabbat_Keeper_Enforce::is_closed(), 'Wednesday is open' );
dsk_test_ok( ! Digitizer_Shabbat_Keeper_Enforce::should_block(), 'nothing to block on Wednesday' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Enforce::banner_html(), '', 'no banner when open' );

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
$GLOBALS['dsk_stub_styles'] = array();
Digitizer_Shabbat_Keeper_Enforce::enqueue_assets();
dsk_test_eq( $GLOBALS['dsk_stub_styles'][ Digitizer_Shabbat_Keeper_Enforce::STYLE_HANDLE ]['src'], DIGITIZER_SHABBAT_KEEPER_URL . 'assets/closed.css', 'the stylesheet is enqueued in business mode even with the banner off: refusals use it' );
dsk_test_eq( $GLOBALS['dsk_stub_styles'][ Digitizer_Shabbat_Keeper_Enforce::STYLE_HANDLE ]['ver'], DIGITIZER_SHABBAT_KEEPER_VERSION, 'the stylesheet carries the plugin version' );
sk_set( array( 'banner_on' => '1' ) );

/* ---- contact CSS ---- */
sk_anon();
$GLOBALS['dsk_stub_inline_styles'] = array();
Digitizer_Shabbat_Keeper_Enforce::enqueue_assets();
dsk_test_eq( $GLOBALS['dsk_stub_inline_styles'], array(), 'contact links untouched by default' );
sk_set( array( 'hide_contact' => '1' ) );
sk_anon();
Digitizer_Shabbat_Keeper_Enforce::enqueue_assets();
dsk_test_ok( false !== strpos( $GLOBALS['dsk_stub_inline_styles'][ Digitizer_Shabbat_Keeper_Enforce::STYLE_HANDLE ][0], 'a[href^="tel:"]' ), 'contact links hidden when asked' );
sk_set( array( 'hide_contact' => '0' ) );

/* ---- nothing is enqueued where business mode does not apply ---- */
$GLOBALS['dsk_stub_styles'] = array();
sk_admin();
Digitizer_Shabbat_Keeper_Enforce::enqueue_assets();
dsk_test_eq( $GLOBALS['dsk_stub_styles'], array(), 'no stylesheet for an exempt administrator' );
sk_anon();
$GLOBALS['dsk_stub_is_admin'] = true;
Digitizer_Shabbat_Keeper_Enforce::enqueue_assets();
dsk_test_eq( $GLOBALS['dsk_stub_styles'], array(), 'no stylesheet in wp-admin' );
$GLOBALS['dsk_stub_is_admin'] = false;

/* ---- Shabbat, closed mode, anonymous: 503 ---- */
sk_set( array( 'mode' => 'closed' ) );
sk_anon();
dsk_test_ok( Digitizer_Shabbat_Keeper_Enforce::should_block(), 'closed mode blocks the page' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Enforce::closed_headers(), array( 'Retry-After: ' . ( $shabbat['end'] - $clock ) ), 'Retry-After until Havdalah' );
$html = Digitizer_Shabbat_Keeper_Enforce::render_closed_screen();
dsk_test_ok( false !== strpos( $html, 'The site is closed for Shabbat' ), 'closed screen carries the title' );
dsk_test_ok( false !== strpos( $html, 'dir="rtl"' ), 'closed screen is RTL on an RTL site' );
dsk_test_ok( false !== strpos( $html, 'Jerusalem' ), 'closed screen names the city' );
dsk_test_ok( false !== strpos( $html, "<link rel='stylesheet' id='digitizer-shabbat-keeper-css' href='" . DIGITIZER_SHABBAT_KEEPER_URL . 'assets/closed.css?ver=' . DIGITIZER_SHABBAT_KEEPER_VERSION ), 'the stylesheet reaches the closed page through the enqueue API' );
dsk_test_ok( false === strpos( $html, '<style' ), 'and no style tag is written by hand' );
dsk_test_ok( false !== strpos( $html, 'id="digitizer-sk-transition"' ) && false !== strpos( $html, 'var t=' . $shabbat['end'] . ',' ), 'closed screen carries the reopening timer' );
dsk_test_ok( false === strpos( $html, 'class="digitizer-sk-credit"' ), 'no credit on the closed screen unless it is switched on' );
sk_set( array( 'credit' => '1' ) );
sk_anon();
$html = Digitizer_Shabbat_Keeper_Enforce::render_closed_screen();
dsk_test_ok( false !== strpos( $html, 'class="digitizer-sk-credit"' ) && false !== strpos( $html, 'href="https://www.digitizer.studio/"' ), 'the credit appears, linked, once switched on' );
sk_set( array( 'credit' => '0' ) );
sk_anon();

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
dsk_test_ok( ! Digitizer_Shabbat_Keeper_Enforce::is_preview(), 'a preview flag without a nonce is not a preview' );
dsk_test_ok( ! Digitizer_Shabbat_Keeper_Enforce::should_block(), 'and it shows an administrator nothing' );
$_GET['_wpnonce'] = 'nonce-something-else';
Digitizer_Shabbat_Keeper_Enforce::reset();
dsk_test_ok( ! Digitizer_Shabbat_Keeper_Enforce::is_preview(), 'nor with the wrong nonce' );
$_GET['_wpnonce'] = wp_create_nonce( Digitizer_Shabbat_Keeper_Enforce::PREVIEW_NONCE );
Digitizer_Shabbat_Keeper_Enforce::reset();
dsk_test_ok( Digitizer_Shabbat_Keeper_Enforce::is_preview(), 'administrator preview recognised' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Enforce::preview_url(), 'https://example.test/?digitizer_shabbat=preview&_wpnonce=nonce-' . Digitizer_Shabbat_Keeper_Enforce::PREVIEW_NONCE, 'the preview link carries the flag and its nonce' );
dsk_test_ok( Digitizer_Shabbat_Keeper_Enforce::applies(), 'preview overrides the exemption' );
dsk_test_ok( Digitizer_Shabbat_Keeper_Enforce::should_block(), 'preview shows the closed site on a Wednesday' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Enforce::reopens_at(), null, 'no reopening time outside a real window' );
dsk_test_ok( false !== strpos( Digitizer_Shabbat_Keeper_Enforce::render_closed_screen(), 'after Havdalah' ), 'closed screen says after Havdalah when there is no window' );
sk_editor();
$_GET['digitizer_shabbat'] = 'preview';
$_GET['_wpnonce']          = wp_create_nonce( Digitizer_Shabbat_Keeper_Enforce::PREVIEW_NONCE );
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
dsk_test_eq( Digitizer_Shabbat_Keeper_Enforce::reopens_at(), null, 'force_closed during a real window promises no calendar reopening' );
dsk_test_ok( false !== strpos( Digitizer_Shabbat_Keeper_Enforce::banner_html(), 'when the closure is lifted' ), 'banner promises no calendar time while force-closed' );
sk_set( array( 'override' => 'force_closed', 'mode' => 'closed' ) );
sk_anon();
$html = Digitizer_Shabbat_Keeper_Enforce::render_closed_screen();
dsk_test_ok( false === strpos( $html, 'Havdalah' ), 'force-closed closed screen never mentions Havdalah, default message included' );
dsk_test_ok( false !== strpos( $html, 'when the closure is lifted' ), 'force-closed closed screen says when the closure is lifted' );
dsk_test_ok( false === strpos( $html, 'digitizer-sk-transition' ), 'no timer on the force-closed screen' );
dsk_test_ok( false === strpos( Digitizer_Shabbat_Keeper_Enforce::banner_html(), 'Havdalah' ), 'force-closed banner does not mention Havdalah' );
sk_set( array( 'override' => 'auto', 'mode' => 'business' ) );

/* ---- browser-side transition fallback ---- */
$clock = $at( '2026-09-02 12:00' );
sk_set( array( 'override' => 'auto', 'mode' => 'business' ) );
sk_anon();
ob_start(); Digitizer_Shabbat_Keeper_Enforce::print_transition_script(); $js = ob_get_clean();
dsk_test_ok( 0 === strpos( $js, '<script id="digitizer-sk-transition">' ), 'the script is printed through the core script-tag helper, with its id' );
dsk_test_ok( false !== strpos( $js, 'var t=' . $shabbat['start'] . ',' ), 'script carries the next transition timestamp' );
dsk_test_ok( false === strpos( Digitizer_Shabbat_Keeper_Enforce::transition_script(), '<script' ), 'transition_script() is bare JavaScript' );
dsk_test_ok( false !== strpos( $js, 'digitizer_sk' ), 'script reloads with the cache-busting query' );
dsk_test_ok( false !== strpos( $js, 'setTimeout(go,Math.min(' ) && false === strpos( $js, '86400' ), 'timer is set however far the transition is, capped inside setTimeout range' );
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

/* ---- settings save: memo reset, event dropped, caches purged ---- */
$GLOBALS['dsk_stub_purged'] = 0;
$GLOBALS['dsk_stub_cdn_purged'] = 0;
add_action( 'litespeed_purge_all', function () { $GLOBALS['dsk_stub_purged']++; } );
add_action( 'digitizer_shabbat_keeper_purge', function () { $GLOBALS['dsk_stub_cdn_purged']++; } );
$GLOBALS['dsk_stub_cron'][ Digitizer_Shabbat_Keeper_Enforce::CRON_HOOK ] = 777;
Digitizer_Shabbat_Keeper_Enforce::on_settings_saved();
dsk_test_ok( ! isset( $GLOBALS['dsk_stub_cron'][ Digitizer_Shabbat_Keeper_Enforce::CRON_HOOK ] ), 'saving settings drops the pending transition event' );
dsk_test_eq( $GLOBALS['dsk_stub_purged'], 1, 'saving settings purges page caches' );
dsk_test_eq( $GLOBALS['dsk_stub_cdn_purged'], 1, 'saving settings fires digitizer_shabbat_keeper_purge for CDNs' );
$GLOBALS['dsk_stub_cron'] = array();
Digitizer_Shabbat_Keeper_Enforce::on_transition();
dsk_test_eq( $GLOBALS['dsk_stub_cdn_purged'], 2, 'a transition fires digitizer_shabbat_keeper_purge too' );

/* ---- the closed-site guard runs before access plugins that exit at priority 1 ---- */
Digitizer_Shabbat_Keeper_Enforce::register();
dsk_test_eq( dsk_stub_filter_priority( 'template_redirect', array( 'Digitizer_Shabbat_Keeper_Enforce', 'maybe_block' ) ), 0, 'closed-site guard registered at template_redirect priority 0' );

exit( dsk_test_summary() );
