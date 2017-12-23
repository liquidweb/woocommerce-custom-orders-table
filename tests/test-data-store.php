<?php
/**
 * Tests for the WC_Order_Data_Store_Custom_Table class.
 *
 * @package Woocommerce_Order_Tables
 * @author  Liquid Web
 */

class DataStoreTest extends TestCase {

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

	/**
	 * @dataProvider order_type_provider()
	 */
	public function test_get_order_type( $order_type ) {
		$order = $this->factory()->order->create_and_get( array(
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
