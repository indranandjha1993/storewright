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

	private const ABROAD = [ 'dhl', 'fedex', 'ups' ];

	public static function register(): void {
		add_filter( 'woocommerce_fulfillment_shipping_providers', [ self::class, 'providers' ], 15 );
		add_filter( 'storewright_console_carriers', [ self::class, 'forConsole' ] );
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

	public static function forConsole(): array {
		$known = FulfillmentUtils::get_shipping_providers();
		$list  = [
			[
				'key'  => self::OWN,
				'name' => self::ownName(),
			],
		];
		foreach ( [ ...array_keys( self::INDIA ), ...self::ABROAD ] as $key ) {
			if ( isset( $known[ $key ] ) ) {
				$list[] = [
					'key'  => $key,
					'name' => $known[ $key ]->get_name(),
				];
			}
		}
		$list[] = [
			'key'  => self::OTHER,
			'name' => __( 'Another carrier', 'storewright' ),
		];
		return $list;
	}

	public static function name( Fulfillment $fulfillment ): string {
		$key = (string) $fulfillment->get_shipment_provider();
		if ( $key === '' || $key === self::OTHER ) {
			return (string) $fulfillment->get_meta( '_provider_name', true );
		}
		$known = FulfillmentUtils::get_shipping_providers();
		return isset( $known[ $key ] ) ? $known[ $key ]->get_name() : $key;
	}

	private static function ownName(): string {
		return __( 'Our own delivery', 'storewright' );
	}
}
