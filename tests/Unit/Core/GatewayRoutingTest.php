<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Storewright\Core\Payments\CashOnDelivery;
use Storewright\Core\Payments\GatewayRouting;

final class GatewayRoutingTest extends TestCase {

	public function test_an_inr_store_offers_razorpay_first(): void {
		self::assertSame(
			[
				'razorpay' => 0,
				'stripe'   => 1,
				'cod'      => 2,
			],
			GatewayRouting::order( 'INR' )
		);
		self::assertSame(
			[
				'razorpay' => 0,
				'stripe'   => 1,
				'cod'      => 2,
			],
			GatewayRouting::order( 'inr' )
		);
	}

	public function test_any_other_currency_offers_stripe_first(): void {
		self::assertSame(
			[
				'stripe'   => 0,
				'razorpay' => 1,
				'cod'      => 2,
			],
			GatewayRouting::order( 'USD' )
		);
	}

	public function test_cash_on_delivery_needs_every_item_to_ship(): void {
		$cod = new CashOnDelivery( 10000, [] );

		self::assertTrue( $cod->allows( true, 749, '560001' ) );
		self::assertFalse( $cod->allows( false, 749, '560001' ) );
	}

	public function test_cash_on_delivery_stops_above_the_limit(): void {
		$cod = new CashOnDelivery( 1000, [] );

		self::assertTrue( $cod->allows( true, 1000, '560001' ) );
		self::assertFalse( $cod->allows( true, 1000.01, '560001' ) );
	}

	public function test_cash_on_delivery_follows_the_pin_list_when_there_is_one(): void {
		$cod = new CashOnDelivery( 10000, [ '560*', '411001...411062' ] );

		self::assertTrue( $cod->allows( true, 500, '560103' ) );
		self::assertTrue( $cod->allows( true, 500, '411030' ) );
		self::assertFalse( $cod->allows( true, 500, '400001' ) );
	}
}
