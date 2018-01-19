<?php
/**
 * Tests for the WC_Order_Data_Store_Custom_Table class.
 *
 * @package Woocommerce_Order_Tables
 * @author  Liquid Web
 */

class DataStoreTest extends TestCase {

	/**
	 * @requires PHP 5.4 In order to support inline closures for hook callbacks.
	 */
	public function test_create() {
		$instance = new WC_Order_Data_Store_Custom_Table();
		$property = new ReflectionProperty( $instance, 'creating' );
		$property->setAccessible( true );
		$order    = new WC_Order( wp_insert_post( array(
			'post_type' => 'product',
		) ) );

		add_action( 'wp_insert_post', function () use ( $property, $instance ) {
			$this->assertTrue(
				$property->getValue( $instance ),
				'As an order is being created, WC_Order_Data_Store_Custom_Table::$creating should be true'
			);
		} );

		$instance->create( $order );

		$this->assertEquals( 1, did_action( 'wp_insert_post' ), 'Expected the "wp_insert_post" action to have been fired.' );
	}

	public function test_delete() {
		$instance = new WC_Order_Data_Store_Custom_Table();
		$order    = WC_Helper_Order::create_order();

		$instance->delete( $order, array( 'force_delete' => false ) );

		$this->assertNotNull(
			$this->get_order_row( $order->get_id() ),
			'Unless force_delete is true, the table row should not be removed.'
		);
	}

	public function test_delete_can_force_delete() {
		$instance = new WC_Order_Data_Store_Custom_Table();
		$order    = WC_Helper_Order::create_order();
		$order_id = $order->get_id();

		$instance->delete( $order, array( 'force_delete' => true ) );

		$this->assertNull( $this->get_order_row( $order_id ), 'When force deleting, the table row should be removed.' );
	}

	public function test_update_post_meta_for_new_order() {
		$order = new WC_Order( wp_insert_post( array(
			'post_type' => 'product',
		) ) );
		$order->set_currency( 'USD' );
		$order->set_prices_include_tax( false );
		$order->set_customer_ip_address( '127.0.0.1' );
		$order->set_customer_user_agent( 'PHPUnit' );

		$this->invoke_update_post_meta( $order, true );

		$row = $this->get_order_row( $order->get_id() );

		$this->assertEquals( 'USD', $row['currency'] );
		$this->assertEquals( '127.0.0.1', $row['customer_ip_address'] );
		$this->assertEquals( 'PHPUnit', $row['customer_user_agent'] );
	}

	public function test_get_order_id_by_order_key() {
		$order = WC_Helper_Order::create_order();
		$instance = new WC_Order_Data_Store_Custom_Table();

		$this->assertEquals( $order->get_id(), $instance->get_order_id_by_order_key( $order->get_order_key() ) );
	}

	public function test_search_orders_can_search_by_order_id() {
		$instance = new WC_Order_Data_Store_Custom_Table();

		$this->assertEquals(
			array( 123 ),
			$instance->search_orders( 123 ),
			'When given a numeric value, search_orders() should include that order ID.'
		);
	}

	public function test_search_orders_can_check_post_meta() {
		$instance = new WC_Order_Data_Store_Custom_Table();
		$order    = WC_Helper_Order::create_order();
		$term     = uniqid( 'search term ' );

		add_post_meta( $order->get_id(), 'some_custom_meta_key', $term );

		add_filter( 'woocommerce_shop_order_search_fields', __CLASS__ . '::return_array_for_test_search_orders_can_check_post_meta' );

		$this->assertEquals(
			array( $order->get_id() ),
			$instance->search_orders( $term ),
			'If post meta keys are specified, they should also be searched.'
		);

		remove_filter( 'woocommerce_shop_order_search_fields', __CLASS__ . '::return_array_for_test_search_orders_can_check_post_meta' );
	}

