<?php

declare(strict_types=1);

namespace Storewright\Core;

final class Settings {

	private const OPTION = 'storewright_settings';

	private const DEFAULTS = [
		'cod_max_total'          => 10000,
		'cod_postcodes'          => [],
		'cod_fee'                => 0,
		'handling_fee'           => 0,
		'handling_fee_free_from' => 0,
		'store_phone'            => '',
		'store_gstin'            => '',
		'gst_rate'               => 18,
		'invoice_prefix'         => 'INV',
		'return_days'            => 7,
	];

	public static function get( string $key ): mixed {
		return self::saved()[ $key ] ?? self::DEFAULTS[ $key ] ?? null;
	}

	public static function set( array $values ): void {
		update_option( self::OPTION, array_intersect_key( $values, self::DEFAULTS ) + self::saved() );
	}

	private static function saved(): array {
		$saved = get_option( self::OPTION, [] );
		return is_array( $saved ) ? $saved : [];
	}
}
