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
}
