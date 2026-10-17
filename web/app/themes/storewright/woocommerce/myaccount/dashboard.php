<?php

defined( 'ABSPATH' ) || exit;

$storewright_customer = new WC_Customer( get_current_user_id() );
$storewright_name     = $storewright_customer->get_first_name() ?: $current_user->display_name;
$storewright_orders   = wc_get_orders(
	[
		'customer' => get_current_user_id(),
		'limit'    => 3,
		'orderby'  => 'date',
		'order'    => 'DESC',
		'type'     => 'shop_order',
	]
);
$storewright_count    = (int) wc_get_customer_order_count( get_current_user_id() );
?>
<div class="sw-overview">
	<header class="sw-overview__head">
		<p class="sw-eyebrow"><?php esc_html_e( 'Your account', 'storewright' ); ?></p>
		<h2 class="sw-overview__title">
			<?php
			/* translators: %s: first name */
			echo esc_html( sprintf( __( 'Hello, %s', 'storewright' ), $storewright_name ) );
			?>
		</h2>
	</header>

	<ul class="sw-overview__cards">
		<li>
			<a class="sw-overview__card" href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>">
				<span class="sw-overview__figure"><?php echo esc_html( number_format_i18n( $storewright_count ) ); ?></span>
				<span><?php echo esc_html( _n( 'Order placed', 'Orders placed', $storewright_count, 'storewright' ) ); ?></span>
			</a>
		</li>
		<li>
			<a class="sw-overview__card" href="<?php echo esc_url( wc_get_endpoint_url( 'addresses' ) ); ?>">
				<span class="sw-overview__figure"><?php echo esc_html( $storewright_customer->get_shipping_postcode() ?: '–' ); ?></span>
				<span><?php esc_html_e( 'Delivery address', 'storewright' ); ?></span>
			</a>
		</li>
		<li>
			<a class="sw-overview__card" href="<?php echo esc_url( wc_get_endpoint_url( 'edit-account' ) ); ?>">
				<span class="sw-overview__figure sw-overview__figure--small"><?php echo esc_html( $storewright_customer->get_email() ); ?></span>
				<span><?php esc_html_e( 'Account details', 'storewright' ); ?></span>
			</a>
		</li>
	</ul>

	<section class="sw-overview__recent">
		<header class="sw-account__section-head">
			<h3><?php esc_html_e( 'Recent orders', 'storewright' ); ?></h3>
			<?php if ( $storewright_orders ) : ?>
				<a class="sw-address__link" href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>"><?php esc_html_e( 'All orders', 'storewright' ); ?></a>
			<?php endif; ?>
		</header>
		<?php if ( ! $storewright_orders ) : ?>
			<p class="sw-account__empty">
				<?php esc_html_e( 'Nothing ordered yet.', 'storewright' ); ?>
				<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Have a look around the shop.', 'storewright' ); ?></a>
			</p>
		<?php else : ?>
			<ul class="sw-overview__orders">
				<?php foreach ( $storewright_orders as $storewright_order ) : ?>
					<li>
						<a href="<?php echo esc_url( $storewright_order->get_view_order_url() ); ?>">
							<span class="sw-overview__order-number">
								<?php
								/* translators: %s: order number */
								echo esc_html( sprintf( __( 'Order #%s', 'storewright' ), $storewright_order->get_order_number() ) );
								?>
							</span>
							<span class="sw-overview__order-date"><?php echo esc_html( wc_format_datetime( $storewright_order->get_date_created() ) ); ?></span>
							<span class="sw-overview__order-status"><?php echo esc_html( wc_get_order_status_name( $storewright_order->get_status() ) ); ?></span>
							<span class="sw-overview__order-total"><?php echo wp_kses_post( $storewright_order->get_formatted_order_total() ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
</div>
<?php
do_action( 'woocommerce_account_dashboard' );
