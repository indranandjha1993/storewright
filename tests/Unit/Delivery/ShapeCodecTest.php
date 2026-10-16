<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Delivery;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Storewright\Core\Delivery\Circle;
use Storewright\Core\Delivery\Point;
use Storewright\Core\Delivery\Polygon;
use Storewright\Core\Delivery\ShapeCodec;

final class ShapeCodecTest extends TestCase {

	public function test_a_stored_radius_and_polygon_read_back_unchanged(): void {
		$circle  = new Circle( new Point( 12.97, 77.59 ), 5000 );
		$polygon = new Polygon( new Point( 0, 0 ), new Point( 0, 1 ), new Point( 1, 1 ) );

		self::assertSame( $circle->toArray(), ShapeCodec::fromArray( $circle->toArray() )->toArray() );
		self::assertSame( $polygon->toArray(), ShapeCodec::fromArray( $polygon->toArray() )->toArray() );
	}

	public function test_geojson_is_read_as_longitude_then_latitude(): void {
		$json = '{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[77.58,12.96],[77.61,12.96],[77.61,12.99],[77.58,12.99],[77.58,12.96]]]}}';

		$polygon = ShapeCodec::fromGeoJson( $json );

		self::assertCount( 4, $polygon->vertices );
		self::assertSame( 12.96, $polygon->vertices[0]->lat );
		self::assertSame( 77.58, $polygon->vertices[0]->lng );
		self::assertTrue( $polygon->contains( new Point( 12.97, 77.59 ) ) );
	}

	public function test_a_feature_collection_uses_its_first_polygon(): void {
		$json = '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[0,0],[1,0],[1,1],[0,0]]]}}]}';

		self::assertCount( 3, ShapeCodec::fromGeoJson( $json )->vertices );
	}

	public function test_anything_but_a_polygon_is_refused(): void {
		$this->expectException( InvalidArgumentException::class );
		ShapeCodec::fromGeoJson( '{"type":"Point","coordinates":[77.59,12.97]}' );
	}
}
