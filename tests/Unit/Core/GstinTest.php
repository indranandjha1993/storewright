<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Storewright\Core\Checkout\Gstin;

final class GstinTest extends TestCase {

	public function test_spaces_and_case_are_normalised(): void {
		self::assertSame( '27AAPFU0939F1ZV', Gstin::normalise( ' 27aapfu0939f1zv ' ) );
	}

	public function test_a_well_formed_number_with_the_right_check_digit_passes(): void {
		self::assertTrue( Gstin::isValid( '27AAPFU0939F1ZV' ) );
		self::assertTrue( Gstin::isValid( '07aabcu9603r1zp' ) );
	}

	public function test_a_wrong_check_digit_or_shape_fails(): void {
		self::assertFalse( Gstin::isValid( '27AAPFU0939F1Z5' ) );
		self::assertFalse( Gstin::isValid( '27AAPFU0939F1ZV1' ) );
		self::assertFalse( Gstin::isValid( '99AAPFU0939F1ZV' ) );
		self::assertFalse( Gstin::isValid( 'GSTIN' ) );
		self::assertFalse( Gstin::isValid( '' ) );
	}
}
