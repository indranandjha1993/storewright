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
		Payments\GatewayRouting::register();
		Checkout\PhoneSync::register();
		Checkout\BusinessFields::register();
		Checkout\PinAutofill::register();
		Checkout\FreeDelivery::register();
		Account\AccountPages::register();
		Emails\OrderButton::register();
		Emails\ShipmentCopy::register();
		Fulfilment\Carriers::register();
		Fulfilment\CustomerView::register();
		Checkout\ThankYou::register();
		Seo\Sitemap::register();
		Seo\Robots::register();
		Seo\Meta::register();
		Seo\StoreSchema::register();
		Invoice\Invoices::register();
		Invoice\ProductTax::register();
		Returns\CustomerForm::register();
		Payments\RazorpayWebhooks::register();
		Delivery\PackageRouting::register();
		add_filter( 'woocommerce_payment_gateways', static fn( array $gateways ): array => [ ...$gateways, Payments\Stripe\StripeCheckoutGateway::class ] );
		add_action( 'template_redirect', [ Payments\Stripe\StripeCheckoutGateway::class, 'confirmOnReturn' ] );
		add_action( 'rest_api_init', [ Payments\Stripe\StripeWebhookEndpoint::class, 'register' ] );
		add_action( 'rest_api_init', [ Delivery\LocationEndpoint::class, 'register' ] );
		add_action( 'rest_api_init', [ Delivery\AreasEndpoint::class, 'register' ] );
		add_action( 'rest_api_init', [ Payments\HealthEndpoint::class, 'register' ] );
		add_action( 'rest_api_init', [ Payments\PaymentsEndpoint::class, 'register' ] );
		add_action( 'rest_api_init', [ Admin\SettingsEndpoint::class, 'register' ] );
		add_action( 'rest_api_init', [ Admin\SetupChecklist::class, 'register' ] );
		add_action( 'rest_api_init', [ Returns\ReturnsEndpoint::class, 'register' ] );
		add_action( 'woocommerce_delete_shipping_zone', [ Delivery\ShapeStore::class, 'zoneDeleted' ] );
		add_action( 'init', [ Delivery\DeliveryBlock::class, 'register' ] );
		add_action( 'init', [ Account\SaveButton::class, 'register' ] );
		EditorBlocks::register();
		add_action( 'rest_api_init', [ Account\SaveButton::class, 'routes' ] );
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static fn( $registry ) => $registry->register( new Payments\Stripe\StripeCheckoutBlock() )
		);
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'storewright seed', Cli\SeedCommand::class );
			\WP_CLI::add_command( 'storewright setup', Cli\SetupCommand::class );
			\WP_CLI::add_command( 'storewright area', Cli\AreaCommand::class );
			\WP_CLI::add_command( 'storewright deliver', Cli\DeliverCommand::class );
			\WP_CLI::add_command( 'storewright pins', Cli\PinsCommand::class );
			\WP_CLI::add_command( 'storewright templates', Cli\TemplatesCommand::class );
		}
	}
);
