<?php

declare(strict_types=1);

namespace Storewright\Core\Payments;

final class WebhookGuard {

	public const RECEIVED  = 'received';
	public const PROCESSED = 'processed';
	public const FAILED    = 'failed';

	public const PASS      = 'pass';
	public const DUPLICATE = 'duplicate';
	public const RECORDED  = 'recorded';

	public function __construct( private readonly EventStore $store ) {}

	public function admit( string $gateway, string $payload, bool $authentic ): string {
		if ( ! $authentic ) {
			return self::PASS;
		}
		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) || ! is_string( $event['id'] ?? null ) || $event['id'] === '' ) {
			return self::PASS;
		}
		$id   = $event['id'];
		$type = is_string( $event['type'] ?? null ) ? $event['type'] : (string) ( $event['event'] ?? 'unknown' );

		$known = $this->store->status( $gateway, $id );
		$this->store->receive( $gateway, $id, $type, hash( 'sha256', $payload ) );

		return $known === self::PROCESSED ? self::DUPLICATE : self::RECORDED;
	}

	public function settle( string $gateway, string $payload, int $httpStatus ): void {
		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) || ! is_string( $event['id'] ?? null ) ) {
			return;
		}
		$this->store->mark( $gateway, $event['id'], $httpStatus >= 200 && $httpStatus < 300 ? self::PROCESSED : self::FAILED );
	}
}
