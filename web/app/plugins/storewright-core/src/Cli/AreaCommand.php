<?php

declare(strict_types=1);

namespace Storewright\Core\Cli;

use Storewright\Core\Delivery\Circle;
use Storewright\Core\Delivery\Point;
use Storewright\Core\Delivery\ShapeCodec;
use Storewright\Core\Delivery\ShapeStore;
use Storewright\Core\Delivery\WooZones;
use Throwable;
use WP_CLI;

final class AreaCommand {

	public function list(): void {
		$rows = array_map(
			static fn( $zone ): array => [
				'id'        => $zone->id,
				'area'      => $zone->name,
				'regions'   => implode( ' ', array_map( static fn( array $r ): string => $r[1], $zone->regions ) ) ?: 'any',
				'postcodes' => implode( ' ', $zone->postcodes ) ?: 'any',
				'methods'   => $zone->enabledMethods,
				'shape'     => $zone->shape ? $zone->shape->toArray()['kind'] : 'none',
			],
			WooZones::load()
		);
		WP_CLI\Utils\format_items( 'table', $rows, [ 'id', 'area', 'regions', 'postcodes', 'methods', 'shape' ] );
	}

	public function radius( array $args ): void {
		[ $zone, $lat, $lng, $km ] = array_pad( $args, 4, null );
		$this->save( (int) $zone, static fn() => new Circle( new Point( (float) $lat, (float) $lng ), (float) $km * 1000 ) );
	}

	public function polygon( array $args ): void {
		[ $zone, $file ] = array_pad( $args, 2, '' );
		if ( ! is_readable( (string) $file ) ) {
			WP_CLI::error( "Cannot read {$file}." );
		}
		$this->save( (int) $zone, static fn() => ShapeCodec::fromGeoJson( (string) file_get_contents( (string) $file ) ) );
	}

	public function clear( array $args ): void {
		global $wpdb;
		( new ShapeStore( $wpdb ) )->clear( (int) ( $args[0] ?? 0 ) );
		WP_CLI::success( 'Shape removed; the area now follows its regions and PIN rules only.' );
	}

	private function save( int $zone, callable $build ): void {
		try {
			$shape = $build();
		} catch ( Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
			return;
		}
		global $wpdb;
		( new ShapeStore( $wpdb ) )->put( $zone, $shape );
		WooZones::forget();
		WP_CLI::success( sprintf( 'Area %d now has a %s.', $zone, $shape->toArray()['kind'] ) );
	}
}
