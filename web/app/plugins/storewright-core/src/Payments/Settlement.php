<?php

declare(strict_types=1);

namespace Storewright\Core\Payments;

use Storewright\Core\Payments\Stripe\StripeCheckoutGateway;
use Storewright\Core\Payments\Stripe\StripeClient;
use Storewright\Platform\GatewayCredentials;
use Throwable;
use WC_Order;

final class Settlement {

	public const PAID    = 'paid';
	public const OPEN    = 'open';
	public const UNKNOWN = 'unknown';

	private const GATEWAYS      = [ 'razorpay', StripeCheckoutGateway::ID ];
	private const AFTER_MINUTES = 15;
	private const WITHIN_DAYS   = 7;

	public static function pending(): array {
		$orders = wc_get_orders(
			[
				'status'         => 'pending',
				'payment_method' => self::GATEWAYS,
				'date_created'   => ( time() - self::WITHIN_DAYS * DAY_IN_SECONDS ) . '...' . ( time() - self::AFTER_MINUTES * MINUTE_IN_SECONDS ),
				'limit'          => 20,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);
		return array_map( [ self::class, 'describe' ], array_filter( $orders, static fn( $order ): bool => $order instanceof WC_Order ) );
	}

	public static function describe( WC_Order $order ): array {
		return [
			'id'       => $order->get_id(),
			'number'   => $order->get_order_number(),
			'gateway'  => $order->get_payment_method(),
			'title'    => $order->get_payment_method_title(),
			'customer' => trim( $order->get_formatted_billing_full_name() ) ?: $order->get_billing_email(),
			'total'    => $order->get_total(),
			'created'  => $order->get_date_created()?->format( 'c' ),
			'status'   => $order->get_status(),
		];
	}

	public static function check( WC_Order $order ): array {
		try {
			return match ( $order->get_payment_method() ) {
				StripeCheckoutGateway::ID => self::stripe( $order ),
				'razorpay'                => self::razorpay( $order ),
				default                   => self::answer( self::UNKNOWN, __( 'This order was not paid online.', 'storewright' ) ),
			};
		} catch ( Throwable $e ) {
			return self::answer( self::UNKNOWN, $e->getMessage() );
		}
	}

	public static function capturedPayment( array $payments ): ?string {
		foreach ( $payments as $payment ) {
			if ( is_array( $payment ) && ( $payment['status'] ?? '' ) === 'captured' ) {
				return (string) $payment['id'];
			}
		}
		return null;
	}

	private static function stripe( WC_Order $order ): array {
		$keys    = StripeCheckoutGateway::keys();
		$session = StripeCheckoutGateway::session( $order );
		if ( $keys === null ) {
			return self::answer( self::UNKNOWN, __( 'Stripe is not set up on this store.', 'storewright' ) );
		}
		if ( $session === '' ) {
			return self::answer( self::OPEN, __( 'The shopper never reached the Stripe payment page.', 'storewright' ) );
		}
		$found = ( new StripeClient( $keys['secret'] ) )->get( 'checkout/sessions/' . rawurlencode( $session ) );
		if ( StripeCheckoutGateway::confirm( $order, $found ) ) {
			return self::answer( self::PAID, __( 'Stripe has the payment. The order is now paid.', 'storewright' ) );
		}
		return self::answer(
			self::OPEN,
			( $found['status'] ?? '' ) === 'expired' ? __( 'The Stripe payment page expired without a payment.', 'storewright' ) : __( 'Stripe has no payment for this order yet.', 'storewright' )
		);
	}

	private static function razorpay( WC_Order $order ): array {
		$keys      = GatewayCredentials::razorpay( get_option( 'woocommerce_razorpay_settings', [] ), wp_get_environment_type() );
		$reference = (string) ( $order->get_meta( 'razorpay_order_id' . $order->get_id() ) ?: $order->get_meta( 'razorpay_order_id_1cc' . $order->get_id() ) );
		if ( $keys['key_id'] === '' || $keys['key_secret'] === '' ) {
			return self::answer( self::UNKNOWN, __( 'Razorpay is not set up on this store.', 'storewright' ) );
		}
		if ( $reference === '' ) {
			return self::answer( self::OPEN, __( 'The shopper never reached the Razorpay payment page.', 'storewright' ) );
		}
		$payments = ( new RazorpayClient( $keys['key_id'], $keys['key_secret'] ) )->get( 'orders/' . rawurlencode( $reference ) . '/payments' );
		$paid     = self::capturedPayment( (array) ( $payments['items'] ?? [] ) );
		if ( $paid === null ) {
			return self::answer( self::OPEN, __( 'Razorpay has no captured payment for this order yet.', 'storewright' ) );
		}
		$order->payment_complete( $paid );
		$order->add_order_note( sprintf( 'Razorpay payment %s found from the console and recorded.', $paid ) );
		return self::answer( self::PAID, __( 'Razorpay has the payment. The order is now paid.', 'storewright' ) );
	}

	private static function answer( string $status, string $message ): array {
		return [
			'status'  => $status,
			'message' => $message,
		];
	}
}
