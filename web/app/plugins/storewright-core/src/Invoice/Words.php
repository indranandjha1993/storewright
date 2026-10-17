<?php

declare(strict_types=1);

namespace Storewright\Core\Invoice;

final class Words {

	private const ONES = [ '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen' ];
	private const TENS = [ '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety' ];

	private const GROUPS = [
		10000000 => 'Crore',
		100000   => 'Lakh',
		1000     => 'Thousand',
		100      => 'Hundred',
	];

	public static function rupees( float $amount ): string {
		$paise  = (int) round( $amount * 100 );
		$rupees = intdiv( $paise, 100 );
		$paise %= 100;
		$words  = 'Rupees ' . self::number( $rupees );
		if ( $paise > 0 ) {
			$words .= ' and ' . self::number( $paise ) . ' Paise';
		}
		return $words . ' Only';
	}

	public static function number( int $value ): string {
		if ( $value === 0 ) {
			return 'Zero';
		}
		$parts = [];
		foreach ( self::GROUPS as $size => $name ) {
			if ( $value >= $size ) {
				$parts[] = self::number( intdiv( $value, $size ) ) . ' ' . $name;
				$value  %= $size;
			}
		}
		if ( $value > 0 ) {
			$parts[] = $value < 20 ? self::ONES[ $value ] : trim( self::TENS[ intdiv( $value, 10 ) ] . ' ' . self::ONES[ $value % 10 ] );
		}
		return implode( ' ', $parts );
	}
}
