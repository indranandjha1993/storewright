<?php

declare(strict_types=1);

namespace Storewright\Platform\WhiteLabel;

final class StaffRoles {

	private const OWNER_ONLY = [ 'edit_theme_options', 'switch_themes', 'customize', 'install_themes', 'update_themes' ];

	public static function register(): void {
		add_filter( 'user_has_cap', [ self::class, 'caps' ] );
		add_action( '_admin_menu', [ self::class, 'shoppersToAccount' ], 0 );
		add_action( 'admin_init', [ self::class, 'shoppersToAccount' ], 0 );
		add_filter( 'woocommerce_rest_check_permissions', [ self::class, 'rest' ], 10, 4 );
	}

	public static function caps( array $caps ): array {
		if ( ! empty( $caps['manage_options'] ) ) {
			return $caps;
		}
		foreach ( self::OWNER_ONLY as $cap ) {
			unset( $caps[ $cap ] );
		}
		return $caps;
	}

	// WooCommerce's orders page dies with an empty 500 for a shopper while the menu builds, before admin_init.
	public static function shoppersToAccount(): void {
		if ( wp_doing_ajax() || ! is_user_logged_in() || current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_posts' ) || ! function_exists( 'wc_get_page_permalink' ) ) {
			return;
		}
		if ( in_array( basename( (string) ( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ), [ 'admin-post.php', 'admin-ajax.php' ], true ) ) {
			return;
		}
		wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
		exit;
	}

	public static function rest( mixed $permission, string $context, int $id, string $kind ): mixed {
		if ( $kind === 'system_status' && ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return $permission;
	}
}
