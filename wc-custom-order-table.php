<?php
/**
 * Plugin Name: WooCommerce - Custom Order Table
 * Description: Store WooCommerce order data in a custom table.
 * Version: 1.0.0
 * Requires at least: 4.7
 * Tested up to: 4.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define('WC_CUSTOM_ORDER_TABLE_URL', plugin_dir_url(__FILE__));
define('WC_CUSTOM_ORDER_TABLE_PATH', plugin_dir_path(__FILE__));

if ( file_exists( WC_CUSTOM_ORDER_TABLE_PATH . 'vendor/autoload_52.php' ) ) {
    require( WC_CUSTOM_ORDER_TABLE_PATH . 'vendor/autoload_52.php' );
}

function wc_custom_order_table_install() {
    $installer = new WC_Custom_Order_Table_Install();
    register_activation_hook( __FILE__, array( $installer, 'activate' ) );
}

register_activation_hook( __FILE__, 'wc_custom_order_table_install' );

/**
 * @return WC_Custom_Order_Table
 */
function wc_custom_order_table() {
	global $wc_custom_order_table;

	if( ! $wc_custom_order_table instanceof WC_Custom_Order_Table ) {
		$wc_custom_order_table = new WC_Custom_Order_Table;
		$wc_custom_order_table->setup();
	}

	return $wc_custom_order_table;
}

add_action('plugins_loaded', 'wc_custom_order_table');