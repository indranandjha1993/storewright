<?php

declare(strict_types=1);

namespace Storewright\Core\Fulfilment;

use RuntimeException;

final class CarrierException extends RuntimeException {

	public function __construct( string $message, public readonly bool $retryable = true ) {
		parent::__construct( $message );
	}

	public static function http( string $message, int $code ): self {
		return new self( esc_html( $message ), $code >= 500 || $code === 429 );
	}
}
