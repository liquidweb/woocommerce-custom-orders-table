<?php
/**
 * Tests for the plugin installation.
 *
 * @package Woocommerce_Order_Tables
 * @author  Liquid Web
 */

class InstallationTest extends TestCase {

	public function setUp() {
		self::drop_orders_table();
	}

	public function test_table_is_created_on_plugin_activation() {
		$this->assertFalse(
			self::orders_table_exists(),
			'The wp_woocommerce_orders table should not exist at the beginning of this test.'
		);

		self::reactivate_plugin();

		$this->assertTrue(
			self::orders_table_exists(),
			'Upon activation, the table should be created.'
		);
	}

	public function test_table_is_only_installed_if_it_does_not_already_exist() {
		self::reactivate_plugin();

		$this->assertTrue(
			self::orders_table_exists(),
			'Upon activation, the table should be created.'
		);

		// Deactivate, then re-activate the plugin.
		self::reactivate_plugin();

		$this->assertTrue(
			self::orders_table_exists(),
			'The table should still exist, just as it was.'
		);
	}

	public function test_can_install_table() {
		$this->assertFalse(
			self::orders_table_exists(),
			'The wp_woocommerce_orders table should not exist at the beginning of this test.'
		);

		WC_Custom_Order_Table_Install::activate();

		$this->assertTrue(
			self::orders_table_exists(),
			'Upon activation, the table should be created.'
		);
		$this->assertNotEmpty(
			get_option( WC_Custom_Order_Table_Install::SCHEMA_VERSION_KEY ),
			'The schema version should be stored in the options table.'
		);
	}

	public function test_returns_early_if_already_on_latest_schema_version() {
		WC_Custom_Order_Table_Install::activate();

		$this->assertFalse(
			WC_Custom_Order_Table_Install::activate(),
			'The activate() method should return false if the schema versions match.'
		);
	}

	public function test_can_upgrade_table() {
		WC_Custom_Order_Table_Install::activate();

		// Get the current schema version, then increment it.
		$property = new ReflectionProperty( 'WC_Custom_Order_Table_Install', 'table_version' );
		$property->setAccessible( true );
		$version  = $property->getValue();
		$property->setValue( $version + 1 );

		// Run the activation script again.
		WC_Custom_Order_Table_Install::activate();

		$this->assertEquals(
			$version + 1,
			get_option( WC_Custom_Order_Table_Install::SCHEMA_VERSION_KEY ),
			'The schema version should have been incremented.'
		);
	}
}