	/**
	 * Filter callback for test_search_orders_can_check_post_meta().
	 *
	 * Can be dropped once PHP 5.3 isn't a requirement, as closures are far nicer.
	 */
	public static function return_array_for_test_search_orders_can_check_post_meta() {
		return array( 'some_custom_meta_key' );
	}

	/**
	 * Same as test_search_orders_can_check_post_meta(), but the filter is never added.
	 */
	public function test_search_orders_only_checks_post_meta_if_specified() {
		$instance = new WC_Order_Data_Store_Custom_Table();
		$order    = WC_Helper_Order::create_order();
		$term     = uniqid( 'search term ' );

		add_post_meta( $order->get_id(), 'some_custom_meta_key', $term );

		$this->assertEmpty(
			$instance->search_orders( $term ),
			'Only search post meta if keys are provided.'
		);
	}

	public function test_search_orders_checks_table_for_product_item_matches() {
		$instance = new WC_Order_Data_Store_Custom_Table();
		$product  = WC_Helper_Product::create_simple_product();
		$order    = WC_Helper_Order::create_order();
		$order->add_product( $product );
		$order->save();

		$this->assertEquals(
			array( $order->get_id() ),
			$instance->search_orders( $product->get_name() ),
			'Order searches should extend to the names of product items.'
		);
	}

	public function test_search_orders_checks_table_for_product_item_matches_with_like_comparison() {
		$instance = new WC_Order_Data_Store_Custom_Table();
		$product  = WC_Helper_Product::create_simple_product();
		$product->set_name( 'Foo Bar Baz' );
		$product->save();
		$order    = WC_Helper_Order::create_order();
		$order->add_product( $product );
		$order->save();

		$this->assertEquals(
			array( $order->get_id() ),
			$instance->search_orders( 'bar' ),
			'Product items should be searched using a LIKE comparison and wildcards.'
		);
	}

	/**
	 * To ensure the regular expressions are working as expected, grab some actual queries from
	 * WC_Admin_Report::get_order_report_data() and verify.
	 *
	 * @dataProvider filter_order_report_provider()
	 */
	public function test_filter_order_report_query( $original_query, $changed_clauses ) {
		$this->assertEquals(
			$this->normalize_query_array( array_merge( $original_query, $changed_clauses ) ),
			$this->normalize_query_array( WC_Order_Data_Store_Custom_Table::filter_order_report_query( $original_query ) ),
			'Did not perform the expected changes to the order report query.'
		);
	}

