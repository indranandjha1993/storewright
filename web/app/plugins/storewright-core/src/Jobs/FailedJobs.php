<?php

declare(strict_types=1);

namespace Storewright\Core\Jobs;

use ActionScheduler_Logger;
use ActionScheduler_Store;
use InvalidArgumentException;
use Storewright\Platform\Heartbeat;

final class FailedJobs {

	private const OVERDUE_AFTER = '-5 minutes';

	private const LIMIT = 20;

	public static function summary(): array {
		$store = ActionScheduler_Store::instance();
		$last  = Heartbeat::last();
		return [
			'last'    => $last,
			'stale'   => Heartbeat::stale( $last, time() ),
			'pending' => (int) $store->query_actions( [ 'status' => ActionScheduler_Store::STATUS_PENDING ], 'count' ),
			'overdue' => (int) $store->query_actions(
				[
					'status'       => ActionScheduler_Store::STATUS_PENDING,
					'date'         => as_get_datetime_object( self::OVERDUE_AFTER ),
					'date_compare' => '<=',
				],
				'count'
			),
			'failed'  => array_map(
				[ self::class, 'describe' ],
				$store->query_actions(
					[
						'status'   => ActionScheduler_Store::STATUS_FAILED,
						'per_page' => self::LIMIT,
						'orderby'  => 'date',
						'order'    => 'DESC',
					]
				)
			),
		];
	}

	public static function retry( string $id ): int {
		self::assertFailed( $id );
		$store  = ActionScheduler_Store::instance();
		$action = $store->fetch_action( $id );
		$again  = as_enqueue_async_action( $action->get_hook(), $action->get_args(), $action->get_group() );
		$store->cancel_action( $id );
		/* translators: %d: the new job id */
		ActionScheduler_Logger::instance()->log( $id, sprintf( __( 'Queued again from the dashboard as job %d', 'storewright' ), $again ) );
		return (int) $again;
	}

	public static function dismiss( string $id ): void {
		self::assertFailed( $id );
		ActionScheduler_Store::instance()->cancel_action( $id );
		ActionScheduler_Logger::instance()->log( $id, __( 'Dismissed from the dashboard', 'storewright' ) );
	}

	private static function assertFailed( string $id ): void {
		try {
			$status = ActionScheduler_Store::instance()->get_status( $id );
		} catch ( InvalidArgumentException ) {
			$status = '';
		}
		if ( $status !== ActionScheduler_Store::STATUS_FAILED ) {
			throw new InvalidArgumentException( esc_html__( 'Only a failed job can be run again or dismissed.', 'storewright' ) );
		}
	}

	private static function describe( string|int $id ): array {
		$id     = (string) $id;
		$store  = ActionScheduler_Store::instance();
		$action = $store->fetch_action( $id );
		$logs   = ActionScheduler_Logger::instance()->get_logs( $id );
		$last   = end( $logs );
		return [
			'id'   => (int) $id,
			'hook' => $action->get_hook(),
			'args' => $action->get_args(),
			'date' => $store->get_date( $id )->getTimestamp(),
			'log'  => $last ? $last->get_message() : '',
		];
	}
}
