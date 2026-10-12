<?php
/**
 * Boot and lifecycle: what happens when the plugin loads, is activated,
 * is deactivated.
 *
 * @package Digitizer_Shabbat_Keeper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Digitizer_Shabbat_Keeper_Core {

	/** The option Digitizer Pro Tools' Shabbat Keeper module keeps its settings in. */
	const DPT_OPTION = 'dpt_shabbat_keeper';

	/** @var Digitizer_Shabbat_Keeper_Admin|null */
	private static $admin = null;

	public static function boot() {
		// A site that got the files without the activation hook - a network
		// sub-site, a must-use install - still needs its option.
		if ( ! is_array( get_option( Digitizer_Shabbat_Keeper_Settings::OPTION ) ) ) {
			Digitizer_Shabbat_Keeper_Settings::install_defaults();
		}

		Digitizer_Shabbat_Keeper_Enforce::register();
		Digitizer_Shabbat_Keeper_Integrations::register();

		if ( is_admin() && class_exists( 'Digitizer_Shabbat_Keeper_Admin' ) ) {
			self::$admin = new Digitizer_Shabbat_Keeper_Admin();
		}

		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
	}

	/**
	 * The Hebrew catalog ships inside the plugin. WordPress 6.8 and later read
	 * the Domain Path header and find it on their own; earlier versions have
	 * to be told where it is.
	 */
	public static function load_textdomain() {
		if ( version_compare( get_bloginfo( 'version' ), '6.8', '>=' ) ) {
			return;
		}
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Needed on WordPress 5.7-6.7 for the bundled catalog; 6.8+ returns above.
		load_plugin_textdomain( 'digitizer-shabbat-keeper', false, dirname( plugin_basename( DIGITIZER_SHABBAT_KEEPER_FILE ) ) . '/languages' );
	}

	public static function activate() {
		self::import_from_dpt();
		Digitizer_Shabbat_Keeper_Settings::install_defaults();
		Digitizer_Shabbat_Keeper_Enforce::purge_caches();
	}

	/**
	 * A site moving here from Digitizer Pro Tools keeps what it chose there.
	 * Once, one way, and only when this plugin has no settings of its own;
	 * the module's option is left as it is.
	 *
	 * @return bool Whether settings were imported.
	 */
	public static function import_from_dpt() {
		if ( is_array( get_option( Digitizer_Shabbat_Keeper_Settings::OPTION ) ) ) {
			return false;
		}
		$theirs = get_option( self::DPT_OPTION );
		if ( ! is_array( $theirs ) ) {
			return false;
		}
		$clean = Digitizer_Shabbat_Keeper_Settings::sanitize( $theirs );
		// Those settings were somebody's decision already; no need to ask again.
		$clean['saved'] = '1';
		return (bool) add_option( Digitizer_Shabbat_Keeper_Settings::OPTION, $clean );
	}

	/** Nothing of ours keeps running: the pending purge is dropped, and pages cached with a banner or a closed screen are thrown away. */
	public static function deactivate() {
		wp_clear_scheduled_hook( Digitizer_Shabbat_Keeper_Enforce::CRON_HOOK );
		Digitizer_Shabbat_Keeper_Enforce::purge_caches();
	}
}
