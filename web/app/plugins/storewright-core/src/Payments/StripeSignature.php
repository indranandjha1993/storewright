<?php

declare(strict_types=1);

namespace Storewright\Core\Payments;

final class StripeSignature {

	public function __construct( private readonly int $tolerance = 300 ) {}

	public function verify( string $payload, string $header, string $secret, int $now ): bool {
		if ( $secret === '' || $header === '' ) {
			return false;
		}
		$timestamp  = null;
		$signatures = [];
		foreach ( explode( ',', $header ) as $part ) {
			[ $key, $value ] = array_pad( explode( '=', trim( $part ), 2 ), 2, '' );
			if ( $key === 't' ) {
				$timestamp = ctype_digit( $value ) ? (int) $value : null;
			} elseif ( $key === 'v1' ) {
				$signatures[] = $value;
			}
		}
		if ( $timestamp === null || abs( $now - $timestamp ) > $this->tolerance ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}
		return false;
	}
}
