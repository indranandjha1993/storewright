<?php

declare(strict_types=1);

namespace Storewright\Platform\Brand;

final class Fonts {

	private const SYSTEM_SANS  = "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, 'Noto Sans', sans-serif";
	private const SYSTEM_SERIF = "ui-serif, Georgia, 'Times New Roman', serif";

	private const CATALOGUE = [
		'system'           => [
			'name'   => 'System sans',
			'kind'   => 'sans',
			'family' => self::SYSTEM_SANS,
		],
		'system-serif'     => [
			'name'   => 'System serif',
			'kind'   => 'serif',
			'family' => self::SYSTEM_SERIF,
		],
		'inter'            => [
			'name'    => 'Inter',
			'kind'    => 'sans',
			'family'  => "'Inter Variable', " . self::SYSTEM_SANS,
			'face'    => 'Inter Variable',
			'weights' => '100 900',
		],
		'manrope'          => [
			'name'    => 'Manrope',
			'kind'    => 'sans',
			'family'  => "'Manrope Variable', " . self::SYSTEM_SANS,
			'face'    => 'Manrope Variable',
			'weights' => '200 800',
		],
		'space-grotesk'    => [
			'name'    => 'Space Grotesk',
			'kind'    => 'sans',
			'family'  => "'Space Grotesk Variable', " . self::SYSTEM_SANS,
			'face'    => 'Space Grotesk Variable',
			'weights' => '300 700',
		],
		'fraunces'         => [
			'name'    => 'Fraunces',
			'kind'    => 'serif',
			'family'  => "'Fraunces Variable', " . self::SYSTEM_SERIF,
			'face'    => 'Fraunces Variable',
			'weights' => '100 900',
		],
		'playfair-display' => [
			'name'    => 'Playfair Display',
			'kind'    => 'serif',
			'family'  => "'Playfair Display Variable', " . self::SYSTEM_SERIF,
			'face'    => 'Playfair Display Variable',
			'weights' => '400 900',
		],
		'source-serif-4'   => [
			'name'    => 'Source Serif',
			'kind'    => 'serif',
			'family'  => "'Source Serif 4 Variable', " . self::SYSTEM_SERIF,
			'face'    => 'Source Serif 4 Variable',
			'weights' => '200 900',
		],
	];

	public static function slugs(): array {
		return array_keys( self::CATALOGUE );
	}

	public static function has( string $slug ): bool {
		return isset( self::CATALOGUE[ $slug ] );
	}

	public static function get( string $slug ): array {
		$font = self::CATALOGUE[ $slug ] ?? self::CATALOGUE['system'];
		return [
			'slug'   => isset( self::CATALOGUE[ $slug ] ) ? $slug : 'system',
			'name'   => $font['name'],
			'kind'   => $font['kind'],
			'family' => $font['family'],
			'faces'  => self::faces( $slug, $font ),
		];
	}

	public static function all(): array {
		return array_map( [ self::class, 'get' ], self::slugs() );
	}

	public static function emailFamily( string $slug ): string {
		return ( self::CATALOGUE[ $slug ]['kind'] ?? 'sans' ) === 'serif' ? 'Georgia' : 'Helvetica';
	}

	public static function emailStack( string $slug ): string {
		return self::emailFamily( $slug ) === 'Georgia'
			? "Georgia, 'Times New Roman', serif"
			: "'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif";
	}

	public static function fontFaceCss( array $faces ): string {
		$css = '';
		foreach ( $faces as $face ) {
			$css .= sprintf(
				"@font-face{font-family:'%s';font-style:normal;font-display:swap;font-weight:%s;src:url(%s) format('woff2-variations');unicode-range:%s}",
				$face['fontFamily'],
				$face['fontWeight'],
				esc_url( $face['src'][0] ),
				$face['unicodeRange']
			);
		}
		return $css;
	}

	private static function faces( string $slug, array $font ): array {
		if ( ! isset( $font['face'] ) ) {
			return [];
		}
		$base = self::fontsUrl();
		return [
			[
				'fontFamily'   => $font['face'],
				'fontStyle'    => 'normal',
				'fontWeight'   => $font['weights'],
				'fontDisplay'  => 'swap',
				'src'          => [ "{$base}/{$slug}-latin-wght-normal.woff2" ],
				'unicodeRange' => 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+20B9, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD',
			],
			[
				'fontFamily'   => $font['face'],
				'fontStyle'    => 'normal',
				'fontWeight'   => $font['weights'],
				'fontDisplay'  => 'swap',
				'src'          => [ "{$base}/{$slug}-latin-ext-wght-normal.woff2" ],
				'unicodeRange' => 'U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF',
			],
		];
	}

	private static function fontsUrl(): string {
		return function_exists( 'get_theme_file_uri' ) ? get_theme_file_uri( 'assets/fonts' ) : '/assets/fonts';
	}
}
