<?php

declare(strict_types=1);

namespace Storewright\Core\Cli;

use WC_Admin_Status;
use WP_CLI;

final class TemplatesCommand {

	public function __invoke(): void {
		$outdated = [];
		$root     = get_stylesheet_directory() . '/woocommerce/';
		$files    = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $item ) {
			$file = (string) $item;
			if ( ! str_ends_with( $file, '.php' ) ) {
				continue;
			}
			$name  = substr( $file, strlen( $root ) );
			$core  = WC()->plugin_path() . '/templates/' . $name;
			$ours  = WC_Admin_Status::get_file_version( $file );
			$them  = is_file( $core ) ? WC_Admin_Status::get_file_version( $core ) : '';
			$state = $them === '' ? 'not in WooCommerce' : ( version_compare( (string) $ours, $them, '<' ) ? 'outdated' : 'current' );
			WP_CLI::log( sprintf( '%-40s ours %-8s WooCommerce %-8s %s', $name, $ours ?: '-', $them ?: '-', $state ) );
			if ( $state === 'outdated' ) {
				$outdated[] = $name;
			}
		}
		if ( $outdated !== [] ) {
			WP_CLI::error( 'WooCommerce changed templates the theme overrides: ' . implode( ', ', $outdated ) . '. Compare them, update the override and its @version.' );
		}
		WP_CLI::success( 'Every template override matches its WooCommerce version.' );
	}
}
