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

		$this->assertSame( 10, has_action( 'woocommerce_init', 'wc_custom_order_table' ) );

		do_action( 'woocommerce_init' );

		$this->assertInstanceOf(
			'WooCommerce_Custom_Orders_Table',
			$wc_custom_order_table,
			'The plugin should not be bootstrapped until woocommerce_init.'
		);
	}

	/**
	 * @testWith ["WC_CUSTOM_ORDER_TABLE_URL"]
	 *           ["WC_CUSTOM_ORDER_TABLE_PATH"]
	 */
	public function test_constants_are_defined( $constant ) {
		$this->assertTrue( defined( $constant ) );
	}

	public function test_autoloader_registered() {
		$this->assertContains( 'wc_custom_order_table_autoload', spl_autoload_functions() );
	}
}
