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
				SELECT * FROM ' . esc_sql( $this->get_custom_table_name() ) . '
				WHERE ' . esc_sql( $this->custom_table_primary_key ) . ' = %d LIMIT 1
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
			foreach ( WooCommerce_Custom_Orders_Table::get_postmeta_mapping() as $column => $meta_key ) {
				delete_post_meta( $order->get_id(), $meta_key );
			}
		}
	}

	/**
	 * Determine if the given primary key already exists in the custom table.
	 *
	 * @global $wpdb
	 *
	 * @param int $primary_key The primary key.
	 *
	 * @return bool True if a row for $primary_key is already present, false otherwise.
	 */
	public function row_exists( $primary_key ) {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'
					SELECT COUNT(*) FROM ' . esc_sql( $this->get_custom_table_name() ) . '
					WHERE ' . esc_sql( $this->custom_table_primary_key ) . ' = %d
				',
				$primary_key
			)
		);
	}

	/**
	 * Delete the given row by ID.
	 *
	 * @global $wpdb
	 *
	 * @param int $primary_key The row to delete.
	 *
	 * @return bool True if the row was deleted, false otherwise.
	 */
	public function delete_row( $primary_key ) {
		global $wpdb;

		return (bool) $wpdb->delete(
			$this->get_custom_table_name(),
			[
				$this->custom_table_primary_key => $primary_key,
			]
		);
	}

	/**
	 * Retrieve the name of the custom table for this data store.
	 *
	 * @return string The custom table used by this data store.
	 */
	abstract protected function get_custom_table_name();
}
