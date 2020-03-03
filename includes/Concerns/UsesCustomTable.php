<?php
/**
 * Shared functionality for data stores that use custom tables.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

namespace LiquidWeb\WooCommerceCustomOrdersTable\Concerns;

use WC_Abstract_Order;
use WC_Data_Exception;
use WooCommerce_Custom_Orders_Table;
use WP_Error;

trait UsesCustomTable {

	/**
	 * Read data from the custom table.
	 *
	 * If the corresponding row does not yet exist, the plugin will attempt to migrate it
	 * automatically. This behavior can be modified by returning FALSE from the
	 * "wc_custom_order_table_automatic_migration" filter.
	 *
	 * @param WC_Abstract_Order $order       The order object, passed by reference.
	 * @param object            $post_object The post object.
	 */
	protected function read_order_data( &$order, $post_object ) {
		$data = $this->get_order_data_from_table( $order );

		if ( ! empty( $data ) ) {
			$order->set_props( $data );
		} else {
			/**
			 * Toggle the ability for WooCommerce Custom Orders Table to automatically migrate orders.
			 *
			 * @param bool $migrate Whether or not orders should automatically be migrated once they
			 *                      have been loaded.
			 */
			$migrate = apply_filters( 'wc_custom_order_table_automatic_migration', true );

			if ( $migrate ) {
				$this->populate_from_meta( $order );
			}
		}
	}

	/**
	 * Retrieve a single order from the database.
	 *
	 * @global $wpdb
	 *
	 * @param WC_Abstract_Order $order The order object.
	 *
	 * @return array The order row, as an associative array.
	 */
	public function get_order_data_from_table( $order ) {
		global $wpdb;

		$data = (array) $wpdb->get_row(
			$wpdb->prepare(
				'
				SELECT * FROM ' . esc_sql( self::get_custom_table_name() ) . '
				WHERE order_id = %d LIMIT 1
				',
				$order->get_id()
			),
			ARRAY_A
		);

		// Return early if there's no matching row in the custom table.
		if ( empty( $data ) ) {
			return array();
		}

		// Expand anything that might need assistance.
		if ( isset( $data['prices_include_tax'] ) ) {
			$data['prices_include_tax'] = wc_string_to_bool( $data['prices_include_tax'] );
		}

		// @todo Apply custom data via filter.
		$post                  = get_post( $order->get_id() );
		$data['customer_note'] = $post->post_excerpt;

		return $data;
	}

	/**
	 * Populate custom table with data from postmeta, for migrations.
	 *
	 * @global $wpdb
	 *
	 * @param WC_Abstract_Order $order  The order object, passed by reference.
	 * @param bool              $delete Optional. Whether or not the post meta should be deleted.
	 *                                  Default is false.
	 *
	 * @return WP_Error|null A WP_Error object if there was a problem populating the order, or null
	 *                       if there were no issues.
	 */
	public function populate_from_meta( WC_Abstract_Order &$order, $delete = false ) {
		global $wpdb;

		try {
			$table_data = $this->get_order_data_from_table( $order );
			$order      = WooCommerce_Custom_Orders_Table::populate_order_from_post_meta( $order );

			$this->update_post_meta( $order );
		} catch ( WC_Data_Exception $e ) {
			return new WP_Error( 'woocommerce-custom-order-table-migration', $e->getMessage() );
		}

		if ( $wpdb->last_error ) {
			return new WP_Error( 'woocommerce-custom-order-table-migration', $wpdb->last_error );
		}

		if ( true === $delete ) {
			foreach ( self::map_columns_to_post_meta_keys() as $column => $meta_key ) {
				delete_post_meta( $order->get_id(), $meta_key );
			}
		}
	}

	/**
	 * Determine if the given primary key already exists in the custom table.
	 *
	 * @global $wpdb
	 *
	 * @param int $order_id The order ID.
	 *
	 * @return bool True if a row for $order_id is already present, false otherwise.
	 */
	public function row_exists( $order_id ) {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'
					SELECT COUNT(*) FROM ' . esc_sql( self::get_custom_table_name() ) . '
					WHERE order_id = %d
				',
				$order_id
			)
		);
	}

	/**
	 * Delete the given row by ID.
	 *
	 * @global $wpdb
	 *
	 * @param int $order_id The order/refund row to delete.
	 *
	 * @return bool True if the row was deleted, false otherwise.
	 */
	public function delete_row( $order_id ) {
		global $wpdb;

		return (bool) $wpdb->delete( self::get_custom_table_name(), [
			'order_id' => $order_id,
		] );
	}

	/**
	 * Retrieve the name of the custom table for this data store.
	 *
	 * @global $wpdb
	 *
	 * @return string The custom table used by this data store.
	 */
	public static function get_custom_table_name() {
		global $wpdb;

		/**
		 * Filter the WooCommerce orders table name.
		 *
		 * @param string $table The WooCommerce orders table name.
		 */
		return apply_filters( 'wc_custom_orders_table_name', "{$wpdb->prefix}woocommerce_orders" );
	}

	/**
	 * Retrieve a mapping of database columns to default WooCommerce post-meta keys.
	 *
	 * @return array
	 */
	public static function map_columns_to_post_meta_keys() {
		return [
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

			'amount'               => '_refund_amount',
			'reason'               => '_refund_reason',
			'refunded_by'          => '_refunded_by',
			'refunded_payment'     => '_refunded_payment',
		];
	}
}
