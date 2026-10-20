<?php

declare(strict_types=1);

namespace Storewright\Platform\WhiteLabel;

final class StoreMenu {

	private const ORDER = [ 'wc-orders', 'storewright-returns', 'wc-admin&path=/customers', 'storewright-payments', 'storewright-areas', 'storewright-brand', 'wc-settings', 'wc-reports', 'wc-admin&path=/analytics/overview', 'wc-status' ];

	private const REMOVED = [ 'wc-addons' ];

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'arrange' ], 9999 );
		add_action( 'admin_init', [ self::class, 'home' ] );
		add_action( 'admin_init', [ self::class, 'zones' ] );
		add_filter( 'woocommerce_get_sections_shipping', [ self::class, 'sections' ] );
	}

	public static function arrange(): void {
		global $submenu;
		remove_menu_page( 'admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM' );
		foreach ( self::REMOVED as $slug ) {
			remove_submenu_page( 'woocommerce', $slug );
		}
		if ( empty( $submenu['woocommerce'] ) ) {
			return;
		}
		$rank = static function ( array $item ): int {
			$slug = (string) $item[2];
			foreach ( self::ORDER as $position => $known ) {
				if ( $slug === $known || str_starts_with( $slug, $known . '&' ) ) {
					return $position;
				}
			}
			return count( self::ORDER );
		};
		usort( $submenu['woocommerce'], static fn( array $a, array $b ): int => $rank( $a ) <=> $rank( $b ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	public static function home(): void {
		if ( ( $_GET['page'] ?? '' ) === 'wc-admin' && ! isset( $_GET['path'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( admin_url() );
			exit;
		}
	}

	public static function zones(): void {
		if ( ! is_admin() || ( $_GET['page'] ?? '' ) !== 'wc-settings' || ( $_GET['tab'] ?? '' ) !== 'shipping' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$section = (string) ( $_GET['section'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $section === '' && ! isset( $_GET['zone_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( admin_url( 'admin.php?page=storewright-areas' ) );
			exit;
		}
	}

	public static function sections( array $sections ): array {
		unset( $sections[''] );
		return $sections;
	}
}
