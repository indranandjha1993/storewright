<?php

declare(strict_types=1);

namespace Storewright\Platform\Brand;

final class Palette {

	private const RADII = [
		'sharp' => [
			'radius' => '2px',
			'small'  => '2px',
			'button' => '2px',
		],
		'soft'  => [
			'radius' => '10px',
			'small'  => '8px',
			'button' => '10px',
		],
		'round' => [
			'radius' => '20px',
			'small'  => '12px',
			'button' => '999px',
		],
	];

	public static function from( array $settings ): array {
		$base    = $settings['background'];
		$text    = $settings['text'];
		$accent  = $settings['accent'];
		$dark    = ! Colour::isLight( $base );
		$surface = $dark ? Colour::lighten( $base, 0.06 ) : Colour::lighten( $base, 1.0 );
		return [
			'base'        => $base,
			'surface'     => $surface,
			'contrast'    => $text,
			'muted'       => Colour::mix( $text, $base, 0.38 ),
			'line'        => Colour::mix( $text, $base, 0.88 ),
			'accent'      => $accent,
			'accentHover' => $dark ? Colour::lighten( $accent, 0.12 ) : Colour::darken( $accent, 0.12 ),
			'accentSoft'  => Colour::mix( $accent, $base, 0.86 ),
			'onAccent'    => Colour::readableOn( $accent ),
			'sale'        => $settings['sale'],
			'onSale'      => Colour::readableOn( $settings['sale'] ),
			'shadow'      => sprintf( '0 1px 2px rgb(%1$s / 6%%), 0 8px 24px rgb(%1$s / 6%%)', str_replace( ',', ' ', Colour::rgb( $text ) ) ),
		];
	}

	public static function radii( string $radius ): array {
		return self::RADII[ $radius ] ?? self::RADII['soft'];
	}
}
