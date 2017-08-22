<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CLI Tool for migrating order data to/from custom table.
 *
 * @version  1.0.0
 * @category Class
 */
class WC_Custom_Order_Table_CLI extends WP_CLI_Command
{

    private $count;

    /**
     * Count how many orders have yet to be migrated.
     *
     * ## EXAMPLES
     *
     *     wp wc-order-table count
     *
     */
    public function count() {
        global $wpdb;

        $order_table = wc_custom_order_table()->get_table_name();

        $order_count = $wpdb->get_var( $wpdb->prepare("
            SELECT COUNT(1)
            FROM {$wpdb->posts} p
            LEFT JOIN {$order_table} o ON p.ID = o.order_id
            WHERE p.post_type IN ('" . implode("','", wc_get_order_types('reports')) . "') AND o.order_id IS NULL
            ORDER BY p.post_date DESC
        ", 'shop_order') );

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
     */
    public function migrate($args, $assoc_args)
    {
        global $wpdb;

        $orders_batch = isset($assoc_args['batch']) ? absint($assoc_args['batch']) : 1000;
        $orders_page = isset($assoc_args['page']) ? absint($assoc_args['page']) : 1;

        $order_table = wc_custom_order_table()->get_table_name();

        $order_count = $this->count();

        $total_pages = ceil($order_count / $orders_batch);

        $progress = \WP_CLI\Utils\make_progress_bar('Order Data Migration', $order_count);

        $orders_sql = $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('" . implode("','", wc_get_order_types('reports')) . "') ORDER BY post_date DESC", 'shop_order');
        $batches_processed = 0;

        for ($page = $orders_page; $page <= $total_pages; $page++) {
            $offset = ($page * $orders_batch) - $orders_batch;
            $sql = $wpdb->prepare($orders_sql . ' LIMIT %d OFFSET %d', $orders_batch, max($offset, 0));
            $orders = $wpdb->get_col($sql);

            foreach ($orders as $order) {
                // Accessing the order via wc_get_order will automatically migrate the order to the custom table.
                wc_get_order($order);

                $progress->tick();
            }

            $batches_processed++;
        }

        $progress->finish();

        WP_CLI::log(sprintf(__('%d orders processed in %d batches.', 'wc-custom-order-table'), $order_count, $batches_processed));
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
     */
    public function backfill($args, $assoc_args)
    {
        global $wpdb;

        $orders_batch = isset($assoc_args['batch']) ? absint($assoc_args['batch']) : 1000;
        $orders_page = isset($assoc_args['page']) ? absint($assoc_args['page']) : 1;

        $order_table = wc_custom_order_table()->get_table_name();

        $order_count = $wpdb->get_var("SELECT COUNT(1) FROM {$order_table} o" );

        WP_CLI::log( sprintf( __( '%d orders to be backfilled.', 'wc-custom-order-table' ), $order_count ) );

        $total_pages = ceil($order_count / $orders_batch);

        $progress = \WP_CLI\Utils\make_progress_bar('Order Data Migration', $order_count);

        $orders_sql = "SELECT order_id FROM {$order_table} o";
        $batches_processed = 0;

        for ($page = $orders_page; $page <= $total_pages; $page++) {
            $offset = ($page * $orders_batch) - $orders_batch;
            $sql = $wpdb->prepare($orders_sql . ' LIMIT %d OFFSET %d', $orders_batch, max($offset, 0));
            $orders = $wpdb->get_col($sql);

            foreach ($orders as $order) {
                // Accessing the order via wc_get_order will automatically migrate the order to the custom table.
                $order = wc_get_order($order);
                $order->data_store->backfill_postmeta( $order );

                $progress->tick();
            }

            $batches_processed++;
        }

        $progress->finish();

        WP_CLI::log(sprintf(__('%d orders processed in %d batches.', 'wc-custom-order-table'), $order_count, $batches_processed));
    }
}