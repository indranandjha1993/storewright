<?php

declare(strict_types=1);

namespace Storewright\Platform;

final class Mail
{
	public static function register(): void
	{
		add_filter('wp_mail_from', [self::class, 'fromAddress']);
		add_filter('wp_mail_from_name', [self::class, 'fromName']);
		if (wp_get_environment_type() !== 'production') {
			add_action('phpmailer_init', [self::class, 'captureLocally']);
		}
	}

	public static function fromAddress(string $default): string
	{
		if (str_contains($default, '@') && !str_ends_with($default, '@localhost')) {
			return $default;
		}
		$host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
		$domain = str_contains($host, '.') ? $host : 'storewright.test';
		return getenv('MAIL_FROM') ?: 'store@' . $domain;
	}

	public static function fromName(string $default): string
	{
		return $default === 'WordPress' ? get_bloginfo('name') : $default;
	}

	public static function captureLocally(\PHPMailer\PHPMailer\PHPMailer $mailer): void
	{
		$mailer->isSMTP();
		$mailer->Host = getenv('MAIL_HOST') ?: 'mail';
		$mailer->Port = (int) (getenv('MAIL_SMTP_PORT') ?: 1025);
		$mailer->SMTPAuth = false;
		$mailer->SMTPAutoTLS = false;
	}
}
