<?php

declare(strict_types=1);

namespace Storewright\Core\Fulfilment;

use Storewright\Core\Invoice\ProductTax;
use WC_Order;
use WC_Order_Item_Product;

final class Consignment {

	/** @param list<array{item_id: int, name: string, sku: string, qty: int, price: float, hsn: string}> $items */
	public function __construct(
		public readonly string $reference,
		public readonly string $orderedAt,
		public readonly string $pickup,
		public readonly array $customer,
		public readonly array $items,
		public readonly bool $cod,
		public readonly float $subtotal,
		public readonly float $charges,
		public readonly float $weight,
		public readonly float $length,
		public readonly float $breadth,
		public readonly float $height,
		public readonly bool $reverse = false
	) {}

	/** @param list<array{item_id: int, qty: int}> $items */
	public static function fromOrder( WC_Order $order, array $items, string $pickup, array $parcel, string $reference, float $charges = 0, bool $reverse = false ): self {
		$lines    = [];
		$weight   = 0.0;
		$size     = [ 0.0, 0.0, 0.0 ];
		$subtotal = 0.0;
		foreach ( $items as $wanted ) {
			$item = $order->get_item( $wanted['item_id'] );
			if ( ! $item instanceof WC_Order_Item_Product || $wanted['qty'] < 1 ) {
				continue;
			}
			$qty       = (int) $wanted['qty'];
			$product   = $item->get_product();
			$each      = $item->get_quantity() > 0 ? ( (float) $item->get_total() + (float) $item->get_total_tax() ) / $item->get_quantity() : 0.0;
			$lines[]   = [
				'item_id' => (int) $wanted['item_id'],
				'name'    => $item->get_name(),
				'sku'     => $product ? (string) $product->get_sku() : '',
				'qty'     => $qty,
				'price'   => round( $each, 2 ),
				'hsn'     => $product ? (string) $product->get_meta( ProductTax::HSN, true ) : '',
			];
			$subtotal += $each * $qty;
			$weight   += $qty * ( $product && (float) $product->get_weight() > 0 ? (float) wc_get_weight( (float) $product->get_weight(), 'kg' ) : $parcel['weight'] );
			if ( $product ) {
				foreach ( [ $product->get_length(), $product->get_width(), $product->get_height() ] as $axis => $value ) {
					$size[ $axis ] = max( $size[ $axis ], (float) $value > 0 ? (float) wc_get_dimension( (float) $value, 'cm' ) : 0.0 );
				}
			}
		}
		$address = $order->has_shipping_address() ? 'shipping' : 'billing';
		return new self(
			$reference,
			$order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i' ) : current_time( 'Y-m-d H:i' ),
			$pickup,
			[
				'first'    => $order->{"get_{$address}_first_name"}(),
				'last'     => $order->{"get_{$address}_last_name"}(),
				'address'  => $order->{"get_{$address}_address_1"}(),
				'address2' => $order->{"get_{$address}_address_2"}(),
				'city'     => $order->{"get_{$address}_city"}(),
				'state'    => self::stateName( $order->{"get_{$address}_country"}(), $order->{"get_{$address}_state"}() ),
				'pin'      => $order->{"get_{$address}_postcode"}(),
				'country'  => self::countryName( $order->{"get_{$address}_country"}() ),
				'email'    => $order->get_billing_email(),
				'phone'    => self::phone( $order->get_shipping_phone() !== '' ? $order->get_shipping_phone() : $order->get_billing_phone() ),
			],
			$lines,
			! $reverse && $order->get_payment_method() === 'cod',
			round( $subtotal, 2 ),
			$reverse ? 0.0 : round( $charges, 2 ),
			round( max( $weight, 0.1 ), 3 ),
			$size[0] > 0 ? $size[0] : $parcel['length'],
			$size[1] > 0 ? $size[1] : $parcel['breadth'],
			$size[2] > 0 ? $size[2] : $parcel['height'],
			$reverse
		);
	}

	public function total(): float {
		return round( $this->subtotal + $this->charges, 2 );
	}

	public static function phone( string $raw ): string {
		$digits = preg_replace( '/\D/', '', $raw ) ?? '';
		return strlen( $digits ) > 10 ? substr( $digits, -10 ) : $digits;
	}

	public static function stateName( string $country, string $code ): string {
		$states = WC()->countries->get_states( $country );
		return is_array( $states ) && isset( $states[ $code ] ) ? html_entity_decode( (string) $states[ $code ] ) : $code;
	}

	public static function countryName( string $code ): string {
		$countries = WC()->countries->get_countries();
		return isset( $countries[ $code ] ) ? html_entity_decode( (string) $countries[ $code ] ) : $code;
	}
}
