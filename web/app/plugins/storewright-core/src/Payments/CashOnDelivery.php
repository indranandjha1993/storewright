<?php

declare(strict_types=1);

namespace Storewright\Core\Payments;

use Storewright\Core\Delivery\PostcodePattern;

final class CashOnDelivery {

	public function __construct(
		private readonly float $maxTotal,
		private readonly array $postcodes,
	) {}

	public function allows( bool $everythingShips, float $total, string $postcode ): bool {
		if ( ! $everythingShips || $total > $this->maxTotal ) {
			return false;
		}
		return $this->postcodes === [] || PostcodePattern::anyMatches( $postcode, $this->postcodes );
	}
}
