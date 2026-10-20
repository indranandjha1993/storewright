<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Storewright\Core\Fulfilment\Quote;
use Storewright\Core\Fulfilment\Shipping;

final class ShippingTest extends TestCase {

	public function test_an_order_is_delivered_when_every_live_outbound_parcel_is(): void {
		$delivered = [
			'kind'   => 'outbound',
			'status' => 'delivered',
		];
		$cancelled = [
			'kind'   => 'outbound',
			'status' => 'cancelled',
		];
		$failed    = [
			'kind'   => 'outbound',
			'status' => 'failed',
		];
		$moving    = [
			'kind'   => 'outbound',
			'status' => 'in_transit',
		];
		$pickup    = [
			'kind'   => 'return',
			'status' => 'booked',
		];

		self::assertTrue( Shipping::allDelivered( [ $delivered ] ) );
		self::assertTrue( Shipping::allDelivered( [ $cancelled, $delivered, $failed, $pickup ] ) );
		self::assertFalse( Shipping::allDelivered( [ $delivered, $moving ] ) );
		self::assertFalse( Shipping::allDelivered( [ $cancelled ] ) );
		self::assertFalse( Shipping::allDelivered( [] ) );
	}

	public function test_the_recommended_courier_wins_else_the_cheapest(): void {
		$cheap       = new Quote( '51', 'Xpressbees Surface', 58, 'Oct 22' );
		$recommended = new Quote( '1', 'Blue Dart', 60, 'Oct 22', true );

		self::assertSame( '1', Shipping::bestService( [ $cheap, $recommended ] ) );
		self::assertSame( '51', Shipping::bestService( [ $cheap, new Quote( '54', 'Ekart', 65, 'Oct 22' ) ] ) );
	}
}
