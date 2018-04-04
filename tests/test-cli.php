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
	public function init() {
		$this->cli = new WooCommerce_Custom_Orders_Table_CLI();
	}

	public function test_count() {
		$this->toggle_use_custom_table( false );
		$this->generate_orders( 3 );
		$this->toggle_use_custom_table( true );

		$this->assertEquals( 3, $this->cli->count() );
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
	}

	/**
	 * Trigger a database error in the same way as the test_populate_from_meta_handles_errors test.
	 *
	 * @see DataStoreTest::test_populate_from_meta_handles_errors()
	 */
	public function test_migrate_stops_on_database_error() {
		$this->toggle_use_custom_table( false );
		$order1 = WC_Helper_Order::create_order();
		$order1->set_order_key( '' );
		$order1->save();
		$order2 = WC_Helper_Order::create_order();
		$order2->set_order_key( '' );
		$order2->save();
		$this->toggle_use_custom_table( true );

		$this->cli->migrate();

		$error = array_pop( WP_CLI::$__logger );
		$this->assertEquals( 'error', $error['level'], 'Expected to see a call to WP_CLI::error().' );
	}

	/**
	 * @link https://github.com/liquidweb/woocommerce-custom-orders-table/issues/43
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

	public function test_migrate_with_duplicate_ids() {
		$this->toggle_use_custom_table( false );
		$order_id = WC_Helper_Order::create_order()->get_id();
		$this->toggle_use_custom_table( true );

		// Implicitly migrate the data.
		$order = wc_get_order( $order_id );
		$order->get_total();

		$this->assertEquals( 1, $this->count_orders_in_table_with_ids( $order_id ));

		$this->cli->migrate();

		$this->assertEquals( 1, $this->count_orders_in_table_with_ids( $order_id ));
	}

	public function test_backfill() {
		$order_ids = $this->generate_orders( 5 );
		$index     = 0;

		foreach ( $order_ids as $order_id ) {
			$this->assertEmpty( get_post_meta( $order_id, '_billing_first_name', true ) );
		}

		$this->cli->backfill( array(), array(
			'batch-size' => 2,
		) );

		foreach ( $order_ids as $order_id ) {
			$index++;

			$this->assertNotEmpty(
				get_post_meta( $order_id, '_billing_email', true ),
				"The billing email for order #{$index} was not saved to post meta."
			);
		}
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
}
