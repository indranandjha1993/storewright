<?php

declare(strict_types=1);

namespace Storewright\Core\Fulfilment\Shiprocket;

use Storewright\Core\Fulfilment\CarrierException;

final class Client {

	private const TOKEN_TTL = 9 * DAY_IN_SECONDS;

	public function __construct(
		private readonly string $host,
		private readonly string $email,
		private readonly string $password,
		private readonly bool $sandbox
	) {}

	public function get( string $path, array $query = [] ): array {
		return $this->send( 'GET', $query === [] ? $path : $path . '?' . http_build_query( $query ), null, 30 );
	}

	public function post( string $path, array $body, int $timeout = 30 ): array {
		return $this->send( 'POST', $path, $body, $timeout );
	}

	private function send( string $method, string $path, ?array $body, int $timeout, bool $fresh = false ): array {
		$response = wp_remote_request(
			$this->host . '/v1/external/' . ltrim( $path, '/' ),
			[
				'method'  => $method,
				'timeout' => $timeout,
				'headers' => $this->headers( $this->token( $fresh ) ),
				'body'    => $body === null ? null : wp_json_encode( $body ),
			]
		);
		if ( is_wp_error( $response ) ) {
			throw new CarrierException( esc_html( $response->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code === 401 && ! $fresh ) {
			return $this->send( $method, $path, $body, $timeout, true );
		}
		if ( $code >= 400 || ! is_array( $data ) ) {
			$failure = CarrierException::http( self::reason( $data, $code ), $code );
			throw $failure;
		}
		return $data;
	}

	private function token( bool $fresh ): string {
		$key   = 'storewright_shiprocket_token_' . md5( $this->host . $this->email );
		$token = $fresh ? '' : (string) get_transient( $key );
		if ( $token !== '' ) {
			return $token;
		}
		$response = wp_remote_post(
			$this->host . '/v1/external/auth/login',
			[
				'timeout' => 30,
				'headers' => $this->headers( '' ),
				'body'    => wp_json_encode(
					[
						'email'    => $this->email,
						'password' => $this->password,
					]
				),
			]
		);
		if ( is_wp_error( $response ) ) {
			throw new CarrierException( esc_html( $response->get_error_message() ) );
		}
		$data  = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$token = is_array( $data ) ? (string) ( $data['token'] ?? '' ) : '';
		if ( $token === '' ) {
			throw new CarrierException( esc_html( self::reason( $data, (int) wp_remote_retrieve_response_code( $response ) ) ), false );
		}
		set_transient( $key, $token, self::TOKEN_TTL );
		return $token;
	}

	private function headers( string $token ): array {
		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		];
		if ( $token !== '' ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}
		if ( $this->sandbox ) {
			// The sandbox load balancer refuses any request that does not come from its own console.
			$headers['Referer'] = 'https://sandbox.shiprocket.in/';
		}
		return $headers;
	}

	private static function reason( mixed $data, int $code ): string {
		if ( is_array( $data ) ) {
			foreach ( [ 'errors', 'message', 'error' ] as $key ) {
				if ( ! empty( $data[ $key ] ) ) {
					return is_array( $data[ $key ] ) ? wp_json_encode( $data[ $key ] ) : (string) $data[ $key ];
				}
			}
		}
		return "Shiprocket answered {$code}";
	}
}
