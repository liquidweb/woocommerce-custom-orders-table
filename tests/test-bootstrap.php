<?php
/**
 * Tests for the plugin bootstrapping.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

class BootstrapTest extends TestCase {

	/**
	 * @test
	 *
	 * @global $wc_custom_order_table
	 */
	public function the_plugin_should_be_loaded_after_WooCommerce() {
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
	 * @test
	 */
	public function it_should_register_an_SPL_autoloader() {
		$this->assertContains( 'wc_custom_order_table_autoload', spl_autoload_functions() );
	}
}
