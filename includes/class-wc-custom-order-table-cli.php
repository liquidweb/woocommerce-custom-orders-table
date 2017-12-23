<?php
/**
 * CLI Tool for migrating order data to/from custom table.
 *
 * @package WooCommerce_Custom_Order_Tables
 * @author  Liquid Web
 */

/**
 * Manages the contents of the WooCommerce order table.
 */
class WC_Custom_Order_Table_CLI extends WP_CLI_Command {

	/**
	 * Count how many orders have yet to be migrated.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wc-order-table count
	 *
	 * @global $wpdb
	 */
	public function count() {
		global $wpdb;

		$order_table = wc_custom_order_table()->get_table_name();
		$order_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(1)
			FROM {$wpdb->posts} p
			LEFT JOIN {$order_table} o ON p.ID = o.order_id
			WHERE p.post_type IN ('%s')
			AND o.order_id IS NULL
			ORDER BY p.post_date DESC",
			implode( ',', wc_get_order_types( 'reports' ) )
		) );

		WP_CLI::log( sprintf( __( '%d orders to be migrated.', 'wc-custom-order-table' ), $order_count ) );

		return $order_count;
	}

	/**
	 * Migrate order data to the custom order table.
	 *
	 * ## OPTIONS
	 *
	 * [--batch=<batch>]
	 * : The number of orders to process.
	 * ---
	 * default: 1000
	 * ---
	 *
	 * [--page=<page>]
	 * : The page to start from.
	 * ---
	 * default: 1
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp wc-order-table migrate --batch=100 --page=1
	 *
	 * @global $wpdb
	 *
	 * @param array $args       Positional arguments passed to the command.
	 * @param array $assoc_args Associative arguments (options) passed to the command.
	 */
	public function migrate( $args, $assoc_args ) {
		global $wpdb;

		$orders_batch      = isset( $assoc_args['batch'] ) ? absint( $assoc_args['batch'] ) : 1000;
		$orders_page       = isset( $assoc_args['page'] ) ? absint( $assoc_args['page'] ) : 1;
		$order_table       = wc_custom_order_table()->get_table_name();
		$order_count       = $this->count();
		$total_pages       = ceil( $order_count / $orders_batch );
		$progress          = \WP_CLI\Utils\make_progress_bar( 'Order Data Migration', $order_count );
		$batches_processed = 0;
		$orders_sql        = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_type IN ('%s')
			ORDER BY post_date DESC",
			implode( ',', wc_get_order_types( 'reports' ) )
		);

		// Work through each batch to migrate their orders.
		for ( $page = $orders_page; $page <= $total_pages; $page++ ) {
			$offset = ( $page * $orders_batch ) - $orders_batch;
			$orders = $wpdb->get_col( $wpdb->prepare(
				$orders_sql . ' LIMIT %d OFFSET %d',
				$orders_batch,
				max( $offset, 0 )
			) );

			foreach ( $orders as $order ) {
				// Accessing the order via wc_get_order will automatically migrate the order to the custom table.
				wc_get_order( $order );

				$progress->tick();
			}

			$batches_processed++;
		}

		$progress->finish();

		WP_CLI::log( sprintf(
			/* Translators: %1$d is the number of total orders, %2$d is the number of batches. */
			__( '%1$d orders processed in %2$d batches.', 'wc-custom-order-table' ),
			$order_count,
			$batches_processed
		) );
	}

	/**
	 * Backfill order meta data into postmeta.
	 *
	 * ## OPTIONS
	 *
	 * [--batch=<batch>]
	 * : The number of orders to process.
	 * ---
	 * default: 1000
	 * ---
	 *
	 * [--page=<page>]
	 * : The page to start from.
	 * ---
	 * default: 1
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp wc-order-table backfill --batch=100 --page=1
	 *
	 * @global $wpdb
	 *
	 * @param array $args       Positional arguments passed to the command.
	 * @param array $assoc_args Associative arguments (options) passed to the command.
	 */
	public function backfill( $args, $assoc_args ) {
		global $wpdb;

		$orders_batch      = isset( $assoc_args['batch'] ) ? absint( $assoc_args['batch'] ) : 1000;
		$orders_page       = isset( $assoc_args['page'] ) ? absint( $assoc_args['page'] ) : 1;
		$order_table       = wc_custom_order_table()->get_table_name();
		$order_count       = $wpdb->get_var( "SELECT COUNT(1) FROM {$order_table} o" );
		$total_pages       = ceil( $order_count / $orders_batch );
		$progress          = \WP_CLI\Utils\make_progress_bar( 'Order Data Migration', $order_count );
		$batches_processed = 0;

		WP_CLI::log( sprintf( __( '%d orders to be backfilled.', 'wc-custom-order-table' ), $order_count ) );

		for ( $page = $orders_page; $page <= $total_pages; $page++ ) {
			$offset = ( $page * $orders_batch ) - $orders_batch;
			$orders = $wpdb->get_col( $wpdb->prepare(
				"SELECT order_id FROM {$order_table} o LIMIT %d OFFSET %d",
				$orders_batch,
				max( $offset, 0 )
			) );

			foreach ( $orders as $order ) {
				// Accessing the order via wc_get_order will automatically migrate the order to the custom table.
				$order = wc_get_order( $order );
				$order->get_data_store()->backfill_postmeta( $order );

				$progress->tick();
			}

			$batches_processed++;
		}

		$progress->finish();

		WP_CLI::log( sprintf(
			/* Translators: %1$d is the number of total orders, %2$d is the number of batches. */
			__( '%1$d orders processed in %2$d batches.', 'wc-custom-order-table' ),
			$order_count,
			$batches_processed
		) );
	}
}
