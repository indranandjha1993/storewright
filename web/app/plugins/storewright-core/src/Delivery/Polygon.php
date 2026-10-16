<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

use InvalidArgumentException;

final class Polygon implements Shape {

	private const EDGE_TOLERANCE = 1e-9;

	public readonly array $vertices;

	public function __construct( Point ...$vertices ) {
		if ( count( $vertices ) > 1 && self::same( $vertices[0], $vertices[ count( $vertices ) - 1 ] ) ) {
			array_pop( $vertices );
		}
		if ( count( $vertices ) < 3 ) {
			throw new InvalidArgumentException( 'A delivery polygon needs at least three corners.' );
		}
		$this->vertices = $vertices;
	}

	public function contains( Point $point ): bool {
		$inside = false;
		$count  = count( $this->vertices );
		for ( $i = 0, $j = $count - 1; $i < $count; $j = $i++ ) {
			$a = $this->vertices[ $i ];
			$b = $this->vertices[ $j ];
			if ( self::onSegment( $point, $a, $b ) ) {
				return true;
			}
			if ( ( $a->lat > $point->lat ) !== ( $b->lat > $point->lat )
				&& $point->lng < ( $b->lng - $a->lng ) * ( $point->lat - $a->lat ) / ( $b->lat - $a->lat ) + $a->lng ) {
				$inside = ! $inside;
			}
		}
		return $inside;
	}

	public function toArray(): array {
		return [
			'kind'    => 'polygon',
			'polygon' => array_map( static fn( Point $p ): array => [ $p->lat, $p->lng ], $this->vertices ),
		];
	}

	private static function same( Point $a, Point $b ): bool {
		return $a->lat === $b->lat && $a->lng === $b->lng;
	}

	private static function onSegment( Point $p, Point $a, Point $b ): bool {
		$cross = ( $p->lat - $a->lat ) * ( $b->lng - $a->lng ) - ( $p->lng - $a->lng ) * ( $b->lat - $a->lat );
		if ( abs( $cross ) > self::EDGE_TOLERANCE ) {
			return false;
		}
		return $p->lat >= min( $a->lat, $b->lat ) - self::EDGE_TOLERANCE && $p->lat <= max( $a->lat, $b->lat ) + self::EDGE_TOLERANCE
			&& $p->lng >= min( $a->lng, $b->lng ) - self::EDGE_TOLERANCE && $p->lng <= max( $a->lng, $b->lng ) + self::EDGE_TOLERANCE;
	}
}
