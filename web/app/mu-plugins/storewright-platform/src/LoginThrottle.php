<?php

declare(strict_types=1);

namespace Storewright\Platform;

use WP_Error;
use WP_User;

final class LoginThrottle {

	public const ATTEMPTS = 5;
	public const WINDOW   = 15 * MINUTE_IN_SECONDS;

	public static function register(): void {
		add_filter( 'authenticate', [ self::class, 'gate' ], 30, 2 );
		add_action( 'wp_login_failed', [ self::class, 'failed' ] );
		add_action( 'wp_login', [ self::class, 'clear' ] );
	}

	private static bool $denied = false;

	public static function gate( WP_User|WP_Error|null $user, string $username ): WP_User|WP_Error|null {
		if ( $username === '' || ! self::locked( $username ) ) {
			return $user;
		}
		self::$denied = true;
		return new WP_Error( 'storewright_locked', __( 'Too many attempts. Try again in a few minutes.', 'storewright' ) );
	}

	public static function denied(): bool {
		return self::$denied;
	}

	public static function failed( string $username ): void {
		$key   = self::key( $username );
		$count = (int) get_transient( $key ) + 1;
		set_transient( $key, $count, self::WINDOW );
	}

	public static function clear( string $username ): void {
		delete_transient( self::key( $username ) );
	}

	public static function locked( string $username ): bool {
		return (int) get_transient( self::key( $username ) ) >= self::ATTEMPTS;
	}

	public static function key( string $username ): string {
		$address = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return 'storewright_login_' . md5( strtolower( $username ) . '|' . $address );
	}
}
