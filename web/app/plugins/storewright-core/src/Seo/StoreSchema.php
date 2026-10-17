<?php

declare(strict_types=1);

namespace Storewright\Core\Seo;

final class StoreSchema {

	public static function register(): void {
		add_action( 'wp_head', [ self::class, 'print' ], 20 );
	}

	public static function print(): void {
		if ( ! is_front_page() ) {
			return;
		}
		$home   = home_url( '/' );
		$logo   = (int) get_option( 'site_logo' );
		$store  = array_filter(
			[
				'@type' => 'Organization',
				'@id'   => $home . '#organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => $home,
				'logo'  => $logo ? (string) wp_get_attachment_image_url( $logo, 'full' ) : null,
				'email' => (string) get_option( 'woocommerce_email_from_address' ) ?: null,
			]
		);
		$site   = [
			'@type'           => 'WebSite',
			'@id'             => $home . '#website',
			'name'            => get_bloginfo( 'name' ),
			'url'             => $home,
			'publisher'       => [ '@id' => $home . '#organization' ],
			'potentialAction' => [
				'@type'       => 'SearchAction',
				'target'      => add_query_arg( 'post_type', 'product', $home ) . '&s={search_term_string}',
				'query-input' => 'required name=search_term_string',
			],
		];
		$schema = [
			'@context' => 'https://schema.org',
			'@graph'   => [ $store, $site ],
		];
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	}
}
