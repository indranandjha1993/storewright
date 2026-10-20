<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform;

use PHPUnit\Framework\TestCase;
use Storewright\Platform\CarrierCredentials;

final class CarrierCredentialsTest extends TestCase {

	protected function tearDown(): void {
		foreach ( [ 'SHIPROCKET_EMAIL', 'SHIPROCKET_PASSWORD', 'SHIPROCKET_API_URL', 'SHIPROCKET_WEBHOOK_TOKEN' ] as $name ) {
			putenv( $name );
		}
		parent::tearDown();
	}

	public function test_the_host_decides_sandbox_or_live(): void {
		self::assertSame( 'sandbox', CarrierCredentials::modeOfHost( 'https://api-sandbox.shiprocket.in' ) );
		self::assertSame( 'live', CarrierCredentials::modeOfHost( 'https://apiv2.shiprocket.in' ) );
	}

	public function test_off_without_an_api_user(): void {
		putenv( 'SHIPROCKET_EMAIL=api@example.com' );

		self::assertNull( CarrierCredentials::shiprocketMode() );
		self::assertSame( [ 'shiprocket' => 'off' ], CarrierCredentials::modes( 'production' ) );
		self::assertNull( CarrierCredentials::shiprocket( 'production' ) );
	}

	public function test_live_is_refused_outside_production_and_the_token_goes_with_it(): void {
		putenv( 'SHIPROCKET_EMAIL=api@example.com' );
		putenv( 'SHIPROCKET_PASSWORD=secret' );
		putenv( 'SHIPROCKET_WEBHOOK_TOKEN=hook' );

		self::assertSame( [ 'shiprocket' => 'refused' ], CarrierCredentials::modes( 'local' ) );
		self::assertNull( CarrierCredentials::shiprocket( 'local' ) );
		self::assertSame( '', CarrierCredentials::shiprocketWebhookToken( 'local' ) );
		self::assertSame( [ 'shiprocket' => 'live' ], CarrierCredentials::modes( 'production' ) );
		self::assertSame( 'https://apiv2.shiprocket.in', CarrierCredentials::shiprocket( 'production' )['host'] );
		self::assertSame( 'hook', CarrierCredentials::shiprocketWebhookToken( 'production' ) );
	}

	public function test_the_sandbox_is_allowed_anywhere_and_keeps_its_host(): void {
		putenv( 'SHIPROCKET_EMAIL=api@example.com' );
		putenv( 'SHIPROCKET_PASSWORD=secret' );
		putenv( 'SHIPROCKET_API_URL=https://api-sandbox.shiprocket.in/' );

		$keys = CarrierCredentials::shiprocket( 'local' );

		self::assertSame( 'sandbox', $keys['mode'] );
		self::assertSame( 'https://api-sandbox.shiprocket.in', $keys['host'] );
		self::assertSame( 'api@example.com', $keys['email'] );
	}
}
