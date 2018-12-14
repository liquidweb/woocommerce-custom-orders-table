<?php
/**
 * Tests for the WP-CLI commands.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

class CLITest extends TestCase {

	/**
	 * Holds a fresh instance of the WP-CLI command class.
	 *
	 * @var WooCommerce_Custom_Orders_Table_CLI
	 */
	protected $cli;

	/**
	 * @before
	 */
	public function init_cli() {
		WP_CLI::reset();

		$this->cli = new WooCommerce_Custom_Orders_Table_CLI();

		// Reset the WP_CLI counts.
		WP_CLI::$__logger = array();
		WP_CLI::$__counts = array(
			'debug'   => 0,
			'info'    => 0,
			'success' => 0,
			'warning' => 0,
			'error'   => 0,
		);
	}

	public function test_count() {
		$this->toggle_use_custom_table( false );
		$this->generate_orders( 3 );
		$this->toggle_use_custom_table( true );

		$count = $this->cli->count();

		$this->assertEquals( 3, $count, 'Expected to see 3 orders to migrate.' );
		$this->assertInternalType( 'integer', $count, 'Expected the count to return as an integer.' );
	}

	/**
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/45
	 */
	public function test_count_handles_refunded_orders() {
		$this->toggle_use_custom_table( false );
		$order_ids = $this->generate_orders( 2 );
		$refund    = wc_create_refund( array(
			'order_id' => $order_ids[0]
		) );
		$this->toggle_use_custom_table( true );

		$this->assertEquals( 3, $this->cli->count(), 'Expected to see 3 orders to migrate.' );
	}

	/**
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/57
	 */
	public function test_count_only_counts_unmigrated_orders() {
		$this->toggle_use_custom_table( false );
		$this->generate_orders( 3 );
		$this->toggle_use_custom_table( true );
		$this->generate_orders( 2 );

		$this->assertSame( 3, $this->cli->count(), 'Expected to only see 3 orders to migrate.' );
	}

	public function test_migrate() {
		$this->toggle_use_custom_table( false );
		$order_ids = $this->generate_orders( 5 );
		$this->toggle_use_custom_table( true );

		$this->assertEquals(
			0,
			$this->count_orders_in_table_with_ids( $order_ids ),
			'Before migration, these orders should not exist in the orders table.'
		);

		$this->cli->migrate();

		$this->assertEquals(
			5,
			$this->count_orders_in_table_with_ids( $order_ids ),
			'Expected to see 5 orders in the custom table.'
		);
		$this->greaterThanOrEqual( 5, WP_CLI::$__counts['debug'], 'Expected to see at least five calls to WP_CLI::debug().' );
		$this->cli->assertReceivedMessage( '5 orders were migrated.', 'success' );
	}

	public function test_migrate_works_in_batches() {
		global $wpdb;

		$this->toggle_use_custom_table( false );
		$order_ids = $this->generate_orders( 5 );
		$this->toggle_use_custom_table( true );

		$this->cli->migrate( array(), array(
			'batch-size' => 2,
		) );

		$this->assertContains( 'LIMIT 2', $wpdb->last_query, 'The batch size should be used to limit query results.' );
		$this->assertEquals(
			5,
			$this->count_orders_in_table_with_ids( $order_ids ),
			'Expected to see 5 total orders in the custom table.'
		);

		$this->cli->assertReceivedMessage( 'Beginning batch #1 (2 orders/batch).', 'debug' );
		$this->cli->assertReceivedMessage( 'Beginning batch #2 (2 orders/batch).', 'debug' );
		$this->cli->assertReceivedMessage( 'Beginning batch #3 (2 orders/batch).', 'debug' );
	}

	public function test_migrate_warns_if_no_orders_need_migrating() {
		$this->assertEquals( 0, $this->cli->count(), 'Expected no orders to need migration.' );

		$this->cli->migrate();

		$this->assertEquals( 1, WP_CLI::$__counts['warning'], 'Expected to see a warning if no orders require migration.' );
	}

	/**
	 * Trigger a database error in the same way as the test_populate_from_meta_handles_errors test.
	 *
	 * @see DataStoreTest::test_populate_from_meta_handles_errors()
	 */
	public function test_migrate_skips_on_database_error() {
		global $wpdb;

		// This test will trigger a DB error due to the duplicate order key.
		$wpdb->suppress_errors();

		$this->toggle_use_custom_table( false );
		$order1 = WC_Helper_Order::create_order();
		$order1->set_order_key( 'some-key' );
		$order1->save();
		$order2 = WC_Helper_Order::create_order();
		$order2->set_order_key( 'some-key' );
		$order2->save();
		$this->toggle_use_custom_table( true );

		$this->cli->migrate();

		$this->cli->assertReceivedMessageContaining( "Duplicate entry 'some-key' for key 'order_key'", 'warning' );
	}

	public function test_migrate_catches_infinite_loops() {
		$this->toggle_use_custom_table( false );
		$order_ids = $this->generate_orders( 2 );
		$this->toggle_use_custom_table( true );

		// After an order is inserted, delete it to force an infinite loop.
		add_action( 'woocommerce_order_object_updated_props', function ( $order ) {
			global $wpdb;

			$wpdb->delete( wc_custom_order_table()->get_table_name(), array(
				'order_id' => $order->get_id(),
			) );
		} );

		$this->cli->migrate( array(), array(
			'batch-size' => 1,
		) );

		$error = array_pop( WP_CLI::$__logger );
		$this->assertEquals( 'error', $error['level'], 'Expected to see a call to WP_CLI::error().' );
	}

	/**
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/43
	 */
	public function test_migrate_handles_errors_with_wc_get_order() {
		$this->toggle_use_custom_table( false );
		$order_ids = $this->generate_orders( 3 );
		$this->toggle_use_custom_table( true );

		// For the first item, cause wc_get_order() to break due to a non-existent class.
		add_filter( 'woocommerce_order_class', function ( $classname, $order_type, $order_id ) use ( $order_ids ) {
			return (int) $order_id === $order_ids[0] ? 'SomeNonExistentClassName' : $classname;
		}, 10, 3 );

		$this->cli->migrate();

		$this->assertEquals(
			2,
			$this->count_orders_in_table_with_ids( $order_ids ),
			'Expected to only see two orders in the custom table.'
		);
	}

	public function test_migrate_warns_if_no_orders_were_successfully_migrated() {
		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		// For the first item, cause wc_get_order() to break due to a non-existent class.
		add_filter( 'woocommerce_order_class', function ( $classname ) {
			return 'SomeNonExistentClassName';
		} );

		$this->cli->migrate();

		$this->assertNull( $this->get_order_row( $order->get_id() ) );
		$this->assertEquals( 1, WP_CLI::$__counts['warning'], 'Expected to see a warning if no orders were migrated.' );
	}

	/**
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/45
	 */
	public function test_migrate_handles_refunded_orders() {
		$this->toggle_use_custom_table( false );
		$order_ids   = $this->generate_orders( 2 );
		$refund      = wc_create_refund( array(
			'order_id' => $order_ids[0]
		) );
		$order_ids[] = $refund->get_id();
		$this->toggle_use_custom_table( true );

		$this->cli->migrate();

		$this->assertEquals(
			3,
			$this->count_orders_in_table_with_ids( $order_ids ),
			'Expected to see both orders and the refund in the table.'
		);
	}

	/**
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/56
	 */
	public function test_migrate_handles_exceptions() {
		$this->toggle_use_custom_table( false );
		$order_ids = $this->generate_orders( 3 );
		$this->toggle_use_custom_table( true );

		// Set a duplicate order key for the middle order.
		update_post_meta( $order_ids[1], '_order_key', get_post_meta( $order_ids[0], '_order_key' ) );

		$this->cli->migrate();

		$this->assertEquals(
			2,
			$this->count_orders_in_table_with_ids( $order_ids ),
			'Expected to only see two orders in the custom table.'
		);

		$this->assertContains( $order_ids[1], $this->get_skipped_ids() );
	}

	public function test_migrate_with_duplicate_ids() {
		$this->toggle_use_custom_table( false );
		$order_id = WC_Helper_Order::create_order()->get_id();
		$this->toggle_use_custom_table( true );

		// Implicitly migrate the data.
		$order = wc_get_order( $order_id );
		$order->get_total();

		$this->assertEquals( 1, $this->count_orders_in_table_with_ids( array( $order_id ) ) );

		$this->cli->migrate();

		$this->assertEquals( 1, $this->count_orders_in_table_with_ids( array( $order_id ) ) );
	}

	/**
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/69
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/96
	 */
	public function test_migrate_with_duplicate_null_order_ids() {
		$this->toggle_use_custom_table( false );
		$order1 = WC_Helper_Order::create_order();
		$order1->set_order_key( '' );
		$order1->save();
		$order2 = WC_Helper_Order::create_order();
		$order2->set_order_key( '' );
		$order2->save();
		$this->toggle_use_custom_table( true );

		$this->cli->migrate();

		$this->assertSame(
			2,
			$this->count_orders_in_table_with_ids( array( $order1->get_id(), $order2->get_id() ) ),
			'Two distinct orders can share a NULL order_key.'
		);
	}

	public function test_migrate_aborts_if_no_orders_require_migration() {
		$this->assertSame( 0, $this->cli->count(), 'Expected to start with 0 orders.' );

		$this->cli->migrate();

		$this->assertSame( 1, WP_CLI::$__counts['warning'], 'A warning should have been displayed.' );
	}

	public function test_migrate_output_when_items_were_skipped() {
		global $wpdb;

		$wpdb->suppress_errors();

		$this->toggle_use_custom_table( false );
		$order1 = WC_Helper_Order::create_order();
		$order1->set_order_key( 'first' );
		$order1->save();
		$order2 = WC_Helper_Order::create_order();
		$order2->set_order_key( 'first' );
		$order2->save();
		$order3 = WC_Helper_Order::create_order();
		$order3->set_order_key( 'third' );
		$order3->save();
		$this->toggle_use_custom_table( true );

		$this->cli->migrate();

		$this->assertEquals(
			2,
			$this->count_orders_in_table_with_ids( array( $order2->get_id(), $order3->get_id() ) ),
			'Expected to only see two orders in the custom table.'
		);

		$this->cli->assertReceivedMessage( '2 orders were migrated, with 1 skipped.', 'warning' );
	}

	/**
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/55
	 */
	public function test_migrate_cleans_up_post_meta_by_default() {
		$this->toggle_use_custom_table( false );
		$order_ids = $this->generate_orders( 1 );
		$order_id  = array_shift( $order_ids );
		$this->toggle_use_custom_table( true );

		$this->assertNotEmpty( get_post_meta( $order_id, '_billing_email', true ) );

		$this->cli->migrate();

		$this->assertEmpty( get_post_meta( $order_id, '_billing_email', true ) );
	}

	/**
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/55
	 */
	public function test_migrate_can_leave_post_meta() {
		$this->toggle_use_custom_table( false );
		$order_ids = $this->generate_orders( 1 );
		$order_id  = array_shift( $order_ids );
		$this->toggle_use_custom_table( true );

		$this->assertNotEmpty( get_post_meta( $order_id, '_billing_email', true ) );

		$this->cli->migrate( array(), array( 'save-post-meta' => true ) );

		$this->assertNotEmpty( get_post_meta( $order_id, '_billing_email', true ) );
	}

	public function test_backfill() {
		$order_ids = $this->generate_orders( 5 );

		foreach ( $order_ids as $order_id ) {
			$this->assertEmpty( get_post_meta( $order_id, '_billing_first_name', true ) );
		}

		$this->cli->backfill();

		foreach ( $order_ids as $order_id ) {
			$this->assertNotEmpty(
				get_post_meta( $order_id, '_billing_email', true ),
				"The billing email for order {$order_id} was not saved to post meta."
			);
		}
	}

	public function test_backfill_warns_if_no_orders_need_migrating() {
		$this->assertEquals( 0, $this->cli->count(), 'Expected no orders to need migration.' );

		$this->cli->backfill();

		$this->assertEquals( 1, WP_CLI::$__counts['warning'], 'Expected to see a warning if no orders require migration.' );
	}

	public function test_backfill_when_an_order_has_been_deleted() {
		$order1 = WC_Helper_Order::create_order();
		$order2 = WC_Helper_Order::create_order();

		// The order has been force deleted.
		wp_delete_post( $order1->get_id(), true );

		$this->cli->backfill();

		$this->assertEmpty( get_post_meta( $order1->get_id(), '_billing_email', true ) );
		$this->assertNotEmpty( get_post_meta( $order2->get_id(), '_billing_email', true ) );
	}

	public function test_backfill_if_no_orders_were_backfilled() {
		$this->toggle_use_custom_table( false );
		WC_Helper_Order::create_order();

		$this->cli->backfill();

		$this->assertEquals( 1, WP_CLI::$__counts['warning'], 'Expected to see a warning if no orders were backfilled.' );
	}

	/**
	 * Retrieve the array of skipped IDs from the CLI instance.
	 *
	 * @return array
	 */
	protected function get_skipped_ids() {
		$property = new ReflectionProperty( $this->cli, 'skipped_ids' );
		$property->setAccessible( true );

		return (array) $property->getValue( $this->cli );
	}
}
