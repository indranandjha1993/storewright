<?php
/**
 * Title: Product promises
 * Slug: storewright/product-promises
 * Categories: storewright
 * Description: Three short lines under the buy box.
 * Inserter: no
 */

?>
<ul class="sw-product__promises">
	<li><?php echo storewright_icon( 'truck' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Dispatched within 24 hours on working days', 'storewright' ); ?></li>
	<li><?php echo storewright_icon( 'return' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( '7 day returns, collected from your door', 'storewright' ); ?></li>
	<li><?php echo storewright_icon( 'shield' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Secure payment, cash on delivery available', 'storewright' ); ?></li>
</ul>