	public function filter_order_report_provider() {
		$table = wc_custom_order_table()->get_table_name();

		return array(
			'Order report with post data' => array(
				array(
					'select' => 'SELECT meta__billing_first_name.meta_value as customer_name',
					'from'   => 'FROM wptests_posts AS posts',
					'join'   => 'INNER JOIN wptests_postmeta AS meta__billing_first_name ON ( posts.ID = meta__billing_first_name.post_id AND meta__billing_first_name.meta_key = \'_billing_first_name\' )',
					'where'  => 'WHERE posts.post_type IN ( \'shop_order\',\'shop_order_refund\' ) AND posts.post_status IN ( \'wc-completed\',\'wc-processing\',\'wc-on-hold\')'
				),
				array(
					'select' => 'SELECT order_meta.billing_first_name as customer_name',
					'join'   => "LEFT JOIN {$table} AS order_meta ON ( posts.ID = order_meta.order_id )",
				),
			),
			'Order report with parent meta' => array(
				array(
					'select' => 'SELECT parent_meta__order_total.meta_value as total_refund',
					'from'   => 'FROM wptests_posts AS posts',
					'join'   => 'INNER JOIN wptests_postmeta AS parent_meta__order_total ON (posts.post_parent = parent_meta__order_total.post_id) AND (parent_meta__order_total.meta_key = \'_order_total\')',
					'where'  => 'WHERE posts.post_type IN ( \'shop_order\',\'shop_order_refund\' ) AND posts.post_status IN ( \'wc-completed\',\'wc-processing\',\'wc-on-hold\')',
				),
				array(
					'select' => 'SELECT order_parent_meta.total as total_refund',
					'join'   => "LEFT JOIN {$table} AS order_parent_meta ON ( posts.post_parent = order_parent_meta.order_id )",
				),
			),
			'Complicated query from WC_Tests_Report_Sales_By_Date::test_get_report_data()' => array(
				array(
					'select'   => 'SELECT posts.id as refund_id,
									meta__refund_amount.meta_value as total_refund,
									posts.post_date as post_date,
									order_items.order_item_type as item_type,
									meta__order_total.meta_value as total_sales,
									meta__order_shipping.meta_value as total_shipping,
									meta__order_tax.meta_value as total_tax,
									meta__order_shipping_tax.meta_value as total_shipping_tax,
									SUM( order_item_meta__qty.meta_value) as order_item_count',
					'from'     => 'FROM wptests_posts AS posts',
					'join'     => 'INNER JOIN wptests_postmeta AS meta__refund_amount ON ( posts.ID = meta__refund_amount.post_id AND meta__refund_amount.meta_key = \'_refund_amount\' )
									LEFT JOIN wptests_woocommerce_order_items AS order_items ON (posts.ID = order_items.order_id)
									INNER JOIN wptests_postmeta AS meta__order_total ON ( posts.ID = meta__order_total.post_id AND meta__order_total.meta_key = \'_order_total\' )
									LEFT JOIN wptests_postmeta AS meta__order_shipping ON ( posts.ID = meta__order_shipping.post_id AND meta__order_shipping.meta_key = \'_order_shipping\' )
									LEFT JOIN wptests_postmeta AS meta__order_tax ON ( posts.ID = meta__order_tax.post_id AND meta__order_tax.meta_key = \'_order_tax\' )
									LEFT JOIN wptests_postmeta AS meta__order_shipping_tax ON ( posts.ID = meta__order_shipping_tax.post_id AND meta__order_shipping_tax.meta_key = \'_order_shipping_tax\' )
									LEFT JOIN wptests_woocommerce_order_itemmeta AS order_item_meta__qty ON (order_items.order_item_id = order_item_meta__qty.order_item_id)  AND (order_item_meta__qty.meta_key = \'_qty\')
									LEFT JOIN wptests_posts AS parent ON posts.post_parent = parent.ID',
					'where'    => 'WHERE posts.post_type IN ( \'shop_order\',\'shop_order_refund\' )
									AND parent.post_status IN ( \'wc-completed\',\'wc-processing\',\'wc-on-hold\',\' wc-refunded\')
									AND posts.post_date >= \'2018-01-01 00:00:00\'
									AND posts.post_date < \'2018-01-16 22:10:51\'',
					'group_by' => 'GROUP BY refund_id',
					'order_by' => 'ORDER BY post_date ASC',
				),
				array(
					'select'   => 'SELECT posts.id as refund_id,
									meta__refund_amount.meta_value as total_refund,
									posts.post_date as post_date,
									order_items.order_item_type as item_type,
									order_meta.total as total_sales,
									order_meta.shipping_total as total_shipping,
									order_meta.cart_tax as total_tax,
									order_meta.shipping_tax as total_shipping_tax,
									SUM( order_item_meta__qty.meta_value) as order_item_count',
					'join'     => 'INNER JOIN wptests_postmeta AS meta__refund_amount ON ( posts.ID = meta__refund_amount.post_id AND meta__refund_amount.meta_key = \'_refund_amount\' )
									LEFT JOIN wptests_woocommerce_order_items AS order_items ON (posts.ID = order_items.order_id)
									LEFT JOIN wptests_woocommerce_order_itemmeta AS order_item_meta__qty ON (order_items.order_item_id = order_item_meta__qty.order_item_id)  AND (order_item_meta__qty.meta_key = \'_qty\')
									LEFT JOIN wptests_posts AS parent ON posts.post_parent = parent.ID'
									. " LEFT JOIN {$table} AS order_meta ON ( posts.ID = order_meta.order_id )",
				),
			),
		);
	}

	public function test_rest_populate_address_indexes() {
		$order = $this->generate_order_and_empty_indexes();

		WC_Order_Data_Store_Custom_Table::rest_populate_address_indexes( array(
			'id' => 'add_order_indexes',
		) );

		$order_row = $this->get_order_row( $order->get_id() );

		$this->assertNotEmpty( $order_row['billing_index'] );
		$this->assertNotEmpty( $order_row['shipping_index'] );
	}

	public function test_rest_populate_address_indexes_only_runs_for_add_order_indexes() {
		$order = $this->generate_order_and_empty_indexes();

		WC_Order_Data_Store_Custom_Table::rest_populate_address_indexes( array(
			'id' => 'some_other_id',
		) );

		$order_row = $this->get_order_row( $order->get_id() );

		$this->assertEmpty( $order_row['billing_index'] );
		$this->assertEmpty( $order_row['shipping_index'] );
	}

	public function test_rest_populate_address_indexes_runs_on_woocommerce_rest_system_status_tool_executed() {
		$order = $this->generate_order_and_empty_indexes();

		/*
		 * Instead of calling the method directly, fire the action hook that runs after the default
		 * operation completes.
		 */
		do_action( 'woocommerce_rest_system_status_tool_executed', array(
			'id' => 'add_order_indexes',
		) );

		$order_row = $this->get_order_row( $order->get_id() );

		$this->assertNotEmpty( $order_row['billing_index'] );
		$this->assertNotEmpty( $order_row['shipping_index'] );
	}

	/**
	 * Shortcut for setting up reflection methods + properties for update_post_meta().
	 *
	 * @param WC_Order $order    The order object, passed by reference.
	 * @param bool     $creating Optional. The value 'creating' property in the new instance.
	 *                           Default is false.
	 */
	protected function invoke_update_post_meta( &$order, $creating = false ) {
		$instance = new WC_Order_Data_Store_Custom_Table();

		$property = new ReflectionProperty( $instance, 'creating' );
		$property->setAccessible( true );
		$property->setValue( $instance, (bool) $creating );

		$method   = new ReflectionMethod( $instance, 'update_post_meta' );
		$method->setAccessible( true );
		$method->invokeArgs( $instance, array( &$order ) );
	}

	/**
	 * Given an array of SQL clauses, normalize them for the sake of easier comparison.
	 *
	 * @param array $query An array of SQL clauses, as you might receive from the
	 *                     WC_Order_Data_Store_Custom_Table::filter_order_report_query method().
	 *
	 * @return array The $query array, with each row trimmed of excess whitespace.
	 */
	protected function normalize_query_array( $query ) {
		foreach ( (array) $query as $key => $clause ) {

			// Remove any empty lines.
			$clause = preg_replace( '/^\s+\n/m', '', $clause );

			// Strip excess leading tabs, which make comparisons difficult.
			$clause = preg_replace( '/^\t{2,}/m', "\t", $clause );

			// Trim leading or trailing whitespace.
			$clause = trim( $clause );

			$query[ $key ] = $clause;
		}

		return $query;
	}

	/**
	 * Helper method that generates an order, then empties the billing and shipping indexes.
	 *
	 * @global $wpdb
	 *
	 * @return WC_Order The generated order object.
	 */
	protected function generate_order_and_empty_indexes() {
		global $wpdb;

		$order = WC_Helper_Order::create_order();

		$wpdb->update( wc_custom_order_table()->get_table_name(), array(
			'billing_index'  => null,
			'shipping_index' => null,
		), array(
			'order_id' => $order->get_id(),
		) );

		return $order;
	}
}
