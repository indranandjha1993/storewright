<?php

declare(strict_types=1);

namespace Storewright\Core\Payments;

final class EventLog {

	public const PER_PAGE = 25;

	public static function page( string $gateway, string $status, int $page ): array {
		global $wpdb;
		$table  = $wpdb->prefix . 'storewright_payment_events';
		$offset = ( max( 1, $page ) - 1 ) * self::PER_PAGE;
		$total  = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE (%s = '' OR gateway = %s) AND (%s = '' OR status = %s)", $table, $gateway, $gateway, $status, $status )
		);
		$rows   = $wpdb->get_results(
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
		$items  = array_map(
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
		return [
			'items' => $items,
			'total' => $total,
			'pages' => max( 1, (int) ceil( $total / self::PER_PAGE ) ),
		];
	}
}
