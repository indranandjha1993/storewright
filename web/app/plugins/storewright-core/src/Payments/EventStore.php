<?php

declare(strict_types=1);

namespace Storewright\Core\Payments;

interface EventStore {

	public function status( string $gateway, string $eventId ): ?string;

	public function receive( string $gateway, string $eventId, string $eventType, string $payloadHash ): void;

	public function mark( string $gateway, string $eventId, string $status ): void;
}
