<?php

declare(strict_types=1);

namespace Storewright\Platform\WhiteLabel;

use Storewright\Platform\Brand\Palette;
use Storewright\Platform\Brand\Settings;

final class Brand {

	public static function name(): string {
		return (string) get_bloginfo( 'name' );
	}

	public static function logoId(): int {
		return (int) get_option( 'site_logo' ) ?: (int) get_theme_mod( 'custom_logo' );
	}

	public static function logoUrl(): string {
		$id  = self::logoId();
		$url = $id ? wp_get_attachment_image_url( $id, 'medium' ) : false;
		return $url ? $url : '';
	}

	public static function palette(): array {
		return Palette::from( Settings::get() );
	}

	public static function accent(): string {
		return self::palette()['accent'];
	}
}
