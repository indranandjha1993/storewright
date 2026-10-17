<?php

declare(strict_types=1);

namespace Storewright\Console;

use WP_User;

final class Console {

	public const PAGE       = 'storewright';
	public const CAPABILITY = 'manage_woocommerce';

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'adminMenu' ], 5 );
		add_action( self::PAGE . '_init', [ self::class, 'menuItems' ] );
		add_action( self::PAGE . '_init', [ self::class, 'page' ] );
		add_filter( 'script_module_data_' . self::PAGE, [ self::class, 'data' ] );
		add_filter( 'login_redirect', [ self::class, 'landing' ], 10, 3 );
	}

	public static function url( string $route = '' ): string {
		$url = admin_url( 'admin.php?page=' . self::PAGE );
		return $route === '' ? $url : add_query_arg( 'p', rawurlencode( $route ), $url );
	}

	public static function adminMenu(): void {
		add_menu_page(
			__( 'Console', 'storewright' ),
			__( 'Console', 'storewright' ),
			self::CAPABILITY,
			self::PAGE,
			static fn() => self::generated( 'storewright_console_storewright_render_page' ),
			'dashicons-screenoptions',
			2
		);
	}

	public static function menuItems(): void {
		$items = [
			'today'     => [ __( 'Today', 'storewright' ), '/' ],
			'orders'    => [ __( 'Orders', 'storewright' ), '/orders' ],
			'products'  => [ __( 'Products', 'storewright' ), '/products' ],
			'customers' => [ __( 'Customers', 'storewright' ), '/customers' ],
			'areas'     => [ __( 'Delivery areas', 'storewright' ), '/areas' ],
			'content'   => [ __( 'Pages and journal', 'storewright' ), '/content' ],
			'brand'     => [ __( 'Brand', 'storewright' ), '/brand' ],
		];
		foreach ( $items as $id => [ $label, $path ] ) {
			self::generated( 'storewright_console_register_storewright_menu_item', $id, $label, $path );
		}
	}

	public static function page(): void {
		add_action( 'admin_head', [ self::class, 'hideNoScriptNotice' ] );
		$labels = [
			'Dashboard'           => __( 'Advanced', 'storewright' ),
			'Go to the Dashboard' => __( 'Open the advanced settings', 'storewright' ),
		];
		wp_add_inline_script(
			'wp-i18n',
			sprintf(
				'wp.hooks.addFilter( "i18n.gettext_default", "storewright/console", function ( translation, text ) { var labels = %s; return labels[ text ] || translation; } );',
				wp_json_encode( $labels )
			)
		);
	}

	public static function hideNoScriptNotice(): void {
		echo '<style>.js .hide-if-js{display:none}</style>';
	}

	public static function data( array $data ): array {
		return [
			...$data,
			'adminUrl'  => admin_url(),
			'storeName' => get_bloginfo( 'name' ),
			'currency'  => get_woocommerce_currency(),
			'locale'    => str_replace( '_', '-', get_user_locale() ),
			'timezone'  => wp_timezone_string(),
			'carriers'  => apply_filters( 'storewright_console_carriers', [] ),
			'mapTiles'  => apply_filters(
				'storewright_map_tiles',
				[
					'url'         => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
					'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
				]
			),
		];
	}

	public static function landing( string $redirect, string $requested, WP_User|\WP_Error $user ): string {
		if ( $requested !== '' && $requested !== admin_url() ) {
			return $redirect;
		}
		return $user instanceof WP_User && $user->has_cap( self::CAPABILITY ) ? self::url() : $redirect;
	}

	private static function generated( string $name, mixed ...$args ): void {
		if ( function_exists( $name ) ) {
			$name( ...$args );
		}
	}
}
