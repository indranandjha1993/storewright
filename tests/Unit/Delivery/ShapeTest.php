<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Delivery;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Storewright\Core\Delivery\Circle;
use Storewright\Core\Delivery\Point;
use Storewright\Core\Delivery\Polygon;

final class ShapeTest extends TestCase {

	public function test_one_degree_of_latitude_is_about_111_km(): void {
		self::assertEqualsWithDelta( 111195, ( new Point( 12, 77 ) )->metresTo( new Point( 13, 77 ) ), 50 );
		self::assertSame( 0.0, ( new Point( 12.97, 77.59 ) )->metresTo( new Point( 12.97, 77.59 ) ) );
	}

	public function test_a_point_out_of_range_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		new Point( 91, 0 );
	}

	public function test_a_radius_contains_points_up_to_and_on_its_edge(): void {
		$store = new Point( 12.9716, 77.5946 );
		$five  = new Circle( $store, 5000 );

		self::assertTrue( $five->contains( new Point( 12.95, 77.60 ) ) );
		self::assertFalse( $five->contains( new Point( 13.0358, 77.5970 ) ) );

		$edge = new Point( 12.9716 + 5000 / 111195, 77.5946 );
		self::assertTrue( ( new Circle( $store, $store->metresTo( $edge ) ) )->contains( $edge ) );
	}

	public function test_a_radius_must_be_positive(): void {
		$this->expectException( InvalidArgumentException::class );
		new Circle( new Point( 12, 77 ), 0 );
	}

	public function test_a_square_contains_its_inside_edges_and_corners_but_not_outside(): void {
		$square = new Polygon( new Point( 0, 0 ), new Point( 0, 10 ), new Point( 10, 10 ), new Point( 10, 0 ) );

		self::assertTrue( $square->contains( new Point( 5, 5 ) ) );
		self::assertTrue( $square->contains( new Point( 0, 5 ) ) );
		self::assertTrue( $square->contains( new Point( 10, 10 ) ) );
		self::assertFalse( $square->contains( new Point( 11, 5 ) ) );
		self::assertFalse( $square->contains( new Point( -0.001, 5 ) ) );
	}

	public function test_a_concave_polygon_excludes_its_notch(): void {
		$u = new Polygon( new Point( 0, 0 ), new Point( 0, 9 ), new Point( 9, 9 ), new Point( 9, 6 ), new Point( 3, 6 ), new Point( 3, 3 ), new Point( 9, 3 ), new Point( 9, 0 ) );

		self::assertTrue( $u->contains( new Point( 1, 4.5 ) ) );
		self::assertFalse( $u->contains( new Point( 6, 4.5 ) ) );
		self::assertTrue( $u->contains( new Point( 6, 1.5 ) ) );
	}

	public function test_a_closed_ring_is_accepted_and_two_points_are_not(): void {
		$closed = new Polygon( new Point( 0, 0 ), new Point( 0, 1 ), new Point( 1, 1 ), new Point( 0, 0 ) );
		self::assertCount( 3, $closed->vertices );

		$this->expectException( InvalidArgumentException::class );
		new Polygon( new Point( 0, 0 ), new Point( 1, 1 ) );
	}
}
