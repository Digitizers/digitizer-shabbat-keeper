<?php
/**
 * Decides "closed now" for the current request and applies it: the 503
 * page in closed mode, the banner and contact CSS in business mode, a
 * purge of page caches at each transition and a small script that reloads
 * a page once its transition has passed. Truth is always the computation,
 * never the cron.
 *
 * @package Digitizer_Shabbat_Keeper
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Digitizer_Shabbat_Keeper_Enforce {

	const CRON_HOOK = 'digitizer_shabbat_keeper_transition_event';

	/** @var bool|null */
	private static $closed = null;
	/** @var Digitizer_Shabbat_Keeper_Zmanim|null */
	private static $zmanim = null;
	/** @var bool */
	private static $banner_printed = false;

	public static function register() {
		// Priority 0: membership and access plugins answer at priority 1 and up
		// with a redirect or a 403, and would otherwise answer first; the
		// Shabbat 503 is the earlier decision.
		add_action( 'template_redirect', array( __CLASS__, 'maybe_block' ), 0 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_body_open', array( __CLASS__, 'print_banner' ) );
		add_action( 'wp_footer', array( __CLASS__, 'print_banner_fallback' ) );
		add_action( 'wp_footer', array( __CLASS__, 'print_transition_script' ), 99 );
		add_action( 'wp', array( __CLASS__, 'ensure_transition_event' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'on_transition' ) );
	}

	/** Forget the per-request memo (after a settings save, in tests). */
	public static function reset() {
		self::$closed         = null;
		self::$zmanim         = null;
		self::$banner_printed = false;
	}

	public static function zmanim() {
		if ( null === self::$zmanim ) {
			$loc          = Digitizer_Shabbat_Keeper_Settings::location();
			self::$zmanim = new Digitizer_Shabbat_Keeper_Zmanim( $loc['lat'], $loc['lon'], $loc['candle'], (int) Digitizer_Shabbat_Keeper_Settings::get( 'havdalah' ) );
		}
		return self::$zmanim;
	}

	/** The clock, filterable so tests can pin it. */
	public static function now() {
		return (int) apply_filters( 'digitizer_shabbat_keeper_now', time() );
	}

	const PREVIEW_NONCE = 'digitizer_shabbat_preview';

	/**
	 * An administrator looking at the closed site through the link on the
	 * settings screen. The flag changes nothing, but it does decide what a
	 * logged-in administrator sees, so it carries a nonce: a link someone
	 * else crafted cannot make the site look closed to its owner.
	 */
	public static function is_preview() {
		if ( ! isset( $_GET['digitizer_shabbat'], $_GET['_wpnonce'] ) || 'preview' !== $_GET['digitizer_shabbat'] ) {
			return false;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::PREVIEW_NONCE );
	}

	/** The front page as a visitor would see it during a closure. */
	public static function preview_url() {
		return wp_nonce_url( add_query_arg( 'digitizer_shabbat', 'preview', home_url( '/' ) ), self::PREVIEW_NONCE );
	}

	public static function is_closed() {
		if ( null !== self::$closed ) {
			return self::$closed;
		}
		$override = Digitizer_Shabbat_Keeper_Settings::get( 'override' );
		if ( 'force_open' === $override ) {
			self::$closed = false;
		} elseif ( 'force_closed' === $override || self::is_preview() ) {
			self::$closed = true;
		} else {
			self::$closed = self::zmanim()->is_closed_at( self::now() );
		}
		return self::$closed;
	}

	public static function is_exempt() {
		if ( self::is_preview() ) {
			return false;
		}
		return (bool) apply_filters( 'digitizer_shabbat_keeper_exempt', current_user_can( 'manage_options' ) );
	}

	/** Closed, and this user is not exempt. Request type is not considered here. */
	public static function applies() {
		return self::is_closed() && ! self::is_exempt();
	}

	/** Closed, not exempt, and a context where refusing a purchase or a form makes sense: not cron, not WP-CLI, not a wp-admin screen (admin-ajax still counts, WooCommerce's front-end add-to-cart can travel through it). */
	public static function refusals_apply() {
		if ( ! self::applies() ) {
			return false;
		}
		$context_ok = ! wp_doing_cron()
			&& ! ( defined( 'WP_CLI' ) && WP_CLI )
			&& ! ( is_admin() && ! wp_doing_ajax() );
		return (bool) apply_filters( 'digitizer_shabbat_keeper_refuse', $context_ok );
	}

	/** A visitor-facing page request: not admin, ajax, cron, REST, CLI, XML-RPC or login. */
	public static function is_front_request() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return false;
		}
		if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) {
			return false;
		}
		return true;
	}

	/* ---------------- closed mode ---------------- */

	public static function should_block() {
		return self::is_front_request() && 'closed' === Digitizer_Shabbat_Keeper_Settings::get( 'mode' ) && self::applies();
	}

	public static function maybe_block() {
		if ( ! self::should_block() ) {
			return;
		}
		status_header( 503 );
		nocache_headers();
		foreach ( self::closed_headers() as $header ) {
			header( $header );
		}
		echo self::render_closed_screen(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output, escaped in the view.
		exit;
	}

	/** Extra headers for the 503: Retry-After until Havdalah, or an hour under a manual override. */
	public static function closed_headers() {
		$end   = self::reopens_at();
		$delay = null === $end ? HOUR_IN_SECONDS : max( 60, $end - self::now() );
		return array( 'Retry-After: ' . $delay );
	}

	public static function render_closed_screen() {
		$title   = Digitizer_Shabbat_Keeper_Settings::text( 'closed_title' );
		$message = Digitizer_Shabbat_Keeper_Settings::text( 'closed_message' );
		$reopens = '1' === Digitizer_Shabbat_Keeper_Settings::get( 'closed_show_times' ) ? self::reopens_sentence() : '';
		$loc     = Digitizer_Shabbat_Keeper_Settings::location();
		$city    = $loc['name'];
		$css     = self::css();
		$icon    = get_site_icon_url( 128 );
		$site    = get_bloginfo( 'name' );
		$script  = self::transition_script();
		$credit  = '1' === Digitizer_Shabbat_Keeper_Settings::get( 'credit' );
		ob_start();
		include DIGITIZER_SHABBAT_KEEPER_PATH . 'views/closed-screen.php';
		return (string) ob_get_clean();
	}

	public static function css() {
		$css = file_get_contents( DIGITIZER_SHABBAT_KEEPER_PATH . 'assets/closed.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file shipped with the plugin.
		return is_string( $css ) ? $css : '';
	}

	/* ---------------- times for people ---------------- */

	/** End of the current real window, or null: open, preview outside a window, or force-closed (the calendar does not decide when that ends). */
	public static function reopens_at() {
		if ( 'force_closed' === Digitizer_Shabbat_Keeper_Settings::get( 'override' ) ) {
			return null; // The calendar's end is not this closure's end.
		}
		$w = self::zmanim()->current_window( self::now() );
		return $w ? (int) $w['end'] : null;
	}

	/**
	 * "on Saturday 19:32"; "after Havdalah" for a preview outside a window;
	 * "when the closure is lifted" under force-closed, whose end the calendar
	 * does not know. Each carries its own preposition so it reads after
	 * "reopens" in every language.
	 */
	public static function reopens_text() {
		if ( 'force_closed' === Digitizer_Shabbat_Keeper_Settings::get( 'override' ) ) {
			return __( 'when the closure is lifted', 'digitizer-shabbat-keeper' );
		}
		$end = self::reopens_at();
		if ( null === $end ) {
			return __( 'after Havdalah', 'digitizer-shabbat-keeper' );
		}
		/* translators: PHP date format for the reopening moment: weekday name and time. */
		$when = wp_date( __( 'l H:i', 'digitizer-shabbat-keeper' ), $end, new DateTimeZone( Digitizer_Shabbat_Keeper_Zmanim::TIMEZONE ) );
		/* translators: %s: weekday name and time, e.g. "Saturday 19:32". */
		return sprintf( __( 'on %s', 'digitizer-shabbat-keeper' ), $when );
	}

	private static function reopens_sentence() {
		/* translators: %s: "on Saturday 19:32", "after Havdalah" or "when the closure is lifted". */
		return sprintf( __( 'The site reopens %s.', 'digitizer-shabbat-keeper' ), self::reopens_text() );
	}

	/* ---------------- business mode ---------------- */

	public static function banner_html() {
		if ( 'business' !== Digitizer_Shabbat_Keeper_Settings::get( 'mode' ) || '1' !== Digitizer_Shabbat_Keeper_Settings::get( 'banner_on' ) || ! self::applies() ) {
			return '';
		}
		$text = str_replace( '%s', self::reopens_text(), Digitizer_Shabbat_Keeper_Settings::text( 'banner_text' ) );
		return '<div class="digitizer-sk-banner" role="status">' . esc_html( $text ) . '</div>';
	}

	public static function print_banner() {
		if ( self::$banner_printed || ! self::is_front_request() ) {
			return;
		}
		$html = self::banner_html();
		if ( '' === $html ) {
			return;
		}
		self::$banner_printed = true;
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts in banner_html().
	}

	/** Themes that never fire wp_body_open get the banner from the footer, moved to the top. */
	public static function print_banner_fallback() {
		if ( self::$banner_printed || ! self::is_front_request() ) {
			return;
		}
		$html = self::banner_html();
		if ( '' === $html ) {
			return;
		}
		self::$banner_printed = true;
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts in banner_html().
		wp_print_inline_script_tag( '(function(){var b=document.querySelector(".digitizer-sk-banner");if(b&&document.body.firstChild!==b){document.body.insertBefore(b,document.body.firstChild);}})();' );
	}

	const STYLE_HANDLE = 'digitizer-shabbat-keeper';
	const CONTACT_CSS  = 'a[href^="tel:"],a[href*="wa.me"],a[href*="api.whatsapp.com"]{display:none !important}';

	/** The banner and refusal styles, and the rule that hides contact links, on pages where business mode applies. */
	public static function enqueue_assets() {
		if ( ! self::is_front_request() || 'business' !== Digitizer_Shabbat_Keeper_Settings::get( 'mode' ) || ! self::applies() ) {
			return;
		}
		wp_enqueue_style( self::STYLE_HANDLE, DIGITIZER_SHABBAT_KEEPER_URL . 'assets/closed.css', array(), DIGITIZER_SHABBAT_KEEPER_VERSION );
		if ( '1' === Digitizer_Shabbat_Keeper_Settings::get( 'hide_contact' ) ) {
			wp_add_inline_style( self::STYLE_HANDLE, self::CONTACT_CSS );
		}
	}

	/* ---------------- caches ---------------- */

	/**
	 * The browser's copy of the mechanism: a full-page cache or CDN that serves
	 * every anonymous hit never lets WordPress see a request, so request-driven
	 * WP-Cron may never fire the purge. This inline script knows the next
	 * transition and, when the page is still open past it (or was served stale
	 * after it), reloads with a cache-busting query so the origin answers with
	 * the current state. The timer is always set - a page left open from Sunday
	 * to Friday must still turn - capped at 2e9 ms (23 days) to stay inside
	 * setTimeout's 32-bit range; the next transition is never more than a
	 * week away. Not printed under a manual override, which has no
	 * transition, and idle once the reloaded page carries the matching query.
	 */
	public static function print_transition_script() {
		$js = self::transition_script();
		if ( '' !== $js ) {
			wp_print_inline_script_tag( $js, array( 'id' => self::SCRIPT_ID ) );
		}
	}

	const SCRIPT_ID = 'digitizer-sk-transition';

	/** The script above as bare JavaScript, or '' when it does not apply; the closed screen prints it too, since that response exits before wp_footer. */
	public static function transition_script() {
		if ( ! self::is_front_request() || 'auto' !== Digitizer_Shabbat_Keeper_Settings::get( 'override' ) ) {
			return '';
		}
		$t = (int) self::zmanim()->next_transition( self::now() );
		return '(function(){var t=' . $t . ',n=Math.floor(Date.now()/1000);function q(){try{return new URL(location.href).searchParams.get("digitizer_sk");}catch(e){return null;}}function go(){var u;try{u=new URL(location.href);}catch(e){return;}u.searchParams.set("digitizer_sk",String(t));location.replace(u.toString());}if(n>=t){if(q()!==String(t)){go();}return;}setTimeout(go,Math.min((t-n+2)*1000,2e9));})();';
	}

	public static function ensure_transition_event() {
		if ( ! self::is_front_request() ) {
			return;
		}
		self::schedule_next_transition();
	}

	private static function schedule_next_transition() {
		if ( 'auto' !== Digitizer_Shabbat_Keeper_Settings::get( 'override' ) ) {
			return;
		}
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}
		wp_schedule_single_event( self::zmanim()->next_transition( self::now() ) + 30, self::CRON_HOOK );
	}

	/**
	 * After a settings save: forget the memo, drop the pending transition event
	 * (the location may have moved it) and purge page caches, because a mode or
	 * override change must reach visitors now, not when the cached copy expires.
	 */
	public static function on_settings_saved() {
		self::reset();
		wp_clear_scheduled_hook( self::CRON_HOOK );
		self::purge_caches();
	}

	public static function on_transition() {
		self::reset();
		do_action( 'digitizer_shabbat_keeper_transition', self::zmanim()->is_closed_at( self::now() ) );
		self::purge_caches();
		self::schedule_next_transition();
	}

	/**
	 * Best effort, every known page cache, then one action for the rest: a CDN
	 * or an unknown cache hooks digitizer_shabbat_keeper_purge and is told on every
	 * path that changes what visitors should see - the transition and a
	 * settings save alike. The object cache is left alone: it is not a page
	 * cache, and flushing it would discard unrelated data for no benefit.
	 */
	public static function purge_caches() {
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain(); // WP Rocket.
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache(); // WP Super Cache.
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all(); // W3 Total Cache.
		}
		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache( true ); // WP Fastest Cache.
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache(); // SiteGround Optimizer.
		}
		do_action( 'litespeed_purge_all' ); // LiteSpeed Cache.
		do_action( 'breeze_clear_all_cache' ); // Breeze (Cloudways).
		do_action( 'digitizer_shabbat_keeper_purge' );
	}
}
