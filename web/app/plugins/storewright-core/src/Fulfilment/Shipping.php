<?php

declare(strict_types=1);

namespace Storewright\Core\Fulfilment;

use Automattic\WooCommerce\Admin\Features\Fulfillments\DataStore\FulfillmentsDataStore;
use Automattic\WooCommerce\Admin\Features\Fulfillments\Fulfillment;
use Automattic\WooCommerce\Admin\Features\Fulfillments\FulfillmentUtils;
use Storewright\Core\Fulfilment\Shiprocket\Client;
use Storewright\Core\Fulfilment\Shiprocket\Shiprocket;
use Storewright\Core\Settings;
use Storewright\Platform\CarrierCredentials;
use WC_Order;

final class Shipping {

	public const GROUP = 'storewright';

	private const BOOK   = 'storewright_shipment_book';
	private const PICKUP = 'storewright_shipment_pickup';
	private const LABEL  = 'storewright_shipment_label';
	private const TRACK  = 'storewright_shipment_track';
	private const POLL   = 'storewright_shipments_poll';

	private const TRIES = 6;

	public const LAST_TRY = self::TRIES;

	public static function register(): void {
		add_action( self::BOOK, [ self::class, 'book' ] );
		add_action( self::PICKUP, [ self::class, 'pickup' ], 10, 2 );
		add_action( self::LABEL, [ self::class, 'label' ], 10, 2 );
		add_action( self::TRACK, [ self::class, 'track' ] );
		add_action( self::POLL, [ self::class, 'poll' ] );
		add_action( 'init', [ self::class, 'schedulePoll' ] );
		add_action( 'woocommerce_fulfillment_after_delete', [ self::class, 'fulfillmentRemoved' ] );
	}

	public static function carrier(): ?Carrier {
		$keys = CarrierCredentials::shiprocket( wp_get_environment_type() );
		if ( $keys === null ) {
			return null;
		}
		return new Shiprocket(
			new Client( $keys['host'], $keys['email'], $keys['password'], $keys['mode'] === CarrierCredentials::SANDBOX ),
			(string) Settings::get( 'pickup_location' )
		);
	}

	public static function store(): ShipmentStore {
		global $wpdb;
		return new ShipmentStore( $wpdb );
	}

	/** @return list<array{item_id: int, qty: int}> */
	public static function pending( WC_Order $order ): array {
		$fulfillments = wc_get_container()->get( FulfillmentsDataStore::class )->read_fulfillments( WC_Order::class, (string) $order->get_id() );
		$items        = [];
		foreach ( FulfillmentUtils::get_pending_items( $order, $fulfillments ) as $item ) {
			$product = $item['item']->get_product();
			if ( $product && $product->is_virtual() ) {
				continue;
			}
			$items[] = [
				'item_id' => (int) $item['item_id'],
				'qty'     => (int) $item['qty'],
			];
		}
		return $items;
	}

	public static function consignment( WC_Order $order, array $items, int $fulfillmentId = 0 ): Consignment {
		$first = self::store()->forOrder( $order->get_id() ) === [];
		return Consignment::fromOrder(
			$order,
			$items,
			(string) Settings::get( 'pickup_location' ),
			[
				'weight'  => (float) Settings::get( 'parcel_weight' ),
				'length'  => (float) Settings::get( 'parcel_length' ),
				'breadth' => (float) Settings::get( 'parcel_breadth' ),
				'height'  => (float) Settings::get( 'parcel_height' ),
			],
			$fulfillmentId > 0 ? $order->get_order_number() . '-' . $fulfillmentId : (string) $order->get_order_number(),
			$first ? (float) $order->get_shipping_total() + (float) $order->get_total_fees() : 0.0
		);
	}

	public static function ship( WC_Order $order, string $service ): int {
		$items = self::pending( $order );
		if ( $items === [] ) {
			throw new CarrierException( esc_html__( 'Everything in this order has already been shipped.', 'storewright' ), false );
		}
		$fulfillment = new Fulfillment();
		$fulfillment->set_entity_type( WC_Order::class );
		$fulfillment->set_entity_id( (string) $order->get_id() );
		$fulfillment->set_items( $items );
		$fulfillment->set_shipment_provider( Shiprocket::KEY );
		$fulfillment->set_status( 'unfulfilled' );
		$fulfillment->save();
		$id = $fulfillment->get_id();
		self::store()->save(
			$id,
			[
				'order_id'   => $order->get_id(),
				'carrier'    => Shiprocket::KEY,
				'service'    => $service,
				'status'     => ShipmentStore::BOOKING,
				'tries'      => 0,
				'last_error' => '',
			]
		);
		as_enqueue_async_action( self::BOOK, [ $id ], self::GROUP );
		$order->add_order_note( __( 'Booking a shipment with Shiprocket.', 'storewright' ) );
		return $id;
	}

