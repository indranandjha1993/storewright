<?php

declare(strict_types=1);

namespace Storewright\Platform\Brand;

final class ThemeJson {

	private const SLUGS = [
		'base'         => 'base',
		'surface'      => 'surface',
		'contrast'     => 'contrast',
		'muted'        => 'muted',
		'line'         => 'line',
		'accent'       => 'accent',
		'accent-hover' => 'accentHover',
		'accent-soft'  => 'accentSoft',
		'on-accent'    => 'onAccent',
		'sale'         => 'sale',
		'on-sale'      => 'onSale',
	];

	public static function register(): void {
		add_filter( 'wp_theme_json_data_theme', [ self::class, 'apply' ] );
	}

	public static function apply( \WP_Theme_JSON_Data $theme ): \WP_Theme_JSON_Data {
		return $theme->update_with( self::data( Settings::get() ) );
	}

	public static function data( array $settings ): array {
		$palette = Palette::from( $settings );
		$radii   = Palette::radii( $settings['radius'] );
		return [
			'version'  => 3,
			'settings' => [
				'color'      => [ 'palette' => self::palette( $palette ) ],
				'typography' => [ 'fontFamilies' => self::families( $settings ) ],
				'custom'     => [
					'radius'       => $radii['radius'],
					'radiusSmall'  => $radii['small'],
					'radiusButton' => $radii['button'],
					'shadow'       => $palette['shadow'],
				],
			],
			'styles'   => [
				'elements' => [
					'heading' => [ 'typography' => [ 'fontFamily' => 'var(--wp--preset--font-family--heading)' ] ],
					'button'  => [ 'border' => [ 'radius' => 'var(--wp--custom--radius-button)' ] ],
				],
			],
		];
	}

	public static function refresh(): void {
		if ( class_exists( \WP_Theme_JSON_Resolver::class ) ) {
			\WP_Theme_JSON_Resolver::clean_cached_data();
		}
		wp_cache_delete( 'wp_get_global_stylesheet', 'theme_json' );
		wp_cache_delete( 'wp_get_global_styles_svg_filters', 'theme_json' );
	}

	private static function palette( array $palette ): array {
		$names = [
			'base'         => __( 'Base', 'storewright' ),
			'surface'      => __( 'Surface', 'storewright' ),
			'contrast'     => __( 'Contrast', 'storewright' ),
			'muted'        => __( 'Muted', 'storewright' ),
			'line'         => __( 'Line', 'storewright' ),
			'accent'       => __( 'Accent', 'storewright' ),
			'accent-hover' => __( 'Accent hover', 'storewright' ),
			'accent-soft'  => __( 'Accent soft', 'storewright' ),
			'on-accent'    => __( 'On accent', 'storewright' ),
			'sale'         => __( 'Sale', 'storewright' ),
			'on-sale'      => __( 'On sale', 'storewright' ),
		];
		$out   = [];
		foreach ( self::SLUGS as $slug => $key ) {
			$out[] = [
				'slug'  => $slug,
				'name'  => $names[ $slug ],
				'color' => $palette[ $key ],
			];
		}
		return $out;
	}

	private static function families( array $settings ): array {
		$body    = Fonts::get( $settings['bodyFont'] );
		$heading = Fonts::get( $settings['headingFont'] );
		return [
			self::family( 'sans', __( 'Body', 'storewright' ), $body, $body['faces'] ),
			self::family( 'heading', __( 'Headings', 'storewright' ), $heading, $heading['slug'] === $body['slug'] ? [] : $heading['faces'] ),
			[
				'slug'       => 'mono',
				'name'       => __( 'Mono', 'storewright' ),
				'fontFamily' => 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
			],
		];
	}

	private static function family( string $slug, string $name, array $font, array $faces ): array {
		$family = [
			'slug'       => $slug,
			'name'       => $name,
			'fontFamily' => $font['family'],
		];
		if ( $faces !== [] ) {
			$family['fontFace'] = $faces;
		}
		return $family;
	}
}
