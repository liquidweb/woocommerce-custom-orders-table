<?php
/**
 * Tests for the plugin bootstrapping.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

class BootstrapTest extends TestCase {

	public function test_plugin_only_loads_after_woocommerce() {
		global $wc_custom_order_table;

		// Test bootstrapping may have initialized it for us.
		$wc_custom_order_table = null;

		do_action( 'woocommerce_init' );

		$this->assertInstanceOf(
			'WC_Custom_Order_Table',
			$wc_custom_order_table,
			'The plugin should not be bootstrapped until woocommerce_init.'
		);
	}
}
