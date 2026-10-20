<?php

declare(strict_types=1);

namespace Storewright\Core\Fulfilment;

interface Carrier {

	public function key(): string;

	/** @return list<Quote> */
	public function quotes( Consignment $consignment ): array;

	public function create( Consignment $consignment ): Booking;

	public function assign( Booking $booking, string $service ): Booking;

	public function pickup( Booking $booking ): string;

	public function label( Booking $booking ): string;

	public function cancel( Booking $booking ): void;

	/** @return list<TrackingEvent> */
	public function track( Booking $booking ): array;
}
