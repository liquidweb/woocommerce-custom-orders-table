<?php
/**
 * Tests for the WC_Order_Data_Store_Custom_Table class.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

class OrderDataStoreTest extends TestCase {

	public function test_loading_a_product_can_automatically_populate_from_meta() {
		$this->toggle_use_custom_table( false );
		$order_id = WC_Helper_Order::create_order()->get_id();
		$this->toggle_use_custom_table( true );

		$this->assertEquals( 0, $this->count_orders_in_table_with_ids( array( $order_id ) ) );

		$order = wc_get_order( $order_id );

		$this->assertEquals( 1, $this->count_orders_in_table_with_ids( array( $order->get_id() ) ) );
	}

	/**
	 * Same as test_loading_a_product_can_automatically_populate_from_meta(), but with the
	 * auto-migration disabled via the 'wc_custom_order_table_automatic_migration' filter.
	 */
	public function test_wc_custom_order_table_automatic_migration_filter() {
		$this->toggle_use_custom_table( false );
		$order_id = WC_Helper_Order::create_order()->get_id();
		$this->toggle_use_custom_table( true );

		add_filter( 'wc_custom_order_table_automatic_migration', '__return_false' );

		$order = wc_get_order( $order_id );

		$this->assertEmpty( $order->get_total() );
		$this->assertEquals( 0, $this->count_orders_in_table_with_ids( array( $order_id ) ) );
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

	public function test_get_order_data_from_table() {
		$order = WC_Helper_Order::create_order();
		$data  = $order->get_data_store()->get_order_data_from_table( $order );

		$this->assertEquals( $order->get_id(), $data['order_id'] );
		$this->assertEquals( $order->get_billing_email(), $data['billing_email'] );
		$this->assertEquals( $order->get_prices_include_tax(), $data['prices_include_tax'] );
	}

	public function test_get_order_data_from_table_when_order_is_still_in_post_meta() {
		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		$this->assertEmpty( $order->get_data_store()->get_order_data_from_table( $order ) );
	}

	public function test_update_post_meta_for_new_order() {
		$order = new WC_Order( wp_insert_post( array(
			'post_type' => 'product',
		) ) );
		$order->set_currency( 'USD' );
		$order->set_prices_include_tax( false );
		$order->set_customer_ip_address( '127.0.0.1' );
		$order->set_customer_user_agent( 'PHPUnit' );

		$this->invoke_update_post_meta( $order );

		$row = $this->get_order_row( $order->get_id() );

		$this->assertEquals( 'USD', $row['currency'] );
		$this->assertEquals( '127.0.0.1', $row['customer_ip_address'] );
		$this->assertEquals( 'PHPUnit', $row['customer_user_agent'] );
	}

	/**
	 * @link https://github.com/liquidweb/woocommerce-custom-orders-table/issues/49
	 */
	public function test_update_post_meta_for_existing_order_id() {
		$order = WC_Helper_Order::create_order();
		$order->set_customer_user_agent( 'PHPUnit' );

		$this->invoke_update_post_meta( $order );

		$row = $this->get_order_row( $order->get_id() );

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

	public function test_populate_from_meta() {
		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		$this->assertNull( $this->get_order_row( $order->get_id() ), 'The order row should not exist yet.' );

		// Refresh the order.
		$order   = wc_get_order( $order->get_id() );
		$mapping = WooCommerce_Custom_Orders_Table::get_postmeta_mapping();

		$order->get_data_store()->populate_from_meta( $order );

		$row = $this->get_order_row( $order->get_id() );

		foreach ( $mapping as $column => $meta_key ) {
			$this->assertEquals(
				get_post_meta( $order->get_id(), $meta_key, true ),
				$row[ $column ],
				"Value of the $column column key did not meet expectations."
			);
		}
	}

	public function test_populate_from_meta_can_delete_postmeta_keys() {
		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		$order   = wc_get_order( $order->get_id() );
		$mapping = WooCommerce_Custom_Orders_Table::get_postmeta_mapping();

		$order->get_data_store()->populate_from_meta( $order, true );

		foreach ( $mapping as $column => $meta_key ) {
			$this->assertEmpty(
				get_post_meta( $order->get_id(), $meta_key, true ),
				"Post meta key $meta_key should have been deleted."
			);
		}
	}

	/**
	 * Since populate_from_meta() is typically called within a while() loop, it's important to
	 * catch database errors and terminate so the script doesn't run forever.
	 *
	 * In this case, we're attempting to migrate two orders with the same order key but different
	 * order IDs.
	 */
	public function test_populate_from_meta_handles_errors() {
		global $wpdb;

		$wpdb->hide_errors();
		$wpdb->suppress_errors( true );

		$this->toggle_use_custom_table( false );
		$order1 = WC_Helper_Order::create_order();
		$order1->set_order_key( '' );
		$order1->save();
		$order2 = WC_Helper_Order::create_order();
		$order2->set_order_key( '' );
		$order2->save();
		$this->toggle_use_custom_table( true );

		// Refresh $order1 so we have access to the table-based data store.
		$order1 = wc_get_order( $order1->get_id() );
		$order1->get_data_store()->populate_from_meta( $order1 );

		$this->assertInstanceOf( 'WP_Error', $order1->get_data_store()->populate_from_meta( $order2 ) );
	}

	/**
	 * Shortcut for setting up reflection methods + properties for update_post_meta().
	 *
	 * @param WC_Order $order The order object, passed by reference.
	 */
	protected function invoke_update_post_meta( &$order ) {
		$instance = new WC_Order_Data_Store_Custom_Table();
		$method   = new ReflectionMethod( $instance, 'update_post_meta' );
		$method->setAccessible( true );
		$method->invokeArgs( $instance, array( &$order ) );
	}
}
