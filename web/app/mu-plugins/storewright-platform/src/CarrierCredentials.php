<?php

declare(strict_types=1);

namespace Storewright\Platform;

final class CarrierCredentials {

	public const SANDBOX = 'sandbox';
	public const LIVE    = 'live';
	public const REFUSED = 'refused';
	public const OFF     = 'off';

	private const SHIPROCKET_LIVE = 'https://apiv2.shiprocket.in';

	public static function modes( string $environment ): array {
		return [ 'shiprocket' => self::status( self::shiprocketMode(), $environment ) ];
	}

	public static function shiprocket( string $environment ): ?array {
		$mode = self::shiprocketMode();
		if ( $mode === null || ! self::allowed( $mode, $environment ) ) {
			return null;
		}
		return [
			'mode'     => $mode,
			'host'     => self::shiprocketHost(),
			'email'    => self::env( 'SHIPROCKET_EMAIL' ),
			'password' => self::env( 'SHIPROCKET_PASSWORD' ),
		];
	}

	public static function shiprocketWebhookToken( string $environment ): string {
		return self::shiprocket( $environment ) === null ? '' : self::env( 'SHIPROCKET_WEBHOOK_TOKEN' );
	}

	public static function shiprocketMode(): ?string {
		if ( self::env( 'SHIPROCKET_EMAIL' ) === '' || self::env( 'SHIPROCKET_PASSWORD' ) === '' ) {
			return null;
		}
		return self::modeOfHost( self::shiprocketHost() );
	}

	public static function modeOfHost( string $host ): string {
		return str_contains( strtolower( $host ), 'sandbox' ) ? self::SANDBOX : self::LIVE;
	}

	public static function shiprocketHost(): string {
		$host = rtrim( self::env( 'SHIPROCKET_API_URL' ), '/' );
		return $host === '' ? self::SHIPROCKET_LIVE : $host;
	}

	public static function status( ?string $mode, string $environment ): string {
		if ( $mode === null ) {
			return self::OFF;
		}
		return self::allowed( $mode, $environment ) ? $mode : self::REFUSED;
	}

	private static function allowed( string $mode, string $environment ): bool {
		return $mode === self::SANDBOX || $environment === 'production';
	}

	private static function env( string $name ): string {
		return trim( (string) getenv( $name ) );
	}
}
