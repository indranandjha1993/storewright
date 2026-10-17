<?php

declare(strict_types=1);

namespace Storewright\Core\Emails;

use WC_Email;
use WC_Order;

final class OrderButton {

	private const EMAILS = [ 'customer_processing_order', 'customer_on_hold_order', 'customer_completed_order' ];

	public static function register(): void {
		add_action( 'woocommerce_email_customer_details', [ self::class, 'render' ], 40, 4 );
	}

	public static function render( mixed $order, mixed $sentToAdmin, mixed $plainText, mixed $email ): void {
		if ( ! $order instanceof WC_Order || ! $email instanceof WC_Email || $sentToAdmin || ! in_array( $email->id, self::EMAILS, true ) ) {
			return;
		}
		$url   = self::url( $order );
		$label = __( 'View your order', 'storewright' );
		if ( $plainText ) {
			echo esc_html( $label ) . ': ' . esc_url_raw( $url ) . "\n\n";
			return;
		}
		wc_get_template(
			'emails/email-button.php',
			[
				'url'   => $url,
				'label' => $label,
			]
		);
	}

	public static function url( WC_Order $order ): string {
		return $order->get_customer_id() ? $order->get_view_order_url() : $order->get_checkout_order_received_url();
	}
}
