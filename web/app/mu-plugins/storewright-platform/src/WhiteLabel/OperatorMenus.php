<?php

declare(strict_types=1);

namespace Storewright\Platform\WhiteLabel;

final class OperatorMenus {

	public const HIDDEN = [ 'edit-comments.php', 'tools.php' ];

	public const HIDDEN_SUBMENUS = [ 'woocommerce' => 'wc-status' ];

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'trim' ], 999 );
	}

	public static function trim(): void {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		foreach ( self::HIDDEN as $slug ) {
			remove_menu_page( $slug );
		}
		foreach ( self::HIDDEN_SUBMENUS as $parent => $slug ) {
			remove_submenu_page( $parent, $slug );
		}
	}
}
