<?php

declare(strict_types=1);

namespace Storewright\Platform;

final class HttpCertificates {

	public static function register(): void {
		add_action( 'plugins_loaded', [ self::class, 'useWordPressBundle' ], 0 );
	}

	public static function useWordPressBundle(): void {
		$bundle = ABSPATH . 'wp-includes/certificates/ca-bundle.crt';
		if ( class_exists( \WpOrg\Requests\Requests::class ) && is_readable( $bundle ) ) {
			\WpOrg\Requests\Requests::set_certificate_path( $bundle );
		}
	}
}
