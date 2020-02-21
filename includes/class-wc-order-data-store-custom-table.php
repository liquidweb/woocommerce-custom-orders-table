<?php
/**
 * WooCommerce order data store.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

use LiquidWeb\WooCommerceCustomOrdersTable\Concerns\UsesCustomTable;

/**
 * Extend the WC_Order_Data_Store_CPT class, overloading methods that require database access in
 * order to use the new table.
 *
 * Orders are still treated as posts within WordPress, but the meta is stored in a separate table.
 */
class WC_Order_Data_Store_Custom_Table extends WC_Order_Data_Store_CPT {
	use UsesCustomTable;

	/**
	 * The primary key used in the custom table.
	 *
	 * @var string
	 */
	protected $custom_table_primary_key = 'order_id';

	/**
	 * Hook into WooCommerce database queries related to orders.
	 */
	public function __construct() {

		// When creating a WooCommerce order data store request, filter the MySQL query.
		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', 'WooCommerce_Custom_Orders_Table_Filters::filter_database_queries', PHP_INT_MAX, 2 );
	}

	/**
	 * Retrieve the name of the custom table for this data store.
	 *
	 * @return string The custom table used by this data store.
	 */
	protected function get_custom_table_name() {
		return wc_custom_order_table()->get_orders_table_name();
	}

	/**
	 * Delete an order from the database.
	 *
	 * @global $wpdb
	 *
	 * @param WC_Order $order The order object, passed by reference.
	 * @param array    $args  Additional arguments to pass to the delete method.
	 */
	public function delete( &$order, $args = array() ) {
		global $wpdb;

		$order_id = $order->get_id();

		parent::delete( $order, $args );

		// Delete the database row if force_delete is true.
		if ( isset( $args['force_delete'] ) && $args['force_delete'] ) {
			$wpdb->delete(
				wc_custom_order_table()->get_orders_table_name(),
				array(
					'order_id' => $order_id,
				)
			);
		}
	}

