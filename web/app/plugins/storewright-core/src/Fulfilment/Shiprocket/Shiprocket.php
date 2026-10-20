<?php

declare(strict_types=1);

namespace Storewright\Core\Fulfilment\Shiprocket;

use DateTimeImmutable;
use DateTimeZone;
use Storewright\Core\Fulfilment\Booking;
use Storewright\Core\Fulfilment\Carrier;
use Storewright\Core\Fulfilment\CarrierException;
use Storewright\Core\Fulfilment\Consignment;
use Storewright\Core\Fulfilment\Quote;
use Storewright\Core\Fulfilment\TrackingEvent;
use Storewright\Core\Settings;

final class Shiprocket implements Carrier {

	public const KEY = 'shiprocket';

	private const TRACKING = 'https://shiprocket.co/tracking/';

	private const DEFAULT_PICKUP = 'Store';

	public function __construct( private readonly Client $client, private readonly string $pickup = '' ) {}

	public function key(): string {
		return self::KEY;
	}

	public function quotes( Consignment $consignment ): array {
		$store  = $this->pickupLocation()['pin'];
		$data   = $this->client->get(
			'courier/serviceability/',
			[
				'pickup_postcode'   => $consignment->reverse ? $consignment->customer['pin'] : $store,
				'delivery_postcode' => $consignment->reverse ? $store : $consignment->customer['pin'],
				'weight'            => $consignment->weight,
				'cod'               => $consignment->cod ? 1 : 0,
				'declared_value'    => $consignment->total(),
				'is_return'         => $consignment->reverse ? 1 : 0,
			]
		)['data'] ?? [];
		$quotes = self::parseQuotes( is_array( $data ) ? $data : [] );
		usort( $quotes, static fn( Quote $a, Quote $b ): int => $a->rate <=> $b->rate );
		return $quotes;
	}

	/** @return list<Quote> */
	public static function parseQuotes( array $data ): array {
		$recommended = (string) ( $data['recommended_courier_company_id'] ?? '' );
		$quotes      = [];
		foreach ( (array) ( $data['available_courier_companies'] ?? [] ) as $courier ) {
			if ( ! is_array( $courier ) || empty( $courier['courier_company_id'] ) ) {
				continue;
			}
			$quotes[] = new Quote(
				(string) $courier['courier_company_id'],
				(string) ( $courier['courier_name'] ?? '' ),
				(float) ( $courier['rate'] ?? 0 ),
				(string) ( $courier['etd'] ?? '' ),
				(string) $courier['courier_company_id'] === $recommended
			);
		}
		return $quotes;
	}

	public function create( Consignment $consignment ): Booking {
		$data = $consignment->reverse
			? $this->client->post( 'orders/create/return', self::returnPayload( $consignment, self::storeAddress( true ) ) )
			: $this->client->post( 'orders/create/adhoc', self::payload( $consignment, $this->pickupLocation()['name'] ) );
		if ( empty( $data['shipment_id'] ) ) {
			throw new CarrierException( esc_html( (string) ( $data['message'] ?? 'Shiprocket did not open a shipment' ) ), false );
		}
		return new Booking( (string) $data['order_id'], (string) $data['shipment_id'], reverse: $consignment->reverse );
	}

	public static function payload( Consignment $c, string $pickup ): array {
		return [
			'order_id'              => $c->reference,
			'order_date'            => $c->orderedAt,
			'pickup_location'       => $pickup,
			'billing_customer_name' => $c->customer['first'],
			'billing_last_name'     => $c->customer['last'],
			'billing_address'       => $c->customer['address'],
			'billing_address_2'     => $c->customer['address2'],
			'billing_city'          => $c->customer['city'],
			'billing_pincode'       => $c->customer['pin'],
			'billing_state'         => $c->customer['state'],
			'billing_country'       => $c->customer['country'],
			'billing_email'         => $c->customer['email'],
			'billing_phone'         => $c->customer['phone'],
			'shipping_is_billing'   => true,
			'order_items'           => self::items( $c ),
			'payment_method'        => $c->cod ? 'COD' : 'Prepaid',
			'shipping_charges'      => $c->charges,
			'sub_total'             => $c->subtotal,
			'length'                => $c->length,
			'breadth'               => $c->breadth,
			'height'                => $c->height,
			'weight'                => $c->weight,
		];
	}

