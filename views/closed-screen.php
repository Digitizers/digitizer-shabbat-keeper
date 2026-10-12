<?php
/**
 * The page a visitor sees in closed mode. Rendered by Digitizer_Shabbat_Keeper_Enforce.
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
</main>
<?php echo $script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static script with one integer, built by Digitizer_Shabbat_Keeper_Enforce::transition_script(). ?>
</body>
</html>
