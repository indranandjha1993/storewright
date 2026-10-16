<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

use Throwable;
use WP_REST_Request;
use WP_REST_Response;

final class LocationEndpoint {

	public static function register(): void {
		register_rest_route(
			'storewright/v1',
			'/delivery',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'handle' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'pin' => [ 'type' => 'string' ],
					'lat' => [ 'type' => 'number' ],
					'lng' => [ 'type' => 'number' ],
				],
			]
		);
	}

	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		wc_load_cart();
		if ( $request->get_param( 'lat' ) !== null && $request->get_param( 'lng' ) !== null ) {
			try {
				$point = new Point( (float) $request->get_param( 'lat' ), (float) $request->get_param( 'lng' ) );
			} catch ( Throwable ) {
				return new WP_REST_Response( ShopperCheck::byPin( '' ), 400 );
			}
			return new WP_REST_Response( ShopperCheck::byPoint( $point ), 200 );
		}
		return new WP_REST_Response( ShopperCheck::byPin( (string) $request->get_param( 'pin' ) ), 200 );
	}
}