	public static function returnPayload( Consignment $c, array $store ): array {
		return [
			'order_id'               => $c->reference,
			'order_date'             => $c->orderedAt,
			'pickup_customer_name'   => $c->customer['first'],
			'pickup_last_name'       => $c->customer['last'],
			'pickup_address'         => $c->customer['address'],
			'pickup_address_2'       => $c->customer['address2'],
			'pickup_city'            => $c->customer['city'],
			'pickup_state'           => $c->customer['state'],
			'pickup_country'         => $c->customer['country'],
			'pickup_pincode'         => $c->customer['pin'],
			'pickup_email'           => $c->customer['email'],
			'pickup_phone'           => $c->customer['phone'],
			'shipping_customer_name' => $store['name'],
			'shipping_last_name'     => '',
			'shipping_address'       => $store['address'],
			'shipping_address_2'     => $store['address2'],
			'shipping_city'          => $store['city'],
			'shipping_state'         => $store['state'],
			'shipping_country'       => $store['country'],
			'shipping_pincode'       => $store['pin'],
			'shipping_email'         => $store['email'],
			'shipping_phone'         => $store['phone'],
			'order_items'            => self::items( $c ),
			'payment_method'         => 'Prepaid',
			'sub_total'              => $c->subtotal,
			'length'                 => $c->length,
			'breadth'                => $c->breadth,
			'height'                 => $c->height,
			'weight'                 => $c->weight,
		];
	}

	public function assign( Booking $booking, string $service ): Booking {
		// Shiprocket takes half a minute to allot a waybill, so this only ever runs inside a job.
		$body = [
			'shipment_id' => (int) $booking->shipment,
			'courier_id'  => (int) $service,
		];
		if ( $booking->reverse ) {
			$body['is_return'] = 1;
		}
		$data = $this->client->post( 'courier/assign/awb', $body, 90 );
		$awb  = (string) ( $data['response']['data']['awb_code'] ?? '' );
		if ( (int) ( $data['awb_assign_status'] ?? 0 ) !== 1 || $awb === '' ) {
			$reason = $data['response']['data'] ?? $data['message'] ?? 'Shiprocket could not allot a waybill';
			throw new CarrierException( esc_html( is_string( $reason ) ? $reason : wp_json_encode( $reason ) ) );
		}
		return $booking->with( $awb, (string) ( $data['response']['data']['courier_name'] ?? '' ), $service, self::TRACKING . $awb );
	}

	public function pickup( Booking $booking ): string {
		$data = $this->client->post( 'courier/generate/pickup', [ 'shipment_id' => [ (int) $booking->shipment ] ], 60 );
		$when = $data['response']['pickup_scheduled_date'] ?? $data['response']['data'] ?? '';
		if ( (int) ( $data['pickup_status'] ?? 0 ) !== 1 ) {
			throw new CarrierException( esc_html( is_string( $when ) && $when !== '' ? $when : 'The courier has not confirmed a pickup yet' ) );
		}
		return is_string( $when ) ? $when : '';
	}

	public function label( Booking $booking ): string {
		$data = $this->client->post( 'courier/generate/label', [ 'shipment_id' => [ (int) $booking->shipment ] ], 60 );
		$url  = (string) ( $data['label_url'] ?? '' );
		if ( (int) ( $data['label_created'] ?? 0 ) !== 1 || $url === '' ) {
			throw new CarrierException( esc_html( (string) ( $data['message'] ?? 'The label is not ready yet' ) ) );
		}
		return $url;
	}

	public function cancel( Booking $booking ): void {
		if ( $booking->awb !== '' ) {
			$this->client->post( 'orders/cancel/shipment/awbs', [ 'awbs' => [ $booking->awb ] ], 60 );
		}
		$this->client->post( 'orders/cancel', [ 'ids' => [ (int) $booking->reference ] ], 60 );
	}

	public function track( Booking $booking ): array {
		$data = $this->client->get( 'courier/track/awb/' . rawurlencode( $booking->awb ) )['tracking_data'] ?? [];
		return self::parseTracking( is_array( $data ) ? $data : [] );
	}

