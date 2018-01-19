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
	 * Count how many orders have yet to be migrated into the custom order table.
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
		$order_types = wc_get_order_types( 'reports' );
		$order_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*)
			FROM {$wpdb->posts} p
			LEFT JOIN {$order_table} o ON p.ID = o.order_id
			WHERE p.post_type IN (" . implode( ', ', array_fill( 0, count( $order_types ), '%s' ) ) . ')
			AND o.order_id IS NULL',
			$order_types
		) ); // WPCS: Unprepared SQL ok, DB call ok.

		WP_CLI::log( sprintf(
			/* Translators: %1$d is the number of orders to be migrated. */
			_n( 'There is %1$d order to be migrated.', 'There are %1$d orders to be migrated.', $order_count, 'wc-custom-order-table' ),
			$order_count
		) );

		return $order_count;
	}

	/**
	 * Migrate order data to the custom order table.
	 *
	 * ## OPTIONS
	 *
	 * [--batch-size=<batch-size>]
	 * : The number of orders to process in each batch.
	 * ---
	 * default: 1000
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp wc-order-table migrate --batch-size=100
	 *
	 * @global $wpdb
	 *
	 * @param array $args       Positional arguments passed to the command.
	 * @param array $assoc_args Associative arguments (options) passed to the command.
	 */
	public function migrate( $args = array(), $assoc_args = array() ) {
		global $wpdb;

		$order_count = $this->count();

		if ( ! $order_count ) {
			return WP_CLI::warning( __( 'There are no orders to migrate, aborting.', 'wc-custom-order-table' ) );
		}

		$assoc_args  = wp_parse_args( $assoc_args, array(
			'batch-size' => 1000,
		) );
		$order_table = wc_custom_order_table()->get_table_name();
		$order_types = wc_get_order_types( 'reports' );
		$progress    = WP_CLI\Utils\make_progress_bar( 'Order Data Migration', $order_count );
		$processed   = 0;
		$order_query = $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN " . esc_sql( $order_table ) . ' o ON p.ID = o.order_id
			WHERE p.post_type IN (' . implode( ', ', array_fill( 0, count( $order_types ), '%s' ) ) . ')
			AND o.order_id IS NULL
			ORDER BY p.post_date DESC
			LIMIT %d',
			array_merge( $order_types, array( $assoc_args['batch-size'] ) )
		);
		$order_data  = $wpdb->get_col( $order_query ); // WPCS: Unprepared SQL ok, DB call ok.

		while ( ! empty( $order_data ) ) {
			foreach ( $order_data as $order_id ) {
				$order = wc_get_order( $order_id );
				$order->get_data_store()->populate_from_meta( $order );

				$processed++;
				$progress->tick();
			}

			// Load up the next batch.
			$order_data = array_filter( $wpdb->get_col( $order_query ) ); // WPCS: Unprepared SQL ok, DB call ok.
		}

		$progress->finish();

		// Issue a warning if no orders were migrated.
		if ( ! $processed ) {
			return WP_CLI::warning( __( 'No orders were migrated.', 'wc-custom-order-table' ) );
		}

		WP_CLI::success( sprintf(
			/* Translators: %1$d is the number of migrated orders. */
			_n( '%1$d order was migrated.', '%1$d orders were migrated.', $processed, 'wc-custom-order-table' ),
			$processed
		) );
	}

	/**
	 * Copy order data into the postmeta table.
	 *
	 * Note that this could dramatically increase the size of your postmeta table, but is recommended
	 * if you wish to stop using the custom order table plugin.
	 *
	 * ## OPTIONS
	 *
	 * [--batch-size=<batch-size>]
	 * : The number of orders to process in each batch.
	 * ---
	 * default: 1000
	 * ---
	 *
	 * [--batch=<batch>]
	 * : The batch number to start from when migrating data.
	 * ---
	 * default: 1
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp wc-order-table backfill --batch-size=100 --batch=3
	 *
	 * @global $wpdb
	 *
	 * @param array $args       Positional arguments passed to the command.
	 * @param array $assoc_args Associative arguments (options) passed to the command.
	 */
	public function backfill( $args = array(), $assoc_args = array() ) {
		global $wpdb;

		$order_table = wc_custom_order_table()->get_table_name();
		$order_count = $wpdb->get_var( 'SELECT COUNT(order_id) FROM ' . esc_sql( $order_table ) ); // WPCS: DB call ok.

		if ( ! $order_count ) {
			return WP_CLI::warning( __( 'There are no orders to migrate, aborting.', 'wc-custom-order-table' ) );
		}

		$assoc_args  = wp_parse_args( $assoc_args, array(
			'batch-size' => 1000,
			'batch'      => 1,
		) );
		$progress    = WP_CLI\Utils\make_progress_bar( 'Order Data Migration', $order_count );
		$processed   = 0;
		$starting    = ( $assoc_args['batch'] - 1 ) * $assoc_args['batch-size'];
		$order_query = 'SELECT order_id FROM ' . esc_sql( $order_table ) . ' LIMIT %d, %d';
		$order_data  = $wpdb->get_col( $wpdb->prepare( $order_query, $starting, $assoc_args['batch-size'] ) ); // WPCS: Unprepared SQL ok, DB call ok.

		while ( ! empty( $order_data ) ) {
			foreach ( $order_data as $order_id ) {
				$order = wc_get_order( $order_id );
				$order->get_data_store()->backfill_postmeta( $order );

				$processed++;
				$progress->tick();
			}

			// Load up the next batch.
			$order_data = $wpdb->get_col( $wpdb->prepare( $order_query, $starting + $processed, $assoc_args['batch-size'] ) ); // WPCS: Unprepared SQL ok, DB call ok.
		}

		$progress->finish();

		// Issue a warning if no orders were migrated.
		if ( ! $processed ) {
			return WP_CLI::warning( __( 'No orders were migrated.', 'wc-custom-order-table' ) );
		}

		WP_CLI::success( sprintf(
			/* Translators: %1$d is the number of migrated orders. */
			_n( '%1$d order was migrated.', '%1$d orders were migrated.', $processed, 'wc-custom-order-table' ),
			$processed
		) );
	}
}
