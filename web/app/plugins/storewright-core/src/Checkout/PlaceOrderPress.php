<?php

declare(strict_types=1);

namespace Storewright\Core\Checkout;

use Storewright\Core\Asset;

final class PlaceOrderPress {

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	public static function enqueue(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		Asset::script( 'storewright-place-order', 'assets/js/place-order.js', [ 'wp-data' ] );
	}
}
