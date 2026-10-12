<?php
require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-hebrew-calendar.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-sun.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-cities.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-zmanim.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-enforce.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-integrations.php';

$GLOBALS['dsk_stub_doing_ajax'] = false;
$GLOBALS['dsk_stub_is_admin'] = false; // A visitor-facing request by default; refusals_apply() also checks is_admin().
$GLOBALS['dsk_stub_notices'] = array();
function wc_add_notice( $msg, $type = 'success' ) { $GLOBALS['dsk_stub_notices'][] = array( $msg, $type ); }

/* Plugins present on this pretend site: WooCommerce, CF7, WPForms, Elementor Pro. Not Gravity. */
class WooCommerce {}
function wpcf7() {}
function wpforms() { return $GLOBALS['dsk_stub_wpforms']; }
$GLOBALS['dsk_stub_wpforms'] = (object) array( 'process' => (object) array( 'errors' => array() ) );
class ElementorPro_Plugin_Stub {}
class_alias( 'ElementorPro_Plugin_Stub', 'ElementorPro\Plugin' );

$tz = new DateTimeZone( 'Asia/Jerusalem' );
$at = function ( $local ) use ( $tz ) { return ( new DateTime( $local, $tz ) )->getTimestamp(); };
$clock = $at( '2026-09-05 12:00' ); // Shabbat noon.
add_filter( 'digitizer_shabbat_keeper_now', function () use ( &$clock ) { return $clock; } );
$GLOBALS['dsk_stub_no_user'] = true;

Digitizer_Shabbat_Keeper_Settings::install_defaults();
Digitizer_Shabbat_Keeper_Enforce::reset();
Digitizer_Shabbat_Keeper_Integrations::hook_plugins();

/* ---- what got hooked ---- */
dsk_test_ok( dsk_stub_has_filter( 'woocommerce_is_purchasable' ), 'WooCommerce purchasable filter hooked' );
dsk_test_ok( dsk_stub_has_filter( 'woocommerce_store_api_cart_errors' ), 'Store API errors hooked' );
dsk_test_ok( dsk_stub_has_filter( 'wpcf7_validate' ), 'CF7 hooked' );
dsk_test_ok( dsk_stub_has_filter( 'wpforms_process_before' ), 'WPForms hooked' );
dsk_test_ok( dsk_stub_has_filter( 'elementor_pro/forms/validation' ), 'Elementor forms hooked' );
dsk_test_ok( ! dsk_stub_has_filter( 'gform_validation' ), 'Gravity Forms absent, not hooked' );
dsk_test_ok( dsk_stub_has_filter( 'do_shortcode_tag' ), 'shortcode markup hooked' );
dsk_test_ok( dsk_stub_has_filter( 'pre_comment_on_post' ), 'comment posts hooked' );

/* ---- closed, visitor ---- */
dsk_test_eq( apply_filters( 'woocommerce_is_purchasable', true, null ), false, 'nothing purchasable on Shabbat' );
dsk_test_eq( apply_filters( 'woocommerce_add_to_cart_validation', true, 7 ), false, 'add to cart refused' );
dsk_test_eq( $GLOBALS['dsk_stub_notices'][0][1], 'error', 'refusal is an error notice' );
$errors = new WP_Error();
apply_filters( 'woocommerce_store_api_cart_errors', $errors, null );
dsk_test_ok( in_array( 'digitizer_shabbat_keeper', $errors->get_error_codes(), true ), 'Store API gets an error' );
dsk_test_ok( dsk_stub_has_filter( 'woocommerce_valid_order_statuses_for_payment' ), 'order-pay statuses filter hooked' );
dsk_test_ok( dsk_stub_has_filter( 'woocommerce_before_pay_action' ), 'order-pay handler hooked' );
dsk_test_eq( apply_filters( 'woocommerce_valid_order_statuses_for_payment', array( 'pending', 'failed' ), null ), array(), 'no order may be paid while closed' );
$thrown = null;
try { do_action_stub( 'woocommerce_before_pay_action', null ); } catch ( Exception $e ) { $thrown = $e->getMessage(); }
dsk_test_ok( is_string( $thrown ) && '' !== $thrown, 'paying an existing order while closed throws the refusal' );
$link = apply_filters( 'woocommerce_loop_add_to_cart_link', '<a class="button">Add</a>', null );
dsk_test_ok( false !== strpos( $link, 'digitizer-sk-closed' ), 'shop grid button replaced' );

$out = apply_filters( 'do_shortcode_tag', '<form>cf7</form>', 'contact-form-7', array(), array() );
dsk_test_ok( false !== strpos( $out, 'digitizer-sk-closed-form' ), 'CF7 shortcode replaced' );
$out = apply_filters( 'do_shortcode_tag', '<form>wpf</form>', 'wpforms', array(), array() );
dsk_test_ok( false !== strpos( $out, 'digitizer-sk-closed-form' ), 'WPForms shortcode replaced' );
$out = apply_filters( 'do_shortcode_tag', '<div>gallery</div>', 'gallery', array(), array() );
dsk_test_eq( $out, '<div>gallery</div>', 'other shortcodes untouched' );

