<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Storewright\Core\Payments\EventStore;
use Storewright\Core\Payments\WebhookGuard;

final class WebhookGuardTest extends TestCase {

	private const EVENT = '{"id":"evt_1","type":"charge.succeeded"}';

	private EventStore $store;
	private WebhookGuard $guard;

	protected function setUp(): void {
		$this->store = new class() implements EventStore {
			public array $rows = [];

			public function status( string $gateway, string $eventId ): ?string {
				return $this->rows[ "$gateway:$eventId" ]['status'] ?? null;
			}

			public function receive( string $gateway, string $eventId, string $eventType, string $payloadHash ): void {
				$key                = "$gateway:$eventId";
				$this->rows[ $key ] = isset( $this->rows[ $key ] )
					? [
						...$this->rows[ $key ],
						'deliveries' => $this->rows[ $key ]['deliveries'] + 1,
					]
					: [
						'type'       => $eventType,
						'status'     => WebhookGuard::RECEIVED,
						'deliveries' => 1,
						'hash'       => $payloadHash,
					];
			}

			public function mark( string $gateway, string $eventId, string $status ): void {
				$this->rows[ "$gateway:$eventId" ]['status'] = $status;
			}
		};
		$this->guard = new WebhookGuard( $this->store );
	}

	public function test_an_unverified_event_is_passed_through_untouched(): void {
		self::assertSame( WebhookGuard::PASS, $this->guard->admit( 'stripe', 'evt_1', 'charge.succeeded', self::EVENT, false ) );
		self::assertSame( [], $this->store->rows );
	}

	public function test_an_event_without_an_id_is_passed_through(): void {
		self::assertSame( WebhookGuard::PASS, $this->guard->admit( 'stripe', '', 'x', '{"type":"x"}', true ) );
	}

	public function test_a_new_event_is_recorded_and_settled_as_processed(): void {
		self::assertSame( WebhookGuard::RECORDED, $this->guard->admit( 'stripe', 'evt_1', 'charge.succeeded', self::EVENT, true ) );

		$this->guard->settle( 'stripe', 'evt_1', 200 );

		self::assertSame( 'processed', $this->store->rows['stripe:evt_1']['status'] );
		self::assertSame( 'charge.succeeded', $this->store->rows['stripe:evt_1']['type'] );
		self::assertSame( hash( 'sha256', self::EVENT ), $this->store->rows['stripe:evt_1']['hash'] );
	}

	public function test_a_second_delivery_of_a_processed_event_is_a_duplicate_and_counted(): void {
		$this->guard->admit( 'stripe', 'evt_1', 'charge.succeeded', self::EVENT, true );
		$this->guard->settle( 'stripe', 'evt_1', 200 );

		self::assertSame( WebhookGuard::DUPLICATE, $this->guard->admit( 'stripe', 'evt_1', 'charge.succeeded', self::EVENT, true ) );
		self::assertCount( 1, $this->store->rows );
		self::assertSame( 2, $this->store->rows['stripe:evt_1']['deliveries'] );
	}

	public function test_a_failed_event_is_processed_again_when_redelivered(): void {
		$this->guard->admit( 'stripe', 'evt_1', 'charge.succeeded', self::EVENT, true );
		$this->guard->settle( 'stripe', 'evt_1', 500 );

		self::assertSame( 'failed', $this->store->rows['stripe:evt_1']['status'] );
		self::assertSame( WebhookGuard::RECORDED, $this->guard->admit( 'stripe', 'evt_1', 'charge.succeeded', self::EVENT, true ) );
	}

	public function test_the_same_event_id_on_another_gateway_is_a_separate_event(): void {
		$this->guard->admit( 'stripe', 'evt_1', 'charge.succeeded', self::EVENT, true );
		$this->guard->settle( 'stripe', 'evt_1', 200 );

		self::assertSame( WebhookGuard::RECORDED, $this->guard->admit( 'razorpay', 'evt_1', 'payment.authorized', self::EVENT, true ) );
	}

	public function test_razorpay_signatures_are_an_hmac_of_the_raw_body(): void {
		$body = '{"event":"payment.authorized"}';

		self::assertTrue( \Storewright\Core\Payments\RazorpayWebhooks::authentic( $body, hash_hmac( 'sha256', $body, 'hook' ), 'hook' ) );
		self::assertFalse( \Storewright\Core\Payments\RazorpayWebhooks::authentic( $body . ' ', hash_hmac( 'sha256', $body, 'hook' ), 'hook' ) );
		self::assertFalse( \Storewright\Core\Payments\RazorpayWebhooks::authentic( $body, hash_hmac( 'sha256', $body, 'hook' ), '' ) );
	}
}
