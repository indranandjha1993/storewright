<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

final class PincodeDirectory {

	private const PLACEHOLDER_PINS = 5;
	private const OFFICE_TRIM_KM   = 50;
	private const PREFIX_LIMIT_KM  = 200;
	private const INDIA            = [ 6.0, 37.6, 68.0, 97.5 ];

	public array $report = [
		'offices'          => 0,
		'pins'             => 0,
		'with_point'       => 0,
		'placeholders'     => 0,
		'off_prefix'       => 0,
		'unmatched_states' => [],
	];

	public function __construct( private readonly array $stateCodes ) {}

	public function build( iterable $offices ): array {
		$byPin  = [];
		$shared = [];
		foreach ( $offices as $office ) {
			$pin = preg_replace( '/\D/', '', (string) ( $office['pincode'] ?? '' ) );
			if ( strlen( $pin ) !== 6 ) {
				continue;
			}
			++$this->report['offices'];
			$point           = self::point( $office['latitude'] ?? '', $office['longitude'] ?? '' );
			$byPin[ $pin ][] = [ 'point' => $point ] + $office;
			if ( $point ) {
				$shared[ self::key( $point ) ][ $pin ] = true;
			}
		}

		$pins = [];
		foreach ( $byPin as $pin => $rows ) {
			$points = [];
			foreach ( $rows as $row ) {
				if ( $row['point'] && count( $shared[ self::key( $row['point'] ) ] ) >= self::PLACEHOLDER_PINS ) {
					++$this->report['placeholders'];
					continue;
				}
				if ( $row['point'] ) {
					$points[] = $row['point'];
				}
			}
			$pins[ $pin ] = [
				'pin'      => (string) $pin,
				'district' => self::titleCase( self::mostCommon( array_column( $rows, 'district' ) ) ),
				'state'    => $this->stateCode( self::mostCommon( array_column( $rows, 'statename' ) ) ),
				'point'    => self::trimmedMedian( $points ),
				'offices'  => count( $rows ),
			];
		}

		$this->dropOffPrefix( $pins );
		$this->report['pins']       = count( $pins );
		$this->report['with_point'] = count( array_filter( $pins, static fn( array $p ): bool => $p['point'] !== null ) );
		return $pins;
	}

	private function dropOffPrefix( array &$pins ): void {
		$byPrefix = [];
		foreach ( $pins as $pin => $row ) {
			if ( $row['point'] ) {
				$byPrefix[ substr( (string) $pin, 0, 3 ) ][] = $row['point'];
			}
		}
		$centres = array_map( self::median( ... ), $byPrefix );
		foreach ( $pins as $pin => &$row ) {
			$centre = $centres[ substr( (string) $pin, 0, 3 ) ] ?? null;
			if ( $row['point'] && $centre && $row['point']->metresTo( $centre ) > self::PREFIX_LIMIT_KM * 1000 ) {
				$row['point'] = null;
				++$this->report['off_prefix'];
			}
		}
	}

	private static function trimmedMedian( array $points ): ?Point {
		$centre = self::median( $points );
		if ( $centre === null ) {
			return null;
		}
		$near = array_filter( $points, static fn( Point $p ): bool => $p->metresTo( $centre ) <= self::OFFICE_TRIM_KM * 1000 );
		return self::median( array_values( $near ) ) ?? $centre;
	}

	private static function median( array $points ): ?Point {
		if ( $points === [] ) {
			return null;
		}
		$lats = array_map( static fn( Point $p ): float => $p->lat, $points );
		$lngs = array_map( static fn( Point $p ): float => $p->lng, $points );
		return new Point( self::middle( $lats ), self::middle( $lngs ) );
	}

	private static function middle( array $values ): float {
		sort( $values );
		$count = count( $values );
		$half  = intdiv( $count, 2 );
		return $count % 2 ? $values[ $half ] : ( $values[ $half - 1 ] + $values[ $half ] ) / 2;
	}

	private static function point( mixed $lat, mixed $lng ): ?Point {
		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			return null;
		}
		[ $south, $north, $west, $east ] = self::INDIA;
		$lat                             = (float) $lat;
		$lng                             = (float) $lng;
		return $lat >= $south && $lat <= $north && $lng >= $west && $lng <= $east ? new Point( $lat, $lng ) : null;
	}

	private static function key( Point $point ): string {
		return round( $point->lat, 5 ) . ',' . round( $point->lng, 5 );
	}

	private static function mostCommon( array $values ): string {
		$counts = array_count_values( array_filter( array_map( static fn( $v ): string => trim( (string) $v ), $values ) ) );
		arsort( $counts );
		return (string) array_key_first( $counts );
	}

	private static function titleCase( string $value ): string {
		return ucwords( strtolower( $value ) );
	}

	private function stateCode( string $name ): string {
		$key = self::stateKey( $name );
		if ( isset( $this->stateCodes[ $key ] ) ) {
			return $this->stateCodes[ $key ];
		}
		if ( $name !== '' ) {
			$this->report['unmatched_states'][ $name ] = true;
		}
		return '';
	}

	public static function stateKey( string $name ): string {
		$name = (string) preg_replace( '/\p{Mn}/u', '', (string) \Normalizer::normalize( $name, \Normalizer::FORM_D ) );
		$name = strtoupper( str_replace( '&', ' AND ', $name ) );
		$name = (string) preg_replace( '/^THE\s+/', '', $name );
		return (string) preg_replace( '/[^A-Z]/', '', $name );
	}
}
