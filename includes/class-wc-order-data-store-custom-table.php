<?php
/**
 * WooCommerce order data store.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

/**
 * Extend the WC_Order_Data_Store_CPT class, overloading methods that require database access in
 * order to use the new table.
 *
 * Orders are still treated as posts within WordPress, but the meta is stored in a separate table.
 */
class WC_Order_Data_Store_Custom_Table extends WC_Order_Data_Store_CPT {

	/**
	 * Sets meta type to 'order' to use the 'woocommerce_ordermeta' table.
	 *
	 * @var string
	 */
	protected $meta_type = 'order';

	/**
	 * Hook into WooCommerce database queries related to orders.
	 */
	public function __construct() {

		// When creating a WooCommerce order data store request, filter the MySQL query.
		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', 'WooCommerce_Custom_Orders_Table_Filters::filter_database_queries', PHP_INT_MAX, 2 );
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
				wc_custom_order_table()->get_table_name(),
				array(
					'order_id' => $order_id,
				)
			); // WPCS: DB call OK.
		}
	}

	/**
	 * Read order data from the custom orders table.
	 *
	 * If the order does not yet exist, the plugin will attempt to migrate it automatically. This
	 * behavior can be modified via the "wc_custom_order_table_automatic_migration" filter.
	 *
	 * @param WC_Order $order       The order object, passed by reference.
	 * @param object   $post_object The post object.
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
	 * @param WC_Order $order The order object.
	 *
	 * @return array The order row, as an associative array.
	 */
	public function get_order_data_from_table( $order ) {
		global $wpdb;

		$table = wc_custom_order_table()->get_table_name();
		$data  = (array) $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . esc_sql( $table ) . ' WHERE order_id = %d LIMIT 1',
				$order->get_id()
			),
			ARRAY_A
		); // WPCS: DB call OK.

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

		$table      = wc_custom_order_table()->get_table_name();
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
		if ( ! wc_custom_order_table()->row_exists( $order_data['order_id'] ) ) {
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
				'SELECT SUM(o.amount) FROM ' . esc_sql( wc_custom_order_table()->get_table_name() ) . " AS o
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

		$table = wc_custom_order_table()->get_table_name();

		return $wpdb->get_var(
			$wpdb->prepare(
				'SELECT order_id FROM ' . esc_sql( $table ) . ' WHERE order_key = %s',
				$order_key
			)
		); // WPCS: DB call OK.
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
			$table     = wc_custom_order_table()->get_table_name();

			// Find results based on search fields that map to table columns.
			if ( ! empty( $in_table ) ) {
				$columns = array_keys( array_intersect( $mapping, $in_table ) );
				$where   = array();

				foreach ( $columns as $column ) {
					$where[] = "{$column} LIKE %s";
				}

				$order_ids = array_merge(
					$order_ids,
					$wpdb->get_col(
						$wpdb->prepare(
							'SELECT DISTINCT order_id FROM ' . esc_sql( $table ) . ' WHERE ' . implode( ' OR ', $where ),
							array_fill( 0, count( $where ), '%' . $wpdb->esc_like( $term ) . '%' )
						)
					)
				);  // WPCS: DB call OK, Unprepared SQL ok, PreparedSQLPlaceholders replacement count ok.
			}

			// For anything else, fall back to postmeta.
			if ( ! empty( $meta_keys ) ) {
				$order_ids = array_merge(
					$order_ids,
					$wpdb->get_col(
						$wpdb->prepare(
							"
					SELECT DISTINCT order_id FROM {$wpdb->ordermeta}
					WHERE meta_value LIKE %s
					AND meta_key IN (" . implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) ) . ')',
							array_merge(
								array( '%' . $wpdb->esc_like( $term ) . '%' ),
								$meta_keys
							)
						)
					)
				); // WPCS: DB call OK.
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
		); // WPCS: DB call OK.

		// Reduce the array of order IDs to unique values.
		$order_ids = array_unique( $order_ids );

		return apply_filters( 'woocommerce_shop_order_search_results', $order_ids, $term, $search_fields );
	}

	/**
	 * Populate custom table with data from postmeta, for migrations.
	 *
	 * @global $wpdb
	 *
	 * @param WC_Order $order  The order object, passed by reference.
	 * @param bool     $delete Optional. Whether or not the post meta should be deleted. Default
	 *                         is false.
	 *
	 * @return WP_Error|null A WP_Error object if there was a problem populating the order, or null
	 *                       if there were no issues.
	 */
	public function populate_from_meta( &$order, $delete = false ) {
		global $wpdb;

		try {
			$table_data = $this->get_order_data_from_table( $order );
			$order      = WooCommerce_Custom_Orders_Table::populate_order_from_post_meta( $order );

			$this->update_post_meta( $order );
			if ( empty( $wpdb->last_error ) ) {
				// Only save order metadata in new table in case the previous step was ok.
				$order->save_meta_data();
			}
		} catch ( WC_Data_Exception $e ) {
			return new WP_Error( 'woocommerce-custom-order-table-migration', $e->getMessage() );
		}

		if ( $wpdb->last_error ) {
			return new WP_Error( 'woocommerce-custom-order-table-migration', $wpdb->last_error );
		}

		if ( true === $delete ) {
			// Remove the filter to enable `delete_post_meta` access to legacy postmeta table to remove order metadata.
			remove_action( 'delete_post_metadata', 'WooCommerce_Custom_Orders_Table_Meta::map_post_metadata' );
			remove_action( 'get_post_metadata', 'WooCommerce_Custom_Orders_Table_Meta::map_post_metadata' );
			foreach ( get_post_meta( $order->get_id() ) as $meta_key => $meta_value ) {
				delete_post_meta( $order->get_id(), $meta_key );
			}
			add_action( 'delete_post_metadata', 'WooCommerce_Custom_Orders_Table_Meta::map_post_metadata', 10, 5 );
			add_action( 'get_post_metadata', 'WooCommerce_Custom_Orders_Table_Meta::map_post_metadata', 10, 4 );
		}
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

	/**
	 * Implements `update_or_delete_post_meta` method.
	 *
	 * @param WC_Order $order The order doing the operation.
	 * @param string   $meta_key The key of the meta data.
	 * @param string   $meta_value The value of the meta data.
	 */
	protected function update_or_delete_post_meta( $order, $meta_key, $meta_value ) {
		if ( in_array( $meta_value, array( array(), '' ), true ) && ! in_array( $meta_key, $this->must_exist_meta_keys, true ) ) {
			$updated = delete_metadata( $this->meta_type, $order->get_id(), $meta_key, $meta_value );
		} else {
			$updated = update_metadata( $this->meta_type, $order->get_id(), $meta_key, $meta_value );
		}

		return (bool) $updated;
	}

	/**
	 * Implements has_meta() equivalent because it has `postmeta` table hardcoded.
	 *
	 * Todo: paramatrize 'has_meta()' to accept any kind of meta.
	 *
	 * @param int $order_id The ID of the post of the order.
	 */
	public static function order_has_meta( $order_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value, meta_id, order_id
				FROM $wpdb->ordermeta WHERE order_id = %d",
				$order_id
			),
			ARRAY_A
		);
	}
}
