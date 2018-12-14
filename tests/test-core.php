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

	/**
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/98
	 */
	public function test_populate_order_from_post_meta_handles_invalid_billing_emails() {
		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		update_post_meta( $order->get_id(), '_billing_email', 'this is an invalid email address' );
		$this->toggle_use_custom_table( true );

		WooCommerce_Custom_Orders_Table::populate_order_from_post_meta( $order );

		$this->assertSame(
			'this is an invalid email address',
			$order->get_billing_email(),
			'Don\'t let an invalid email address cause a migration failure.'
		);
	}

	public function test_migrate_to_post_meta() {
		$order    = WC_Helper_Order::create_order();
		$row      = $this->get_order_row( $order->get_id() );
		$mapping  = WooCommerce_Custom_Orders_Table::get_postmeta_mapping();

		// For versions < WooCommerce 3.3, a few fields may be set.
		unset( $mapping['billing_email'], $mapping['customer_id'] );

		foreach ( $mapping as $column => $meta_key ) {
			$this->assertEmpty( get_post_meta( $order->get_id(), $meta_key, true ) );
		}

		WooCommerce_Custom_Orders_Table::migrate_to_post_meta( $order );

		foreach ( $mapping as $column => $meta_key ) {
			$this->assertEquals(
				$row[ $column ],
				get_post_meta( $order->get_id(), $meta_key, true ),
				"Value of the $meta_key meta key did not meet expectations."
			);
		}
	}

	public function test_migrate_to_post_meta_with_refunds() {
		$order    = WC_Helper_Order::create_order();
		$refund   = wc_create_refund( array(
			'order_id' => $order->get_id(),
			'amount'   => 5,
			'reason'   => 'For testing',
		) );
		$row      = $this->get_order_row( $refund->get_id() );
		$mapping  = WooCommerce_Custom_Orders_Table::get_postmeta_mapping();

		// For versions < WooCommerce 3.3, a few fields may be set.
		unset( $mapping['billing_email'], $mapping['customer_id'] );

		WooCommerce_Custom_Orders_Table::migrate_to_post_meta( $refund );

		foreach ( $mapping as $column => $meta_key ) {
			$this->assertEquals(
				$row[ $column ],
				get_post_meta( $refund->get_id(), $meta_key, true ),
				"Value of the $meta_key meta key did not meet expectations."
			);
		}
	}

	public function test_migrate_to_post_meta_returns_early_if_table_row_is_empty() {
		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		$last_changed = wp_cache_get( 'last_changed', 'posts' );

		WooCommerce_Custom_Orders_Table::migrate_to_post_meta( $order );

		$this->assertEquals(
			$last_changed,
			wp_cache_get( 'last_changed', 'posts' ),
			'No calls to update_post_meta() should have been made.'
		);
	}

	public function test_migrate_to_post_meta_can_delete_table_rows() {
		$order = WC_Helper_Order::create_order();

		WooCommerce_Custom_Orders_Table::migrate_to_post_meta( $order, true );

		$this->assertEmpty( $this->get_order_row( $order->get_id() ) );
	}
}
