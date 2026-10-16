<?php

declare(strict_types=1);

namespace Storewright\Core\Delivery;

interface Shape {

	public function contains( Point $point ): bool;

	public function toArray(): array;
}
