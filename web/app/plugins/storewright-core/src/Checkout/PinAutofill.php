<?php

declare(strict_types=1);

namespace Storewright\Core\Checkout;

use Storewright\Core\Asset;

final class PinAutofill {

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	public static function enqueue(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		Asset::script( 'storewright-checkout-pin', 'assets/js/checkout-pin.js', [ 'wp-data' ] );
		wp_add_inline_script(
			'storewright-checkout-pin',
			'window.storewrightCheckoutPin = ' . wp_json_encode(
				[
					'endpoint' => rest_url( 'storewright/v1/delivery' ),
					'nonce'    => wp_create_nonce( 'wp_rest' ),
				]
			) . ';',
			'before'
		);
	}
}
