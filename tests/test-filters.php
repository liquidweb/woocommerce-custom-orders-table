<?php
/**
 * Tests for the query filters.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

class FiltersTest extends TestCase {

	public function test_filter_database_queries() {
		$args = [
			'meta_query' => [
				'relation' => 'OR',
				'customer_emails' => [
					'key'     => '_billing_email',
					'value'   => [ 'test@example.com' ],
					'compare' => 'IN',
				],
				'customer_ids' => [
					'key'     => '_customer_user',
					'value'   => [ 2 ],
					'compare' => 'IN',
				],
			],
		];

		$this->assertEquals( [
			'meta_query' => $args['meta_query'],
			'_wc_has_meta_columns' => false,
			'wc_order_meta_query'  => [
				[
					'key'      => 'billing_email',
					'value'    => [ 'test@example.com' ],
					'compare'  => 'IN',
					'_old_key' => '_billing_email',
				],
				[
					'key'      => 'customer_id',
					'value'    => [ 2 ],
					'compare'  => 'IN',
					'_old_key' => '_customer_user',
				],
			],
		], WooCommerce_Custom_Orders_Table_Filters::filter_database_queries( $args, [] ) );
	}

	public function test_filter_database_queries_without_meta_queries() {
		$this->assertEquals( [
			'foo'                  => 'bar',
			'wc_order_meta_query'  => [],
			'_wc_has_meta_columns' => false,
		], WooCommerce_Custom_Orders_Table_Filters::filter_database_queries( [
			'foo' => 'bar',
		], [] ) );
	}

	/**
	 * To ensure the regular expressions are working as expected, grab some actual queries from
	 * WC_Admin_Report::get_order_report_data() and verify.
	 *
	 * @dataProvider filter_order_report_provider()
	 */
	public function test_filter_order_report_query( $original_query, $changed_clauses ) {
		$this->assertEquals(
			$this->normalize_query_array( array_merge( $original_query, $changed_clauses ) ),
			$this->normalize_query_array( WooCommerce_Custom_Orders_Table_Filters::filter_order_report_query( $original_query ) ),
			'Did not perform the expected changes to the order report query.'
		);
	}

	public function filter_order_report_provider() {
		$table = wc_custom_order_table()->get_table_name();

		return array(
			'Order report with post data' => array(
				array(
					'select' => 'SELECT meta__billing_first_name.meta_value as customer_name',
					'from'   => 'FROM wptests_posts AS posts',
					'join'   => 'INNER JOIN wptests_postmeta AS meta__billing_first_name ON ( posts.ID = meta__billing_first_name.post_id AND meta__billing_first_name.meta_key = \'_billing_first_name\' )',
					'where'  => 'WHERE posts.post_type IN ( \'shop_order\',\'shop_order_refund\' ) AND posts.post_status IN ( \'wc-completed\',\'wc-processing\',\'wc-on-hold\')'
				),
				array(
					'select' => 'SELECT order_meta.billing_first_name as customer_name',
					'join'   => "LEFT JOIN {$table} AS order_meta ON ( posts.ID = order_meta.order_id )",
				),
			),
			'Order report with parent meta' => array(
				array(
					'select' => 'SELECT parent_meta__order_total.meta_value as total_refund',
					'from'   => 'FROM wptests_posts AS posts',
					'join'   => 'INNER JOIN wptests_postmeta AS parent_meta__order_total ON (posts.post_parent = parent_meta__order_total.post_id) AND (parent_meta__order_total.meta_key = \'_order_total\')',
					'where'  => 'WHERE posts.post_type IN ( \'shop_order\',\'shop_order_refund\' ) AND posts.post_status IN ( \'wc-completed\',\'wc-processing\',\'wc-on-hold\')',
				),
				array(
					'select' => 'SELECT order_parent_meta.total as total_refund',
					'join'   => "LEFT JOIN {$table} AS order_parent_meta ON ( posts.post_parent = order_parent_meta.order_id )",
				),
			),
			'Complicated query from WC_Tests_Report_Sales_By_Date::test_get_report_data()' => array(
				array(
					'select'   => 'SELECT posts.id as refund_id,
									meta__refund_amount.meta_value as total_refund,
									posts.post_date as post_date,
									order_items.order_item_type as item_type,
									meta__order_total.meta_value as total_sales,
									meta__order_shipping.meta_value as total_shipping,
									meta__order_tax.meta_value as total_tax,
									meta__order_shipping_tax.meta_value as total_shipping_tax,
									SUM( order_item_meta__qty.meta_value) as order_item_count',
					'from'     => 'FROM wptests_posts AS posts',
					'join'     => 'INNER JOIN wptests_postmeta AS meta__refund_amount ON ( posts.ID = meta__refund_amount.post_id AND meta__refund_amount.meta_key = \'_refund_amount\' )
									LEFT JOIN wptests_woocommerce_order_items AS order_items ON (posts.ID = order_items.order_id)
									INNER JOIN wptests_postmeta AS meta__order_total ON ( posts.ID = meta__order_total.post_id AND meta__order_total.meta_key = \'_order_total\' )
									LEFT JOIN wptests_postmeta AS meta__order_shipping ON ( posts.ID = meta__order_shipping.post_id AND meta__order_shipping.meta_key = \'_order_shipping\' )
									LEFT JOIN wptests_postmeta AS meta__order_tax ON ( posts.ID = meta__order_tax.post_id AND meta__order_tax.meta_key = \'_order_tax\' )
									LEFT JOIN wptests_postmeta AS meta__order_shipping_tax ON ( posts.ID = meta__order_shipping_tax.post_id AND meta__order_shipping_tax.meta_key = \'_order_shipping_tax\' )
									LEFT JOIN wptests_woocommerce_order_itemmeta AS order_item_meta__qty ON (order_items.order_item_id = order_item_meta__qty.order_item_id)  AND (order_item_meta__qty.meta_key = \'_qty\')
									LEFT JOIN wptests_posts AS parent ON posts.post_parent = parent.ID',
					'where'    => 'WHERE posts.post_type IN ( \'shop_order\',\'shop_order_refund\' )
									AND parent.post_status IN ( \'wc-completed\',\'wc-processing\',\'wc-on-hold\',\' wc-refunded\')
									AND posts.post_date >= \'2018-01-01 00:00:00\'
									AND posts.post_date < \'2018-01-16 22:10:51\'',
					'group_by' => 'GROUP BY refund_id',
					'order_by' => 'ORDER BY post_date ASC',
				),
				array(
					'select'   => 'SELECT posts.id as refund_id,
									order_meta.amount as total_refund,
									posts.post_date as post_date,
									order_items.order_item_type as item_type,
									order_meta.total as total_sales,
									order_meta.shipping_total as total_shipping,
									order_meta.cart_tax as total_tax,
									order_meta.shipping_tax as total_shipping_tax,
									SUM( order_item_meta__qty.meta_value) as order_item_count',
					'join'     => 'LEFT JOIN wptests_woocommerce_order_items AS order_items ON (posts.ID = order_items.order_id)
									LEFT JOIN wptests_woocommerce_order_itemmeta AS order_item_meta__qty ON (order_items.order_item_id = order_item_meta__qty.order_item_id)  AND (order_item_meta__qty.meta_key = \'_qty\')
									LEFT JOIN wptests_posts AS parent ON posts.post_parent = parent.ID'
									. " LEFT JOIN {$table} AS order_meta ON ( posts.ID = order_meta.order_id )",
				),
			),
			'Refunded order details'        => array(
				array(
					'select'   => 'SELECT posts.id as refund_id,
									meta__refund_amount.meta_value as total_refund,
									posts.post_date as post_date,
									order_items.order_item_type as item_type,
									meta__order_total.meta_value as total_sales,
									meta__order_shipping.meta_value as total_shipping,
									meta__order_tax.meta_value as total_tax,
									meta__order_shipping_tax.meta_value as total_shipping_tax,
									SUM( order_item_meta__qty.meta_value) as order_item_count',
					'from'     => 'FROM wptests_posts AS posts',
					'join'     => 'INNER JOIN wptests_postmeta AS meta__refund_amount ON ( posts.ID = meta__refund_amount.post_id AND meta__refund_amount.meta_key = \'_refund_amount\' )
									LEFT JOIN wptests_woocommerce_order_items AS order_items ON (posts.ID = order_items.order_id)
									INNER JOIN wptests_postmeta AS meta__order_total ON ( posts.ID = meta__order_total.post_id AND meta__order_total.meta_key = \'_order_total\' )
									LEFT JOIN wptests_postmeta AS meta__order_shipping ON ( posts.ID = meta__order_shipping.post_id AND meta__order_shipping.meta_key = \'_order_shipping\' )
									LEFT JOIN wptests_postmeta AS meta__order_tax ON ( posts.ID = meta__order_tax.post_id AND meta__order_tax.meta_key = \'_order_tax\' )
									LEFT JOIN wptests_postmeta AS meta__order_shipping_tax ON ( posts.ID = meta__order_shipping_tax.post_id AND meta__order_shipping_tax.meta_key = \'_order_shipping_tax\' )
									LEFT JOIN wptests_woocommerce_order_itemmeta AS order_item_meta__qty ON (order_items.order_item_id = order_item_meta__qty.order_item_id)  AND (order_item_meta__qty.meta_key = \'_qty\')
									LEFT JOIN wptests_posts AS parent ON posts.post_parent = parent.ID',
					'where'    => 'WHERE posts.post_type IN ( \'shop_order\',\'shop_order_refund\' )
									AND parent.post_status IN ( \'wc-completed\',\'wc-processing\',\'wc-on-hold\')
									AND posts.post_date >= \'2018-03-01 00:00:00\'
									AND posts.post_date < \'2018-03-20 20:44:11\'',
					'group_by' => 'GROUP BY refund_id',
					'order_by' => 'ORDER BY post_date ASC',
				),
				array(
					'select'   => 'SELECT posts.id as refund_id,
									order_meta.amount as total_refund,
									posts.post_date as post_date,
									order_items.order_item_type as item_type,
									order_meta.total as total_sales,
									order_meta.shipping_total as total_shipping,
									order_meta.cart_tax as total_tax,
									order_meta.shipping_tax as total_shipping_tax,
									SUM( order_item_meta__qty.meta_value) as order_item_count',
					'join'     => 'LEFT JOIN wptests_woocommerce_order_items AS order_items ON (posts.ID = order_items.order_id)
									LEFT JOIN wptests_woocommerce_order_itemmeta AS order_item_meta__qty ON (order_items.order_item_id = order_item_meta__qty.order_item_id)  AND (order_item_meta__qty.meta_key = \'_qty\')
									LEFT JOIN wptests_posts AS parent ON posts.post_parent = parent.ID'
									. " LEFT JOIN {$table} AS order_meta ON ( posts.ID = order_meta.order_id )",
				),
			),
		);
	}

	public function test_rest_populate_address_indexes() {
		$order = $this->generate_order_and_empty_indexes();

		WooCommerce_Custom_Orders_Table_Filters::rest_populate_address_indexes( array(
			'id' => 'add_order_indexes',
		) );

		$order_row = $this->get_order_row( $order->get_id() );

		$this->assertNotEmpty( $order_row['billing_index'] );
		$this->assertNotEmpty( $order_row['shipping_index'] );
	}

	public function test_rest_populate_address_indexes_only_runs_for_add_order_indexes() {
		$order = $this->generate_order_and_empty_indexes();

		WooCommerce_Custom_Orders_Table_Filters::rest_populate_address_indexes( array(
			'id' => 'some_other_id',
		) );

		$order_row = $this->get_order_row( $order->get_id() );

		$this->assertEmpty( $order_row['billing_index'] );
		$this->assertEmpty( $order_row['shipping_index'] );
	}

	public function test_rest_populate_address_indexes_runs_on_woocommerce_rest_system_status_tool_executed() {
		$order = $this->generate_order_and_empty_indexes();

		/*
		 * Instead of calling the method directly, fire the action hook that runs after the default
		 * operation completes.
		 */
		do_action( 'woocommerce_rest_system_status_tool_executed', array(
			'id' => 'add_order_indexes',
		) );

		$order_row = $this->get_order_row( $order->get_id() );

		$this->assertNotEmpty( $order_row['billing_index'] );
		$this->assertNotEmpty( $order_row['shipping_index'] );
	}

	/**
	 * Given an array of SQL clauses, normalize them for the sake of easier comparison.
	 *
	 * @param array $query An array of SQL clauses, as you might receive from the
	 *                     WooCommerce_Custom_Orders_Table_Filters::filter_order_report_query method().
	 *
	 * @return array The $query array, with each row trimmed of excess whitespace.
	 */
	protected function normalize_query_array( $query ) {
		foreach ( (array) $query as $key => $clause ) {

			// Remove any empty lines.
			$clause = preg_replace( '/^\s+\n/m', '', $clause );

			// Strip excess leading tabs, which make comparisons difficult.
			$clause = preg_replace( '/^\t{2,}/m', "\t", $clause );

			// Trim leading or trailing whitespace.
			$clause = trim( $clause );

			$query[ $key ] = $clause;
		}

		return $query;
	}

	/**
	 * Helper method that generates an order, then empties the billing and shipping indexes.
	 *
	 * @global $wpdb
	 *
	 * @return WC_Order The generated order object.
	 */
	protected function generate_order_and_empty_indexes() {
		global $wpdb;

		$order = WC_Helper_Order::create_order();

		$wpdb->update( wc_custom_order_table()->get_table_name(), array(
			'billing_index'  => null,
			'shipping_index' => null,
		), array(
			'order_id' => $order->get_id(),
		) );

		return $order;
	}
}
