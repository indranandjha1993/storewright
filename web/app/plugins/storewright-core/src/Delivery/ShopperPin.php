<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

final class ShopperPin {

	private const KEY = 'storewright_pin';

	public static function get(): string {
		$pin = function_exists( 'WC' ) && WC()->session ? WC()->session->get( self::KEY ) : '';
		return is_string( $pin ) ? $pin : '';
	}

	public static function set( string $pin ): void {
		WC()->session->set( self::KEY, $pin );
	}
}
