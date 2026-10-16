<?php

declare(strict_types=1);

namespace Storewright\Platform\WhiteLabel;

final class Wording {

	public const STORE = 'Store';

	private const OPERATOR_ROUTES = '#^/(?:wc-admin|wc-analytics|wc/v\d)(?:/|$)#';

	public static function register(): void {
		if ( is_admin() ) {
			self::hook();
			add_action( 'admin_init', [ self::class, 'scripts' ], 0 );
		}
		add_action( 'login_init', [ self::class, 'hook' ] );
		add_filter( 'rest_pre_dispatch', [ self::class, 'restRequest' ], 10, 3 );
		add_filter( 'woocommerce_rest_prepare_note', [ self::class, 'note' ] );
		add_filter( 'woocommerce_gateway_method_description', [ self::class, 'single' ], 20 );
	}

	public static function note( \WP_REST_Response $response ): \WP_REST_Response {
		$data = $response->get_data();
		foreach ( [ 'title', 'content' ] as $field ) {
			if ( isset( $data[ $field ] ) && is_string( $data[ $field ] ) ) {
				$data[ $field ] = self::rewrite( $data[ $field ], Brand::name() );
			}
		}
		$response->set_data( $data );
		return $response;
	}

	public static function hook(): void {
		add_filter( 'gettext', [ self::class, 'single' ], 20 );
		add_filter( 'gettext_with_context', [ self::class, 'single' ], 20 );
		add_filter( 'ngettext', [ self::class, 'single' ], 20 );
		add_filter( 'ngettext_with_context', [ self::class, 'single' ], 20 );
	}

	public static function restRequest( mixed $result, \WP_REST_Server $server, \WP_REST_Request $request ): mixed {
		if ( preg_match( self::OPERATOR_ROUTES, $request->get_route() ) ) {
			self::hook();
		}
		return $result;
	}

	public static function single( string $translation ): string {
		return self::rewrite( $translation, Brand::name() );
	}

	public static function rewrite( string $text, string $brand ): string {
		if ( ! str_contains( $text, 'WooCommerce' ) && ! str_contains( $text, 'WordPress' ) ) {
			return $text;
		}
		return (string) preg_replace(
			[ '/\s*(?:&#8212;|\x{2014})\s*(?:WordPress|WooCommerce)\s*$/u', '/\bWooCommerce\b(?!\.com)/', '/\bWordPress\b(?!\.(?:org|com|net))/' ],
			[ '', self::STORE, $brand ],
			$text
		);
	}

	public static function scripts(): void {
		$script = sprintf(
			'( function ( hooks, store, brand ) { var rewrite = function ( text ) { return typeof text === "string" ? text.replace( /\s*(?:&#8212;|\u2014)\s*(?:WordPress|WooCommerce)\s*$/, "" ).replace( /\bWooCommerce\b(?!\.com)/g, store ).replace( /\bWordPress\b(?!\.(?:org|com|net))/g, brand ) : text; }; [ "i18n.gettext", "i18n.gettext_with_context", "i18n.ngettext", "i18n.ngettext_with_context" ].forEach( function ( hook ) { hooks.addFilter( hook, "storewright/wording", rewrite ); } ); } )( wp.hooks, %s, %s );',
			wp_json_encode( self::STORE ),
			wp_json_encode( Brand::name() )
		);
		wp_add_inline_script( 'wp-i18n', $script );
	}
}
