<?php

declare(strict_types=1);

namespace Storewright\Core\Admin;

use Storewright\Core\Checkout\Gstin;
use Storewright\Core\Invoice\Gst;
use WC_Admin_Settings;

final class StoreSettings {

	private const OPTION = 'storewright_settings';

	public static function register(): void {
		add_filter( 'woocommerce_general_settings', [ self::class, 'fields' ] );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPTION . '[store_gstin]', [ self::class, 'gstin' ] );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPTION . '[invoice_prefix]', [ self::class, 'prefix' ] );
	}

	public static function fields( array $settings ): array {
		$ours = [
			[
				'title' => __( 'Store details', 'storewright' ),
				'type'  => 'title',
				'desc'  => __( 'Printed on emails and invoices.', 'storewright' ),
				'id'    => 'storewright_store_details',
			],
			[
				'title'   => __( 'Phone', 'storewright' ),
				'id'      => self::OPTION . '[store_phone]',
				'type'    => 'text',
				'default' => '',
			],
			[
				'title'             => __( 'GSTIN', 'storewright' ),
				'desc'              => __( 'Leave empty if the business is not registered for GST.', 'storewright' ),
				'desc_tip'          => true,
				'id'                => self::OPTION . '[store_gstin]',
				'type'              => 'text',
				'default'           => '',
				'custom_attributes' => [ 'maxlength' => 15 ],
			],
			[
				'title'    => __( 'Usual GST rate', 'storewright' ),
				'desc'     => __( 'Used for products without their own rate. Prices already include it.', 'storewright' ),
				'desc_tip' => true,
				'id'       => self::OPTION . '[gst_rate]',
				'type'     => 'select',
				'default'  => '18',
				'options'  => array_combine( array_map( 'strval', Gst::RATES ), array_map( static fn( int $rate ): string => $rate . '%', Gst::RATES ) ),
			],
			[
				'title'             => __( 'Invoice prefix', 'storewright' ),
				'desc'              => __( 'Invoices are numbered like INV/26-27/0001, starting again each April.', 'storewright' ),
				'desc_tip'          => true,
				'id'                => self::OPTION . '[invoice_prefix]',
				'type'              => 'text',
				'default'           => 'INV',
				'custom_attributes' => [ 'maxlength' => 4 ],
			],
			[
				'title'             => __( 'Returns accepted for', 'storewright' ),
				'desc'              => __( 'days after an order is completed. 0 turns returns off.', 'storewright' ),
				'id'                => self::OPTION . '[return_days]',
				'type'              => 'number',
				'default'           => '7',
				'css'               => 'width: 5em',
				'custom_attributes' => [
					'min' => 0,
					'max' => 90,
				],
			],
			[
				'title'             => __( 'Cash on delivery up to', 'storewright' ),
				'desc'              => __( 'Orders above this total have to be paid online.', 'storewright' ),
				'desc_tip'          => true,
				'id'                => self::OPTION . '[cod_max_total]',
				'type'              => 'number',
				'default'           => '10000',
				'css'               => 'width: 8em',
				'custom_attributes' => [
					'min'  => 0,
					'step' => 100,
				],
			],
			[
				'type' => 'sectionend',
				'id'   => 'storewright_store_details',
			],
		];
		foreach ( $settings as $index => $field ) {
			if ( ( $field['type'] ?? '' ) === 'sectionend' ) {
				array_splice( $settings, $index + 1, 0, $ours );
				return $settings;
			}
		}
		return [ ...$settings, ...$ours ];
	}

	public static function gstin( mixed $value ): string {
		$gstin = Gstin::normalise( (string) $value );
		if ( $gstin !== '' && ! Gstin::isValid( $gstin ) ) {
			WC_Admin_Settings::add_error( __( 'That GSTIN does not look right. It has 15 characters, such as 29ABCDE1234F1Z5.', 'storewright' ) );
			return '';
		}
		return $gstin;
	}

	public static function prefix( mixed $value ): string {
		$prefix = strtoupper( trim( (string) $value ) );
		if ( ! preg_match( '/^[A-Z0-9]{1,4}$/', $prefix ) ) {
			WC_Admin_Settings::add_error( __( 'The invoice prefix is one to four letters or digits, such as INV.', 'storewright' ) );
			return 'INV';
		}
		return $prefix;
	}
}
