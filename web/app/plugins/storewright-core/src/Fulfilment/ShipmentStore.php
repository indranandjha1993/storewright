<?php

declare(strict_types=1);

namespace Storewright\Core\Fulfilment;

use wpdb;

final class ShipmentStore {

	public const BOOKING   = 'booking';
	public const BOOKED    = 'booked';
	public const FAILED    = 'failed';
	public const CANCELLED = 'cancelled';

	private const CLOSED = [ self::FAILED, self::CANCELLED, TrackingEvent::DELIVERED, TrackingEvent::RETURNED, TrackingEvent::LOST ];

	public function __construct( private readonly wpdb $db ) {}

	public function find( int $fulfillmentId ): ?array {
		$row = $this->db->get_row( $this->db->prepare( "SELECT * FROM {$this->table()} WHERE fulfillment_id = %d", $fulfillmentId ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	public function findByAwb( string $awb ): ?array {
		$row = $this->db->get_row( $this->db->prepare( "SELECT * FROM {$this->table()} WHERE awb = %s", $awb ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/** @return list<array<string, mixed>> */
	public function forOrder( int $orderId ): array {
		return $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->table()} WHERE order_id = %d ORDER BY fulfillment_id", $orderId ), ARRAY_A ) ?: [];
	}

	/** @return list<array<string, mixed>> */
	public function open(): array {
		$holes = implode( ', ', array_fill( 0, count( self::CLOSED ), '%s' ) );
		return $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->table()} WHERE awb <> '' AND status NOT IN ({$holes}) ORDER BY updated_at", ...self::CLOSED ), ARRAY_A ) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function save( int $fulfillmentId, array $values ): void {
		$row     = $this->find( $fulfillmentId );
		$values += [ 'updated_at' => current_time( 'mysql', true ) ];
		if ( $row === null ) {
			$this->db->insert( $this->table(), [ 'fulfillment_id' => $fulfillmentId ] + $values );
			return;
		}
		$this->db->update( $this->table(), $values, [ 'fulfillment_id' => $fulfillmentId ] );
	}

	/** @param list<TrackingEvent> $events */
	public function addEvents( int $fulfillmentId, array $events, string $source ): int {
		$added = 0;
		foreach ( $events as $event ) {
			$done   = $this->db->query(
				$this->db->prepare(
					"INSERT IGNORE INTO {$this->eventsTable()} (fulfillment_id, status, raw_status, location, occurred_at, source) VALUES (%d, %s, %s, %s, %s, %s)",
					$fulfillmentId,
					$event->status,
					$event->raw,
					$event->location,
					gmdate( 'Y-m-d H:i:s', $event->at ),
					$source
				)
			);
			$added += (int) $done;
		}
		return $added;
	}

	/** @return list<array<string, mixed>> */
	public function events( int $fulfillmentId ): array {
		return $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->eventsTable()} WHERE fulfillment_id = %d ORDER BY occurred_at DESC, id DESC", $fulfillmentId ), ARRAY_A ) ?: [];
	}

	public function latest( int $fulfillmentId ): ?array {
		$row = $this->db->get_row( $this->db->prepare( "SELECT * FROM {$this->eventsTable()} WHERE fulfillment_id = %d ORDER BY occurred_at DESC, id DESC LIMIT 1", $fulfillmentId ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	public static function isClosed( string $status ): bool {
		return in_array( $status, self::CLOSED, true );
	}

	private function table(): string {
		return $this->db->prefix . 'storewright_shipments';
	}

	private function eventsTable(): string {
		return $this->db->prefix . 'storewright_shipment_events';
	}
}
