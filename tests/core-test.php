<?php
require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-hebrew-calendar.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-sun.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-cities.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-zmanim.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-enforce.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-integrations.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-core.php';

$GLOBALS['dsk_stub_textdomains'] = array();
function load_plugin_textdomain( $domain, $deprecated = false, $rel_path = false ) {
	$GLOBALS['dsk_stub_textdomains'][ $domain ] = $rel_path;
	return true;
}
$GLOBALS['dsk_stub_purges'] = 0;
add_action( 'digitizer_shabbat_keeper_purge', function () { $GLOBALS['dsk_stub_purges']++; } );

$option = Digitizer_Shabbat_Keeper_Settings::OPTION;

/* ---- activation on a clean site ---- */
Digitizer_Shabbat_Keeper_Core::activate();
dsk_test_eq( get_option( $option ), Digitizer_Shabbat_Keeper_Settings::defaults(), 'activation seeds the defaults' );
dsk_test_eq( $GLOBALS['dsk_stub_purges'], 1, 'activation purges page caches: every page is about to change' );

/* ---- activation again changes nothing ---- */
Digitizer_Shabbat_Keeper_Settings::save( array( 'city' => 'haifa' ) );
Digitizer_Shabbat_Keeper_Core::activate();
dsk_test_eq( Digitizer_Shabbat_Keeper_Settings::get( 'city' ), 'haifa', 're-activating keeps what the site chose' );

/* ---- a site arriving from Digitizer Pro Tools keeps its settings ---- */
$GLOBALS['dsk_stub_options'] = array(
	Digitizer_Shabbat_Keeper_Core::DPT_OPTION => array(
		'mode'        => 'closed',
		'city'        => 'tel_aviv',
		'havdalah'    => '72',
		'block_woo'   => '0',
		'banner_text' => 'Back %s',
		'credit'      => '1',
		'mystery'     => 'x',
	),
);
dsk_test_ok( Digitizer_Shabbat_Keeper_Core::import_from_dpt(), 'import reports that it imported' );
$o = get_option( $option );
dsk_test_eq( array( $o['mode'], $o['city'], $o['havdalah'], $o['block_woo'], $o['banner_text'] ), array( 'closed', 'tel_aviv', '72', '0', 'Back %s' ), 'the module\'s choices come across' );
dsk_test_ok( ! isset( $o['mystery'] ), 'through the whitelist: an unknown key does not' );
dsk_test_eq( $o['saved'], '1', 'imported settings were already somebody\'s decision' );
dsk_test_eq( count( $o ), count( Digitizer_Shabbat_Keeper_Settings::defaults() ), 'and the result is a complete option' );
dsk_test_ok( is_array( get_option( Digitizer_Shabbat_Keeper_Core::DPT_OPTION ) ), 'the module\'s own option is left alone' );

/* ---- import never overwrites this plugin's own settings ---- */
Digitizer_Shabbat_Keeper_Settings::save( array( 'city' => 'eilat' ) );
dsk_test_ok( ! Digitizer_Shabbat_Keeper_Core::import_from_dpt(), 'no import once this plugin has settings' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Settings::get( 'city' ), 'eilat', 'and they are untouched' );

/* ---- nothing to import, nothing imported ---- */
$GLOBALS['dsk_stub_options'] = array();
dsk_test_ok( ! Digitizer_Shabbat_Keeper_Core::import_from_dpt(), 'no module option: no import' );
$GLOBALS['dsk_stub_options'] = array( Digitizer_Shabbat_Keeper_Core::DPT_OPTION => 'not-an-array' );
dsk_test_ok( ! Digitizer_Shabbat_Keeper_Core::import_from_dpt(), 'a malformed module option: no import' );

