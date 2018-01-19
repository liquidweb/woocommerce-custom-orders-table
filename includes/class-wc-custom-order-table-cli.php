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
			_n( 'There is %d order to be migrated.', 'There are %d orders to be migrated.', $order_count, 'wc-custom-order-table' ),
			$order_count
		) );

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
	 * ## EXAMPLES
	 *
	 *     wp wc-order-table migrate --batch=100
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
			'batch' => 1000,
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
			array_merge( $order_types, array( $assoc_args['batch'] ) )
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
			_n( '%d order was migrated.', '%d orders were migrated.', $processed, 'wc-custom-order-table' ),
			$processed
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
	public function backfill( $args = array(), $assoc_args = array() ) {
		global $wpdb;

		$order_table = wc_custom_order_table()->get_table_name();
		$order_count = $wpdb->get_var( 'SELECT COUNT(order_id) FROM ' . esc_sql( $order_table ) ); // WPCS: DB call ok.

		if ( ! $order_count ) {
			return WP_CLI::warning( __( 'There are no orders to migrate, aborting.', 'wc-custom-order-table' ) );
		}

		$assoc_args  = wp_parse_args( $assoc_args, array(
			'batch' => 1000,
			'page'  => 1,
		) );
		$progress    = WP_CLI\Utils\make_progress_bar( 'Order Data Migration', $order_count );
		$processed   = 0;
		$starting    = ( $assoc_args['page'] - 1 ) * $assoc_args['batch'];
		$order_query = 'SELECT order_id FROM ' . esc_sql( $order_table ) . ' LIMIT %d, %d';
		$order_data  = $wpdb->get_col( $wpdb->prepare( $order_query, $starting, $assoc_args['batch'] ) ); // WPCS: Unprepared SQL ok, DB call ok.

		while ( ! empty( $order_data ) ) {
			foreach ( $order_data as $order_id ) {
				$order = wc_get_order( $order_id );
				$order->get_data_store()->backfill_postmeta( $order );

				$processed++;
				$progress->tick();
			}

			// Load up the next batch.
			$order_data = $wpdb->get_col( $wpdb->prepare( $order_query, $starting + $processed, $assoc_args['batch'] ) ); // WPCS: Unprepared SQL ok, DB call ok.
		}

		$progress->finish();

		// Issue a warning if no orders were migrated.
		if ( ! $processed ) {
			return WP_CLI::warning( __( 'No orders were migrated.', 'wc-custom-order-table' ) );
		}

		WP_CLI::success( sprintf(
			_n( '%d order was migrated.', '%d orders were migrated.', $processed, 'wc-custom-order-table' ),
			$processed
		) );
	}
}
