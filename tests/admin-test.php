<?php
require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-hebrew-calendar.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-sun.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-cities.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-zmanim.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-enforce.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-admin.php';

Digitizer_Shabbat_Keeper_Settings::install_defaults();
$GLOBALS['dsk_stub_is_admin'] = true;

/* ---- the notice until the settings have been saved once ---- */
$html = Digitizer_Shabbat_Keeper_Admin::setup_notice_html( 'plugins' );
dsk_test_ok( false !== strpos( $html, 'Jerusalem' ), 'the notice names the city in force' );
dsk_test_ok( false !== strpos( $html, 'purchases and forms are refused' ), 'and what the default mode does' );
dsk_test_ok( false !== strpos( $html, 'options-general.php?page=digitizer-shabbat-keeper' ), 'and links to the settings screen' );
dsk_test_ok( '' !== Digitizer_Shabbat_Keeper_Admin::setup_notice_html( 'dashboard' ), 'shown on the Dashboard too' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Admin::setup_notice_html( 'edit-post' ), '', 'and nowhere else' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Admin::setup_notice_html( '' ), '', 'not without a screen' );

$GLOBALS['dsk_stub_denied_caps'] = array( 'manage_options' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Admin::setup_notice_html( 'plugins' ), '', 'not for someone who cannot change the settings' );
$GLOBALS['dsk_stub_denied_caps'] = array();

Digitizer_Shabbat_Keeper_Settings::save( array( 'mode' => 'closed', 'city' => 'haifa' ) );
dsk_test_eq( Digitizer_Shabbat_Keeper_Admin::setup_notice_html( 'plugins' ), '', 'gone once the settings have been saved' );

/* ---- the notice describes closed mode when that is the mode ---- */
$GLOBALS['dsk_stub_options'] = array();
Digitizer_Shabbat_Keeper_Settings::install_defaults();
$o         = get_option( Digitizer_Shabbat_Keeper_Settings::OPTION );
$o['mode'] = 'closed';
update_option( Digitizer_Shabbat_Keeper_Settings::OPTION, $o );
dsk_test_ok( false !== strpos( Digitizer_Shabbat_Keeper_Admin::setup_notice_html( 'plugins' ), 'the whole site closes' ), 'closed mode is described as closed' );

/* ---- the plugin-row link ---- */
$admin = new Digitizer_Shabbat_Keeper_Admin();
$links = $admin->action_links( array( 'deactivate' => '<a>Deactivate</a>' ) );
dsk_test_eq( count( $links ), 2, 'one link added, none removed' );
dsk_test_ok( false !== strpos( end( $links ), 'options-general.php?page=digitizer-shabbat-keeper' ), 'it points at the settings screen' );
dsk_test_eq( count( $admin->action_links( null ) ), 1, 'a filter that hands over something else still gets a list back' );
dsk_test_ok( dsk_stub_has_filter( 'plugin_action_links_digitizer-shabbat-keeper/digitizer-shabbat-keeper.php' ), 'hooked on this plugin\'s row only' );
dsk_test_ok( dsk_stub_has_filter( 'admin_post_digitizer_sk_save' ), 'the save handler is registered' );

/* ---- every reason the windows engine can emit has a name ---- */
$keys = array_merge( array( 'shabbat' ), array_values( Digitizer_Shabbat_Keeper_Hebrew_Calendar::HOLIDAYS ) );
foreach ( $keys as $key ) {
	dsk_test_ok( Digitizer_Shabbat_Keeper_Admin::reason_label( $key ) !== $key, "reason '$key' has a label" );
}
dsk_test_eq( Digitizer_Shabbat_Keeper_Admin::reason_label( 'shabbat+rosh_hashana_1' ), 'Shabbat + Rosh Hashana I', 'merged reasons are joined' );

exit( dsk_test_summary() );
