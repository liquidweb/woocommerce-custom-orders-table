<?php
/**
 * Tests for the plugin installation.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

/**
 * @covers \WooCommerce_Custom_Orders_Table_Install
 */
class InstallationTest extends TestCase {

	/**
	 * Clean up existing installations.
	 *
	 * @global $wpdb
	 */
	public function setUp() {
		global $wpdb;

		parent::setUp();

		$wpdb->query( sprintf(
			'DROP TABLE IF EXISTS %s, %s',
			esc_sql( WC_Order_Data_Store_Custom_Table::get_custom_table_name() ),
			esc_sql( WC_Order_Refund_Data_Store_Custom_Table::get_custom_table_name() )
		) );
		delete_option( WooCommerce_Custom_Orders_Table_Install::SCHEMA_VERSION_KEY );
	}

	/**
	 * @test
	 *
	 * @global $wpdb
	 */
	public function custom_tables_should_be_created_upon_activation() {
		global $wpdb;

		WooCommerce_Custom_Orders_Table_Install::activate();

		$this->assertTrue(
			(bool) $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.tables WHERE table_name = %s LIMIT 1',
				wc_custom_order_table()->get_orders_table_name()
			) ),
			'Upon activation, the orders table should be created.'
		);
		$this->assertTrue(
			(bool) $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.tables WHERE table_name = %s LIMIT 1',
				WC_Order_Refund_Data_Store_Custom_Table::get_custom_table_name()
			) ),
			'Upon activation, the refunds table should be created.'
		);
		$this->assertNotEmpty(
			get_option( WooCommerce_Custom_Orders_Table_Install::SCHEMA_VERSION_KEY ),
			'The schema version should be stored in the options table.'
		);
	}

	/**
	 * @test
	 * @testdox The activate() method should return early if the schema is already up-to-date
	 */
	public function the_activate_method_should_return_early_if_the_schema_is_already_up_to_date() {
		WooCommerce_Custom_Orders_Table_Install::activate();

		$this->assertFalse(
			WooCommerce_Custom_Orders_Table_Install::activate(),
			'The activate() method should return false if the schema versions match.'
		);
	}

	/**
	 * @test
	 */
	public function it_should_update_the_table_if_a_newer_schema_is_available() {
		WooCommerce_Custom_Orders_Table_Install::activate();

		// Get the current schema version, then increment it.
		$property = new ReflectionProperty( 'WooCommerce_Custom_Orders_Table_Install', 'table_version' );
		$property->setAccessible( true );
		$version = $property->getValue();
		$property->setValue( $version + 1 );

		// Run the activation script again.
		WooCommerce_Custom_Orders_Table_Install::activate();

		$this->assertSame(
			$version + 1,
			get_option( WooCommerce_Custom_Orders_Table_Install::SCHEMA_VERSION_KEY ),
			'The schema version should have been incremented.'
		);
	}

	/**
	 * @test
	 */
	public function the_current_schema_version_option_should_not_be_autoloaded() {
		global $wpdb;

		WooCommerce_Custom_Orders_Table_Install::activate();

		$this->assertEquals(
			'no',
			$wpdb->get_var( $wpdb->prepare(
				"SELECT autoload FROM $wpdb->options WHERE option_name = %s LIMIT 1",
				WooCommerce_Custom_Orders_Table_Install::SCHEMA_VERSION_KEY
			) ),
			'The schema version should not be autoloaded.'
		);
	}

	/**
	 * @test
	 * @group Columns
	 * @group Indexes
	 * @group Orders
	 */
	public function it_should_generate_indexes_for_the_orders_table() {
		WooCommerce_Custom_Orders_Table_Install::activate();

		$table = WC_Order_Data_Store_Custom_Table::get_custom_table_name();

		$this->assert_table_has_index( $table, 'order_id', 'PRIMARY', true );
		$this->assert_table_has_index( $table, 'order_key', 'order_key', true );
		$this->assert_table_has_index( $table, 'customer_id', 'customer_id', false );
		$this->assert_table_has_index( $table, 'total', 'order_total', false );
	}

	/**
	 * @test
	 * @group Columns
	 * @group Indexes
	 * @group Refunds
	 */
	public function it_should_generate_indexes_for_the_refunds_table() {
		WooCommerce_Custom_Orders_Table_Install::activate();

		$table = WC_Order_Refund_Data_Store_Custom_Table::get_custom_table_name();

		$this->assert_table_has_index( $table, 'refund_id', 'PRIMARY', true );
		$this->assert_table_has_index( $table, 'total', 'order_total', false );
	}

	/**
	 * @test
	 * @testWith ["billing_country", 2]
	 *           ["shipping_country", 2]
	 *           ["currency", 3]
	 * @group Columns
	 * @group Orders
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/48
	 */
	public function verify_char_column_length( $column, $length ) {
		$this->assert_column_has_type(
			WC_Order_Data_Store_Custom_Table::get_custom_table_name(),
			$column,
			sprintf( 'char(%d)', $length ),
			sprintf( 'Column "%s" did not match the expected CHAR length of %d.', $column, $length )
		);
	}

	/**
	 * @test
	 * @testWith ["order_key", 100]
	 *           ["billing_index", 255]
	 *           ["billing_first_name", 100]
	 *           ["billing_last_name", 100]
	 *           ["billing_company", 100]
	 *           ["billing_address_1", 255]
	 *           ["billing_address_2", 200]
	 *           ["billing_city", 100]
	 *           ["billing_state", 100]
	 *           ["billing_postcode", 20]
	 *           ["billing_email", 200]
	 *           ["billing_phone", 200]
	 *           ["shipping_index", 255]
	 *           ["shipping_first_name", 100]
	 *           ["shipping_last_name", 100]
	 *           ["shipping_company", 100]
	 *           ["shipping_address_1", 255]
	 *           ["shipping_address_2", 200]
	 *           ["shipping_city", 100]
	 *           ["shipping_state", 100]
	 *           ["shipping_postcode", 20]
	 *           ["payment_method", 100]
	 *           ["payment_method_title", 100]
	 *           ["discount_total", 100]
	 *           ["discount_tax", 100]
	 *           ["shipping_total", 100]
	 *           ["shipping_tax", 100]
	 *           ["cart_tax", 100]
	 *           ["total", 100]
	 *           ["version", 16]
	 *           ["prices_include_tax", 3]
	 *           ["transaction_id", 200]
	 *           ["customer_ip_address", 40]
	 *           ["created_via", 200]
	 *           ["date_completed", 20]
	 *           ["date_paid", 20]
	 *           ["cart_hash", 32]
	 * @group Columns
	 * @group Orders
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/48
	 */
	public function verify_char_column_length_for_orders_table( $column, $length ) {
		$this->assert_column_has_type(
			WC_Order_Data_Store_Custom_Table::get_custom_table_name(),
			$column,
			sprintf( 'varchar(%d)', $length ),
			sprintf( 'Column "%s" did not match the expected VARCHAR length of %d.', $column, $length )
		);
	}

	/**
	 * @test
	 * @testWith ["discount_total", 100]
	 *           ["discount_tax", 100]
	 *           ["shipping_total", 100]
	 *           ["shipping_tax", 100]
	 *           ["cart_tax", 100]
	 *           ["total", 100]
	 *           ["version", 16]
	 *           ["prices_include_tax", 3]
	 *           ["amount", 100]
	 * @group Columns
	 * @group Refunds
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/48
	 */
	public function verify_char_column_length_for_refunds_table( $column, $length ) {
		$this->assert_column_has_type(
			WC_Order_Refund_Data_Store_Custom_Table::get_custom_table_name(),
			$column,
			sprintf( 'varchar(%d)', $length ),
			sprintf( 'Column "%s" did not match the expected VARCHAR length of %d.', $column, $length )
		);
	}

	/**
	 * @test
	 * @testdox The User-Agent property should be stored in a text column
	 * @group Columns
	 * @group Orders
	 * @ticket https://github.com/liquidweb/woocommerce-custom-orders-table/issues/89
	 */
	public function the_user_agent_property_should_be_stored_in_a_text_column() {
		$this->assert_column_has_type(
			WC_Order_Data_Store_Custom_Table::get_custom_table_name(),
			'customer_user_agent',
			'text'
		);
	}

	/**
	 * Assert that $table has the given index.
	 *
	 * @param string $table  The table name.
	 * @param string $column The column name.
	 * @param string $key    The key name.
	 * @param bool   $unique Whether or not the column should be unique. Default is false.
	 */
	protected function assert_table_has_index( $table, $column, $key_name, $unique = false ) {
		global $wpdb;

		$indexes = $wpdb->get_results( "SHOW INDEX FROM ${table}", ARRAY_A );

		foreach ( $indexes as $index ) {
			if ( $index['Key_name'] !== $key_name ) {
				continue;
			}

			$this->assertSame(
				! $unique,
				(bool) $index['Non_unique'],
				sprintf(
					'Did not match expected non-uniqueness (Received %d, expected %d)',
					(int) $unique,
					$index['Non_unique']
				)
			);

			$this->assertEquals(
				$column,
				$index['Column_name'],
				sprintf( 'Expected index "%s" on column %s.', $key_name, $column )
			);

			// We've checked the index we've come to check, return early.
			return;
		}

		$this->fail( sprintf( 'Could not find an index with name "%s".', $key_name ) );
	}

	/**
	 * Assert the type of a given column matches expectations.
	 *
	 * @global $wpdb
	 *
	 * @param string $table    The table to query.
	 * @param string $column   The column name to find.
	 * @param string $expected The expected column type, e.g. "varchar(255)".
	 * @param string $message  Optional. An error message to display if the assertion fails.
	 */
	protected function assert_column_has_type( $table, $column, $expected, $message = '' ) {
		global $wpdb;

		$this->assertSame(
			$expected,
			$wpdb->get_row( $wpdb->prepare(
				'SHOW COLUMNS FROM ' . esc_sql( $table ) . ' WHERE Field = %s',
				$column
			) )->Type,
			$message
		);
	}
}
