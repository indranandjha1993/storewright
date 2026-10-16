<?php

declare(strict_types=1);

namespace Storewright\Platform\WhiteLabel;

final class Promotions {

	private const SUBMENUS = [
		'woocommerce'           => [ 'wc-addons', 'wc-admin&path=/extensions' ],
		'woocommerce-marketing' => [ 'wc-admin&path=/marketing', 'admin.php?page=wc-admin&path=/marketing' ],
	];

	public static function register(): void {
		add_filter( 'woocommerce_allow_marketplace_suggestions', '__return_false' );
		add_filter( 'pre_option_woocommerce_show_marketplace_suggestions', static fn(): string => 'no' );
		add_filter( 'woocommerce_helper_suppress_admin_notices', '__return_true' );
		add_action( 'admin_menu', [ self::class, 'menus' ], 999 );
	}

	public static function menus(): void {
		foreach ( self::SUBMENUS as $parent => $slugs ) {
			foreach ( $slugs as $slug ) {
				remove_submenu_page( $parent, $slug );
			}
		}
	}
}
