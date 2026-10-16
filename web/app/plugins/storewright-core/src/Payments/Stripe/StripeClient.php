<?php

declare(strict_types=1);

namespace Storewright\Core\Payments\Stripe;

use RuntimeException;

final class StripeClient {

	private const BASE = 'https://api.stripe.com/v1/';

	public function __construct( private readonly string $secretKey ) {}

	public function post( string $path, array $params, string $idempotencyKey ): array {
		return $this->request( 'POST', $path, $params, [ 'Idempotency-Key' => $idempotencyKey ] );
	}

	public function get( string $path, array $params = [] ): array {
		return $this->request( 'GET', $path . ( $params ? '?' . http_build_query( $params ) : '' ), [], [] );
	}

	private function request( string $method, string $path, array $params, array $headers ): array {
		$response = wp_remote_request(
			self::BASE . $path,
			[
				'method'  => $method,
				'timeout' => 30,
				'headers' => $headers + [ 'Authorization' => 'Bearer ' . $this->secretKey ],
				'body'    => $method === 'POST' ? http_build_query( $params ) : null,
			]
		);
		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( esc_html( $response->get_error_message() ) );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 || ! is_array( $body ) ) {
			throw new RuntimeException( esc_html( (string) ( $body['error']['message'] ?? "Stripe returned {$code}" ) ) );
		}
		return $body;
	}
}
