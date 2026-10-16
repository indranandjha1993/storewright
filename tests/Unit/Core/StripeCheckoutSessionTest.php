<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Storewright\Core\Payments\Stripe\CheckoutSession;
use Storewright\Core\Payments\Stripe\Money;

final class StripeCheckoutSessionTest extends TestCase {

	private const ORDER = [
		'id'          => 54,
		'key'         => 'wc_order_abc',
		'currency'    => 'INR',
		'total'       => 827.0,
		'email'       => 'asha@example.com',
		'title'       => 'Order 54 at Storewright',
		'summary'     => 'Stoneware Mug x 1, Running a Small Shop Online x 1',
		'success_url' => 'http://shop.test/checkout/order-received/54/?key=wc_order_abc',
		'cancel_url'  => 'http://shop.test/checkout/order-pay/54/?pay_for_order=true&key=wc_order_abc',
	];

	public function test_amounts_are_sent_in_the_currency_minor_unit(): void {
		self::assertSame( 82700, Money::minor( 827.0, 'INR' ) );
		self::assertSame( 1999, Money::minor( 19.99, 'usd' ) );
		self::assertSame( 1500, Money::minor( 1500, 'JPY' ) );
		self::assertSame( 12350, Money::minor( 12.345, 'KWD' ) );
	}

	public function test_the_session_charges_the_order_total_once(): void {
		$params = CheckoutSession::params( self::ORDER );

		self::assertSame( 'payment', $params['mode'] );
		self::assertSame( 'inr', $params['line_items'][0]['price_data']['currency'] );
		self::assertSame( 82700, $params['line_items'][0]['price_data']['unit_amount'] );
		self::assertSame( 1, $params['line_items'][0]['quantity'] );
		self::assertSame( '54', $params['metadata']['order_id'] );
		self::assertSame( 'asha@example.com', $params['customer_email'] );
	}

	public function test_the_session_leaves_payment_methods_to_the_stripe_account(): void {
		self::assertArrayNotHasKey( 'payment_method_types', CheckoutSession::params( self::ORDER ) );
	}

	public function test_the_return_url_carries_the_session_id(): void {
		$params = CheckoutSession::params( self::ORDER );

		self::assertStringEndsWith( '?key=wc_order_abc&session_id={CHECKOUT_SESSION_ID}', $params['success_url'] );
		self::assertSame( self::ORDER['cancel_url'], $params['cancel_url'] );
	}

	public function test_a_paid_session_for_this_order_and_amount_pays_for_it(): void {
		$session = [
			'payment_status' => 'paid',
			'amount_total'   => 82700,
			'currency'       => 'inr',
			'metadata'       => [ 'order_id' => '54' ],
		];

		self::assertTrue( CheckoutSession::paysFor( $session, self::ORDER ) );
	}

	public function test_a_session_that_differs_in_any_way_does_not(): void {
		$paid = [
			'payment_status' => 'paid',
			'amount_total'   => 82700,
			'currency'       => 'inr',
			'metadata'       => [ 'order_id' => '54' ],
		];

		self::assertFalse( CheckoutSession::paysFor( [ 'payment_status' => 'unpaid' ] + $paid, self::ORDER ) );
		self::assertFalse( CheckoutSession::paysFor( [ 'amount_total' => 100 ] + $paid, self::ORDER ) );
		self::assertFalse( CheckoutSession::paysFor( [ 'currency' => 'usd' ] + $paid, self::ORDER ) );
		self::assertFalse( CheckoutSession::paysFor( [ 'metadata' => [ 'order_id' => '55' ] ] + $paid, self::ORDER ) );
	}
}
