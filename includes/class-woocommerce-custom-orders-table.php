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
	 * The database table name for orders.
	 *
	 * @var string
	 */
	protected $table_name = null;

	/**
	 * The database table name for order meta data.
	 *
	 * @var string
	 */
	protected $meta_table_name = null;

	/**
	 * Steps to run on plugin initialization.
	 *
	 * @global $wpdb
	 */
	public function setup() {
		global $wpdb;

		$this->table_name      = $wpdb->prefix . 'woocommerce_orders';
		$this->meta_table_name = $wpdb->prefix . 'woocommerce_ordermeta';

		// Registers 'ordermeta' table in the global wpdb object.
		global $wpdb;
		$wpdb->ordermeta = wc_custom_order_table()->get_meta_table_name();
		// Use the plugin's custom data stores for customers and orders.
		add_filter( 'woocommerce_customer_data_store', __CLASS__ . '::customer_data_store' );
		add_filter( 'woocommerce_order_data_store', __CLASS__ . '::order_data_store' );
		add_filter( 'woocommerce_order-refund_data_store', __CLASS__ . '::order_refund_data_store' );

		// Filter order report queries.
		add_filter( 'woocommerce_reports_get_order_report_query', 'WooCommerce_Custom_Orders_Table_Filters::filter_order_report_query' );

		// Fill-in after re-indexing of billing/shipping addresses.
		add_action( 'woocommerce_rest_system_status_tool_executed', 'WooCommerce_Custom_Orders_Table_Filters::rest_populate_address_indexes' );

		// When associating previous orders with a customer based on email, update the record.
		add_action( 'woocommerce_update_new_customer_past_order', 'WooCommerce_Custom_Orders_Table_Filters::update_past_customer_order', 10, 2 );

		// Move custom meta keys query to the ordermeta table.
		add_action( 'get_meta_sql', 'WooCommerce_Custom_Orders_Table_Filters::get_meta_sql', 10, 6 );

		// Register the table within WooCommerce.
		add_filter( 'woocommerce_install_get_tables', array( $this, 'register_tables_name' ) );

		// Removes default metabox and add the ordermeta one.
		add_action( 'add_meta_boxes_shop_order', 'WooCommerce_Custom_Orders_Table_Meta::replace_order_metabox' );
		add_action( 'add_meta_boxes_shop_order_refund', 'WooCommerce_Custom_Orders_Table_Meta::replace_order_metabox' );

		// Add save method for metadata in metabox in the edit form.
		add_action( 'save_post_shop_order', 'WooCommerce_Custom_Orders_Table_Meta::save_order_meta_data', 10, 3 );

		// Add save method for metadata in metabox in the edit form.
		add_action( 'delete_post', 'WooCommerce_Custom_Orders_Table_Meta::delete_order_meta_data', 10 );

		// Add support for ordermeta in pre-CRUD operations.
		add_action( 'add_post_metadata', 'WooCommerce_Custom_Orders_Table_Meta::map_post_metadata', 10, 5 );
		add_action( 'update_post_metadata', 'WooCommerce_Custom_Orders_Table_Meta::map_post_metadata', 10, 5 );
		add_action( 'delete_post_metadata', 'WooCommerce_Custom_Orders_Table_Meta::map_post_metadata', 10, 5 );
		add_action( 'get_post_metadata', 'WooCommerce_Custom_Orders_Table_Meta::map_post_metadata', 10, 4 );

		// If we're in a WP-CLI context, load the WP-CLI command.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'wc orders-table', 'WooCommerce_Custom_Orders_Table_CLI' );
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
	 * Retrieve the WooCommerce ordermeta table name.
	 *
	 * @return string The database table name.
	 */
	public function get_meta_table_name() {
		/**
		 * Filter the WooCommerce order meta table name.
		 *
		 * @param string $table The WooCommerce order meta table name.
		 */
		return apply_filters( 'wc_customer_order_meta_table_name', $this->meta_table_name );
	}

	/**
	 * Simple helper method to determine if a row already exists for the given order ID.
	 *
	 * @global $wpdb
	 *
	 * @param int $order_id The order ID.
	 *
	 * @return bool Whether or not a row already exists for this order ID.
	 */
	public function row_exists( $order_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(order_id) FROM ' . esc_sql( $this->get_table_name() ) . ' WHERE order_id = %d',
				$order_id
			)
		);
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

			'amount'               => '_refund_amount',
			'reason'               => '_refund_reason',
			'refunded_by'          => '_refunded_by',
		);
	}

	/**
	 * Given a WC_Order object, fill its properties from post meta.
	 *
	 * @param WC_Order $order The WC_Order object to populate.
	 *
	 * @return WC_Order The populated WC_Order object.
	 */
	public static function populate_order_from_post_meta( $order ) {
		$meta              = get_post_meta( $order->get_id() );
		$order_fields      = self::get_postmeta_mapping(); // Non order_fields are assumed to be order meta_data.
		$key_to_column_map = array_flip( $order_fields );
		$table_data        = $order->get_data_store()->get_order_data_from_table( $order );

		foreach ( $meta as $meta_key => $meta_value ) {
			$meta_value = reset( $meta_value );
			if ( in_array( $meta_key, $order_fields, true ) ) {
				$column = $key_to_column_map[ $meta_key ];
				if ( empty( $table_data->$column ) && ! empty( $meta_value ) ) {
					switch ( $column ) {
						case 'billing_index':
						case 'shipping_index':
							break;

						/*
						 * Migration isn't the time to validate (and potentially throw exceptions);
						 * if it was accepted into WooCommerce core, let it persist.
						 *
						 * If we're unable to set an email address due to $order->set_billing_email(),
						 * try to circumvent the check by using reflection to call the protected
						 * $order->set_address_prop() method.
						 */
						case 'billing_email':
							try {
								$order->set_billing_email( $meta_value );
							} catch ( WC_Data_Exception $e ) {
								$method = new ReflectionMethod( $order, 'set_address_prop' );
								$method->setAccessible( true );
								$method->invoke( $order, 'email', 'billing', $meta_value );
							}
							break;

						case 'prices_include_tax':
							$order->set_prices_include_tax( 'yes' === $meta_value );
							break;

						default:
							$order->{"set_{$column}"}( $meta_value );
							break;
					}
				}
			} else {
				$order->add_meta_data( $meta_key, $meta_value );
			}
		}

		return $order;
	}

	/**
	 * Register the tables names within WooCommerce.
	 *
	 * @param array $tables An array of known WooCommerce tables.
	 *
	 * @return array The filtered $tables array.
	 */
	public function register_tables_name( $tables ) {
		$new_tables = array(
			$this->get_table_name(),
			$this->get_meta_table_name(),
		);

		foreach ( $new_tables as $table ) {
			if ( ! in_array( $table, $tables, true ) ) {
				$tables[] = $table;
				sort( $tables );
			}
		}

		return $tables;
	}

	/**
	 * Restore an order's data in the post_meta table.
	 *
	 * @global $wpdb
	 *
	 * @param WC_Abstract_Order $order  The order or refund object, passed by reference.
	 * @param bool              $delete Optional. Should the row in the custom table be deleted?
	 *                                  Default is false.
	 */
	public static function migrate_to_post_meta( &$order, $delete = false ) {
		global $wpdb;

		$data = $order->get_data_store()->get_order_data_from_table( $order );

		if ( is_null( $data ) ) {
			return;
		}

		if ( isset( $data['prices_include_tax'] ) ) {
			$data['prices_include_tax'] = wc_bool_to_string( $data['prices_include_tax'] );
		}

		foreach ( self::get_postmeta_mapping() as $column => $meta_key ) {
			if ( isset( $data[ $column ] ) ) {
				update_post_meta( $order->get_id(), $meta_key, $data[ $column ] );
			}
		}

		// Remove the row from the custom table.
		if ( true === $delete ) {

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete(
				wc_custom_order_table()->get_table_name(),
				array( 'order_id' => $order->get_id() ),
				array( '%d' )
			);
		}
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

	/**
	 * Retrieve the class name of the WooCommerce order refund data store.
	 *
	 * @return string The data store class name.
	 */
	public static function order_refund_data_store() {
		return 'WC_Order_Refund_Data_Store_Custom_Table';
	}

}
