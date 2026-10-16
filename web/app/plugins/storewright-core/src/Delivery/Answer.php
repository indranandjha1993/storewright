<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

final class Answer {

	public const DELIVERABLE   = 'deliverable';
	public const UNDELIVERABLE = 'undeliverable';
	public const APPROXIMATE   = 'approximate';

	public const NO_AREA      = 'no_area';
	public const NO_METHODS   = 'no_methods';
	public const OUTSIDE_AREA = 'outside_area';
	public const NEEDS_POINT  = 'needs_location';
	public const COVERED      = 'covered';

	private function __construct(
		public readonly string $status,
		public readonly string $reason,
		public readonly ?Zone $zone,
	) {}

	public static function deliverable( Zone $zone ): self {
		return new self( self::DELIVERABLE, self::COVERED, $zone );
	}

	public static function approximate( Zone $zone ): self {
		return new self( self::APPROXIMATE, self::NEEDS_POINT, $zone );
	}

	public static function undeliverable( string $reason, ?Zone $zone = null ): self {
		return new self( self::UNDELIVERABLE, $reason, $zone );
	}

	public function isDeliverable(): bool {
		return $this->status === self::DELIVERABLE;
	}

	public function toArray(): array {
		return [
			'status' => $this->status,
			'reason' => $this->reason,
			'area'   => $this->zone ? [
				'id'   => $this->zone->id,
				'name' => $this->zone->name,
			] : null,
		];
	}
}
