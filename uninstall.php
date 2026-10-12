<?php
/**
 * Remove everything Digitizer Shabbat Keeper stored: one option and one
 * scheduled event. On a network the plugin can have run on any subset of
 * sites, so every site is visited - and only this plugin's own data is
 * touched in each.
 *
 * @package Digitizer_Shabbat_Keeper
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Forget this site's settings and its pending cache purge.
 *
 * A closure rather than a named function: an uninstall file is included, and
 * a name declared at the top level of an included file cannot be included
 * twice - which the tests do, once for a single site and once for a network.
 *
 * @var callable $digitizer_shabbat_keeper_uninstall_site
 */
$digitizer_shabbat_keeper_uninstall_site = function () {
	delete_option( 'digitizer_shabbat_keeper' );
	wp_clear_scheduled_hook( 'digitizer_shabbat_keeper_transition_event' );
};

if ( is_multisite() ) {
	// get_sites() stops at 100 unless the cap is lifted.
	$digitizer_shabbat_keeper_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $digitizer_shabbat_keeper_sites as $digitizer_shabbat_keeper_site_id ) {
		switch_to_blog( (int) $digitizer_shabbat_keeper_site_id );
		$digitizer_shabbat_keeper_uninstall_site();
		restore_current_blog();
	}
} else {
	$digitizer_shabbat_keeper_uninstall_site();
}
