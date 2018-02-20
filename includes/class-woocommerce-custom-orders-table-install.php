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
				order_key varchar(100),
				customer_id BIGINT UNSIGNED NOT NULL,
				billing_index varchar(255),
				billing_first_name varchar(100),
				billing_last_name varchar(100),
				billing_company varchar(100),
				billing_address_1 varchar(200),
				billing_address_2 varchar(200),
				billing_city varchar(100),
				billing_state varchar(100),
				billing_postcode varchar(100),
				billing_country varchar(100),
				billing_email varchar(200) NOT NULL,
				billing_phone varchar(200),
				shipping_index varchar(255),
				shipping_first_name varchar(100),
				shipping_last_name varchar(100),
				shipping_company varchar(100),
				shipping_address_1 varchar(200),
				shipping_address_2 varchar(200),
				shipping_city varchar(100),
				shipping_state varchar(100),
				shipping_postcode varchar(100),
				shipping_country varchar(100),
				payment_method varchar(100),
				payment_method_title varchar(100),
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
				customer_ip_address varchar(40),
				customer_user_agent varchar(200),
				created_via varchar(200) NOT NULL,
				date_completed varchar(20) DEFAULT NULL,
				date_paid varchar(20) DEFAULT NULL,
				cart_hash varchar(32),
				amount varchar(100),
				refunded_by BIGINT UNSIGNED,
				reason text,
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
