<?php
/**
 * Title: Hero
 * Slug: storewright/hero
 * Categories: storewright
 * Description: Full width opening section with a headline, a line of copy and one call to action.
 */

?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"backgroundColor":"accent-soft","layout":{"type":"constrained","contentSize":"48rem"}} -->
<div class="wp-block-group alignfull has-accent-soft-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
	<!-- wp:heading {"textAlign":"center","level":1,"fontSize":"xx-large"} -->
	<h1 class="wp-block-heading has-text-align-center has-xx-large-font-size"><?php esc_html_e( 'Made well, delivered to your door', 'storewright' ); ?></h1>
	<!-- /wp:heading -->
	<!-- wp:paragraph {"align":"center","fontSize":"large","textColor":"muted"} -->
	<p class="has-text-align-center has-muted-color has-text-color has-large-font-size"><?php esc_html_e( 'Everyday things for home and work, with tracked delivery and simple returns.', 'storewright' ); ?></p>
	<!-- /wp:paragraph -->
	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
	<div class="wp-block-buttons">
		<!-- wp:button -->
		<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/shop/"><?php esc_html_e( 'Shop everything', 'storewright' ); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
