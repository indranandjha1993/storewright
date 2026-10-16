<?php

declare(strict_types=1);

namespace Storewright\Core\Payments\Stripe;

final class Money {

	private const ZERO_DECIMAL  = [ 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' ];
	private const THREE_DECIMAL = [ 'BHD', 'JOD', 'KWD', 'OMR', 'TND' ];

	public static function minor( float $amount, string $currency ): int {
		$currency = strtoupper( $currency );
		if ( in_array( $currency, self::ZERO_DECIMAL, true ) ) {
			return (int) round( $amount );
		}
		if ( in_array( $currency, self::THREE_DECIMAL, true ) ) {
			return (int) ( round( $amount * 100 ) * 10 );
		}
		return (int) round( $amount * 100 );
	}
}
