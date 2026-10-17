<?php

declare(strict_types=1);

namespace Storewright\Core\Payments;

use RuntimeException;

final class RazorpayClient {

	private const BASE = 'https://api.razorpay.com/v1/';

	public function __construct( private readonly string $keyId, private readonly string $secret ) {}

	public function get( string $path ): array {
		$response = wp_remote_get(
			self::BASE . $path,
			[
				'timeout' => 30,
				'headers' => [ 'Authorization' => 'Basic ' . base64_encode( "{$this->keyId}:{$this->secret}" ) ], // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			]
		);
		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( esc_html( $response->get_error_message() ) );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 || ! is_array( $body ) ) {
			throw new RuntimeException( esc_html( (string) ( $body['error']['description'] ?? "Razorpay returned {$code}" ) ) );
		}
		return $body;
	}
}
