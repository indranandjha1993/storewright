<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Storewright\Core\Checkout\PhoneSync;

final class PhoneSyncTest extends TestCase {

	public function test_a_shipping_phone_fills_an_empty_billing_phone(): void {
		self::assertSame( [ '9123456780', '9123456780' ], PhoneSync::fill( '', '9123456780' ) );
	}

	public function test_a_billing_phone_fills_an_empty_shipping_phone(): void {
		self::assertSame( [ '9123456780', '9123456780' ], PhoneSync::fill( ' 9123456780 ', '' ) );
	}

	public function test_two_different_phones_are_both_kept(): void {
		self::assertSame( [ '9000000001', '9000000002' ], PhoneSync::fill( '9000000001', '9000000002' ) );
	}

	public function test_no_phone_stays_empty(): void {
		self::assertSame( [ '', '' ], PhoneSync::fill( '', '' ) );
	}
}
