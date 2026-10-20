<?php

declare(strict_types=1);

namespace Storewright\Core\Admin;

final class Flash {

	private const KEY = 'storewright_flash_';

	public static function register(): void {
		add_action( 'admin_notices', [ self::class, 'output' ] );
	}

	public static function success( string $message ): void {
		self::add( $message, 'success' );
	}

	public static function error( string $message ): void {
		self::add( $message, 'error' );
	}

	public static function output(): void {
		$flash = get_transient( self::KEY . get_current_user_id() );
		if ( ! is_array( $flash ) ) {
			return;
		}
		delete_transient( self::KEY . get_current_user_id() );
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( (string) $flash['type'] ), esc_html( (string) $flash['message'] ) );
	}

	private static function add( string $message, string $type ): void {
		set_transient(
			self::KEY . get_current_user_id(),
			[
				'message' => $message,
				'type'    => $type,
			],
			MINUTE_IN_SECONDS
		);
	}
}
