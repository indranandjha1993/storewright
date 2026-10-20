<?php

declare(strict_types=1);

namespace Storewright\Core\Fulfilment;

use Storewright\Platform\CarrierCredentials;
use WP_REST_Request;
use WP_REST_Response;

final class ShiprocketWebhook {

	public static function register(): void {
		register_rest_route(
			'storewright/v1',
			'/webhooks/shiprocket',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'receive' ],
				'permission_callback' => [ self::class, 'authentic' ],
			]
		);
	}

	public static function authentic( WP_REST_Request $request ): bool {
		return self::tokenMatches( (string) $request->get_header( 'x-api-key' ), CarrierCredentials::shiprocketWebhookToken( wp_get_environment_type() ) );
	}

	public static function tokenMatches( string $given, string $expected ): bool {
		return $expected !== '' && $given !== '' && hash_equals( $expected, $given );
	}

	public static function receive( WP_REST_Request $request ): WP_REST_Response {
		$awb = sanitize_text_field( (string) ( $request->get_param( 'awb' ) ?? $request->get_param( 'awb_code' ) ?? '' ) );
		return new WP_REST_Response( [ 'ok' => $awb !== '' && Shipping::trackSoon( $awb ) ], 200 );
	}
}
