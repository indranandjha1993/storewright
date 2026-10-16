<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform;

use Storewright\Platform\GatewayCredentials;
use Storewright\Tests\TestCase;

final class GatewayCredentialsTest extends TestCase {

	private const VARIABLES = [
		'STRIPE_PUBLISHABLE_KEY',
		'STRIPE_SECRET_KEY',
		'STRIPE_WEBHOOK_SECRET',
		'RAZORPAY_KEY_ID',
		'RAZORPAY_KEY_SECRET',
		'RAZORPAY_WEBHOOK_SECRET',
	];

	protected function tearDown(): void {
		foreach ( self::VARIABLES as $name ) {
			putenv( $name );
		}
		parent::tearDown();
	}

	private function stripeKeys( string $mode ): void {
		putenv( "STRIPE_PUBLISHABLE_KEY=pk_{$mode}_abc" );
		putenv( "STRIPE_SECRET_KEY=sk_{$mode}_abc" );
		putenv( 'STRIPE_WEBHOOK_SECRET=whsec_abc' );
	}

	public function test_sandbox_stripe_keys_are_handed_to_the_gateway(): void {
		$this->stripeKeys( 'test' );

		self::assertSame(
			[
				'mode'        => 'sandbox',
				'publishable' => 'pk_test_abc',
				'secret'      => 'sk_test_abc',
				'webhook'     => 'whsec_abc',
			],
			GatewayCredentials::stripeKeys( 'development' )
		);
	}

	public function test_live_stripe_keys_work_only_in_production(): void {
		$this->stripeKeys( 'live' );

		self::assertSame( 'live', GatewayCredentials::stripeKeys( 'production' )['mode'] );
		self::assertNull( GatewayCredentials::stripeKeys( 'staging' ) );
		self::assertSame( 'refused', GatewayCredentials::modes( 'staging' )['stripe'] );
	}

	public function test_sandbox_keys_are_allowed_in_production(): void {
		$this->stripeKeys( 'test' );

		self::assertSame( 'sandbox', GatewayCredentials::modes( 'production' )['stripe'] );
	}

	public function test_a_restricted_sandbox_key_is_accepted(): void {
		putenv( 'STRIPE_PUBLISHABLE_KEY=pk_test_abc' );
		putenv( 'STRIPE_SECRET_KEY=rk_test_abc' );

		self::assertSame( 'rk_test_abc', GatewayCredentials::stripeKeys( 'development' )['secret'] );
	}

	public function test_stripe_keys_from_different_modes_are_ignored(): void {
		putenv( 'STRIPE_PUBLISHABLE_KEY=pk_live_abc' );
		putenv( 'STRIPE_SECRET_KEY=sk_test_abc' );

		self::assertNull( GatewayCredentials::stripeKeys( 'production' ) );
		self::assertSame( 'off', GatewayCredentials::modes( 'production' )['stripe'] );
	}

	public function test_sandbox_razorpay_keys_switch_the_gateway_on(): void {
		putenv( 'RAZORPAY_KEY_ID=rzp_test_abc' );
		putenv( 'RAZORPAY_KEY_SECRET=secret' );
		putenv( 'RAZORPAY_WEBHOOK_SECRET=hook' );

		$settings = GatewayCredentials::razorpay( false, 'development' );

		self::assertSame( 'rzp_test_abc', $settings['key_id'] );
		self::assertSame( 'secret', $settings['key_secret'] );
		self::assertSame( 'hook', $settings['webhook_secret'] );
		self::assertSame( 'yes', $settings['enabled'] );
	}

	public function test_live_razorpay_keys_are_refused_outside_production(): void {
		putenv( 'RAZORPAY_KEY_ID=rzp_live_abc' );
		putenv( 'RAZORPAY_KEY_SECRET=secret' );

		self::assertSame( '', GatewayCredentials::razorpay( false, 'development' )['key_id'] );
		self::assertSame( 'refused', GatewayCredentials::modes( 'development' )['razorpay'] );
		self::assertSame( 'live', GatewayCredentials::modes( 'production' )['razorpay'] );
	}

	public function test_razorpay_without_keys_is_never_offered(): void {
		$settings = GatewayCredentials::razorpay( [ 'enabled' => 'yes' ], 'development' );

		self::assertSame( '', $settings['key_id'] );
		self::assertSame( 'no', $settings['enabled'] );
		self::assertSame(
			[
				'stripe'   => 'off',
				'razorpay' => 'off',
			],
			GatewayCredentials::modes( 'development' )
		);
	}

	public function test_razorpay_keys_from_the_environment_are_never_written_to_the_database(): void {
		putenv( 'RAZORPAY_KEY_ID=rzp_test_abc' );
		putenv( 'RAZORPAY_KEY_SECRET=secret' );
		$settings = GatewayCredentials::razorpay( false, 'development' );

		$saved = GatewayCredentials::keepOutOfDatabase( $settings, GatewayCredentials::razorpayMode(), GatewayCredentials::razorpayFields() );

		self::assertArrayNotHasKey( 'key_id', $saved );
		self::assertArrayNotHasKey( 'key_secret', $saved );
		self::assertSame( 'yes', $saved['enabled'] );
	}

	public function test_razorpay_keys_an_operator_typed_in_are_saved_when_the_environment_has_none(): void {
		$typed = [
			'key_id'  => 'rzp_test_typed',
			'enabled' => 'yes',
		];

		self::assertSame( $typed, GatewayCredentials::keepOutOfDatabase( $typed, GatewayCredentials::razorpayMode(), GatewayCredentials::razorpayFields() ) );
	}
}
