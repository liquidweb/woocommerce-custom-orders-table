<?php
/**
 * WooCommerce order data store.
 *
 * @package WooCommerce_Custom_Order_Tables
 * @author  Liquid Web
 */

/**
 * Extend the WC_Order_Data_Store_CPT class in order to overload methods, overloading methods that
 * directly query the post-meta tables.
 */
class WC_Customer_Data_Store_Custom_Table extends WC_Customer_Data_Store {

	public function __construct() {
		add_filter( 'woocommerce_pre_customer_bought_product', __CLASS__ . '::pre_customer_bought_product', 10, 4 );

		// Handle deleting a customer from the database.
		add_action( 'deleted_user', __CLASS__ . '::reset_order_customer_id_on_deleted_user' );
		remove_action( 'deleted_user', 'wc_reset_order_customer_id_on_deleted_user' );
	}

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
		$last_order = $wpdb->get_var( "SELECT posts.ID
			FROM $wpdb->posts AS posts
			LEFT JOIN $table AS meta on posts.ID = meta.order_id
			WHERE meta.customer_id = '" . esc_sql( $customer->get_id() ) . "'
			AND   posts.post_type  = 'shop_order'
			AND   posts.post_status IN ( '" . implode( "','", array_map( 'esc_sql', array_keys( wc_get_order_statuses() ) ) ) . "' )
			ORDER BY posts.ID DESC
		" );

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
			$table = wc_custom_order_table()->get_table_name();
			$count = $wpdb->get_var( "SELECT COUNT(*)
				FROM $wpdb->posts as posts
				LEFT JOIN $table AS meta ON posts.ID = meta.order_id
				WHERE   meta.customer_id = '" . esc_sql( $customer->get_id() ) . "'
				AND     posts.post_type  = 'shop_order'
				AND     posts.post_status IN ( '" . implode( "','", array_map( 'esc_sql', array_keys( wc_get_order_statuses() ) ) ) . "' )
			" );
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

		$spent = apply_filters( 'woocommerce_customer_get_total_spent', get_user_meta( $customer->get_id(), '_money_spent', true ), $customer );

		if ( '' === $spent ) {
			$table    = wc_custom_order_table()->get_table_name();
			$statuses = array_map( 'esc_sql', wc_get_is_paid_statuses() );
			$spent    = $wpdb->get_var( apply_filters( 'woocommerce_customer_get_total_spent_query', "SELECT SUM(meta.total)
				FROM $wpdb->posts as posts
				LEFT JOIN {$table} AS meta ON posts.ID = meta.order_id
				WHERE   meta.customer_id    = '" . esc_sql( $customer->get_id() ) . "'
				AND     posts.post_type     = 'shop_order'
				AND     posts.post_status   IN ( 'wc-" . implode( "','wc-", $statuses ) . "' )
			", $customer ) );

			if ( ! $spent ) {
				$spent = 0;
			}
			update_user_meta( $customer->get_id(), '_money_spent', $spent );
		}

		return wc_format_decimal( $spent, 2 );
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

		if ( false === ( $result = get_transient( $transient_name ) ) ) {
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
			$statuses      = array_map( 'esc_sql', wc_get_is_paid_statuses() );

			if ( sizeof( $customer_data ) == 0 ) {
				return false;
			}

			$table  = wc_custom_order_table()->get_table_name();
			$result = $wpdb->get_col( "
				SELECT im.meta_value FROM {$wpdb->posts} AS p
				INNER JOIN {$table} AS pm ON p.ID = pm.order_id
				INNER JOIN {$wpdb->prefix}woocommerce_order_items AS i ON p.ID = i.order_id
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS im ON i.order_item_id = im.order_item_id
				WHERE p.post_status IN ( 'wc-" . implode( "','wc-", $statuses ) . "' )
				AND (
					pm.billing_email IN ( '" . implode( "','", $customer_data ) . "' )
					OR pm.customer_id IN ( '" . implode( "','", $customer_data ) . "' )
				)
				AND im.meta_key IN ( '_product_id', '_variation_id' )
				AND im.meta_value != 0
			" );
			$result = array_map( 'absint', $result );

			set_transient( $transient_name, $result, DAY_IN_SECONDS * 30 );
		}

		return in_array( (int) $product_id, $result );
	}

	/**
	 * Reset customer_id on orders when a user is deleted.
	 *
	 * @param int $user_id
	 */
	public static function reset_order_customer_id_on_deleted_user( $user_id ) {
		global $wpdb;

		$wpdb->update(
			wc_custom_order_table()->get_table_name(),
			array( 'customer_id' => 0 ),
			array( 'customer_id' => $user_id )
		);
	}
}
