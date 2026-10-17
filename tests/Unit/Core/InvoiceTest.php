<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Core;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Storewright\Core\Invoice\Gst;
use Storewright\Core\Invoice\InvoiceNumber;
use Storewright\Core\Invoice\StateCodes;
use Storewright\Core\Invoice\Words;

final class InvoiceTest extends TestCase {

	public function test_the_financial_year_turns_over_in_april(): void {
		self::assertSame( '25-26', InvoiceNumber::financialYear( new DateTimeImmutable( '2026-03-31' ) ) );
		self::assertSame( '26-27', InvoiceNumber::financialYear( new DateTimeImmutable( '2026-04-01' ) ) );
		self::assertSame( '99-00', InvoiceNumber::financialYear( new DateTimeImmutable( '2099-12-01' ) ) );
	}

	public function test_numbers_stay_within_sixteen_characters(): void {
		$number = InvoiceNumber::format( 'INV', '26-27', 42 );

		self::assertSame( 'INV/26-27/0042', $number );
		self::assertLessThanOrEqual( 16, strlen( InvoiceNumber::format( 'ABCD', '26-27', 99999 ) ) );
	}

	public function test_gst_inside_the_state_is_split_between_centre_and_state(): void {
		$split = Gst::split( 449.0, 12, true );

		self::assertSame( 400.89, $split['taxable'] );
		self::assertSame( 48.11, round( $split['cgst'] + $split['sgst'], 2 ) );
		self::assertSame( 449.0, round( $split['taxable'] + $split['cgst'] + $split['sgst'], 2 ) );
		self::assertSame( 0.0, $split['igst'] );
	}

	public function test_gst_across_states_is_integrated(): void {
		$split = Gst::split( 1180.0, 18, false );

		self::assertSame( 1000.0, $split['taxable'] );
		self::assertSame( 180.0, $split['igst'] );
		self::assertSame( 0.0, $split['cgst'] );
	}

	public function test_amounts_are_written_the_indian_way(): void {
		self::assertSame( 'Rupees One Thousand Two Hundred Forty Seven Only', Words::rupees( 1247 ) );
		self::assertSame( 'Rupees Twelve Lakh Thirty Four Thousand Five Hundred Sixty Seven and Fifty Paise Only', Words::rupees( 1234567.5 ) );
		self::assertSame( 'Rupees One Hundred Five Crore Only', Words::rupees( 1050000000 ) );
		self::assertSame( 'Rupees Zero Only', Words::rupees( 0 ) );
	}

	public function test_state_codes_follow_the_gstin_prefix(): void {
		self::assertSame( '29', StateCodes::gst( 'KA' ) );
		self::assertSame( '27', StateCodes::gst( 'mh' ) );
		self::assertSame( '37', StateCodes::gst( 'AP' ) );
		self::assertSame( '', StateCodes::gst( 'XX' ) );
	}
}
