<?php
/**
 * Core plugin functionality.
 *
 * @package WooCommerce_Custom_Order_Tables
 * @author  Liquid Web
 */

/**
 * Core functionality for WooCommerce Custom Order Tables.
 */
class WC_Custom_Order_Table {

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

		// Inject the plugin into order processing.
		add_filter( 'woocommerce_customer_data_store', array( $this, 'customer_data_store' ) );
		add_filter( 'woocommerce_order_data_store', array( $this, 'order_data_store' ) );

		// Register the CLI command if we're running WP_CLI.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'wc-order-table', 'WC_Custom_Order_Table_CLI' );
		}
	}

	/**
	 * Retrieve the WooCommerce order table name.
	 *
	 * @return string The database table name.
	 */
	public function get_table_name() {
		/**
		 * Filter the WooCommerce order table name.
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
	public function customer_data_store() {
		return 'WC_Customer_Data_Store_Custom_Table';
	}

	/**
	 * Retrieve the class name of the WooCommerce order data store.
	 *
	 * @return string The data store class name.
	 */
	public function order_data_store() {
		return 'WC_Order_Data_Store_Custom_Table';
	}
}
