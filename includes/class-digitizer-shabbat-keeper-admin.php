<?php
/**
 * Shabbat Keeper settings screen.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Digitizer_Shabbat_Keeper_Admin {

	const PAGE_SLUG = 'dpt-shabbat-keeper';
	const NONCE     = 'digitizer_sk_settings_nonce';

	public function __construct() {
		add_action( 'admin_post_digitizer_sk_save', array( $this, 'handle_save' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_notices' ) );
	}

	public function register_menu( $parent_slug ) {
		add_submenu_page(
			$parent_slug,
			__( 'Shabbat Keeper', 'digitizer-shabbat-keeper' ),
			__( 'Shabbat Keeper', 'digitizer-shabbat-keeper' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only display flag from our own redirect.
	public function maybe_show_notices() {
		if ( self::PAGE_SLUG !== dpt_current_admin_page() ) {
			return;
		}
		if ( isset( $_GET['dpt_saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'digitizer-shabbat-keeper' ) . '</p></div>';
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'digitizer-shabbat-keeper' ) );
		}
		check_admin_referer( self::NONCE );

		// Raw array on purpose: Digitizer_Shabbat_Keeper_Settings::sanitize() unslashes and
		// whitelists every field itself.
		$data = isset( $_POST['digitizer_sk'] ) && is_array( $_POST['digitizer_sk'] ) ? $_POST['digitizer_sk'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Sanitized per field in Digitizer_Shabbat_Keeper_Settings::sanitize().
		Digitizer_Shabbat_Keeper_Settings::save( $data );
		Digitizer_Shabbat_Keeper_Enforce::on_settings_saved();

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'dpt_saved' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Human name for a window reason such as "shabbat+rosh_hashana_2". */
	public static function reason_label( $reason ) {
		$names = array(
			'shabbat'        => __( 'Shabbat', 'digitizer-shabbat-keeper' ),
			'rosh_hashana_1' => __( 'Rosh Hashana I', 'digitizer-shabbat-keeper' ),
			'rosh_hashana_2' => __( 'Rosh Hashana II', 'digitizer-shabbat-keeper' ),
			'yom_kippur'     => __( 'Yom Kippur', 'digitizer-shabbat-keeper' ),
			'sukkot_1'       => __( 'Sukkot', 'digitizer-shabbat-keeper' ),
			'shmini_atzeret' => __( 'Shmini Atzeret', 'digitizer-shabbat-keeper' ),
			'pesach_1'       => __( 'Pesach', 'digitizer-shabbat-keeper' ),
			'pesach_7'       => __( 'Seventh day of Pesach', 'digitizer-shabbat-keeper' ),
			'shavuot'        => __( 'Shavuot', 'digitizer-shabbat-keeper' ),
		);
		$out = array();
		foreach ( explode( '+', (string) $reason ) as $key ) {
			$out[] = isset( $names[ $key ] ) ? $names[ $key ] : $key;
		}
		return implode( ' + ', $out );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o      = Digitizer_Shabbat_Keeper_Settings::all();
		$tz     = new DateTimeZone( Digitizer_Shabbat_Keeper_Zmanim::TIMEZONE );
		$now    = Digitizer_Shabbat_Keeper_Enforce::now();
		$zmanim = Digitizer_Shabbat_Keeper_Enforce::zmanim();
		$next   = array_slice( $zmanim->windows( $now, $now + 70 * DAY_IN_SECONDS ), 0, 7 );
		$closed = Digitizer_Shabbat_Keeper_Enforce::is_closed();
		// The first window still ahead: under force_open inside a real window
		// the list starts with that window, whose start is already past.
		$ahead = array_values( array_filter( $next, function ( $w ) use ( $now ) {
			return $w['start'] > $now;
		} ) );
		/* translators: PHP date format for a closure start or end: weekday, day.month, time. */
		$fmt = __( 'D j.n H:i', 'digitizer-shabbat-keeper' );
		?>
		<div class="wrap dpt-wrap">
			<h1 class="dpt-title">
				<span class="dashicons dashicons-clock"></span>
				<?php esc_html_e( 'Shabbat Keeper', 'digitizer-shabbat-keeper' ); ?>
				<span class="dpt-version">v<?php echo esc_html( DPT_VERSION ); ?></span>
			</h1>

			<div class="dpt-panel">
				<h2><?php esc_html_e( 'Status', 'digitizer-shabbat-keeper' ); ?></h2>
				<p>
					<strong><?php echo $closed ? esc_html__( 'Now: closed', 'digitizer-shabbat-keeper' ) : esc_html__( 'Now: open', 'digitizer-shabbat-keeper' ); ?></strong>
					<?php if ( $closed ) : ?>
						&middot; <?php echo esc_html( sprintf( __( 'Reopens %s', 'digitizer-shabbat-keeper' ), Digitizer_Shabbat_Keeper_Enforce::reopens_text() ) ); ?>
					<?php elseif ( ! empty( $ahead ) ) : ?>
						&middot; <?php echo esc_html( sprintf( __( 'Next closing: %s', 'digitizer-shabbat-keeper' ), wp_date( $fmt, $ahead[0]['start'], $tz ) ) ); ?>
					<?php endif; ?>
					&middot; <?php echo esc_html( Digitizer_Shabbat_Keeper_Settings::location()['name'] ); ?>
				</p>
				<p><a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( add_query_arg( 'digitizer_shabbat', 'preview', home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Preview the closed site', 'digitizer-shabbat-keeper' ); ?></a></p>
				<h3><?php esc_html_e( 'Upcoming closures', 'digitizer-shabbat-keeper' ); ?></h3>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'From', 'digitizer-shabbat-keeper' ); ?></th>
						<th><?php esc_html_e( 'Until', 'digitizer-shabbat-keeper' ); ?></th>
						<th><?php esc_html_e( 'Reason', 'digitizer-shabbat-keeper' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $next as $w ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( $fmt, $w['start'], $tz ) ); ?></td>
							<td><?php echo esc_html( wp_date( $fmt, $w['end'], $tz ) ); ?></td>
							<td><?php echo esc_html( self::reason_label( $w['reason'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'Nothing leaves the site: the times are computed here from the city you pick.', 'digitizer-shabbat-keeper' ); ?></p>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="digitizer_sk_save" />
				<?php wp_nonce_field( self::NONCE ); ?>

				<div class="dpt-panel">
					<h2><?php esc_html_e( 'Mode', 'digitizer-shabbat-keeper' ); ?></h2>
					<p><label><input type="radio" name="digitizer_sk[mode]" value="closed" <?php checked( $o['mode'], 'closed' ); ?> /> <?php esc_html_e( 'Closed - every visitor sees a "closed for Shabbat" page', 'digitizer-shabbat-keeper' ); ?></label></p>
					<p><label><input type="radio" name="digitizer_sk[mode]" value="business" <?php checked( $o['mode'], 'business' ); ?> /> <?php esc_html_e( 'Open to read, closed for business - pages stay readable; purchases and forms are refused', 'digitizer-shabbat-keeper' ); ?></label></p>
				</div>

				<div class="dpt-panel">
					<h2><?php esc_html_e( 'Location', 'digitizer-shabbat-keeper' ); ?></h2>
					<table class="form-table"><tbody>
						<tr>
							<th scope="row"><label for="digitizer_sk_city"><?php esc_html_e( 'City', 'digitizer-shabbat-keeper' ); ?></label></th>
							<td>
								<select id="digitizer_sk_city" name="digitizer_sk[city]">
									<?php foreach ( Digitizer_Shabbat_Keeper_Cities::all() as $slug => $city ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $o['city'], $slug ); ?>><?php echo esc_html( $city['name'] ); ?> (<?php echo esc_html( $city['candle'] ); ?>)</option>
									<?php endforeach; ?>
									<option value="custom" <?php selected( $o['city'], 'custom' ); ?>><?php esc_html_e( 'Custom', 'digitizer-shabbat-keeper' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Custom', 'digitizer-shabbat-keeper' ); ?></th>
							<td>
								<label><?php esc_html_e( 'Latitude', 'digitizer-shabbat-keeper' ); ?> <input type="number" step="0.001" min="29.4" max="33.4" name="digitizer_sk[custom_lat]" value="<?php echo esc_attr( $o['custom_lat'] ); ?>" class="small-text" /></label>
								<label><?php esc_html_e( 'Longitude', 'digitizer-shabbat-keeper' ); ?> <input type="number" step="0.001" min="34.2" max="35.9" name="digitizer_sk[custom_lon]" value="<?php echo esc_attr( $o['custom_lon'] ); ?>" class="small-text" /></label>
								<label><?php esc_html_e( 'Candle lighting, minutes before sunset', 'digitizer-shabbat-keeper' ); ?> <input type="number" min="0" max="60" name="digitizer_sk[custom_candle]" value="<?php echo esc_attr( $o['custom_candle'] ); ?>" class="small-text" /></label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Havdalah', 'digitizer-shabbat-keeper' ); ?></th>
							<td>
								<p><label><input type="radio" name="digitizer_sk[havdalah]" value="42" <?php checked( $o['havdalah'], '42' ); ?> /> <?php esc_html_e( '42 minutes after sunset', 'digitizer-shabbat-keeper' ); ?></label></p>
								<p><label><input type="radio" name="digitizer_sk[havdalah]" value="72" <?php checked( $o['havdalah'], '72' ); ?> /> <?php esc_html_e( '72 minutes after sunset (Rabbeinu Tam)', 'digitizer-shabbat-keeper' ); ?></label></p>
							</td>
						</tr>
					</tbody></table>
				</div>

				<div class="dpt-panel">
					<h2><?php esc_html_e( 'Closed page', 'digitizer-shabbat-keeper' ); ?></h2>
					<table class="form-table"><tbody>
						<tr>
							<th scope="row"><label for="digitizer_sk_closed_title"><?php esc_html_e( 'Title', 'digitizer-shabbat-keeper' ); ?></label></th>
							<td><input type="text" id="digitizer_sk_closed_title" name="digitizer_sk[closed_title]" value="<?php echo esc_attr( $o['closed_title'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( Digitizer_Shabbat_Keeper_Settings::text( 'closed_title' ) ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="digitizer_sk_closed_message"><?php esc_html_e( 'Message', 'digitizer-shabbat-keeper' ); ?></label></th>
							<td>
								<?php wp_editor( $o['closed_message'], 'digitizer_sk_closed_message', array( 'textarea_name' => 'digitizer_sk[closed_message]', 'textarea_rows' => 5, 'teeny' => true, 'media_buttons' => false ) ); ?>
								<p class="description"><?php echo esc_html( Digitizer_Shabbat_Keeper_Settings::text( 'closed_message' ) ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Show the reopening time', 'digitizer-shabbat-keeper' ); ?></th>
							<td><?php $this->switch_field( 'closed_show_times', $o['closed_show_times'] ); ?></td>
						</tr>
					</tbody></table>
				</div>

				<div class="dpt-panel">
					<h2><?php esc_html_e( 'Business mode', 'digitizer-shabbat-keeper' ); ?></h2>
					<table class="form-table"><tbody>
						<tr><th scope="row"><?php esc_html_e( 'Refuse WooCommerce purchases', 'digitizer-shabbat-keeper' ); ?></th><td><?php $this->switch_field( 'block_woo', $o['block_woo'] ); ?></td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Refuse form submissions (Elementor, Contact Form 7, WPForms, Gravity Forms)', 'digitizer-shabbat-keeper' ); ?></th><td><?php $this->switch_field( 'block_forms', $o['block_forms'] ); ?></td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Hide phone and WhatsApp links', 'digitizer-shabbat-keeper' ); ?></th><td><?php $this->switch_field( 'hide_contact', $o['hide_contact'] ); ?></td></tr>
						<tr><th scope="row"><?php esc_html_e( 'Show a banner at the top of every page', 'digitizer-shabbat-keeper' ); ?></th><td><?php $this->switch_field( 'banner_on', $o['banner_on'] ); ?></td></tr>
						<tr>
							<th scope="row"><label for="digitizer_sk_banner_text"><?php esc_html_e( 'Banner text', 'digitizer-shabbat-keeper' ); ?></label></th>
							<td>
								<input type="text" id="digitizer_sk_banner_text" name="digitizer_sk[banner_text]" value="<?php echo esc_attr( $o['banner_text'] ); ?>" class="large-text" placeholder="<?php echo esc_attr( Digitizer_Shabbat_Keeper_Settings::text( 'banner_text' ) ); ?>" />
								<p class="description"><?php esc_html_e( '%s is replaced by the reopening time.', 'digitizer-shabbat-keeper' ); ?></p>
							</td>
						</tr>
					</tbody></table>
				</div>

				<div class="dpt-panel">
					<h2><?php esc_html_e( 'Override', 'digitizer-shabbat-keeper' ); ?></h2>
					<p><label><input type="radio" name="digitizer_sk[override]" value="auto" <?php checked( $o['override'], 'auto' ); ?> /> <?php esc_html_e( 'Automatic (by the calendar)', 'digitizer-shabbat-keeper' ); ?></label></p>
					<p><label><input type="radio" name="digitizer_sk[override]" value="force_open" <?php checked( $o['override'], 'force_open' ); ?> /> <?php esc_html_e( 'Force open', 'digitizer-shabbat-keeper' ); ?></label></p>
					<p><label><input type="radio" name="digitizer_sk[override]" value="force_closed" <?php checked( $o['override'], 'force_closed' ); ?> /> <?php esc_html_e( 'Force closed', 'digitizer-shabbat-keeper' ); ?></label></p>
					<p class="description"><?php esc_html_e( 'A forced state ignores the calendar until you switch back to automatic.', 'digitizer-shabbat-keeper' ); ?></p>
				</div>

				<p class="dpt-actions">
					<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save Settings', 'digitizer-shabbat-keeper' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/** Hidden 0 before the checkbox so an unticked switch still posts, as Cookie Banner does. */
	private function switch_field( $name, $checked ) {
		?>
		<label class="dpt-switch">
			<input type="hidden" name="digitizer_sk[<?php echo esc_attr( $name ); ?>]" value="0" />
			<input type="checkbox" name="digitizer_sk[<?php echo esc_attr( $name ); ?>]" value="1" <?php checked( $checked, '1' ); ?> />
			<span class="dpt-switch-slider"></span>
		</label>
		<?php
	}
}
