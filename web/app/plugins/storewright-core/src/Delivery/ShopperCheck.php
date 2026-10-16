<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

final class ShopperCheck {

	public const PIN_PATTERN = '/^[1-8][0-9]{5}$/';

	public static function byPin( string $pin ): array {
		$pin = preg_replace( '/\D/', '', $pin );
		if ( ! preg_match( self::PIN_PATTERN, (string) $pin ) ) {
			return self::result( 'invalid', (string) $pin, null, null );
		}
		global $wpdb;
		$entry = ( new Pincodes( $wpdb ) )->find( (string) $pin );
		self::remember( (string) $pin, $entry, null );
		if ( $entry === null ) {
			return self::result( 'unknown', (string) $pin, null, null );
		}
		return self::result( 'found', (string) $pin, $entry, DeliveryCheck::forShopper( new Address( 'IN', $entry['state'], (string) $pin ), null ) );
	}

	public static function byPoint( Point $point ): array {
		global $wpdb;
		$entry = ( new Pincodes( $wpdb ) )->nearest( $point );
		if ( $entry === null ) {
			return self::result( 'unknown', '', null, null );
		}
		self::remember( $entry['pin'], $entry, $point );
		return self::result( 'found', $entry['pin'], $entry, DeliveryCheck::forShopper( new Address( 'IN', $entry['state'], $entry['pin'] ), $point ) );
	}

	public static function current(): ?array {
		$pin = ShopperPin::get();
		return $pin === '' ? null : self::byPinWithoutRemembering( $pin );
	}

	private static function byPinWithoutRemembering( string $pin ): array {
		global $wpdb;
		$entry = ( new Pincodes( $wpdb ) )->find( $pin );
		if ( $entry === null ) {
			return self::result( 'unknown', $pin, null, null );
		}
		return self::result( 'found', $pin, $entry, DeliveryCheck::forShopper( new Address( 'IN', $entry['state'], $pin ), ShopperLocation::get() ) );
	}

	private static function remember( string $pin, ?array $entry, ?Point $point ): void {
		if ( ! WC()->session ) {
			return;
		}
		if ( WC()->session instanceof \WC_Session_Handler ) {
			WC()->session->set_customer_session_cookie( true );
		}
		ShopperPin::set( $pin );
		$point ? ShopperLocation::set( $point ) : ShopperLocation::clear();
		$customer = WC()->customer;
		if ( ! $customer || ! $entry || ( is_user_logged_in() && $customer->get_shipping_postcode() !== '' ) ) {
			return;
		}
		if ( $customer->get_shipping_postcode() !== $pin ) {
			$customer->set_shipping_city( $entry['district'] );
		}
		$customer->set_shipping_country( 'IN' );
		$customer->set_shipping_state( $entry['state'] );
		$customer->set_shipping_postcode( $pin );
		$customer->save();
	}

	private static function result( string $lookup, string $pin, ?array $entry, ?Answer $answer ): array {
		return [
			'lookup'   => $lookup,
			'pin'      => $pin,
			'district' => $entry['district'] ?? '',
			'status'   => $answer ? $answer->status : Answer::UNDELIVERABLE,
			'area'     => $answer?->zone?->name,
			'message'  => Messages::forShopper( $lookup, $pin, (string) ( $entry['district'] ?? '' ), $answer ),
		];
	}
}
