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

	public function admit( string $gateway, string $eventId, string $eventType, string $payload, bool $authentic ): string {
		if ( ! $authentic || $eventId === '' ) {
			return self::PASS;
		}
		$known = $this->store->status( $gateway, $eventId );
		$this->store->receive( $gateway, $eventId, $eventType !== '' ? $eventType : 'unknown', hash( 'sha256', $payload ) );
		return $known === self::PROCESSED ? self::DUPLICATE : self::RECORDED;
	}

	public function settle( string $gateway, string $eventId, int $httpStatus ): void {
		$this->store->mark( $gateway, $eventId, $httpStatus >= 200 && $httpStatus < 300 ? self::PROCESSED : self::FAILED );
	}
}
