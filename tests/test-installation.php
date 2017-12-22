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

		$instance = new WC_Custom_Order_Table_Install();
		$instance->activate();

		$this->assertTrue(
			self::orders_table_exists(),
			'Upon activation, the table should be created.'
		);
		$this->assertNotEmpty(
			get_option( 'wc_orders_table_version' ),
			'The schema version should be stored in the options table.'
		);
	}

	public function test_can_upgrade_table() {
		$instance = new WC_Custom_Order_Table_Install();
		$instance->activate();

		// Get the current schema version, then increment it.
		$property = new ReflectionProperty( $instance, 'table_version' );
		$property->setAccessible( true );
		$version  = $property->getValue( $instance );
		$property->setValue( $instance, $version + 1 );

		// Run the activation script again.
		$instance->activate();

		$this->assertEquals(
			$version + 1,
			get_option( 'wc_orders_table_version' ),
			'The schema version should have been incremented.'
		);
	}
}
