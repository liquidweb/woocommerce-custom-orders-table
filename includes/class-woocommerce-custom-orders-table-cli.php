<?php
/**
 * CLI Tool for migrating order data to/from custom table.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

use WP_CLI\Iterators\Query as QueryIterator;

/**
 * Manages the contents of the WooCommerce orders table.
 */
class WooCommerce_Custom_Orders_Table_CLI extends WP_CLI_Command {

	/**
	 * Contains IDs of any orders that have been skipped during the migration.
	 *
	 * @var array
	 */
	protected $skipped_ids = array();

	/**
	 * Bootstrap the WP-CLI command.
	 */
	public function __construct() {
		add_action( 'woocommerce_caught_exception', 'self::handle_exceptions' );

		// Ensure the custom table has been installed.
		WooCommerce_Custom_Orders_Table_Install::activate();
	}

	/**
	 * Count how many orders have yet to be migrated into the custom orders table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wc-order-table count
	 *
	 * @global $wpdb
	 *
	 * @return int The number of orders to be migrated.
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
			_n( 'There is %1$d order to be migrated.', 'There are %1$d orders to be migrated.', $order_count, 'woocommerce-custom-orders-table' ),
			$order_count
		) );

		return (int) $order_count;
	}

	/**
	 * Migrate order data to the custom orders table.
	 *
	 * ## OPTIONS
	 *
	 * [--batch-size=<batch-size>]
	 * : The number of orders to process in each batch.
	 * ---
	 * default: 100
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

		// Abort if there are no orders to migrate.
		if ( ! $order_count ) {
			return WP_CLI::warning( __( 'There are no orders to migrate, aborting.', 'woocommerce-custom-orders-table' ) );
		}

		$assoc_args  = wp_parse_args( $assoc_args, array(
			'batch-size' => 100,
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
		$batch_count = 1;

		while ( ! empty( array_diff( $order_data, $this->skipped_ids ) ) ) {
			WP_CLI::debug( sprintf(
				/* Translators: %1$d is the batch number, %2$d is the batch size. */
				__( 'Beginning batch #%1$d (%2$d orders/batch).', 'woocommerce-custom-orders-table' ),
				$batch_count,
				$assoc_args['batch-size']
			) );

			// Iterate over each order in this batch.
			foreach ( $order_data as $order_id ) {
				try {
					$order = wc_get_order( $order_id );
				} catch ( Exception $e ) {
					$order = false;
					WP_CLI::warning( sprintf(
						/* Translators: %1$d is the order ID, %2$s is the exception message. */
						__( 'Encountered an error migrating order #%1$d: %2$s', 'woocommerce-custom-orders-table' ),
						$order_id,
						$e->getMessage()
					) );
				}

				// Either an error occurred or wc_get_order() could not find the order.
				if ( false === $order ) {
					$this->skipped_ids[] = $order_id;

					WP_CLI::warning( sprintf(
						/* Translators: %1$d is the order ID. */
						__( 'Unable to retrieve order with ID %1$d, skipping', 'woocommerce-custom-orders-table' ),
						$order_id
					) );

				} else {
					$result = $order->get_data_store()->populate_from_meta( $order );

					if ( is_wp_error( $result ) ) {
						$this->skipped_ids[] = $order_id;

						WP_CLI::warning( sprintf(
							/* Translators: %1$d is the order ID, %2$s is the error message. */
							__( 'A database error occurred while migrating order %1$d, skipping: %2$s.', 'woocommerce-custom-orders-table' ),
							$order_id,
							$result->get_error_message()
						) );
					} else {
						$processed++;

						WP_CLI::debug( sprintf(
							/* Translators: %1$d is the migrated order ID. */
							__( 'Order ID %1$d has been migrated.', 'woocommerce-custom-orders-table' ),
							$order_id
						) );
					}
				}

				$progress->tick();
			}

			// Load up the next batch.
			$next_batch = array_filter( $wpdb->get_col( $order_query ) ); // WPCS: Unprepared SQL ok, DB call ok.

			if ( $next_batch === $order_data ) {
				return WP_CLI::error( __( 'Infinite loop detected, aborting.', 'woocommerce-custom-orders-table' ) );
			} else {
				$order_data = $next_batch;
				$batch_count++;
			}
		}

		$progress->finish();

		// Issue a warning if no orders were migrated.
		if ( ! $processed ) {
			return WP_CLI::warning( __( 'No orders were migrated.', 'woocommerce-custom-orders-table' ) );
		}

		if ( empty( $this->skipped_ids ) ) {
			return WP_CLI::success( sprintf(
				/* Translators: %1$d is the number of migrated orders. */
				_n( '%1$d order was migrated.', '%1$d orders were migrated.', $processed, 'woocommerce-custom-orders-table' ),
				$processed
			) );
		} else {
			WP_CLI::warning( sprintf(
				/* Translators: %1$d is the number of orders migrated, %2$d is the number of skipped records. */
				_n( '%1$d order was migrated, with %2$d skipped.', '%1$d orders were migrated, with %2$d skipped.', $processed, 'woocommerce-custom-orders-table' ),
				$processed,
				count( $this->skipped_ids )
			) );
		}
	}

	/**
	 * Copy order data into the postmeta table.
	 *
	 * Note that this could dramatically increase the size of your postmeta table, but is recommended
	 * if you wish to stop using the custom orders table plugin.
	 *
	 * ## OPTIONS
	 *
	 * [--batch-size=<batch-size>]
	 * : The number of orders to process in each batch.
	 * ---
	 * default: 100
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

		$assoc_args  = wp_parse_args( $assoc_args, array(
			'batch-size' => 100,
		) );
		$order_table = wc_custom_order_table()->get_table_name();
		$order_count = $wpdb->get_var( 'SELECT COUNT(order_id) FROM ' . esc_sql( $order_table ) ); // WPCS: DB call ok.
		$order_query = new QueryIterator( 'SELECT order_id FROM ' . esc_sql( $order_table ), $assoc_args['batch-size'] );
		$progress    = WP_CLI\Utils\make_progress_bar( 'Order Data Migration', $order_count );
		$processed   = 0;

		while ( $order_query->valid() ) {
			$order = wc_get_order( $order_query->current()->order_id );

			if ( $order ) {
				$order->get_data_store()->backfill_postmeta( $order );
			}

			$processed++;
			$progress->tick();
			$order_query->next();
		}

		$progress->finish();

		// Issue a warning if no orders were migrated.
		if ( ! $processed ) {
			return WP_CLI::warning( __( 'No orders were migrated.', 'woocommerce-custom-orders-table' ) );
		}

		WP_CLI::success( sprintf(
			/* Translators: %1$d is the number of migrated orders. */
			_n( '%1$d order was migrated.', '%1$d orders were migrated.', $processed, 'woocommerce-custom-orders-table' ),
			$processed
		) );
	}

	/**
	 * Callback function for the "woocommerce_caught_exception" action.
	 *
	 * @throws Exception Re-throw the previously-caught Exception.
	 *
	 * @param Exception $exception The Exception object.
	 */
	public static function handle_exceptions( $exception ) {
		throw $exception;
	}
}
