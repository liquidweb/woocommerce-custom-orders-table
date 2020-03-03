<?php
/**
 * WooCommerce customer data store.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

/**
 * Extend the WC_Order_Data_Store_CPT class in order to overload methods that directly query the
 * postmeta table.
 */
class WC_Customer_Data_Store_Custom_Table extends WC_Customer_Data_Store {

	/**
	 * Gets the customers last order.
	 *
	 * @global $wpdb
	 *
	 * @param WC_Customer $customer The WC_Customer object, passed by reference.
	 *
	 * @return WC_Order|false The last order from this customer, or FALSE if the customer has not
	 *                        made an order.
	 */
	public function get_last_order( &$customer ) {
		global $wpdb;

		$table      = wc_custom_order_table()->get_table_name();
		$statuses   = wc_get_order_statuses();
		$last_order = $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT posts.ID FROM $wpdb->posts AS posts
				LEFT JOIN " . esc_sql( $table ) . " AS meta on posts.ID = meta.order_id
				WHERE meta.customer_id = %d
				AND   posts.post_type  = 'shop_order'
				AND   posts.post_status IN (" . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ')
				ORDER BY posts.ID DESC LIMIT 1',
				array_merge( array( $customer->get_id() ), array_keys( $statuses ) )
			)
		);

		return $last_order ? wc_get_order( (int) $last_order ) : false;
	}

	/**
	 * Return the number of orders this customer has.
	 *
	 * @global $wpdb
	 *
	 * @param WC_Customer $customer The WC_Customer object, passed by reference.
	 *
	 * @return int The number of orders for this customer.
	 */
	public function get_order_count( &$customer ) {
		global $wpdb;

		$count = get_user_meta( $customer->get_id(), '_order_count', true );

		if ( '' === $count ) {
			$table    = wc_custom_order_table()->get_table_name();
			$statuses = wc_get_order_statuses();
			$count    = $wpdb->get_var(
				$wpdb->prepare(
					"
					SELECT COUNT(*) FROM $wpdb->posts as posts
					LEFT JOIN " . esc_sql( $table ) . " AS meta ON posts.ID = meta.order_id
					WHERE meta.customer_id = %d
					AND   posts.post_type  = 'shop_order'
					AND   posts.post_status IN (" . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ')',
					array_merge( array( $customer->get_id() ), array_keys( $statuses ) )
				)
			);
			update_user_meta( $customer->get_id(), '_order_count', $count );
		}

		return (int) $count;
	}

	/**
	 * Return how much money this customer has spent.
	 *
	 * @global $wpdb
	 *
	 * @param WC_Customer $customer The WC_Customer object, passed by reference.
	 *
	 * @return float The total amount spent by the customer.
	 */
	public function get_total_spent( &$customer ) {
		global $wpdb;

		$spent = get_user_meta( $customer->get_id(), '_money_spent', true );

		/**
		 * Filter the total amount spent by the given customer across all orders.
		 *
		 * @param float $spent The total of all orders for this customer.
		 * @param WC_Customer $customer The customer being queried.
		 */
		$spent = apply_filters( 'woocommerce_customer_get_total_spent', $spent, $customer );

		// If there's no saved value, attempt to calculate one.
		if ( '' === $spent ) {
			$table    = wc_custom_order_table()->get_table_name();
			$statuses = array_map( 'self::prefix_wc_status', wc_get_is_paid_statuses() );
			$sql      = $wpdb->prepare(
				"
				SELECT SUM(meta.total) FROM $wpdb->posts as posts
				LEFT JOIN " . esc_sql( $table ) . " AS meta ON posts.ID = meta.order_id
				WHERE   meta.customer_id  = %d
				AND     posts.post_type   = 'shop_order'
				AND     posts.post_status IN (" . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ')',
				array_merge( array( $customer->get_id() ), $statuses )
			);

			/**
			 * Filter the MySQL query used to determine how much a customer has spent across all orders.
			 *
			 * @param string      $sql      The prepared MySQL statement.
			 * @param WC_Customer $customer The customer being queried.
			 */
			$sql   = apply_filters( 'woocommerce_customer_get_total_spent_query', $sql, $customer );
			$spent = (float) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			update_user_meta( $customer->get_id(), '_money_spent', $spent );
		}

		return $spent;
	}

	/**
	 * Register callbacks when the data store is first instantiated.
	 */
	public static function add_hooks() {
		add_filter( 'woocommerce_pre_customer_bought_product', __CLASS__ . '::pre_customer_bought_product', 10, 4 );

		// Handle deleting a customer from the database.
		add_action( 'deleted_user', __CLASS__ . '::reset_order_customer_id_on_deleted_user' );
		remove_action( 'deleted_user', 'wc_reset_order_customer_id_on_deleted_user' );
	}

	/**
	 * Short-circuit the wc_customer_bought_product() function.
	 *
	 * @see wc_customer_bought_product()
	 *
	 * @global $wpdb
	 *
	 * @param bool   $purchased      Whether or not the customer has purchased the product.
	 * @param string $customer_email Customer email to check.
	 * @param int    $user_id User ID to check.
	 * @param int    $product_id Product ID to check.
	 *
	 * @return bool Whether or not the customer has already purchased the given product ID.
	 */
	public static function pre_customer_bought_product( $purchased, $customer_email, $user_id, $product_id ) {
		global $wpdb;

		$transient_name = 'wc_cbp_' . md5( $customer_email . $user_id . WC_Cache_Helper::get_transient_version( 'orders' ) );
		$result         = get_transient( $transient_name );

		if ( false === $result ) {
			$customer_data = array( $user_id );

			if ( $user_id ) {
				$user = get_user_by( 'id', $user_id );

				if ( isset( $user->user_email ) ) {
					$customer_data[] = $user->user_email;
				}
			}

			if ( is_email( $customer_email ) ) {
				$customer_data[] = $customer_email;
			}

			$customer_data = array_map( 'esc_sql', array_filter( array_unique( $customer_data ) ) );
			$statuses      = array_map( 'self::prefix_wc_status', wc_get_is_paid_statuses() );

			if ( 0 === count( $customer_data ) ) {
				return false;
			}

			$table  = wc_custom_order_table()->get_table_name();
			$result = $wpdb->get_col(
				$wpdb->prepare(
					"
					SELECT im.meta_value FROM {$wpdb->posts} AS p
					INNER JOIN " . esc_sql( $table ) . " AS pm ON p.ID = pm.order_id
					INNER JOIN {$wpdb->prefix}woocommerce_order_items AS i ON p.ID = i.order_id
					INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS im ON i.order_item_id = im.order_item_id
					WHERE p.post_status IN (" . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ')
					AND (
						pm.billing_email IN (' . implode( ', ', array_fill( 0, count( $customer_data ), '%s' ) ) . ')
						OR pm.customer_id IN (' . implode( ', ', array_fill( 0, count( $customer_data ), '%s' ) ) . ")
					)
					AND im.meta_key IN ( '_product_id', '_variation_id' )
					AND im.meta_value != 0",
					array_merge( $statuses, $customer_data, $customer_data )
				)
			);
			$result = array_map( 'absint', $result );

			set_transient( $transient_name, $result, DAY_IN_SECONDS * 30 );
		}

		return in_array( (int) $product_id, $result, true );
	}

	/**
	 * Reset customer_id on orders when a user is deleted.
	 *
	 * @param int $user_id The ID of the deleted user.
	 */
	public static function reset_order_customer_id_on_deleted_user( $user_id ) {
		global $wpdb;

		$wpdb->update(
			wc_custom_order_table()->get_table_name(),
			array( 'customer_id' => 0 ),
			array( 'customer_id' => $user_id )
		);
	}

	/**
	 * Helper function to prefix a status with 'wc-'.
	 *
	 * Statuses that already contain the prefix will be skipped.
	 *
	 * @param string $status The status to prefix.
	 *
	 * @return string The status with 'wc-' prefixed.
	 */
	public static function prefix_wc_status( $status ) {
		if ( 'wc-' === substr( $status, 0, 3 ) ) {
			return $status;
		}

		return 'wc-' . $status;
	}
}
