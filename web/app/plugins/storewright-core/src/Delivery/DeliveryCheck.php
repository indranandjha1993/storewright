<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

final class DeliveryCheck {

	public static function answer( Address $address, ?Point $point = null ): Answer {
		return ( new Serviceability( WooZones::load() ) )->check( $address, $point );
	}
}
