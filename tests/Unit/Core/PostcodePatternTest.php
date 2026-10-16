<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Storewright\Core\Delivery\PostcodePattern;

final class PostcodePatternTest extends TestCase {

	public function test_an_exact_pin_matches_itself_only(): void {
		self::assertTrue( ( new PostcodePattern( '560001' ) )->matches( '560001' ) );
		self::assertFalse( ( new PostcodePattern( '560001' ) )->matches( '560002' ) );
	}

	public function test_spaces_are_ignored(): void {
		self::assertTrue( ( new PostcodePattern( '560 001' ) )->matches( ' 560001 ' ) );
	}

	public function test_a_wildcard_matches_the_prefix(): void {
		$pattern = new PostcodePattern( '560*' );

		self::assertTrue( $pattern->matches( '560103' ) );
		self::assertFalse( $pattern->matches( '561003' ) );
	}

	public function test_a_range_is_inclusive_and_numeric(): void {
		$range = new PostcodePattern( '110001...110099' );

		self::assertTrue( $range->matches( '110001' ) );
		self::assertTrue( $range->matches( '110099' ) );
		self::assertFalse( $range->matches( '110100' ) );
		self::assertFalse( $range->matches( '11005' ) );
		self::assertFalse( $range->matches( 'ABC123' ) );
	}

	public function test_empty_values_never_match(): void {
		self::assertFalse( ( new PostcodePattern( '' ) )->matches( '560001' ) );
		self::assertFalse( ( new PostcodePattern( '560*' ) )->matches( '' ) );
	}

	public function test_any_of_a_list(): void {
		self::assertTrue( PostcodePattern::anyMatches( '411001', [ '560*', '411001' ] ) );
		self::assertFalse( PostcodePattern::anyMatches( '400001', [ '560*', '411001' ] ) );
	}
}
