<?php
/**
 * Migration utility.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

namespace LiquidWeb\WooCommerceCustomOrdersTable\Util;

use LiquidWeb\WooCommerceCustomOrdersTable\Exceptions\MigrationException;
use LiquidWeb\WooCommerceCustomOrdersTable\Exceptions\MigrationMappingException;
use WC_Order_Data_Store_Custom_Table;

/**
 * Migration utility for moving data between custom tables and post meta.
 */
class Migration {

	/**
	 * Table columns mapped to post meta keys.
	 *
	 * @var string[]
	 */
	private $mappings;

	/**
	 * The data store's table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->mappings = WC_Order_Data_Store_Custom_Table::map_columns_to_post_meta_keys();
		$this->table    = WC_Order_Data_Store_Custom_Table::get_custom_table_name();
	}

	/**
	 * Extract known post meta keys and move them into the custom table for the order type.
	 *
	 * @throws \LiquidWeb\WooCommerceCustomOrdersTable\Exceptions\MigrationException If the row
	 *         could not be inserted into the database table.
	 *
	 * @global $wpdb
	 *
	 * @param int  $post_id The order/refund's post ID.
	 * @param bool $delete  Optional. Should the migrated post meta keys be deleted automatically
	 *                      upon successful migration? Default is false.
	 *
	 * @return bool True if all known meta keys were migrated, false otherwise.
	 */
	public function migrate_to_custom_table( $post_id, $delete = false ) {
		global $wpdb;

		$meta = get_post_meta( $post_id );
		$row  = [];

		foreach ( $this->mappings as $column_name => $meta_key ) {
			if ( isset( $meta[ $meta_key ][0] ) ) {
				$row[ $column_name ] = $meta[ $meta_key ][0];
			}
		}

		// Add the post ID to the primary key column.
		$row['order_id'] = $post_id;

		// Finally, insert the row into the table.
		if ( ! $wpdb->insert( $this->table, $row ) ) {
			throw new MigrationException(
				sprintf(
					/* Translators: %1$d is the post ID, %2$s is the WPDB error message. */
					__( 'Unable to insert row for post ID #%1$d: %2$s.', 'woocommerce-custom-orders-table' ),
					$post_id,
					$wpdb->last_error
				)
			);
		}

		// Optionally, clean up the post meta keys.
		if ( $delete ) {
			$this->delete_post_meta_keys( $post_id );
		}

		return true;
	}

	/**
	 * Restore the given order to the default WooCommerce behavior of storing order details in
	 * post meta.
	 *
	 * @throws \LiquidWeb\WooCommerceCustomOrdersTable\Exceptions\MigrationMappingException If a column
	 *         does not map to a valid post meta key.
	 * @throws \LiquidWeb\WooCommerceCustomOrdersTable\Exceptions\MigrationException        If not all
	 *         columns are moved into post meta.
	 *
	 * @global $wpdb
	 *
	 * @param int  $post_id The order/refund ID.
	 * @param bool $delete  Optional. Should the migrated table row be deleted automatically
	 *                      upon successful migration? Default is false.
	 *
	 * @return bool True if all post meta values were restored, false otherwise.
	 */
	public function restore_to_post_meta( $post_id, $delete = false ) {
		global $wpdb;

		$row = (array) $wpdb->get_row(
			$wpdb->prepare(
				'
				SELECT * FROM ' . esc_sql( $this->table ) . '
				WHERE order_id = %d LIMIT 1
				',
				$post_id
			),
			ARRAY_A
		);

		// Don't worry about the primary key, that doesn't need to end up in meta.
		unset( $row['order_id'] );

		// Store the columns in post meta.
		foreach ( $row as $column => $value ) {

			// If we don't have a mapping, ensure we're not leaving a value in the ether.
			if ( ! isset( $this->mappings[ $column ] ) ) {
				if ( empty( $value ) ) {
					unset( $row[ $column ] );
					continue;
				} else {
					throw new MigrationMappingException(
						sprintf(
							/* Translators: %1$s is the table name, %2$s is the column. */
							__( 'No post meta key has been mapped to the `%1$s`.`%2$s`', 'woocommerce-custom-orders-table' ),
							$this->table,
							$column
						)
					);
				}
			}

			// Once the post meta row has been written, remove the column from $row.
			if ( update_post_meta( $post_id, $this->mappings[ $column ], $value ) ) {
				unset( $row[ $column ] );
			}
		}

		// Verify that we've moved everything.
		if ( ! empty( $row ) ) {
			throw new MigrationException(
				sprintf(
					/* Translators: %1$d is the post ID, %2$s is the list of un-migrated columns. */
					__( 'Not all columns were moved to post meta for post ID #%1$d: %2$s.', 'woocommerce-custom-orders-table' ),
					$post_id,
					implode( ', ', array_keys( $row ) )
				)
			);
		}

		if ( $delete ) {
			$this->delete_custom_table_row( $post_id );
		}

		return true;
	}

	/**
	 * Delete the known post meta keys for the given order/refund.
	 *
	 * @param int $post_id The order/refund ID.
	 *
	 * @return int The number of post meta keys deleted.
	 */
	public function delete_post_meta_keys( $post_id ) {
		$deleted = 0;

		foreach ( $this->mappings as $column_name => $meta_key ) {
			if ( delete_post_meta( $post_id, $meta_key ) ) {
				$deleted++;
			}
		}

		return $deleted;
	}

	/**
	 * Delete the given row from the data store's custom table.
	 *
	 * @global $wpdb
	 *
	 * @param int $post_id The order/refund ID.
	 *
	 * @return bool True if the row was deleted, false otherwise.
	 */
	public function delete_custom_table_row( $post_id ) {
		global $wpdb;

		return (bool) $wpdb->delete( $this->table, [
			'order_id' => $post_id,
		] );
	}
}
