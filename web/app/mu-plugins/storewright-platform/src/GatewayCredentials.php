<?php

declare(strict_types=1);

namespace Storewright\Platform;

final class GatewayCredentials {

	private const STRIPE = [
		'test_publishable_key' => 'STRIPE_TEST_PUBLISHABLE_KEY',
		'test_secret_key'      => 'STRIPE_TEST_SECRET_KEY',
		'test_webhook_secret'  => 'STRIPE_TEST_WEBHOOK_SECRET',
		'publishable_key'      => 'STRIPE_LIVE_PUBLISHABLE_KEY',
		'secret_key'           => 'STRIPE_LIVE_SECRET_KEY',
		'webhook_secret'       => 'STRIPE_LIVE_WEBHOOK_SECRET',
	];

	private const RAZORPAY = [
		'key_id'         => 'RAZORPAY_KEY_ID',
		'key_secret'     => 'RAZORPAY_KEY_SECRET',
		'webhook_secret' => 'RAZORPAY_WEBHOOK_SECRET',
	];

	public static function register(): void {
		foreach ( [ 'option', 'default_option' ] as $hook ) {
			add_filter( $hook . '_woocommerce_stripe_settings', static fn( mixed $settings ): array => self::testModeOutsideProduction( self::merge( $settings, self::STRIPE ), wp_get_environment_type() ) );
			add_filter( $hook . '_woocommerce_razorpay_settings', static fn( mixed $settings ): mixed => self::merge( $settings, self::razorpayMap() ) );
		}
	}

	public static function razorpayMap(): array {
		$map = self::RAZORPAY;
		if ( getenv( 'RAZORPAY_KEY_ID' ) === false && getenv( 'RAZORPAY_TEST_KEY_ID' ) !== false ) {
			$map = [
				'key_id'         => 'RAZORPAY_TEST_KEY_ID',
				'key_secret'     => 'RAZORPAY_TEST_KEY_SECRET',
				'webhook_secret' => 'RAZORPAY_TEST_WEBHOOK_SECRET',
			];
		}
		return $map;
	}

	public static function testModeOutsideProduction( array $settings, string $environment ): array {
		if ( $environment !== 'production' ) {
			$settings['testmode'] = 'yes';
		}
		return $settings;
	}

	public static function merge( mixed $settings, array $map ): array {
		$settings  = is_array( $settings ) ? $settings : [];
		$settings += [ 'description' => '' ];
		foreach ( $map as $setting => $variable ) {
			$value = getenv( $variable );
			if ( $value !== false && $value !== '' ) {
				$settings[ $setting ] = $value;
			}
		}
		return $settings;
	}
}
