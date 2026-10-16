<?php

declare(strict_types=1);

namespace Storewright\Platform\WhiteLabel;

final class Login {

	public static function register(): void {
		add_filter( 'login_headerurl', static fn(): string => home_url( '/' ) );
		add_filter( 'login_headertext', static fn(): string => Brand::name() );
		add_filter( 'login_display_language_dropdown', '__return_false' );
		add_action( 'login_enqueue_scripts', [ self::class, 'style' ] );
	}

	public static function style(): void {
		$logo   = Brand::logoUrl();
		$accent = Brand::accent();
		$mark   = $logo !== ''
			? sprintf( 'background:url(%s) center/contain no-repeat;width:100%%;height:84px;', esc_url_raw( $logo ) )
			: 'background:none;width:auto;height:auto;text-indent:0;font-size:1.75rem;font-weight:700;line-height:1.2;color:#15171a;';
		wp_add_inline_style(
			'login',
			sprintf(
				'.login h1 a{%1$s}.wp-core-ui .button-primary{background:%2$s;border-color:%2$s}.wp-core-ui .button-primary:hover,.wp-core-ui .button-primary:focus{background:%3$s;border-color:%3$s}.login input:focus{border-color:%2$s;box-shadow:0 0 0 1px %2$s}.login #nav a:hover,.login #backtoblog a:hover{color:%2$s}',
				$mark,
				$accent,
				Brand::darken( $accent, 0.1 )
			)
		);
	}
}
