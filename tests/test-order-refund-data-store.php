<?php
/**
 * Tests for the WC_Order_Refund_Data_Store_Custom_Table class.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

class OrderRefundDataStoreTest extends TestCase {

	protected $user;

	/**
	 * Since refunds are issued by people, generate and act as a user.
	 *
	 * @before
	 */
	public function set_current_user() {
		$this->user = $this->factory()->user->create();

		wp_set_current_user( $this->user );
	}

	public function test_read_order_data_meta() {
		$order  = WC_Helper_Order::create_order();
		$refund = wc_create_refund( array(
			'order_id' => $order->get_id(),
			'amount'   => 5,
			'reason'   => 'For testing',
		) );

		// Refresh the refund.
		$refund = wc_get_order( $refund->get_id() );

		$this->assertEquals( 5, $refund->get_amount() );
		$this->assertEquals( $this->user, $refund->get_refunded_by() );
		$this->assertEquals( 'For testing', $refund->get_reason() );
	}

	public function test_update_post_meta() {
		$order  = WC_Helper_Order::create_order();
		$refund = wc_create_refund( array(
			'order_id' => $order->get_id(),
			'amount'   => 7,
			'reason'   => 'For testing',
		) );
		$row    = $this->get_order_row( $refund->get_id() );

		$this->assertEquals( 7, $row['amount'] );
		$this->assertEquals( $this->user, $row['refunded_by'] );
		$this->assertEquals( 'For testing', $row['reason'] );
	}
}
