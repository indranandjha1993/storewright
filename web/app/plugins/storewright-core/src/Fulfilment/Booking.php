<?php

declare(strict_types=1);

namespace Storewright\Core\Fulfilment;

final class Booking {

	public function __construct(
		public readonly string $reference,
		public readonly string $shipment,
		public readonly string $awb = '',
		public readonly string $courier = '',
		public readonly string $service = '',
		public readonly string $trackingUrl = '',
		public readonly bool $reverse = false
	) {}

	public function with( string $awb, string $courier, string $service, string $trackingUrl ): self {
		return new self( $this->reference, $this->shipment, $awb, $courier, $service, $trackingUrl, $this->reverse );
	}

	public function booked(): bool {
		return $this->awb !== '';
	}
}
