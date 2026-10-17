<?php

declare(strict_types=1);

namespace Storewright\Core\Invoice;

use DateTimeInterface;

final class InvoiceNumber {

	public static function financialYear( DateTimeInterface $date ): string {
		$year  = (int) $date->format( 'Y' );
		$start = (int) $date->format( 'n' ) >= 4 ? $year : $year - 1;
		return sprintf( '%02d-%02d', $start % 100, ( $start + 1 ) % 100 );
	}

	public static function format( string $prefix, string $year, int $serial ): string {
		return sprintf( '%s/%s/%04d', $prefix, $year, $serial );
	}

	public static function next( string $year ): int {
		global $wpdb;
		$option = 'storewright_invoice_serial_' . $year;
		add_option( $option, '0', '', false );
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET option_value = LAST_INSERT_ID(option_value + 1) WHERE option_name = %s', $wpdb->options, $option ) );
		$serial = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
		wp_cache_delete( $option, 'options' );
		return $serial;
	}
}
