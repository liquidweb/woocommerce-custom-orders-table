<?php
/**
 * Tests for the Migration utility.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

use LiquidWeb\WooCommerceCustomOrdersTable\Contracts\CustomTableDataStore;
use LiquidWeb\WooCommerceCustomOrdersTable\Exceptions\MigrationException;
use LiquidWeb\WooCommerceCustomOrdersTable\Exceptions\MigrationMappingException;
use LiquidWeb\WooCommerceCustomOrdersTable\Util\Migration;

/**
 * @covers LiquidWeb\WooCommerceCustomOrdersTable\Util\Migration
 * @group Migrations
 */
class MigrationTest extends TestCase {

	/**
	 * @test
	 * @group Orders
	 */
	public function it_should_be_able_to_migrate_an_order_from_post_meta() {
		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		$instance = new Migration();

		$this->assertTrue( $instance->migrate_to_custom_table( $order->get_id() ) );

		// Verify each column in the row.
		$row = $this->get_order_row( $order->get_id() );

		$this->assertEquals( $row['order_id'], $order->get_id() );
		$this->assertEquals( $row['order_key'], $order->get_order_key() );
		$this->assertEquals( $row['customer_id'], $order->get_customer_id() );
		$this->assertEquals( $row['payment_method'], $order->get_payment_method() );
		$this->assertEquals( $row['payment_method_title'], $order->get_payment_method_title() );
		$this->assertEquals( $row['transaction_id'], $order->get_transaction_id() );
		$this->assertEquals( $row['customer_ip_address'], $order->get_customer_ip_address() );
		$this->assertEquals( $row['customer_user_agent'], $order->get_customer_user_agent() );
		$this->assertEquals( $row['created_via'], $order->get_created_via() );
		$this->assertEquals( $row['date_completed'], $order->get_date_completed() );
		$this->assertEquals( $row['date_paid'], $order->get_date_paid() );
		$this->assertEquals( $row['cart_hash'], $order->get_cart_hash() );
		$this->assertEquals( $row['billing_index'], get_post_meta( $order->get_id(), '_billing_address_index', true ) );
		$this->assertSame( $row['billing_first_name'], $order->get_billing_first_name() );
		$this->assertSame( $row['billing_last_name'], $order->get_billing_last_name() );
		$this->assertSame( $row['billing_company'], $order->get_billing_company() );
		$this->assertSame( $row['billing_address_1'], $order->get_billing_address_1() );
		$this->assertEquals( $row['billing_address_2'], $order->get_billing_address_2() );
		$this->assertSame( $row['billing_city'], $order->get_billing_city() );
		$this->assertSame( $row['billing_state'], $order->get_billing_state() );
		$this->assertSame( $row['billing_postcode'], $order->get_billing_postcode() );
		$this->assertSame( $row['billing_country'], $order->get_billing_country() );
		$this->assertSame( $row['billing_email'], $order->get_billing_email() );
		$this->assertSame( $row['billing_phone'], $order->get_billing_phone() );
		$this->assertEquals( $row['shipping_index'], get_post_meta( $order->get_id(), '_shipping_address_index', true ) );
		$this->assertEquals( $row['shipping_first_name'], $order->get_shipping_first_name() );
		$this->assertEquals( $row['shipping_last_name'], $order->get_shipping_last_name() );
		$this->assertEquals( $row['shipping_company'], $order->get_shipping_company() );
		$this->assertEquals( $row['shipping_address_1'], $order->get_shipping_address_1() );
		$this->assertEquals( $row['shipping_address_2'], $order->get_shipping_address_2() );
		$this->assertEquals( $row['shipping_city'], $order->get_shipping_city() );
		$this->assertEquals( $row['shipping_state'], $order->get_shipping_state() );
		$this->assertEquals( $row['shipping_postcode'], $order->get_shipping_postcode() );
		$this->assertEquals( $row['shipping_country'], $order->get_shipping_country() );
		$this->assertEquals( $row['discount_total'], $order->get_discount_total() );
		$this->assertEquals( $row['discount_tax'], $order->get_discount_tax() );
		$this->assertEquals( $row['shipping_total'], $order->get_shipping_total() );
		$this->assertEquals( $row['shipping_tax'], $order->get_shipping_tax() );
		$this->assertEquals( $row['cart_tax'], $order->get_cart_tax() );
		$this->assertEquals( $row['total'], $order->get_total() );
		$this->assertSame( $row['version'], $order->get_version() );
		$this->assertSame( $row['currency'], $order->get_currency() );
		$this->assertSame( $row['prices_include_tax'], wc_bool_to_string( $order->get_prices_include_tax() ) );
	}

