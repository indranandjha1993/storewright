<?php

declare(strict_types=1);

namespace Storewright\Core\Payments;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class PaymentsEndpoint {

	private const PER_PAGE = 25;

	public static function register(): void {
		$staff = static fn(): bool => current_user_can( 'manage_woocommerce' );
		register_rest_route(
			'storewright/v1',
			'/payments/events',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'events' ],
				'permission_callback' => $staff,
				'args'                => [
					'gateway' => [
						'type' => 'string',
						'enum' => [ '', 'razorpay', 'stripe' ],
					],
					'status'  => [
						'type' => 'string',
						'enum' => [ '', WebhookGuard::RECEIVED, WebhookGuard::PROCESSED, WebhookGuard::FAILED ],
					],
					'page'    => [
						'type'    => 'integer',
						'minimum' => 1,
						'default' => 1,
					],
				],
			]
		);
		register_rest_route(
			'storewright/v1',
			'/payments/unsettled',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static fn(): WP_REST_Response => new WP_REST_Response( Settlement::pending() ),
				'permission_callback' => $staff,
			]
		);
		register_rest_route(
			'storewright/v1',
			'/payments/unsettled/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ self::class, 'check' ],
				'permission_callback' => $staff,
			]
		);
	}

	public static function events( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$table   = $wpdb->prefix . 'storewright_payment_events';
		$gateway = (string) $request['gateway'];
		$status  = (string) $request['status'];
		$offset  = ( (int) $request['page'] - 1 ) * self::PER_PAGE;
		$total   = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE (%s = '' OR gateway = %s) AND (%s = '' OR status = %s)", $table, $gateway, $gateway, $status, $status )
		);
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT gateway, event_id, event_type, status, deliveries, received_at, processed_at FROM %i WHERE (%s = '' OR gateway = %s) AND (%s = '' OR status = %s) ORDER BY received_at DESC, id DESC LIMIT %d OFFSET %d",
				$table,
				$gateway,
				$gateway,
				$status,
				$status,
				self::PER_PAGE,
				$offset
			),
			ARRAY_A
		);
		$items   = array_map(
			static fn( array $row ): array => [
				'gateway'    => $row['gateway'],
				'id'         => $row['event_id'],
				'type'       => $row['event_type'],
				'status'     => $row['status'],
				'deliveries' => (int) $row['deliveries'],
				'received'   => get_date_from_gmt( (string) $row['received_at'], 'c' ),
				'processed'  => $row['processed_at'] ? get_date_from_gmt( (string) $row['processed_at'], 'c' ) : null,
			],
			(array) $rows
		);
		return new WP_REST_Response(
			[
				'items' => $items,
				'total' => $total,
				'pages' => max( 1, (int) ceil( $total / self::PER_PAGE ) ),
			]
		);
	}

	public static function check( WP_REST_Request $request ): WP_REST_Response {
		$order = wc_get_order( (int) $request['id'] );
		if ( ! $order instanceof \WC_Order ) {
			return new WP_REST_Response( [ 'message' => __( 'That order does not exist.', 'storewright' ) ], 404 );
		}
		$answer = Settlement::check( $order );
		return new WP_REST_Response(
			[
				...$answer,
				'order' => Settlement::describe( wc_get_order( $order->get_id() ) ?: $order ),
			]
		);
	}
}
