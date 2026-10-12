<?php
/**
 * The page a visitor sees in closed mode. Rendered by
 * Digitizer_Shabbat_Keeper_Enforce::render_closed_screen(), which sets
 * $title, $message, $reopens, $city, $css, $icon, $site, $script and $credit.
 *
 * This response is sent and the request ends before wp_head would run, so
 * nothing can be enqueued for it: its one stylesheet is read from the
 * plugin's own assets/closed.css and printed inline.
 *
 * @package Digitizer_Shabbat_Keeper
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo esc_html( $title ); ?> - <?php echo esc_html( $site ); ?></title>
<style><?php echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static stylesheet shipped with the plugin. ?></style>
</head>
<body class="digitizer-sk-page">
<main class="digitizer-sk-card" role="main">
	<?php if ( $icon ) : ?>
		<img class="digitizer-sk-icon" src="<?php echo esc_url( $icon ); ?>" alt="">
	<?php endif; ?>
	<h1><?php echo esc_html( $title ); ?></h1>
	<div class="digitizer-sk-message"><?php echo wp_kses_post( $message ); ?></div>
	<?php if ( '' !== $reopens ) : ?>
		<p class="digitizer-sk-reopens"><?php echo esc_html( $reopens ); ?></p>
	<?php endif; ?>
	<p class="digitizer-sk-site"><?php echo esc_html( $site ); ?><?php if ( '' !== $city ) : ?> &middot; <?php echo esc_html( $city ); ?><?php endif; ?></p>
	<?php if ( $credit ) : ?>
		<p class="digitizer-sk-credit">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: the name Digitizer, linked to its website. */
					__( 'Shabbat Keeper by %s', 'digitizer-shabbat-keeper' ),
					'<a href="https://www.digitizer.studio/" rel="noopener">Digitizer</a>'
				),
				array(
					'a' => array(
						'href' => array(),
						'rel'  => array(),
					),
				)
			);
			?>
		</p>
	<?php endif; ?>
</main>
<?php
if ( '' !== $script ) {
	wp_print_inline_script_tag( $script, array( 'id' => Digitizer_Shabbat_Keeper_Enforce::SCRIPT_ID ) );
}
?>
</body>
</html>
