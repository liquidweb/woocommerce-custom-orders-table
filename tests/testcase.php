<?php
/**
 * Base test case for WooCommerce Custom Orders Table.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

class TestCase extends WC_Unit_Test_Case {

	/**
	 * Delete all data from the orders table after each test.
	 *
	 * @after
	 *
	 * @global $wpdb
	 */
	protected function truncate_table() {
		global $wpdb;

		$wpdb->query( "DELETE FROM {$wpdb->prefix}woocommerce_orders" );
	}

	/**
	 * Toggle whether or not the custom table should be used.
	 *
	 * @param bool $enabled Optional. Whether or not the custom table should be used. Default is true.
	 */
	protected function toggle_use_custom_table( $enabled = true ) {
		if ( $enabled ) {
			add_filter( 'woocommerce_customer_data_store', 'WC_Custom_Order_Table::customer_data_store' );
			add_filter( 'woocommerce_order_data_store', 'WC_Custom_Order_Table::order_data_store' );
		} else {
			remove_filter( 'woocommerce_customer_data_store', 'WC_Custom_Order_Table::customer_data_store' );
			remove_filter( 'woocommerce_order_data_store', 'WC_Custom_Order_Table::order_data_store' );
		}
	}

	/**
	 * Generate a $number of orders and return the order IDs in an array.
	 *
	 * @param int $number The number of orders to generate.
	 *
	 * @return array An array of the generated order IDs.
	 */
	protected function generate_orders( $number = 5 ) {
		$orders = array();

		for ( $i = 0; $i < $number; $i++ ) {
			$orders[] = WC_Helper_Order::create_order()->get_id();
		}

		return $orders;
	}

	/**
	 * Given an array of IDs, see how many of those IDs exist in the table.
	 *
	 * @global $wpdb
	 *
	 * @param array $order_ids An array of order IDs to look for.
	 *
	 * @return int The number of matches found in the database.
	 */
	protected function count_orders_in_table_with_ids( $order_ids = array() ) {
		global $wpdb;

		if ( empty( $order_ids ) ) {
			return 0;
		}

		return (int) $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(order_id) FROM {$wpdb->prefix}woocommerce_orders
			WHERE order_id IN (" . implode( ', ', array_fill( 0, count( $order_ids ), '%d' ) ) . ')',
		$order_ids ) );
	}

	/**
	 * Retrieve a single row from the Orders table.
	 *
	 * @global $wpdb
	 *
	 * @param int $order_id The order ID to retrieve.
	 *
	 * @return array|null The contents of the database row or null if the given row doesn't exist.
	 */
	protected function get_order_row( $order_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}woocommerce_orders WHERE order_id = %d",
			$order_id
		), ARRAY_A );
	}

	/**
	 * Determine if the custom orders table exists.
	 *
	 * @global $wpdb
	 */
	protected static function orders_table_exists() {
		global $wpdb;

		return (bool) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM information_schema.tables WHERE table_name = %s LIMIT 1',
			$wpdb->prefix . 'woocommerce_orders'
		) );
	}

	/**
	 * Drop the wp_woocommerce_orders table.
	 *
	 * @global $wpdb
	 */
	protected static function drop_orders_table() {
		global $wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}woocommerce_orders" );

		delete_option( WC_Custom_Order_Table_Install::SCHEMA_VERSION_KEY );
	}

	/**
	 * Emulate deactivating, then subsequently reactivating the plugin.
	 */
	protected static function reactivate_plugin() {
		$plugin = plugin_basename( dirname( __DIR__ ) . '/woocommerce-custom-orders-table.php' );

		do_action( 'deactivate_' . $plugin, false );
		do_action( 'activate_' . $plugin, false );
	}
}
