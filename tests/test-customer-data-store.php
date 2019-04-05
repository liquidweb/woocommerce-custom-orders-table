<?php
/**
 * Tests for the WC_Customer_Data_Store_Custom_Table class.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

class CustomerDataStoreTest extends TestCase {

	/**
	 * @testWith ["some-value", "wc-some-value"]
	 *           ["wc-some-value", "wc-some-value"]
	 */
	public function test_prefix_wc_status( $value, $expected ) {
		$this->assertSame(
			$expected,
			WC_Customer_Data_Store_Custom_Table::prefix_wc_status( $value )
		);
	}
}
