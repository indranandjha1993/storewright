<?php

declare(strict_types=1);

namespace Storewright\Core\Seo;

final class Sitemap {

	public static function register(): void {
		add_action( 'init', [ self::class, 'rules' ] );
		add_filter( 'home_url', [ self::class, 'rename' ], 10, 2 );
		add_filter( 'wp_sitemaps_add_provider', static fn( $provider, string $name ) => $name === 'users' ? false : $provider, 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies', static fn( array $taxonomies ): array => array_diff_key( $taxonomies, array_flip( [ 'post_format', 'product_tag', 'product_shipping_class' ] ) ) );
	}

	public static function rules(): void {
		add_rewrite_rule( '^sitemap\.xml$', 'index.php?sitemap=index', 'top' );
		add_rewrite_rule( '^sitemap\.xsl$', 'index.php?sitemap-stylesheet=sitemap', 'top' );
		add_rewrite_rule( '^sitemap-index\.xsl$', 'index.php?sitemap-stylesheet=index', 'top' );
		add_rewrite_rule( '^sitemap-([a-z]+?)-([a-z\d_-]+?)-(\d+?)\.xml$', 'index.php?sitemap=$matches[1]&sitemap-subtype=$matches[2]&paged=$matches[3]', 'top' );
		add_rewrite_rule( '^sitemap-([a-z]+?)-(\d+?)\.xml$', 'index.php?sitemap=$matches[1]&paged=$matches[2]', 'top' );
	}

	public static function rename( string $url, string $path ): string {
		return str_starts_with( $path, '/wp-sitemap' ) ? str_replace( '/wp-sitemap', '/sitemap', $url ) : $url;
	}
}
