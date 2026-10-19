<?php

declare(strict_types=1);

namespace Storewright\Platform\WhiteLabel;

use WP_Admin_Bar;

final class AdminChrome {

	private const BAR_NODES = [ 'wp-logo', 'comments', 'updates', 'customize' ];

	private const DASHBOARD_BOXES = [
		[ 'dashboard_primary', 'side' ],
		[ 'dashboard_quick_press', 'side' ],
		[ 'dashboard_site_health', 'normal' ],
		[ 'dashboard_right_now', 'normal' ],
		[ 'dashboard_activity', 'normal' ],
		[ 'woocommerce_dashboard_recent_reviews', 'normal' ],
		[ 'wc_admin_dashboard_setup', 'normal' ],
	];

	public static function register(): void {
		add_action( 'admin_bar_menu', [ self::class, 'adminBar' ], 9999 );
		add_filter( 'admin_footer_text', static fn(): string => esc_html( Brand::name() ) );
		add_filter( 'update_footer', '__return_empty_string', PHP_INT_MAX );
		add_action( 'wp_dashboard_setup', [ self::class, 'dashboard' ], 99 );
		add_action( 'admin_menu', [ self::class, 'menu' ], 999 );
		add_filter( 'woocommerce_admin_features', [ self::class, 'features' ] );
		add_action( 'admin_head', [ self::class, 'help' ] );
	}

	public static function adminBar( WP_Admin_Bar $bar ): void {
		foreach ( self::BAR_NODES as $node ) {
			$bar->remove_node( $node );
		}
		$account = $bar->get_node( 'my-account' );
		if ( $account && is_string( $account->title ) ) {
			/* translators: %s: user display name */
			$greeting = sprintf( __( 'Howdy, %s' ), '' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			$bar->add_node(
				[
					'id'    => 'my-account',
					'title' => str_replace( $greeting, '', $account->title ),
				]
			);
		}
	}

	public static function help(): void {
		$screen = get_current_screen();
		if ( $screen ) {
			$screen->remove_help_tabs();
			$screen->set_help_sidebar( '' );
		}
	}

	public static function dashboard(): void {
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
		foreach ( self::DASHBOARD_BOXES as [ $id, $context ] ) {
			remove_meta_box( $id, 'dashboard', $context );
		}
	}

	public static function features( array $features ): array {
		return array_values( array_diff( $features, [ 'activity-panels' ] ) );
	}

	public static function menu(): void {
		global $menu;
		remove_menu_page( 'index.php' );
		foreach ( (array) $menu as $position => $item ) {
			$slug = (string) ( $item[2] ?? '' );
			$icon = match ( true ) {
				$slug === 'woocommerce'                     => 'dashicons-store',
				str_ends_with( $slug, 'PAYMENTS_MENU_ITEM' ) => 'dashicons-money-alt',
				default                                     => null,
			};
			if ( $icon !== null ) {
				$menu[ $position ][6] = $icon; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
	}
}
