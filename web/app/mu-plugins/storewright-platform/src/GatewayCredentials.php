<?php

declare(strict_types=1);

namespace Storewright\Platform;

final class GatewayCredentials {

	public const SANDBOX = 'sandbox';
	public const LIVE    = 'live';
	public const REFUSED = 'refused';
	public const OFF     = 'off';

	public static function register(): void {
		foreach ( [ 'option', 'default_option' ] as $hook ) {
			add_filter( $hook . '_woocommerce_stripe_settings', static fn( mixed $saved ): array => self::stripe( $saved, wp_get_environment_type() ) );
			add_filter( $hook . '_woocommerce_razorpay_settings', static fn( mixed $saved ): array => self::razorpay( $saved, wp_get_environment_type() ) );
		}
	}

	public static function modes( string $environment ): array {
		return [
			'stripe'   => self::status( self::stripeMode(), $environment ),
			'razorpay' => self::status( self::razorpayMode(), $environment ),
		];
	}

	public static function stripe( mixed $saved, string $environment ): array {
		$settings = self::settings( $saved );
		if ( $environment !== 'production' ) {
			$settings['testmode'] = 'yes';
		}
		$mode = self::stripeMode();
		if ( $mode === null || ! self::allowed( $mode, $environment ) ) {
			return $settings + [ 'testmode' => 'no' ];
		}
		$prefix                                  = $mode === self::SANDBOX ? 'test_' : '';
		$settings['testmode']                    = $mode === self::SANDBOX ? 'yes' : 'no';
		$settings[ $prefix . 'publishable_key' ] = self::env( 'STRIPE_PUBLISHABLE_KEY' );
		$settings[ $prefix . 'secret_key' ]      = self::env( 'STRIPE_SECRET_KEY' );
		if ( self::env( 'STRIPE_WEBHOOK_SECRET' ) !== '' ) {
			$settings[ $prefix . 'webhook_secret' ] = self::env( 'STRIPE_WEBHOOK_SECRET' );
		}
		return $settings + [
			'enabled'                         => 'yes',
			'upe_checkout_experience_enabled' => 'yes',
			'optimized_checkout_element'      => 'yes',
		];
	}

	public static function razorpay( mixed $saved, string $environment ): array {
		$settings = self::settings( $saved ) + [
			'key_id'     => '',
			'key_secret' => '',
		];
		$mode     = self::razorpayMode();
		if ( $mode === null || ! self::allowed( $mode, $environment ) ) {
			return $settings;
		}
		$settings['key_id']     = self::env( 'RAZORPAY_KEY_ID' );
		$settings['key_secret'] = self::env( 'RAZORPAY_KEY_SECRET' );
		if ( self::env( 'RAZORPAY_WEBHOOK_SECRET' ) !== '' ) {
			$settings['webhook_secret'] = self::env( 'RAZORPAY_WEBHOOK_SECRET' );
		}
		return $settings + [ 'enabled' => 'yes' ];
	}

	private static function stripeMode(): ?string {
		$secret      = self::mode( self::env( 'STRIPE_SECRET_KEY' ), [ 'sk_test_', 'rk_test_' ], [ 'sk_live_', 'rk_live_' ] );
		$publishable = self::mode( self::env( 'STRIPE_PUBLISHABLE_KEY' ), [ 'pk_test_' ], [ 'pk_live_' ] );
		return $secret !== null && $secret === $publishable ? $secret : null;
	}

	private static function razorpayMode(): ?string {
		if ( self::env( 'RAZORPAY_KEY_SECRET' ) === '' ) {
			return null;
		}
		return self::mode( self::env( 'RAZORPAY_KEY_ID' ), [ 'rzp_test_' ], [ 'rzp_live_' ] );
	}

	private static function mode( string $key, array $sandbox, array $live ): ?string {
		foreach ( [
			self::SANDBOX => $sandbox,
			self::LIVE    => $live,
		] as $mode => $prefixes ) {
			foreach ( $prefixes as $prefix ) {
				if ( str_starts_with( $key, $prefix ) ) {
					return $mode;
				}
			}
		}
		return null;
	}

	private static function allowed( string $mode, string $environment ): bool {
		return $mode === self::SANDBOX || $environment === 'production';
	}

	private static function status( ?string $mode, string $environment ): string {
		if ( $mode === null ) {
			return self::OFF;
		}
		return self::allowed( $mode, $environment ) ? $mode : self::REFUSED;
	}

	private static function settings( mixed $saved ): array {
		return ( is_array( $saved ) ? $saved : [] ) + [ 'description' => '' ];
	}

	private static function env( string $name ): string {
		return trim( (string) getenv( $name ) );
	}
}