	public static function book( int $fulfillmentId ): void {
		$store   = self::store();
		$row     = $store->find( $fulfillmentId );
		$carrier = self::carrier();
		if ( $row === null || $carrier === null || $row['status'] === ShipmentStore::CANCELLED ) {
			return;
		}
		$order       = wc_get_order( (int) $row['order_id'] );
		$fulfillment = new Fulfillment( (string) $fulfillmentId );
		if ( ! $order instanceof WC_Order || $fulfillment->get_id() === 0 ) {
			return;
		}
		try {
			$booking = new Booking( (string) $row['reference'], (string) $row['shipment'], (string) $row['awb'], (string) $row['courier'], (string) $row['service'] );
			if ( $booking->reference === '' ) {
				$booking = $carrier->create( self::consignment( $order, self::itemsOf( $fulfillment ), $fulfillmentId ) );
				$store->save(
					$fulfillmentId,
					[
						'reference' => $booking->reference,
						'shipment'  => $booking->shipment,
					]
				);
			}
			if ( ! $booking->booked() ) {
				$booking = $carrier->assign( $booking, (string) $row['service'] );
			}
		} catch ( CarrierException $e ) {
			self::failed( $order, $fulfillmentId, $e );
			throw $e;
		}
		$store->save(
			$fulfillmentId,
			[
				'awb'        => $booking->awb,
				'courier'    => $booking->courier,
				'status'     => ShipmentStore::BOOKED,
				'raw_status' => 'AWB ASSIGNED',
				'last_error' => '',
				'tries'      => 0,
			]
		);
		$fulfillment->set_shipment_provider( Carriers::keyFor( $booking->courier ) );
		$fulfillment->set_tracking_number( $booking->awb );
		$fulfillment->set_tracking_url( $booking->trackingUrl );
		$fulfillment->set_status( 'fulfilled' );
		$fulfillment->save();
		/* translators: 1: courier name, 2: waybill number */
		$order->add_order_note( sprintf( __( 'Shipment booked with %1$s, waybill %2$s. The courier is being asked to pick it up.', 'storewright' ), $booking->courier, $booking->awb ) );
		do_action( 'woocommerce_fulfillment_created_notification', $order->get_id(), $fulfillment, $order ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		as_enqueue_async_action( self::PICKUP, [ $fulfillmentId, 1 ], self::GROUP );
		as_enqueue_async_action( self::LABEL, [ $fulfillmentId, 1 ], self::GROUP );
	}

	public static function pickup( int $fulfillmentId, int $attempt = 1 ): void {
		self::step(
			$fulfillmentId,
			$attempt,
			self::PICKUP,
			static fn( Carrier $carrier, Booking $booking ): array => [ 'pickup_at' => $carrier->pickup( $booking ) ],
			__( 'The courier has not confirmed a pickup', 'storewright' )
		);
	}

	public static function label( int $fulfillmentId, int $attempt = 1 ): void {
		self::step(
			$fulfillmentId,
			$attempt,
			self::LABEL,
			static fn( Carrier $carrier, Booking $booking ): array => [ 'label_url' => $carrier->label( $booking ) ],
			__( 'The label could not be fetched', 'storewright' )
		);
	}

	public static function track( int $fulfillmentId ): void {
		$store   = self::store();
		$row     = $store->find( $fulfillmentId );
		$carrier = self::carrier();
		if ( $row === null || $carrier === null || $row['awb'] === '' || ShipmentStore::isClosed( (string) $row['status'] ) ) {
			return;
		}
		$events = $carrier->track( self::bookingOf( $row ) );
		if ( $events === [] ) {
			return;
		}
		$added  = $store->addEvents( $fulfillmentId, $events, 'poll' );
		$latest = $events[ array_key_last( $events ) ];
		if ( $latest->status === $row['status'] && $added === 0 ) {
			return;
		}
		$store->save(
			$fulfillmentId,
			[
				'status'     => $latest->status,
				'raw_status' => $latest->raw,
			]
		);
		$order = wc_get_order( (int) $row['order_id'] );
		if ( $order instanceof WC_Order && $latest->status !== $row['status'] ) {
			/* translators: 1: waybill number, 2: status in plain words, 3: place */
			$order->add_order_note( trim( sprintf( __( 'Waybill %1$s: %2$s %3$s', 'storewright' ), $row['awb'], TrackingEvent::words( $latest->status ), $latest->location !== '' ? '(' . $latest->location . ')' : '' ) ) );
		}
	}

	public static function poll(): void {
		foreach ( self::store()->open() as $row ) {
			try {
				self::track( (int) $row['fulfillment_id'] );
			} catch ( CarrierException $e ) {
				self::store()->save( (int) $row['fulfillment_id'], [ 'last_error' => $e->getMessage() ] );
			}
		}
	}

	public static function trackSoon( string $awb ): bool {
		$row = self::store()->findByAwb( $awb );
		if ( $row === null ) {
			return false;
		}
		if ( ! as_has_scheduled_action( self::TRACK, [ (int) $row['fulfillment_id'] ], self::GROUP ) ) {
			as_enqueue_async_action( self::TRACK, [ (int) $row['fulfillment_id'] ], self::GROUP );
		}
		return true;
	}

	public static function cancel( int $fulfillmentId ): void {
		$store   = self::store();
		$row     = $store->find( $fulfillmentId );
		$carrier = self::carrier();
		if ( $row === null || $carrier === null ) {
			return;
		}
		if ( $row['reference'] !== '' && $row['status'] !== ShipmentStore::CANCELLED ) {
			$carrier->cancel( self::bookingOf( $row ) );
		}
		$store->save(
			$fulfillmentId,
			[
				'status'     => ShipmentStore::CANCELLED,
				'raw_status' => 'CANCELED',
			]
		);
		$fulfillment = new Fulfillment( (string) $fulfillmentId );
		if ( $fulfillment->get_id() > 0 ) {
			$fulfillment->delete();
		}
		$order = wc_get_order( (int) $row['order_id'] );
		if ( $order instanceof WC_Order ) {
			/* translators: %s: waybill number or "the booking" */
			$order->add_order_note( sprintf( __( 'Shiprocket shipment %s cancelled; the items can be shipped again.', 'storewright' ), $row['awb'] !== '' ? $row['awb'] : __( 'booking', 'storewright' ) ) );
		}
	}

	public static function retry( int $fulfillmentId ): void {
		$store = self::store();
		$row   = $store->find( $fulfillmentId );
		if ( $row === null ) {
			return;
		}
		$store->save(
			$fulfillmentId,
			[
				'last_error' => '',
				'tries'      => 0,
				'status'     => $row['awb'] === '' ? ShipmentStore::BOOKING : $row['status'],
			]
		);
		if ( $row['awb'] === '' ) {
			as_enqueue_async_action( self::BOOK, [ $fulfillmentId ], self::GROUP );
			return;
		}
		if ( $row['pickup_at'] === '' || $row['pickup_at'] === null ) {
			as_enqueue_async_action( self::PICKUP, [ $fulfillmentId, 1 ], self::GROUP );
		}
		if ( $row['label_url'] === '' || $row['label_url'] === null ) {
			as_enqueue_async_action( self::LABEL, [ $fulfillmentId, 1 ], self::GROUP );
		}
	}

	public static function fulfillmentRemoved( mixed $fulfillment ): void {
		if ( ! $fulfillment instanceof Fulfillment ) {
			return;
		}
		$row = self::store()->find( $fulfillment->get_id() );
		if ( $row === null || $row['status'] === ShipmentStore::CANCELLED || self::carrier() === null ) {
			return;
		}
		try {
			if ( $row['reference'] !== '' ) {
				self::carrier()->cancel( self::bookingOf( $row ) );
			}
		} catch ( CarrierException $e ) {
			self::store()->save( $fulfillment->get_id(), [ 'last_error' => $e->getMessage() ] );
		}
		self::store()->save(
			$fulfillment->get_id(),
			[
				'status'     => ShipmentStore::CANCELLED,
				'raw_status' => 'CANCELED',
			]
		);
	}

	public static function schedulePoll(): void {
		if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( self::POLL, [], self::GROUP ) ) {
			as_schedule_recurring_action( time() + 5 * MINUTE_IN_SECONDS, 30 * MINUTE_IN_SECONDS, self::POLL, [], self::GROUP );
		}
	}

