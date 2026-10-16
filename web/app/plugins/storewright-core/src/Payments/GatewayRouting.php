<?php

declare(strict_types=1);

namespace Storewright\Core\Payments;

use Storewright\Core\Settings;

final class GatewayRouting {

	private const PREFERRED = [
		'INR' => [ 'razorpay', 'stripe', 'cod' ],
	];
	private const FALLBACK  = [ 'stripe', 'razorpay', 'cod' ];

	public static function register(): void {
		add_filter( 'default_option_woocommerce_gateway_order', static fn(): array => self::order( get_woocommerce_currency() ) );
		add_filter( 'woocommerce_available_payment_gateways', [ self::class, 'filterAvailable' ] );
	}

	public static function order( string $currency ): array {
		return array_flip( self::PREFERRED[ strtoupper( $currency ) ] ?? self::FALLBACK );
	}

	public static function filterAvailable( mixed $gateways ): mixed {
		if ( ! is_array( $gateways ) || ! isset( $gateways['cod'] ) || ( is_admin() && ! wp_doing_ajax() ) ) {
			return $gateways;
		}
		$cart = WC()->cart;
		if ( ! $cart ) {
			return $gateways;
		}
		$cod = new CashOnDelivery( (float) Settings::get( 'cod_max_total' ), (array) Settings::get( 'cod_postcodes' ) );
		if ( ! $cod->allows( self::everythingShips( $cart ), (float) $cart->get_total( 'edit' ), (string) WC()->customer?->get_shipping_postcode() ) ) {
			unset( $gateways['cod'] );
		}
		return $gateways;
	}

	private static function everythingShips( \WC_Cart $cart ): bool {
		foreach ( $cart->get_cart() as $item ) {
			$product = $item['data'] ?? null;
			if ( ! $product instanceof \WC_Product || ! $product->needs_shipping() || $product->is_downloadable() ) {
				return false;
			}
		}
		return $cart->get_cart_contents_count() > 0;
	}
}
