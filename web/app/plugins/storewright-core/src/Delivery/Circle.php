<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

use InvalidArgumentException;

final class Circle implements Shape {

	public function __construct( public readonly Point $centre, public readonly float $radiusMetres ) {
		if ( $radiusMetres <= 0 ) {
			throw new InvalidArgumentException( 'A delivery radius must be greater than zero.' );
		}
	}

	public function contains( Point $point ): bool {
		return $this->centre->metresTo( $point ) <= $this->radiusMetres;
	}

	public function toArray(): array {
		return [
			'kind'     => 'radius',
			'lat'      => $this->centre->lat,
			'lng'      => $this->centre->lng,
			'radius_m' => $this->radiusMetres,
		];
	}
}
