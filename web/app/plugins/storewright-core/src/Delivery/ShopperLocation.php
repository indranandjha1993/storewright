<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

use Throwable;

final class ShopperLocation {

	private const KEY = 'storewright_location';

	public static function get(): ?Point {
		$saved = function_exists( 'WC' ) && WC()->session ? WC()->session->get( self::KEY ) : null;
		if ( ! is_array( $saved ) ) {
			return null;
		}
		try {
			return new Point( (float) $saved[0], (float) $saved[1] );
		} catch ( Throwable ) {
			return null;
		}
	}

	public static function set( Point $point ): void {
		WC()->session->set( self::KEY, [ $point->lat, $point->lng ] );
	}

	public static function clear(): void {
		WC()->session->set( self::KEY, null );
	}
}
