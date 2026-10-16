<?php

declare(strict_types=1);

namespace Storewright\Core\Setup;

use WC_Shipping_Zone;
use WC_Shipping_Zones;

final class StoreDefaults {

	public const ZONE           = 'India';
	public const FLAT_RATE      = '79';
	public const FREE_FROM      = '999';
	private const STORE_OPTIONS = [
		'woocommerce_default_country'           => 'IN:KA',
		'woocommerce_currency'                  => 'INR',
		'woocommerce_ship_to_countries'         => '',
		'woocommerce_ship_to_destination'       => 'shipping',
		'woocommerce_checkout_phone_field'      => 'required',
		'woocommerce_calc_taxes'                => 'no',
		'woocommerce_coming_soon'               => 'no',
		'woocommerce_task_list_hidden'          => 'yes',
		'woocommerce_extended_task_list_hidden' => 'yes',
		'woocommerce_custom_orders_table_data_sync_enabled' => 'no',
		'timezone_string'                       => 'Asia/Kolkata',
		'blogdescription'                       => '',
	];

	public function apply(): array {
		foreach ( self::STORE_OPTIONS as $option => $value ) {
			update_option( $option, $value );
		}
		update_option( 'woocommerce_onboarding_profile', [ 'skipped' => true ] );
		add_option(
			'woocommerce_cod_settings',
			[
				'enabled'            => 'yes',
				'title'              => __( 'Cash on delivery', 'storewright' ),
				'description'        => __( 'Pay in cash or by UPI when the parcel arrives.', 'storewright' ),
				'instructions'       => __( 'Keep the amount ready when the parcel arrives.', 'storewright' ),
				'enable_for_methods' => [],
				'enable_for_virtual' => 'no',
			]
		);
		add_option(
			'woocommerce_razorpay_settings',
			[
				'title'       => 'Razorpay',
				'description' => __( 'UPI, cards, netbanking, wallets and EMI on Razorpay\'s secure page.', 'storewright' ),
			]
		);

		return [
			'options' => count( self::STORE_OPTIONS ) + 1,
			'zone'    => $this->domesticZone(),
		];
	}

	private function domesticZone(): int {
		foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
			if ( $zone['zone_name'] === self::ZONE ) {
				return (int) $zone['id'];
			}
		}

		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( self::ZONE );
		$zone->add_location( 'IN', 'country' );
		$zone->save();

		$flat = \WC_Shipping_Zones::get_shipping_method( $zone->add_shipping_method( 'flat_rate' ) );
		update_option(
			$flat->get_instance_option_key(),
			[
				'title'      => __( 'Standard delivery', 'storewright' ),
				'tax_status' => 'none',
				'cost'       => self::FLAT_RATE,
			]
		);

		$free = \WC_Shipping_Zones::get_shipping_method( $zone->add_shipping_method( 'free_shipping' ) );
		update_option(
			$free->get_instance_option_key(),
			[
				'title'      => __( 'Free delivery', 'storewright' ),
				'requires'   => 'min_amount',
				'min_amount' => self::FREE_FROM,
			]
		);

		return $zone->get_id();
	}
}
