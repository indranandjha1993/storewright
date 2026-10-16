<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

use WC_Shipping_Zone;
use WP_Error;

final class PackageRouting {

	public static function register(): void {
		add_filter( 'woocommerce_cart_shipping_packages', [ self::class, 'tagPackages' ] );
		add_filter( 'woocommerce_package_rates', [ self::class, 'rates' ], 20, 2 );
		add_action( 'woocommerce_store_api_cart_errors', [ self::class, 'cartErrors' ], 10, 2 );
		add_action( 'woocommerce_after_checkout_validation', [ self::class, 'classicCheckout' ], 10, 2 );
		add_action( 'woocommerce_my_account_after_my_address', [ self::class, 'accountBadge' ] );
	}

	public static function tagPackages( array $packages ): array {
		$shared = ShopperLocation::get();
		foreach ( $packages as &$package ) {
			$package['storewright_location'] = $shared ? [ $shared->lat, $shared->lng ] : null;
		}
		return $packages;
	}

	public static function rates( array $rates, array $package ): array {
		$address = self::address( $package['destination'] ?? [] );
		if ( $address->postcode === '' ) {
			return $rates;
		}
		$answer = self::answer( $address );
		if ( ! $answer->isDeliverable() && $answer->status !== Answer::APPROXIMATE ) {
			return [];
		}
		$woo = \WC_Shipping_Zones::get_zone_matching_package( $package );
		if ( $answer->zone === null || $answer->zone->id === $woo->get_id() ) {
			return $rates;
		}
		$routed = [];
		foreach ( ( new WC_Shipping_Zone( $answer->zone->id ) )->get_shipping_methods( true ) as $method ) {
			$routed += (array) $method->get_rates_for_package( $package );
		}
		return $routed;
	}

	public static function cartErrors( WP_Error $errors, \WC_Cart $cart ): void {
		$message = self::refusal( $cart );
		if ( $message !== null ) {
			$errors->add( 'storewright_undeliverable', $message );
		}
	}

	public static function classicCheckout( array $data, WP_Error $errors ): void {
		$message = WC()->cart ? self::refusal( WC()->cart ) : null;
		if ( $message !== null ) {
			$errors->add( 'storewright_undeliverable', $message );
		}
	}

	public static function accountBadge( string $type ): void {
		if ( $type !== 'shipping' || ! is_user_logged_in() ) {
			return;
		}
		$customer = new \WC_Customer( get_current_user_id() );
		$address  = new Address( $customer->get_shipping_country(), $customer->get_shipping_state(), $customer->get_shipping_postcode() );
		if ( $address->postcode === '' ) {
			return;
		}
		$answer = self::answer( $address );
		printf(
			'<p class="sw-deliverability sw-deliverability--%1$s">%2$s</p>',
			esc_attr( $answer->status ),
			esc_html( Messages::badge( $answer ) )
		);
	}

	private static function refusal( \WC_Cart $cart ): ?string {
		$customer = WC()->customer;
		if ( ! $cart->needs_shipping() || ! $customer ) {
			return null;
		}
		$address = new Address( $customer->get_shipping_country(), $customer->get_shipping_state(), $customer->get_shipping_postcode() );
		if ( $address->postcode === '' ) {
			return null;
		}
		$answer = self::answer( $address );
		return $answer->isDeliverable() || $answer->status === Answer::APPROXIMATE ? null : Messages::undeliverable( $answer, $address );
	}

	private static function answer( Address $address ): Answer {
		return DeliveryCheck::forShopper( $address, ShopperLocation::get() );
	}

	private static function address( array $destination ): Address {
		return new Address( (string) ( $destination['country'] ?? '' ), (string) ( $destination['state'] ?? '' ), (string) ( $destination['postcode'] ?? '' ) );
	}
}
