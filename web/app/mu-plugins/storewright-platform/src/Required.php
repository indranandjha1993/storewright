<?php

declare(strict_types=1);

namespace Storewright\Platform;

final class Required {

	public const PLUGINS = [
		'woocommerce/woocommerce.php',
		'woo-razorpay/woo-razorpay.php',
		'storewright-core/storewright-core.php',
	];

	public const THEME = 'storewright';

	public static function register(): void {
		add_filter( 'pre_update_option_active_plugins', [ self::class, 'keepPlugins' ], 10, 2 );
		add_filter( 'pre_update_option_template', [ self::class, 'keepTemplate' ], 10, 2 );
		add_filter( 'pre_update_option_stylesheet', [ self::class, 'keepStylesheet' ], 10, 2 );
		add_filter( 'plugin_action_links', [ self::class, 'links' ], 99, 2 );
		add_filter( 'wp_prepare_themes_for_js', [ self::class, 'themes' ] );
	}

	public static function keepPlugins( mixed $value, mixed $old ): array {
		$present = array_filter( self::PLUGINS, static fn( string $plugin ): bool => is_file( WP_PLUGIN_DIR . '/' . $plugin ) );
		return self::protect( is_array( $value ) ? $value : [], is_array( $old ) ? $old : [], $present );
	}

	public static function protect( array $value, array $old, array $required = self::PLUGINS ): array {
		foreach ( $required as $plugin ) {
			if ( in_array( $plugin, $old, true ) && ! in_array( $plugin, $value, true ) ) {
				$value[] = $plugin;
			}
		}
		return array_values( $value );
	}

	public static function keepTemplate( mixed $value, mixed $old ): mixed {
		return $value === self::THEME || ! $old ? $value : $old;
	}

	public static function keepStylesheet( mixed $value, mixed $old ): mixed {
		if ( ! $old || ! is_string( $value ) ) {
			return $value;
		}
		return wp_get_theme( $value )->get_template() === self::THEME ? $value : $old;
	}

	public static function links( array $actions, string $plugin ): array {
		if ( ! in_array( $plugin, self::PLUGINS, true ) ) {
			return $actions;
		}
		unset( $actions['deactivate'] );
		$actions['storewright'] = '<span class="storewright-required">' . esc_html__( 'Required by Storewright', 'storewright' ) . '</span>';
		return $actions;
	}

	public static function themes( array $themes ): array {
		return array_filter( $themes, static fn( array $theme, string $slug ): bool => wp_get_theme( $slug )->get_template() === self::THEME, ARRAY_FILTER_USE_BOTH );
	}
}
