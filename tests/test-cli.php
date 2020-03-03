<?php
/**
 * Tests for the WP-CLI commands.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

/**
 * @group CLI
 * @group Migrations
 */
class CLITest extends TestCase {

	/**
	 * Holds a fresh instance of the WP-CLI command class.
	 *
	 * @var WooCommerce_Custom_Orders_Table_CLI
	 */
	protected $cli;

	/**
	 * Populate $this->cli with a clean instance of our CLI class.
	 *
	 * @before
	 */
	public function init_cli() {
		WP_CLI::reset();

		$this->cli = new WooCommerce_Custom_Orders_Table_CLI();
	}

	/**
	 * @test
	 * @group Orders
	 */
	public function count_should_retrieve_the_number_of_posts_requiring_migration() {
		$this->toggle_use_custom_table( false );
		$this->generate_orders( 3 );
		$this->toggle_use_custom_table( true );

		$count = $this->cli->count();

		$this->assertEquals( 3, $count, 'Expected to see 3 posts to migrate.' );
		$this->assertInternalType( 'integer', $count, 'Expected the count to return as an integer.' );
	}

	/**
	 * @test
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/45
	 * @group Refunds
	 */
	public function count_should_include_refunds_that_require_migration() {
		$this->toggle_use_custom_table( false );
		$order_ids = $this->generate_orders( 2 );
		$refund    = wc_create_refund( array(
			'order_id' => $order_ids[0],
		) );
		$this->toggle_use_custom_table( true );

		$this->assertEquals( 3, $this->cli->count(), 'Expected to see 3 posts to migrate.' );
	}

	/**
	 * @test
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/57
	 * @group Orders
	 */
	public function count_should_ignore_orders_already_present_in_the_custom_table() {
		$this->toggle_use_custom_table( false );
		$this->generate_orders( 3 );
		$this->toggle_use_custom_table( true );
		$this->generate_orders( 2 ); // Created while custom tables are active.

		$this->assertSame( 3, $this->cli->count(), 'Expected to only see 3 orders to migrate.' );
	}

	/**
	 * @test
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/57
	 * @group Refunds
	 */
	public function count_should_ignore_refunds_already_present_in_the_custom_table() {
		$this->toggle_use_custom_table( false );
		$old_orders = $this->generate_orders( 3 );
		wc_create_refund( [
			'order_id' => $old_orders[1],
		] );
		$this->toggle_use_custom_table( true );
		$new_orders = $this->generate_orders( 2 ); // Created while custom tables are active.
		wc_create_refund( [
			'order_id' => $new_orders[0],
		] );

		$this->assertSame(
			4,
			$this->cli->count(),
			'Expected to only see 4 posts to migrate: three orders and one refund.'
		);
	}

	/**
	 * @test
	 * @group Orders
	 */
	public function migrate_should_move_rows_from_post_meta_to_the_custom_orders_table() {
		$this->toggle_use_custom_table( false );
		$order_ids = $this->generate_orders( 3 );
		$this->toggle_use_custom_table( true );

		foreach ( $order_ids as $order_id ) {
			$this->assertNull(
				$this->get_order_row( $order_id ),
				'Orders should not exist in the orders table prior to migration.'
			);
		}

		$this->cli->migrate();

		foreach ( $order_ids as $order_id ) {
			$this->assertNotNull(
				$this->get_order_row( $order_id ),
				'Orders should have been migrated into the custom table.'
			);
		}

		$this->greaterThanOrEqual(
			count( $order_ids ),
			WP_CLI::$__counts['debug'],
			'Expected to see at least one call to WP_CLI::debug() for each migrated order.'
		);
		$this->cli->assertReceivedMessage( '5 orders were migrated.', 'success' );
	}

