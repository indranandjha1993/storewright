<?php

declare(strict_types=1);

namespace Storewright\Platform;

final class Hardening {

	public static function register(): void {
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'wp_headers', [ self::class, 'headers' ] );
		add_filter( 'rest_endpoints', [ self::class, 'hideUsersFromGuests' ] );
		add_filter( 'login_errors', static fn(): string => __( 'Check your username and password.', 'storewright' ) );
		add_filter( 'pre_option_blog_public', [ self::class, 'indexing' ] );
		add_action( 'template_redirect', [ self::class, 'blockAuthorArchives' ], 0 );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'rsd_link' );
	}

	public static function headers( array $headers ): array {
		unset( $headers['X-Pingback'] );
		$headers['X-Content-Type-Options'] = 'nosniff';
		$headers['X-Frame-Options']        = 'SAMEORIGIN';
		$headers['Referrer-Policy']        = 'strict-origin-when-cross-origin';
		return $headers;
	}

	public static function hideUsersFromGuests( array $endpoints ): array {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( str_starts_with( $route, '/wp/v2/users' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	public static function indexing( mixed $value ): mixed {
		return defined( 'DISALLOW_INDEXING' ) && DISALLOW_INDEXING ? '0' : $value;
	}

	public static function blockAuthorArchives(): void {
		if ( is_author() && ! is_user_logged_in() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
		}
	}
}