	/**
	 * Retrieve a single order from the database.
	 *
	 * @global $wpdb
	 *
	 * @param WC_Order $order The order object.
	 *
	 * @return array The order row, as an associative array.
	 */
	public function get_order_data_from_table( $order ) {
		global $wpdb;

		$table = wc_custom_order_table()->get_orders_table_name();
		$data  = (array) $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . esc_sql( $table ) . ' WHERE order_id = %d LIMIT 1',
				$order->get_id()
			),
			ARRAY_A
		);

		// Return early if there's no matching row in the orders table.
		if ( empty( $data ) ) {
			return array();
		}

		$post = get_post( $order->get_id() );

		// Expand anything that might need assistance.
		if ( isset( $data['prices_include_tax'] ) ) {
			$data['prices_include_tax'] = wc_string_to_bool( $data['prices_include_tax'] );
		}

		// Append additional data.
		$data['customer_note'] = $post->post_excerpt;

		return $data;
	}

	/**
	 * Helper method that updates all the post meta for an order based on its settings in the
	 * WC_Order class.
	 *
	 * @global $wpdb
	 *
	 * @param WC_Order $order The order object, passed by reference.
	 */
	protected function update_post_meta( &$order ) {
		global $wpdb;

		$table      = wc_custom_order_table()->get_orders_table_name();
		$changes    = array();
		$order_key  = $order->get_order_key( 'edit' );
		$order_data = array(
			'order_id'             => $order->get_id(),
			'order_key'            => $order_key ? $order_key : null,
			'customer_id'          => $order->get_customer_id( 'edit' ),
			'payment_method'       => $order->get_payment_method( 'edit' ),
			'payment_method_title' => $order->get_payment_method_title( 'edit' ),
			'transaction_id'       => $order->get_transaction_id( 'edit' ),
			'customer_ip_address'  => $order->get_customer_ip_address( 'edit' ),
			'customer_user_agent'  => $order->get_customer_user_agent( 'edit' ),
			'created_via'          => $order->get_created_via( 'edit' ),
			'date_completed'       => $order->get_date_completed( 'edit' ),
			'date_paid'            => $order->get_date_paid( 'edit' ),
			'cart_hash'            => $order->get_cart_hash( 'edit' ),

			'billing_index'        => implode( ' ', $order->get_address( 'billing' ) ),
			'billing_first_name'   => $order->get_billing_first_name( 'edit' ),
			'billing_last_name'    => $order->get_billing_last_name( 'edit' ),
			'billing_company'      => $order->get_billing_company( 'edit' ),
			'billing_address_1'    => $order->get_billing_address_1( 'edit' ),
			'billing_address_2'    => $order->get_billing_address_2( 'edit' ),
			'billing_city'         => $order->get_billing_city( 'edit' ),
			'billing_state'        => $order->get_billing_state( 'edit' ),
			'billing_postcode'     => $order->get_billing_postcode( 'edit' ),
			'billing_country'      => $order->get_billing_country( 'edit' ),
			'billing_email'        => $order->get_billing_email( 'edit' ),
			'billing_phone'        => $order->get_billing_phone( 'edit' ),

			'shipping_index'       => implode( ' ', $order->get_address( 'shipping' ) ),
			'shipping_first_name'  => $order->get_shipping_first_name( 'edit' ),
			'shipping_last_name'   => $order->get_shipping_last_name( 'edit' ),
			'shipping_company'     => $order->get_shipping_company( 'edit' ),
			'shipping_address_1'   => $order->get_shipping_address_1( 'edit' ),
			'shipping_address_2'   => $order->get_shipping_address_2( 'edit' ),
			'shipping_city'        => $order->get_shipping_city( 'edit' ),
			'shipping_state'       => $order->get_shipping_state( 'edit' ),
			'shipping_postcode'    => $order->get_shipping_postcode( 'edit' ),
			'shipping_country'     => $order->get_shipping_country( 'edit' ),

			'discount_total'       => $order->get_discount_total( 'edit' ),
			'discount_tax'         => $order->get_discount_tax( 'edit' ),
			'shipping_total'       => $order->get_shipping_total( 'edit' ),
			'shipping_tax'         => $order->get_shipping_tax( 'edit' ),
			'cart_tax'             => $order->get_cart_tax( 'edit' ),
			'total'                => $order->get_total( 'edit' ),

			'version'              => $order->get_version( 'edit' ),
			'currency'             => $order->get_currency( 'edit' ),
			'prices_include_tax'   => wc_bool_to_string( $order->get_prices_include_tax( 'edit' ) ),
		);

		// Convert dates to timestamps, if they exist.
		foreach ( array( 'date_completed', 'date_paid' ) as $date ) {
			if ( $order_data[ $date ] instanceof WC_DateTime ) {
				$order_data[ $date ] = $order_data[ $date ]->getTimestamp();
			}
		}

		// Insert or update the database record.
		if ( ! $this->row_exists( $order_data['order_id'] ) ) {
			$inserted = $wpdb->insert( $table, $order_data ); // WPCS: DB call OK.

			if ( 1 !== $inserted ) {
				return;
			}

			/*
			 * WooCommerce prior to 3.3 lacks some necessary filters to entirely move order details
			 * into a custom database table. If the site is running WooCommerce < 3.3.0, store the
			 * billing email and customer ID in the post meta table as well, for backwards-compatibility.
			 */
			if ( version_compare( WC()->version, '3.3.0', '<' ) ) {
				update_post_meta( $order->get_id(), '_billing_email', $order->get_billing_email() );
				update_post_meta( $order->get_id(), '_customer_user', $order->get_customer_id() );
			}
		} else {
			$changes = array_intersect_key( $order_data, $order->get_changes() );

			/*
			 * WC_Order::get_changes() will mark all address fields as changed if one has changed.
			 *
			 * If any of these fields are present, be sure we update the index column.
			 */
			if ( isset( $changes['billing_first_name'] ) ) {
				$changes['billing_index'] = $order_data['billing_index'];
			}

			if ( isset( $changes['shipping_first_name'] ) ) {
				$changes['shipping_index'] = $order_data['shipping_index'];
			}

			if ( ! empty( $changes ) ) {
				$wpdb->update( $table, $changes, array( 'order_id' => $order->get_id() ) ); // WPCS: DB call OK.
			}
		}

		$updated_props = array_keys( (array) $changes );

		// If customer changed, update any downloadable permissions.
		$customer_props = array( 'customer_user', 'billing_email' );

		if ( array_intersect( $customer_props, $updated_props ) ) {
			$data_store = WC_Data_Store::load( 'customer-download' );
			$data_store->update_user_by_order_id( $order->get_id(), $order->get_customer_id(), $order->get_billing_email() );
		}

		do_action( 'woocommerce_order_object_updated_props', $order, $updated_props );
	}

	/**
	 * Get amount already refunded.
	 *
	 * @global $wpdb
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return float The amount already refunded.
	 */
	public function get_total_refunded( $order ) {
		global $wpdb;

		$total = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT SUM(o.amount) FROM ' . esc_sql( wc_custom_order_table()->get_orders_table_name() ) . " AS o
				INNER JOIN $wpdb->posts AS p ON ( p.post_type = 'shop_order_refund' AND p.post_parent = %d )
				WHERE o.order_id = p.ID",
				$order->get_id()
			)
		);

		return floatval( $total );
	}

	/**
	 * Finds an order ID based on its order key.
	 *
	 * @param string $order_key The order key.
	 *
	 * @return int The ID of an order, or 0 if the order could not be found
	 */
	public function get_order_id_by_order_key( $order_key ) {
		global $wpdb;

		$table = wc_custom_order_table()->get_orders_table_name();

		return $wpdb->get_var(
			$wpdb->prepare(
				'SELECT order_id FROM ' . esc_sql( $table ) . ' WHERE order_key = %s',
				$order_key
			)
		);
	}

	/**
	 * Search order data for a term and return ids.
	 *
	 * @param  string $term The search term.
	 *
	 * @return array An array of order IDs.
	 */
	public function search_orders( $term ) {
		global $wpdb;

		/**
		 * Searches on meta data can be slow - this lets you choose what fields to search.
		 * 3.0.0 added _billing_address and _shipping_address meta which contains all address data to make this faster.
		 * This however won't work on older orders unless updated, so search a few others (expand this using the filter if needed).
		 *
		 * @var array
		 */
		$search_fields = array_map(
			'wc_clean',
			apply_filters(
				'woocommerce_shop_order_search_fields',
				array(
					'_billing_address_index',
					'_shipping_address_index',
					'_billing_last_name',
					'_billing_email',
				)
			)
		);
		$term          = wc_clean( $term );
		$order_ids     = array();

		// Treat a numeric search term as an order ID.
		if ( is_numeric( $term ) ) {
			$order_ids[] = absint( $term );
		}

		// Search for order meta fields.
		if ( ! empty( $search_fields ) ) {
			$mapping   = WooCommerce_Custom_Orders_Table::get_postmeta_mapping();
			$in_table  = array_intersect( $search_fields, $mapping );
			$meta_keys = array_diff( $search_fields, $in_table );
			$table     = wc_custom_order_table()->get_orders_table_name();

			// Find results based on search fields that map to table columns.
			if ( ! empty( $in_table ) ) {
				$columns = array_keys( array_intersect( $mapping, $in_table ) );
				$where   = array();

				foreach ( $columns as $column ) {
					$where[] = "{$column} LIKE %s";
				}

				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$order_ids = array_merge(
					$order_ids,
					$wpdb->get_col(
						$wpdb->prepare(
							'SELECT DISTINCT order_id FROM ' . esc_sql( $table ) . ' WHERE ' . implode( ' OR ', $where ),
							array_fill( 0, count( $where ), '%' . $wpdb->esc_like( $term ) . '%' )
						)
					)
				);
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			}

			// For anything else, fall back to postmeta.
			if ( ! empty( $meta_keys ) ) {
				$order_ids = array_merge(
					$order_ids,
					$wpdb->get_col(
						$wpdb->prepare(
							"
					SELECT DISTINCT post_id FROM {$wpdb->postmeta}
					WHERE meta_value LIKE %s
					AND meta_key IN (" . implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) ) . ')',
							array_merge(
								array( '%' . $wpdb->esc_like( $term ) . '%' ),
								$meta_keys
							)
						)
					)
				);
			}
		}

		// Search item names.
		$order_ids = array_merge(
			$order_ids,
			$wpdb->get_col(
				$wpdb->prepare(
					"
					SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items
					WHERE order_item_name LIKE %s",
					'%' . $wpdb->esc_like( $term ) . '%'
				)
			)
		);

		// Reduce the array of order IDs to unique values.
		$order_ids = array_unique( $order_ids );

		return apply_filters( 'woocommerce_shop_order_search_results', $order_ids, $term, $search_fields );
	}

	/**
	 * Populate order postmeta from a custom table, for rolling back.
	 *
	 * @param WC_Order $order The order object, passed by reference.
	 */
	public function backfill_postmeta( &$order ) {
		$data = $this->get_order_data_from_table( $order );

		if ( empty( $data ) ) {
			return;
		}

		if ( isset( $data['prices_include_tax'] ) ) {
			$data['prices_include_tax'] = wc_bool_to_string( $data['prices_include_tax'] );
		}

		foreach ( WooCommerce_Custom_Orders_Table::get_postmeta_mapping() as $column => $meta_key ) {
			if ( isset( $data[ $column ] ) ) {
				update_post_meta( $order->get_id(), $meta_key, $data[ $column ] );
			}
		}
	}
}
