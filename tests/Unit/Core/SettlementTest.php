<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Storewright\Core\Payments\Settlement;

final class SettlementTest extends TestCase {

	public function test_a_captured_payment_settles_the_order(): void {
		self::assertSame( 'pay_ok', Settlement::capturedPayment( [ self::payment( 'pay_failed', 'failed' ), self::payment( 'pay_ok', 'captured' ) ] ) );
	}

	public function test_authorised_or_failed_payments_do_not(): void {
		self::assertNull( Settlement::capturedPayment( [ self::payment( 'pay_a', 'authorized' ), self::payment( 'pay_b', 'failed' ) ] ) );
		self::assertNull( Settlement::capturedPayment( [] ) );
		self::assertNull( Settlement::capturedPayment( [ 'not a payment' ] ) );
	}

	private static function payment( string $id, string $status ): array {
		return [
			'id'     => $id,
			'status' => $status,
		];
	}
}
