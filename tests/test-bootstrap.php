<?php
/**
 * Tests for the plugin bootstrapping.
 *
 * @package Woocommerce_Order_Tables
 * @author  Liquid Web
 */

class BootstrapTest extends WP_UnitTestCase {

	/**
	 * Tear down the plugin after each test run.
	 *
	 * @before
	 */
	public function tear_down_plugin() {
		global $wc_custom_order_table;

		// Destroy the global $wc_custom_order_table instance.
		unset( $wc_custom_order_table );
	}

	public function test_plugin_only_loads_after_woocommerce() {
		global $wc_custom_order_table;

		$this->assertNull(
			$wc_custom_order_table,
			'Before bootstrapping, $wc_custom_order_table should be empty.'
		);

		do_action( 'woocommerce_init' );

		$this->assertInstanceOf(
			'WC_Custom_Order_Table',
			$wc_custom_order_table,
			'The plugin should not be bootstrapped until woocommerce_init.'
		);
	}
}
