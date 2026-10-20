<?php

declare(strict_types=1);

namespace Storewright\Core;

final class Schema {

	public const VERSION = '4';
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
		dbDelta(
			"CREATE TABLE {$wpdb->prefix}storewright_area_geometry (
  zone_id bigint(20) unsigned NOT NULL,
  shape longtext NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (zone_id)
) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$wpdb->prefix}storewright_pincodes (
  pin char(6) NOT NULL,
  district varchar(100) NOT NULL,
  state varchar(10) NOT NULL,
  lat decimal(9,6) NULL,
  lng decimal(9,6) NULL,
  offices smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (pin),
  KEY lat_lng (lat, lng)
) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$wpdb->prefix}storewright_shipments (
  fulfillment_id bigint(20) unsigned NOT NULL,
  order_id bigint(20) unsigned NOT NULL,
  carrier varchar(20) NOT NULL,
  reference varchar(40) NOT NULL DEFAULT '',
  shipment varchar(40) NOT NULL DEFAULT '',
  awb varchar(40) NOT NULL DEFAULT '',
  courier varchar(100) NOT NULL DEFAULT '',
  service varchar(20) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL,
  raw_status varchar(100) NOT NULL DEFAULT '',
  label_url text NULL,
  pickup_at varchar(40) NOT NULL DEFAULT '',
  tries tinyint(3) unsigned NOT NULL DEFAULT 0,
  last_error text NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (fulfillment_id),
  KEY order_id (order_id),
  KEY awb (awb),
  KEY status (status)
) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$wpdb->prefix}storewright_shipment_events (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  fulfillment_id bigint(20) unsigned NOT NULL,
  status varchar(20) NOT NULL,
  raw_status varchar(100) NOT NULL,
  location varchar(191) NOT NULL DEFAULT '',
  occurred_at datetime NOT NULL,
  source varchar(20) NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY scan (fulfillment_id, occurred_at, raw_status),
  KEY fulfillment_id (fulfillment_id)
) {$charset};"
		);
		update_option( self::OPTION, self::VERSION, false );
	}
}