	/**
	 * @test
	 * @group Refunds
	 */
	public function it_should_be_able_to_migrate_a_refund_from_post_meta() {
		$this->toggle_use_custom_table( false );
		$order  = WC_Helper_Order::create_order();
		$refund = wc_create_refund( [
			'order_id'         => $order->get_id(),
			'reason'           => 'For testing',
			'refunded_by'      => 1,
		] );
		$refund->set_refunded_payment( true );
		$refund->save();
		$this->toggle_use_custom_table( true );

		$instance = new Migration( WC_Order_Refund_Data_Store_Custom_Table::class );

		$this->assertTrue( $instance->migrate_to_custom_table( $refund->get_id() ) );

		// Verify individual rows.
		$row = $this->get_order_row( $refund->get_id() );

		$this->assertEquals( $row['order_id'], $refund->get_id() );
		$this->assertEquals( $row['discount_total'], $refund->get_discount_total() );
		$this->assertEquals( $row['discount_tax'], $refund->get_discount_tax() );
		$this->assertEquals( $row['shipping_total'], $refund->get_shipping_total() );
		$this->assertEquals( $row['shipping_tax'], $refund->get_shipping_tax() );
		$this->assertEquals( $row['cart_tax'], $refund->get_cart_tax() );
		$this->assertEquals( $row['total'], $refund->get_total() );
		$this->assertSame( $row['version'], $refund->get_version() );
		$this->assertEquals( $row['currency'], $refund->get_currency() );
		$this->assertEquals( $row['prices_include_tax'], wc_bool_to_string( $refund->get_prices_include_tax() ) );
		$this->assertEquals( $row['amount'], $refund->get_amount() );
		$this->assertSame( $row['reason'], $refund->get_reason() );
		$this->assertEquals( $row['refunded_by'], $refund->get_refunded_by() );
		$this->assertTrue( $refund->get_refunded_payment() );
	}

	/**
	 * @test
	 * @group Errors
	 */
	public function it_should_throw_a_MigrationException_if_insertion_fails() {
		global $wpdb;

		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		// Insert another row with this ID.
		$wpdb->insert( WC_Order_Data_Store_Custom_Table::get_custom_table_name(), [
			'order_id'           => $order->get_id(),
			'billing_first_name' => uniqid(),
		] );
		$wpdb->hide_errors();
		$wpdb->suppress_errors( true );

		$this->expectException( MigrationException::class );

		$instance = new Migration( WC_Order_Data_Store_Custom_Table::class );
		$instance->migrate_to_custom_table( $order->get_id() );
	}

	/**
	 * @test
	 */
	public function it_should_be_able_to_delete_post_meta_after_migration() {
		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		$instance = $this->getMockBuilder( Migration::class )
			->setConstructorArgs( [ WC_Order_Data_Store_Custom_Table::class ] )
			->setMethods( [ 'delete_post_meta_keys' ] )
			->getMock();
		$instance->expects( $this->once() )
			->method( 'delete_post_meta_keys' )
			->with( $this->equalTo( $order->get_id() ) );

		$instance->migrate_to_custom_table( $order->get_id(), true );
	}

