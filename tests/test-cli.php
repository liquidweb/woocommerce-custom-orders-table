<?php
/**
 * Tests for the WP-CLI commands.
 *
 * @package Woocommerce_Order_Tables
 * @author  Liquid Web
 */

class CLITest extends TestCase {

	/**
	 * Holds a fresh instance of the WP-CLI command class.
	 *
	 * @var WC_Custom_Order_Table_CLI
	 */
	protected $cli;

	/**
	 * @before
	 */
	public function init() {
		$this->cli = new WC_Custom_Order_Table_CLI();
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
			'batch' => 2,
		) );

		$this->assertContains( 'LIMIT 2', $wpdb->last_query, 'The batch size should be used to limit query results.' );
		$this->assertEquals(
			5,
			$this->count_orders_in_table_with_ids( $order_ids ),
			'Expected to see 5 total orders in the custom table.'
		);
	}

	public function test_backfill() {
		$order_ids = $this->generate_orders( 5 );
		$index     = 0;

		foreach ( $order_ids as $order_id ) {
			$this->assertEmpty( get_post_meta( $order_id, '_billing_email', true ) );
		}

		$this->cli->backfill(array(), array( 'batch' => 2));

		foreach ( $order_ids as $order_id ) {
			$index++;

			$this->assertNotEmpty(
				get_post_meta( $order_id, '_billing_email', true ),
				"The billing email for order #{$index} was not saved to post meta."
			);
		}
	}
}
