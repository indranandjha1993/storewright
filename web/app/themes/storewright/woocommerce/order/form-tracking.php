<?php

defined( 'ABSPATH' ) || exit;

global $post;
?>
<form action="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" method="post" class="woocommerce-form woocommerce-form-track-order track_order sw-track" aria-label="<?php esc_attr_e( 'Track an order', 'storewright' ); ?>">
	<?php do_action( 'woocommerce_order_tracking_form_start' ); ?>
	<p class="sw-track__field">
		<label for="orderid"><?php esc_html_e( 'Order number', 'storewright' ); ?></label>
		<input class="input-text" type="text" name="orderid" id="orderid" inputmode="numeric" autocomplete="off" required value="<?php echo isset( $_REQUEST['orderid'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['orderid'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>">
	</p>
	<p class="sw-track__field">
		<label for="order_email"><?php esc_html_e( 'Email you ordered with', 'storewright' ); ?></label>
		<input class="input-text" type="email" name="order_email" id="order_email" autocomplete="email" required value="<?php echo isset( $_REQUEST['order_email'] ) ? esc_attr( sanitize_email( wp_unslash( $_REQUEST['order_email'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>">
	</p>
	<?php do_action( 'woocommerce_order_tracking_form' ); ?>
	<p class="sw-track__submit">
		<button type="submit" class="wp-element-button" name="track" value="track"><?php esc_html_e( 'Track order', 'storewright' ); ?></button>
	</p>
	<?php wp_nonce_field( 'woocommerce-order_tracking', 'woocommerce-order-tracking-nonce' ); ?>
	<?php do_action( 'woocommerce_order_tracking_form_end' ); ?>
</form>