	/**
	 * @test
	 */
	public function it_should_not_clean_up_post_meta_by_default() {
		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		$instance = $this->getMockBuilder( Migration::class )
			->setConstructorArgs( [ WC_Order_Data_Store_Custom_Table::class ] )
			->setMethods( [ 'delete_post_meta_keys' ] )
			->getMock();
		$instance->expects( $this->never() )
			->method( 'delete_post_meta_keys' )
			->with( $this->equalTo( $order->get_id() ) );

		$instance->migrate_to_custom_table( $order->get_id() );
	}

	/**
	 * @test
	 * @depends it_should_throw_a_MigrationException_if_insertion_fails
	 */
	public function it_should_not_clean_up_post_meta_unless_insertion_was_successful() {
		global $wpdb;

		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		$instance = $this->getMockBuilder( Migration::class )
			->setConstructorArgs( [ WC_Order_Data_Store_Custom_Table::class ] )
			->setMethods( [ 'delete_post_meta_keys' ] )
			->getMock();
		$instance->expects( $this->never() )
			->method( 'delete_post_meta_keys' )
			->with( $this->equalTo( $order->get_id() ) );

		// Trigger an insertion error.
		$wpdb->insert( WC_Order_Data_Store_Custom_Table::get_custom_table_name(), [
			'order_id'           => $order->get_id(),
			'billing_first_name' => uniqid(),
		] );
		$wpdb->hide_errors();
		$wpdb->suppress_errors( true );

		$this->expectException( MigrationException::class );

		$instance->migrate_to_custom_table( $order->get_id(), true );
	}

	/**
	 * @test
	 * @group Orders
	 */
	public function it_should_be_able_to_restore_an_order_to_post_meta() {
		$order = WC_Helper_Order::create_order();

		$instance = new Migration( WC_Order_Data_Store_Custom_Table::class );

		$this->assertTrue( $instance->restore_to_post_meta( $order->get_id() ) );

		$meta = get_post_meta( $order->get_id() );
		$row  = $this->get_order_row( $order->get_id() );

		$this->assertArrayNotHasKey( 'order_id', $meta, 'The primary key does not belong in post meta.' );
		$this->assertEquals( $meta['_order_key'][0], $order->get_order_key() );
		$this->assertEquals( $meta['_customer_user'][0], $order->get_customer_id() );
		$this->assertEquals( $meta['_payment_method'][0], $order->get_payment_method() );
		$this->assertEquals( $meta['_payment_method_title'][0], $order->get_payment_method_title() );
		$this->assertEquals( $meta['_transaction_id'][0], $order->get_transaction_id() );
		$this->assertEquals( $meta['_customer_ip_address'][0], $order->get_customer_ip_address() );
		$this->assertEquals( $meta['_customer_user_agent'][0], $order->get_customer_user_agent() );
		$this->assertEquals( $meta['_created_via'][0], $order->get_created_via() );
		$this->assertEquals( $meta['_date_completed'][0], $order->get_date_completed() );
		$this->assertEquals( $meta['_date_paid'][0], $order->get_date_paid() );
		$this->assertSame( $meta['_cart_hash'][0], $order->get_cart_hash() );
		$this->assertSame( $meta['_billing_address_index'][0], $row['billing_index'] );
		$this->assertSame( $meta['_billing_first_name'][0], $order->get_billing_first_name() );
		$this->assertSame( $meta['_billing_last_name'][0], $order->get_billing_last_name() );
		$this->assertSame( $meta['_billing_company'][0], $order->get_billing_company() );
		$this->assertSame( $meta['_billing_address_1'][0], $order->get_billing_address_1() );
		$this->assertEquals( $meta['_billing_address_2'][0], $order->get_billing_address_2() );
		$this->assertSame( $meta['_billing_city'][0], $order->get_billing_city() );
		$this->assertSame( $meta['_billing_state'][0], $order->get_billing_state() );
		$this->assertSame( $meta['_billing_postcode'][0], $order->get_billing_postcode() );
		$this->assertSame( $meta['_billing_country'][0], $order->get_billing_country() );
		$this->assertSame( $meta['_billing_email'][0], $order->get_billing_email() );
		$this->assertSame( $meta['_billing_phone'][0], $order->get_billing_phone() );
		$this->assertEquals( $meta['_shipping_address_index'][0], $row['shipping_index'] );
		$this->assertEquals( $meta['_shipping_first_name'][0], $order->get_shipping_first_name() );
		$this->assertEquals( $meta['_shipping_last_name'][0], $order->get_shipping_last_name() );
		$this->assertEquals( $meta['_shipping_company'][0], $order->get_shipping_company() );
		$this->assertEquals( $meta['_shipping_address_1'][0], $order->get_shipping_address_1() );
		$this->assertEquals( $meta['_shipping_address_2'][0], $order->get_shipping_address_2() );
		$this->assertEquals( $meta['_shipping_city'][0], $order->get_shipping_city() );
		$this->assertEquals( $meta['_shipping_state'][0], $order->get_shipping_state() );
		$this->assertEquals( $meta['_shipping_postcode'][0], $order->get_shipping_postcode() );
		$this->assertEquals( $meta['_shipping_country'][0], $order->get_shipping_country() );
		$this->assertEquals( $meta['_cart_discount'][0], $order->get_discount_total() );
		$this->assertEquals( $meta['_cart_discount_tax'][0], $order->get_discount_tax() );
		$this->assertEquals( $meta['_order_shipping'][0], $order->get_shipping_total() );
		$this->assertEquals( $meta['_order_shipping_tax'][0], $order->get_shipping_tax() );
		$this->assertEquals( $meta['_order_tax'][0], $order->get_cart_tax() );
		$this->assertEquals( $meta['_order_total'][0], $order->get_total() );
		$this->assertSame( $meta['_order_version'][0], $order->get_version() );
		$this->assertSame( $meta['_order_currency'][0], $order->get_currency() );
		$this->assertSame( $meta['_prices_include_tax'][0], wc_bool_to_string( $order->get_prices_include_tax() ) );
	}