	private static function step( int $fulfillmentId, int $attempt, string $hook, callable $work, string $gaveUp ): void {
		$store   = self::store();
		$row     = $store->find( $fulfillmentId );
		$carrier = self::carrier();
		if ( $row === null || $carrier === null || $row['awb'] === '' || ShipmentStore::isClosed( (string) $row['status'] ) ) {
			return;
		}
		try {
			$store->save( $fulfillmentId, $work( $carrier, self::bookingOf( $row ) ) + [ 'last_error' => '' ] );
		} catch ( CarrierException $e ) {
			if ( $e->retryable && $attempt < self::TRIES ) {
				as_schedule_single_action( time() + $attempt * 10 * MINUTE_IN_SECONDS, $hook, [ $fulfillmentId, $attempt + 1 ], self::GROUP );
				return;
			}
			$store->save( $fulfillmentId, [ 'last_error' => $gaveUp . ': ' . $e->getMessage() ] );
			throw $e;
		}
	}

	private static function failed( WC_Order $order, int $fulfillmentId, CarrierException $e ): void {
		self::store()->save(
			$fulfillmentId,
			[
				'status'     => ShipmentStore::FAILED,
				'last_error' => $e->getMessage(),
			]
		);
		/* translators: %s: the carrier's message */
		$order->add_order_note( sprintf( __( 'Shiprocket could not book the shipment: %s', 'storewright' ), $e->getMessage() ) );
	}

	/** @return list<array{item_id: int, qty: int}> */
	private static function itemsOf( Fulfillment $fulfillment ): array {
		return array_values(
			array_map(
				static fn( array $item ): array => [
					'item_id' => (int) $item['item_id'],
					'qty'     => (int) $item['qty'],
				],
				$fulfillment->get_items()
			)
		);
	}

	private static function bookingOf( array $row ): Booking {
		return new Booking( (string) $row['reference'], (string) $row['shipment'], (string) $row['awb'], (string) $row['courier'], (string) $row['service'], (string) $row['awb'] !== '' ? 'https://shiprocket.co/tracking/' . $row['awb'] : '' );
	}
}
