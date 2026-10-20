<?php

declare(strict_types=1);

namespace Storewright\Platform\Brand;

use InvalidArgumentException;

final class Update {

	public static function apply( array $input ): void {
		Settings::save( array_intersect_key( $input, Settings::DEFAULTS ) );
		if ( array_key_exists( 'name', $input ) ) {
			$name = trim( sanitize_text_field( (string) $input['name'] ) );
			if ( $name === '' ) {
				throw new InvalidArgumentException( esc_html__( 'The store needs a name.', 'storewright' ) );
			}
			update_option( 'blogname', $name );
		}
		if ( array_key_exists( 'logo', $input ) ) {
			$logo = (int) $input['logo'];
			if ( $logo > 0 && ! wp_attachment_is_image( $logo ) ) {
				throw new InvalidArgumentException( esc_html__( 'The logo has to be an image from the media library.', 'storewright' ) );
			}
			update_option( 'site_logo', $logo );
			set_theme_mod( 'custom_logo', $logo );
		}
		if ( array_key_exists( 'heroImage', $input ) ) {
			$hero = (int) $input['heroImage'];
			if ( $hero > 0 && ! wp_attachment_is_image( $hero ) ) {
				throw new InvalidArgumentException( esc_html__( 'The home page photo has to be an image from the media library.', 'storewright' ) );
			}
			update_option( 'storewright_hero_image', $hero );
		}
		if ( array_key_exists( 'description', $input ) ) {
			update_option( 'blogdescription', mb_substr( trim( sanitize_text_field( (string) $input['description'] ) ), 0, 160 ) );
		}
		if ( array_key_exists( 'announcement', $input ) ) {
			update_option( 'storewright_announcement', mb_substr( trim( sanitize_text_field( (string) $input['announcement'] ) ), 0, 140 ) );
		}
		ThemeJson::refresh();
	}
}
