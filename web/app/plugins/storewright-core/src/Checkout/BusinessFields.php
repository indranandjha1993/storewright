<?php

declare(strict_types=1);

namespace Storewright\Core\Checkout;

use WP_Error;

final class BusinessFields {

	public const BUSINESS = 'storewright/business';
	public const GSTIN    = 'storewright/gstin';

	public static function register(): void {
		add_action( 'woocommerce_init', [ self::class, 'fields' ] );
	}

	public static function fields(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}
		woocommerce_register_additional_checkout_field(
			[
				'id'                => self::BUSINESS,
				'label'             => __( 'Business name', 'storewright' ),
				'optionalLabel'     => __( 'Business name, for a tax invoice (optional)', 'storewright' ),
				'location'          => 'contact',
				'type'              => 'text',
				'attributes'        => [
					'autocomplete' => 'organization',
					'maxLength'    => 120,
				],
				'sanitize_callback' => static fn( mixed $value ): string => trim( sanitize_text_field( (string) $value ) ),
			]
		);
		woocommerce_register_additional_checkout_field(
			[
				'id'                => self::GSTIN,
				'label'             => __( 'GSTIN', 'storewright' ),
				'optionalLabel'     => __( 'GSTIN (optional)', 'storewright' ),
				'location'          => 'contact',
				'type'              => 'text',
				'attributes'        => [
					'autocomplete'   => 'off',
					'autocapitalize' => 'characters',
					'maxLength'      => 15,
				],
				'sanitize_callback' => static fn( mixed $value ): string => Gstin::normalise( (string) $value ),
				'validate_callback' => [ self::class, 'validateGstin' ],
			]
		);
	}

	public static function validateGstin( mixed $value ): WP_Error|true {
		$gstin = Gstin::normalise( (string) $value );
		if ( $gstin === '' || Gstin::isValid( $gstin ) ) {
			return true;
		}
		return new WP_Error( 'storewright_gstin', __( 'That GSTIN does not look right. It has 15 characters, such as 29ABCDE1234F1Z5.', 'storewright' ) );
	}
}
