<?php

declare(strict_types=1);

namespace Storewright\Core;

final class Schema {

	public const VERSION = '1';
	private const OPTION = 'storewright_schema_version';

	public static function register(): void {
		register_activation_hook( FILE, [ self::class, 'install' ] );
		add_action( 'plugins_loaded', [ self::class, 'upgrade' ], 5 );
	}

	public static function upgrade(): void {
		if ( get_option( self::OPTION ) !== self::VERSION ) {
			self::install();
		}
	}

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$wpdb->prefix}storewright_payment_events (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  gateway varchar(20) NOT NULL,
  event_id varchar(191) NOT NULL,
  event_type varchar(100) NOT NULL,
  status varchar(20) NOT NULL,
  deliveries int(10) unsigned NOT NULL DEFAULT 1,
  payload_hash char(64) NOT NULL,
  received_at datetime NOT NULL,
  processed_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY gateway_event (gateway, event_id),
  KEY status_received (status, received_at)
) {$charset};"
		);
		update_option( self::OPTION, self::VERSION, false );
	}
}
