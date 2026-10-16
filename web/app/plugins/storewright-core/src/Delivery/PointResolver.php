<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

final class PointResolver {

	private const SHARED_POINT_RANGE_M = 15000;

	public static function choose( ?Point $shared, ?Point $pinCentre ): ?Point {
		if ( $shared === null ) {
			return $pinCentre;
		}
		if ( $pinCentre === null || $shared->metresTo( $pinCentre ) <= self::SHARED_POINT_RANGE_M ) {
			return $shared;
		}
		return $pinCentre;
	}
}
