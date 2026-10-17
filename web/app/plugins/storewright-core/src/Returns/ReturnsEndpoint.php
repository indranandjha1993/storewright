<?php

declare(strict_types=1);

namespace Storewright\Core\Returns;

use InvalidArgumentException;
use WC_Order;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ReturnsEndpoint {

	public static function register(): void {
		$staff = static fn(): bool => current_user_can( 'manage_woocommerce' );
		register_rest_route(
			'storewright/v1',
			'/returns',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'list' ],
				'permission_callback' => $staff,
			]
		);
		register_rest_route(
			'storewright/v1',
			'/returns/(?P<order>\d+)/(?P<id>[a-f0-9-]{36})',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'move' ],
				'permission_callback' => $staff,
				'args'                => [
					'status' => [
						'type'     => 'string',
						'enum'     => [ 'approved', 'declined', 'received', 'refunded' ],
						'required' => true,
					],
					'note'   => [
						'type' => 'string',
					],
				],
			]
		);
	}

	public static function list( WP_REST_Request $request ): WP_REST_Response {
		$orders = wc_get_orders(
			[
				'limit'      => 50,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'meta_key'   => $request['all'] ? ReturnRequests::META : ReturnRequests::OPEN, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
				'meta_value' => $request['all'] ? null : 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
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
		return new WP_REST_Response( $rows );
	}

	public static function move( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$order = wc_get_order( (int) $request['order'] );
		if ( ! $order instanceof WC_Order ) {
			return new WP_Error( 'storewright_return_missing', __( 'That order does not exist.', 'storewright' ), [ 'status' => 404 ] );
		}
		$status = (string) $request['status'];
		try {
			$current = array_values( array_filter( ReturnRequests::all( $order ), static fn( array $item ): bool => $item['id'] === $request['id'] ) )[0] ?? null;
			if ( $status === 'refunded' && $current !== null ) {
				self::refund( $order, $current );
			}
			$moved = ReturnRequests::move( $order, (string) $request['id'], $status, sanitize_textarea_field( (string) $request['note'] ) );
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'storewright_return_invalid', $e->getMessage(), [ 'status' => 400 ] );
		}
		ReturnMail::customer( $order, $moved );
		return new WP_REST_Response( self::describe( wc_get_order( $order->get_id() ) ?: $order, $moved ) );
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
		];
	}
}
