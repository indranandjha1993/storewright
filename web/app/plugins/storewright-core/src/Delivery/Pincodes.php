<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

final class Pincodes {

	private string $table;

	public function __construct( private readonly \wpdb $db ) {
		$this->table = $db->prefix . 'storewright_pincodes';
	}

	public function find( string $pin ): ?array {
		$row = $this->db->get_row( $this->db->prepare( 'SELECT pin, district, state, lat, lng FROM %i WHERE pin = %s', $this->table, preg_replace( '/\D/', '', $pin ) ), ARRAY_A );
		return $row ? self::shape( $row ) : null;
	}

	public function nearest( Point $point ): ?array {
		foreach ( [ 0.05, 0.2, 0.8, 3.0 ] as $span ) {
			$rows = $this->db->get_results(
				$this->db->prepare(
					'SELECT pin, district, state, lat, lng FROM %i WHERE lat BETWEEN %f AND %f AND lng BETWEEN %f AND %f',
					$this->table,
					$point->lat - $span,
					$point->lat + $span,
					$point->lng - $span,
					$point->lng + $span
				),
				ARRAY_A
			);
			if ( $rows ) {
				usort( $rows, static fn( array $a, array $b ): int => self::distance( $point, $a ) <=> self::distance( $point, $b ) );
				return self::shape( $rows[0] );
			}
		}
		return null;
	}

	public function replaceAll( array $pins ): int {
		$this->db->query( $this->db->prepare( 'TRUNCATE TABLE %i', $this->table ) );
		foreach ( array_chunk( $pins, 500 ) as $chunk ) {
			$values = [];
			foreach ( $chunk as $p ) {
				$values[] = $p['point']
					? $this->db->prepare( '(%s, %s, %s, %f, %f, %d)', $p['pin'], $p['district'], $p['state'], $p['point']->lat, $p['point']->lng, $p['offices'] )
					: $this->db->prepare( '(%s, %s, %s, NULL, NULL, %d)', $p['pin'], $p['district'], $p['state'], $p['offices'] );
			}
			$this->db->query( "INSERT INTO {$this->table} (pin, district, state, lat, lng, offices) VALUES " . implode( ',', $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return count( $pins );
	}

	private static function distance( Point $point, array $row ): float {
		return $point->metresTo( new Point( (float) $row['lat'], (float) $row['lng'] ) );
	}

	private static function shape( array $row ): array {
		return [
			'pin'      => $row['pin'],
			'district' => $row['district'],
			'state'    => $row['state'],
			'point'    => $row['lat'] !== null && $row['lng'] !== null ? new Point( (float) $row['lat'], (float) $row['lng'] ) : null,
		];
	}
}
