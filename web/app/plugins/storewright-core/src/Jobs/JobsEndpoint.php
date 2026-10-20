<?php

declare(strict_types=1);

namespace Storewright\Core\Jobs;

use ActionScheduler_Logger;
use ActionScheduler_Store;
use Storewright\Platform\Heartbeat;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class JobsEndpoint {

	private const OVERDUE_AFTER = '-5 minutes';

	private const FAILED_LIMIT = 50;

	public static function register(): void {
		$staff = static fn(): bool => current_user_can( 'manage_woocommerce' );
		register_rest_route(
			'storewright/v1',
			'/jobs',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'show' ],
				'permission_callback' => $staff,
			]
		);
		foreach ( [ 'retry', 'dismiss' ] as $action ) {
			register_rest_route(
				'storewright/v1',
				'/jobs/(?P<id>\d+)/' . $action,
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ self::class, $action ],
					'permission_callback' => $staff,
				]
			);
		}
	}

	public static function show(): WP_REST_Response {
		$store = ActionScheduler_Store::instance();
		$last  = Heartbeat::last();
		return new WP_REST_Response(
			[
				'runner'  => [
					'last'  => $last === null ? null : gmdate( 'c', $last ),
					'stale' => Heartbeat::stale( $last, time() ),
				],
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
							'per_page' => self::FAILED_LIMIT,
							'orderby'  => 'date',
							'order'    => 'DESC',
						]
					)
				),
			]
		);
	}

	public static function retry( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (string) $request['id'];
		if ( ! self::failed( $id ) ) {
			return new WP_Error( 'storewright_job_not_failed', __( 'Only a failed job can be run again.', 'storewright' ), [ 'status' => 409 ] );
		}
		$action = ActionScheduler_Store::instance()->fetch_action( $id );
		$again  = as_enqueue_async_action( $action->get_hook(), $action->get_args(), $action->get_group() );
		ActionScheduler_Store::instance()->cancel_action( $id );
		/* translators: %d: the new action id */
		ActionScheduler_Logger::instance()->log( $id, sprintf( __( 'Queued again from the console as job %d', 'storewright' ), $again ) );
		return new WP_REST_Response( [ 'id' => (int) $again ] );
	}

	public static function dismiss( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (string) $request['id'];
		if ( ! self::failed( $id ) ) {
			return new WP_Error( 'storewright_job_not_failed', __( 'Only a failed job can be dismissed.', 'storewright' ), [ 'status' => 409 ] );
		}
		ActionScheduler_Store::instance()->cancel_action( $id );
		ActionScheduler_Logger::instance()->log( $id, __( 'Dismissed from the console', 'storewright' ) );
		return new WP_REST_Response( [ 'id' => (int) $id ] );
	}

	private static function failed( string $id ): bool {
		try {
			return ActionScheduler_Store::instance()->get_status( $id ) === ActionScheduler_Store::STATUS_FAILED;
		} catch ( \InvalidArgumentException ) {
			return false;
		}
	}

	private static function describe( string|int $id ): array {
		$id     = (string) $id;
		$store  = ActionScheduler_Store::instance();
		$action = $store->fetch_action( $id );
		$logs   = ActionScheduler_Logger::instance()->get_logs( $id );
		$last   = end( $logs );
		return [
			'id'    => (int) $id,
			'hook'  => $action->get_hook(),
			'args'  => $action->get_args(),
			'group' => $action->get_group(),
			'date'  => $store->get_date( $id )->format( 'c' ),
			'log'   => $last ? $last->get_message() : '',
		];
	}
}
