<?php

declare(strict_types=1);

namespace Storewright\Platform;

final class Hardening {

	private const HEAD_LINKS = [
		[ 'wp_generator', 10 ],
		[ 'wlwmanifest_link', 10 ],
		[ 'rsd_link', 10 ],
		[ 'wp_shortlink_wp_head', 10 ],
		[ 'rest_output_link_wp_head', 10 ],
		[ 'wp_oembed_add_discovery_links', 10 ],
		[ 'wp_oembed_add_host_js', 10 ],
		[ 'feed_links_extra', 3 ],
		[ 'wp_resource_hints', 2 ],
	];

	public static function register(): void {
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'wp_headers', [ self::class, 'headers' ] );
		add_filter( 'rest_endpoints', [ self::class, 'hideUsersFromGuests' ] );
		add_filter( 'rest_index', [ self::class, 'quietIndex' ] );
		add_filter( 'login_errors', [ self::class, 'loginErrors' ] );
		add_filter( 'pre_option_blog_public', [ self::class, 'indexing' ] );
		add_action( 'template_redirect', [ self::class, 'blockAuthorArchives' ], 0 );
		add_action( 'template_redirect', [ self::class, 'noLinkHeaders' ], 999 );
		add_filter( 'the_generator', '__return_empty_string' );
		add_filter( 'style_loader_src', [ self::class, 'noVersion' ] );
		add_filter( 'script_loader_src', [ self::class, 'noVersion' ] );
		add_filter( 'x_redirect_by', '__return_false' );
		add_filter( 'show_admin_bar', [ self::class, 'adminBar' ] );
		add_filter( 'embed_oembed_discover', '__return_false' );
		foreach ( self::HEAD_LINKS as [ $hook, $priority ] ) {
			remove_action( 'wp_head', $hook, $priority );
		}
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
	}

	public static function loginErrors( string $errors ): string {
		return LoginThrottle::denied() ? $errors : __( 'Check your username and password.', 'storewright' );
	}

	public static function headers( array $headers ): array {
		unset( $headers['X-Pingback'] );
		$headers['X-Content-Type-Options']     = 'nosniff';
		$headers['X-Frame-Options']            = 'SAMEORIGIN';
		$headers['Referrer-Policy']            = 'strict-origin-when-cross-origin';
		$headers['Permissions-Policy']         = 'camera=(), microphone=(), geolocation=(self), payment=(self)';
		$headers['Cross-Origin-Opener-Policy'] = 'same-origin-allow-popups';
		if ( is_ssl() ) {
			$headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
		}
		return $headers;
	}

	public static function quietIndex( \WP_REST_Response $response ): \WP_REST_Response {
		if ( is_user_logged_in() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return $response;
		}
		$data = (array) $response->get_data();
		unset( $data['name'], $data['description'], $data['url'], $data['home'], $data['gmt_offset'], $data['timezone_string'], $data['namespaces'], $data['authentication'], $data['routes'], $data['site_logo'], $data['site_icon'], $data['site_icon_url'] );
		$response->set_data( $data );
		return $response;
	}

	public static function noLinkHeaders(): void {
		header_remove( 'Link' );
	}

	public static function noVersion( string $src ): string {
		return str_contains( $src, 'ver=' ) && ! str_contains( $src, '/app/' ) ? (string) remove_query_arg( 'ver', $src ) : $src;
	}

	public static function adminBar( bool $show ): bool {
		return $show && current_user_can( 'manage_woocommerce' );
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
