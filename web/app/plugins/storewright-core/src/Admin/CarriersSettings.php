<?php

declare(strict_types=1);

namespace Storewright\Core\Admin;

use Storewright\Platform\CarrierCredentials;
use WC_Admin_Settings;

final class CarriersSettings {

	public const TAB = 'carriers';

	private const OPTION = 'storewright_settings';

	public static function register(): void {
		add_filter( 'woocommerce_settings_tabs_array', [ self::class, 'tab' ], 26 );
		add_action( 'woocommerce_settings_tabs_' . self::TAB, [ self::class, 'output' ] );
		add_action( 'woocommerce_update_options_' . self::TAB, [ self::class, 'save' ] );
	}

	public static function tab( array $tabs ): array {
		$tabs[ self::TAB ] = __( 'Carriers', 'storewright' );
		return $tabs;
	}

	public static function output(): void {
		WC_Admin_Settings::output_fields( self::fields() );
	}

	public static function save(): void {
		WC_Admin_Settings::save_fields( self::fields() );
	}

	public static function fields(): array {
		$mode  = CarrierCredentials::modes( wp_get_environment_type() )['shiprocket'];
		$state = match ( $mode ) {
			CarrierCredentials::OFF     => __( 'Shiprocket is off: add SHIPROCKET_EMAIL and SHIPROCKET_PASSWORD of an API user to the server settings, and SHIPROCKET_API_URL to use its sandbox.', 'storewright' ),
			CarrierCredentials::REFUSED => __( 'Live Shiprocket credentials are refused outside production, so booking is off here.', 'storewright' ),
			/* translators: %s: sandbox or live */
			default                     => sprintf( __( 'Shiprocket is on in %s mode. Shipments are booked from the order screen.', 'storewright' ), $mode ),
		};
		$webhook = match ( $mode ) {
			CarrierCredentials::SANDBOX => __( 'The sandbox sends no tracking updates, so no webhook is needed there; the store still asks it where each parcel is every half hour.', 'storewright' ),
			CarrierCredentials::LIVE    => sprintf(
				/* translators: %s: webhook address */
				__( 'The store asks Shiprocket where each parcel is every half hour. For updates within the minute, add a webhook in Shiprocket under Settings, API, Webhooks pointing at %s with the token from SHIPROCKET_WEBHOOK_TOKEN.', 'storewright' ),
				home_url( '/wp-json/storewright/v1/webhooks/shiprocket' )
			),
			default                     => '',
		};
		return [
			[
				'title' => __( 'Shiprocket', 'storewright' ),
				'type'  => 'title',
				'desc'  => trim( $state . ' ' . $webhook ),
				'id'    => 'storewright_shiprocket',
			],
			[
				'title'    => __( 'Pickup address', 'storewright' ),
				'desc'     => __( 'The nickname of the pickup address in Shiprocket. Leave empty to use the first one there, or to have one named Store made from the store address when there is none.', 'storewright' ),
				'desc_tip' => true,
				'id'       => self::OPTION . '[pickup_location]',
				'type'     => 'text',
				'default'  => '',
			],
			[
				'title'             => __( 'Parcel weight when a product has none', 'storewright' ),
				'desc'              => __( 'In kilograms, per item. Products with a weight use their own.', 'storewright' ),
				'desc_tip'          => true,
				'id'                => self::OPTION . '[parcel_weight]',
				'type'              => 'number',
				'default'           => '0.5',
				'css'               => 'width: 8em',
				'custom_attributes' => [
					'min'  => 0.01,
					'step' => 0.01,
				],
			],
			[
				'title'             => __( 'Parcel size when products have none', 'storewright' ),
				'desc'              => __( 'Length in centimetres. Products with dimensions set the parcel size instead.', 'storewright' ),
				'desc_tip'          => true,
				'id'                => self::OPTION . '[parcel_length]',
				'type'              => 'number',
				'default'           => '10',
				'css'               => 'width: 8em',
				'custom_attributes' => [
					'min'  => 1,
					'step' => 1,
				],
			],
			[
				'title'             => __( 'Breadth', 'storewright' ),
				'id'                => self::OPTION . '[parcel_breadth]',
				'type'              => 'number',
				'default'           => '10',
				'css'               => 'width: 8em',
				'custom_attributes' => [
					'min'  => 1,
					'step' => 1,
				],
			],
			[
				'title'             => __( 'Height', 'storewright' ),
				'id'                => self::OPTION . '[parcel_height]',
				'type'              => 'number',
				'default'           => '10',
				'css'               => 'width: 8em',
				'custom_attributes' => [
					'min'  => 1,
					'step' => 1,
				],
			],
			[
				'type' => 'sectionend',
				'id'   => 'storewright_shiprocket',
			],
		];
	}
}
