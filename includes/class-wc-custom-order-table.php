<?php
/**
 * Core plugin functionality.
 *
 * @package WooCommerce_Custom_Order_Tables
 * @author  Liquid Web
 */

/**
 * Core functionality for WooCommerce Custom Order Tables.
 */
class WC_Custom_Order_Table {

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

		// Inject the plugin into order processing.
		add_filter( 'woocommerce_order_data_store', array( $this, 'order_data_store' ) );
		add_filter( 'posts_join', array( $this, 'wp_query_customer_query' ), 10, 2 );

		// Register the CLI command if we're running WP_CLI.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'wc-order-table', 'WC_Custom_Order_Table_CLI' );
		}
	}

	/**
	 * Retrieve the WooCommerce order table name.
	 *
	 * @return string The database table name.
	 */
	public function get_table_name() {
		/**
		 * Filter the WooCommerce order table name.
		 *
		 * @param string $table The WooCommerce orders table name.
		 */
		return apply_filters( 'wc_customer_order_table_name', $this->table_name );
	}

	/**
	 * Retrieve the class name of the WooCommerce order data store.
	 *
	 * @return string The data store class name.
	 */
	public function order_data_store() {
		return 'WC_Order_Data_Store_Custom_Table';
	}

	/**
	 * Modify posts_join queries when the query includes wc_customer_query.
	 *
	 * @global $wpdb
	 *
	 * @param string   $join     The SQL JOIN statement.
	 * @param WP_Query $wp_query The current WP_Query object.
	 *
	 * @return string The [potentially] filtered JOIN statement.
	 */
	public function wp_query_customer_query( $join, $wp_query ) {
		global $wpdb;

		// If there is no wc_customer_query then no need to process anything.
		if ( ! isset( $wp_query->query_vars['wc_customer_query'] ) ) {
			return $join;
		}

		$customer_query = $this->generate_wc_customer_query( $wp_query->query_vars['wc_customer_query'] );
		$query_parts    = array();

		if ( ! empty( $customer_query['emails'] ) ) {
			$emails        = '\'' . implode( '\', \'', array_unique( $customer_query['emails'] ) ) . '\'';
			$query_parts[] = "{$this->get_table_name()}.billing_email IN ( {$emails} )";
		}

		if ( ! empty( $customer_query['users'] ) ) {
			$users         = implode( ',', array_unique( $customer_query['users'] ) );
			$query_parts[] = "{$this->get_table_name()}.customer_id IN ( {$users} )";
		}

		if ( ! empty( $query_parts ) ) {
			$query = '( ' . implode( ') OR (', $query_parts ) . ' )';
			$join .= "JOIN {$this->get_table_name()} ON
			( {$wpdb->posts}.ID = {$this->get_table_name()}.order_id )
			AND ( {$query} )";
		}

		return $join;
	}

	/**
	 * Given a wc_customer_query argument, construct an array of customers grouped by either email
	 * address or user ID.
	 *
	 * @param array $values Query arguments from WP_Query->query_vars['wc_customer_query'].
	 *
	 * @return array A complex array with two keys: "emails" and "users".
	 */
	public function generate_wc_customer_query( $values ) {
		$customer_query = array(
			'emails' => array(),
			'users'  => array(),
		);

		foreach ( $values as $value ) {
			// If the value is an array, call this method recursively and merge the results.
			if ( is_array( $value ) ) {
				$query = $this->generate_wc_customer_query( $value );

				if ( is_array( $query['emails'] ) ) {
					$customer_query['emails'] = array_merge( $customer_query['emails'], $query['emails'] );
				}

				if ( is_array( $query['users'] ) ) {
					$customer_query['users'] = array_merge( $customer_query['users'], $query['users'] );
				}
			} elseif ( is_email( $value ) ) {
				$customer_query['emails'][] = sanitize_email( $value );

			} else {
				$customer_query['users'][] = strval( absint( $value ) );
			}
		}

		return $customer_query;
	}
}
