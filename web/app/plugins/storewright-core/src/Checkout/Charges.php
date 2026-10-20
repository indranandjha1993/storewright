<?php

declare(strict_types=1);

namespace Storewright\Core\Checkout;

use Storewright\Core\Asset;
use Storewright\Core\Settings;
use WC_Cart;

final class Charges {

	public static function register(): void {
		add_action( 'woocommerce_cart_calculate_fees', [ self::class, 'apply' ] );
		add_action( 'woocommerce_blocks_loaded', [ self::class, 'storeApi' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue' ], 20 );
	}

	public static function handling( float $subtotal, float $fee, float $freeFrom ): float {
		if ( $fee <= 0 ) {
			return 0.0;
		}
		return $freeFrom > 0 && $subtotal >= $freeFrom ? 0.0 : $fee;
	}

	public static function cashOnDelivery( string $method, float $fee ): float {
		return $method === 'cod' && $fee > 0 ? $fee : 0.0;
	}

	public static function apply( WC_Cart $cart ): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		if ( $cart->needs_shipping() ) {
			$handling = self::handling( (float) $cart->get_subtotal(), (float) Settings::get( 'handling_fee' ), (float) Settings::get( 'handling_fee_free_from' ) );
			if ( $handling > 0 ) {
				$cart->add_fee( __( 'Packing and handling', 'storewright' ), $handling );
			}
		}
		$cod = self::cashOnDelivery( (string) WC()->session?->get( 'chosen_payment_method' ), (float) Settings::get( 'cod_fee' ) );
		if ( $cod > 0 ) {
			$cart->add_fee( __( 'Cash on delivery handling', 'storewright' ), $cod );
		}
	}

	public static function storeApi(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			return;
		}
		woocommerce_store_api_register_update_callback(
			[
				'namespace' => 'storewright',
				'callback'  => static function ( array $data ): void {
					$method = sanitize_key( (string) ( $data['payment_method'] ?? '' ) );
					if ( $method !== '' && WC()->session ) {
						WC()->session->set( 'chosen_payment_method', $method );
					}
				},
			]
		);
	}

	public static function enqueue(): void {
		if ( (float) Settings::get( 'cod_fee' ) <= 0 || ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		Asset::script( 'storewright-checkout-charges', 'assets/js/checkout-charges.js', [ 'wp-data', 'wc-blocks-checkout', 'wc-blocks-data-store' ] );
	}
}