	/**
	 * @test
	 * @group Refunds
	 */
	public function it_should_be_able_to_restore_a_refund_to_post_meta() {
		$order  = WC_Helper_Order::create_order();
		$refund = wc_create_refund( [
			'order_id'         => $order->get_id(),
			'reason'           => 'For testing',
			'refunded_by'      => 1,
		] );

		$instance = new Migration( WC_Order_Refund_Data_Store_Custom_Table::class );

		$this->assertTrue( $instance->restore_to_post_meta( $refund->get_id() ) );

		$meta = get_post_meta( $refund->get_id() );
		$row  = $this->get_order_row( $order->get_id() );

		$this->assertArrayNotHasKey( 'order_id', $meta, 'The primary key does not belong in post meta.' );
		$this->assertEquals( $meta['_cart_discount'][0], $refund->get_discount_total() );
		$this->assertEquals( $meta['_cart_discount_tax'][0], $refund->get_discount_tax() );
		$this->assertEquals( $meta['_order_shipping'][0], $refund->get_shipping_total() );
		$this->assertEquals( $meta['_order_shipping_tax'][0], $refund->get_shipping_tax() );
		$this->assertEquals( $meta['_order_tax'][0], $refund->get_cart_tax() );
		$this->assertEquals( $meta['_order_total'][0], $refund->get_total() );
		$this->assertSame( $meta['_order_version'][0], $refund->get_version() );
		$this->assertEquals( $meta['_order_currency'][0], $refund->get_currency() );
		$this->assertEquals( $meta['_prices_include_tax'][0], wc_bool_to_string( $refund->get_prices_include_tax() ) );
		$this->assertEquals( $meta['_refund_amount'][0], $refund->get_amount() );
		$this->assertSame( $meta['_refund_reason'][0], $refund->get_reason() );
		$this->assertEquals( $meta['_refunded_by'][0], $refund->get_refunded_by() );
		//$this->assertTrue( $meta['_refunded_payment'][0] );
	}

