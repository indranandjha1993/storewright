<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

use InvalidArgumentException;

final class Point {

	private const EARTH_RADIUS_M = 6371008.8;

	public function __construct( public readonly float $lat, public readonly float $lng ) {
		if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || is_nan( $lat ) || is_nan( $lng ) ) {
			throw new InvalidArgumentException( 'Latitude must be within -90..90 and longitude within -180..180.' );
		}
	}

	public function metresTo( Point $other ): float {
		$phi1 = deg2rad( $this->lat );
		$phi2 = deg2rad( $other->lat );
		$dPhi = $phi2 - $phi1;
		$dLam = deg2rad( $other->lng - $this->lng );
		$a    = sin( $dPhi / 2 ) ** 2 + cos( $phi1 ) * cos( $phi2 ) * sin( $dLam / 2 ) ** 2;
		return 2 * self::EARTH_RADIUS_M * asin( min( 1.0, sqrt( $a ) ) );
	}
}
