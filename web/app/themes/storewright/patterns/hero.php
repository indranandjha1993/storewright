<?php
/**
 * Title: Hero
 * Slug: storewright/hero
 * Categories: storewright
 * Description: Full width opening section with a photograph, a headline, a line of copy and two calls to action.
 */

$storewright_image = storewright_hero_image();
?>
<section class="sw-hero alignfull<?php echo $storewright_image ? ' sw-hero--photo' : ''; ?>">
	<?php if ( $storewright_image ) : ?>
		<img class="sw-hero__image" src="<?php echo esc_url( $storewright_image ); ?>" alt="" width="1600" height="1067" fetchpriority="high" decoding="async">
	<?php endif; ?>
	<div class="sw-hero__inner">
		<div class="sw-hero__copy">
			<p class="sw-eyebrow"><?php esc_html_e( 'New this season', 'storewright' ); ?></p>
			<h1 class="sw-hero__title"><?php esc_html_e( 'Made well, delivered to your door', 'storewright' ); ?></h1>
			<p class="sw-hero__lead"><?php esc_html_e( 'Everyday things for home and work. Tracked delivery, simple returns, and same-day in the city.', 'storewright' ); ?></p>
			<div class="sw-hero__actions">
				<a class="wp-element-button" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'Shop everything', 'storewright' ); ?></a>
				<a class="sw-button sw-button--ghost" href="<?php echo esc_url( home_url( '/shop/?orderby=popularity' ) ); ?>"><?php esc_html_e( 'Best sellers', 'storewright' ); ?><?php echo storewright_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			</div>
			<ul class="sw-hero__trust">
				<li><?php echo storewright_icon( 'truck' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Tracked delivery', 'storewright' ); ?></li>
				<li><?php echo storewright_icon( 'return' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Easy returns', 'storewright' ); ?></li>
				<li><?php echo storewright_icon( 'wallet' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'UPI, cards, cash on delivery', 'storewright' ); ?></li>
			</ul>
		</div>
	</div>
</section>
