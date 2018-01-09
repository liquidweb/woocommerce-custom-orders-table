<?php
/**
 * Table installation procedure.
 *
 * @package WooCommerce_Custom_Order_Tables
 * @author  Liquid Web
 */

/**
 * Installer for WooCommerce Custom Order Tables.
 *
 * Usage:
 *
 *     WC_Custom_Order_Table_Install::activate();
 */
class WC_Custom_Order_Table_Install {

	/**
	 * The option key that contains the current schema version.
	 */
	const SCHEMA_VERSION_KEY = 'wc_orders_table_version';

	/**
	 * The database table schema version.
	 *
	 * @var int
	 */
	protected static $table_version = 2;

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
				order_id BIGINT UNSIGNED NOT NULL,
				order_key varchar(100) NOT NULL,
				customer_id BIGINT UNSIGNED NOT NULL,
				billing_first_name varchar(100) NOT NULL,
				billing_last_name varchar(100) NOT NULL,
				billing_company varchar(100) NOT NULL,
				billing_address_1 varchar(200) NOT NULL,
				billing_address_2 varchar(200) NOT NULL,
				billing_city varchar(100) NOT NULL,
				billing_state varchar(100) NOT NULL,
				billing_postcode varchar(100) NOT NULL,
				billing_country varchar(100) NOT NULL,
				billing_email varchar(200) NOT NULL,
				billing_phone varchar(200) NOT NULL,
				shipping_first_name varchar(100) NOT NULL,
				shipping_last_name varchar(100) NOT NULL,
				shipping_company varchar(100) NOT NULL,
				shipping_address_1 varchar(200) NOT NULL,
				shipping_address_2 varchar(200) NOT NULL,
				shipping_city varchar(100) NOT NULL,
				shipping_state varchar(100) NOT NULL,
				shipping_postcode varchar(100) NOT NULL,
				shipping_country varchar(100) NOT NULL,
				payment_method varchar(100) NOT NULL,
				payment_method_title varchar(100) NOT NULL,
				discount_total varchar(100) NOT NULL DEFAULT 0,
				discount_tax varchar(100) NOT NULL DEFAULT 0,
				shipping_total varchar(100) NOT NULL DEFAULT 0,
				shipping_tax varchar(100) NOT NULL DEFAULT 0,
				cart_tax varchar(100) NOT NULL DEFAULT 0,
				total varchar(100) NOT NULL DEFAULT 0,
				version varchar(16) NOT NULL,
				currency varchar(3) NOT NULL,
				prices_include_tax varchar(3) NOT NULL,
				transaction_id varchar(200) NOT NULL,
				customer_ip_address varchar(40) NOT NULL,
				customer_user_agent varchar(200) NOT NULL,
				created_via varchar(200) NOT NULL,
				date_completed datetime DEFAULT NULL,
				date_paid datetime DEFAULT NULL,
				cart_hash varchar(32) NOT NULL,
			PRIMARY KEY  (order_id),
			UNIQUE KEY `order_key` (`order_key`),
			KEY `customer_id` (`customer_id`),
			KEY `order_total` (`total`)
			) $collate;
		";

		// Apply the database migration.
		dbDelta( $tables );

		// Store the table version in the options table.
		update_option( self::SCHEMA_VERSION_KEY, (int) self::$table_version, false );
	}
}
