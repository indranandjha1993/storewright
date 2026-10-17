<?php

declare(strict_types=1);

namespace Storewright\Platform;

final class Mail {

	public static function register(): void {
		add_filter( 'wp_mail_from', [ self::class, 'fromAddress' ] );
		add_filter( 'wp_mail_from_name', [ self::class, 'fromName' ] );
		if ( wp_get_environment_type() !== 'production' ) {
			add_action( 'phpmailer_init', [ self::class, 'captureLocally' ] );
		} elseif ( getenv( 'MAIL_HOST' ) ) {
			add_action( 'phpmailer_init', [ self::class, 'smtp' ] );
		}
	}

	public static function smtp( \PHPMailer\PHPMailer\PHPMailer $mailer ): void {
		$mailer->isSMTP();
		$mailer->Host       = (string) getenv( 'MAIL_HOST' );
		$mailer->Port       = (int) ( getenv( 'MAIL_SMTP_PORT' ) ?: 587 );
		$mailer->SMTPSecure = getenv( 'MAIL_ENCRYPTION' ) === 'ssl' ? 'ssl' : 'tls';
		$mailer->SMTPAuth   = (string) getenv( 'MAIL_USER' ) !== '';
		$mailer->Username   = (string) getenv( 'MAIL_USER' );
		$mailer->Password   = (string) getenv( 'MAIL_PASSWORD' );
	}

	public static function fromAddress( string $address ): string {
		if ( str_contains( $address, '@' ) && ! str_ends_with( $address, '@localhost' ) ) {
			return $address;
		}
		$host   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$domain = str_contains( $host, '.' ) ? $host : 'storewright.test';
		return getenv( 'MAIL_FROM' ) ?: 'store@' . $domain;
	}

	public static function fromName( string $name ): string {
		return $name === 'WordPress' ? get_bloginfo( 'name' ) : $name;
	}

	public static function captureLocally( \PHPMailer\PHPMailer\PHPMailer $mailer ): void {
		$mailer->isSMTP();
		$mailer->Host        = getenv( 'MAIL_HOST' ) ?: 'mail';
		$mailer->Port        = (int) ( getenv( 'MAIL_SMTP_PORT' ) ?: 1025 );
		$mailer->SMTPAuth    = false;
		$mailer->SMTPAutoTLS = false;
	}
}
