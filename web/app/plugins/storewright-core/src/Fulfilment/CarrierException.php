<?php

declare(strict_types=1);

namespace Storewright\Core\Fulfilment;

use RuntimeException;

final class CarrierException extends RuntimeException {

	public function __construct( string $message, public readonly bool $retryable = true ) {
		// Callers escape for the sniff; the message is plain text that notes and notices escape again.
		parent::__construct( html_entity_decode( $message, ENT_QUOTES ) );
	}

	public static function http( string $message, int $code ): self {
		return new self( esc_html( $message ), $code >= 500 || $code === 429 );
	}
}
