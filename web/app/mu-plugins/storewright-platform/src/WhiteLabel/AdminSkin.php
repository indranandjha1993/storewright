<?php

declare(strict_types=1);

namespace Storewright\Platform\WhiteLabel;

final class AdminSkin {

	public static function register(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
		add_filter( 'get_user_option_admin_color', static fn(): string => 'fresh' );
		add_action( 'admin_init', [ self::class, 'oneScheme' ] );
	}

	public static function oneScheme(): void {
		remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );
	}

	public static function enqueue(): void {
		$plugin = dirname( __DIR__, 2 ) . '/plugin.php';
		$assets = dirname( $plugin ) . '/assets';
		wp_enqueue_style( 'storewright-admin', plugins_url( 'assets/admin.css', $plugin ), [], (string) filemtime( $assets . '/admin.css' ) );
		wp_enqueue_script( 'storewright-admin', plugins_url( 'assets/admin.js', $plugin ), [], (string) filemtime( $assets . '/admin.js' ), true );
		wp_add_inline_script( 'storewright-admin', 'window.storewrightShell = ' . wp_json_encode( self::shell() ) . ';', 'before' );
	}

	public static function shell(): array {
		return [
			'name'    => Brand::name(),
			'logo'    => Brand::logoUrl(),
			'home'    => home_url( '/' ),
			'label'   => __( 'Console', 'storewright' ),
			'user'    => wp_get_current_user()->display_name,
			'logout'  => wp_logout_url( home_url( '/' ) ),
			'signOut' => __( 'Sign out', 'storewright' ),
		];
	}
}