	/**
	 * @test
	 * @group Refunds
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/45
	 */
	public function migrate_should_be_able_to_handle_refunded_orders() {
		$this->toggle_use_custom_table( false );
		$order_ids = $this->generate_orders( 2 );
		$refund    = wc_create_refund( [
			'order_id' => $order_ids[0],
		] );
		$this->toggle_use_custom_table( true );

		$this->cli->migrate();

		$this->assertNotNull( $this->get_order_row( $order_ids[0] ) );
		$this->assertNotNull( $this->get_order_row( $order_ids[1] ) );
		$this->assertNotNull( $this->get_order_row( $refund->get_id() ) );
	}

	/**
	 * @test
	 * @group Orders
	 */
	public function migrate_should_be_able_to_work_in_batches() {
		global $wpdb;

		$this->toggle_use_custom_table( false );
		$order_ids = $this->generate_orders( 5 );
		$this->toggle_use_custom_table( true );

		$this->cli->migrate( [], [
			'batch-size' => 2,
		] );

		$this->assertContains( 'LIMIT 0, 2', $wpdb->last_query, 'The batch size should be used to limit query results.' );

		foreach ( $order_ids as $order_id ) {
			$this->assertNotNull(
				$this->get_order_row( $order_id ),
				'Orders should have been migrated into the custom table.'
			);
		}

		$this->cli->assertReceivedMessage( 'Beginning batch #1 (2 orders/batch).', 'debug' );
		$this->cli->assertReceivedMessage( 'Beginning batch #2 (2 orders/batch).', 'debug' );
		$this->cli->assertReceivedMessage( 'Beginning batch #3 (2 orders/batch).', 'debug' );
	}

	/**
	 * @test
	 * @group Orders
	 */
	public function migrate_should_warn_the_user_if_no_orders_need_migrating() {
		$this->assertEquals( 0, $this->cli->count(), 'Expected no orders to need migration.' );

		$this->cli->migrate();

		$this->assertEquals( 1, WP_CLI::$__counts['warning'], 'Expected to see a warning if no orders require migration.' );
	}

