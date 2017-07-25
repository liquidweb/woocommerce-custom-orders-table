<?php
/**
 * Plugin Name: WooCommerce - Custom Order Table
 * Description: Store WooCommerce order data in a custom table.
 * Version: 1.0.0
 * Requires at least: 4.7
 * Tested up to: 4.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Custom_Order_Table {

	protected $table_name = null;
	protected $table_version = 1;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->table_name = $wpdb->prefix . 'woocommerce_orders';

		add_action( 'plugins_loaded', array( $this, 'init' ) );

		add_filter( 'woocommerce_order_data_store', array( $this, 'order_data_store' ) );
		add_filter( 'posts_join', array( $this, 'wp_query_customer_query' ), 10, 2 );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
	}

	public function get_table_name() {
		return apply_filters( 'wc_customer_order_table_name', $this->table_name );
	}

	/**
	 * Init the plugin.
	 */
	public function init() {
		require_once 'includes/class-wc-order-data-store-custom-table.php';
		require_once 'includes/class-wc-custom-order-table-cli.php';
	}

	/**
	 * Init the order data store.
	 *
	 * @return string
	 */
	public function order_data_store() {
		return 'WC_Order_Data_Store_Custom_Table';
	}

	/**
	 * Filter WP_Query for wc_customer_query
	 *
	 * @return string
	 */
	public function wp_query_customer_query( $join, $wp_query ) {
		global $wpdb;

		// If there is no wc_customer_query then no need to process anything
		if( ! isset( $wp_query->query_vars['wc_customer_query'] ) ) {
			return $join;
		}

		$customer_query = $this->generate_wc_customer_query( $wp_query->query_vars['wc_customer_query'] );


		$query_parts = array();

		if( ! empty( $customer_query['emails'] ) ) {
			$emails = '\'' . implode( '\', \'', array_unique( $customer_query['emails'] ) ) . '\'';
			$query_parts[] = "{$this->get_table_name()}.billing_email IN ( {$emails} )";
		}

		if( ! empty( $customer_query['users'] ) ) {
			$users  = implode( ',', array_unique( $customer_query['users'] ) );
			$query_parts[] = "{$this->get_table_name()}.customer_id IN ( {$users} )";
		}

		if( ! empty( $query_parts ) ) {
			$query = '( ' . implode( ') OR (', $query_parts ) . ' )';
			$join .= "
            JOIN {$this->get_table_name()} ON
            ( {$wpdb->posts}.ID = {$this->get_table_name()}.order_id )
            AND ( {$query} )";
		}

		return $join;
	}

	public function generate_wc_customer_query( $values ) {
		$customer_query['emails'] = array();
		$customer_query['users'] = array();

		foreach ( $values as $value ) {
			if ( is_array( $value ) ) {
				$query = $this->generate_wc_customer_query( $value );

				if( is_array( $query['emails'] ) ) {
					$customer_query['emails'] = array_merge( $customer_query['emails'], $query['emails'] );
				}

				if( is_array( $query['users'] ) ) {
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

	public function activate() {
		$this->maybe_install_tables();
	}

	public function get_latest_table_version() {
		return absint( $this->table_version );
	}

	public function get_installed_table_version() {
		return absint( get_option( 'wc_orders_table_version' ) );
	}

	protected function maybe_install_tables() {
		if( $this->get_installed_table_version() < $this->get_latest_table_version() ) {
			$this->install_tables();
		}
	}

	protected function install_tables() {
		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$table = $this->get_table_name();

		$tables = "
		CREATE TABLE {$table} (
		order_id BIGINT UNSIGNED NOT NULL,
		order_key varchar(100) NOT NULL,
		customer_id BIGINT UNSIGNED NOT NULL,
		billing_first_name varchar(100) NOT NULL,
		billing_last_name varchar(100) NOT NULL,
		billing_company varchar(100) NOT NULL,
		billing_address_1 varchar(200) NOT NULL,
		billing_address_2 varchar(200) NOT NULL,
		billing_city varchar(100) NOT NULL,
		billing_state varchar(100) NOT NULL,
		billing_postcode varchar(100) NOT NULL,
		billing_country varchar(100) NOT NULL,
		billing_email varchar(200) NOT NULL,
		billing_phone varchar(200) NOT NULL,
		shipping_first_name varchar(100) NOT NULL,
		shipping_last_name varchar(100) NOT NULL,
		shipping_company varchar(100) NOT NULL,
		shipping_address_1 varchar(200) NOT NULL,
		shipping_address_2 varchar(200) NOT NULL,
		shipping_city varchar(100) NOT NULL,
		shipping_state varchar(100) NOT NULL,
		shipping_postcode varchar(100) NOT NULL,
		shipping_country varchar(100) NOT NULL,
		payment_method varchar(100) NOT NULL,
		payment_method_title varchar(100) NOT NULL,

        discount_total float NOT NULL DEFAULT 0,
        discount_tax float NOT NULL DEFAULT 0,
        shipping_total float NOT NULL DEFAULT 0,
        shipping_tax float NOT NULL DEFAULT 0,
        cart_tax float NOT NULL DEFAULT 0,
        total float NOT NULL DEFAULT 0,
		version varchar(16) NOT NULL,
		currency varchar(3) NOT NULL,
		prices_include_tax tinyint(1) NOT NULL,

		transaction_id varchar(200) NOT NULL,
		customer_ip_address varchar(40) NOT NULL,
		customer_user_agent varchar(200) NOT NULL,
		created_via varchar(200) NOT NULL,
		date_completed datetime DEFAULT NULL,
		date_paid datetime DEFAULT NULL,
		cart_hash varchar(32) NOT NULL,

		PRIMARY KEY  (order_id)
		) $collate;
		";

		dbDelta( $tables );
		update_option('wc_orders_table_version', $this->get_latest_table_version() );
	}
}

function wc_custom_order_table() {
	global $wc_custom_order_table;

	if( ! $wc_custom_order_table instanceof WC_Custom_Order_Table ) {
		$wc_custom_order_table = new WC_Custom_Order_Table;
	}

	return $wc_custom_order_table;
}

wc_custom_order_table();