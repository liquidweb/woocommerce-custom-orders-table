<?php
/**
 * Tests for the plugin installation.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
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

		$wpdb->query( 'DROP TABLE IF EXISTS ' . esc_sql( wc_custom_order_table()->get_table_name() ) );
		delete_option( WooCommerce_Custom_Orders_Table_Install::SCHEMA_VERSION_KEY );
	}

	public function test_table_is_created_on_plugin_activation() {
		self::reactivate_plugin();

		$this->assertTrue(
			$this->orders_table_exists(),
			'Upon activation, the table should be created.'
		);
	}

	public function test_can_install_table() {
		WooCommerce_Custom_Orders_Table_Install::activate();

		$this->assertTrue(
			$this->orders_table_exists(),
			'Upon activation, the table should be created.'
		);
		$this->assertNotEmpty(
			get_option( WooCommerce_Custom_Orders_Table_Install::SCHEMA_VERSION_KEY ),
			'The schema version should be stored in the options table.'
		);
	}

	public function test_returns_early_if_already_on_latest_schema_version() {
		WooCommerce_Custom_Orders_Table_Install::activate();

		$this->assertFalse(
			WooCommerce_Custom_Orders_Table_Install::activate(),
			'The activate() method should return false if the schema versions match.'
		);
	}

	public function test_can_upgrade_table() {
		WooCommerce_Custom_Orders_Table_Install::activate();

		// Get the current schema version, then increment it.
		$property = new ReflectionProperty( 'WooCommerce_Custom_Orders_Table_Install', 'table_version' );
		$property->setAccessible( true );
		$version = $property->getValue();
		$property->setValue( $version + 1 );

		// Run the activation script again.
		WooCommerce_Custom_Orders_Table_Install::activate();

		$this->assertEquals(
			$version + 1,
			get_option( WooCommerce_Custom_Orders_Table_Install::SCHEMA_VERSION_KEY ),
			'The schema version should have been incremented.'
		);
	}

	public function test_current_schema_version_is_not_autoloaded() {
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
	 * Test that the generated database schema contains the expected indexes.
	 *
	 * @testWith [0, "PRIMARY", "order_id"]
	 *           [0, "order_key", "order_key"]
	 *           [1, "customer_id", "customer_id"]
	 *           [1, "order_total", "total"]
	 */
	public function test_database_indexes( $non_unique, $key_name, $column_name ) {
		global $wpdb;

		WooCommerce_Custom_Orders_Table_Install::activate();

		$table   = wc_custom_order_table()->get_table_name();
		$indexes = $wpdb->get_results( "SHOW INDEX FROM $table", ARRAY_A );
		$search  = array(
			'Non_unique'  => $non_unique,
			'Key_name'    => $key_name,
			'Column_name' => $column_name,
		);

		// Find the index by name.
		foreach ( $indexes as $index ) {
			if ( $index['Key_name'] !== $key_name ) {
				continue;
			}

			$this->assertEquals(
				$non_unique,
				$index['Non_unique'],
				sprintf(
					'Did not match expected non-uniqueness (Received %d, expected %d)',
					$non_unique,
					$index['Non_unique']
				)
			);

			$this->assertEquals(
				$column_name,
				$index['Column_name'],
				sprintf( 'Expected index "%s" on column %s.', $key_name, $column_name )
			);

			// We've checked the index we've come to check, return early.
			return;
		}

		$this->fail( sprintf( 'Could not find an index with name "%s".', $key_name ) );
	}

	/**
	 * Test the lengths of VARCHAR fields.
	 *
	 * @global $wpdb
	 *
	 * @testWith ["order_key", 100]
	 *           ["billing_index", 255]
	 *           ["billing_first_name", 100]
	 *           ["billing_last_name", 100]
	 *           ["billing_company", 100]
	 *           ["billing_address_1", 200]
	 *           ["billing_address_2", 200]
	 *           ["billing_city", 100]
	 *           ["billing_state", 100]
	 *           ["billing_postcode", 100]
	 *           ["billing_country", 100]
	 *           ["billing_email", 200]
	 *           ["billing_phone", 200]
	 *           ["shipping_index", 255]
	 *           ["shipping_first_name", 100]
	 *           ["shipping_last_name", 100]
	 *           ["shipping_company", 100]
	 *           ["shipping_address_1", 200]
	 *           ["shipping_address_2", 200]
	 *           ["shipping_city", 100]
	 *           ["shipping_state", 100]
	 *           ["shipping_postcode", 100]
	 *           ["shipping_country", 100]
	 *           ["payment_method", 100]
	 *           ["payment_method_title", 100]
	 *           ["discount_total", 100]
	 *           ["discount_tax", 100]
	 *           ["shipping_total", 100]
	 *           ["shipping_tax", 100]
	 *           ["cart_tax", 100]
	 *           ["total", 100]
	 *           ["version", 16]
	 *           ["currency", 3]
	 *           ["prices_include_tax", 3]
	 *           ["transaction_id", 200]
	 *           ["customer_ip_address", 40]
	 *           ["customer_user_agent", 200]
	 *           ["created_via", 200]
	 *           ["date_completed", 20]
	 *           ["date_paid", 20]
	 *           ["cart_hash", 32]
	 *           ["amount", 100]
	 *
	 * @link https://github.com/liquidweb/woocommerce-custom-orders-table/issues/48
	 */
	public function test_varchar_length( $column, $length ) {
		global $wpdb;

		$this->assertEquals(
			sprintf( 'varchar(%d)', $length ),
			$wpdb->get_row( $wpdb->prepare(
				'SHOW COLUMNS FROM ' . esc_sql( wc_custom_order_table()->get_table_name() ) . ' WHERE Field = %s',
				$column
			) )->Type,
			sprintf( 'Column "%s" did not match the expected VARCHAR length of %d.', $column, $length )
		);
	}

	/**
	 * Determine if the custom orders table exists.
	 *
	 * @global $wpdb
	 */
	protected function orders_table_exists() {
		global $wpdb;

		return (bool) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM information_schema.tables WHERE table_name = %s LIMIT 1',
			wc_custom_order_table()->get_table_name()
		) );
	}
}
