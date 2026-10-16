<?php

declare(strict_types=1);

namespace Storewright\Platform\Brand;

use InvalidArgumentException;
use WP_CLI;

final class Command {

	public function show( array $args, array $options ): void {
		$settings = Settings::get();
		$rows     = [];
		foreach ( $settings as $key => $value ) {
			$rows[] = [
				'setting' => $key,
				'value'   => $value,
			];
		}
		WP_CLI\Utils\format_items( (string) ( $options['format'] ?? 'table' ), $rows, [ 'setting', 'value' ] );
	}

	public function set( array $args ): void {
		$changes = [];
		foreach ( $args as $pair ) {
			[ $key, $value ] = array_pad( explode( '=', (string) $pair, 2 ), 2, '' );
			if ( ! array_key_exists( $key, Settings::DEFAULTS ) ) {
				WP_CLI::error( sprintf( 'Unknown setting %s. Settings: %s.', $key, implode( ', ', array_keys( Settings::DEFAULTS ) ) ) );
			}
			$changes[ $key ] = $value;
		}
		try {
			Settings::save( $changes );
		} catch ( InvalidArgumentException $e ) {
			WP_CLI::error( $e->getMessage() );
		}
		ThemeJson::refresh();
		WP_CLI::success( 'Brand updated.' );
	}

	public function reset(): void {
		delete_option( Settings::OPTION );
		ThemeJson::refresh();
		WP_CLI::success( 'Brand back to the defaults.' );
	}
}
