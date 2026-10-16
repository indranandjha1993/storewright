<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Delivery;

use Brain\Monkey\Functions;
use InvalidArgumentException;
use Storewright\Core\Delivery\AreaInput;
use Storewright\Core\Delivery\Circle;
use Storewright\Core\Delivery\Polygon;
use Storewright\Tests\TestCase;

final class AreaInputTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'sanitize_text_field' )->alias( 'strip_tags' );
	}

	public function test_a_full_area_is_read(): void {
		$input = AreaInput::from(
			[
				'name'    => ' Pune same-day ',
				'regions' => [
					[
						'type' => 'country',
						'code' => 'in',
					],
					[
						'type' => 'state',
						'code' => 'IN:MH',
					],
				],
				'pins'    => "411*\n411001-411099",
				'shape'   => [
					'kind'     => 'radius',
					'lat'      => 18.52,
					'lng'      => 73.85,
					'radius_m' => 8000,
				],
				'methods' => [
					[
						'type'  => 'flat_rate',
						'title' => 'Same-day delivery',
						'cost'  => '59',
					],
				],
			]
		);

		self::assertSame( 'Pune same-day', $input->name );
		self::assertSame( [ [ 'country', 'IN' ], [ 'state', 'IN:MH' ] ], $input->regions );
		self::assertSame( [ '411*', '411001...411099' ], $input->pins );
		self::assertInstanceOf( Circle::class, $input->shape );
		self::assertSame( 0, $input->methods[0]['id'] );
		self::assertTrue( $input->methods[0]['enabled'] );
		self::assertSame( '59', $input->methods[0]['cost'] );
	}

	public function test_a_polygon_and_no_methods(): void {
		$input = AreaInput::from(
			[
				'name'  => 'Old town',
				'shape' => [
					'kind'    => 'polygon',
					'polygon' => [ [ 18.5, 73.8 ], [ 18.6, 73.8 ], [ 18.6, 73.9 ] ],
				],
			]
		);

		self::assertInstanceOf( Polygon::class, $input->shape );
		self::assertSame( [], $input->methods );
		self::assertSame( [], $input->pins );
	}

	public function test_an_area_needs_a_name(): void {
		$this->expectExceptionMessage( 'Give the area a name.' );

		AreaInput::from( [ 'name' => '  ' ] );
	}

	public function test_an_unknown_region_type_is_refused(): void {
		$this->expectException( InvalidArgumentException::class );

		AreaInput::from(
			[
				'name'    => 'Somewhere',
				'regions' => [
					[
						'type' => 'city',
						'code' => 'Pune',
					],
				],
			]
		);
	}

	public function test_a_shape_that_cannot_be_read_is_refused(): void {
		$this->expectExceptionMessage( 'The shape on the map could not be read. Draw it again.' );

		AreaInput::from(
			[
				'name'  => 'Broken',
				'shape' => [
					'kind'    => 'polygon',
					'polygon' => [ 'a', 'b', 'c' ],
				],
			]
		);
	}

	public function test_a_polygon_with_two_corners_is_refused(): void {
		$this->expectExceptionMessage( 'A delivery polygon needs at least three corners.' );

		AreaInput::from(
			[
				'name'  => 'Line',
				'shape' => [
					'kind'    => 'polygon',
					'polygon' => [ [ 18.5, 73.8 ], [ 18.6, 73.8 ] ],
				],
			]
		);
	}

	public function test_a_new_method_must_be_a_known_kind_with_a_name(): void {
		$this->expectExceptionMessage( 'A new delivery method is either a delivery charge or free delivery.' );

		AreaInput::from(
			[
				'name'    => 'Pune',
				'methods' => [ [ 'type' => 'local_pickup' ] ],
			]
		);
	}

	public function test_a_charge_must_be_a_positive_number(): void {
		$this->expectExceptionMessage( 'The amount for Same-day must be a number such as 49, or left empty.' );

		AreaInput::from(
			[
				'name'    => 'Pune',
				'methods' => [
					[
						'type'  => 'flat_rate',
						'title' => 'Same-day',
						'cost'  => '-5',
					],
				],
			]
		);
	}

	public function test_an_existing_method_keeps_its_id_and_state(): void {
		$input = AreaInput::from(
			[
				'name'    => 'India',
				'methods' => [
					[
						'id'      => '7',
						'enabled' => false,
					],
				],
			]
		);

		self::assertSame( 7, $input->methods[0]['id'] );
		self::assertFalse( $input->methods[0]['enabled'] );
	}
}
