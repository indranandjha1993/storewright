<?php

declare(strict_types=1);

namespace Storewright\Core\Checkout;

final class Gstin {

	private const PATTERN  = '/^[0-3][0-9][A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';
	private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

	public static function normalise( string $value ): string {
		return strtoupper( (string) preg_replace( '/\s+/', '', $value ) );
	}

	public static function isValid( string $value ): bool {
		$gstin = self::normalise( $value );
		if ( ! preg_match( self::PATTERN, $gstin ) ) {
			return false;
		}
		$state = (int) substr( $gstin, 0, 2 );
		return $state >= 1 && $state <= 38 && $gstin[14] === self::checkDigit( substr( $gstin, 0, 14 ) );
	}

	public static function checkDigit( string $body ): string {
		$sum = 0;
		foreach ( str_split( $body ) as $index => $char ) {
			$product = (int) strpos( self::ALPHABET, $char ) * ( $index % 2 === 0 ? 1 : 2 );
			$sum    += intdiv( $product, 36 ) + $product % 36;
		}
		return self::ALPHABET[ ( 36 - $sum % 36 ) % 36 ];
	}
}
