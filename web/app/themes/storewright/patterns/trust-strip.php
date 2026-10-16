<?php
/**
 * Title: Trust strip
 * Slug: storewright/trust-strip
 * Categories: storewright
 * Description: Three short promises about delivery, returns and payment.
 */

?>
<!-- wp:group {"align":"full","className":"sw-trust","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}},"layout":{"type":"constrained","contentSize":"78rem"}} -->
<div class="wp-block-group alignfull sw-trust" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)">
	<!-- wp:columns -->
	<div class="wp-block-columns">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"medium"} -->
			<h3 class="wp-block-heading has-medium-font-size"><?php esc_html_e( 'Tracked delivery', 'storewright' ); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"fontSize":"small","textColor":"muted"} -->
			<p class="has-muted-color has-text-color has-small-font-size"><?php esc_html_e( 'Follow every parcel from dispatch to your door.', 'storewright' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"medium"} -->
			<h3 class="wp-block-heading has-medium-font-size"><?php esc_html_e( 'Simple returns', 'storewright' ); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"fontSize":"small","textColor":"muted"} -->
			<p class="has-muted-color has-text-color has-small-font-size"><?php esc_html_e( 'Request a return from your account in a minute.', 'storewright' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"medium"} -->
			<h3 class="wp-block-heading has-medium-font-size"><?php esc_html_e( 'Pay your way', 'storewright' ); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"fontSize":"small","textColor":"muted"} -->
			<p class="has-muted-color has-text-color has-small-font-size"><?php esc_html_e( 'UPI, cards, netbanking, wallets and cash on delivery.', 'storewright' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
