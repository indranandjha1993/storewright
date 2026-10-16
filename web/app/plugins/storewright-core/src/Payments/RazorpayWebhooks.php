<?php

declare(strict_types=1);

namespace Storewright\Core\Payments;

use Storewright\Platform\GatewayCredentials;

final class RazorpayWebhooks {

	private const GATEWAY = 'razorpay';

	public static function register(): void {
		add_action( 'admin_post_nopriv_rzp_wc_webhook', [ self::class, 'intercept' ], 1 );
		add_action( 'admin_post_rzp_wc_webhook', [ self::class, 'intercept' ], 1 );
	}

	public static function authentic( string $payload, string $signature, string $secret ): bool {
		return $secret !== '' && $signature !== '' && hash_equals( hash_hmac( 'sha256', $payload, $secret ), $signature );
	}

	public static function intercept(): void {
		$payload   = (string) file_get_contents( 'php://input' );
		$signature = isset( $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ) ) : '';
		$id        = isset( $_SERVER['HTTP_X_RAZORPAY_EVENT_ID'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_RAZORPAY_EVENT_ID'] ) ) : '';
		$event     = json_decode( $payload, true );
		$type      = is_array( $event ) ? (string) ( $event['event'] ?? '' ) : '';

		global $wpdb;
		$guard    = new WebhookGuard( new WpdbEventStore( $wpdb ) );
		$decision = $guard->admit( self::GATEWAY, $id, $type, $payload, self::authentic( $payload, $signature, GatewayCredentials::razorpayWebhookSecret( wp_get_environment_type() ) ) );

		if ( $decision === WebhookGuard::DUPLICATE ) {
			status_header( 200 );
			exit;
		}
		if ( $decision === WebhookGuard::RECORDED ) {
			add_action( 'shutdown', static fn() => $guard->settle( self::GATEWAY, $id, (int) http_response_code() ) );
		}
	}
}
