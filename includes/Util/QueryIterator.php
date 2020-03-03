<?php
/**
 * Iterator for looping through a posts query.
 *
 * This is strongly based on \WP_CLI\Iterators\Query, but is bundled with the plugin since WP-CLI
 * may not always be available on WooCommerce sites.
 *
 * @link https://github.com/wp-cli/wp-cli/blob/master/php/WP_CLI/Iterators/Query.php
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

namespace LiquidWeb\WooCommerceCustomOrdersTable\Util;

use LiquidWeb\WooCommerceCustomOrdersTable\Exceptions\QueryException;

/**
 * Iterate through a database query results.
 */
class QueryIterator implements \Countable, \Iterator {

	/**
	 * The maximum number of rows to retrieve at a time.
	 *
	 * @var int
	 */
	private $chunk_size;

	/**
	 * The modified query, used for counting rows.
	 *
	 * @var string
	 */
	private $count_query = '';

	/**
	 * The global $wpdb instance.
	 *
	 * @var \WPDB
	 */
	private $db;

	/**
	 * Whether or not we've made it through all available data.
	 *
	 * @var bool
	 */
	private $depleted = false;

	/**
	 * Our current position against all available data.
	 *
	 * @var int
	 */
	private $global_index = 0;

	/**
	 * Our current position within the current dataset.
	 *
	 * @var int
	 */
	private $index_in_results = 0;

	/**
	 * The current offset to use when loading the next set of data.
	 *
	 * @var int
	 */
	private $offset = 0;

	/**
	 * The raw, user-provided SQL query.
	 *
	 * @var string
	 */
	private $query = '';

	/**
	 * The current set of data from the database.
	 *
	 * @var array
	 */
	private $results = [];

	/**
	 * How many total rows are we aware of?
	 *
	 * @var int
	 */
	private $row_count = 0;

	/**
	 * Creates a new query iterator.
	 *
	 * @param string $query      The query as a string. It shouldn't include any LIMIT clauses.
	 * @param int    $chunk_size Optional. How many rows to retrieve at once; default is 500.
	 */
	public function __construct( $query, $chunk_size = 500 ) {
		$this->query      = $query;
		$this->chunk_size = $chunk_size;
		$this->db         = $GLOBALS['wpdb'];

		// Swap the SELECT clause for SELECT COUNT(*).
		$this->count_query = preg_replace( '/^.*? FROM /', 'SELECT COUNT(*) FROM ', $query, 1, $replacements );

		// If we haven't found anything, assume we can't count.
		if ( 1 !== $replacements ) {
			$this->count_query = '';
		}
	}

	/**
	 * Count the total number of query results.
	 *
	 * @return int
	 */
	public function count() {
		$this->row_count = (int) $this->db->get_var( $this->count_query );

		return $this->row_count;
	}

	/**
	 * Get the current element.
	 *
	 * @return object An object representing the current row.
	 */
	public function current() {
		return $this->results[ $this->index_in_results ];
	}

	/**
	 * Get the current element's key.
	 *
	 * @return int
	 */
	public function key() {
		return $this->global_index;
	}

	/**
	 * Advance the iterator.
	 */
	public function next() {
		$this->index_in_results++;
		$this->global_index++;
	}

	/**
	 * Reset everything.
	 */
	public function rewind() {
		$this->results          = [];
		$this->global_index     = 0;
		$this->index_in_results = 0;
		$this->offset           = 0;
		$this->depleted         = false;
	}

	/**
	 * Check if the current position is valid.
	 *
	 * If no query data has yet been loaded (or we've already looped through it), the next set of
	 * results will be loaded from the database.
	 *
	 * @return bool
	 */
	public function valid() {
		if ( $this->depleted ) {
			return false;
		}

		// If we don't yet have the data, query the database.
		if ( ! isset( $this->results[ $this->index_in_results ] ) ) {
			$items_loaded = $this->load_items_from_db();

			// Nothing came back, so we've reached the end.
			if ( ! $items_loaded ) {
				$this->rewind();
				$this->depleted = true;
				return false;
			}

			$this->index_in_results = 0;
		}

		return true;
	}

	/**
	 * Reduces the offset when the query row count shrinks
	 *
	 * In cases where the iterated rows are being updated such that they will no
	 * longer be returned by the original query, the offset must be reduced to
	 * iterate over all remaining rows.
	 */
	private function adjust_offset_for_shrinking_result_set() {
		if ( empty( $this->count_query ) ) {
			return;
		}

		$row_count = $this->db->get_var( $this->count_query );

		// We have fewer rows than the last time we counted.
		if ( $row_count < $this->row_count ) {
			$this->offset -= $this->row_count - $row_count;
		}

		$this->row_count = $row_count;
	}

	/**
	 * Retrieve the next chunk of results from the database.
	 *
	 * @throws QueryException When a database error is encountered.
	 *
	 * @return bool True if results were loaded, false otherwise.
	 */
	private function load_items_from_db() {
		$this->adjust_offset_for_shrinking_result_set();

		$this->results = $this->db->get_results(
			$this->query . sprintf( ' LIMIT %d OFFSET %d', $this->chunk_size, $this->offset ),
			OBJECT
		);

		if ( ! $this->results ) {
			if ( $this->db->last_error ) {
				throw new QueryException( 'Database error: ' . $this->db->last_error );
			}

			return false;
		}

		$this->offset += $this->chunk_size;
		return true;
	}
}
