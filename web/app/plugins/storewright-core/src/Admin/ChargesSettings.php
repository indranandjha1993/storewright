<?php

declare(strict_types=1);

namespace Storewright\Core\Admin;

use Storewright\Core\Invoice\Gst;
use WC_Admin_Settings;

final class ChargesSettings {

	public const TAB = 'charges';

	private const OPTION = 'storewright_settings';

	public static function register(): void {
		add_filter( 'woocommerce_settings_tabs_array', [ self::class, 'tab' ], 25 );
		add_action( 'woocommerce_settings_tabs_' . self::TAB, [ self::class, 'output' ] );
		add_action( 'woocommerce_update_options_' . self::TAB, [ self::class, 'save' ] );
		add_action( 'woocommerce_admin_field_storewright_lines', [ self::class, 'lines' ] );
	}

	public static function lines( array $field ): void {
		$value = WC_Admin_Settings::get_option( $field['id'], $field['default'] );
		$text  = is_array( $value ) ? implode( "\n", $value ) : (string) $value;
		printf(
			'<tr><th scope="row" class="titledesc"><label for="%1$s">%2$s %3$s</label></th><td class="forminp"><textarea name="%1$s" id="%1$s" style="%4$s">%5$s</textarea></td></tr>',
			esc_attr( $field['id'] ),
			esc_html( $field['title'] ),
			wc_help_tip( $field['desc'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_attr( $field['css'] ),
			esc_textarea( $text )
		);
	}

	public static function url(): string {
		return admin_url( 'admin.php?page=wc-settings&tab=' . self::TAB );
	}

	public static function tab( array $tabs ): array {
		$tabs[ self::TAB ] = __( 'Taxes and charges', 'storewright' );
		return $tabs;
	}

	public static function output(): void {
		WC_Admin_Settings::output_fields( self::fields() );
	}

	public static function save(): void {
		WC_Admin_Settings::save_fields( self::fields() );
	}

	public static function fields(): array {
		return [
			[
				'title' => __( 'GST', 'storewright' ),
				'type'  => 'title',
				'desc'  => __( 'Prices on the store include GST. Each invoice splits the tax into CGST and SGST, or IGST for another state, from the rate on the product or the usual rate below. Set an HSN code and a rate on products whose rate differs.', 'storewright' ),
				'id'    => 'storewright_gst',
			],
			[
				'title'             => __( 'GSTIN', 'storewright' ),
				'desc'              => __( 'Leave empty if the business is not registered for GST; invoices then print without a tax split.', 'storewright' ),
				'desc_tip'          => true,
				'id'                => self::OPTION . '[store_gstin]',
				'type'              => 'text',
				'default'           => '',
				'custom_attributes' => [ 'maxlength' => 15 ],
			],
			[
				'title'    => __( 'Usual GST rate', 'storewright' ),
				'desc'     => __( 'Used for products without their own rate, and for delivery and charges.', 'storewright' ),
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
				'type' => 'sectionend',
				'id'   => 'storewright_gst',
			],
			[
				'title' => __( 'Charges', 'storewright' ),
				'type'  => 'title',
				'desc'  => __( 'Amounts added to an order at checkout, shown as their own line in the bag, the checkout, emails and the invoice. They include GST at the usual rate. Leave an amount at 0 to charge nothing.', 'storewright' ),
				'id'    => 'storewright_charges',
			],
			[
				'title'             => __( 'Packing and handling', 'storewright' ),
				'desc'              => __( 'Added to every order that ships.', 'storewright' ),
				'desc_tip'          => true,
				'id'                => self::OPTION . '[handling_fee]',
				'type'              => 'number',
				'default'           => '0',
				'css'               => 'width: 8em',
				'custom_attributes' => [
					'min'  => 0,
					'step' => 1,
				],
			],
			[
				'title'             => __( 'Free packing from', 'storewright' ),
				'desc'              => __( 'Orders of at least this amount pay no packing and handling. 0 means it is always charged.', 'storewright' ),
				'desc_tip'          => true,
				'id'                => self::OPTION . '[handling_fee_free_from]',
				'type'              => 'number',
				'default'           => '0',
				'css'               => 'width: 8em',
				'custom_attributes' => [
					'min'  => 0,
					'step' => 1,
				],
			],
			[
				'title'             => __( 'Cash on delivery handling', 'storewright' ),
				'desc'              => __( 'Added when the shopper chooses to pay on delivery.', 'storewright' ),
				'desc_tip'          => true,
				'id'                => self::OPTION . '[cod_fee]',
				'type'              => 'number',
				'default'           => '0',
				'css'               => 'width: 8em',
				'custom_attributes' => [
					'min'  => 0,
					'step' => 1,
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
				'title'             => __( 'Cash on delivery PIN codes', 'storewright' ),
				'desc'              => __( 'One rule per line: a PIN code, a prefix such as 560* or a range such as 560001-560099. Leave empty to offer cash on delivery everywhere you deliver.', 'storewright' ),
				'desc_tip'          => true,
				'id'                => self::OPTION . '[cod_postcodes]',
				'type'              => 'storewright_lines',
				'default'           => '',
				'css'               => 'width: 20em; height: 6em',
				'custom_attributes' => [],
			],
			[
				'type' => 'sectionend',
				'id'   => 'storewright_charges',
			],
		];
	}
}
