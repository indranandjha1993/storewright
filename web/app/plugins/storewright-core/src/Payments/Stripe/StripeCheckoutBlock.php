<?php

declare(strict_types=1);

namespace Storewright\Core\Payments\Stripe;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class StripeCheckoutBlock extends AbstractPaymentMethodType {

	protected $name = StripeCheckoutGateway::ID;

	public function initialize(): void {
		$this->settings = (array) get_option( 'woocommerce_stripe_settings', [] );
	}

	public function is_active(): bool {
		return ( $this->settings['enabled'] ?? 'yes' ) === 'yes' && StripeCheckoutGateway::keys() !== null;
	}

	public function get_payment_method_script_handles(): array {
		wp_register_script(
			'storewright-stripe-checkout',
			plugins_url( 'assets/js/stripe-checkout-block.js', \Storewright\Core\FILE ),
			[ 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ],
			\Storewright\Core\VERSION,
			true
		);
		return [ 'storewright-stripe-checkout' ];
	}

	public function get_payment_method_data(): array {
		$gateway = new StripeCheckoutGateway();
		return [
			'title'       => $gateway->get_title(),
			'description' => $gateway->get_description(),
			'supports'    => $gateway->supports,
		];
	}
}
