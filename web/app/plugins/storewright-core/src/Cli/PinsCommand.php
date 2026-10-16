<?php

declare(strict_types=1);

namespace Storewright\Core\Cli;

use Storewright\Core\Delivery\PincodeDirectory;
use Storewright\Core\Delivery\Pincodes;
use Storewright\Core\Delivery\Point;
use WP_CLI;

final class PinsCommand {

	private const ALIASES = [
		'PUDUCHERRY'  => 'PY',
		'LAKSHADWEEP' => 'LD',
		'ORISSA'      => 'OR',
	];

	public function import( array $args ): void {
		$file = (string) ( $args[0] ?? '' );
		if ( ! is_readable( $file ) ) {
			WP_CLI::error( "Cannot read {$file}." );
		}
		$directory = new PincodeDirectory( self::stateCodes() );
		$pins      = $directory->build( self::offices( $file ) );
		global $wpdb;
		( new Pincodes( $wpdb ) )->replaceAll( $pins );
		$report = $directory->report;
		WP_CLI::success(
			sprintf(
				'%d PINs from %d offices; %d with a point, %d placeholder coordinates dropped, %d points rejected as far from their district, %d army post rows skipped.',
				$report['pins'],
				$report['offices'],
				$report['with_point'],
				$report['placeholders'],
				$report['off_prefix'],
				$report['army_post']
			)
		);
		if ( $report['unmatched_states'] ) {
			WP_CLI::warning( 'State names without a WooCommerce code: ' . implode( ', ', array_keys( $report['unmatched_states'] ) ) );
		}
	}

	public function find( array $args ): void {
		global $wpdb;
		self::show( ( new Pincodes( $wpdb ) )->find( (string) ( $args[0] ?? '' ) ) );
	}

	public function nearest( array $args ): void {
		global $wpdb;
		self::show( ( new Pincodes( $wpdb ) )->nearest( new Point( (float) ( $args[0] ?? 0 ), (float) ( $args[1] ?? 0 ) ) ) );
	}

	private static function show( ?array $pin ): void {
		if ( $pin === null ) {
			WP_CLI::error( 'No PIN found.' );
		}
		$pin['point'] = $pin['point'] ? [ $pin['point']->lat, $pin['point']->lng ] : null;
		WP_CLI::line( (string) wp_json_encode( $pin ) );
	}

	private static function stateCodes(): array {
		$codes = [];
		foreach ( WC()->countries->get_states( 'IN' ) as $code => $name ) {
			$codes[ PincodeDirectory::stateKey( html_entity_decode( (string) $name ) ) ] = (string) $code;
		}
		return $codes + self::ALIASES;
	}

	private static function offices( string $file ): \Generator {
		$handle  = fopen( $file, 'r' );
		$columns = array_map( static fn( $c ): string => strtolower( trim( (string) $c ) ), (array) fgetcsv( $handle, null, ',', '"', '' ) );
		for ( $row = fgetcsv( $handle, null, ',', '"', '' ); $row !== false; $row = fgetcsv( $handle, null, ',', '"', '' ) ) {
			if ( count( $row ) === count( $columns ) ) {
				yield array_combine( $columns, $row );
			}
		}
		fclose( $handle );
	}
}
