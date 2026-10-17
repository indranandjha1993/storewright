<?php

declare(strict_types=1);

namespace Storewright\Core\Account;

use Storewright\Core\Asset;
use WP_REST_Request;
use WP_REST_Response;

final class SaveButton {

	public static function register(): void {
		register_block_type(
			'storewright/save-button',
			[
				'api_version'     => '3',
				'title'           => __( 'Save for later', 'storewright' ),
				'category'        => 'widgets',
				'render_callback' => [ self::class, 'render' ],
			]
		);
	}

	public static function routes(): void {
		register_rest_route(
			'storewright/v1',
			'/saved/(?P<id>\d+)',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'toggle' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);
	}

	public static function toggle( WP_REST_Request $request ): WP_REST_Response {
		$product = wc_get_product( (int) $request['id'] );
		if ( ! $product instanceof \WC_Product || ! $product->is_visible() ) {
			return new WP_REST_Response( [ 'message' => __( 'That product is not available.', 'storewright' ) ], 404 );
		}
		$items = new SavedItems( get_current_user_id() );
		return new WP_REST_Response(
			[
				'saved' => $items->toggle( $product->get_id() ),
				'count' => count( $items->ids() ),
			]
		);
	}

	public static function render(): string {
		$heart   = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round" aria-hidden="true"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/></svg>';
		$product = wc_get_product( get_the_ID() );
		if ( ! $product instanceof \WC_Product ) {
			return wp_is_serving_rest_request() ? '<span class="sw-save">' . $heart . '<span>' . esc_html__( 'Save for later', 'storewright' ) . '</span></span>' : '';
		}
		if ( ! is_user_logged_in() ) {
			return sprintf(
				'<a class="sw-save" href="%s">%s<span>%s</span></a>',
				esc_url( wc_get_page_permalink( 'myaccount' ) ),
				$heart,
				esc_html__( 'Sign in to save', 'storewright' )
			);
		}
		Asset::script( 'storewright-save', 'assets/js/save.js', [] );
		wp_add_inline_script(
			'storewright-save',
			'window.storewrightSave = ' . wp_json_encode(
				[
					'endpoint' => rest_url( 'storewright/v1/saved/' ),
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'save'     => __( 'Save for later', 'storewright' ),
					'saved'    => __( 'Saved', 'storewright' ),
				]
			) . ';',
			'before'
		);
		$saved = ( new SavedItems( get_current_user_id() ) )->has( $product->get_id() );
		return sprintf(
			'<button type="button" class="sw-save" aria-pressed="%s" data-sw-save="%d">%s<span>%s</span></button>',
			$saved ? 'true' : 'false',
			$product->get_id(),
			$heart,
			$saved ? esc_html__( 'Saved', 'storewright' ) : esc_html__( 'Save for later', 'storewright' )
		);
	}
}
