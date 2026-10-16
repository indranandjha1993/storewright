<?php

declare(strict_types=1);

namespace Storewright\Platform\Brand;

final class Tokens {

	public static function register(): void {
		add_action( 'admin_head', [ self::class, 'printAdmin' ], 1 );
		add_action( 'login_head', [ self::class, 'printLogin' ], 1 );
		add_action( 'wp_head', [ self::class, 'meta' ], 1 );
	}

	public static function css( ?array $settings = null ): string {
		$settings = $settings ?? Settings::get();
		$palette  = Palette::from( $settings );
		$radii    = Palette::radii( $settings['radius'] );
		$vars     = [
			'--sw-base'          => $palette['base'],
			'--sw-surface'       => $palette['surface'],
			'--sw-contrast'      => $palette['contrast'],
			'--sw-muted'         => $palette['muted'],
			'--sw-line'          => $palette['line'],
			'--sw-accent'        => $palette['accent'],
			'--sw-accent-hover'  => $palette['accentHover'],
			'--sw-accent-soft'   => $palette['accentSoft'],
			'--sw-on-accent'     => $palette['onAccent'],
			'--sw-sale'          => $palette['sale'],
			'--sw-on-sale'       => $palette['onSale'],
			'--sw-shadow'        => $palette['shadow'],
			'--sw-radius'        => $radii['radius'],
			'--sw-radius-small'  => $radii['small'],
			'--sw-radius-button' => $radii['button'],
			'--sw-font-body'     => Fonts::get( $settings['bodyFont'] )['family'],
			'--sw-font-heading'  => Fonts::get( $settings['headingFont'] )['family'],
		];
		$css      = '';
		foreach ( $vars as $name => $value ) {
			$css .= "{$name}:{$value};";
		}
		return ":root{{$css}}";
	}

	public static function fontFaces( ?array $settings = null ): string {
		$settings = $settings ?? Settings::get();
		$faces    = Fonts::get( $settings['bodyFont'] )['faces'];
		if ( $settings['headingFont'] !== $settings['bodyFont'] ) {
			$faces = [ ...$faces, ...Fonts::get( $settings['headingFont'] )['faces'] ];
		}
		return Fonts::fontFaceCss( $faces );
	}

	public static function adminCss( ?array $settings = null ): string {
		$settings = $settings ?? Settings::get();
		$palette  = Palette::from( $settings );
		$accent   = $palette['accent'];
		$hover    = $palette['accentHover'];
		return sprintf(
			':root{--wp-admin-theme-color:%1$s;--wp-admin-theme-color--rgb:%2$s;--wp-admin-theme-color-darker-10:%3$s;--wp-admin-theme-color-darker-20:%4$s;--wp-admin-border-width-focus:2px;--wp-components-color-accent:%1$s;--wp-components-color-accent-darker-10:%3$s;--wp-components-color-accent-darker-20:%4$s;--wp-components-color-accent-inverted:%5$s}.boot-layout__stage,.boot-layout__inspector{--wp-components-color-accent:%1$s;--wp-components-color-accent-darker-10:%3$s;--wp-components-color-accent-darker-20:%4$s;--wp-components-color-accent-inverted:%5$s}.wp-core-ui .button-primary{background:%1$s;border-color:%1$s;color:%5$s}.wp-core-ui .button-primary:hover,.wp-core-ui .button-primary:focus{background:%3$s;border-color:%3$s;color:%5$s}#adminmenu li.current a.menu-top,#adminmenu li.wp-has-current-submenu a.wp-has-current-submenu,#adminmenu .wp-has-current-submenu .wp-submenu-head{background:%1$s;color:%5$s}',
			$accent,
			Colour::rgb( $accent ),
			$hover,
			Colour::darken( $accent, 0.2 ),
			$palette['onAccent']
		);
	}

	public static function admin(): string {
		return self::css() . self::fontFaces() . self::adminCss();
	}

	public static function printAdmin(): void {
		self::meta();
		printf( '<style id="storewright-tokens">%s</style>', self::admin() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function printLogin(): void {
		self::meta();
		printf( '<style id="storewright-tokens">%s%s</style>', self::css(), self::fontFaces() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function meta(): void {
		printf( '<meta name="theme-color" content="%s">' . "\n", esc_attr( Palette::from( Settings::get() )['accent'] ) );
	}
}
