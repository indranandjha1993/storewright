<?php

declare(strict_types=1);

namespace Storewright\Platform\Brand;

use InvalidArgumentException;

final class Settings {

	public const OPTION = 'storewright_brand';

	public const DEFAULTS = [
		'accent'      => '#0f5c4d',
		'background'  => '#fafaf7',
		'text'        => '#15171a',
		'sale'        => '#b4472b',
		'headingFont' => 'fraunces',
		'bodyFont'    => 'inter',
		'radius'      => 'soft',
	];

	public const RADII = [ 'sharp', 'soft', 'round' ];

	private const COLOURS = [ 'accent', 'background', 'text', 'sale' ];

	public static function get(): array {
		$stored = get_option( self::OPTION );
		return self::clean( is_array( $stored ) ? $stored : [] );
	}

	public static function save( array $changes ): array {
		$settings = self::validate( [ ...self::get(), ...$changes ] );
		update_option( self::OPTION, $settings, true );
		return $settings;
	}

	public static function validate( array $input ): array {
		$settings = [];
		foreach ( self::COLOURS as $key ) {
			$value = trim( (string) ( $input[ $key ] ?? self::DEFAULTS[ $key ] ) );
			if ( ! Colour::isHex( $value ) ) {
				/* translators: %s: setting name */
				throw new InvalidArgumentException( esc_html( sprintf( __( 'The %s colour must be a hex colour such as #0F5C4D.', 'storewright' ), self::label( $key ) ) ) );
			}
			$settings[ $key ] = Colour::normalise( $value );
		}
		if ( Colour::contrast( $settings['text'], $settings['background'] ) < 4.5 ) {
			throw new InvalidArgumentException( esc_html__( 'The text colour needs more contrast against the background so the store stays readable.', 'storewright' ) );
		}
		foreach ( [ 'headingFont', 'bodyFont' ] as $key ) {
			$value = (string) ( $input[ $key ] ?? self::DEFAULTS[ $key ] );
			if ( ! Fonts::has( $value ) ) {
				throw new InvalidArgumentException( esc_html__( 'Choose a typeface from the list.', 'storewright' ) );
			}
			$settings[ $key ] = $value;
		}
		$radius = (string) ( $input['radius'] ?? self::DEFAULTS['radius'] );
		if ( ! in_array( $radius, self::RADII, true ) ) {
			throw new InvalidArgumentException( esc_html__( 'Corners are sharp, soft or round.', 'storewright' ) );
		}
		$settings['radius'] = $radius;
		return $settings;
	}

	private static function clean( array $stored ): array {
		$settings = [];
		foreach ( self::COLOURS as $key ) {
			$value            = (string) ( $stored[ $key ] ?? '' );
			$settings[ $key ] = Colour::isHex( $value ) ? Colour::normalise( $value ) : self::DEFAULTS[ $key ];
		}
		foreach ( [ 'headingFont', 'bodyFont' ] as $key ) {
			$settings[ $key ] = Fonts::has( (string) ( $stored[ $key ] ?? '' ) ) ? $stored[ $key ] : self::DEFAULTS[ $key ];
		}
		$settings['radius'] = in_array( $stored['radius'] ?? '', self::RADII, true ) ? $stored['radius'] : self::DEFAULTS['radius'];
		return $settings;
	}

	private static function label( string $key ): string {
		return match ( $key ) {
			'accent'     => __( 'accent', 'storewright' ),
			'background' => __( 'background', 'storewright' ),
			'text'       => __( 'text', 'storewright' ),
			default      => __( 'sale', 'storewright' ),
		};
	}
}
