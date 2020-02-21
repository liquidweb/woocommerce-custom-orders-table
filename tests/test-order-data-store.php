<?php
/**
 * Tests for the WC_Order_Data_Store_Custom_Table class.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

/**
 * @group DataStores
 * @group Orders
 */
class OrderDataStoreTest extends TestCase {

	/**
	 * @test
	 * @testdox get_custom_table_name() can be filtered
	 */
	public function get_custom_table_name_can_be_filtered() {
		$table = 'some_custom_table_name_' . uniqid();

		add_filter( 'wc_custom_orders_table_name', function () use ( $table ) {
			return $table;
		} );

		$this->assertSame( $table, WC_Order_Data_Store_Custom_Table::get_custom_table_name() );
	}

	/**
	 * @test
	 */
	public function it_should_store_orders_in_the_orders_table() {
		$order = WC_Helper_Order::create_order();
		$row   = $this->get_order_row( $order->get_id() );

		$this->assertNotNull( $row, 'Expected to see a row in the orders table.' );
	}

	/**
	 * @test
	 * @depends it_should_store_orders_in_the_orders_table
	 */
	public function it_should_retrieve_order_meta_data() {
		$order = WC_Helper_Order::create_order();
		$row   = $this->get_order_row( $order->get_id() );

		// Refresh the order.
		$order = wc_get_order( $order->get_id() );

		$this->assertSame( $row['order_key'], $order->get_order_key() );
		$this->assertEquals( $row['customer_id'], $order->get_customer_id() );
		$this->assertSame( $row['billing_first_name'], $order->get_billing_first_name() );
		$this->assertSame( $row['billing_last_name'], $order->get_billing_last_name() );
		$this->assertSame( $row['billing_company'], $order->get_billing_company() );
		$this->assertSame( $row['billing_address_1'], $order->get_billing_address_1() );
		$this->assertSame( $row['billing_address_2'], $order->get_billing_address_2() );
		$this->assertSame( $row['billing_city'], $order->get_billing_city() );
		$this->assertSame( $row['billing_state'], $order->get_billing_state() );
		$this->assertSame( $row['billing_postcode'], $order->get_billing_postcode() );
		$this->assertSame( $row['billing_country'], $order->get_billing_country() );
		$this->assertSame( $row['billing_email'], $order->get_billing_email() );
		$this->assertSame( $row['billing_phone'], $order->get_billing_phone() );
		$this->assertSame( $row['shipping_first_name'], $order->get_shipping_first_name() );
		$this->assertSame( $row['shipping_last_name'], $order->get_shipping_last_name() );
		$this->assertSame( $row['shipping_company'], $order->get_shipping_company() );
		$this->assertSame( $row['shipping_address_1'], $order->get_shipping_address_1() );
		$this->assertSame( $row['shipping_address_2'], $order->get_shipping_address_2() );
		$this->assertSame( $row['shipping_city'], $order->get_shipping_city() );
		$this->assertSame( $row['shipping_state'], $order->get_shipping_state() );
		$this->assertSame( $row['shipping_postcode'], $order->get_shipping_postcode() );
		$this->assertSame( $row['shipping_country'], $order->get_shipping_country() );
		$this->assertSame( $row['payment_method'], $order->get_payment_method() );
		$this->assertSame( $row['payment_method_title'], $order->get_payment_method_title() );
		$this->assertEquals( $row['discount_total'], $order->get_discount_total() );
		$this->assertEquals( $row['discount_tax'], $order->get_discount_tax() );
		$this->assertEquals( $row['shipping_total'], $order->get_shipping_total() );
		$this->assertEquals( $row['shipping_tax'], $order->get_shipping_tax() );
		$this->assertEquals( $row['cart_tax'], $order->get_cart_tax() );
		$this->assertEquals( $row['total'], $order->get_total() );
		$this->assertEquals( $row['version'], $order->get_version() );
		$this->assertSame( $row['currency'], $order->get_currency() );
		$this->assertSame( wc_string_to_bool( $row['prices_include_tax'] ), $order->get_prices_include_tax() );
		$this->assertEquals( $row['transaction_id'], $order->get_transaction_id() );
		$this->assertSame( $row['customer_ip_address'], $order->get_customer_ip_address() );
		$this->assertSame( $row['customer_user_agent'], $order->get_customer_user_agent() );
		$this->assertSame( $row['created_via'], $order->get_created_via() );
		$this->assertEquals( $row['date_completed'], $order->get_date_completed() );
		$this->assertEquals( $row['date_paid'], $order->get_date_paid() );
		$this->assertEquals( $row['cart_hash'], $order->get_cart_hash() );
	}

