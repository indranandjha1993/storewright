<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform;

use Brain\Monkey\Functions;
use Storewright\Platform\Heartbeat;
use Storewright\Tests\TestCase;

final class HeartbeatTest extends TestCase {

	public function test_a_runner_that_never_reported_is_not_stale(): void {
		self::assertFalse( Heartbeat::stale( null, 1_000_000 ) );
	}

	public function test_a_recent_run_is_not_stale(): void {
		self::assertFalse( Heartbeat::stale( 1_000_000 - 14 * 60, 1_000_000 ) );
	}

	public function test_a_run_older_than_fifteen_minutes_is_stale(): void {
		self::assertTrue( Heartbeat::stale( 1_000_000 - 16 * 60, 1_000_000 ) );
	}

	public function test_only_the_cron_runner_records_a_heartbeat(): void {
		$writes = [];
		Functions\when( 'update_option' )->alias(
			static function ( string $name, mixed $value, bool $autoload ) use ( &$writes ): bool {
				$writes[] = [ $name, $autoload ];
				return true;
			}
		);

		Heartbeat::record( 'Async Request' );
		Heartbeat::record( 'WP Cron' );

		self::assertSame( [ [ Heartbeat::OPTION, false ] ], $writes );
	}

	public function test_last_reads_the_stored_time(): void {
		Functions\when( 'get_option' )->justReturn( '1700000000' );

		self::assertSame( 1700000000, Heartbeat::last() );
	}
}
