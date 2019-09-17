<?php
/**
 * Base test case for WooCommerce Custom Orders Table.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

class TestCase extends WC_Unit_Test_Case {

	/**
	 * Ensure each testcase starts off with a clean table installations.
	 *
	 * @beforeClass
	 */
	public static function remove_table_version_from_options() {
		delete_option( WooCommerce_Custom_Orders_Table_Install::SCHEMA_VERSION_KEY );
	}

	/**
	 * Delete all data from the orders table after each test.
	 *
	 * @after
	 *
	 * @global $wpdb
	 */
	protected function truncate_table() {
		global $wpdb;

		WooCommerce_Custom_Orders_Table_Install::activate();

		$wpdb->suppress_errors( false );
		$wpdb->query( 'DELETE FROM ' . esc_sql( wc_custom_order_table()->get_table_name() ) );
	}

	/**
	 * Toggle whether or not the custom table should be used.
	 *
	 * @param bool $enabled Optional. Whether or not the custom table should be used. Default is true.
	 */
	protected function toggle_use_custom_table( $enabled = true ) {
		if ( $enabled ) {
			add_filter( 'woocommerce_customer_data_store', 'WooCommerce_Custom_Orders_Table::customer_data_store' );
			add_filter( 'woocommerce_order_data_store', 'WooCommerce_Custom_Orders_Table::order_data_store' );
			add_filter( 'woocommerce_order-refund_data_store', 'WooCommerce_Custom_Orders_Table::order_refund_data_store' );
			add_action( 'add_post_metadata', 'WooCommerce_Custom_Orders_Table_Meta::map_post_metadata', 10, 5 );
			add_action( 'update_post_metadata', 'WooCommerce_Custom_Orders_Table_Meta::map_post_metadata', 10, 5 );
			add_action( 'delete_post_metadata', 'WooCommerce_Custom_Orders_Table_Meta::map_post_metadata', 10, 5 );
			add_action( 'get_post_metadata', 'WooCommerce_Custom_Orders_Table_Meta::map_post_metadata', 10, 4 );
		} else {
			remove_filter( 'woocommerce_customer_data_store', 'WooCommerce_Custom_Orders_Table::customer_data_store' );
			remove_filter( 'woocommerce_order_data_store', 'WooCommerce_Custom_Orders_Table::order_data_store' );
			remove_filter( 'woocommerce_order-refund_data_store', 'WooCommerce_Custom_Orders_Table::order_refund_data_store' );
			remove_action( 'add_post_metadata', 'WooCommerce_Custom_Orders_Table_Meta::map_post_metadata' );
			remove_action( 'update_post_metadata', 'WooCommerce_Custom_Orders_Table_Meta::map_post_metadata' );
			remove_action( 'delete_post_metadata', 'WooCommerce_Custom_Orders_Table_Meta::map_post_metadata' );
			remove_action( 'get_post_metadata', 'WooCommerce_Custom_Orders_Table_Meta::map_post_metadata' );
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

		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(order_id) FROM ' . esc_sql( wc_custom_order_table()->get_table_name() ) . '
			WHERE order_id IN (' . implode( ', ', array_fill( 0, count( (array) $order_ids ), '%d' ) ) . ')',
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
			'SELECT * FROM ' . esc_sql( wc_custom_order_table()->get_table_name() ) . ' WHERE order_id = %d',
			$order_id
		), ARRAY_A );
	}

	/**
	 * Emulate deactivating, then subsequently reactivating the plugin.
	 */
	protected static function reactivate_plugin() {
		$plugin = basename( dirname( __DIR__ ) ) . '/woocommerce-custom-orders-table.php';

		do_action( 'deactivate_' . $plugin, false );
		do_action( 'activate_' . $plugin, false );
	}
}
