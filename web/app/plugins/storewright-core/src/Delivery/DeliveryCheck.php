<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

final class DeliveryCheck {

	public static function answer( Address $address, ?Point $point = null ): Answer {
		return ( new Serviceability( WooZones::load() ) )->check( $address, $point );
	}

	public static function forShopper( Address $address, ?Point $shared ): Answer {
		global $wpdb;
		$pin = $address->country === 'IN' ? ( new Pincodes( $wpdb ) )->find( $address->postcode ) : null;
		return self::answer( $address, PointResolver::choose( $shared, $pin['point'] ?? null ) );
	}
}
