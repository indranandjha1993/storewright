<?php

declare(strict_types=1);

namespace Storewright\Core\Payments\Stripe;

use Storewright\Core\Payments\StripeSignature;
use Storewright\Core\Payments\WebhookGuard;
use Storewright\Core\Payments\WpdbEventStore;
use WP_REST_Request;
use WP_REST_Response;

final class StripeWebhookEndpoint {

	public static function register(): void {
		register_rest_route(
			'storewright/v1',
			'/webhooks/stripe',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'handle' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$keys      = StripeCheckoutGateway::keys();
		$payload   = (string) $request->get_body();
		$authentic = $keys !== null && ( new StripeSignature() )->verify( $payload, (string) $request->get_header( 'stripe-signature' ), $keys['webhook'], time() );
		if ( ! $authentic ) {
			return new WP_REST_Response( [ 'error' => 'signature' ], 400 );
		}

		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) ) {
			return new WP_REST_Response( [ 'error' => 'payload' ], 400 );
		}
		global $wpdb;
		$guard    = new WebhookGuard( new WpdbEventStore( $wpdb ) );
		$id       = (string) ( $event['id'] ?? '' );
		$decision = $guard->admit( StripeCheckoutGateway::ID, $id, (string) ( $event['type'] ?? '' ), $payload, true );
		if ( $decision === WebhookGuard::DUPLICATE ) {
			return new WP_REST_Response( [ 'received' => 'duplicate' ], 200 );
		}

		$status = self::apply( $event ) ? 200 : 500;
		$guard->settle( StripeCheckoutGateway::ID, $id, $status );
		return new WP_REST_Response( [ 'received' => true ], $status );
	}

	private static function apply( array $event ): bool {
		$session = $event['data']['object'] ?? [];
		$order   = isset( $session['metadata']['order_id'] ) ? wc_get_order( absint( $session['metadata']['order_id'] ) ) : null;
		if ( ! $order || ! str_starts_with( (string) ( $event['type'] ?? '' ), 'checkout.session.' ) ) {
			return true;
		}
		return match ( $event['type'] ) {
			'checkout.session.completed', 'checkout.session.async_payment_succeeded' => self::paidOrWaiting( $order, $session ),
			'checkout.session.async_payment_failed' => (bool) $order->update_status( 'failed', 'Stripe reports the payment failed.' ),
			default => true,
		};
	}

	private static function paidOrWaiting( \WC_Order $order, array $session ): bool {
		if ( ( $session['payment_status'] ?? '' ) === 'unpaid' ) {
			$order->update_status( 'on-hold', 'Waiting for Stripe to confirm the payment.' );
			return true;
		}
		return StripeCheckoutGateway::confirm( $order, $session );
	}
}
