<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

final class Serviceability {

	public function __construct( private readonly array $zones ) {}

	public function check( Address $address, ?Point $point = null ): Answer {
		$fallback = null;
		foreach ( $this->zones as $zone ) {
			if ( ! $zone->covers( $address ) ) {
				continue;
			}
			if ( $zone->enabledMethods === 0 ) {
				$fallback ??= Answer::undeliverable( Answer::NO_METHODS, $zone );
				continue;
			}
			if ( $zone->shape === null ) {
				return Answer::deliverable( $zone );
			}
			if ( $point === null ) {
				$fallback = $fallback?->status === Answer::APPROXIMATE ? $fallback : Answer::approximate( $zone );
				continue;
			}
			if ( $zone->shape->contains( $point ) ) {
				return Answer::deliverable( $zone );
			}
			$fallback = $fallback?->status === Answer::APPROXIMATE ? $fallback : Answer::undeliverable( Answer::OUTSIDE_AREA, $zone );
		}
		return $fallback ?? Answer::undeliverable( Answer::NO_AREA );
	}
}
