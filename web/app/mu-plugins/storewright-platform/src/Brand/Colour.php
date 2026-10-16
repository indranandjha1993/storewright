<?php

declare(strict_types=1);

namespace Storewright\Platform\Brand;

final class Colour {

	public static function isHex( string $colour ): bool {
		return (bool) preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $colour );
	}

	public static function normalise( string $colour ): string {
		$hex = ltrim( strtolower( $colour ), '#' );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return '#' . $hex;
	}

	public static function channels( string $colour ): array {
		return array_map( 'hexdec', str_split( ltrim( self::normalise( $colour ), '#' ), 2 ) );
	}

	public static function rgb( string $colour ): string {
		return implode( ',', self::channels( $colour ) );
	}

	public static function mix( string $colour, string $with, float $amount ): string {
		$a = self::channels( $colour );
		$b = self::channels( $with );
		return vsprintf( '#%02x%02x%02x', array_map( static fn( int $x, int $y ): int => (int) round( $x * ( 1 - $amount ) + $y * $amount ), $a, $b ) );
	}

	public static function darken( string $colour, float $amount ): string {
		return self::mix( $colour, '#000000', $amount );
	}

	public static function lighten( string $colour, float $amount ): string {
		return self::mix( $colour, '#ffffff', $amount );
	}

	public static function luminance( string $colour ): float {
		$linear = array_map(
			static function ( int $channel ): float {
				$c = $channel / 255;
				return $c <= 0.03928 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
			},
			self::channels( $colour )
		);
		return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
	}

	public static function contrast( string $a, string $b ): float {
		$la = self::luminance( $a );
		$lb = self::luminance( $b );
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	public static function readableOn( string $background ): string {
		return self::contrast( $background, '#ffffff' ) >= self::contrast( $background, '#15171a' ) ? '#ffffff' : '#15171a';
	}

	public static function isLight( string $colour ): bool {
		return self::luminance( $colour ) > 0.5;
	}
}
