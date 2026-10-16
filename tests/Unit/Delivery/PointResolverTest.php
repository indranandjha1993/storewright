<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Delivery;

use PHPUnit\Framework\TestCase;
use Storewright\Core\Delivery\Point;
use Storewright\Core\Delivery\PointResolver;

final class PointResolverTest extends TestCase {

	public function test_without_a_shared_location_the_pin_point_is_used(): void {
		$pin = new Point( 12.98, 77.59 );

		self::assertSame( $pin, PointResolver::choose( null, $pin ) );
		self::assertNull( PointResolver::choose( null, null ) );
	}

	public function test_a_shared_location_near_the_address_wins(): void {
		$shared = new Point( 12.95, 77.60 );

		self::assertSame( $shared, PointResolver::choose( $shared, new Point( 12.98, 77.59 ) ) );
	}

	public function test_a_shared_location_far_from_the_address_is_ignored(): void {
		$pin = new Point( 18.52, 73.86 );

		self::assertSame( $pin, PointResolver::choose( new Point( 12.95, 77.60 ), $pin ) );
	}

	public function test_a_shared_location_is_used_when_the_pin_has_no_point(): void {
		$shared = new Point( 12.95, 77.60 );

		self::assertSame( $shared, PointResolver::choose( $shared, null ) );
	}
}
