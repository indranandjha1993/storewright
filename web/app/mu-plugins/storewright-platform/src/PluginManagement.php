<?php

declare(strict_types=1);

namespace Storewright\Platform;

final class PluginManagement {

	public const LOCKED = [ 'install_themes', 'upload_themes', 'update_themes', 'delete_themes', 'switch_themes', 'edit_themes', 'edit_plugins', 'update_core' ];

	public static function register(): void {
		if ( ! self::open() ) {
			return;
		}
		add_filter( 'map_meta_cap', [ self::class, 'caps' ], 10, 2 );
		add_filter( 'site_transient_update_plugins', [ self::class, 'updates' ] );
		add_filter( 'auto_update_plugin', [ self::class, 'autoUpdate' ], 10, 2 );
	}

	public static function mode(): string {
		return getenv( 'PLUGIN_MANAGEMENT' ) === 'open' ? 'open' : 'managed';
	}

	public static function open(): bool {
		return self::mode() === 'open';
	}

	public static function caps( array $caps, string $cap ): array {
		return in_array( $cap, self::LOCKED, true ) ? [ 'do_not_allow' ] : $caps;
	}

	public static function updates( mixed $transient ): mixed {
		if ( is_object( $transient ) && isset( $transient->response ) && is_array( $transient->response ) ) {
			foreach ( Required::PLUGINS as $plugin ) {
				unset( $transient->response[ $plugin ] );
			}
		}
		return $transient;
	}

	public static function autoUpdate( mixed $update, mixed $item ): mixed {
		return is_object( $item ) && isset( $item->plugin ) && in_array( $item->plugin, Required::PLUGINS, true ) ? false : $update;
	}

	public static function extras(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			$includes = ( defined( 'ABSPATH' ) ? ABSPATH : '' ) . 'wp-admin/includes/plugin.php';
			if ( ! is_file( $includes ) ) {
				return [];
			}
			require_once $includes;
		}
		$extras = [];
		foreach ( get_plugins() as $file => $data ) {
			if ( in_array( $file, Required::PLUGINS, true ) ) {
				continue;
			}
			$extras[] = [
				'plugin'  => $file,
				'name'    => (string) $data['Name'],
				'version' => (string) $data['Version'],
				'active'  => is_plugin_active( $file ),
			];
		}
		return $extras;
	}
}
