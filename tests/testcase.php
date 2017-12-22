<?php
/**
 * Base test case for WooCommerce Custom Order Tables.
 *
 * @package Woocommerce_Order_Tables
 * @author  Liquid Web
 */

class TestCase extends WP_UnitTestCase {

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
	}

	/**
	 * Emulate deactivating, then subsequently reactivating the plugin.
	 */
	protected static function reactivate_plugin() {
		$plugin = plugin_basename( dirname( __DIR__ ) . '/wc-custom-order-table.php' );

		do_action( 'deactivate_' . $plugin, false );
		do_action( 'activate_' . $plugin, false );
	}
}
