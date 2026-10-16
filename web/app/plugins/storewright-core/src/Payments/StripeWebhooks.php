<?php

declare(strict_types=1);

namespace Storewright\Core\Payments;

final class StripeWebhooks {

	private const GATEWAY = 'stripe';

	public static function register(): void {
		add_action( 'woocommerce_api_wc_stripe', [ self::class, 'intercept' ], 1 );
	}

	public static function intercept(): void {
		if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
			return;
		}
		$payload   = (string) file_get_contents( 'php://input' );
		$header    = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ) : '';
		$authentic = ( new StripeSignature() )->verify( $payload, $header, self::secret(), time() );

		global $wpdb;
		$guard    = new WebhookGuard( new WpdbEventStore( $wpdb ) );
		$decision = $guard->admit( self::GATEWAY, $payload, $authentic );

		if ( $decision === WebhookGuard::DUPLICATE ) {
			status_header( 200 );
			exit;
		}
		if ( $decision === WebhookGuard::RECORDED ) {
			add_action( 'shutdown', static fn() => $guard->settle( self::GATEWAY, $payload, (int) http_response_code() ) );
		}
	}

	private static function secret(): string {
		$settings = get_option( 'woocommerce_stripe_settings', [] );
		$settings = is_array( $settings ) ? $settings : [];
		$key      = ( $settings['testmode'] ?? 'no' ) === 'yes' ? 'test_webhook_secret' : 'webhook_secret';
		return (string) ( $settings[ $key ] ?? '' );
	}
}
