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
		add_filter( 'woocommerce_email_styles', [ self::class, 'styles' ], 20 );
		add_filter( 'woocommerce_email_settings', [ self::class, 'fields' ] );
	}

	public static function fields( array $settings ): array {
		$decided = [ ...array_keys( self::COLOURS ), 'woocommerce_email_font_family', 'woocommerce_email_auto_sync_with_theme', 'woocommerce_email_header_image' ];
		$kept    = [];
		foreach ( $settings as $field ) {
			if ( in_array( $field['id'] ?? '', $decided, true ) ) {
				continue;
			}
			if ( ( $field['id'] ?? '' ) === 'email_template_options' && ( $field['type'] ?? '' ) === 'title' ) {
				$field['desc'] = __( 'The logo, colours and typeface come from Store, Brand.', 'storewright' );
			}
			if ( ( $field['id'] ?? '' ) === 'email_notification_settings' && ( $field['type'] ?? '' ) === 'title' ) {
				$field['desc'] = __( 'The emails the store sends. Open one to change its subject, heading and whether it is sent.', 'storewright' );
			}
			$kept[] = $field;
		}
		return $kept;
	}

	public static function styles( string $css ): string {
		$settings = Settings::get();
		$heading  = Fonts::emailStack( $settings['headingFont'] );
		$body     = Fonts::emailStack( $settings['bodyFont'] );
		$text     = self::colour( 'contrast' );
		$muted    = self::colour( 'muted' );
		return $css . <<<CSS
		.email-logo-text { color: {$text}; font-family: {$heading}; font-size: 26px; font-weight: 700; letter-spacing: -0.5px; }
		.email-logo-text a { color: {$text}; text-decoration: none; }
		h1, h2 { font-family: {$heading}; }
		h1 { font-size: 30px; letter-spacing: -0.5px; }
		h2 span { font-family: {$body}; }
		#body_content .email-order-details tbody tr:last-child td { border-bottom: 0; }
		#body_content .email-order-details .order-totals-last td, #body_content .email-order-details .order-totals-last th { border-bottom: 0; }
		#template_footer #credit, #template_footer #credit a { color: {$muted}; }
		CSS;
	}

	public static function colour( string $key ): string {
		return Palette::from( Settings::get() )[ $key ];
	}
}
