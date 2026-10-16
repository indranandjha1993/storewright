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
			add_filter( $hook . '_woocommerce_stripe_settings', static fn( mixed $settings ): array => self::stripe( $settings, wp_get_environment_type() ) );
			add_filter( $hook . '_woocommerce_razorpay_settings', static fn( mixed $settings ): array => self::razorpay( $settings ) );
		}
	}

	public static function stripe( mixed $settings, string $environment ): array {
		$settings = self::testModeOutsideProduction( self::merge( $settings, self::STRIPE ), $environment );
		$required = $settings['testmode'] === 'yes' ? [ 'test_publishable_key', 'test_secret_key' ] : [ 'publishable_key', 'secret_key' ];
		return self::enableWhenConfigured(
			$settings,
			$required,
			[
				'enabled'                         => 'yes',
				'upe_checkout_experience_enabled' => 'yes',
				'optimized_checkout_element'      => 'yes',
			]
		);
	}

	public static function razorpay( mixed $settings ): array {
		$settings = self::merge( $settings, self::razorpayMap() ) + [
			'key_id'     => '',
			'key_secret' => '',
		];
		return self::enableWhenConfigured( $settings, [ 'key_id', 'key_secret' ], [ 'enabled' => 'yes' ] );
	}

	public static function enableWhenConfigured( array $settings, array $required, array $defaults ): array {
		foreach ( $required as $key ) {
			if ( ( $settings[ $key ] ?? '' ) === '' ) {
				return $settings;
			}
		}
		return $settings + $defaults;
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
		$settings += [ 'testmode' => 'no' ];
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
