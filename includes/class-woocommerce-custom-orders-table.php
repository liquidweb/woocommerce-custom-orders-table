<?php
/**
 * Core plugin functionality.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

/**
 * Core functionality for WooCommerce Custom Orders Table.
 */
class WooCommerce_Custom_Orders_Table {

	/**
	 * The database table name.
	 *
	 * @var string
	 */
	protected $table_name = null;

	/**
	 * Steps to run on plugin initialization.
	 *
	 * @global $wpdb
	 */
	public function setup() {
		global $wpdb;

		$this->table_name = $wpdb->prefix . 'woocommerce_orders';

		// Use the plugin's custom data stores for customers and orders.
		add_filter( 'woocommerce_customer_data_store', __CLASS__ . '::customer_data_store' );
		add_filter( 'woocommerce_order_data_store', __CLASS__ . '::order_data_store' );

		// If we're in a WP-CLI context, load the WP-CLI command.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'wc-order-table', 'WooCommerce_Custom_Orders_Table_CLI' );
		}
	}

	/**
	 * Retrieve the WooCommerce orders table name.
	 *
	 * @return string The database table name.
	 */
	public function get_table_name() {
		/**
		 * Filter the WooCommerce orders table name.
		 *
		 * @param string $table The WooCommerce orders table name.
		 */
		return apply_filters( 'wc_customer_order_table_name', $this->table_name );
	}

	/**
	 * Retrieve the class name of the WooCommerce customer data store.
	 *
	 * @return string The data store class name.
	 */
	public static function customer_data_store() {
		return 'WC_Customer_Data_Store_Custom_Table';
	}

	/**
	 * Retrieve the class name of the WooCommerce order data store.
	 *
	 * @return string The data store class name.
	 */
	public static function order_data_store() {
		return 'WC_Order_Data_Store_Custom_Table';
	}
}
