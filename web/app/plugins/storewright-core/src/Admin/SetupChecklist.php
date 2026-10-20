<?php

declare(strict_types=1);

namespace Storewright\Core\Admin;

use Storewright\Platform\GatewayCredentials;
use WC_Shipping_Zones;

final class SetupChecklist {

	private const DISMISSED = 'storewright_setup_dismissed';

	public static function describe(): array {
		$steps = [
			self::step( 'brand', __( 'Make it yours', 'storewright' ), __( 'Name, logo, colours and typefaces.', 'storewright' ), get_option( 'storewright_brand' ) !== false || (int) get_option( 'site_logo' ) > 0, 'admin.php?page=storewright-brand' ),
			self::step( 'store', __( 'Add the store address', 'storewright' ), __( 'Printed on emails and invoices, with your GSTIN if you have one.', 'storewright' ), (string) get_option( 'woocommerce_store_address' ) !== '', 'admin.php?page=wc-settings' ),
			self::step( 'payments', __( 'Take payments', 'storewright' ), __( 'Razorpay and Stripe keys go in the server settings; cash on delivery works without them.', 'storewright' ), self::payments(), 'admin.php?page=storewright-payments' ),
			self::step( 'delivery', __( 'Set where you deliver', 'storewright' ), __( 'Areas, delivery charges and same-day zones.', 'storewright' ), self::delivery(), 'admin.php?page=storewright-areas' ),
			self::step( 'products', __( 'Add your products', 'storewright' ), __( 'Photos, prices, stock and the HSN code for invoices.', 'storewright' ), self::count( 'product' ) > 0, 'post-new.php?post_type=product' ),
			self::step( 'pages', __( 'Write your policies', 'storewright' ), __( 'Shipping and returns, terms of sale and privacy.', 'storewright' ), self::published( (int) get_option( 'woocommerce_terms_page_id' ) ) && self::published( (int) get_option( 'wp_page_for_privacy_policy' ) ), 'edit.php?post_type=page' ),
			self::step(
				'order',
				__( 'Place a test order', 'storewright' ),
				__( 'Buy something with cash on delivery to see the emails and the order screen.', 'storewright' ),
				wc_get_orders(
					[
						'limit'  => 1,
						'return' => 'ids',
					]
				) !== [],
				'admin.php?page=wc-orders'
			),
		];
		return [
			'steps'     => $steps,
			'done'      => count( array_filter( array_column( $steps, 'done' ) ) ),
			'dismissed' => get_user_meta( get_current_user_id(), self::DISMISSED, true ) === '1',
		];
	}

	public static function dismiss(): void {
		update_user_meta( get_current_user_id(), self::DISMISSED, '1' );
	}

	private static function step( string $id, string $title, string $detail, bool $done, string $path ): array {
		return [
			'id'     => $id,
			'title'  => $title,
			'detail' => $detail,
			'done'   => $done,
			'url'    => admin_url( $path ),
		];
	}

	private static function payments(): bool {
		$cod = (array) get_option( 'woocommerce_cod_settings', [] );
		if ( ( $cod['enabled'] ?? 'no' ) === 'yes' ) {
			return true;
		}
		$modes = class_exists( GatewayCredentials::class ) ? GatewayCredentials::modes( wp_get_environment_type() ) : [];
		return array_intersect( $modes, [ GatewayCredentials::LIVE, GatewayCredentials::SANDBOX ] ) !== [];
	}

	private static function delivery(): bool {
		foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
			if ( ! empty( $zone['shipping_methods'] ) ) {
				return true;
			}
		}
		return false;
	}

	private static function count( string $type ): int {
		return (int) ( wp_count_posts( $type )->publish ?? 0 );
	}

	private static function published( int $id ): bool {
		return $id > 0 && get_post_status( $id ) === 'publish';
	}
}
