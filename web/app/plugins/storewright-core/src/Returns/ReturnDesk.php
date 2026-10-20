<?php

declare(strict_types=1);

namespace Storewright\Core\Returns;

use InvalidArgumentException;
use Storewright\Core\Fulfilment\CarrierException;
use Storewright\Core\Fulfilment\Shipping;
use WC_Order;

final class ReturnDesk {

	public static function rows( bool $all ): array {
		$orders = wc_get_orders(
			[
				'limit'      => 50,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'meta_key'   => $all ? ReturnRequests::META : ReturnRequests::OPEN, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
				'meta_value' => $all ? null : 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
			]
		);
		$rows   = [];
		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			foreach ( ReturnRequests::all( $order ) as $item ) {
				$rows[] = self::describe( $order, $item );
			}
		}
		usort( $rows, static fn( array $a, array $b ): int => strcmp( $b['created'], $a['created'] ) );
		return $rows;
	}

	public static function move( int $orderId, string $id, string $status, string $note ): array {
		$order = wc_get_order( $orderId );
		if ( ! $order instanceof WC_Order ) {
			throw new InvalidArgumentException( esc_html__( 'That order does not exist.', 'storewright' ) );
		}
		$current = array_values( array_filter( ReturnRequests::all( $order ), static fn( array $item ): bool => $item['id'] === $id ) )[0] ?? null;
		if ( $status === 'refunded' && $current !== null ) {
			self::refund( $order, $current );
		}
		$moved = ReturnRequests::move( $order, $id, $status, $note );
		ReturnMail::customer( $order, $moved );
		if ( $status === 'approved' && Shipping::carrier() !== null ) {
			self::bookPickup( $order, $moved );
		}
		return self::describe( wc_get_order( $order->get_id() ) ?: $order, $moved );
	}

	public static function pickup( int $orderId, string $id ): void {
		$order   = wc_get_order( $orderId );
		$request = $order instanceof WC_Order ? ReturnRequests::find( $order, $id ) : null;
		if ( $request === null || $request['status'] !== 'approved' ) {
			throw new InvalidArgumentException( esc_html__( 'Only an approved return can have a pickup booked.', 'storewright' ) );
		}
		Shipping::collect( $order, $id, $request['items'] );
	}

	private static function bookPickup( WC_Order $order, array $request ): void {
		try {
			Shipping::collect( $order, $request['id'], $request['items'] );
		} catch ( CarrierException $e ) {
			/* translators: %s: the carrier's message */
			$order->add_order_note( sprintf( __( 'The return pickup could not be booked with Shiprocket: %s. Arrange it by hand or try again from Returns.', 'storewright' ), $e->getMessage() ) );
		}
	}

	private static function refund( WC_Order $order, array $request ): void {
		$lines = [];
		foreach ( $request['items'] as $id => $qty ) {
			$item = $order->get_item( (int) $id );
			if ( $item instanceof \WC_Order_Item_Product && $item->get_quantity() > 0 ) {
				$lines[ (int) $id ] = [
					'qty'          => (int) $qty,
					'refund_total' => round( (float) $item->get_total() / $item->get_quantity() * (int) $qty, 2 ),
				];
			}
		}
		$gateway = WC()->payment_gateways()->payment_gateways()[ $order->get_payment_method() ] ?? null;
		$online  = $order->get_payment_method() !== 'cod' && $gateway && $gateway->supports( 'refunds' );
		$refund  = wc_create_refund(
			[
				'order_id'       => $order->get_id(),
				'amount'         => array_sum( array_column( $lines, 'refund_total' ) ),
				'reason'         => __( 'Returned items', 'storewright' ),
				'line_items'     => $lines,
				'refund_payment' => $online,
				'restock_items'  => true,
			]
		);
		if ( is_wp_error( $refund ) ) {
			throw new InvalidArgumentException( esc_html( $refund->get_error_message() ) );
		}
		if ( ! $online ) {
			$order->add_order_note( __( 'Return refunded in the store records. Send the money to the customer by UPI or bank transfer.', 'storewright' ) );
		}
	}

	private static function describe( WC_Order $order, array $item ): array {
		return [
			...$item,
			'order'      => $order->get_id(),
			'number'     => $order->get_order_number(),
			'customer'   => trim( $order->get_formatted_billing_full_name() ) ?: $order->get_billing_email(),
			'summary'    => ReturnRequests::summary( $order, $item['items'] ),
			'reasonText' => ReturnRequests::reason( $item['reason'] ),
			'payment'    => $order->get_payment_method_title(),
			'next'       => ReturnRequests::next( $item['status'] ),
			'pickup'     => Shipping::store()->forReturn( $order->get_id(), $item['id'] ),
		];
	}
}
