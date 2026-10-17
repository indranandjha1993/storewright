<?php

declare(strict_types=1);

namespace Storewright\Core\Seo;

final class Robots {

	public static function register(): void {
		add_filter( 'robots_txt', [ self::class, 'text' ], 99, 2 );
	}

	public static function text( string $output, mixed $visible ): string {
		if ( ! $visible ) {
			return "User-agent: *\nDisallow: /\n";
		}
		$lines = [ 'User-agent: *' ];
		foreach ( [ 'cart', 'checkout', 'myaccount' ] as $page ) {
			$path = wp_parse_url( (string) wc_get_page_permalink( $page ), PHP_URL_PATH );
			if ( is_string( $path ) && $path !== '/' ) {
				$lines[] = 'Disallow: ' . $path;
			}
		}
		$lines[] = 'Disallow: /*?add-to-cart=';
		$lines[] = 'Disallow: /*?*add-to-cart=';
		$lines[] = '';
		$lines[] = 'Sitemap: ' . home_url( '/sitemap.xml' );
		return implode( "\n", $lines ) . "\n";
	}
}
