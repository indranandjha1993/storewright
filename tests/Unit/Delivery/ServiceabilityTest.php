<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Delivery;

use PHPUnit\Framework\TestCase;
use Storewright\Core\Delivery\Address;
use Storewright\Core\Delivery\Answer;
use Storewright\Core\Delivery\Circle;
use Storewright\Core\Delivery\Point;
use Storewright\Core\Delivery\Serviceability;
use Storewright\Core\Delivery\Zone;

final class ServiceabilityTest extends TestCase {

	private const STORE = [ 12.9716, 77.5946 ];

	private static function city( int $methods = 1 ): Zone {
		return new Zone( 1, 'Bengaluru 5 km', [ [ 'country', 'IN' ] ], [ '560*' ], $methods, new Circle( new Point( ...self::STORE ), 5000 ) );
	}

	private static function karnataka(): Zone {
		return new Zone( 2, 'Karnataka', [ [ 'state', 'IN:KA' ] ], [], 1 );
	}

	private static function india(): Zone {
		return new Zone( 3, 'India', [ [ 'country', 'IN' ] ], [], 1 );
	}

	public function test_a_pin_inside_a_plain_zone_is_deliverable(): void {
		$answer = ( new Serviceability( [ self::india() ] ) )->check( new Address( 'IN', 'MH', '411001' ) );

		self::assertSame( Answer::DELIVERABLE, $answer->status );
		self::assertSame( 'India', $answer->zone->name );
	}

	public function test_no_matching_zone_is_undeliverable(): void {
		$answer = ( new Serviceability( [ self::karnataka() ] ) )->check( new Address( 'IN', 'MH', '411001' ) );

		self::assertSame( Answer::UNDELIVERABLE, $answer->status );
		self::assertSame( Answer::NO_AREA, $answer->reason );
	}

	public function test_a_zone_without_delivery_methods_never_delivers(): void {
		$answer = ( new Serviceability( [ new Zone( 9, 'Paused', [ [ 'country', 'IN' ] ], [], 0 ) ] ) )->check( new Address( 'IN', 'KA', '560001' ) );

		self::assertSame( Answer::NO_METHODS, $answer->reason );
		self::assertFalse( $answer->isDeliverable() );
	}

	public function test_the_first_matching_zone_wins(): void {
		$answer = ( new Serviceability( [ self::karnataka(), self::india() ] ) )->check( new Address( 'IN', 'KA', '560001' ) );

		self::assertSame( 'Karnataka', $answer->zone->name );
	}

	public function test_a_point_inside_the_radius_is_deliverable(): void {
		$answer = ( new Serviceability( [ self::city() ] ) )->check( new Address( 'IN', 'KA', '560034' ), new Point( 12.95, 77.60 ) );

		self::assertSame( Answer::DELIVERABLE, $answer->status );
	}

	public function test_a_point_outside_the_radius_falls_through_to_the_next_zone(): void {
		$service = new Serviceability( [ self::city(), self::karnataka() ] );

		self::assertSame( 'Karnataka', $service->check( new Address( 'IN', 'KA', '560064' ), new Point( 13.1, 77.6 ) )->zone->name );
	}

	public function test_a_point_outside_the_only_zone_is_outside_the_area(): void {
		$answer = ( new Serviceability( [ self::city() ] ) )->check( new Address( 'IN', 'KA', '560064' ), new Point( 13.1, 77.6 ) );

		self::assertSame( Answer::OUTSIDE_AREA, $answer->reason );
		self::assertSame( 1, $answer->zone->id );
	}

	public function test_a_radius_zone_without_a_point_is_approximate(): void {
		$answer = ( new Serviceability( [ self::city() ] ) )->check( new Address( 'IN', 'KA', '560034' ) );

		self::assertSame( Answer::APPROXIMATE, $answer->status );
		self::assertSame( Answer::NEEDS_POINT, $answer->reason );
	}

	public function test_a_plain_zone_after_a_radius_zone_still_delivers_without_a_point(): void {
		$answer = ( new Serviceability( [ self::city(), self::karnataka() ] ) )->check( new Address( 'IN', 'KA', '560034' ) );

		self::assertSame( Answer::DELIVERABLE, $answer->status );
		self::assertSame( 'Karnataka', $answer->zone->name );
	}

	public function test_postcode_rules_still_apply_to_a_geometry_zone(): void {
		$answer = ( new Serviceability( [ self::city() ] ) )->check( new Address( 'IN', 'KA', '570001' ), new Point( ...self::STORE ) );

		self::assertSame( Answer::NO_AREA, $answer->reason );
	}

	public function test_an_answer_reads_as_an_array(): void {
		self::assertSame(
			[
				'status' => 'deliverable',
				'reason' => 'covered',
				'area'   => [
					'id'   => 3,
					'name' => 'India',
				],
			],
			( new Serviceability( [ self::india() ] ) )->check( new Address( 'IN', 'KA', '560001' ) )->toArray()
		);
	}
}
