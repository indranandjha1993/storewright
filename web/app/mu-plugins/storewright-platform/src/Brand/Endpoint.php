<?php

declare(strict_types=1);

namespace Storewright\Platform\Brand;

use InvalidArgumentException;
use Storewright\Platform\WhiteLabel\Brand;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class Endpoint {

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
	}

	public static function routes(): void {
		register_rest_route(
			'storewright/v1',
			'/brand',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ self::class, 'show' ],
					'permission_callback' => [ self::class, 'allowed' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ self::class, 'update' ],
					'permission_callback' => [ self::class, 'allowed' ],
				],
			]
		);
	}

	public static function allowed(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	public static function show(): WP_REST_Response {
		return new WP_REST_Response( self::describe() );
	}

	public static function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$input = (array) $request->get_json_params();
		try {
			Settings::save( array_intersect_key( $input, Settings::DEFAULTS ) );
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'storewright_brand_invalid', $e->getMessage(), [ 'status' => 400 ] );
		}
		if ( array_key_exists( 'name', $input ) ) {
			$name = trim( sanitize_text_field( (string) $input['name'] ) );
			if ( $name === '' ) {
				return new WP_Error( 'storewright_brand_invalid', __( 'The store needs a name.', 'storewright' ), [ 'status' => 400 ] );
			}
			update_option( 'blogname', $name );
		}
		if ( array_key_exists( 'logo', $input ) ) {
			$logo = (int) $input['logo'];
			if ( $logo > 0 && ! wp_attachment_is_image( $logo ) ) {
				return new WP_Error( 'storewright_brand_invalid', __( 'The logo has to be an image from the media library.', 'storewright' ), [ 'status' => 400 ] );
			}
			update_option( 'site_logo', $logo );
			set_theme_mod( 'custom_logo', $logo );
		}
		if ( array_key_exists( 'heroImage', $input ) ) {
			$hero = (int) $input['heroImage'];
			if ( $hero > 0 && ! wp_attachment_is_image( $hero ) ) {
				return new WP_Error( 'storewright_brand_invalid', __( 'The home page photo has to be an image from the media library.', 'storewright' ), [ 'status' => 400 ] );
			}
			update_option( 'storewright_hero_image', $hero );
		}
		if ( array_key_exists( 'announcement', $input ) ) {
			update_option( 'storewright_announcement', mb_substr( trim( sanitize_text_field( (string) $input['announcement'] ) ), 0, 140 ) );
		}
		ThemeJson::refresh();
		return new WP_REST_Response( self::describe() );
	}

	public static function describe(): array {
		$settings = Settings::get();
		$logo     = Brand::logoId();
		$hero     = (int) get_option( 'storewright_hero_image' );
		return [
			'name'         => Brand::name(),
			'announcement' => (string) get_option( 'storewright_announcement', '' ),
			'logo'         => [
				'id'  => $logo,
				'url' => Brand::logoUrl(),
			],
			'heroImage'    => [
				'id'  => $hero,
				'url' => $hero ? (string) wp_get_attachment_image_url( $hero, 'large' ) : '',
			],
			'settings'     => $settings,
			'palette'      => Palette::from( $settings ),
			'radii'        => Palette::radii( $settings['radius'] ),
			'fonts'        => Fonts::all(),
			'fontCss'      => Fonts::fontFaceCss( array_merge( ...array_column( Fonts::all(), 'faces' ) ) ),
			'adminCss'     => Tokens::admin(),
		];
	}
}
