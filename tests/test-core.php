<?php
/**
 * Tests for the core WooCommerce_Custom_Orders_Table class.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

class CoreTest extends TestCase {

	public function test_order_row_exists() {
		$order = WC_Helper_Order::create_order();

		$this->assertTrue( wc_custom_order_table()->row_exists( $order->get_id() ) );
		$this->assertFalse( wc_custom_order_table()->row_exists( $order->get_id() + 1 ) );
	}
}
