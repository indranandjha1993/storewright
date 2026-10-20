<?php

declare(strict_types=1);

namespace Storewright\Core\Fulfilment;

use wpdb;

final class ShipmentStore {

	public const OUTBOUND = 'outbound';
	public const RETURN   = 'return';

	public const BOOKING   = 'booking';
	public const BOOKED    = 'booked';
	public const FAILED    = 'failed';
	public const CANCELLED = 'cancelled';

	private const CLOSED = [ self::FAILED, self::CANCELLED, TrackingEvent::DELIVERED, TrackingEvent::RETURNED, TrackingEvent::LOST ];

	public function __construct( private readonly wpdb $db ) {}

	public function find( int $id ): ?array {
		return $this->row( $this->db->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ) );
	}

	public function findByAwb( string $awb ): ?array {
		return $this->row( $this->db->prepare( "SELECT * FROM {$this->table()} WHERE awb = %s ORDER BY id DESC LIMIT 1", $awb ) );
	}

	public function forFulfillment( int $fulfillmentId ): ?array {
		return $this->row( $this->db->prepare( "SELECT * FROM {$this->table()} WHERE fulfillment_id = %d ORDER BY id DESC LIMIT 1", $fulfillmentId ) );
	}

	public function forReturn( int $orderId, string $returnId ): ?array {
		return $this->row( $this->db->prepare( "SELECT * FROM {$this->table()} WHERE order_id = %d AND return_id = %s AND status <> %s ORDER BY id DESC LIMIT 1", $orderId, $returnId, self::CANCELLED ) );
	}

	/** @return list<array<string, mixed>> */
	public function forOrder( int $orderId ): array {
		return $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->table()} WHERE order_id = %d ORDER BY id", $orderId ), ARRAY_A ) ?: [];
	}

	/** @return list<array<string, mixed>> */
	public function open(): array {
		$holes = implode( ', ', array_fill( 0, count( self::CLOSED ), '%s' ) );
		return $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->table()} WHERE awb <> '' AND status NOT IN ({$holes}) ORDER BY updated_at", ...self::CLOSED ), ARRAY_A ) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function insert( array $values ): int {
		$this->db->insert( $this->table(), $values + [ 'updated_at' => current_time( 'mysql', true ) ] );
		return (int) $this->db->insert_id;
	}

	public function save( int $id, array $values ): void {
		$this->db->update( $this->table(), $values + [ 'updated_at' => current_time( 'mysql', true ) ], [ 'id' => $id ] );
	}

	/** @param list<TrackingEvent> $events */
	public function addEvents( int $id, array $events, string $source ): int {
		$added = 0;
		foreach ( $events as $event ) {
			$done   = $this->db->query(
				$this->db->prepare(
					"INSERT IGNORE INTO {$this->eventsTable()} (shipment_id, status, raw_status, location, occurred_at, source) VALUES (%d, %s, %s, %s, %s, %s)",
					$id,
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
	public function events( int $id ): array {
		return $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->eventsTable()} WHERE shipment_id = %d ORDER BY occurred_at DESC, id DESC", $id ), ARRAY_A ) ?: [];
	}

	public function latest( int $id ): ?array {
		return $this->row( $this->db->prepare( "SELECT * FROM {$this->eventsTable()} WHERE shipment_id = %d ORDER BY occurred_at DESC, id DESC LIMIT 1", $id ) );
	}

	public function latestForFulfillment( int $fulfillmentId ): ?array {
		$shipment = $this->forFulfillment( $fulfillmentId );
		return $shipment === null ? null : $this->latest( (int) $shipment['id'] );
	}

	public static function isClosed( string $status ): bool {
		return in_array( $status, self::CLOSED, true );
	}

	private function row( string $sql ): ?array {
		$row = $this->db->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $row ) ? $row : null;
	}

	private function table(): string {
		return $this->db->prefix . 'storewright_shipments';
	}

	private function eventsTable(): string {
		return $this->db->prefix . 'storewright_shipment_events';
	}
}