	/**
	 * @test
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/49
	 */
	public function it_should_be_able_to_update_order_details() {
		$order  = WC_Helper_Order::create_order();
		$order->set_billing_first_name( 'James' );
		$order->set_billing_last_name( 'Bond' );
		$order->save();

		$row = $this->get_order_row( $order->get_id() );

		$this->assertSame( 'James', $row['billing_first_name'] );
		$this->assertSame( 'Bond', $row['billing_last_name'] );
	}

	/**
	 * @test
	 */
	public function it_should_attempt_to_migrate_missing_rows_from_post_meta() {
		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		$this->assertNull( $this->get_order_row( $order->get_id() ) );
		wc_get_order( $order->get_id() );
		$this->assertNotNull( $this->get_order_row( $order->get_id() ) );
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
		$this->toggle_use_custom_table( true );

		add_filter( 'wc_custom_order_table_automatic_migration', '__return_false' );

		$order = wc_get_order( $order->get_id() );

		$this->assertNull( $this->get_order_row( $order->get_id() ) );
	}

	/**
	 * @test
	 * @group Migrations
	 */
	public function it_should_be_able_to_backfill_post_meta() {
		$this->markTestIncomplete();
	}

	/**
	 * @test
	 * @testdox row_exists() should verify that the given primary key exists
	 */
	public function row_exists_should_verify_that_the_given_primary_key_exists() {
		$order = WC_Helper_Order::create_order();

		$this->assertTrue( $order->get_data_store()->row_exists( $order->get_id() ) );
		$this->assertFalse( $order->get_data_store()->row_exists( $order->get_id() + 1 ) );
	}

	/**
	 * @test
	 */
	public function it_should_preserve_the_orders_table_row_when_an_order_is_trashed() {
		$order      = WC_Helper_Order::create_order();
		$order_id   = $order->get_id();
		$data_store = $order->get_data_store();
		$order->delete( false );

		$this->assertTrue( $data_store->row_exists( $order_id ) );
	}

	/**
	 * @test
	 */
	public function it_should_remove_the_orders_table_row_when_an_order_is_permanently_deleted() {
		$order      = WC_Helper_Order::create_order();
		$order_id   = $order->get_id();
		$data_store = $order->get_data_store();
		$order->delete( true );

		$this->assertFalse( $data_store->row_exists( $order_id ) );
	}

	/**
	 * @test
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/68
	 */
	public function customer_notes_should_be_appended_to_order_data() {
		$order = WC_Helper_Order::create_order();
		$order->set_customer_note( 'This is a new post excerpt.' );
		$order->save();

		$order = wc_get_order( $order );

		$this->assertSame( 'This is a new post excerpt.', $order->get_customer_note() );
	}

	/**
	 * @test
	 */
	public function order_ids_can_be_found_based_on_their_order_keys() {
		$order    = WC_Helper_Order::create_order();
		$instance = new WC_Order_Data_Store_Custom_Table();

		$this->assertEquals(
			$order->get_id(),
			$instance->get_order_id_by_order_key( $order->get_order_key() ),
			'An order\'s key should be able to be used to find the corresponding order\'s ID.'
		);
	}

	/**
	 * @test
	 * @testdox search_orders() can search by order ID
	 * @group Search
	 */
	public function search_orders_can_search_by_order_id() {
		$instance = new WC_Order_Data_Store_Custom_Table();

		$this->assertSame(
			array( 123 ),
			$instance->search_orders( 123 ),
			'When given a numeric value, search_orders() should include that order ID.'
		);
	}

	/**
	 * @test
	 * @testdox search_orders() can also check against post meta
	 * @group Search
	 */
	public function search_orders_can_check_post_meta() {
		$instance = new WC_Order_Data_Store_Custom_Table();
		$order    = WC_Helper_Order::create_order();
		$term     = uniqid( 'search term ' );

		add_post_meta( $order->get_id(), 'some_custom_meta_key', $term );

		add_filter( 'woocommerce_shop_order_search_fields', function ( $fields ) {
			return [
				'some_custom_meta_key',
			];
		} );

		$this->assertEquals(
			[ $order->get_id() ],
			$instance->search_orders( $term ),
			'If post meta keys are specified, they should also be searched.'
		);
	}

	/**
	 * Same as test_search_orders_can_check_post_meta(), but the filter is never added.
	 *
	 * @test
	 * @testdox search_orders() only checks post meta if instructed to do so
	 * @depends test_search_orders_can_check_post_meta
	 * @group Search
	 */
	public function search_orders_only_checks_post_meta_if_specified() {
		$instance = new WC_Order_Data_Store_Custom_Table();
		$order    = WC_Helper_Order::create_order();
		$term     = uniqid( 'search term ' );

		add_post_meta( $order->get_id(), 'some_custom_meta_key', $term );

		$this->assertEmpty(
			$instance->search_orders( $term ),
			'Only search post meta if keys are provided.'
		);
	}

