<?php
/**
 * Plugin Name: Storewright Core
 * Description: Payments routing, fulfilments, invoices and store settings for Storewright.
 * Version: 0.1.0
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Requires Plugins: woocommerce
 * Author: Indra Nand Jha
 * License: GPL-2.0-or-later
 * Text Domain: storewright
 */

declare(strict_types=1);

namespace Storewright\Core;

defined( 'ABSPATH' ) || exit;

const VERSION = '0.1.0';
const FILE    = __FILE__;

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

Schema::register();

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( \WooCommerce::class ) ) {
			return;
		}
		Payments\StripeWebhooks::register();
		Payments\GatewayRouting::register();
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'storewright seed', Cli\SeedCommand::class );
			\WP_CLI::add_command( 'storewright setup', Cli\SetupCommand::class );
		}
	}
);