$widget = new class() { public function get_name() { return 'form'; } };
$out = apply_filters( 'elementor/widget/render_content', '<form>el</form>', $widget );
dsk_test_ok( false !== strpos( $out, 'digitizer-sk-closed-form' ), 'Elementor form widget replaced' );
$heading = new class() { public function get_name() { return 'heading'; } };
dsk_test_eq( apply_filters( 'elementor/widget/render_content', '<h2>x</h2>', $heading ), '<h2>x</h2>', 'other widgets untouched' );

$cf7 = new class() { public $invalid = array(); public function invalidate( $tag, $msg ) { $this->invalid[] = array( $tag, $msg ); } };
apply_filters( 'wpcf7_validate', $cf7, array( 'your-name' ) );
dsk_test_eq( count( $cf7->invalid ), 1, 'CF7 submission invalidated' );

do_action_stub( 'wpforms_process_before', array(), array( 'id' => 9 ) );
dsk_test_ok( isset( $GLOBALS['dsk_stub_wpforms']->process->errors[9]['header'] ), 'WPForms submission refused' );

/* Elementor Pro's Ajax_Handler keeps two lists, and Form_Record::validate() reads only $errors. */
$ajax = new class() {
	public $errors = array();
	public $messages = array();
	public function add_error( $field, $m = '' ) { $this->errors[ $field ] = $m; return $this; }
	public function add_error_message( $m ) { $this->messages[] = $m; return $this; }
};
do_action_stub( 'elementor_pro/forms/validation', null, $ajax );
dsk_test_eq( count( $ajax->errors ), 1, 'Elementor submission stopped by a validation error, not only a message' );
dsk_test_eq( count( $ajax->messages ), 1, 'Elementor visitor is told why' );
$legacy = new class() { public $messages = array(); public function add_error_message( $m ) { $this->messages[] = $m; } };
do_action_stub( 'elementor_pro/forms/validation', null, $legacy );
dsk_test_eq( count( $legacy->messages ), 0, 'a handler that cannot be stopped is left alone' );
dsk_test_eq( apply_filters( 'comments_open', true, 5 ), false, 'comment forms closed while closed' );
$died = null;
try { do_action_stub( 'pre_comment_on_post', 5 ); } catch ( Exception $e ) { $died = $e->getMessage(); }
dsk_test_ok( is_string( $died ) && '' !== $died, 'a direct comment post dies with the refusal' );

/* ---- a literal % in banner_text does not fatal message() ---- */
Digitizer_Shabbat_Keeper_Settings::save( array( 'banner_text' => '10% off when we reopen at %s' ) );
Digitizer_Shabbat_Keeper_Enforce::reset();
dsk_test_ok( false !== strpos( Digitizer_Shabbat_Keeper_Integrations::message(), '10% off' ), 'message() keeps a literal % in banner_text' );
Digitizer_Shabbat_Keeper_Settings::save( array( 'banner_text' => '' ) );
Digitizer_Shabbat_Keeper_Enforce::reset();

/* ---- refuse_checkout / product_notice, closed and anonymous ---- */
$GLOBALS['dsk_stub_notices'] = array();
apply_filters( 'woocommerce_checkout_process', null );
dsk_test_eq( count( $GLOBALS['dsk_stub_notices'] ), 1, 'checkout on Shabbat produces one notice' );
dsk_test_eq( $GLOBALS['dsk_stub_notices'][0][1], 'error', 'checkout refusal is an error notice' );
ob_start();
apply_filters( 'woocommerce_single_product_summary', null );
$product_notice_out = ob_get_clean();
dsk_test_ok( false !== strpos( $product_notice_out, 'digitizer-sk-closed-notice' ), 'product page shows the closed notice' );

/* ---- refusals_apply(): cron, WP-CLI and non-AJAX wp-admin are skipped ---- */
$GLOBALS['dsk_stub_doing_cron'] = true;
dsk_test_eq( apply_filters( 'woocommerce_is_purchasable', true, null ), true, 'cron never refuses a purchase' );
$GLOBALS['dsk_stub_doing_cron'] = false;

$GLOBALS['dsk_stub_is_admin']   = true;
$GLOBALS['dsk_stub_doing_ajax'] = false;
dsk_test_eq( apply_filters( 'woocommerce_is_purchasable', true, null ), true, 'a plain wp-admin request never refuses a purchase, so a manager can build an order by hand' );
$GLOBALS['dsk_stub_doing_ajax'] = true;
dsk_test_eq( apply_filters( 'woocommerce_is_purchasable', true, null ), false, 'admin-ajax still refuses a purchase' );
$GLOBALS['dsk_stub_is_admin']   = false;
$GLOBALS['dsk_stub_doing_ajax'] = false;

