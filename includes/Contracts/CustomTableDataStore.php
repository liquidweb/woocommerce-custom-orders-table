<?php
/**
 * Defines the interface used by data stores that have their own custom tables.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

namespace LiquidWeb\WooCommerceCustomOrdersTable\Contracts;

interface CustomTableDataStore {

	/**
	 * Retrieve a mapping of database columns to default WooCommerce post-meta keys.
	 *
	 * @return array
	 */
	public static function map_columns_to_post_meta_keys();

	/**
	 * Retrieve the name of the custom table for this data store.
	 *
	 * @return string The custom table used by this data store.
	 */
	public static function get_custom_table_name();

	/**
	 * Retrieve the column name that serves as the primary key in the custom table.
	 *
	 * @return string The primary key column name.
	 */
	public static function get_custom_table_primary_key();
}
