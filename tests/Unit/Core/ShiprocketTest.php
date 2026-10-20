<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Storewright\Core\Fulfilment\Carriers;
use Storewright\Core\Fulfilment\Consignment;
use Storewright\Core\Fulfilment\Shiprocket\Shiprocket;
use Storewright\Core\Fulfilment\ShiprocketWebhook;
use Storewright\Core\Fulfilment\TrackingEvent;

final class ShiprocketTest extends TestCase {

	public function test_quotes_keep_the_courier_id_rate_and_arrival_and_mark_the_recommended_one(): void {
		$quotes = Shiprocket::parseQuotes(
			[
				'recommended_courier_company_id' => 1,
				'available_courier_companies'    => [
					[
						'courier_company_id' => 24,
						'courier_name'       => 'Xpressbees 2kg surface',
						'rate'               => 155,
						'etd'                => 'Oct 22, 2026',
					],
					[
						'courier_company_id' => 1,
						'courier_name'       => 'Blue Dart',
						'rate'               => '58',
						'etd'                => 'Oct 22, 2026',
					],
					[ 'courier_name' => 'nameless, no id' ],
				],
			]
		);

		self::assertCount( 2, $quotes );
		self::assertSame( '24', $quotes[0]->service );
		self::assertSame( 155.0, $quotes[0]->rate );
		self::assertFalse( $quotes[0]->recommended );
		self::assertSame( 'Blue Dart', $quotes[1]->name );
		self::assertSame( 'Oct 22, 2026', $quotes[1]->arrives );
		self::assertTrue( $quotes[1]->recommended );
	}

	public function test_tracking_is_empty_until_the_courier_scans(): void {
		self::assertSame(
			[],
			Shiprocket::parseTracking(
				[
					'track_status' => 0,
					'error'        => 'Aahh! There is no activities found in our DB.',
				]
			)
		);
	}

	public function test_tracking_scans_become_events_in_time_order_with_plain_statuses(): void {
		$events = Shiprocket::parseTracking(
			[
				'track_status'              => 1,
				'shipment_track_activities' => [
					[
						'date'            => '2026-10-22 09:10:00',
						'activity'        => 'Shipment out for delivery',
						'location'        => 'Bengaluru',
						'sr-status-label' => 'OUT FOR DELIVERY',
					],
					[
						'date'            => '2026-10-21 18:00:00',
						'activity'        => 'Picked up',
						'location'        => 'Bengaluru Hub',
						'sr-status-label' => 'PICKED UP',
					],
					[ 'activity' => 'no date, ignored' ],
				],
			]
		);

		self::assertCount( 2, $events );
		self::assertSame( TrackingEvent::PICKED_UP, $events[0]->status );
		self::assertSame( 'Bengaluru Hub', $events[0]->location );
		self::assertSame( TrackingEvent::OUT_FOR_DELIVERY, $events[1]->status );
		self::assertSame( 'OUT FOR DELIVERY', $events[1]->raw );
		self::assertLessThan( $events[1]->at, $events[0]->at );
	}

	public function test_courier_words_map_to_one_status_each(): void {
		self::assertSame( TrackingEvent::DELIVERED, TrackingEvent::fromWords( 'Delivered' ) );
		self::assertSame( TrackingEvent::RETURNED, TrackingEvent::fromWords( 'RTO Delivered' ) );
		self::assertSame( TrackingEvent::RETURNING, TrackingEvent::fromWords( 'RTO Initiated' ) );
		self::assertSame( TrackingEvent::OUT_FOR_DELIVERY, TrackingEvent::fromWords( 'Out For Delivery' ) );
		self::assertSame( TrackingEvent::UNDELIVERED, TrackingEvent::fromWords( 'Undelivered' ) );
		self::assertSame( TrackingEvent::IN_TRANSIT, TrackingEvent::fromWords( 'In Transit' ) );
		self::assertSame( TrackingEvent::PICKED_UP, TrackingEvent::fromWords( 'Picked Up' ) );
		self::assertSame( TrackingEvent::CANCELLED, TrackingEvent::fromWords( 'Canceled' ) );
		self::assertSame( TrackingEvent::LOST, TrackingEvent::fromWords( 'Damaged' ) );
		self::assertSame( TrackingEvent::BOOKED, TrackingEvent::fromWords( 'AWB Assigned' ) );
	}

