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
	 * Map table columns to related postmeta keys.
	 *
	 * @var array
	 */
	protected $postmeta_mapping = array(
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

	/**
	 * Retrieve the database table column => post_meta mapping.
	 *
	 * @return array An array of database columns and their corresponding post_meta keys.
	 */
	public function get_postmeta_mapping() {
		return $this->postmeta_mapping;
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
			$wpdb->delete("{$wpdb->prefix}woocommerce_orders", array( 'order_id' => $order_id ) );
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

		parent::read_order_data( $order, $post_object );

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
	 * @return object The order row, as an object.
	 */
	public function get_order_data_from_table( $order ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}woocommerce_orders WHERE order_id = %d;", $order->get_id() ) );
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

		$edit_data = array(
			'order_key'            => $order->get_order_key( 'edit' ),
			'customer_id'          => $order->get_customer_id( 'edit' ),
			'payment_method'       => $order->get_payment_method( 'edit' ),
			'payment_method_title' => $order->get_payment_method_title( 'edit' ),
			'transaction_id'       => $order->get_transaction_id( 'edit' ),
			'customer_ip_address'  => $order->get_customer_ip_address( 'edit' ),
			'customer_user_agent'  => $order->get_customer_user_agent( 'edit' ),
			'created_via'          => $order->get_created_via( 'edit' ),
			'date_completed'       => (string) $order->get_date_completed( 'edit' ),
			'date_paid'            => (string) $order->get_date_paid( 'edit' ),
			'cart_hash'            => $order->get_cart_hash( 'edit' ),

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
			'cart_tax'             => $order->get_total_tax( 'edit' ),
			'total'                => $order->get_total( 'edit' ),

			'version'              => $order->get_version( 'edit' ),
			'currency'             => $order->get_currency( 'edit' ),
			'prices_include_tax'   => $order->get_prices_include_tax( 'edit' ),
		);

		$changes = array();

		if ( $this->creating ) {
			$wpdb->insert(
				wc_custom_order_table()->get_table_name(),
				array_merge( array(
					'order_id' => $order->get_id(),
				), $edit_data )
			);

			// We are no longer creating the order, it is created.
			$this->creating = false;
		} else {
			$changes = array_intersect_key( $edit_data, $order->get_changes() );

			if ( ! empty( $changes ) ) {
				$wpdb->update(
					"{$wpdb->prefix}woocommerce_orders",
					$changes,
					array(
						'order_id' => $order->get_id(),
					)
				);
			}
		}

		$updated_props = array_keys( (array) $changes );

		// If customer changed, update any downloadable permissions.
		if ( in_array( 'customer_user', $updated_props ) || in_array( 'billing_email', $updated_props ) ) {
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
		) );
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

		$order_ids = array();

		// Treat a numeric search term as an order ID.
		if ( is_numeric( $term ) ) {
			$order_ids[] = absint( $term );
		}

		// Search given post meta columns for the query.
		$postmeta_search = array();

		/**
		 * Searches on meta data can be slow - this lets you choose what fields to search.
		 *
		 * WooCommerce 2.7.0 added _billing_address and _shipping_address meta which contains all
		 * address data to make this faster. However, this won't work on older orders unless they
		 * are updated, so search a few others (expand this using the filter if needed).
		 */
		$meta_search_fields = array_map( 'wc_clean', apply_filters( 'woocommerce_shop_order_search_fields', array() ) );

		// If we were given meta fields to search, make it happen.
		if ( ! empty( $meta_search_fields ) ) {
			$postmeta_search = $wpdb->get_col( $wpdb->prepare( "
					SELECT DISTINCT post_id
					FROM {$wpdb->postmeta}
					WHERE meta_key IN (" . implode( ',', array_fill( 0, count( $meta_search_fields ), '%s' ) ) . ')
					AND meta_value LIKE %s
				',
				array_merge( $meta_search_fields, array( '%' . $wpdb->esc_like( $term ) . '%' ) )
			) );
		}

		return array_unique( array_merge(
			$order_ids,
			$postmeta_search,
			$wpdb->get_col(
				$wpdb->prepare( "
						SELECT order_id
						FROM {$wpdb->prefix}woocommerce_order_items as order_items
						WHERE order_item_name LIKE %s
					",
					'%' . $wpdb->esc_like( $term ) . '%'
				)
			)
		) );
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

		if ( is_null( $table_data ) ) {
			$original_creating = $this->creating;
			$this->creating    = true;
		}

		foreach ( $this->get_postmeta_mapping() as $column => $meta_key ) {
			$meta = get_post_meta( $order->get_id(), $meta_key, true );

			if ( empty( $table_data->$column ) && ! empty( $meta ) ) {
				switch ( $column ) {
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
			foreach ( $this->get_postmeta_mapping() as $column => $meta_key ) {
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

		foreach ( $this->get_postmeta_mapping() as $column => $meta_key ) {
			if ( isset( $data->$column ) ) {
				update_post_meta( $order->get_id(), $meta_key, $data->$column );
			}
		}
	}
}
