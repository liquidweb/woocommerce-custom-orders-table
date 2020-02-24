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
		$wpdb->query( 'DELETE FROM ' . esc_sql( wc_custom_order_table()->get_orders_table_name() ) );
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
		} else {
			remove_filter( 'woocommerce_customer_data_store', 'WooCommerce_Custom_Orders_Table::customer_data_store' );
			remove_filter( 'woocommerce_order_data_store', 'WooCommerce_Custom_Orders_Table::order_data_store' );
			remove_filter( 'woocommerce_order-refund_data_store', 'WooCommerce_Custom_Orders_Table::order_refund_data_store' );
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
	 * Retrieve a single row from the orders table.
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
			'SELECT * FROM ' . esc_sql( wc_custom_order_table()->get_orders_table_name() ) . ' WHERE order_id = %d',
			$order_id
		), ARRAY_A );
	}

	/**
	 * Retrieve a single row from the refunds table.
	 *
	 * @global $wpdb
	 *
	 * @param int $refund_id The refund ID to retrieve.
	 *
	 * @return array|null The contents of the database row or null if the given row doesn't exist.
	 */
	protected function get_refund_row( $refund_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . esc_sql( WC_Order_Refund_Data_Store_Custom_Table::get_custom_table_name() ) . ' WHERE refund_id = %d',
			$refund_id
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
