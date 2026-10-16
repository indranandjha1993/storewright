<?php

declare(strict_types=1);

namespace Storewright\Core\Checkout;

use WC_Order;

final class PhoneSync {

	public static function register(): void {
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', [ self::class, 'apply' ] );
		add_action( 'woocommerce_checkout_create_order', [ self::class, 'apply' ] );
	}

	public static function apply( WC_Order $order ): void {
		[ $billing, $shipping ] = self::fill( (string) $order->get_billing_phone(), (string) $order->get_shipping_phone() );
		$order->set_billing_phone( $billing );
		$order->set_shipping_phone( $shipping );
	}

	public static function fill( string $billing, string $shipping ): array {
		$billing  = trim( $billing );
		$shipping = trim( $shipping );
		return [ $billing !== '' ? $billing : $shipping, $shipping !== '' ? $shipping : $billing ];
	}
}
