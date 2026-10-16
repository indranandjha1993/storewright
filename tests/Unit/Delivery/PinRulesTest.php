<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Delivery;

use Brain\Monkey\Functions;
use InvalidArgumentException;
use Storewright\Core\Delivery\PinRules;
use Storewright\Tests\TestCase;

final class PinRulesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
	}

	public function test_one_rule_per_line_or_comma(): void {
		self::assertSame( [ '560001', '560*', '411001' ], PinRules::parse( " 560001\n560*, 411001\r\n\n" ) );
	}

	public function test_a_hyphen_between_equal_codes_is_a_range(): void {
		self::assertSame( [ '560001...560099', '110001...110010' ], PinRules::parse( "560001 - 560099\n110001...110010" ) );
	}

	public function test_a_hyphenated_postcode_stays_a_postcode(): void {
		self::assertSame( [ '100-0001' ], PinRules::parse( '100-0001' ) );
	}

	public function test_repeats_are_dropped_and_letters_upper_cased(): void {
		self::assertSame( [ 'SW1A*', '560001' ], PinRules::parse( "sw1a*\n560001\n560001" ) );
	}

	public function test_a_backwards_range_is_refused(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'The range 560099...560001 needs two codes of the same length, the lower one first.' );

		PinRules::parse( '560099...560001' );
	}

	public function test_a_range_of_unequal_codes_is_refused(): void {
		$this->expectException( InvalidArgumentException::class );

		PinRules::parse( '56000...560099' );
	}

	public function test_anything_else_is_refused_by_name(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( '560*01 is not a PIN code' );

		PinRules::parse( "560001\n560*01" );
	}

	public function test_nothing_typed_is_no_rules(): void {
		self::assertSame( [], PinRules::parse( " \n " ) );
	}
}
