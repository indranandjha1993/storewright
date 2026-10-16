<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

use InvalidArgumentException;
use OutOfBoundsException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class AreasEndpoint {

	private const NAMESPACE = 'storewright/v1';

	public static function register(): void {
		$staff = [ 'permission_callback' => [ self::class, 'allowed' ] ];
		register_rest_route(
			self::NAMESPACE,
			'/areas',
			[
				[
					'methods'  => WP_REST_Server::READABLE,
					'callback' => [ self::class, 'index' ],
					...$staff,
				],
				[
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => [ self::class, 'create' ],
					...$staff,
				],
			]
		);
		register_rest_route(
			self::NAMESPACE,
			'/areas/(?P<id>\d+)',
			[
				[
					'methods'  => WP_REST_Server::EDITABLE,
					'callback' => [ self::class, 'update' ],
					...$staff,
				],
				[
					'methods'  => WP_REST_Server::DELETABLE,
					'callback' => [ self::class, 'delete' ],
					...$staff,
				],
			]
		);
		register_rest_route(
			self::NAMESPACE,
			'/areas/order',
			[
				'methods'  => WP_REST_Server::EDITABLE,
				'callback' => [ self::class, 'reorder' ],
				'args'     => [
					'ids' => [
						'type'     => 'array',
						'items'    => [ 'type' => 'integer' ],
						'required' => true,
					],
				],
				...$staff,
			]
		);
		register_rest_route(
			self::NAMESPACE,
			'/areas/test',
			[
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => [ self::class, 'test' ],
				'args'     => [
					'pin' => [ 'type' => 'string' ],
					'lat' => [
						'type'    => 'number',
						'minimum' => -90,
						'maximum' => 90,
					],
					'lng' => [
						'type'    => 'number',
						'minimum' => -180,
						'maximum' => 180,
					],
				],
				...$staff,
			]
		);
	}

	public static function allowed(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	public static function index(): WP_REST_Response {
		return new WP_REST_Response(
			[
				'areas'  => self::areas()->all(),
				'places' => Places::options(),
			]
		);
	}

	public static function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return self::change( static fn() => self::areas()->create( AreaInput::from( (array) $request->get_json_params() ) ), 201 );
	}

	public static function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return self::change( static fn() => self::areas()->update( (int) $request['id'], AreaInput::from( (array) $request->get_json_params() ) ) );
	}

	public static function delete( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return self::change( static fn() => self::areas()->delete( (int) $request['id'] ) );
	}

	public static function reorder( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return self::change( static fn() => self::areas()->reorder( (array) $request['ids'] ) );
	}

	public static function test( WP_REST_Request $request ): WP_REST_Response {
		$lat    = $request->get_param( 'lat' );
		$lng    = $request->get_param( 'lng' );
		$result = $lat !== null && $lng !== null
			? ShopperCheck::byPoint( new Point( (float) $lat, (float) $lng ), false )
			: ShopperCheck::byPin( (string) $request->get_param( 'pin' ), false );
		return new WP_REST_Response(
			[
				...$result,
				'options' => self::options( $result ),
			]
		);
	}

	private static function options( array $result ): array {
		if ( $result['areaId'] === null || $result['status'] === Answer::UNDELIVERABLE ) {
			return [];
		}
		$zone = \WC_Shipping_Zones::get_zone( (int) $result['areaId'] );
		if ( ! $zone ) {
			return [];
		}
		return array_values(
			array_map(
				static fn( $method ): array => [
					'title'  => $method->get_title(),
					'charge' => DeliveryMethods::charge( $method ),
				],
				$zone->get_shipping_methods( true )
			)
		);
	}

	private static function change( callable $change, int $status = 200 ): WP_REST_Response|WP_Error {
		try {
			$change();
		} catch ( OutOfBoundsException $e ) {
			return new WP_Error( 'storewright_area_missing', $e->getMessage(), [ 'status' => 404 ] );
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'storewright_area_invalid', $e->getMessage(), [ 'status' => 400 ] );
		}
		return new WP_REST_Response( [ 'areas' => self::areas()->all() ], $status );
	}

	private static function areas(): Areas {
		global $wpdb;
		return new Areas( $wpdb );
	}
}
