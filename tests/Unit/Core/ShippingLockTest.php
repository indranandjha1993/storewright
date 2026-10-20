<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Core;

use Brain\Monkey\Functions;
use Storewright\Core\Fulfilment\CarrierException;
use Storewright\Core\Fulfilment\Shipping;
use Storewright\Tests\TestCase;

final class ShippingLockTest extends TestCase {

	public function test_a_second_booking_on_the_same_order_is_refused_while_the_first_runs(): void {
		Functions\when( 'get_option' )->justReturn( (string) time() );
		Functions\when( 'add_option' )->justReturn( false );
		Functions\when( 'esc_html__' )->returnArg();

		$this->expectException( CarrierException::class );
		$this->expectExceptionMessage( 'already on its way' );

		Shipping::lock( 1042 );
	}

	public function test_a_lock_left_behind_by_a_crash_is_cleared_after_a_minute(): void {
		Functions\when( 'get_option' )->justReturn( (string) ( time() - 120 ) );
		Functions\expect( 'delete_option' )->once()->with( 'storewright_booking_1042' );
		Functions\when( 'add_option' )->justReturn( true );

		Shipping::lock( 1042 );
		self::assertTrue( true );
	}
}