/* ---- boot: option, hooks, and the catalog on older cores ---- */
$GLOBALS['dsk_stub_options'] = array();
$GLOBALS['dsk_stub_filters'] = array();
Digitizer_Shabbat_Keeper_Core::boot();
dsk_test_ok( is_array( get_option( $option ) ), 'boot seeds the option on a site that never ran the activation hook' );
dsk_test_eq( dsk_stub_filter_priority( 'template_redirect', array( 'Digitizer_Shabbat_Keeper_Enforce', 'maybe_block' ) ), 0, 'boot registers enforcement' );
dsk_test_eq( dsk_stub_filter_priority( 'init', array( 'Digitizer_Shabbat_Keeper_Integrations', 'hook_plugins' ) ), 20, 'and the integrations' );
$GLOBALS['dsk_stub_wp_version'] = '6.9';
Digitizer_Shabbat_Keeper_Core::load_textdomain();
dsk_test_eq( $GLOBALS['dsk_stub_textdomains'], array(), 'WordPress 6.8+ finds the bundled catalog from the header on its own' );
$GLOBALS['dsk_stub_wp_version'] = '6.7.2';
Digitizer_Shabbat_Keeper_Core::load_textdomain();
dsk_test_eq( $GLOBALS['dsk_stub_textdomains'], array( 'digitizer-shabbat-keeper' => 'digitizer-shabbat-keeper/languages' ), 'earlier versions are told where it is' );

/* ---- deactivation ---- */
$GLOBALS['dsk_stub_cron'][ Digitizer_Shabbat_Keeper_Enforce::CRON_HOOK ] = 999;
$GLOBALS['dsk_stub_purges'] = 0;
add_action( 'digitizer_shabbat_keeper_purge', function () { $GLOBALS['dsk_stub_purges']++; } );
Digitizer_Shabbat_Keeper_Core::deactivate();
dsk_test_ok( ! isset( $GLOBALS['dsk_stub_cron'][ Digitizer_Shabbat_Keeper_Enforce::CRON_HOOK ] ), 'deactivation drops the pending purge' );
dsk_test_eq( $GLOBALS['dsk_stub_purges'], 1, 'and purges pages cached with a banner or a closed screen' );
dsk_test_ok( is_array( get_option( $option ) ), 'settings survive deactivation' );

/* ---- a network sub-site that never ran the activation hook still gets the import ---- */
$GLOBALS['dsk_stub_options'] = array( Digitizer_Shabbat_Keeper_Core::DPT_OPTION => array( 'mode' => 'closed', 'city' => 'haifa' ) );
$GLOBALS['dsk_stub_filters'] = array();
Digitizer_Shabbat_Keeper_Core::boot();
$o = get_option( $option );
dsk_test_eq( array( $o['mode'], $o['city'], $o['saved'] ), array( 'closed', 'haifa', '1' ), 'boot imports the module\'s settings before it would seed defaults' );

/* ---- network activation and deactivation reach every site ---- */
$GLOBALS['dsk_stub_multisite']    = true;
$GLOBALS['dsk_stub_sites']        = range( 1, 130 );
$GLOBALS['dsk_stub_site_options'] = array(
	7 => array( Digitizer_Shabbat_Keeper_Core::DPT_OPTION => array( 'city' => 'eilat' ) ),
	9 => array( $option => array_merge( Digitizer_Shabbat_Keeper_Settings::defaults(), array( 'city' => 'tiberias' ) ) ),
);
$GLOBALS['dsk_stub_options']      = array( 'main' => 'context' );
$GLOBALS['dsk_stub_filters']      = array();
$GLOBALS['dsk_stub_purged_sites'] = array();
add_action( 'digitizer_shabbat_keeper_purge', function () { $GLOBALS['dsk_stub_purged_sites'][] = $GLOBALS['dsk_stub_current_blog']; } );
Digitizer_Shabbat_Keeper_Core::activate( true );
dsk_test_eq( $GLOBALS['dsk_stub_purged_sites'], range( 1, 130 ), 'network activation visits all 130 sites, not only the first 100' );
dsk_test_eq( $GLOBALS['dsk_stub_site_options'][7][ $option ]['city'], 'eilat', 'a sub-site arriving from Digitizer Pro Tools keeps its city' );
dsk_test_eq( $GLOBALS['dsk_stub_site_options'][9][ $option ]['city'], 'tiberias', 'a sub-site with settings of its own keeps them' );
dsk_test_eq( $GLOBALS['dsk_stub_site_options'][130][ $option ], Digitizer_Shabbat_Keeper_Settings::defaults(), 'and a new one gets the defaults' );
dsk_test_eq( $GLOBALS['dsk_stub_options'], array( 'main' => 'context' ), 'the request ends on the site it started on' );