	/**
	 * @test
	 * @testdox search_orders() checks product names
	 * @group Search
	 */
	public function search_orders_checks_table_for_product_item_matches() {
		$instance = new WC_Order_Data_Store_Custom_Table();
		$product  = WC_Helper_Product::create_simple_product();
		$order    = WC_Helper_Order::create_order();
		$order->add_product( $product );
		$order->save();

		$this->assertEquals(
			array( $order->get_id() ),
			$instance->search_orders( $product->get_name() ),
			'Order searches should extend to the names of product items.'
		);
	}

	/**
	 * @test
	 * @testdox search_orders() checks product names using a LIKE comparison
	 * @depends search_orders_checks_table_for_product_item_matches
	 * @group Search
	 */
	public function search_orders_checks_table_for_product_item_matches_with_like_comparison() {
		$instance = new WC_Order_Data_Store_Custom_Table();
		$product  = WC_Helper_Product::create_simple_product();
		$product->set_name( 'Foo Bar Baz' );
		$product->save();
		$order    = WC_Helper_Order::create_order();
		$order->add_product( $product );
		$order->save();

		$this->assertEquals(
			array( $order->get_id() ),
			$instance->search_orders( 'bar' ),
			'Product items should be searched using a LIKE comparison and wildcards.'
		);
	}

	public function test_populate_from_meta() {
		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		$this->assertNull( $this->get_order_row( $order->get_id() ), 'The order row should not exist yet.' );

		// Refresh the order.
		$order   = wc_get_order( $order->get_id() );
		$mapping = WooCommerce_Custom_Orders_Table::get_postmeta_mapping();

		$order->get_data_store()->populate_from_meta( $order );

		$row = $this->get_order_row( $order->get_id() );

		foreach ( $mapping as $column => $meta_key ) {
			$this->assertEquals(
				get_post_meta( $order->get_id(), $meta_key, true ),
				$row[ $column ],
				"Value of the $column column key did not meet expectations."
			);
		}
	}

	public function test_populate_from_meta_can_delete_postmeta_keys() {
		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		$order   = wc_get_order( $order->get_id() );
		$mapping = WooCommerce_Custom_Orders_Table::get_postmeta_mapping();

		$order->get_data_store()->populate_from_meta( $order, true );

		foreach ( $mapping as $column => $meta_key ) {
			$this->assertEmpty(
				get_post_meta( $order->get_id(), $meta_key, true ),
				"Post meta key $meta_key should have been deleted."
			);
		}
	}

	/**
	 * Since populate_from_meta() is typically called within a while() loop, it's important to
	 * catch database errors and terminate so the script doesn't run forever.
	 *
	 * In this case, we're attempting to migrate two orders with the same order key but different
	 * order IDs.
	 */
	public function test_populate_from_meta_handles_errors() {
		global $wpdb;

		$wpdb->hide_errors();
		$wpdb->suppress_errors( true );

		$this->toggle_use_custom_table( false );
		$order1 = WC_Helper_Order::create_order();
		$order1->set_order_key( 'some-key' );
		$order1->save();
		$order2 = WC_Helper_Order::create_order();
		$order2->set_order_key( 'some-key' );
		$order2->save();
		$this->toggle_use_custom_table( true );

		// Refresh $order1 so we have access to the table-based data store.
		$order1 = wc_get_order( $order1->get_id() );
		$order1->get_data_store()->populate_from_meta( $order1 );

		$this->assertInstanceOf( 'WP_Error', $order1->get_data_store()->populate_from_meta( $order2 ) );
	}

	public function test_populate_from_meta_handles_wc_data_exceptions() {
		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		// Refresh the instance.
		$order = wc_get_order( $order->get_id() );

		add_action( 'woocommerce_order_object_updated_props', function () {
			throw new WC_Data_Exception( 'test-data-exception', 'A sample WC_Data_Exception' );
		} );

		$this->assertInstanceOf( 'WP_Error', $order->get_data_store()->populate_from_meta( $order ) );
	}

	public function test_backfill_postmeta() {
		$order = WC_Helper_Order::create_order();

		$order->get_data_store()->backfill_postmeta( $order );

		$meta = get_post_meta( $order->get_id() );

		$this->assertEmpty(
			array_diff(
				array_keys( $meta ),
				WooCommerce_Custom_Orders_Table::get_postmeta_mapping()
			),
			'The only post meta the order should have is what was backfilled from the custom table.'
		);
	}

	public function test_backfill_postmeta_does_nothing_if_the_order_row_is_empty() {
		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		$order = wc_get_order( $order->get_id() );

		$this->assertNull( $order->get_data_store()->backfill_postmeta( $order ) );
	}
}
