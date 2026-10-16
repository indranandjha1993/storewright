<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Delivery;

use PHPUnit\Framework\TestCase;
use Storewright\Core\Delivery\PincodeDirectory;

final class PincodeDirectoryTest extends TestCase {

	private const STATES = [
		'KARNATAKA'   => 'KA',
		'MAHARASHTRA' => 'MH',
		'DELHI'       => 'DL',
	];

	private static function office( string $pin, string $lat, string $lng, string $district = 'BENGALURU', string $state = 'KARNATAKA' ): array {
		return [
			'pincode'   => $pin,
			'latitude'  => $lat,
			'longitude' => $lng,
			'district'  => $district,
			'statename' => $state,
		];
	}

	public function test_a_pin_takes_the_median_of_its_offices_with_district_and_state(): void {
		$directory = new PincodeDirectory( self::STATES );

		$pins = $directory->build(
			[
				self::office( '560001', '12.98', '77.59' ),
				self::office( '560001', '12.99', '77.60' ),
				self::office( '560001', '12.97', '77.58' ),
			]
		);

		self::assertSame( 12.98, $pins['560001']['point']->lat );
		self::assertSame( 77.59, $pins['560001']['point']->lng );
		self::assertSame( 'Bengaluru', $pins['560001']['district'] );
		self::assertSame( 'KA', $pins['560001']['state'] );
		self::assertSame( 3, $pins['560001']['offices'] );
	}

	public function test_a_coordinate_shared_by_many_pins_is_a_placeholder(): void {
		$offices = [];
		foreach ( [ '560001', '560002', '560003', '560004', '560005' ] as $pin ) {
			$offices[] = self::office( $pin, '19.2', '77.4' );
		}
		$offices[] = self::office( '560001', '12.98', '77.59' );

		$directory = new PincodeDirectory( self::STATES );
		$pins      = $directory->build( $offices );

		self::assertSame( 12.98, $pins['560001']['point']->lat );
		self::assertNull( $pins['560002']['point'] );
		self::assertSame( 5, $directory->report['placeholders'] );
	}

	public function test_an_office_far_from_the_rest_does_not_move_the_point(): void {
		$pins = ( new PincodeDirectory( self::STATES ) )->build(
			[
				self::office( '560001', '12.98', '77.59' ),
				self::office( '560001', '12.99', '77.59' ),
				self::office( '560001', '28.61', '77.20' ),
			]
		);

		self::assertSame( 12.985, $pins['560001']['point']->lat );
	}

	public function test_a_point_far_from_its_sorting_district_is_rejected(): void {
		$directory = new PincodeDirectory( self::STATES );

		$pins = $directory->build(
			[
				self::office( '560001', '12.98', '77.59' ),
				self::office( '560002', '12.96', '77.58' ),
				self::office( '560003', '12.99', '77.57' ),
				self::office( '560004', '28.61', '77.20' ),
			]
		);

		self::assertNull( $pins['560004']['point'] );
		self::assertSame( 1, $directory->report['off_prefix'] );
	}

	public function test_coordinates_outside_india_and_missing_values_are_ignored(): void {
		$pins = ( new PincodeDirectory( self::STATES ) )->build(
			[
				self::office( '110001', 'NA', '', 'NEW DELHI', 'DELHI' ),
				self::office( '110001', '51.5', '-0.12', 'NEW DELHI', 'DELHI' ),
			]
		);

		self::assertNull( $pins['110001']['point'] );
		self::assertSame( 'DL', $pins['110001']['state'] );
	}

	public function test_unknown_state_names_are_reported_and_bad_pins_skipped(): void {
		$directory = new PincodeDirectory( self::STATES );

		$pins = $directory->build( [ self::office( '123', '12', '77' ), self::office( '999999', '12.9', '77.6', 'X', 'ATLANTIS' ) ] );

		self::assertArrayNotHasKey( '123', $pins );
		self::assertSame( '', $pins['999999']['state'] );
		self::assertArrayHasKey( 'ATLANTIS', $directory->report['unmatched_states'] );
	}

	public function test_state_names_are_compared_without_articles_and_punctuation(): void {
		self::assertSame( 'DADRAANDNAGARHAVELIANDDAMANANDDIU', PincodeDirectory::stateKey( 'The Dadra & Nagar Haveli and Daman & Diu' ) );
		self::assertSame( 'JAMMUANDKASHMIR', PincodeDirectory::stateKey( 'JAMMU AND KASHMIR' ) );
		self::assertSame( PincodeDirectory::stateKey( 'THE DADRA AND NAGAR HAVELI AND DAMAN AND DIU' ), PincodeDirectory::stateKey( 'Dādra and Nagar Haveli and Damān and Diu' ) );
	}
}
