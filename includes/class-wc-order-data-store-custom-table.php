<?php
/**
 * WooCommerce order data store.
 *
 * @package WooCommerce_Custom_Order_Tables
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
	 * Set to true when creating so we know to insert meta data.
	 *
	 * @var boolean
	 */
	protected $creating = false;

	/**
	 * Hook into WooCommerce database queries related to orders.
	 */
	public function __construct() {

		// When creating a WooCommerce order data store request, filter the MySQL query.
		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', __CLASS__ . '::filter_database_queries', 10, 2 );
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
	 * Create a new order in the database.
	 *
	 * @param WC_Order $order The order object, passed by reference.
	 */
	public function create( &$order ) {
		$this->creating = true;

		parent::create( $order );
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
				"{$wpdb->prefix}woocommerce_orders",
				array( 'order_id' => $order_id )
			); // WPCS: DB call OK.
		}
	}

	/**
	 * Read order data.
	 *
	 * @param WC_Order $order The order object, passed by reference.
	 * @param object   $post_object The post object.
	 */
	protected function read_order_data( &$order, $post_object ) {
		global $wpdb;

		$data = $this->get_order_data_from_table( $order );

		if ( ! empty( $data ) ) {
			$order->set_props( $data );

		} else {
			// Automatically backfill order data from meta, but allow for disabling.
			if ( apply_filters( 'wc_custom_order_table_automatic_migration', true ) ) {
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
	 * @return object The order row, as an associative array.
	 */
	public function get_order_data_from_table( $order ) {
		global $wpdb;

		$table = wc_custom_order_table()->get_table_name();
		$data  = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . esc_sql( $table ) . ' WHERE order_id = %d LIMIT 1',
			$order->get_id()
		), ARRAY_A ); // WPCS: DB call OK.

		// If no matches were found, this record needs to be created.
		if ( null === $data ) {
			$this->creating = true;

			return array();
		}

		// Expand anything that might need assistance.
		$data['prices_include_tax'] = wc_string_to_bool( $data['prices_include_tax'] );

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
		$order_data = array(
			'order_id'             => $order->get_id( 'edit' ),
			'order_key'            => $order->get_order_key( 'edit' ),
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
		if ( $this->creating ) {
			$wpdb->insert( $table, $order_data ); // WPCS: DB call OK.

			$this->creating = false;

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
	 * Finds an order ID based on its order key.
	 *
	 * @param string $order_key The order key.
	 *
	 * @return int The ID of an order, or 0 if the order could not be found
	 */
	public function get_order_id_by_order_key( $order_key ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare(
			"SELECT order_id FROM {$wpdb->prefix}woocommerce_orders WHERE order_key = %s",
			$order_key
		) ); // WPCS: DB call OK.
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
			'wc_clean', apply_filters(
				'woocommerce_shop_order_search_fields', array(
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
			$mapping   = self::get_postmeta_mapping();
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

				$order_ids = array_merge( $order_ids, $wpdb->get_col( $wpdb->prepare(
					'SELECT DISTINCT order_id FROM ' . esc_sql( $table ) . ' WHERE ' . implode( ' OR ', $where ),
					array_fill( 0, count( $where ), '%' . $wpdb->esc_like( $term ) . '%' )
				) ) );  // WPCS: DB call OK, Unprepared SQL ok, PreparedSQLPlaceholders replacement count ok.
			}

			// For anything else, fall back to postmeta.
			if ( ! empty( $meta_keys ) ) {
				$order_ids = array_merge( $order_ids, $wpdb->get_col( $wpdb->prepare( "
					SELECT DISTINCT post_id FROM {$wpdb->postmeta}
					WHERE meta_value LIKE %s
					AND meta_key IN (" . implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) ) . ')',
					array_merge(
						array( '%' . $wpdb->esc_like( $term ) . '%' ),
						$meta_keys
					)
				) ) ); // WPCS: DB call OK.
			}
		}

		// Search item names.
		$order_ids = array_merge( $order_ids, $wpdb->get_col( $wpdb->prepare( "
			SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items
			WHERE order_item_name LIKE %s",
			'%' . $wpdb->esc_like( $term ) . '%'
		) ) ); // WPCS: DB call OK.

		// Reduce the array of order IDs to unique values.
		$order_ids = array_unique( $order_ids );

		return apply_filters( 'woocommerce_shop_order_search_results', $order_ids, $term, $search_fields );
	}

	/**
	 * Populate custom table with data from postmeta, for migrations.
	 *
	 * @param WC_Order $order  The order object, passed by reference.
	 * @param bool     $save   Optional. Whether or not the post meta should be updated. Default
	 *                         is true.
	 * @param bool     $delete Optional. Whether or not the post meta should be deleted. Default
	 *                         is false.
	 *
	 * @return WC_Order the order object.
	 */
	public function populate_from_meta( &$order, $save = true, $delete = false ) {
		$table_data = $this->get_order_data_from_table( $order );
		$original_creating = $this->creating;

		if ( is_null( $table_data ) ) {
			$this->creating = true;
		}

		foreach ( self::get_postmeta_mapping() as $column => $meta_key ) {
			$meta = get_post_meta( $order->get_id(), $meta_key, true );

			if ( empty( $table_data->$column ) && ! empty( $meta ) ) {
				switch ( $column ) {
					case 'billing_index':
					case 'shipping_index';
						break;

					case 'prices_include_tax':
						$order->set_prices_include_tax( 'yes' === $meta );
						break;

					default:
						$order->{"set_{$column}"}( $meta );
				}
			}
		}

		if ( true === $save ) {
			$this->update_post_meta( $order );
		}

		if ( true === $delete ) {
			foreach ( self::get_postmeta_mapping() as $column => $meta_key ) {
				delete_post_meta( $order->get_id(), $meta_key );
			}
		}

		$this->creating = $original_creating;

		return $order;
	}

	/**
	 * Populate order postmeta from a custom table, for rolling back.
	 *
	 * @param WC_Order $order The order object, passed by reference.
	 */
	public function backfill_postmeta( &$order ) {
		$data = $this->get_order_data_from_table( $order );

		if ( is_null( $data ) ) {
			return;
		}

		foreach ( self::get_postmeta_mapping() as $column => $meta_key ) {
			if ( isset( $data->$column ) ) {
				update_post_meta( $order->get_id(), $meta_key, $data->$column );
			}
		}
	}

	/**
	 * Determine if any filters are required on the MySQL query and, if so, apply them.
	 *
	 * @param array $query_args The arguments to be passed to WP_Query.
	 * @param array $query_vars The raw query vars passed to build the query.
	 *
	 * @return array The potentially-filtered $query_args array.
	 */
	public static function filter_database_queries( $query_args, $query_vars ) {
		$query_args['wc_order_meta_query']  = array();
		$query_args['_wc_has_meta_columns'] = false;

		// Iterate over the meta_query to find special cases.
		if ( isset( $query_args['meta_query'] ) ) {
			foreach ( $query_args['meta_query'] as $index => $meta_query ) {

				// Flatten complex meta queries.
				if ( is_array( $meta_query ) && 1 === count( $meta_query ) && is_array( current( $meta_query ) ) ) {
					$meta_query = current( $meta_query );
				}

				if ( isset( $meta_query['customer_emails'] ) ) {
					$query_args['wc_order_meta_query'][] = array_merge( $meta_query['customer_emails'], array(
						'key'      => 'billing_email',
						'_old_key' => $meta_query['customer_emails']['key'],
					) );
				}

				if ( isset( $meta_query['customer_ids'] ) ) {
					$query_args['wc_order_meta_query'][] = array_merge( $meta_query['customer_ids'], array(
						'key'      => 'customer_id',
						'_old_key' => $meta_query['customer_ids']['key'],
					) );
				}

				if ( isset( $meta_query['key'] ) ) {
					$column = array_search( $meta_query['key'], self::get_postmeta_mapping(), true );

					if ( $column ) {
						$query_args['wc_order_meta_query'][] = array_merge( $meta_query, array(
							'key'      => $column,
							'_old_key' => $meta_query['key'],
						) );
					}
				} else {
					// Let this meta query pass through unaltered.
					$query_args['_wc_has_meta_columns'] = true;
				}
			}
		}

		// Add filters to address specific portions of the query.
		add_filter( 'posts_join', __CLASS__ . '::posts_join', 10, 2 );
		add_filter( 'posts_where', __CLASS__ . '::meta_query_where', 100, 2 );

		return $query_args;
	}

	/**
	 * Filter the JOIN statement generated by WP_Query.
	 *
	 * @global $wpdb
	 *
	 * @param string   $join     The MySQL JOIN statement.
	 * @param WP_Query $wp_query The WP_Query object, passed by reference.
	 *
	 * @return string The filtered JOIN statement.
	 */
	public static function posts_join( $join, $wp_query ) {
		global $wpdb;

		/*
		 * Remove the now-unnecessary INNER JOIN with the post_meta table unless there's some post
		 * meta that doesn't have a column in the custom table.
		 *
		 * @see WP_Meta_Query::get_sql_for_clause()
		 */
		if ( ! $wp_query->get( '_wc_has_meta_columns', false ) ) {
			// Match the post_meta table INNER JOIN, with or without an alias.
			$regex = "/\sINNER\sJOIN\s{$wpdb->postmeta}\s+(AS\s[^\s]+)?\s*ON\s\([^\)]+\)/i";

			$join = preg_replace( $regex, '', $join );
		}

		$table = wc_custom_order_table()->get_table_name();
		$join .= " LEFT JOIN {$table} ON ( {$wpdb->posts}.ID = {$table}.order_id ) ";

		// Don't necessarily apply this to subsequent posts_join filter callbacks.
		remove_filter( 'posts_join', __CLASS__ . '::posts_join', 10, 2 );

		return $join;
	}

	/**
	 * Filter the "WHERE" portion of the MySQL query to look at the custom orders table instead of
	 * post meta.
	 *
	 * @global $wpdb
	 *
	 * @param string   $where    The MySQL WHERE statement.
	 * @param WP_Query $wp_query The WP_Query object, passed by reference.
	 *
	 * @return string The [potentially-] filtered WHERE statement.
	 */
	public static function meta_query_where( $where, $wp_query ) {
		global $wpdb;

		$meta_query = $wp_query->get( 'wc_order_meta_query' );
		$table      = wc_custom_order_table()->get_table_name();

		if ( empty( $meta_query ) ) {
			return $where;
		}

		foreach ( $meta_query as $query ) {
			$regex = $wpdb->prepare( '/\(\s?(\w+\.)?meta_key = %s AND (\w+\.)?meta_value /i', $query['_old_key'] );
			$where = preg_replace( $regex, "( {$table}.{$query['key']} ", $where );
		}

		// Ensure this doesn't affect all subsequent queries.
		remove_filter( 'posts_where', __CLASS__ . '::meta_query_where', 100, 2 );

		return $where;
	}
}