	public function test_the_order_payload_carries_the_parcel_items_and_payment_kind(): void {
		$consignment = new Consignment(
			'1042-7',
			'2026-10-20 23:00',
			'Store',
			[
				'first'    => 'Asha',
				'last'     => 'Menon',
				'address'  => '12 Market Street',
				'address2' => '',
				'city'     => 'Bengaluru',
				'state'    => 'Karnataka',
				'pin'      => '560064',
				'country'  => 'India',
				'email'    => 'asha@example.com',
				'phone'    => '9123456780',
			],
			[
				[
					'item_id' => 12,
					'name'    => 'Stoneware Mug',
					'sku'     => '',
					'qty'     => 2,
					'price'   => 449.0,
					'hsn'     => '691200',
				],
			],
			true,
			898.0,
			79.0,
			0.8,
			12.0,
			12.0,
			12.0
		);

		$payload = Shiprocket::payload( $consignment, 'Store' );

		self::assertSame( '1042-7', $payload['order_id'] );
		self::assertSame( 'Store', $payload['pickup_location'] );
		self::assertSame( 'COD', $payload['payment_method'] );
		self::assertSame( 898.0, $payload['sub_total'] );
		self::assertSame( 79.0, $payload['shipping_charges'] );
		self::assertSame( 'item-12', $payload['order_items'][0]['sku'] );
		self::assertSame( 2, $payload['order_items'][0]['units'] );
		self::assertSame( '691200', $payload['order_items'][0]['hsn'] );
		self::assertSame( 0.8, $payload['weight'] );
		self::assertSame( 977.0, $consignment->total() );
	}

	public function test_a_return_is_picked_up_from_the_customer_and_sent_to_the_store(): void {
		$consignment = new Consignment(
			'1042-R3',
			'2026-10-21 09:00',
			'Store',
			[
				'first'    => 'Asha',
				'last'     => 'Menon',
				'address'  => '12 Market Street',
				'address2' => '',
				'city'     => 'Bengaluru',
				'state'    => 'Karnataka',
				'pin'      => '560064',
				'country'  => 'India',
				'email'    => 'asha@example.com',
				'phone'    => '9123456780',
			],
			[
				[
					'item_id' => 12,
					'name'    => 'Stoneware Mug',
					'sku'     => 'SW-MUG',
					'qty'     => 1,
					'price'   => 449.0,
					'hsn'     => '',
				],
			],
			false,
			449.0,
			0.0,
			0.4,
			12.0,
			9.0,
			10.0,
			true
		);

		$payload = Shiprocket::returnPayload(
			$consignment,
			[
				'name'     => 'Demo Store',
				'email'    => 'shop@example.com',
				'phone'    => '9000000000',
				'address'  => '14 Church Street',
				'address2' => '',
				'city'     => 'Bengaluru',
				'state'    => 'Karnataka',
				'country'  => 'India',
				'pin'      => '560001',
			]
		);

		self::assertSame( '560064', $payload['pickup_pincode'] );
		self::assertSame( 'Asha', $payload['pickup_customer_name'] );
		self::assertSame( '560001', $payload['shipping_pincode'] );
		self::assertSame( 'Demo Store', $payload['shipping_customer_name'] );
		self::assertSame( 'Prepaid', $payload['payment_method'] );
		self::assertSame( 'SW-MUG', $payload['order_items'][0]['sku'] );
		self::assertArrayNotHasKey( 'hsn', $payload['order_items'][0] );
		self::assertArrayNotHasKey( 'shipping_charges', $payload );
	}

	public function test_phones_keep_the_last_ten_digits(): void {
		self::assertSame( '9123456780', Consignment::phone( '+91 91234 56780' ) );
		self::assertSame( '9123456780', Consignment::phone( '09123456780' ) );
		self::assertSame( '', Consignment::phone( '' ) );
	}

	public function test_a_courier_name_maps_to_a_known_carrier_or_to_shiprocket(): void {
		self::assertSame( 'xpressbees', Carriers::keyFor( 'Xpressbees 2kg surface' ) );
		self::assertSame( 'bluedart', Carriers::keyFor( 'Blue Dart' ) );
		self::assertSame( 'delhivery', Carriers::keyFor( 'Delhivery Surface 5 Kgs' ) );
		self::assertSame( 'india-post', Carriers::keyFor( 'India Post Speed Post' ) );
		self::assertSame( 'shiprocket', Carriers::keyFor( 'Amazon Shipping' ) );
	}

	public function test_the_webhook_token_must_match_exactly_and_be_set(): void {
		self::assertTrue( ShiprocketWebhook::tokenMatches( 'abc', 'abc' ) );
		self::assertFalse( ShiprocketWebhook::tokenMatches( 'abd', 'abc' ) );
		self::assertFalse( ShiprocketWebhook::tokenMatches( '', '' ) );
		self::assertFalse( ShiprocketWebhook::tokenMatches( 'abc', '' ) );
	}
}
