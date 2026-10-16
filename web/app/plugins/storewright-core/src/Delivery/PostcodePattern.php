<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

final class PostcodePattern {

	public function __construct( private readonly string $pattern ) {}

	public static function anyMatches( string $postcode, array $patterns ): bool {
		foreach ( $patterns as $pattern ) {
			if ( ( new self( (string) $pattern ) )->matches( $postcode ) ) {
				return true;
			}
		}
		return false;
	}

	public function matches( string $postcode ): bool {
		$postcode = self::normalise( $postcode );
		$pattern  = self::normalise( $this->pattern );
		if ( $postcode === '' || $pattern === '' ) {
			return false;
		}
		if ( str_contains( $pattern, '...' ) ) {
			[ $from, $to ] = array_map( self::normalise( ... ), explode( '...', $pattern, 2 ) );
			return ctype_digit( $postcode ) && ctype_digit( $from ) && ctype_digit( $to )
				&& strlen( $postcode ) === strlen( $from ) && $postcode >= $from && $postcode <= $to;
		}
		if ( str_ends_with( $pattern, '*' ) ) {
			return str_starts_with( $postcode, rtrim( $pattern, '*' ) );
		}
		return $postcode === $pattern;
	}

	private static function normalise( string $value ): string {
		return strtoupper( (string) preg_replace( '/\s+/', '', $value ) );
	}
}
