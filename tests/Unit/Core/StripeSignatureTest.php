<?php

declare(strict_types=1);

namespace Storewright\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Storewright\Core\Payments\StripeSignature;

final class StripeSignatureTest extends TestCase {

	private const SECRET = 'whsec_unit';
	private const BODY   = '{"id":"evt_1","type":"charge.succeeded"}';

	private function header( int $timestamp, string $secret = self::SECRET, string $body = self::BODY ): string {
		return 't=' . $timestamp . ',v1=' . hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
	}

	public function test_a_correctly_signed_payload_is_authentic(): void {
		self::assertTrue( ( new StripeSignature() )->verify( self::BODY, $this->header( 1000 ), self::SECRET, 1000 ) );
	}

	public function test_any_matching_v1_signature_is_accepted_during_secret_rotation(): void {
		$header = 't=1000,v1=' . str_repeat( '0', 64 ) . ',' . substr( $this->header( 1000 ), 7 );

		self::assertTrue( ( new StripeSignature() )->verify( self::BODY, $header, self::SECRET, 1000 ) );
	}

	public function test_a_tampered_body_is_rejected(): void {
		self::assertFalse( ( new StripeSignature() )->verify( '{"id":"evt_2"}', $this->header( 1000 ), self::SECRET, 1000 ) );
	}

	public function test_the_wrong_secret_is_rejected(): void {
		self::assertFalse( ( new StripeSignature() )->verify( self::BODY, $this->header( 1000, 'whsec_other' ), self::SECRET, 1000 ) );
	}

	public function test_a_replay_outside_the_tolerance_is_rejected(): void {
		self::assertFalse( ( new StripeSignature( 300 ) )->verify( self::BODY, $this->header( 1000 ), self::SECRET, 1301 ) );
		self::assertTrue( ( new StripeSignature( 300 ) )->verify( self::BODY, $this->header( 1000 ), self::SECRET, 1300 ) );
	}

	public function test_missing_secret_or_header_is_never_authentic(): void {
		$signature = new StripeSignature();

		self::assertFalse( $signature->verify( self::BODY, $this->header( 1000 ), '', 1000 ) );
		self::assertFalse( $signature->verify( self::BODY, '', self::SECRET, 1000 ) );
		self::assertFalse( $signature->verify( self::BODY, 'v1=abc', self::SECRET, 1000 ) );
	}
}
