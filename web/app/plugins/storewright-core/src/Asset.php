<?php

declare(strict_types=1);

namespace Storewright\Core;

final class Asset {

	public static function url( string $path ): string {
		return plugins_url( $path, FILE );
	}

	public static function script( string $handle, string $path, array $dependencies = [] ): void {
		wp_enqueue_script( $handle, self::url( $path ), $dependencies, self::version( $path ), true );
	}

	public static function version( string $path ): string {
		$modified = filemtime( dirname( FILE ) . '/' . $path );
		return $modified === false ? VERSION : (string) $modified;
	}
}
