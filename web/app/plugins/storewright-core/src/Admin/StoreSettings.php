<?php

declare(strict_types=1);

namespace Storewright\Core\Admin;

use Storewright\Core\Checkout\Gstin;
use WC_Admin_Settings;

final class StoreSettings {

	private const OPTION = 'storewright_settings';

	public static function register(): void {
		add_filter( 'woocommerce_general_settings', [ self::class, 'fields' ] );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPTION, [ self::class, 'sanitize' ], 10, 3 );
	}

	public static function sanitize( mixed $value, array $option, mixed $raw ): mixed {
		return match ( $option['id'] ?? '' ) {
			self::OPTION . '[store_gstin]'    => self::gstin( $value ),
			self::OPTION . '[invoice_prefix]' => self::prefix( $value ),
			self::OPTION . '[cod_postcodes]'  => self::rules( $raw ),
			default                           => $value,
		};
	}

	public static function fields( array $settings ): array {
		$ours     = [
			[
				'title' => __( 'Store details', 'storewright' ),
				'type'  => 'title',
				'desc'  => __( 'The phone is printed on emails and invoices. GST and charges are under Taxes and charges.', 'storewright' ),
				'id'    => 'storewright_store_details',
			],
			[
				'title'   => __( 'Phone', 'storewright' ),
				'id'      => self::OPTION . '[store_phone]',
				'type'    => 'text',
				'default' => '',
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
				'type' => 'sectionend',
				'id'   => 'storewright_store_details',
			],
		];
		$settings = array_values( array_filter( $settings, static fn( array $field ): bool => ( $field['id'] ?? '' ) !== 'woocommerce_calc_taxes' ) );
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

	public static function rules( mixed $value ): array {
		$lines = preg_split( '/[\r\n,]+/', (string) $value ) ?: [];
		return array_values( array_filter( array_map( static fn( string $line ): string => strtoupper( trim( $line ) ), $lines ) ) );
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
