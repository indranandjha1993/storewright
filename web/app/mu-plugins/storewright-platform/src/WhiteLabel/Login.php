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
		$logo    = Brand::logoUrl();
		$palette = Brand::palette();
		$mark    = $logo !== ''
			? sprintf( 'background:url(%s) center/contain no-repeat;width:100%%;height:84px;', esc_url_raw( $logo ) )
			: 'background:none;width:auto;height:auto;text-indent:0;font:700 1.75rem/1.2 var(--sw-font-heading);color:var(--sw-contrast);';
		wp_add_inline_style(
			'login',
			sprintf(
				'body.login{background:var(--sw-base);font-family:var(--sw-font-body)}.login h1 a{%1$s}.login form{border-radius:var(--sw-radius);border-color:var(--sw-line);box-shadow:var(--sw-shadow)}.login input[type=text],.login input[type=password]{border-radius:var(--sw-radius-small)}.wp-core-ui .button-primary{background:%2$s;border-color:%2$s;color:%4$s;border-radius:var(--sw-radius-button)}.wp-core-ui .button-primary:hover,.wp-core-ui .button-primary:focus{background:%3$s;border-color:%3$s;color:%4$s}.login input:focus{border-color:%2$s;box-shadow:0 0 0 1px %2$s}.login #nav a,.login #backtoblog a,.login .privacy-policy-page-link a{color:var(--sw-muted)}.login #nav a:hover,.login #backtoblog a:hover,.login .privacy-policy-page-link a:hover{color:%2$s}.login .button.wp-hide-pw{color:var(--sw-muted)}.login .button.wp-hide-pw:hover,.login .button.wp-hide-pw:focus{color:%2$s;box-shadow:none}',
				$mark,
				$palette['accent'],
				$palette['accentHover'],
				$palette['onAccent']
			)
		);
	}
}