$GLOBALS['dsk_stub_purged_sites'] = array();
$GLOBALS['dsk_stub_cron'][ Digitizer_Shabbat_Keeper_Enforce::CRON_HOOK ] = 999;
Digitizer_Shabbat_Keeper_Core::deactivate( true );
dsk_test_eq( $GLOBALS['dsk_stub_purged_sites'], range( 1, 130 ), 'network deactivation purges every site\'s cached pages' );
dsk_test_ok( ! isset( $GLOBALS['dsk_stub_cron'][ Digitizer_Shabbat_Keeper_Enforce::CRON_HOOK ] ), 'and drops the pending purge' );

$GLOBALS['dsk_stub_purged_sites'] = array();
$GLOBALS['dsk_stub_current_blog'] = 1;
Digitizer_Shabbat_Keeper_Core::deactivate( false );
dsk_test_eq( count( $GLOBALS['dsk_stub_purged_sites'] ), 1, 'deactivating on one site of a network touches that site only' );
$GLOBALS['dsk_stub_multisite'] = false;
$GLOBALS['dsk_stub_filters']   = array();

/* ---- uninstall: a single site ---- */
define( 'WP_UNINSTALL_PLUGIN', 'digitizer-shabbat-keeper/digitizer-shabbat-keeper.php' );
$GLOBALS['dsk_stub_options'] = array( $option => array( 'mode' => 'closed' ), 'blogname' => 'Example', Digitizer_Shabbat_Keeper_Core::DPT_OPTION => array( 'mode' => 'closed' ) );
$GLOBALS['dsk_stub_cron']    = array( Digitizer_Shabbat_Keeper_Enforce::CRON_HOOK => 5, 'someone_elses_event' => 6 );
require dirname( __DIR__ ) . '/uninstall.php';
dsk_test_ok( false === get_option( $option ), 'uninstall removes the option' );
dsk_test_eq( get_option( 'blogname' ), 'Example', 'and nothing that is not ours' );
dsk_test_ok( is_array( get_option( Digitizer_Shabbat_Keeper_Core::DPT_OPTION ) ), 'Digitizer Pro Tools\' option included' );
dsk_test_eq( $GLOBALS['dsk_stub_cron'], array( 'someone_elses_event' => 6 ), 'uninstall clears our event and only ours' );

/* ---- uninstall: every site of a network, past the default cap of 100 ---- */
$GLOBALS['dsk_stub_multisite']    = true;
$GLOBALS['dsk_stub_sites']        = range( 1, 130 );
$GLOBALS['dsk_stub_site_options'] = array();
foreach ( $GLOBALS['dsk_stub_sites'] as $id ) {
	$GLOBALS['dsk_stub_site_options'][ $id ] = array( $option => array( 'city' => 'haifa' ), 'blogname' => 'Site ' . $id );
}
$GLOBALS['dsk_stub_options'] = array( 'main' => 'context' );
require dirname( __DIR__ ) . '/uninstall.php';
$left = 0;
foreach ( $GLOBALS['dsk_stub_site_options'] as $id => $opts ) {
	if ( isset( $opts[ $option ] ) ) {
		$left++;
	}
}
dsk_test_eq( $left, 0, 'all 130 sites lose the option, not only the first 100' );
dsk_test_eq( $GLOBALS['dsk_stub_site_options'][130]['blogname'], 'Site 130', 'each site keeps everything else' );
dsk_test_eq( $GLOBALS['dsk_stub_options'], array( 'main' => 'context' ), 'and the request ends on the site it started on' );

exit( dsk_test_summary() );
