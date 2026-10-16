<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

final class Zone {

	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly array $regions,
		public readonly array $postcodes,
		public readonly int $enabledMethods,
		public readonly ?Shape $shape = null,
	) {}

	public function covers( Address $address ): bool {
		return $this->regionMatches( $address ) && $this->postcodeMatches( $address );
	}

	private function regionMatches( Address $address ): bool {
		if ( $this->regions === [] ) {
			return true;
		}
		foreach ( $this->regions as [ $type, $code ] ) {
			$code    = strtoupper( (string) $code );
			$matched = match ( $type ) {
				'country' => $code === $address->country,
				'state'   => $code === $address->country . ':' . $address->state,
				default   => false,
			};
			if ( $matched ) {
				return true;
			}
		}
		return false;
	}

	private function postcodeMatches( Address $address ): bool {
		return $this->postcodes === [] || PostcodePattern::anyMatches( $address->postcode, $this->postcodes );
	}
}
