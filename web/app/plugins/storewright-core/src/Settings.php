<?php

declare(strict_types=1);

namespace Storewright\Core;

final class Settings {

	private const OPTION = 'storewright_settings';

	private const DEFAULTS = [
		'cod_max_total' => 10000,
		'cod_postcodes' => [],
	];

	public static function get( string $key ): mixed {
		$saved = get_option( self::OPTION, [] );
		$saved = is_array( $saved ) ? $saved : [];
		return $saved[ $key ] ?? self::DEFAULTS[ $key ] ?? null;
	}
}
