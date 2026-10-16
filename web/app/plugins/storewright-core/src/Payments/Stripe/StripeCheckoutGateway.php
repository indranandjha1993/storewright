<?php

declare(strict_types=1);

namespace Storewright\Core\Payments\Stripe;

use Storewright\Platform\GatewayCredentials;
use Throwable;
use WC_Order;
use WC_Payment_Gateway;
use WP_Error;

final class StripeCheckoutGateway extends WC_Payment_Gateway {

	public const ID        = 'stripe';
	private const SESSION  = '_storewright_stripe_session';
	private const INTENT   = '_storewright_stripe_payment_intent';
	private const ATTEMPTS = '_storewright_stripe_attempts';

	public function __construct() {
		$this->id                 = self::ID;
		$this->method_title       = 'Stripe';
		$this->method_description = __( 'Shoppers pay on Stripe\'s own checkout page with any method enabled on your Stripe account.', 'storewright' );
		$this->has_fields         = false;
		$this->supports           = [ 'products', 'refunds' ];
		$this->init_form_fields();
		$this->init_settings();
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			function (): void {
				$this->process_admin_options();
			}
		);
	}

	public function init_form_fields(): void {
		$this->form_fields = [
			'enabled'     => [
				'title'   => __( 'Enable', 'storewright' ),
				'type'    => 'checkbox',
				'label'   => __( 'Offer Stripe at checkout', 'storewright' ),
				'default' => 'yes',
			],
			'title'       => [
				'title'   => __( 'Title', 'storewright' ),
				'type'    => 'text',
				'default' => 'Stripe',
			],
			'description' => [
				'title'   => __( 'Description', 'storewright' ),
				'type'    => 'text',
				'default' => __( 'Cards, Apple Pay, Google Pay and Link on Stripe\'s secure page.', 'storewright' ),
			],
		];
	}

	public static function keys(): ?array {
		return GatewayCredentials::stripeKeys( wp_get_environment_type() );
	}

	public function is_available(): bool {
		return parent::is_available() && self::keys() !== null;
	}

	public function process_payment( $order_id ): array {
		$order    = wc_get_order( $order_id );
		$keys     = self::keys();
		$attempts = (int) $order->get_meta( self::ATTEMPTS ) + 1;
		try {
			$session = ( new StripeClient( $keys['secret'] ) )->post(
				'checkout/sessions',
				CheckoutSession::params( self::describe( $order ) ),
				"storewright-order-{$order->get_id()}-{$attempts}"
			);
		} catch ( Throwable $e ) {
			wc_add_notice( __( 'Stripe could not start the payment. Please try again or choose another way to pay.', 'storewright' ), 'error' );
			$order->add_order_note( 'Stripe checkout session failed: ' . $e->getMessage() );
			return [ 'result' => 'failure' ];
		}
		$order->update_meta_data( self::SESSION, $session['id'] );
		$order->update_meta_data( self::ATTEMPTS, (string) $attempts );
		$order->save();
		return [
			'result'   => 'success',
			'redirect' => $session['url'],
		];
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ): bool|WP_Error {
		$order  = wc_get_order( $order_id );
		$intent = (string) $order->get_meta( self::INTENT );
		$keys   = self::keys();
		if ( ! $order || $intent === '' || $keys === null ) {
			return new WP_Error( 'storewright_stripe_refund', __( 'This order has no Stripe payment to refund.', 'storewright' ) );
		}
		$minor = Money::minor( (float) ( $amount ?? $order->get_total() ), $order->get_currency() );
		try {
			$refund = ( new StripeClient( $keys['secret'] ) )->post(
				'refunds',
				[
					'payment_intent' => $intent,
					'amount'         => $minor,
					'metadata'       => [
						'order_id' => (string) $order->get_id(),
						'reason'   => (string) $reason,
					],
				],
				"storewright-refund-{$order->get_id()}-{$minor}-" . count( $order->get_refunds() )
			);
		} catch ( Throwable $e ) {
			return new WP_Error( 'storewright_stripe_refund', $e->getMessage() );
		}
		$order->add_order_note( sprintf( 'Stripe refund %s for %s.', $refund['id'], wc_price( (float) $amount, [ 'currency' => $order->get_currency() ] ) ) );
		return true;
	}

	public static function confirm( WC_Order $order, array $session ): bool {
		if ( $order->is_paid() ) {
			return true;
		}
		if ( ! CheckoutSession::paysFor( $session, self::describe( $order ) ) ) {
			return false;
		}
		$order->update_meta_data( self::INTENT, (string) $session['payment_intent'] );
		$order->payment_complete( (string) $session['payment_intent'] );
		$order->add_order_note( 'Paid on Stripe Checkout.' );
		return true;
	}

	public static function confirmOnReturn(): void {
		$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id   = absint( get_query_var( 'order-received' ) );
		$keys       = self::keys();
		if ( $session_id === '' || $order_id === 0 || $keys === null || ! is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_payment_method() !== self::ID || $order->get_meta( self::SESSION ) !== $session_id ) {
			return;
		}
		try {
			self::confirm( $order, ( new StripeClient( $keys['secret'] ) )->get( 'checkout/sessions/' . rawurlencode( $session_id ) ) );
		} catch ( Throwable $e ) {
			$order->add_order_note( 'Stripe session check on return failed: ' . $e->getMessage() );
		}
	}

	public static function describe( WC_Order $order ): array {
		$items = array_map( static fn( $item ): string => $item->get_name() . ' x ' . $item->get_quantity(), array_values( $order->get_items() ) );
		return [
			'id'          => $order->get_id(),
			'key'         => $order->get_order_key(),
			'currency'    => $order->get_currency(),
			'total'       => (float) $order->get_total(),
			'email'       => $order->get_billing_email(),
			'title'       => sprintf( 'Order %s at %s', $order->get_order_number(), get_bloginfo( 'name' ) ),
			'summary'     => mb_substr( implode( ', ', $items ), 0, 300 ),
			'success_url' => $order->get_checkout_order_received_url(),
			'cancel_url'  => $order->get_checkout_payment_url(),
		];
	}
}
