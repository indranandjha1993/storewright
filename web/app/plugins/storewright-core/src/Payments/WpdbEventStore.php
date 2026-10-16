<?php

declare(strict_types=1);

namespace Storewright\Core\Payments;

final class WpdbEventStore implements EventStore {

	private string $table;

	public function __construct( private readonly \wpdb $db ) {
		$this->table = $db->prefix . 'storewright_payment_events';
	}

	public function status( string $gateway, string $eventId ): ?string {
		$status = $this->db->get_var(
			$this->db->prepare( 'SELECT status FROM %i WHERE gateway = %s AND event_id = %s', $this->table, $gateway, $eventId )
		);
		return is_string( $status ) ? $status : null;
	}

	public function receive( string $gateway, string $eventId, string $eventType, string $payloadHash ): void {
		$this->db->query(
			$this->db->prepare(
				'INSERT INTO %i (gateway, event_id, event_type, status, payload_hash, received_at) VALUES (%s, %s, %s, %s, %s, UTC_TIMESTAMP())
				ON DUPLICATE KEY UPDATE deliveries = deliveries + 1',
				$this->table,
				$gateway,
				$eventId,
				$eventType,
				WebhookGuard::RECEIVED,
				$payloadHash
			)
		);
	}

	public function mark( string $gateway, string $eventId, string $status ): void {
		$this->db->query(
			$this->db->prepare(
				'UPDATE %i SET status = %s, processed_at = UTC_TIMESTAMP() WHERE gateway = %s AND event_id = %s',
				$this->table,
				$status,
				$gateway,
				$eventId
			)
		);
	}
}
