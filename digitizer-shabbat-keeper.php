<?php
/**
 * Plugin Name:       Digitizer Shabbat Keeper
 * Plugin URI:        https://github.com/Digitizers/digitizer-shabbat-keeper
 * Description:       Closes your site, or only its shop and forms, from candle lighting to Havdalah every Shabbat and Jewish holiday. Times are computed on your server for the Israeli city you pick - nothing is sent anywhere.
 * Version:           1.0.0
 * Requires at least: 5.7
 * Requires PHP:      7.2
 * Author:            Digitizer
 * Author URI:        https://www.digitizer.studio
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       digitizer-shabbat-keeper
 * Domain Path:       /languages
 *
 * @package Digitizer_Shabbat_Keeper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIGITIZER_SHABBAT_KEEPER_VERSION', '1.0.0' );
define( 'DIGITIZER_SHABBAT_KEEPER_FILE', __FILE__ );
define( 'DIGITIZER_SHABBAT_KEEPER_PATH', plugin_dir_path( __FILE__ ) );
define( 'DIGITIZER_SHABBAT_KEEPER_URL', plugin_dir_url( __FILE__ ) );

require_once DIGITIZER_SHABBAT_KEEPER_PATH . 'includes/class-digitizer-shabbat-keeper-hebrew-calendar.php';
require_once DIGITIZER_SHABBAT_KEEPER_PATH . 'includes/class-digitizer-shabbat-keeper-sun.php';
require_once DIGITIZER_SHABBAT_KEEPER_PATH . 'includes/class-digitizer-shabbat-keeper-cities.php';
require_once DIGITIZER_SHABBAT_KEEPER_PATH . 'includes/class-digitizer-shabbat-keeper-zmanim.php';
require_once DIGITIZER_SHABBAT_KEEPER_PATH . 'includes/class-digitizer-shabbat-keeper-settings.php';
require_once DIGITIZER_SHABBAT_KEEPER_PATH . 'includes/class-digitizer-shabbat-keeper-enforce.php';
require_once DIGITIZER_SHABBAT_KEEPER_PATH . 'includes/class-digitizer-shabbat-keeper-integrations.php';
require_once DIGITIZER_SHABBAT_KEEPER_PATH . 'includes/class-digitizer-shabbat-keeper-core.php';

if ( is_admin() ) {
	require_once DIGITIZER_SHABBAT_KEEPER_PATH . 'includes/class-digitizer-shabbat-keeper-admin.php';
}

register_activation_hook( __FILE__, array( 'Digitizer_Shabbat_Keeper_Core', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Digitizer_Shabbat_Keeper_Core', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Digitizer_Shabbat_Keeper_Core', 'boot' ) );
