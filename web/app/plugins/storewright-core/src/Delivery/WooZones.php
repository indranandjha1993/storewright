<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

use WC_Shipping_Zone;
use WC_Shipping_Zones;

final class WooZones {

	private static ?array $cache = null;

	public static function load(): array {
		if ( self::$cache !== null ) {
			return self::$cache;
		}
		global $wpdb;
		$shapes     = ( new ShapeStore( $wpdb ) )->all();
		$continents = WC()->countries->get_continents();
		$zones      = [];
		foreach ( WC_Shipping_Zones::get_zones( 'admin' ) as $data ) {
			$zones[] = self::zone( new WC_Shipping_Zone( (int) $data['id'] ), $shapes, $continents );
		}
		$zones[]     = self::zone( new WC_Shipping_Zone( 0 ), $shapes, $continents );
		self::$cache = $zones;
		return $zones;
	}

	public static function forget(): void {
		self::$cache = null;
	}

	private static function zone( WC_Shipping_Zone $zone, array $shapes, array $continents ): Zone {
		$regions   = [];
		$postcodes = [];
		foreach ( $zone->get_zone_locations() as $location ) {
			match ( $location->type ) {
				'postcode'  => $postcodes[] = $location->code,
				'continent' => array_push( $regions, ...array_map( static fn( string $c ): array => [ 'country', $c ], $continents[ $location->code ]['countries'] ?? [] ) ),
				default     => $regions[]   = [ $location->type, $location->code ],
			};
		}
		return new Zone(
			$zone->get_id(),
			$zone->get_id() === 0 ? __( 'Everywhere else', 'storewright' ) : $zone->get_zone_name(),
			$regions,
			$postcodes,
			count( $zone->get_shipping_methods( true ) ),
			$shapes[ $zone->get_id() ] ?? null,
		);
	}
}
