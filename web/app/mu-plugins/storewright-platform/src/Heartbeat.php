<?php

declare(strict_types=1);

namespace Storewright\Platform;

final class Heartbeat {

	public const OPTION = 'storewright_cron_heartbeat';

	public const STALE_AFTER = 15 * 60;

	public static function register(): void {
		add_action( 'action_scheduler_run_queue', [ self::class, 'record' ], 5 );
	}

	public static function record( string $context = 'WP Cron' ): void {
		if ( $context !== 'WP Cron' ) {
			return;
		}
		update_option( self::OPTION, time(), false );
	}

	public static function last(): ?int {
		$last = get_option( self::OPTION );
		return is_numeric( $last ) ? (int) $last : null;
	}

	public static function stale( ?int $last, int $now ): bool {
		return $last !== null && $now - $last > self::STALE_AFTER;
	}

	public static function healthy(): bool {
		return ! self::stale( self::last(), time() );
	}
}
