<?php
require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-cities.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-settings.php';

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $key, $value ) { return update_option( $key, $value ); }
}

/* ---- defaults ---- */
$d = Digitizer_Shabbat_Keeper_Settings::defaults();
dsk_test_eq( $d['mode'], 'business', 'default mode is business' );
dsk_test_eq( $d['city'], 'jerusalem', 'default city' );
dsk_test_eq( $d['havdalah'], '42', 'default havdalah' );
dsk_test_eq( $d['override'], 'auto', 'default override' );
dsk_test_eq( $d['hide_contact'], '0', 'contact links shown by default' );
dsk_test_eq( $d['block_woo'], '1', 'woo blocked by default' );
dsk_test_eq( count( $d ), 15, 'fifteen keys' );

/* ---- install_defaults seeds, then only fills gaps ---- */
Digitizer_Shabbat_Keeper_Settings::install_defaults();
dsk_test_eq( get_option( Digitizer_Shabbat_Keeper_Settings::OPTION ), $d, 'seeded with defaults' );
update_option( Digitizer_Shabbat_Keeper_Settings::OPTION, array( 'mode' => 'closed', 'stale' => 'x' ) );
Digitizer_Shabbat_Keeper_Settings::install_defaults();
$o = get_option( Digitizer_Shabbat_Keeper_Settings::OPTION );
dsk_test_eq( $o['mode'], 'closed', 'existing value kept' );
dsk_test_eq( $o['city'], 'jerusalem', 'missing key filled' );
dsk_test_ok( ! isset( $o['stale'] ), 'unknown key dropped' );

/* ---- sanitize ---- */
$c = Digitizer_Shabbat_Keeper_Settings::sanitize( array(
	'mode'          => 'banana',
	'city'          => 'atlantis',
	'havdalah'      => '99',
	'override'      => 'maybe',
	'custom_lat'    => '123.4',
	'custom_lon'    => '-200',
	'custom_candle' => '500',
	'block_woo'     => 'yes',
	'banner_on'     => '0',
	'closed_title'  => "  <b>Closed</b>\n",
	'closed_message' => '<p>Hello</p><script>alert(1)</script>',
	'banner_text'   => 'Back at %s',
	'evil'          => '1',
) );
dsk_test_eq( $c['mode'], 'business', 'unknown mode falls back' );
dsk_test_eq( $c['city'], 'jerusalem', 'unknown city falls back' );
dsk_test_eq( $c['havdalah'], '42', 'unknown havdalah falls back' );
dsk_test_eq( $c['override'], 'auto', 'unknown override falls back' );
dsk_test_eq( $c['custom_lat'], '33.4', 'latitude clamped to Israel (Codex round-9 P2)' );
dsk_test_eq( $c['custom_lon'], '34.2', 'longitude clamped to Israel' );
dsk_test_eq( $c['custom_candle'], '60', 'candle minutes clamped' );
dsk_test_eq( $c['block_woo'], '1', 'truthy bool is 1' );
dsk_test_eq( $c['banner_on'], '0', 'zero bool is 0' );
dsk_test_eq( $c['closed_title'], 'Closed', 'title is plain text, trimmed' );
dsk_test_ok( false === strpos( $c['closed_message'], '<script' ), 'script stripped from message' );
dsk_test_ok( false !== strpos( $c['closed_message'], '<p>' ), 'paragraph kept in message' );
dsk_test_eq( $c['banner_text'], 'Back at %s', 'banner keeps its placeholder' );
dsk_test_ok( ! isset( $c['evil'] ), 'unknown key dropped' );

$c = Digitizer_Shabbat_Keeper_Settings::sanitize( array( 'mode' => 'closed', 'city' => 'custom', 'havdalah' => '72', 'override' => 'force_closed', 'custom_lat' => '31.5', 'custom_lon' => '34.9', 'custom_candle' => '22' ) );
dsk_test_eq( $c['mode'], 'closed', 'valid mode kept' );
dsk_test_eq( $c['city'], 'custom', 'custom city kept' );
dsk_test_eq( $c['havdalah'], '72', 'Rabbeinu Tam kept' );
dsk_test_eq( $c['override'], 'force_closed', 'force kept' );
dsk_test_eq( $c['custom_lat'], '31.5', 'latitude kept' );

/* ---- text fallbacks ---- */
Digitizer_Shabbat_Keeper_Settings::save( array( 'closed_title' => '' ) );
dsk_test_eq( Digitizer_Shabbat_Keeper_Settings::text( 'closed_title' ), 'The site is closed for Shabbat', 'empty title falls back to the translated default' );
dsk_test_ok( false !== strpos( Digitizer_Shabbat_Keeper_Settings::text( 'banner_text' ), '%s' ), 'default banner text carries the placeholder' );
Digitizer_Shabbat_Keeper_Settings::save( array( 'closed_title' => 'Shhh' ) );
dsk_test_eq( Digitizer_Shabbat_Keeper_Settings::text( 'closed_title' ), 'Shhh', 'saved title wins' );

/* ---- location ---- */
Digitizer_Shabbat_Keeper_Settings::save( array( 'city' => 'haifa' ) );
dsk_test_eq( Digitizer_Shabbat_Keeper_Settings::location()['candle'], 30, 'city location comes from the table' );
Digitizer_Shabbat_Keeper_Settings::save( array( 'city' => 'custom', 'custom_lat' => '31.5', 'custom_lon' => '34.9', 'custom_candle' => '25' ) );
$loc = Digitizer_Shabbat_Keeper_Settings::location();
dsk_test_eq( array( $loc['lat'], $loc['lon'], $loc['candle'] ), array( 31.5, 34.9, 25 ), 'custom location comes from settings' );

exit( dsk_test_summary() );
