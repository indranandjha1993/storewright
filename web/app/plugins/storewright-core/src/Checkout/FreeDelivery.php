<?php

declare(strict_types=1);

namespace Storewright\Core\Checkout;

use Storewright\Core\Asset;
use WC_Shipping_Zones;

final class FreeDelivery {

	public static function register(): void {
		add_action( 'woocommerce_blocks_loaded', [ self::class, 'extend' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue' ], 20 );
	}

	public static function extend(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			[
				'endpoint'        => 'cart',
				'namespace'       => 'storewright',
				'data_callback'   => [ self::class, 'data' ],
				'schema_callback' => static fn(): array => [
					'freeDeliveryFrom' => [ 'type' => [ 'number', 'null' ] ],
					'remaining'        => [ 'type' => [ 'number', 'null' ] ],
				],
				'schema_type'     => ARRAY_A,
			]
		);
	}

	public static function data(): array {
		$threshold = self::threshold();
		if ( $threshold === null ) {
			return [
				'freeDeliveryFrom' => null,
				'remaining'        => null,
			];
		}
		$cart     = WC()->cart;
		$subtotal = (float) $cart->get_displayed_subtotal() - (float) $cart->get_discount_total();
		return [
			'freeDeliveryFrom' => $threshold,
			'remaining'        => max( 0.0, round( $threshold - $subtotal, 2 ) ),
		];
	}

	public static function threshold(): ?float {
		$cart = WC()->cart;
		if ( ! $cart || ! $cart->needs_shipping() ) {
			return null;
		}
		$packages = $cart->get_shipping_packages();
		if ( $packages === [] ) {
			return null;
		}
		$zone = WC_Shipping_Zones::get_zone_matching_package( reset( $packages ) );
		foreach ( $zone->get_shipping_methods( true ) as $method ) {
			if ( $method->id === 'free_shipping' && in_array( $method->get_option( 'requires' ), [ 'min_amount', 'either' ], true ) ) {
				return (float) $method->get_option( 'min_amount' );
			}
		}
		return null;
	}

	public static function enqueue(): void {
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
			Asset::script( 'storewright-free-delivery', 'assets/js/free-delivery.js', [ 'wp-data', 'wp-i18n', 'wc-blocks-data-store' ] );
		}
	}
}
