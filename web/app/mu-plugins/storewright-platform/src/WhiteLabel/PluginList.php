<?php

declare(strict_types=1);

namespace Storewright\Platform\WhiteLabel;

use Storewright\Platform\PluginManagement;

final class PluginList {

	public static function register(): void {
		add_filter( 'all_plugins', [ self::class, 'plugins' ] );
		add_filter( 'plugin_row_meta', [ self::class, 'meta' ], 100, 2 );
		add_filter( 'bulk_actions-plugins', [ self::class, 'bulkActions' ], 100 );
		add_filter( 'views_plugins', [ self::class, 'views' ], 100 );
		add_filter( 'manage_plugins_columns', [ self::class, 'columns' ], 100 );
		add_filter( 'admin_body_class', [ self::class, 'bodyClass' ] );
	}

	public static function plugins( array $plugins ): array {
		if ( isset( $plugins['woocommerce/woocommerce.php'] ) ) {
			$plugins['woocommerce/woocommerce.php'] = self::describe( $plugins['woocommerce/woocommerce.php'], __( 'Commerce engine', 'storewright' ), __( 'Catalogue, bag, checkout, orders and customer accounts.', 'storewright' ), false );
		}
		if ( isset( $plugins['woo-razorpay/woo-razorpay.php'] ) ) {
			$plugins['woo-razorpay/woo-razorpay.php'] = self::describe( $plugins['woo-razorpay/woo-razorpay.php'], 'Razorpay', __( 'Payments through Razorpay: cards, UPI, netbanking and wallets.', 'storewright' ), true );
		}
		return $plugins;
	}

	public static function meta( array $meta, string $plugin ): array {
		return $plugin === 'woocommerce/woocommerce.php' ? array_slice( $meta, 0, 1 ) : $meta;
	}

	public static function bulkActions( array $actions ): array {
		return PluginManagement::open() ? $actions : [];
	}

	public static function views( array $views ): array {
		return PluginManagement::open() ? $views : [];
	}

	public static function columns( array $columns ): array {
		if ( ! PluginManagement::open() ) {
			unset( $columns['cb'] );
		}
		return $columns;
	}

	public static function bodyClass( string $classes ): string {
		return $classes . ( PluginManagement::open() ? ' storewright-plugins-open' : ' storewright-plugins-managed' );
	}

	private static function describe( array $plugin, string $name, string $description, bool $keepLinks ): array {
		$plugin['Name']        = $name;
		$plugin['Title']       = $name;
		$plugin['Description'] = $description;
		if ( ! $keepLinks ) {
			$plugin['PluginURI'] = '';
			$plugin['AuthorURI'] = '';
			$plugin['Author']    = '';
		}
		return $plugin;
	}
}