	/** @return list<TrackingEvent> */
	public static function parseTracking( array $data ): array {
		if ( (int) ( $data['track_status'] ?? 0 ) !== 1 ) {
			return [];
		}
		$events = [];
		foreach ( (array) ( $data['shipment_track_activities'] ?? [] ) as $scan ) {
			if ( ! is_array( $scan ) || empty( $scan['date'] ) ) {
				continue;
			}
			$raw      = (string) ( $scan['sr-status-label'] ?? $scan['activity'] ?? '' );
			$events[] = new TrackingEvent( self::timestamp( (string) $scan['date'] ), TrackingEvent::fromWords( $raw ), $raw, (string) ( $scan['location'] ?? '' ) );
		}
		usort( $events, static fn( TrackingEvent $a, TrackingEvent $b ): int => $a->at <=> $b->at );
		return $events;
	}

	/** @return array{name: string, pin: string} */
	public function pickupLocation(): array {
		$key   = 'storewright_shiprocket_pickup_' . md5( $this->pickup );
		$found = get_transient( $key );
		if ( is_array( $found ) ) {
			return $found;
		}
		$addresses = $this->client->get( 'settings/company/pickup' )['data']['shipping_address'] ?? [];
		$chosen    = null;
		foreach ( (array) $addresses as $address ) {
			if ( ! is_array( $address ) ) {
				continue;
			}
			if ( $chosen === null || ( $this->pickup !== '' && (string) ( $address['pickup_location'] ?? '' ) === $this->pickup ) ) {
				$chosen = $address;
			}
		}
		if ( $chosen === null ) {
			$chosen = $this->addStorePickup();
		}
		$found = [
			'name' => (string) ( $chosen['pickup_location'] ?? $chosen['pickup_code'] ?? self::DEFAULT_PICKUP ),
			'pin'  => (string) ( $chosen['pin_code'] ?? '' ),
		];
		set_transient( $key, $found, 10 * MINUTE_IN_SECONDS );
		return $found;
	}

	public static function storeAddress( bool $complete = false ): array {
		[ $country, $state ] = array_pad( explode( ':', (string) get_option( 'woocommerce_default_country', 'IN:KA' ) ), 2, '' );
		$phone               = Consignment::phone( (string) Settings::get( 'store_phone' ) );
		if ( $complete && ( $phone === '' || (string) get_option( 'woocommerce_store_address' ) === '' || (string) get_option( 'woocommerce_store_postcode' ) === '' ) ) {
			throw new CarrierException( esc_html__( 'The courier needs the store address and phone: fill them under Store, Settings, General.', 'storewright' ), false );
		}
		return [
			'name'     => (string) get_bloginfo( 'name' ),
			'email'    => (string) get_option( 'admin_email' ),
			'phone'    => $phone,
			'address'  => (string) get_option( 'woocommerce_store_address' ),
			'address2' => (string) get_option( 'woocommerce_store_address_2' ),
			'city'     => (string) get_option( 'woocommerce_store_city' ),
			'state'    => Consignment::stateName( $country, $state ),
			'country'  => Consignment::countryName( $country ),
			'pin'      => (string) get_option( 'woocommerce_store_postcode' ),
		];
	}

	private static function items( Consignment $c ): array {
		return array_map(
			static fn( array $item ): array => array_filter(
				[
					'name'          => $item['name'],
					'sku'           => $item['sku'] !== '' ? $item['sku'] : 'item-' . $item['item_id'],
					'units'         => $item['qty'],
					'selling_price' => $item['price'],
					'hsn'           => $item['hsn'],
				],
				static fn( mixed $value ): bool => $value !== ''
			),
			$c->items
		);
	}

	private function addStorePickup(): array {
		$store = self::storeAddress( true );
		$data  = $this->client->post(
			'settings/company/addpickup',
			[
				'pickup_location' => self::DEFAULT_PICKUP,
				'name'            => $store['name'],
				'email'           => $store['email'],
				'phone'           => $store['phone'],
				'address'         => $store['address'],
				'address_2'       => $store['address2'],
				'city'            => $store['city'],
				'state'           => $store['state'],
				'country'         => $store['country'],
				'pin_code'        => $store['pin'],
			]
		);
		if ( empty( $data['success'] ) || ! is_array( $data['address'] ?? null ) ) {
			throw new CarrierException( esc_html( (string) ( $data['message'] ?? 'Shiprocket has no pickup address; add one in its panel under Settings, Pickup addresses' ) ), false );
		}
		return $data['address'];
	}

	private static function timestamp( string $date ): int {
		try {
			return ( new DateTimeImmutable( $date, new DateTimeZone( 'Asia/Kolkata' ) ) )->getTimestamp();
		} catch ( \Exception ) {
			return time();
		}
	}
}
