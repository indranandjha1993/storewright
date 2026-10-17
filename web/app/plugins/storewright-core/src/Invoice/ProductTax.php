<?php

declare(strict_types=1);

namespace Storewright\Core\Invoice;

use WC_Product;

final class ProductTax {

	public const HSN  = '_storewright_hsn';
	public const RATE = '_storewright_gst_rate';

	public static function register(): void {
		add_action( 'woocommerce_product_options_tax', [ self::class, 'fields' ] );
		add_action( 'woocommerce_product_options_general_product_data', [ self::class, 'fields' ] );
		add_action( 'woocommerce_admin_process_product_object', [ self::class, 'save' ] );
	}

	public static function fields(): void {
		static $shown = false;
		if ( $shown ) {
			return;
		}
		$shown = true;
		echo '<div class="options_group">';
		woocommerce_wp_text_input(
			[
				'id'          => self::HSN,
				'label'       => __( 'HSN code', 'storewright' ),
				'description' => __( 'Printed on GST invoices.', 'storewright' ),
				'desc_tip'    => true,
			]
		);
		woocommerce_wp_select(
			[
				'id'      => self::RATE,
				'label'   => __( 'GST rate', 'storewright' ),
				'options' => [ '' => __( 'Store default', 'storewright' ) ] + array_combine( array_map( 'strval', Gst::RATES ), array_map( static fn( int $rate ): string => "{$rate}%", Gst::RATES ) ),
			]
		);
		echo '</div>';
	}

	public static function save( WC_Product $product ): void {
		if ( ! isset( $_POST[ self::HSN ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		$hsn  = preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST[ self::HSN ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$rate = sanitize_text_field( wp_unslash( $_POST[ self::RATE ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$product->update_meta_data( self::HSN, substr( (string) $hsn, 0, 8 ) );
		$product->update_meta_data( self::RATE, in_array( (int) $rate, Gst::RATES, true ) && $rate !== '' ? $rate : '' );
	}
}
