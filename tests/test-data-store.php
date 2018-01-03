<?php
/**
 * Tests for the WC_Order_Data_Store_Custom_Table class.
 *
 * @package Woocommerce_Order_Tables
 * @author  Liquid Web
 */

class DataStoreTest extends TestCase {

	/**
	 * Fire the necessary actions to bootstrap WordPress.
	 *
	 * @before
	 */
	public function init() {
		do_action( 'init' );
	}

	/**
	 * Remove any closures that have been assigned to filters.
	 *
	 * @after
	 */
	public function remove_filter_callbacks() {
		remove_all_filters( 'woocommerce_shop_order_search_fields' );
	}

	public function test_create() {
		$instance  = new WC_Order_Data_Store_Custom_Table();
		$order     = $this->factory()->order->create_and_get();
		$order_key = 'my_custom_order_key';

		add_filter( 'woocommerce_generate_order_key', function () use ( $order_key ) {
			return $order_key;
		} );

		$instance->create( $order );

		$this->assertEquals( 'wc_' . $order_key, $order->get_order_key() );
	}

	public function test_get_order_count() {
		$orders = $this->factory()->order->create_many( 5, array(
			'post_status' => 'wc-pending',
		) );

		$this->assertEquals(
			count( $orders ),
			( new WC_Order_Data_Store_Custom_Table() )->get_order_count( 'wc-pending' )
		);
	}

	public function test_get_order_count_filters_by_status() {
		$this->factory()->order->create( array(
			'post_status' => 'not_a_pending_status',
		) );

		$this->assertEquals(
			0,
			( new WC_Order_Data_Store_Custom_Table() )->get_order_count( 'wc-pending' ),
			'The get_order_count() method should only count records matching $status.'
		);
	}

	public function test_get_unpaid_orders() {
		$order   = $this->factory()->order->create( array(
			'post_status' => 'wc-pending',
		) );
		$pending = ( new WC_Order_Data_Store_Custom_Table() )
			->get_unpaid_orders( time() + DAY_IN_SECONDS );

		$this->assertCount( 1, $pending, 'There should be only one unpaid order.' );
		$this->assertEquals(
			$order,
			array_shift( $pending ),
			'The ID of the one unpaid order should be that of $order.'
		);
	}

	public function test_get_unpaid_orders_uses_date_filtering() {
		$order   = $this->factory()->order->create( array(
			'post_status' => 'wc-pending',
		) );
		$pending = ( new WC_Order_Data_Store_Custom_Table() )
			->get_unpaid_orders( time() - HOUR_IN_SECONDS );

		$this->assertEmpty( $pending, 'No unpaid orders should match the time window.' );
	}

	public function test_search_orders_can_search_by_order_id() {
		$this->assertEquals(
			array( 123 ),
			( new WC_Order_Data_Store_Custom_Table() )->search_orders( 123 ),
			'When given a numeric value, search_orders() should include that order ID.'
		);
	}

	public function test_search_orders_can_check_post_meta() {
		$order = $this->factory()->order->create();
		$term  = uniqid( 'search term ' );

		add_post_meta( $order, 'some_custom_meta_key', $term );

		add_filter( 'woocommerce_shop_order_search_fields', function () {
			return array( 'some_custom_meta_key' );
		} );

		$this->assertEquals(
			array( $order ),
			( new WC_Order_Data_Store_Custom_Table() )->search_orders( $term ),
			'If post meta keys are specified, they should also be searched.'
		);
	}

	/**
	 * Same as test_search_orders_can_check_post_meta(), but the filter is never added.
	 */
	public function test_search_orders_only_checks_post_meta_if_specified() {
		$order = $this->factory()->order->create();
		$term  = uniqid( 'search term ' );

		add_post_meta( $order, 'some_custom_meta_key', $term );

		$this->assertEmpty(
			( new WC_Order_Data_Store_Custom_Table() )->search_orders( $term ),
			'Only search post meta if keys are provided.'
		);
	}

	/**
	 * @dataProvider order_type_provider()
	 */
	public function test_get_order_type( $order_type ) {
		$order = $this->factory()->order->create( array(
			'post_type' => $order_type,
		) );

		$this->assertEquals(
			$order_type,
			( new WC_Order_Data_Store_Custom_Table() )->get_order_type( $order )
		);
	}

	/**
	 * Provide a list of all available order types.
	 */
	public function order_type_provider() {
		$types = array();

		foreach ( wc_get_order_types() as $type ) {
			$types[ $type ] = array( $type );
		}

		return $types;
	}
}