	/**
	 * @test
	 * @group Errors
	 */
	public function it_should_throw_a_MigrationMappingException_if_a_column_has_a_value_but_does_not_have_a_corresponding_post_meta_key() {
		$order   = WC_Helper_Order::create_order();
		$mapping = WC_Order_Data_Store_Custom_Table::map_columns_to_post_meta_keys();
		unset( $mapping['billing_first_name'] );

		$instance = new Migration( WC_Order_Data_Store_Custom_Table::class );
		$property = new \ReflectionProperty( $instance, 'mappings' );
		$property->setAccessible( true );
		$property->setValue( $instance, $mapping );

		$this->expectException( MigrationMappingException::class );

		$instance->restore_to_post_meta( $order->get_id() );
	}

	/**
	 * @test
	 * @group Errors
	 */
	public function it_should_ignore_missing_columns_if_there_is_no_value() {
		$order   = WC_Helper_Order::create_order();
		$mapping = WC_Order_Data_Store_Custom_Table::map_columns_to_post_meta_keys();
		unset( $mapping['shipping_address_2'] );

		$instance = new Migration( WC_Order_Data_Store_Custom_Table::class );
		$property = new \ReflectionProperty( $instance, 'mappings' );
		$property->setAccessible( true );
		$property->setValue( $instance, $mapping );

		$this->assertTrue( $instance->restore_to_post_meta( $order->get_id() ) );
	}

	/**
	 * @test
	 * @group Errors
	 */
	public function it_should_throw_a_MigrationException_if_not_all_rows_are_migrated_back_to_post_meta() {
		$order    = WC_Helper_Order::create_order();
		$instance = new Migration( WC_Order_Data_Store_Custom_Table::class );

		// Short-circuit the update_post_meta() call for order_key, causing an error.
		add_filter( 'update_post_metadata', function ( $check, $object_id, $meta_key ) {
			if ( '_order_key' === $meta_key ) {
				return false;
			}

			return $check;
		}, 10, 3 );

		$this->expectException( MigrationException::class );

		$instance->restore_to_post_meta( $order->get_id() );
	}

	/**
	 * @test
	 */
	public function it_should_be_able_to_delete_the_custom_table_row_after_restoring_to_post_meta() {
		$order    = WC_Helper_Order::create_order();
		$instance = $this->getMockBuilder( Migration::class )
			->setConstructorArgs( [ WC_Order_Data_Store_Custom_Table::class ] )
			->setMethods( [ 'delete_custom_table_row' ] )
			->getMock();
		$instance->expects( $this->once() )
			->method( 'delete_custom_table_row' )
			->with( $this->equalTo( $order->get_id() ) );

		$instance->restore_to_post_meta( $order->get_id(), true );
	}

	/**
	 * @test
	 */
	public function it_should_not_remove_custom_table_rows_by_default() {
		$order    = WC_Helper_Order::create_order();
		$instance = $this->getMockBuilder( Migration::class )
			->setConstructorArgs( [ WC_Order_Data_Store_Custom_Table::class ] )
			->setMethods( [ 'delete_custom_table_row' ] )
			->getMock();
		$instance->expects( $this->never() )
			->method( 'delete_custom_table_row' )
			->with( $this->equalTo( $order->get_id() ) );

		$instance->restore_to_post_meta( $order->get_id() );
	}

	/**
	 * @test
	 * @depends it_should_throw_a_MigrationException_if_not_all_rows_are_migrated_back_to_post_meta
	 */
	public function it_should_not_remove_custom_table_rows_unless_all_post_meta_keys_have_been_restored() {
		$order    = WC_Helper_Order::create_order();
		$instance = $this->getMockBuilder( Migration::class )
			->setConstructorArgs( [ WC_Order_Data_Store_Custom_Table::class ] )
			->setMethods( [ 'delete_custom_table_row' ] )
			->getMock();
		$instance->expects( $this->never() )
			->method( 'delete_custom_table_row' )
			->with( $this->equalTo( $order->get_id() ) );

		// Short-circuit the update_post_meta() call for order_key, causing an error.
		add_filter( 'update_post_metadata', function ( $check, $object_id, $meta_key ) {
			if ( '_order_key' === $meta_key ) {
				return false;
			}

			return $check;
		}, 10, 3 );

		$this->expectException( MigrationException::class );

		$instance->restore_to_post_meta( $order->get_id(), true );
	}

