<?php

declare(strict_types=1);

namespace Storewright\Core\Cli;

use Storewright\Core\Delivery\Address;
use Storewright\Core\Delivery\DeliveryCheck;
use Storewright\Core\Delivery\Point;
use WP_CLI;

final class DeliverCommand {

	public function __invoke( array $args, array $assoc ): void {
		[ $country, $state, $postcode ] = array_pad( $args, 3, '' );
		$point                          = isset( $assoc['lat'], $assoc['lng'] ) ? new Point( (float) $assoc['lat'], (float) $assoc['lng'] ) : null;
		WP_CLI::line( (string) wp_json_encode( DeliveryCheck::answer( new Address( $country, $state, $postcode ), $point )->toArray() ) );
	}
}
