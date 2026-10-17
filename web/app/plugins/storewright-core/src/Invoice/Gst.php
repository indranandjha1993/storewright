<?php

declare(strict_types=1);

namespace Storewright\Core\Invoice;

final class Gst {

	public const RATES = [ 0, 5, 12, 18, 28 ];

	public static function split( float $gross, float $rate, bool $sameState ): array {
		$taxable = round( $gross / ( 1 + $rate / 100 ), 2 );
		$tax     = round( $gross - $taxable, 2 );
		$half    = round( $tax / 2, 2 );
		return [
			'taxable' => $taxable,
			'cgst'    => $sameState ? $half : 0.0,
			'sgst'    => $sameState ? round( $tax - $half, 2 ) : 0.0,
			'igst'    => $sameState ? 0.0 : $tax,
		];
	}
}
