<?php
/**
 * Tests for the WC_Order_Refund_Data_Store_Custom_Table class.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

/**
 * @group DataStores
 * @group Refunds
 */
class OrderRefundDataStoreTest extends TestCase {

	/**
	 * @var \WP_User
	 */
	private $user;

	/**
	 * Since refunds are issued by people, generate and act as a user.
	 *
	 * @before
	 */
	public function set_current_user() {
		$this->user = $this->factory()->user->create();

		wp_set_current_user( $this->user );
	}

	/**
	 * @test
	 */
	public function it_should_store_refunds_in_the_refunds_table() {
		$order  = WC_Helper_Order::create_order();
		$refund = wc_create_refund( array(
			'order_id' => $order->get_id(),
			'amount'   => 5,
			'reason'   => 'For testing',
		) );

		$row = $this->get_refund_row( $refund->get_id() );

		$this->assertNotEmpty( $row, 'Expected to see a row in the refunds table.' );
		$this->assertEquals( 5, $row['amount'] );
		$this->assertEquals( $this->user, $refund->get_refunded_by() );
		$this->assertEquals( 'For testing', $row['reason'] );
	}

	/**
	 * @test
	 */
	public function an_order_may_have_multiple_refunds() {
		$order   = WC_Helper_Order::create_order();
		$refund1 = wc_create_refund( array(
			'order_id' => $order->get_id(),
			'amount'   => 1,
			'reason'   => 'For testing',
		) );
		$refund2 = wc_create_refund( array(
			'order_id' => $order->get_id(),
			'amount'   => 2,
			'reason'   => 'Also for testing',
		) );

		$this->assertNotNull( $this->get_refund_row( $refund1->get_id() ) );
		$this->assertNotNull( $this->get_refund_row( $refund2->get_id() ) );
	}

	/**
	 * @test
	 * @depends it_should_store_refunds_in_the_refunds_table
	 */
	public function it_should_retrieve_refund_meta_data() {
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

	/**
	 * @test
	 */
	public function it_should_be_able_to_update_refund_details() {
		$order  = WC_Helper_Order::create_order();
		$refund = wc_create_refund( array(
			'order_id' => $order->get_id(),
			'amount'   => 7,
			'reason'   => 'For testing',
		) );

		$refund->set_amount( 9 );
		$refund->set_reason( 'Some other reason' );
		$refund->save();

		$row = $this->get_refund_row( $refund->get_id() );

		$this->assertEquals( 9, $row['amount'] );
		$this->assertEquals( 'Some other reason', $row['reason'] );
	}

	/**
	 * @test
	 */
	public function it_should_preserve_the_refunds_table_row_when_a_refund_is_trashed() {
		$order  = WC_Helper_Order::create_order();
		$refund = wc_create_refund( array(
			'order_id' => $order->get_id(),
			'amount'   => 7,
			'reason'   => 'For testing',
		) );

		$this->assertTrue( $refund->get_data_store()->row_exists( $refund->get_id() ) );
	}

	/**
	 * @test
	 */
	public function it_should_remove_the_refunds_table_row_when_a_refund_is_permanently_deleted() {
		$order  = WC_Helper_Order::create_order();
		$refund = wc_create_refund( array(
			'order_id' => $order->get_id(),
			'amount'   => 7,
			'reason'   => 'For testing',
		) );

		$this->assertFalse( $refund->get_data_store()->row_exists( $refund->get_id() ) );
	}

	/**
	 * @test
	 * @group Migrations
	 */
	public function it_should_attempt_to_migrate_missing_rows_from_post_meta() {
		$this->toggle_use_custom_table( false );
		$order  = WC_Helper_Order::create_order();
		$refund = wc_create_refund( array(
			'order_id' => $order->get_id(),
			'amount'   => 7,
			'reason'   => 'For testing',
		) );
		$this->toggle_use_custom_table( true );

		$refund = wc_get_order( $refund->get_id() );
		$row    = $this->get_refund_row( $refund->get_id() );

		$this->assertEquals( 7, $row['amount'] );
		$this->assertEquals( $row['amount'], $refund->get_amount() );
		$this->assertEquals( 'For testing', $row['reason'] );
		$this->assertEquals( $row['reason'], $refund->get_reason() );
	}

	/**
	 * @test
	 * @testdox It should not attempt to migrate missing rows if the wc_custom_order_table_automatic_migration filter returns false
	 * @depends it_should_attempt_to_migrate_missing_rows_from_post_meta
	 * @group Migrations
	 */
	public function do_not_migrate_if_wc_custom_order_table_automatic_migration_is_false() {
		$this->toggle_use_custom_table( false );
		$order  = WC_Helper_Order::create_order();
		$refund = wc_create_refund( array(
			'order_id' => $order->get_id(),
			'amount'   => 7,
			'reason'   => 'For testing',
		) );
		$this->toggle_use_custom_table( true );

		add_filter( 'wc_custom_order_table_automatic_migration', '__return_false' );

		$refund = wc_get_order( $refund->get_id() );

		$this->assertNull( $this->get_refund_row( $refund->get_id() ) );
		$this->assertEquals( 7, $refund->get_amount() );
		$this->assertEquals( 'For testing', $refund->get_reason() );
	}

	/**
	 * @test
	 * @group Migrations
	 */
	public function it_should_be_able_to_backfill_post_meta() {
		$this->markTestIncomplete( 'Backfilling refund data is not currently supported.' );

		$order  = WC_Helper_Order::create_order();
		$refund = wc_create_refund( array(
			'order_id' => $order->get_id(),
			'amount'   => 7,
			'reason'   => 'For testing',
		) );
	}

	/**
	 * @test
	 * @testdox row_exists() should verify that the given primary key exists
	 */
	public function row_exists_should_verify_that_the_given_primary_key_exists() {
		$order  = WC_Helper_Order::create_order();
		$refund = wc_create_refund( array(
			'order_id' => $order->get_id(),
		) );

		$this->assertTrue( $refund->get_data_store()->row_exists( $refund->get_id() ) );
		$this->assertFalse( $refund->get_data_store()->row_exists( $refund->get_id() + 1 ) );
	}
}
