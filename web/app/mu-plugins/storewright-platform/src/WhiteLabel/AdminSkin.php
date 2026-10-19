<?php

declare(strict_types=1);

namespace Storewright\Platform\WhiteLabel;

final class AdminSkin {

	public static function register(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	public static function enqueue(): void {
		$plugin = dirname( __DIR__, 2 ) . '/plugin.php';
		$file   = dirname( $plugin ) . '/assets/admin.css';
		wp_enqueue_style( 'storewright-admin', plugins_url( 'assets/admin.css', $plugin ), [], (string) filemtime( $file ) );
	}
}
