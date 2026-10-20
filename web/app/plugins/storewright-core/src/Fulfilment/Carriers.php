<?php

declare(strict_types=1);

namespace Storewright\Core\Fulfilment;

use Automattic\WooCommerce\Admin\Features\Fulfillments\Fulfillment;
use Automattic\WooCommerce\Admin\Features\Fulfillments\FulfillmentUtils;
use Automattic\WooCommerce\Admin\Features\Fulfillments\Providers\CustomShippingProvider;

final class Carriers {

	public const OWN   = 'own-delivery';
	public const OTHER = 'other';

	private const INDIA = [
		'delhivery'  => 'Delhivery',
		'bluedart'   => 'Blue Dart',
		'dtdc'       => 'DTDC',
		'india-post' => 'India Post',
		'ekart'      => 'Ekart',
		'xpressbees' => 'Xpressbees',
		'shadowfax'  => 'Shadowfax',
		'shiprocket' => 'Shiprocket',
	];

	public static function register(): void {
		add_filter( 'woocommerce_fulfillment_shipping_providers', [ self::class, 'providers' ], 15 );
	}

	public static function providers( mixed $providers ): array {
		$providers = is_array( $providers ) ? $providers : [];
		foreach ( [
			...self::INDIA,
			self::OWN => self::ownName(),
		] as $key => $name ) {
			$providers[ $key ] = new CustomShippingProvider( $key, $name, '', '' );
		}
		return $providers;
	}

	public static function name( Fulfillment $fulfillment ): string {
		$key = (string) $fulfillment->get_shipment_provider();
		if ( $key === '' || $key === self::OTHER ) {
			return (string) $fulfillment->get_meta( '_provider_name', true );
		}
		$known = FulfillmentUtils::get_shipping_providers();
		return isset( $known[ $key ] ) ? $known[ $key ]->get_name() : $key;
	}

	public static function keyFor( string $courier ): string {
		$name = strtolower( $courier );
		foreach ( [ 'delhivery', 'blue dart', 'bluedart', 'dtdc', 'india post', 'ekart', 'xpressbees', 'shadowfax' ] as $needle ) {
			if ( str_contains( $name, $needle ) ) {
				return str_replace( ' ', '', $needle === 'india post' ? 'india-post' : $needle );
			}
		}
		return 'shiprocket';
	}

	private static function ownName(): string {
		return __( 'Our own delivery', 'storewright' );
	}
}
