<?php
/**
 * Plugin Name: Storewright Console
 * Description: The full-screen console store staff work in.
 * Version: 0.1.0
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Requires Plugins: woocommerce
 * Author: Indra Nand Jha
 * License: GPL-2.0-or-later
 * Text Domain: storewright
 */

declare(strict_types=1);

namespace Storewright\Console;

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( string $className ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( ! str_starts_with( $className, $prefix ) ) {
			return;
		}
		$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $className, strlen( $prefix ) ) ) . '.php';
		if ( is_file( $file ) ) {
			require $file;
		}
	}
);

if ( is_file( __DIR__ . '/build/build.php' ) ) {
	require_once __DIR__ . '/build/build.php';
	Console::register();
	EmailLinks::register();
}
