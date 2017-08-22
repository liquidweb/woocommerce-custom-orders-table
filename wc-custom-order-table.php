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

function wc_custom_order_table() {
	global $wc_custom_order_table;

	if( ! $wc_custom_order_table instanceof WC_Custom_Order_Table ) {
		$wc_custom_order_table = new WC_Custom_Order_Table;
	}

	return $wc_custom_order_table;
}

wc_custom_order_table();