	/**
	 * @test
	 * @group Orders
	 */
	public function it_should_be_able_to_delete_post_meta_keys_for_orders() {
		$this->toggle_use_custom_table( false );
		$order = WC_Helper_Order::create_order();
		$this->toggle_use_custom_table( true );

		// Add some meta that does not exist in the mapping.
		add_post_meta( $order->get_id(), '_some_custom_key', uniqid() );

		$instance = new Migration( WC_Order_Data_Store_Custom_Table::class );

		$this->assertGreaterThan( 0, $instance->delete_post_meta_keys( $order->get_id() ) );

		$post_meta = get_post_meta( $order->get_id() );

		foreach ( WC_Order_Data_Store_Custom_Table::map_columns_to_post_meta_keys() as $meta_key ) {
			$this->assertArrayNotHasKey( $meta_key, $post_meta );
		}

		$this->assertArrayHasKey( '_some_custom_key', $post_meta, 'Meta keys outside of the mapping should be left alone.' );
	}

	/**
	 * @test
	 * @group Refunds
	 */
	public function it_should_be_able_to_delete_post_meta_keys_for_refunds() {
		$this->toggle_use_custom_table( false );
		$order  = WC_Helper_Order::create_order();
		$refund = wc_create_refund( [
			'order_id' => $order->get_id(),
		] );
		$this->toggle_use_custom_table( true );

		// Add some meta that does not exist in the mapping.
		add_post_meta( $refund->get_id(), '_some_custom_key', uniqid() );

		$instance = new Migration( WC_Order_Refund_Data_Store_Custom_Table::class );

		$this->assertGreaterThan( 0, $instance->delete_post_meta_keys( $refund->get_id() ) );

		$post_meta = get_post_meta( $refund->get_id() );

		foreach ( WC_Order_Refund_Data_Store_Custom_Table::map_columns_to_post_meta_keys() as $meta_key ) {
			$this->assertArrayNotHasKey( $meta_key, $post_meta );
		}

		$this->assertArrayHasKey( '_some_custom_key', $post_meta, 'Meta keys outside of the mapping should be left alone.' );
	}

	/**
	 * @test
	 * @group Orders
	 */
	public function it_should_be_able_delete_an_orders_table_row() {
		$order = WC_Helper_Order::create_order();

		$this->assertTrue( $order->get_data_store()->row_exists( $order->get_id() ) );

		$instance = new Migration( WC_Order_Data_Store_Custom_Table::class );

		$this->assertTrue( $instance->delete_custom_table_row( $order->get_id() ) );
		$this->assertFalse( $order->get_data_store()->row_exists( $order->get_id() ) );
	}

	/**
	 * @test
	 * @group Refunds
	 */
	public function it_should_be_able_delete_a_refunds_table_row() {
		$order  = WC_Helper_Order::create_order();
		$refund = wc_create_refund( [
			'order_id' => $order->get_id(),
		] );

		$this->assertTrue( $refund->get_data_store()->row_exists( $refund->get_id() ) );

		$instance = new Migration( WC_Order_Refund_Data_Store_Custom_Table::class );

		$this->assertTrue( $instance->delete_custom_table_row( $refund->get_id() ) );
		$this->assertFalse( $refund->get_data_store()->row_exists( $refund->get_id() ) );
	}

	/**
	 * @test
	 */
	public function it_should_return_false_if_the_given_id_does_not_exist() {
		$order = WC_Helper_Order::create_order();

		$instance = new Migration( WC_Order_Data_Store_Custom_Table::class );

		$this->assertTrue( $instance->delete_custom_table_row( $order->get_id() ) );
		$this->assertFalse( $instance->delete_custom_table_row( $order->get_id() ) );
	}
}
