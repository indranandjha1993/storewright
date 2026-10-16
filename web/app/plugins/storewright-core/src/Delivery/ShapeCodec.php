<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

use InvalidArgumentException;

final class ShapeCodec {

	public static function fromArray( array $data ): Shape {
		return match ( $data['kind'] ?? '' ) {
			'radius'  => new Circle( new Point( (float) $data['lat'], (float) $data['lng'] ), (float) $data['radius_m'] ),
			'polygon' => new Polygon( ...array_map( static fn( array $p ): Point => new Point( (float) $p[0], (float) $p[1] ), (array) $data['polygon'] ) ),
			default   => throw new InvalidArgumentException( 'Unknown shape.' ),
		};
	}

	public static function fromGeoJson( string $json ): Polygon {
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			throw new InvalidArgumentException( 'The file is not valid JSON.' );
		}
		$geometry = $data['type'] === 'FeatureCollection' ? ( $data['features'][0]['geometry'] ?? [] ) : ( $data['geometry'] ?? $data );
		if ( ( $geometry['type'] ?? '' ) !== 'Polygon' || empty( $geometry['coordinates'][0] ) ) {
			throw new InvalidArgumentException( 'Expected a GeoJSON Polygon.' );
		}
		return new Polygon( ...array_map( static fn( array $c ): Point => new Point( (float) $c[1], (float) $c[0] ), $geometry['coordinates'][0] ) );
	}
}
