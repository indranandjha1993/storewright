<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

use InvalidArgumentException;

final class PinRules {

	private const CODE = '[A-Z0-9][A-Z0-9 -]*';

	public static function parse( string $text ): array {
		$rules = [];
		foreach ( preg_split( '/[\r\n,]+/', strtoupper( $text ) ) ?: [] as $line ) {
			$line = trim( $line );
			if ( $line === '' ) {
				continue;
			}
			$rules[] = self::rule( $line );
		}
		return array_values( array_unique( $rules ) );
	}

	private static function rule( string $line ): string {
		if ( preg_match( '/^(\d+)\s*(\.\.\.|-)\s*(\d+)$/', $line, $range ) && ( $range[2] === '...' || strlen( $range[1] ) === strlen( $range[3] ) ) ) {
			return self::range( $line, $range[1], $range[3] );
		}
		if ( preg_match( '/^' . self::CODE . '\*?$/', $line ) ) {
			return $line;
		}
		/* translators: %s: the rule as typed */
		throw new InvalidArgumentException( esc_html( sprintf( __( '%s is not a PIN code, a prefix such as 560* or a range such as 560001...560099.', 'storewright' ), $line ) ) );
	}

	private static function range( string $line, string $from, string $to ): string {
		if ( strlen( $from ) !== strlen( $to ) || $from > $to ) {
			/* translators: %s: the range as typed */
			throw new InvalidArgumentException( esc_html( sprintf( __( 'The range %s needs two codes of the same length, the lower one first.', 'storewright' ), $line ) ) );
		}
		return "{$from}...{$to}";
	}
}
