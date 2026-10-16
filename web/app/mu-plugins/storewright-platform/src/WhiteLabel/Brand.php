<?php

declare(strict_types=1);

namespace Storewright\Platform\WhiteLabel;

final class Brand {

	private const FALLBACK_ACCENT = '#0F5C4D';

	public static function name(): string {
		return (string) get_bloginfo( 'name' );
	}

	public static function logoUrl(): string {
		$id  = (int) get_option( 'site_logo' ) ?: (int) get_theme_mod( 'custom_logo' );
		$url = $id ? wp_get_attachment_image_url( $id, 'medium' ) : false;
		return $url ? $url : '';
	}

	public static function accent(): string {
		$palette = function_exists( 'wp_get_global_settings' ) ? (array) wp_get_global_settings( [ 'color', 'palette', 'theme' ] ) : [];
		foreach ( $palette as $color ) {
			if ( ( $color['slug'] ?? '' ) === 'accent' && self::isHex( (string) ( $color['color'] ?? '' ) ) ) {
				return $color['color'];
			}
		}
		return self::FALLBACK_ACCENT;
	}

	public static function isHex( string $color ): bool {
		return (bool) preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $color );
	}

	public static function darken( string $hex, float $amount ): string {
		$hex = ltrim( $hex, '#' );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$channels = array_map( static fn( string $pair ): int => (int) round( hexdec( $pair ) * ( 1 - $amount ) ), str_split( $hex, 2 ) );
		return vsprintf( '#%02x%02x%02x', $channels );
	}

	public static function rgb( string $hex ): string {
		return implode( ',', array_map( 'hexdec', str_split( ltrim( self::darken( $hex, 0 ), '#' ), 2 ) ) );
	}
}
