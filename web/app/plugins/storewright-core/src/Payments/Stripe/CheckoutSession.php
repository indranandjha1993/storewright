<?php

declare(strict_types=1);

namespace Storewright\Core\Payments\Stripe;

final class CheckoutSession {

	public static function params( array $order ): array {
		$currency = strtolower( $order['currency'] );
		return [
			'mode'                => 'payment',
			'client_reference_id' => (string) $order['id'],
			'customer_email'      => $order['email'],
			'success_url'         => $order['success_url'] . ( str_contains( $order['success_url'], '?' ) ? '&' : '?' ) . 'session_id={CHECKOUT_SESSION_ID}',
			'cancel_url'          => $order['cancel_url'],
			'line_items'          => [
				[
					'quantity'   => 1,
					'price_data' => [
						'currency'     => $currency,
						'unit_amount'  => Money::minor( $order['total'], $currency ),
						'product_data' => [
							'name'        => $order['title'],
							'description' => $order['summary'],
						],
					],
				],
			],
			'metadata'            => [
				'order_id'  => (string) $order['id'],
				'order_key' => $order['key'],
			],
			'payment_intent_data' => [
				'description' => $order['title'],
				'metadata'    => [ 'order_id' => (string) $order['id'] ],
			],
		];
	}

	public static function paysFor( array $session, array $order ): bool {
		return ( $session['payment_status'] ?? '' ) === 'paid'
			&& (string) ( $session['metadata']['order_id'] ?? '' ) === (string) $order['id']
			&& strtolower( (string) ( $session['currency'] ?? '' ) ) === strtolower( $order['currency'] )
			&& (int) ( $session['amount_total'] ?? -1 ) === Money::minor( $order['total'], $order['currency'] );
	}
}
