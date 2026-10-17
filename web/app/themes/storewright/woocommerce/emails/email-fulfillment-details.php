<?php

use Storewright\Core\Fulfilment\Carriers;

defined( 'ABSPATH' ) || exit;

if ( $fulfillment->get_date_deleted() !== null ) {
	return;
}

$storewright_number  = (string) $fulfillment->get_meta( '_tracking_number', true );
$storewright_link    = (string) $fulfillment->get_meta( '_tracking_url', true );
$storewright_carrier = class_exists( Carriers::class ) ? Carriers::name( $fulfillment ) : (string) $fulfillment->get_shipment_provider();
$storewright_order   = $sent_to_admin ? $order->get_edit_order_url() : ( $order->get_customer_id() ? $order->get_view_order_url() : $order->get_checkout_order_received_url() );
?>
<h2 class="email-order-detail-heading">
	<?php esc_html_e( 'Shipment details', 'storewright' ); ?>
	<br><span>
		<?php
		/* translators: 1: order number, 2: order date */
		echo esc_html( sprintf( __( 'Order #%1$s (%2$s)', 'storewright' ), $order->get_order_number(), wc_format_datetime( $order->get_date_created() ) ) );
		?>
	</span>
</h2>

<?php if ( $storewright_carrier !== '' || $storewright_number !== '' ) : ?>
	<p>
		<?php if ( $storewright_carrier !== '' ) : ?>
			<strong><?php esc_html_e( 'Carrier', 'storewright' ); ?>:</strong> <?php echo esc_html( $storewright_carrier ); ?><br>
		<?php endif; ?>
		<?php if ( $storewright_number !== '' ) : ?>
			<strong><?php esc_html_e( 'Tracking number', 'storewright' ); ?>:</strong> <?php echo esc_html( $storewright_number ); ?>
		<?php endif; ?>
	</p>
<?php endif; ?>

<?php if ( $storewright_link !== '' && ! $plain_text ) : ?>
	<?php
	wc_get_template(
		'emails/email-button.php',
		[
			'url'   => $storewright_link,
			'label' => __( 'Track your parcel', 'storewright' ),
		]
	);
	?>
<?php elseif ( $storewright_link !== '' ) : ?>
	<?php echo esc_html__( 'Track your parcel', 'storewright' ) . ': ' . esc_url_raw( $storewright_link ) . "\n"; ?>
<?php endif; ?>

<?php do_action( 'woocommerce_email_before_fulfillment_table', $order, $fulfillment, $sent_to_admin, $plain_text, $email ); ?>

<div style="margin-bottom: 24px;">
	<table class="td font-family email-order-details" cellspacing="0" cellpadding="6" style="width: 100%;" border="1">
		<tbody>
			<?php
			echo wc_get_email_fulfillment_items( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$order,
				$fulfillment,
				[
					'show_sku'      => $sent_to_admin,
					'show_image'    => true,
					'image_size'    => [ 48, 48 ],
					'plain_text'    => $plain_text,
					'sent_to_admin' => $sent_to_admin,
				]
			);
			?>
		</tbody>
	</table>
</div>

<p><a href="<?php echo esc_url( $storewright_order ); ?>"><?php esc_html_e( 'See the whole order', 'storewright' ); ?></a></p>

<?php
do_action( 'woocommerce_email_after_fulfillment_table', $order, $fulfillment, $sent_to_admin, $plain_text, $email );
