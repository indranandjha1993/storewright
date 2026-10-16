<?php

declare(strict_types=1);

namespace Storewright\Platform\Brand;

use Storewright\Platform\WhiteLabel\Brand;

final class Emails {

	private const COLOURS = [
		'woocommerce_email_base_color'            => 'accent',
		'woocommerce_email_background_color'      => 'base',
		'woocommerce_email_body_background_color' => 'surface',
		'woocommerce_email_text_color'            => 'contrast',
		'woocommerce_email_footer_text_color'     => 'muted',
	];

	public static function register(): void {
		foreach ( self::COLOURS as $option => $key ) {
			add_filter( "pre_option_{$option}", static fn(): string => self::colour( $key ) );
		}
		add_filter( 'pre_option_woocommerce_email_font_family', static fn(): string => Fonts::emailFamily( Settings::get()['bodyFont'] ) );
		add_filter( 'pre_option_woocommerce_email_auto_sync_with_theme', static fn(): string => 'no' );
		add_filter( 'pre_option_woocommerce_email_header_image', static fn( mixed $pre ): mixed => Brand::logoUrl() ?: $pre );
	}

	public static function colour( string $key ): string {
		return Palette::from( Settings::get() )[ $key ];
	}
}
