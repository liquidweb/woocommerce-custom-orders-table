<?php
/**
 * Tests for the WC_Order_Data_Store_Custom_Table class.
 *
 * @package Woocommerce_Order_Tables
 * @author  Liquid Web
 */

class DataStoreTest extends TestCase {

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

		add_filter( 'woocommerce_shop_order_search_fields', function () {
			remove_filter( 'woocommerce_shop_order_search_fields', __FUNCTION__ );
			return array( 'some_custom_meta_key' );
		} );

		$this->assertEquals(
			array( $order->get_id() ),
			$instance->search_orders( $term ),
			'If post meta keys are specified, they should also be searched.'
		);
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
}
