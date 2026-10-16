<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

use Throwable;

final class ShapeStore {

	private string $table;

	public function __construct( private readonly \wpdb $db ) {
		$this->table = $db->prefix . 'storewright_area_geometry';
	}

	public function all(): array {
		$shapes = [];
		$rows   = $this->db->get_results( $this->db->prepare( 'SELECT zone_id, shape FROM %i', $this->table ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			try {
				$shapes[ (int) $row['zone_id'] ] = ShapeCodec::fromArray( (array) json_decode( (string) $row['shape'], true ) );
			} catch ( Throwable ) {
				continue;
			}
		}
		return $shapes;
	}

	public function put( int $zoneId, Shape $shape ): void {
		$this->db->replace(
			$this->table,
			[
				'zone_id'    => $zoneId,
				'shape'      => (string) wp_json_encode( $shape->toArray() ),
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			],
			[ '%d', '%s', '%s' ]
		);
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
	}

	public function clear( int $zoneId ): void {
		$this->db->delete( $this->table, [ 'zone_id' => $zoneId ], [ '%d' ] );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
	}

	public static function zoneDeleted( int $zoneId ): void {
		global $wpdb;
		( new self( $wpdb ) )->clear( $zoneId );
	}
}