/* ---- open, visitor: everything passes through ---- */
$clock = $at( '2026-09-02 12:00' );
Digitizer_Shabbat_Keeper_Enforce::reset();
$GLOBALS['dsk_stub_notices'] = array();
dsk_test_eq( apply_filters( 'woocommerce_is_purchasable', true, null ), true, 'purchasable on Wednesday' );
dsk_test_eq( apply_filters( 'woocommerce_add_to_cart_validation', true, 7 ), true, 'add to cart allowed' );
dsk_test_eq( apply_filters( 'woocommerce_valid_order_statuses_for_payment', array( 'pending', 'failed' ), null ), array( 'pending', 'failed' ), 'order-pay statuses untouched on Wednesday' );
$thrown = null;
try { do_action_stub( 'woocommerce_before_pay_action', null ); } catch ( Exception $e ) { $thrown = $e->getMessage(); }
dsk_test_eq( $thrown, null, 'paying an existing order on Wednesday is not refused' );
dsk_test_eq( apply_filters( 'do_shortcode_tag', '<form>cf7</form>', 'contact-form-7', array(), array() ), '<form>cf7</form>', 'form shown on Wednesday' );
dsk_test_eq( apply_filters( 'comments_open', true, 5 ), true, 'comments open on Wednesday' );
$died = null;
try { do_action_stub( 'pre_comment_on_post', 5 ); } catch ( Exception $e ) { $died = $e->getMessage(); }
dsk_test_eq( $died, null, 'comment post allowed on Wednesday' );
apply_filters( 'woocommerce_checkout_process', null );
dsk_test_eq( count( $GLOBALS['dsk_stub_notices'] ), 0, 'no checkout notice on Wednesday' );
ob_start();
apply_filters( 'woocommerce_single_product_summary', null );
dsk_test_eq( ob_get_clean(), '', 'no product notice on Wednesday' );

/* ---- closed, administrator: exempt ---- */
$clock = $at( '2026-09-05 12:00' );
$GLOBALS['dsk_stub_no_user'] = false;
$GLOBALS['dsk_stub_denied_caps'] = array();
Digitizer_Shabbat_Keeper_Enforce::reset();
dsk_test_eq( apply_filters( 'woocommerce_is_purchasable', true, null ), true, 'administrator can still buy' );

/* ---- switches off ---- */
$GLOBALS['dsk_stub_no_user'] = true;
$GLOBALS['dsk_stub_filters'] = array();
add_filter( 'digitizer_shabbat_keeper_now', function () use ( &$clock ) { return $clock; } );
Digitizer_Shabbat_Keeper_Settings::save( array( 'block_woo' => '0', 'block_forms' => '0' ) );
Digitizer_Shabbat_Keeper_Enforce::reset();
Digitizer_Shabbat_Keeper_Integrations::hook_plugins();
dsk_test_ok( ! dsk_stub_has_filter( 'woocommerce_is_purchasable' ), 'woo not hooked when switched off' );
dsk_test_ok( ! dsk_stub_has_filter( 'wpcf7_validate' ), 'forms not hooked when switched off' );
dsk_test_ok( ! dsk_stub_has_filter( 'pre_comment_on_post' ), 'comments not hooked in business mode with forms switched off' );
$GLOBALS['dsk_stub_filters'] = array();
add_filter( 'digitizer_shabbat_keeper_now', function () use ( &$clock ) { return $clock; } );
Digitizer_Shabbat_Keeper_Settings::save( array( 'mode' => 'closed', 'block_woo' => '0', 'block_forms' => '0' ) );
Digitizer_Shabbat_Keeper_Enforce::reset();
Digitizer_Shabbat_Keeper_Integrations::hook_plugins();
dsk_test_eq( Digitizer_Shabbat_Keeper_Settings::get( 'block_woo' ), '0', 'both business switches really are off' );
dsk_test_ok( dsk_stub_has_filter( 'pre_comment_on_post' ), 'closed mode guards comments even with forms switched off' );
dsk_test_ok( dsk_stub_has_filter( 'comments_open' ), 'closed mode closes comment forms even with forms switched off' );
dsk_test_ok( dsk_stub_has_filter( 'woocommerce_store_api_cart_errors' ), 'closed mode refuses the Store API even with the WooCommerce switch off' );
dsk_test_ok( dsk_stub_has_filter( 'woocommerce_checkout_process' ), 'closed mode refuses checkout even with the WooCommerce switch off' );
dsk_test_ok( dsk_stub_has_filter( 'woocommerce_before_pay_action' ), 'closed mode refuses order payment even with the WooCommerce switch off' );
dsk_test_ok( dsk_stub_has_filter( 'wpcf7_validate' ), 'closed mode refuses form posts even with the forms switch off' );
dsk_test_ok( dsk_stub_has_filter( 'elementor_pro/forms/validation' ), 'closed mode refuses Elementor posts even with the forms switch off' );

/* Actions in the harness share the filter registry; run one by hand. */
function do_action_stub( $tag ) {
	$args = func_get_args();
	call_user_func_array( 'apply_filters', array_merge( array( $tag, $args[1] ), array_slice( $args, 2 ) ) );
}

exit( dsk_test_summary() );
