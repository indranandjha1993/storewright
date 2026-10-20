<?php

declare(strict_types=1);

namespace Storewright\Core\Returns;

use InvalidArgumentException;
use Storewright\Core\Settings;
use WC_Order;
use WC_Order_Item_Product;

final class ReturnRequests {

	public const META   = '_storewright_returns';
	public const OPEN   = '_storewright_return_open';
	public const STATES = [ 'requested', 'approved', 'received', 'refunded', 'declined' ];

	public const REASONS = [ 'damaged', 'wrong', 'fit', 'changed', 'other' ];

	public static function all( WC_Order $order ): array {
		$saved = $order->get_meta( self::META );
		return is_array( $saved ) ? array_values( $saved ) : [];
	}

	public static function find( WC_Order $order, string $id ): ?array {
		foreach ( self::all( $order ) as $request ) {
			if ( $request['id'] === $id ) {
				return $request;
			}
		}
		return null;
	}

	public static function deadline( WC_Order $order ): ?int {
		$completed = $order->get_date_completed();
		return $completed ? $completed->getTimestamp() + (int) Settings::get( 'return_days' ) * DAY_IN_SECONDS : null;
	}

	public static function returnable( WC_Order $order ): array {
		$deadline = self::deadline( $order );
		if ( $order->get_status() !== 'completed' || $deadline === null || $deadline < time() ) {
			return [];
		}
		$taken = [];
		foreach ( self::all( $order ) as $request ) {
			if ( $request['status'] !== 'declined' ) {
				foreach ( $request['items'] as $id => $qty ) {
					$taken[ (int) $id ] = ( $taken[ (int) $id ] ?? 0 ) + (int) $qty;
				}
			}
		}
		$left = [];
		foreach ( $order->get_items() as $id => $item ) {
			$product = $item instanceof WC_Order_Item_Product ? $item->get_product() : null;
			if ( ! $product || ! $product->needs_shipping() ) {
				continue;
			}
			$qty = $item->get_quantity() + $order->get_qty_refunded_for_item( $id ) - ( $taken[ $id ] ?? 0 );
			if ( $qty > 0 ) {
				$left[ $id ] = $qty;
			}
		}
		return $left;
	}

	public static function request( WC_Order $order, array $items, string $reason, string $note ): array {
		$left  = self::returnable( $order );
		$items = array_filter( array_map( 'intval', $items ), static fn( int $qty ): bool => $qty > 0 );
		if ( $items === [] ) {
			throw new InvalidArgumentException( esc_html__( 'Choose at least one item to send back.', 'storewright' ) );
		}
		foreach ( $items as $id => $qty ) {
			if ( $qty > ( $left[ (int) $id ] ?? 0 ) ) {
				throw new InvalidArgumentException( esc_html__( 'Some of those items cannot be returned.', 'storewright' ) );
			}
		}
		if ( ! in_array( $reason, self::REASONS, true ) ) {
			throw new InvalidArgumentException( esc_html__( 'Tell us why the items are coming back.', 'storewright' ) );
		}
		$request = [
			'id'      => wp_generate_uuid4(),
			'items'   => $items,
			'reason'  => $reason,
			'note'    => mb_substr( $note, 0, 500 ),
			'status'  => 'requested',
			'created' => gmdate( 'c' ),
			'updated' => gmdate( 'c' ),
			'amount'  => self::amount( $order, $items ),
		];
		self::save( $order, [ ...self::all( $order ), $request ] );
		$order->add_order_note( sprintf( 'Return requested: %s. Reason: %s.', self::summary( $order, $items ), self::reason( $reason ) ) );
		return $request;
	}

	public static function move( WC_Order $order, string $id, string $status, string $note = '' ): array {
		$requests = self::all( $order );
		foreach ( $requests as $index => $request ) {
			if ( $request['id'] !== $id ) {
				continue;
			}
			if ( ! in_array( $status, self::next( $request['status'] ), true ) ) {
				throw new InvalidArgumentException( esc_html__( 'That step does not follow from where the return is now.', 'storewright' ) );
			}
			$requests[ $index ] = [
				...$request,
				'status'  => $status,
				'updated' => gmdate( 'c' ),
				'answer'  => $note !== '' ? mb_substr( $note, 0, 500 ) : ( $request['answer'] ?? '' ),
			];
			self::save( $order, $requests );
			$order->add_order_note( sprintf( 'Return %s: %s.', $status, self::summary( $order, $request['items'] ) ) );
			return $requests[ $index ];
		}
		throw new InvalidArgumentException( esc_html__( 'That return does not exist.', 'storewright' ) );
	}

	public static function next( string $status ): array {
		return match ( $status ) {
			'requested' => [ 'approved', 'declined' ],
			'approved'  => [ 'received', 'declined' ],
			'received'  => [ 'refunded' ],
			default     => [],
		};
	}

	public static function amount( WC_Order $order, array $items ): float {
		$total = 0.0;
		foreach ( $items as $id => $qty ) {
			$item = $order->get_item( (int) $id );
			if ( $item instanceof WC_Order_Item_Product && $item->get_quantity() > 0 ) {
				$total += (float) $item->get_total() / $item->get_quantity() * (int) $qty;
			}
		}
		return round( $total, 2 );
	}

	public static function summary( WC_Order $order, array $items ): string {
		$parts = [];
		foreach ( $items as $id => $qty ) {
			$item    = $order->get_item( (int) $id );
			$parts[] = sprintf( '%d x %s', (int) $qty, $item ? $item->get_name() : '#' . $id );
		}
		return implode( ', ', $parts );
	}

	public static function reason( string $reason ): string {
		return match ( $reason ) {
			'damaged' => __( 'Arrived damaged', 'storewright' ),
			'wrong'   => __( 'Wrong item sent', 'storewright' ),
			'fit'     => __( 'Does not fit', 'storewright' ),
			'changed' => __( 'Changed my mind', 'storewright' ),
			default   => __( 'Something else', 'storewright' ),
		};
	}

	private static function save( WC_Order $order, array $requests ): void {
		$open = array_filter( $requests, static fn( array $request ): bool => in_array( $request['status'], [ 'requested', 'approved', 'received' ], true ) );
		$order->update_meta_data( self::META, array_values( $requests ) );
		$order->update_meta_data( self::OPEN, $open === [] ? 'no' : 'yes' );
		$order->save();
	}
}
