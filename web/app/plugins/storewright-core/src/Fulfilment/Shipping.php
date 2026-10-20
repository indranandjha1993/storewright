<?php

declare(strict_types=1);

namespace Storewright\Core\Fulfilment;

use Automattic\WooCommerce\Admin\Features\Fulfillments\DataStore\FulfillmentsDataStore;
use Automattic\WooCommerce\Admin\Features\Fulfillments\Fulfillment;
use Automattic\WooCommerce\Admin\Features\Fulfillments\FulfillmentUtils;
use Storewright\Core\Fulfilment\Shiprocket\Client;
use Storewright\Core\Fulfilment\Shiprocket\Shiprocket;
use Storewright\Core\Returns\ReturnDesk;
use Storewright\Core\Returns\ReturnMail;
use Storewright\Core\Returns\ReturnRequests;
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

	/** @param list<array{item_id: int, qty: int}> $items */
	public static function consignment( WC_Order $order, array $items, string $reference = '', bool $reverse = false ): Consignment {
		$first = ! $reverse && self::store()->forOrder( $order->get_id() ) === [];
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
			$reference !== '' ? $reference : (string) $order->get_order_number(),
			$first ? (float) $order->get_shipping_total() + (float) $order->get_total_fees() : 0.0,
			$reverse
		);
	}

	public static function ship( WC_Order $order, string $service ): int {
		self::lock( $order->get_id() );
		try {
			return self::open( $order, $service );
		} finally {
			delete_option( self::lockName( $order->get_id() ) );
		}
	}

	public static function lock( int $orderId ): void {
		$name = self::lockName( $orderId );
		$held = get_option( $name );
		if ( is_numeric( $held ) && (int) $held < time() - MINUTE_IN_SECONDS ) {
			delete_option( $name );
		}
		if ( ! add_option( $name, (string) time(), '', false ) ) {
			throw new CarrierException( esc_html__( 'A booking for this order is already on its way; give it a moment.', 'storewright' ), false );
		}
	}

	private static function lockName( int $orderId ): string {
		return 'storewright_booking_' . $orderId;
	}

	private static function open( WC_Order $order, string $service ): int {
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
		$id = self::store()->insert(
			[
				'kind'           => ShipmentStore::OUTBOUND,
				'order_id'       => $order->get_id(),
				'fulfillment_id' => $fulfillment->get_id(),
				'carrier'        => Shiprocket::KEY,
				'service'        => $service,
				'status'         => ShipmentStore::BOOKING,
			]
		);
		as_enqueue_async_action( self::BOOK, [ $id ], self::GROUP );
		$order->add_order_note( __( 'Booking a shipment with Shiprocket.', 'storewright' ) );
		return $id;
	}

	/** @param array<int|string, int> $items */
	public static function collect( WC_Order $order, string $returnId, array $items ): int {
		$carrier = self::carrier();
		if ( $carrier === null ) {
			throw new CarrierException( esc_html__( 'Shiprocket is off on this store.', 'storewright' ), false );
		}
		self::lock( $order->get_id() );
		try {
			return self::openReturn( $order, $returnId, $items, $carrier );
		} finally {
			delete_option( self::lockName( $order->get_id() ) );
		}
	}

	/** @param array<int|string, int> $items */
	private static function openReturn( WC_Order $order, string $returnId, array $items, Carrier $carrier ): int {
		if ( self::store()->forReturn( $order->get_id(), $returnId ) !== null ) {
			throw new CarrierException( esc_html__( 'A pickup is already booked for this return.', 'storewright' ), false );
		}
		$list = [];
		foreach ( $items as $itemId => $qty ) {
			$list[] = [
				'item_id' => (int) $itemId,
				'qty'     => (int) $qty,
			];
		}
		$quotes = $carrier->quotes( self::consignment( $order, $list, '', true ) );
		if ( $quotes === [] ) {
			throw new CarrierException( esc_html__( 'No courier offers a pickup from that address.', 'storewright' ), false );
		}
		$id = self::store()->insert(
			[
				'kind'      => ShipmentStore::RETURN,
				'order_id'  => $order->get_id(),
				'return_id' => $returnId,
				'carrier'   => Shiprocket::KEY,
				'service'   => self::bestService( $quotes ),
				'status'    => ShipmentStore::BOOKING,
			]
		);
		as_enqueue_async_action( self::BOOK, [ $id ], self::GROUP );
		$order->add_order_note( __( 'Booking the return pickup with Shiprocket.', 'storewright' ) );
		return $id;
	}

	/** @param list<Quote> $quotes */
	public static function bestService( array $quotes ): string {
		foreach ( $quotes as $quote ) {
			if ( $quote->recommended ) {
				return $quote->service;
			}
		}
		return $quotes[0]->service;
	}

	public static function book( int $id ): void {
		$store   = self::store();
		$row     = $store->find( $id );
		$carrier = self::carrier();
		if ( $row === null || $carrier === null || $row['status'] === ShipmentStore::CANCELLED ) {
			return;
		}
		$order = wc_get_order( (int) $row['order_id'] );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		try {
			$booking = self::bookingOf( $row );
			if ( $booking->reference === '' ) {
				$booking = $carrier->create( self::consignmentOf( $order, $row ) );
				$store->save(
					$id,
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
			self::failed( $order, $id, $e );
			throw $e;
		}
		$store->save(
			$id,
			[
				'awb'        => $booking->awb,
				'courier'    => $booking->courier,
				'status'     => ShipmentStore::BOOKED,
				'raw_status' => 'AWB ASSIGNED',
				'last_error' => '',
				'tries'      => 0,
			]
		);
		if ( $row['kind'] === ShipmentStore::RETURN ) {
			self::returnBooked( $order, $row, $booking );
		} else {
			self::shipmentBooked( $order, (int) $row['fulfillment_id'], $booking );
		}
		as_enqueue_async_action( self::PICKUP, [ $id, 1 ], self::GROUP );
		as_enqueue_async_action( self::LABEL, [ $id, 1 ], self::GROUP );
	}

	public static function pickup( int $id, int $attempt = 1 ): void {
		self::step(
			$id,
			$attempt,
			self::PICKUP,
			static fn( Carrier $carrier, Booking $booking ): array => [ 'pickup_at' => $carrier->pickup( $booking ) ],
			__( 'The courier has not confirmed a pickup', 'storewright' )
		);
	}

	public static function label( int $id, int $attempt = 1 ): void {
		self::step(
			$id,
			$attempt,
			self::LABEL,
			static fn( Carrier $carrier, Booking $booking ): array => [ 'label_url' => $carrier->label( $booking ) ],
			__( 'The label could not be fetched', 'storewright' )
		);
	}

	public static function track( int $id ): void {
		$store   = self::store();
		$row     = $store->find( $id );
		$carrier = self::carrier();
		if ( $row === null || $carrier === null || $row['awb'] === '' || ShipmentStore::isClosed( (string) $row['status'] ) ) {
			return;
		}
		$events = $carrier->track( self::bookingOf( $row ) );
		if ( $events === [] ) {
			return;
		}
		$added  = $store->addEvents( $id, $events, 'poll' );
		$latest = $events[ array_key_last( $events ) ];
		if ( $latest->status === $row['status'] && $added === 0 ) {
			return;
		}
		$store->save(
			$id,
			[
				'status'     => $latest->status,
				'raw_status' => $latest->raw,
			]
		);
		$order = wc_get_order( (int) $row['order_id'] );
		if ( ! $order instanceof WC_Order || $latest->status === $row['status'] ) {
			return;
		}
		/* translators: 1: waybill number, 2: status in plain words, 3: place */
		$order->add_order_note( trim( sprintf( __( 'Waybill %1$s: %2$s %3$s', 'storewright' ), $row['awb'], TrackingEvent::words( $latest->status ), $latest->location !== '' ? '(' . $latest->location . ')' : '' ) ) );
		if ( $latest->status === TrackingEvent::DELIVERED ) {
			self::delivered( $order, $row );
		}
	}

	public static function poll(): void {
		foreach ( self::store()->open() as $row ) {
			try {
				self::track( (int) $row['id'] );
			} catch ( CarrierException $e ) {
				self::store()->save( (int) $row['id'], [ 'last_error' => $e->getMessage() ] );
			}
		}
	}

	public static function trackSoon( string $awb ): bool {
		$row = self::store()->findByAwb( $awb );
		if ( $row === null ) {
			return false;
		}
		if ( ! as_has_scheduled_action( self::TRACK, [ (int) $row['id'] ], self::GROUP ) ) {
			as_enqueue_async_action( self::TRACK, [ (int) $row['id'] ], self::GROUP );
		}
		return true;
	}

	public static function cancel( int $id ): void {
		$store   = self::store();
		$row     = $store->find( $id );
		$carrier = self::carrier();
		if ( $row === null || $carrier === null ) {
			return;
		}
		if ( $row['reference'] !== '' && $row['status'] !== ShipmentStore::CANCELLED ) {
			$carrier->cancel( self::bookingOf( $row ) );
		}
		$store->save(
			$id,
			[
				'status'     => ShipmentStore::CANCELLED,
				'raw_status' => 'CANCELED',
			]
		);
		$fulfillment = (int) $row['fulfillment_id'] > 0 ? new Fulfillment( (string) $row['fulfillment_id'] ) : null;
		if ( $fulfillment && $fulfillment->get_id() > 0 ) {
			$fulfillment->delete();
		}
		$order = wc_get_order( (int) $row['order_id'] );
		if ( $order instanceof WC_Order ) {
			$what = $row['kind'] === ShipmentStore::RETURN ? __( 'return pickup', 'storewright' ) : __( 'shipment', 'storewright' );
			/* translators: 1: shipment or return pickup, 2: waybill number or "booking" */
			$order->add_order_note( sprintf( __( 'Shiprocket %1$s %2$s cancelled.', 'storewright' ), $what, $row['awb'] !== '' ? $row['awb'] : __( 'booking', 'storewright' ) ) );
		}
	}

	public static function retry( int $id ): void {
		$store = self::store();
		$row   = $store->find( $id );
		if ( $row === null ) {
			return;
		}
		$store->save(
			$id,
			[
				'last_error' => '',
				'tries'      => 0,
				'status'     => $row['awb'] === '' ? ShipmentStore::BOOKING : $row['status'],
			]
		);
		if ( $row['awb'] === '' ) {
			as_enqueue_async_action( self::BOOK, [ $id ], self::GROUP );
			return;
		}
		if ( $row['pickup_at'] === '' || $row['pickup_at'] === null ) {
			as_enqueue_async_action( self::PICKUP, [ $id, 1 ], self::GROUP );
		}
		if ( $row['label_url'] === '' || $row['label_url'] === null ) {
			as_enqueue_async_action( self::LABEL, [ $id, 1 ], self::GROUP );
		}
	}

	public static function fulfillmentRemoved( mixed $fulfillment ): void {
		if ( ! $fulfillment instanceof Fulfillment ) {
			return;
		}
		$row = self::store()->forFulfillment( $fulfillment->get_id() );
		if ( $row === null || $row['status'] === ShipmentStore::CANCELLED || self::carrier() === null ) {
			return;
		}
		try {
			if ( $row['reference'] !== '' ) {
				self::carrier()->cancel( self::bookingOf( $row ) );
			}
		} catch ( CarrierException $e ) {
			self::store()->save( (int) $row['id'], [ 'last_error' => $e->getMessage() ] );
		}
		self::store()->save(
			(int) $row['id'],
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

	/** @param list<array<string, mixed>> $rows */
	public static function allDelivered( array $rows ): bool {
		$counted = 0;
		foreach ( $rows as $row ) {
			if ( $row['kind'] !== ShipmentStore::OUTBOUND || in_array( $row['status'], [ ShipmentStore::CANCELLED, ShipmentStore::FAILED ], true ) ) {
				continue;
			}
			if ( $row['status'] !== TrackingEvent::DELIVERED ) {
				return false;
			}
			++$counted;
		}
		return $counted > 0;
	}

	private static function delivered( WC_Order $order, array $row ): void {
		if ( $row['kind'] === ShipmentStore::RETURN ) {
			$request = ReturnRequests::find( $order, (string) $row['return_id'] );
			if ( $request !== null && $request['status'] === 'approved' ) {
				ReturnDesk::move( $order->get_id(), (string) $row['return_id'], 'received', '' );
			}
			return;
		}
		if ( $order->get_status() === 'processing' && self::pending( $order ) === [] && self::allDelivered( self::store()->forOrder( $order->get_id() ) ) ) {
			$order->update_status( 'completed', __( 'Every parcel is delivered.', 'storewright' ) );
		}
	}

	private static function shipmentBooked( WC_Order $order, int $fulfillmentId, Booking $booking ): void {
		$fulfillment = new Fulfillment( (string) $fulfillmentId );
		if ( $fulfillment->get_id() === 0 ) {
			return;
		}
		$fulfillment->set_shipment_provider( Carriers::keyFor( $booking->courier ) );
		$fulfillment->set_tracking_number( $booking->awb );
		$fulfillment->set_tracking_url( $booking->trackingUrl );
		$fulfillment->set_status( 'fulfilled' );
		$fulfillment->save();
		/* translators: 1: courier name, 2: waybill number */
		$order->add_order_note( sprintf( __( 'Shipment booked with %1$s, waybill %2$s. The courier is being asked to pick it up.', 'storewright' ), $booking->courier, $booking->awb ) );
		do_action( 'woocommerce_fulfillment_created_notification', $order->get_id(), $fulfillment, $order ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}

	private static function returnBooked( WC_Order $order, array $row, Booking $booking ): void {
		/* translators: 1: courier name, 2: waybill number */
		$order->add_order_note( sprintf( __( 'Return pickup booked with %1$s, waybill %2$s.', 'storewright' ), $booking->courier, $booking->awb ) );
		$request = ReturnRequests::find( $order, (string) $row['return_id'] );
		if ( $request !== null ) {
			ReturnMail::pickup( $order, $request, $booking->courier, $booking->awb );
		}
	}

	private static function consignmentOf( WC_Order $order, array $row ): Consignment {
		if ( $row['kind'] === ShipmentStore::RETURN ) {
			$request = ReturnRequests::find( $order, (string) $row['return_id'] );
			$items   = [];
			foreach ( (array) ( $request['items'] ?? [] ) as $itemId => $qty ) {
				$items[] = [
					'item_id' => (int) $itemId,
					'qty'     => (int) $qty,
				];
			}
			return self::consignment( $order, $items, $order->get_order_number() . '-R' . $row['id'], true );
		}
		$fulfillment = new Fulfillment( (string) $row['fulfillment_id'] );
		$items       = [];
		foreach ( $fulfillment->get_items() as $item ) {
			$items[] = [
				'item_id' => (int) $item['item_id'],
				'qty'     => (int) $item['qty'],
			];
		}
		return self::consignment( $order, $items, $order->get_order_number() . '-' . $row['id'] );
	}

	private static function step( int $id, int $attempt, string $hook, callable $work, string $gaveUp ): void {
		$store   = self::store();
		$row     = $store->find( $id );
		$carrier = self::carrier();
		if ( $row === null || $carrier === null || $row['awb'] === '' || ShipmentStore::isClosed( (string) $row['status'] ) ) {
			return;
		}
		try {
			$store->save( $id, $work( $carrier, self::bookingOf( $row ) ) + [ 'last_error' => '' ] );
		} catch ( CarrierException $e ) {
			if ( $e->retryable && $attempt < self::TRIES ) {
				as_schedule_single_action( time() + $attempt * 10 * MINUTE_IN_SECONDS, $hook, [ $id, $attempt + 1 ], self::GROUP );
				return;
			}
			$store->save( $id, [ 'last_error' => $gaveUp . ': ' . $e->getMessage() ] );
			throw $e;
		}
	}

	private static function failed( WC_Order $order, int $id, CarrierException $e ): void {
		self::store()->save(
			$id,
			[
				'status'     => ShipmentStore::FAILED,
				'last_error' => $e->getMessage(),
			]
		);
		/* translators: %s: the carrier's message */
		$order->add_order_note( sprintf( __( 'Shiprocket could not book it: %s', 'storewright' ), $e->getMessage() ) );
	}

	private static function bookingOf( array $row ): Booking {
		return new Booking(
			(string) $row['reference'],
			(string) $row['shipment'],
			(string) $row['awb'],
			(string) $row['courier'],
			(string) $row['service'],
			(string) $row['awb'] !== '' ? 'https://shiprocket.co/tracking/' . $row['awb'] : '',
			$row['kind'] === ShipmentStore::RETURN
		);
	}
}
