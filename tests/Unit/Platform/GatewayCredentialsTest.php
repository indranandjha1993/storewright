<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Platform;

use Storewright\Platform\GatewayCredentials;
use Storewright\Tests\TestCase;

final class GatewayCredentialsTest extends TestCase {

	protected function tearDown(): void {
		foreach ( [ 'STRIPE_TEST_PUBLISHABLE_KEY', 'STRIPE_TEST_SECRET_KEY', 'STRIPE_LIVE_PUBLISHABLE_KEY', 'STRIPE_LIVE_SECRET_KEY', 'RAZORPAY_KEY_ID', 'RAZORPAY_TEST_KEY_ID', 'RAZORPAY_TEST_KEY_SECRET' ] as $name ) {
			putenv( $name );
		}
		parent::tearDown();
	}

	public function test_environment_values_override_saved_settings(): void {
		putenv( 'STRIPE_TEST_SECRET_KEY=sk_test_env' );

		$merged = GatewayCredentials::merge(
			[
				'test_secret_key' => 'sk_test_saved',
				'enabled'         => 'yes',
			],
			[ 'test_secret_key' => 'STRIPE_TEST_SECRET_KEY' ]
		);

		self::assertSame( 'sk_test_env', $merged['test_secret_key'] );
		self::assertSame( 'yes', $merged['enabled'] );
	}

	public function test_empty_environment_values_leave_settings_alone(): void {
		putenv( 'STRIPE_TEST_SECRET_KEY=' );

		$merged = GatewayCredentials::merge( [ 'test_secret_key' => 'sk_test_saved' ], [ 'test_secret_key' => 'STRIPE_TEST_SECRET_KEY' ] );

		self::assertSame( 'sk_test_saved', $merged['test_secret_key'] );
	}

	public function test_a_missing_option_becomes_an_array_with_a_description(): void {
		$merged = GatewayCredentials::merge( false, [] );

		self::assertSame( [ 'description' => '' ], $merged );
	}

	public function test_razorpay_falls_back_to_test_keys_when_live_keys_are_absent(): void {
		putenv( 'RAZORPAY_TEST_KEY_ID=rzp_test_1' );
		putenv( 'RAZORPAY_TEST_KEY_SECRET=s' );

		self::assertSame( 'RAZORPAY_TEST_KEY_ID', GatewayCredentials::razorpayMap()['key_id'] );

		putenv( 'RAZORPAY_KEY_ID=rzp_live_1' );

		self::assertSame( 'RAZORPAY_KEY_ID', GatewayCredentials::razorpayMap()['key_id'] );
	}

	public function test_stripe_is_forced_into_test_mode_outside_production(): void {
		self::assertSame( 'yes', GatewayCredentials::testModeOutsideProduction( [ 'testmode' => 'no' ], 'staging' )['testmode'] );
		self::assertSame( 'yes', GatewayCredentials::testModeOutsideProduction( [], 'development' )['testmode'] );
	}

	public function test_production_keeps_the_saved_mode(): void {
		self::assertSame( 'no', GatewayCredentials::testModeOutsideProduction( [ 'testmode' => 'no' ], 'production' )['testmode'] );
	}

	public function test_stripe_switches_on_with_optimized_checkout_once_its_test_keys_are_set(): void {
		putenv( 'STRIPE_TEST_PUBLISHABLE_KEY=pk_test_1' );
		putenv( 'STRIPE_TEST_SECRET_KEY=sk_test_1' );

		$settings = GatewayCredentials::stripe( false, 'development' );

		self::assertSame( 'yes', $settings['enabled'] );
		self::assertSame( 'yes', $settings['upe_checkout_experience_enabled'] );
		self::assertSame( 'yes', $settings['optimized_checkout_element'] );
	}

	public function test_stripe_stays_off_without_both_keys(): void {
		putenv( 'STRIPE_TEST_PUBLISHABLE_KEY=pk_test_1' );

		self::assertArrayNotHasKey( 'enabled', GatewayCredentials::stripe( false, 'development' ) );
	}

	public function test_an_operator_who_switched_stripe_off_is_respected(): void {
		putenv( 'STRIPE_TEST_PUBLISHABLE_KEY=pk_test_1' );
		putenv( 'STRIPE_TEST_SECRET_KEY=sk_test_1' );

		self::assertSame( 'no', GatewayCredentials::stripe( [ 'enabled' => 'no' ], 'development' )['enabled'] );
	}

	public function test_production_needs_live_keys_before_stripe_switches_on(): void {
		putenv( 'STRIPE_TEST_PUBLISHABLE_KEY=pk_test_1' );
		putenv( 'STRIPE_TEST_SECRET_KEY=sk_test_1' );

		self::assertArrayNotHasKey( 'enabled', GatewayCredentials::stripe( [ 'testmode' => 'no' ], 'production' ) );

		putenv( 'STRIPE_LIVE_PUBLISHABLE_KEY=pk_live_1' );
		putenv( 'STRIPE_LIVE_SECRET_KEY=sk_live_1' );

		self::assertSame( 'yes', GatewayCredentials::stripe( [ 'testmode' => 'no' ], 'production' )['enabled'] );
	}

	public function test_razorpay_switches_on_once_its_keys_are_set(): void {
		putenv( 'RAZORPAY_TEST_KEY_ID=rzp_test_1' );
		putenv( 'RAZORPAY_TEST_KEY_SECRET=s' );

		self::assertSame( 'yes', GatewayCredentials::razorpay( false )['enabled'] );
	}
}
