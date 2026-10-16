<?php

declare(strict_types=1);

require dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'Storewright\Platform\VERSION' ) ) {
	define( 'Storewright\Platform\VERSION', '0.0.0-test' );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! class_exists( 'WP_Error' ) ) {
	final class WP_Error {

		public function __construct( public string $code = '', public string $message = '', public mixed $data = null ) {}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}
