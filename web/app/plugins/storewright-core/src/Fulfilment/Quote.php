<?php

declare(strict_types=1);

namespace Storewright\Core\Fulfilment;

final class Quote {

	public function __construct(
		public readonly string $service,
		public readonly string $name,
		public readonly float $rate,
		public readonly string $arrives,
		public readonly bool $recommended = false
	) {}
}
