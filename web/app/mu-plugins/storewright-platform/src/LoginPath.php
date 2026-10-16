<?php

declare(strict_types=1);

namespace Storewright\Platform;

final class LoginPath {

	private static bool $serving = false;

	public static function slug(): string {
		$slug = defined( 'STOREWRIGHT_LOGIN_PATH' ) ? (string) STOREWRIGHT_LOGIN_PATH : 'sign-in';
		return trim( sanitize_title( $slug ), '/' ) ?: 'sign-in';
	}

	public static function register(): void {
		add_action( 'plugins_loaded', [ self::class, 'route' ], 1 );
		add_action( 'wp_loaded', [ self::class, 'serve' ] );
		add_filter( 'site_url', [ self::class, 'rewrite' ] );
		add_filter( 'network_site_url', [ self::class, 'rewrite' ] );
		add_filter( 'wp_redirect', [ self::class, 'rewrite' ] );
		add_filter( 'login_url', [ self::class, 'rewrite' ] );
		add_filter( 'logout_url', [ self::class, 'rewrite' ] );
		add_filter( 'lostpassword_url', [ self::class, 'rewrite' ] );
	}

	public static function url(): string {
		return home_url( '/' . self::slug() . '/' );
	}

	public static function route(): void {
		$path = self::requestPath();
		if ( $path === '/' . self::slug() ) {
			self::$serving      = true;
			$GLOBALS['pagenow'] = 'wp-login.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			return;
		}
		if ( str_ends_with( $path, '/wp-login.php' ) && ! self::$serving && ! defined( 'WP_CLI' ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}
	}

	public static function serve(): void {
		if ( ! self::$serving ) {
			return;
		}
		global $error, $interim_login, $action, $user_login;
		require_once ABSPATH . 'wp-login.php';
		exit;
	}

	public static function rewrite( string $url ): string {
		$login = rtrim( (string) wp_parse_url( (string) get_option( 'siteurl' ), PHP_URL_PATH ), '/' ) . '/wp-login.php';
		if ( ! str_contains( $url, $login ) ) {
			return $url;
		}
		return str_replace( $login, (string) wp_parse_url( self::url(), PHP_URL_PATH ), $url );
	}

	private static function requestPath(): string {
		$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( $home !== '/' && str_starts_with( $path, rtrim( $home, '/' ) ) ) {
			$path = substr( $path, strlen( rtrim( $home, '/' ) ) );
		}
		return '/' . trim( $path, '/' );
	}
}
