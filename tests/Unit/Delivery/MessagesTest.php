<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Delivery;

use Brain\Monkey\Functions;
use Storewright\Core\Delivery\Answer;
use Storewright\Core\Delivery\Messages;
use Storewright\Core\Delivery\Zone;
use Storewright\Tests\TestCase;

final class MessagesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
	}

	private static function india(): Zone {
		return new Zone( 1, 'India', [ [ 'country', 'IN' ] ], [], 1 );
	}

	public function test_a_deliverable_pin_names_the_place(): void {
		self::assertSame( 'We deliver to 560001, Bengaluru Urban.', Messages::forShopper( 'found', '560001', 'Bengaluru Urban', Answer::deliverable( self::india() ) ) );
	}

	public function test_an_approximate_answer_asks_for_the_location(): void {
		self::assertSame( 'We probably deliver to 560066, Bengaluru Urban. Use your location to be sure.', Messages::forShopper( 'found', '560066', 'Bengaluru Urban', Answer::approximate( self::india() ) ) );
	}

	public function test_an_undeliverable_pin_says_what_to_try(): void {
		self::assertSame( 'We do not deliver to 403001, North Goa yet. Try another PIN code or use your location.', Messages::forShopper( 'found', '403001', 'North Goa', Answer::undeliverable( Answer::NO_AREA ) ) );
	}

	public function test_an_unknown_pin_and_an_unplaced_location_read_differently(): void {
		self::assertSame( 'We could not find PIN 100000. Check the number and try again.', Messages::forShopper( 'unknown', '100000', '', null ) );
		self::assertSame( 'We could not place your location. Enter your PIN code instead.', Messages::forShopper( 'unknown', '', '', null ) );
	}

	public function test_a_malformed_pin_asks_for_six_digits(): void {
		self::assertSame( 'Enter the 6 digit PIN code of the delivery address.', Messages::forShopper( 'invalid', '12', '', null ) );
	}
}