	/**
	 * Trigger a database error to ensure we handle them gracefully.
	 *
	 * @global $wpdb
	 *
	 * @test
	 * @group Errors
	 */
	public function migrate_skips_posts_on_database_error() {
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

	/**
	 * @test
	 * @group Errors
	 */
	public function migrate_should_recognize_infinite_loops_and_abort() {
		$this->toggle_use_custom_table( false );
		$order_ids = $this->generate_orders( 2 );
		$this->toggle_use_custom_table( true );

		// After an order is inserted, delete it to force an infinite loop.
		add_action( 'woocommerce_order_object_updated_props', function ( $order ) {
			global $wpdb;

			$wpdb->delete( wc_custom_order_table()->get_orders_table_name(), array(
				'order_id' => $order->get_id(),
			) );
		} );

		$this->cli->migrate( [], [
			'batch-size' => 1,
		] );

		$error = array_pop( WP_CLI::$__logger );
		$this->assertEquals( 'error', $error['level'], 'Expected to see a call to WP_CLI::error().' );
	}

	/**
	 * @test
	 * @testdox Migrate should handle errors caused by wc_get_order()
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/43
	 * @group Errors
	 */
	public function migrate_should_handle_errors_caused_by_wc_get_order() {
		$this->toggle_use_custom_table( false );
		$order_ids = $this->generate_orders( 3 );
		$this->toggle_use_custom_table( true );

		// For the first item, cause wc_get_order() to break due to a non-existent class.
		add_filter( 'woocommerce_order_class', function ( $classname, $order_type, $order_id ) use ( $order_ids ) {
			return (int) $order_id === $order_ids[0] ? 'SomeNonExistentClassName' : $classname;
		}, 10, 3 );

		$this->cli->migrate();

		$this->assertNull( $this->get_order_row( $order_ids[0] ) );
		$this->assertNotNull( $this->get_order_row( $order_ids[1] ) );
		$this->assertNotNull( $this->get_order_row( $order_ids[2] ) );
	}

	/**
	 * @test
	 * @group Errors
	 */
	public function migrate_should_warn_the_user_if_no_orders_were_successfully_migrated() {
		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		// For the first item, cause wc_get_order() to break due to a non-existent class.
		add_filter( 'woocommerce_order_class', function ( $classname ) {
			return 'SomeNonExistentClassName';
		} );

		$this->cli->migrate();

		$this->assertNull( $this->get_order_row( $order->get_id() ) );
		$this->assertContains( array(
			'level'   => 'warning',
			'message' => 'No orders were migrated.',
		), WP_CLI::$__logger );
	}

	/**
	 * @test
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/148
	 * @group Orders
	 */
	public function test_migrate_excludes_skipped_ids_from_the_query_loop() {
		global $wpdb;

		$this->toggle_use_custom_table( false );
		$order_ids = $this->generate_orders( 4 );
		$this->toggle_use_custom_table( true );

		// Log queries without turning on SAVE_QUERIES.
		$orders_table = wc_custom_order_table()->get_orders_table_name();
		$pattern      = "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$orders_table}";
		$query_log    = [];

		add_filter( 'woocommerce_order_class', function ( $classname, $order_type, $order_id ) use ( $order_ids ) {
			return (int) $order_id === $order_ids[0] ? 'SomeNonExistentClassName' : $classname;
		}, 10, 3 );

		add_filter( 'query', function ( $query ) use ( &$query_log, $pattern ) {

			// Only track queries if it includes the posts/orders join.
			if ( false !== strpos( preg_replace( '/\s+/', ' ', $query ), $pattern ) ) {
				$query_log[] = $query;
			}

			return $query;
		} );

		$this->cli->migrate( [], [
			'batch-size' => 2,
		] );

		/*
		 * We should expect to see only three queries here:
		 *
		 * 1. Get the first 2 orders.
		 * 2. Get the second two, but exclude skipped IDs.
		 * 3. Verify that we're wrapping up once we've exhausted the results.
		 */
		$this->assertCount( 3, $query_log );
		$this->assertContains( 'LIMIT 1, 2', $query_log[1] );
		$this->assertSame( $query_log[1], $query_log[2] );
	}

	/**
	 * @test
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/56
	 * @group Errors
	 */
	public function migrate_should_handle_exceptions() {
		$this->toggle_use_custom_table( false );
		$order_ids = $this->generate_orders( 3 );
		$this->toggle_use_custom_table( true );

		// Set a duplicate order key for the middle order.
		update_post_meta( $order_ids[1], '_order_key', get_post_meta( $order_ids[0], '_order_key' ) );

		$this->cli->migrate();

		$this->assertNotNull( $this->get_order_row( $order_ids[0] ) );
		$this->assertNull( $this->get_order_row( $order_ids[1] ) );
		$this->assertNotNull( $this->get_order_row( $order_ids[2] ) );

		$this->assertContains( $order_ids[1], $this->get_skipped_ids() );
	}

	/**
	 * @test
	 * @group Errors
	 */
	public function test_migrate_with_duplicate_ids() {
		$this->markTestIncomplete( 'Needs revisiting.' );

		$this->toggle_use_custom_table( false );
		$order_id = WC_Helper_Order::create_order()->get_id();
		$this->toggle_use_custom_table( true );

		// Implicitly migrate the data.
		$order = wc_get_order( $order_id );
		$order->get_total();

		$this->assertNotNull( 1, $this->get_order_row( $order_id ) );

		$this->cli->migrate();

		$this->assertNotNull( 1, $this->get_order_row( $order_id ) );
	}

	/**
	 * @test
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/69
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/96
	 * @group Orders
	 */
	public function two_distinct_orders_can_share_a_NULL_order_key() {
		$this->toggle_use_custom_table( false );
		$order1 = WC_Helper_Order::create_order();
		$order1->set_order_key( '' );
		$order1->save();
		$order2 = WC_Helper_Order::create_order();
		$order2->set_order_key( '' );
		$order2->save();
		$this->toggle_use_custom_table( true );

		$this->cli->migrate();

		$this->assertNotNull( $this->get_order_row( $order1->get_id() ) );
		$this->assertNotNull( $this->get_order_row( $order2->get_id() ) );
	}

	/**
	 * @test
	 */
	public function the_migrate_command_should_abort_if_no_orders_require_migration() {
		$this->assertSame( 0, $this->cli->count(), 'Expected to start with 0 orders.' );

		$this->cli->migrate();

		$this->assertSame( 1, WP_CLI::$__counts['warning'], 'A warning should have been displayed.' );
	}

	public function test_migrate_output_when_items_were_skipped() {
		$this->markTestIncomplete( 'Needs revisiting.' );

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

		$this->assertNotNull( $this->get_order_row( $order1->get_id() ) );
		$this->assertNull( $this->get_order_row( $order2->get_id() ) );
		$this->assertNotNull( $this->get_order_row( $order1->get_id() ) );

		$this->cli->assertReceivedMessage( '2 orders were migrated, with 1 skipped.', 'warning' );
	}

	/**
	 * @test
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/55
	 */
	public function migrate_should_clean_up_post_meta_by_default() {
		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		$this->assertNotEmpty( get_post_meta( $order->get_id(), '_billing_email', true ) );

		$this->cli->migrate();

		$this->assertEmpty( get_post_meta( $order->get_id(), '_billing_email', true ) );
	}

	/**
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/55
	 */
	public function users_may_optionally_leave_post_meta_in_place() {
		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		$this->assertNotEmpty( get_post_meta( $order->get_id(), '_billing_email', true ) );

		$this->cli->migrate( [], [
			'save-post-meta' => true,
		] );

		$this->assertNotEmpty( get_post_meta( $order->get_id(), '_billing_email', true ) );
	}

	/**
	 * @test
	 */
	public function backfill_should_restore_values_to_post_meta() {
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

	/**
	 * @test
	 */
	public function backfill_should_warn_the_user_if_no_orders_need_migration() {
		$this->assertEquals( 0, $this->cli->count(), 'Expected no orders to need migration.' );

		$this->cli->backfill();

		$this->assertEquals( 1, WP_CLI::$__counts['warning'], 'Expected to see a warning if no orders require migration.' );
	}

	/**
	 * @test
	 */
	public function backfill_can_have_a_batch_size_of_zero() {
		$order_ids = $this->generate_orders( 5 );

		$this->cli->backfill( [], [
			'batch-size' => 0,
		] );

		foreach ( $order_ids as $order_id ) {
			$this->assertNotEmpty(
				get_post_meta( $order_id, '_billing_email', true ),
				"The billing email for order {$order_id} was not saved to post meta."
			);
		}
	}

	/**
	 * @test
	 */
	public function backfill_should_drop_values_if_the_corresponding_order_no_longer_exists() {
		$order_ids = $this->generate_orders( 2 );

		// The order has been force deleted.
		wp_delete_post( $order_ids[0], true );

		$this->cli->backfill();

		$this->assertEmpty( get_post_meta( $order_ids[0], '_billing_email', true ) );
		$this->assertNotEmpty( get_post_meta( $order_ids[1], '_billing_email', true ) );
	}

	/**
	 * @test
	 * @group Errors
	 */
	public function backfill_should_warn_the_user_if_no_orders_were_backfilled() {
		$this->toggle_use_custom_table( false );
		WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		$this->cli->backfill();

		$this->assertEquals( 1, WP_CLI::$__counts['warning'], 'Expected to see a warning if no orders were backfilled.' );
	}

	/**
	 * @test
	 * @group Errors
	 */
	public function the_exception_handler_should_rethrow_the_exception() {
		$exception = new Exception( uniqid() );

		try {
			WooCommerce_Custom_Orders_Table_CLI::handle_exceptions( $exception );
		} catch ( Exception $e ) {
			$this->assertSame( $exception, $e );
			return;
		}

		$this->fail( 'Did not receive the expected exception.' );
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
