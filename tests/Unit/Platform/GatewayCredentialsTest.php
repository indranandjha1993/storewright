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

	public function test_sandbox_stripe_keys_fill_the_test_fields_and_switch_the_gateway_on(): void {
		$this->stripeKeys( 'test' );

		$settings = GatewayCredentials::stripe( false, 'development' );

		self::assertSame( 'yes', $settings['testmode'] );
		self::assertSame( 'pk_test_abc', $settings['test_publishable_key'] );
		self::assertSame( 'sk_test_abc', $settings['test_secret_key'] );
		self::assertSame( 'whsec_abc', $settings['test_webhook_secret'] );
		self::assertArrayNotHasKey( 'secret_key', $settings );
		self::assertSame( 'yes', $settings['enabled'] );
		self::assertSame( 'yes', $settings['optimized_checkout_element'] );
	}

	public function test_live_stripe_keys_fill_the_live_fields_in_production(): void {
		$this->stripeKeys( 'live' );

		$settings = GatewayCredentials::stripe( false, 'production' );

		self::assertSame( 'no', $settings['testmode'] );
		self::assertSame( 'sk_live_abc', $settings['secret_key'] );
		self::assertSame( 'whsec_abc', $settings['webhook_secret'] );
		self::assertSame( 'yes', $settings['enabled'] );
	}

	public function test_live_stripe_keys_are_refused_outside_production(): void {
		$this->stripeKeys( 'live' );

		$settings = GatewayCredentials::stripe( false, 'staging' );

		self::assertSame( 'yes', $settings['testmode'] );
		self::assertArrayNotHasKey( 'secret_key', $settings );
		self::assertArrayNotHasKey( 'enabled', $settings );
		self::assertSame( 'refused', GatewayCredentials::modes( 'staging' )['stripe'] );
	}

	public function test_sandbox_keys_are_allowed_in_production(): void {
		$this->stripeKeys( 'test' );

		self::assertSame( 'yes', GatewayCredentials::stripe( false, 'production' )['testmode'] );
		self::assertSame( 'sandbox', GatewayCredentials::modes( 'production' )['stripe'] );
	}

	public function test_a_restricted_sandbox_key_from_the_stripe_cli_is_accepted(): void {
		putenv( 'STRIPE_PUBLISHABLE_KEY=pk_test_abc' );
		putenv( 'STRIPE_SECRET_KEY=rk_test_abc' );

		self::assertSame( 'rk_test_abc', GatewayCredentials::stripe( false, 'development' )['test_secret_key'] );
	}

	public function test_keys_from_different_modes_are_ignored(): void {
		putenv( 'STRIPE_PUBLISHABLE_KEY=pk_live_abc' );
		putenv( 'STRIPE_SECRET_KEY=sk_test_abc' );

		self::assertArrayNotHasKey( 'enabled', GatewayCredentials::stripe( false, 'production' ) );
		self::assertSame( 'off', GatewayCredentials::modes( 'production' )['stripe'] );
	}

	public function test_an_operator_who_switched_stripe_off_is_respected(): void {
		$this->stripeKeys( 'test' );

		self::assertSame( 'no', GatewayCredentials::stripe( [ 'enabled' => 'no' ], 'development' )['enabled'] );
	}

	public function test_saved_settings_stay_in_test_mode_outside_production(): void {
		self::assertSame( 'yes', GatewayCredentials::stripe( [ 'testmode' => 'no' ], 'staging' )['testmode'] );
		self::assertSame( 'no', GatewayCredentials::stripe( [ 'testmode' => 'no' ], 'production' )['testmode'] );
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

	public function test_without_keys_razorpay_still_gets_empty_fields_and_stays_off(): void {
		$settings = GatewayCredentials::razorpay( false, 'development' );

		self::assertSame( '', $settings['key_id'] );
		self::assertSame( '', $settings['description'] );
		self::assertArrayNotHasKey( 'enabled', $settings );
		self::assertSame(
			[
				'stripe'   => 'off',
				'razorpay' => 'off',
			],
			GatewayCredentials::modes( 'development' )
		);
	}
}
