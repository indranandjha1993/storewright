<?php

declare(strict_types=1);

namespace Storewright\Core\Checkout;

use WC_Order;

final class ThankYou {

	public static function register(): void {
		add_filter( 'woocommerce_thankyou_order_received_text', [ self::class, 'text' ], 30, 2 );
	}

	public static function text( string $text, mixed $order ): string {
		if ( ! $order instanceof WC_Order ) {
			return $text;
		}
		if ( $order->get_payment_method() === 'cod' ) {
			return __( 'Thank you. We have your order and you pay when it arrives.', 'storewright' );
		}
		if ( $order->is_paid() ) {
			return __( 'Thank you. Your payment went through and we have your order.', 'storewright' );
		}
		return __( 'Thank you. We have your order and are waiting for the payment to be confirmed.', 'storewright' );
	}
}
