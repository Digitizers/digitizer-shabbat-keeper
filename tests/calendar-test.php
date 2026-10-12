<?php
require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/class-digitizer-shabbat-keeper-hebrew-calendar.php';

/* ---- known dates (Hebcal, checked 2026-09-10) ---- */
dsk_test_eq( Digitizer_Shabbat_Keeper_Hebrew_Calendar::to_gregorian( 5787, 7, 1 ), array( 'y' => 2026, 'm' => 9, 'd' => 12 ), '1 Tishrei 5787 is 2026-09-12' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Hebrew_Calendar::to_gregorian( 5786, 1, 15 ), array( 'y' => 2026, 'm' => 4, 'd' => 2 ), '15 Nisan 5786 is 2026-04-02' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Hebrew_Calendar::to_gregorian( 5786, 3, 6 ), array( 'y' => 2026, 'm' => 5, 'd' => 22 ), '6 Sivan 5786 is 2026-05-22' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Hebrew_Calendar::to_gregorian( 5787, 7, 10 ), array( 'y' => 2026, 'm' => 9, 'd' => 21 ), '10 Tishrei 5787 is 2026-09-21' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Hebrew_Calendar::to_gregorian( 5787, 7, 22 ), array( 'y' => 2026, 'm' => 10, 'd' => 3 ), '22 Tishrei 5787 is 2026-10-03' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Hebrew_Calendar::from_gregorian( 2026, 9, 12 ), array( 'year' => 5787, 'month' => 7, 'day' => 1 ), '2026-09-12 is 1 Tishrei 5787' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Hebrew_Calendar::from_gregorian( 2024, 3, 24 ), array( 'year' => 5784, 'month' => 13, 'day' => 14 ), '2024-03-24 is 14 Adar II 5784 (leap year, Purim)' );

/* ---- leap years ---- */
dsk_test_ok( Digitizer_Shabbat_Keeper_Hebrew_Calendar::is_leap_year( 5784 ), '5784 is a leap year' );
dsk_test_ok( ! Digitizer_Shabbat_Keeper_Hebrew_Calendar::is_leap_year( 5785 ), '5785 is not a leap year' );
dsk_test_ok( Digitizer_Shabbat_Keeper_Hebrew_Calendar::is_leap_year( 5787 ), '5787 is a leap year' );

/* ---- round trip over every day of 5784-5787 ---- */
$bad   = 0;
$start = gmmktime( 0, 0, 0, 9, 1, 2023 );
$end   = gmmktime( 0, 0, 0, 10, 15, 2027 );
for ( $ts = $start; $ts < $end; $ts += 86400 ) {
	$y = (int) gmdate( 'Y', $ts );
	$m = (int) gmdate( 'n', $ts );
	$d = (int) gmdate( 'j', $ts );
	$h = Digitizer_Shabbat_Keeper_Hebrew_Calendar::from_gregorian( $y, $m, $d );
	$g = Digitizer_Shabbat_Keeper_Hebrew_Calendar::to_gregorian( $h['year'], $h['month'], $h['day'] );
	if ( $g !== array( 'y' => $y, 'm' => $m, 'd' => $d ) ) {
		$bad++;
	}
}
dsk_test_eq( $bad, 0, 'every day round-trips' );

/* ---- holidays ---- */
dsk_test_eq( Digitizer_Shabbat_Keeper_Hebrew_Calendar::holiday_on( 2026, 9, 12 ), 'rosh_hashana_1', 'Rosh Hashana day 1' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Hebrew_Calendar::holiday_on( 2026, 9, 13 ), 'rosh_hashana_2', 'Rosh Hashana day 2' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Hebrew_Calendar::holiday_on( 2026, 9, 21 ), 'yom_kippur', 'Yom Kippur' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Hebrew_Calendar::holiday_on( 2026, 9, 26 ), 'sukkot_1', 'Sukkot day 1' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Hebrew_Calendar::holiday_on( 2026, 10, 3 ), 'shmini_atzeret', 'Shmini Atzeret' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Hebrew_Calendar::holiday_on( 2026, 4, 2 ), 'pesach_1', 'Pesach day 1' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Hebrew_Calendar::holiday_on( 2026, 4, 8 ), 'pesach_7', 'Pesach day 7' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Hebrew_Calendar::holiday_on( 2026, 5, 22 ), 'shavuot', 'Shavuot' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Hebrew_Calendar::holiday_on( 2026, 9, 27 ), null, 'Sukkot day 2 is Chol HaMoed in Israel, not a closure' );
dsk_test_eq( Digitizer_Shabbat_Keeper_Hebrew_Calendar::holiday_on( 2026, 3, 3 ), null, 'Purim is not a closure' );

exit( dsk_test_summary() );
