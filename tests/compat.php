<?php
/**
 * This file contains work-arounds for older versions of the WooCommerce test suite.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */


/**
 * Convert all instances of "USA" in tests to use the ISO 3166-1 alpha-2 (2 character) standard,
 * (e.g. "US").
 *
 * @ticket https://github.com/woocommerce/woocommerce/pull/20222
 */
if ( version_compare( WC_VERSION, '3.4.4', '<=' ) ) {
	add_filter( 'woocommerce_before_order_object_save', 'tests_truncate_order_data_store_country_codes' );
	add_filter( 'woocommerce_order_query_args', 'tests_truncate_order_query_country_codes' );
}

/**
 * Truncate instances of "USA" to "US" for billing/shipping country codes.
 *
 * @param WC_Order $order The order object.
 *
 * @return WC_Order The filtered WC_Order object.
 */
function tests_truncate_order_data_store_country_codes( $order ) {
	foreach ( array( 'billing_country', 'shipping_country' ) as $prop ) {
		if ( 'USA' === call_user_func( array( $order, 'get_' . $prop ) ) ) {
			call_user_func( array( $order, 'set_' . $prop ), 'US' );
		}
	}

	return $order;
}

/**
 * When conducting a WC_Order_Query, shorten "USA" to "US".
 *
 * @param array $args An array of query arguments.
 *
 * @return array The filtered query arguments array.
 */
function tests_truncate_order_query_country_codes( $args ) {
	foreach ( array( 'billing_country', 'shipping_country' ) as $prop ) {
		if ( isset( $args[ $prop ] ) && 'USA' === $args[ $prop ] ) {
			$args[ $prop ] = 'US';
		}
	}

	return $args;
}
