<?php
/**
 * Table installation procedure.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

/**
 * Installer for WooCommerce Custom Orders Table.
 *
 * Usage:
 *
 *     WooCommerce_Custom_Orders_Table_Install::activate();
 */
class WooCommerce_Custom_Orders_Table_Install {

	/**
	 * The option key that contains the current schema version.
	 */
	const SCHEMA_VERSION_KEY = 'wc_orders_table_version';

	/**
	 * The database table schema version.
	 *
	 * @var int
	 */
	protected static $table_version = 3;

	/**
	 * Actions to perform on plugin activation.
	 */
	public static function activate() {
		// We're already on the latest schema version.
		if ( (int) get_option( self::SCHEMA_VERSION_KEY ) === (int) self::$table_version ) {
			return false;
		}

		self::install_tables();
	}

	/**
	 * Perform the database delta to create the table.
	 *
	 * @global $wpdb
	 */
	protected static function install_tables() {
		global $wpdb;

		// Load wp-admin/includes/upgrade.php, which defines dbDelta().
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = wc_custom_order_table()->get_table_name();
		$collate = $wpdb->get_charset_collate();
		$tables  = "
			CREATE TABLE {$table} (
				order_id BIGINT UNSIGNED NOT NULL COMMENT 'Order post ID',
				order_key varchar(100) DEFAULT NULL COMMENT 'Unique order key',
				customer_id BIGINT UNSIGNED NOT NULL COMMENT 'Customer ID. Will be 0 for guests.',
				billing_index varchar(255) DEFAULT NULL COMMENT 'Billing fields, concatenated for search',
				billing_first_name varchar(100) DEFAULT NULL COMMENT 'Billing first name',
				billing_last_name varchar(100) DEFAULT NULL COMMENT 'Billing last name',
				billing_company varchar(100) DEFAULT NULL COMMENT 'Billing company',
				billing_address_1 varchar(255) DEFAULT NULL COMMENT 'Billing street address',
				billing_address_2 varchar(200) DEFAULT NULL COMMENT 'Billing extended address',
				billing_city varchar(100) DEFAULT NULL COMMENT 'Billing city/locality',
				billing_state varchar(100) DEFAULT NULL COMMENT 'Billing state/province/locale',
				billing_postcode varchar(20) DEFAULT NULL COMMENT 'Billing postal code',
				billing_country char(2) DEFAULT NULL COMMENT 'Billing country (ISO 3166-1 Alpha-2)',
				billing_email varchar(200) NOT NULL COMMENT 'Billing email address',
				billing_phone varchar(200) DEFAULT NULL COMMENT 'Billing phone number',
				shipping_index varchar(255) DEFAULT NULL COMMENT 'Shipping fields, concatenated for search',
				shipping_first_name varchar(100) DEFAULT NULL COMMENT 'Shipping first name',
				shipping_last_name varchar(100) DEFAULT NULL COMMENT 'Shipping last name',
				shipping_company varchar(100) DEFAULT NULL COMMENT 'Shipping company',
				shipping_address_1 varchar(255) DEFAULT NULL COMMENT 'Shipping street address',
				shipping_address_2 varchar(200) DEFAULT NULL COMMENT 'Shipping extended address',
				shipping_city varchar(100) DEFAULT NULL COMMENT 'Shipping city/locality',
				shipping_state varchar(100) DEFAULT NULL COMMENT 'Shipping state/province/locale',
				shipping_postcode varchar(20) DEFAULT NULL COMMENT 'Shipping postal code',
				shipping_country char(2) DEFAULT NULL COMMENT 'Shipping country (ISO 3166-1 Alpha-2)',
				payment_method varchar(100) DEFAULT NULL COMMENT 'Payment method ID',
				payment_method_title varchar(100) DEFAULT NULL COMMENT 'Payment method title',
				discount_total varchar(100) NOT NULL DEFAULT 0 COMMENT 'Discount total',
				discount_tax varchar(100) NOT NULL DEFAULT 0 COMMENT 'Discount tax',
				shipping_total varchar(100) NOT NULL DEFAULT 0 COMMENT 'Shipping total',
				shipping_tax varchar(100) NOT NULL DEFAULT 0 COMMENT 'Shipping tax',
				cart_tax varchar(100) NOT NULL DEFAULT 0 COMMENT 'Cart tax',
				total varchar(100) NOT NULL DEFAULT 0 COMMENT 'Order total',
				version varchar(16) NOT NULL COMMENT 'Version of WooCommerce when the order was made',
				currency char(3) NOT NULL COMMENT 'Currency the order was created with',
				prices_include_tax varchar(3) NOT NULL COMMENT 'Did the prices include tax during checkout?',
				transaction_id varchar(200) NOT NULL COMMENT 'Unique transaction ID',
				customer_ip_address varchar(40) DEFAULT NULL COMMENT 'The customer\'s IP address',
				customer_user_agent text DEFAULT NULL COMMENT 'The customer\'s User-Agent string',
				created_via varchar(200) NOT NULL COMMENT 'Order creation method',
				date_completed varchar(20) DEFAULT NULL COMMENT 'Date the order was completed',
				date_paid varchar(20) DEFAULT NULL COMMENT 'Date the order was paid',
				cart_hash varchar(32) DEFAULT NULL COMMENT 'Hash of cart items to ensure orders are not modified',
				amount varchar(100) DEFAULT NULL COMMENT 'The refund amount',
				refunded_by BIGINT UNSIGNED DEFAULT NULL COMMENT 'The ID of the user who issued the refund',
				reason text DEFAULT NULL COMMENT 'The reason for the refund being issued',
			PRIMARY KEY  (order_id),
			UNIQUE KEY `order_key` (`order_key`),
			KEY `customer_id` (`customer_id`),
			KEY `order_total` (`total`)
			) $collate;
		";

		// Apply the database migration.
		dbDelta( $tables );

		$table  = wc_custom_order_table()->get_meta_table_name();
		$tables = "
			CREATE TABLE {$table} (
				meta_id bigint(20) unsigned NOT NULL auto_increment,
				order_id bigint(20) unsigned NOT NULL default '0',
				meta_key varchar(255) default NULL,
				meta_value longtext,
				PRIMARY KEY  (meta_id),
				KEY order_id (order_id),
				KEY meta_key (meta_key(191))
			) $collate;
		";

		// Apply the database migration.
		dbDelta( $tables );

		// Store the table version in the options table.
		update_option( self::SCHEMA_VERSION_KEY, (int) self::$table_version, false );
	}
}
