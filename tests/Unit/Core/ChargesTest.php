<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Core;

use Storewright\Core\Checkout\Charges;
use Storewright\Tests\TestCase;

final class ChargesTest extends TestCase {

	public function test_packing_is_charged_until_the_free_from_amount(): void {
		self::assertSame( 20.0, Charges::handling( 499.0, 20.0, 1000.0 ) );
		self::assertSame( 0.0, Charges::handling( 1000.0, 20.0, 1000.0 ) );
	}

	public function test_packing_is_always_charged_without_a_free_from_amount(): void {
		self::assertSame( 20.0, Charges::handling( 5000.0, 20.0, 0.0 ) );
	}

	public function test_no_packing_charge_when_the_fee_is_zero(): void {
		self::assertSame( 0.0, Charges::handling( 100.0, 0.0, 1000.0 ) );
	}

	public function test_cash_on_delivery_handling_applies_to_cash_only(): void {
		self::assertSame( 25.0, Charges::cashOnDelivery( 'cod', 25.0 ) );
		self::assertSame( 0.0, Charges::cashOnDelivery( 'razorpay', 25.0 ) );
		self::assertSame( 0.0, Charges::cashOnDelivery( 'cod', 0.0 ) );
	}
}
