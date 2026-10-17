<?php

declare(strict_types=1);

namespace Storewright\Core\Invoice;

use Storewright\Core\Settings;
use WC_Order;
use WC_Order_Item;
use WC_Order_Item_Product;

final class Invoice {

	public const NUMBER = '_storewright_invoice_number';
	public const DATE   = '_storewright_invoice_date';

	private const COURIER_SAC = '996812';

	public static function assign( WC_Order $order ): string {
		$existing = (string) $order->get_meta( self::NUMBER );
		if ( $existing !== '' ) {
			return $existing;
		}
		$date   = new \DateTimeImmutable( 'now', wp_timezone() );
		$year   = InvoiceNumber::financialYear( $date );
		$number = InvoiceNumber::format( (string) Settings::get( 'invoice_prefix' ), $year, InvoiceNumber::next( $year ) );
		$order->update_meta_data( self::NUMBER, $number );
		$order->update_meta_data( self::DATE, $date->format( 'c' ) );
		$order->save();
		return $number;
	}

	public static function build( WC_Order $order ): array {
		$sellerState = self::storeState();
		$buyerState  = $order->get_shipping_state() ?: $order->get_billing_state();
		$registered  = (string) Settings::get( 'store_gstin' ) !== '';
		$sameState   = $buyerState === '' || strtoupper( $buyerState ) === $sellerState;
		$lines       = [];
		$highest     = 0.0;
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$rate    = self::rate( $item );
			$highest = max( $highest, $rate );
			$lines[] = self::line( $item->get_name(), self::hsn( $item ), $item->get_quantity(), (float) $item->get_total(), $rate, $sameState );
		}
		foreach ( $order->get_items( [ 'shipping', 'fee' ] ) as $item ) {
			$amount = $item instanceof \WC_Order_Item_Shipping || $item instanceof \WC_Order_Item_Fee ? (float) $item->get_total() : 0.0;
			if ( $amount > 0 ) {
				$lines[] = self::line( $item->get_name(), $item->get_type() === 'shipping' ? self::COURIER_SAC : '', 1, $amount, $highest, $sameState );
			}
		}
		return [
			'number'     => (string) $order->get_meta( self::NUMBER ),
			'date'       => (string) $order->get_meta( self::DATE ),
			'order'      => $order->get_order_number(),
			'registered' => $registered,
			'sameState'  => $sameState,
			'seller'     => self::seller( $sellerState ),
			'buyer'      => self::buyer( $order ),
			'supply'     => self::stateLabel( $buyerState ?: $sellerState ),
			'lines'      => $lines,
			'totals'     => self::totals( $lines, (float) $order->get_total() ),
			'payment'    => $order->get_payment_method_title(),
			'paid'       => $order->is_paid(),
		];
	}

	public static function stateLabel( string $state ): string {
		$states = WC()->countries->get_states( 'IN' ) ?: [];
		$name   = $states[ strtoupper( $state ) ] ?? $state;
		$code   = StateCodes::gst( $state );
		return $code !== '' ? "{$name} ({$code})" : $name;
	}

	private static function line( string $name, string $hsn, int $quantity, float $gross, float $rate, bool $sameState ): array {
		return [
			'name'     => $name,
			'hsn'      => $hsn,
			'quantity' => $quantity,
			'gross'    => round( $gross, 2 ),
			'rate'     => $rate,
			...Gst::split( $gross, $rate, $sameState ),
		];
	}

	private static function totals( array $lines, float $orderTotal ): array {
		$sum = static fn( string $key ): float => round( array_sum( array_column( $lines, $key ) ), 2 );
		return [
			'taxable' => $sum( 'taxable' ),
			'cgst'    => $sum( 'cgst' ),
			'sgst'    => $sum( 'sgst' ),
			'igst'    => $sum( 'igst' ),
			'total'   => round( $orderTotal, 2 ),
			'words'   => Words::rupees( $orderTotal ),
		];
	}

	private static function rate( WC_Order_Item_Product $item ): float {
		$product = $item->get_product();
		$parent  = $product && $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
		$saved   = $parent ? (string) $parent->get_meta( ProductTax::RATE ) : '';
		return $saved !== '' ? (float) $saved : (float) Settings::get( 'gst_rate' );
	}

	private static function hsn( WC_Order_Item $item ): string {
		$product = $item instanceof WC_Order_Item_Product ? $item->get_product() : null;
		$parent  = $product && $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
		return $parent ? (string) $parent->get_meta( ProductTax::HSN ) : '';
	}

	private static function storeState(): string {
		[ , $state ] = array_pad( explode( ':', (string) get_option( 'woocommerce_default_country', 'IN:KA' ) ), 2, '' );
		return strtoupper( $state );
	}

	private static function seller( string $state ): array {
		return [
			'name'    => get_bloginfo( 'name' ),
			'address' => array_values(
				array_filter(
					[
						(string) get_option( 'woocommerce_store_address' ),
						(string) get_option( 'woocommerce_store_address_2' ),
						trim( get_option( 'woocommerce_store_city' ) . ' ' . get_option( 'woocommerce_store_postcode' ) ),
						self::stateLabel( $state ),
					]
				)
			),
			'gstin'   => (string) Settings::get( 'store_gstin' ),
			'phone'   => (string) Settings::get( 'store_phone' ),
			'email'   => (string) get_option( 'woocommerce_email_from_address' ),
		];
	}

	private static function buyer( WC_Order $order ): array {
		$meta = static function ( string $key ) use ( $order ): string {
			foreach ( $order->get_meta_data() as $item ) {
				if ( str_ends_with( (string) $item->key, $key ) ) {
					return (string) $item->value;
				}
			}
			return '';
		};
		return [
			'name'     => trim( $order->get_formatted_billing_full_name() ),
			'business' => $meta( 'storewright/business' ),
			'gstin'    => $meta( 'storewright/gstin' ),
			'billing'  => (string) $order->get_formatted_billing_address(),
			'shipping' => $order->has_shipping_address() ? (string) $order->get_formatted_shipping_address() : '',
			'email'    => $order->get_billing_email(),
			'phone'    => $order->get_billing_phone(),
		];
	}
}
