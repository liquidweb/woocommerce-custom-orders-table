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
	 * Retrieve the database table column => post_meta mapping.
	 *
	 * @return array An array of database columns and their corresponding post_meta keys.
	 */
	public static function get_postmeta_mapping() {
		return array(
			'order_key'            => '_order_key',
			'customer_id'          => '_customer_user',
			'payment_method'       => '_payment_method',
			'payment_method_title' => '_payment_method_title',
			'transaction_id'       => '_transaction_id',
			'customer_ip_address'  => '_customer_ip_address',
			'customer_user_agent'  => '_customer_user_agent',
			'created_via'          => '_created_via',
			'date_completed'       => '_date_completed',
			'date_paid'            => '_date_paid',
			'cart_hash'            => '_cart_hash',

			'billing_index'        => '_billing_address_index',
			'billing_first_name'   => '_billing_first_name',
			'billing_last_name'    => '_billing_last_name',
			'billing_company'      => '_billing_company',
			'billing_address_1'    => '_billing_address_1',
			'billing_address_2'    => '_billing_address_2',
			'billing_city'         => '_billing_city',
			'billing_state'        => '_billing_state',
			'billing_postcode'     => '_billing_postcode',
			'billing_country'      => '_billing_country',
			'billing_email'        => '_billing_email',
			'billing_phone'        => '_billing_phone',

			'shipping_index'       => '_shipping_address_index',
			'shipping_first_name'  => '_shipping_first_name',
			'shipping_last_name'   => '_shipping_last_name',
			'shipping_company'     => '_shipping_company',
			'shipping_address_1'   => '_shipping_address_1',
			'shipping_address_2'   => '_shipping_address_2',
			'shipping_city'        => '_shipping_city',
			'shipping_state'       => '_shipping_state',
			'shipping_postcode'    => '_shipping_postcode',
			'shipping_country'     => '_shipping_country',

			'discount_total'       => '_cart_discount',
			'discount_tax'         => '_cart_discount_tax',
			'shipping_total'       => '_order_shipping',
			'shipping_tax'         => '_order_shipping_tax',
			'cart_tax'             => '_order_tax',
			'total'                => '_order_total',

			'version'              => '_order_version',
			'currency'             => '_order_currency',
			'prices_include_tax'   => '_prices_include_tax',
		);
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
