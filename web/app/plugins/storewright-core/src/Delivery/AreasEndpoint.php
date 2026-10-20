<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class AreasEndpoint {

	public static function register(): void {
		register_rest_route(
			'storewright/v1',
			'/areas/test',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ self::class, 'test' ],
				'permission_callback' => static fn(): bool => current_user_can( 'manage_woocommerce' ),
			]
		);
	}

	public static function test( WP_REST_Request $request ): WP_REST_Response {
		$lat    = $request->get_param( 'lat' );
		$lng    = $request->get_param( 'lng' );
		$result = $lat !== null && $lng !== null
			? ShopperCheck::byPoint( new Point( (float) $lat, (float) $lng ), false )
			: ShopperCheck::byPin( (string) $request->get_param( 'pin' ), false );
		return new WP_REST_Response( $result );
	}
}
