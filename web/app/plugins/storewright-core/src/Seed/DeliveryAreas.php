<?php

declare(strict_types=1);

namespace Storewright\Core\Seed;

use Storewright\Core\Delivery\Circle;
use Storewright\Core\Delivery\Point;
use Storewright\Core\Delivery\ShapeStore;
use Storewright\Core\Setup\StoreDefaults;
use WC_Shipping_Zone;
use WC_Shipping_Zones;

final class DeliveryAreas {

	public const SAME_DAY = 'Bengaluru same-day';

	public function run(): int {
		$zone    = $this->find( self::SAME_DAY ) ?? $this->create();
		$country = $this->find( StoreDefaults::ZONE );
		if ( $country ) {
			$country->set_zone_order( 1 );
			$country->save();
		}
		global $wpdb;
		( new ShapeStore( $wpdb ) )->put( $zone->get_id(), new Circle( new Point( 12.9716, 77.5946 ), 12000 ) );
		return $zone->get_id();
	}

	private function create(): WC_Shipping_Zone {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( self::SAME_DAY );
		$zone->set_zone_order( 0 );
		$zone->add_location( 'IN', 'country' );
		$zone->add_location( '560*', 'postcode' );
		$zone->save();
		$method = WC_Shipping_Zones::get_shipping_method( $zone->add_shipping_method( 'flat_rate' ) );
		update_option(
			$method->get_instance_option_key(),
			[
				'title'      => __( 'Same-day delivery', 'storewright' ),
				'tax_status' => 'none',
				'cost'       => '49',
			]
		);
		return $zone;
	}

	private function find( string $name ): ?WC_Shipping_Zone {
		foreach ( WC_Shipping_Zones::get_zones( 'admin' ) as $zone ) {
			if ( $zone['zone_name'] === $name ) {
				return new WC_Shipping_Zone( (int) $zone['id'] );
			}
		}
		return null;
	}
}
