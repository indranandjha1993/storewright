<?php

declare(strict_types=1);

namespace Storewright\Core\Seo;

use WC_Product;
use WP_Term;

final class Meta {

	private const LENGTH = 160;

	public static function register(): void {
		add_action( 'wp_head', [ self::class, 'print' ], 2 );
	}

	public static function print(): void {
		if ( is_admin() || is_feed() || is_404() || is_search() ) {
			return;
		}
		$page = self::describe();
		if ( $page === null ) {
			return;
		}
		$tags = [
			'description'    => $page['description'],
			'og:type'        => $page['type'],
			'og:title'       => $page['title'],
			'og:description' => $page['description'],
			'og:url'         => $page['url'],
			'og:site_name'   => get_bloginfo( 'name' ),
			'og:locale'      => get_locale(),
			'og:image'       => $page['image'],
			'twitter:card'   => $page['image'] !== '' ? 'summary_large_image' : 'summary',
			...$page['extra'],
		];
		foreach ( array_filter( $tags, static fn( string $value ): bool => $value !== '' ) as $name => $value ) {
			printf(
				'<meta %1$s="%2$s" content="%3$s">' . "\n",
				str_contains( $name, ':' ) && ! str_starts_with( $name, 'twitter:' ) ? 'property' : 'name',
				esc_attr( $name ),
				esc_attr( $value )
			);
		}
	}

	public static function describe(): ?array {
		$object = get_queried_object();
		if ( is_front_page() ) {
			return self::page( get_bloginfo( 'name' ), (string) get_bloginfo( 'description' ), home_url( '/' ), self::fallbackImage(), 'website' );
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( get_queried_object_id() );
			return $product instanceof WC_Product ? self::product( $product ) : null;
		}
		if ( $object instanceof WP_Term ) {
			$image = (int) get_term_meta( $object->term_id, 'thumbnail_id', true );
			return self::page( $object->name, $object->description, (string) get_term_link( $object ), $image ? (string) wp_get_attachment_image_url( $image, 'large' ) : self::fallbackImage(), 'website' );
		}
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return self::page( get_the_title( wc_get_page_id( 'shop' ) ), (string) get_bloginfo( 'description' ), (string) get_permalink( wc_get_page_id( 'shop' ) ), self::fallbackImage(), 'website' );
		}
		if ( is_singular() ) {
			$post = get_queried_object();
			$text = has_excerpt( $post ) ? get_the_excerpt( $post ) : (string) get_post_field( 'post_content', $post );
			return self::page( single_post_title( '', false ), $text, (string) get_permalink( $post ), (string) ( get_the_post_thumbnail_url( $post, 'large' ) ?: self::fallbackImage() ), is_singular( 'post' ) ? 'article' : 'website' );
		}
		return null;
	}

	public static function clip( string $text ): string {
		$text = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( $text ) ) ) );
		if ( mb_strlen( $text ) <= self::LENGTH ) {
			return $text;
		}
		$cut = mb_substr( $text, 0, self::LENGTH - 1 );
		return rtrim( mb_substr( $cut, 0, (int) mb_strrpos( $cut, ' ' ) ), ' ,.;:' ) . '...';
	}

	private static function product( WC_Product $product ): array {
		$image = $product->get_image_id() ? (string) wp_get_attachment_image_url( (int) $product->get_image_id(), 'large' ) : '';
		$page  = self::page( $product->get_name(), $product->get_short_description() ?: $product->get_description(), $product->get_permalink(), $image ?: self::fallbackImage(), 'product' );
		$price = wc_get_price_to_display( $product );
		if ( $price > 0 ) {
			$page['extra'] = [
				'product:price:amount'   => wc_format_decimal( $price, wc_get_price_decimals() ),
				'product:price:currency' => get_woocommerce_currency(),
				'product:availability'   => $product->is_in_stock() ? 'in stock' : 'out of stock',
			];
		}
		return $page;
	}

	private static function page( string $title, string $text, string $url, string $image, string $type ): array {
		return [
			'title'       => wp_strip_all_tags( $title ),
			'description' => self::clip( $text ),
			'url'         => $url,
			'image'       => $image,
			'type'        => $type,
			'extra'       => [],
		];
	}

	private static function fallbackImage(): string {
		$hero = (int) get_option( 'storewright_hero_image' );
		if ( $hero ) {
			return (string) wp_get_attachment_image_url( $hero, 'large' );
		}
		$logo = (int) get_option( 'site_logo' );
		return $logo ? (string) wp_get_attachment_image_url( $logo, 'full' ) : '';
	}
}
