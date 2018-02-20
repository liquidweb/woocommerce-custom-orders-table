<?php
/**
 * WooCommerce order refund data store.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

/**
 * Extend the WC_Order_Refund_Data_Store_CPT class, overloading methods that require database access in
 * order to use the new table.
 *
 * This operates in a way similar to WC_Order_Data_Store_Custom_Table, but is for *refunds*.
 */
class WC_Order_Refund_Data_Store_Custom_Table extends WC_Order_Refund_Data_Store_CPT {

	/**
	 * Set to true when creating so we know to insert meta data.
	 *
	 * @var boolean
	 */
	protected $creating = false;

	/**
	 * Create a new refund in the database.
	 *
	 * @param WC_Order_Refund $refund The refund object, passed by reference.
	 */
	public function create( &$refund ) {
		$this->creating = true;

		parent::create( $refund );
	}

	/**
	 * Read refund data.
	 *
	 * @param WC_Order_Refund $refund      The refund object, passed by reference.
	 * @param object          $post_object The post object.
	 */
	protected function read_order_data( &$refund, $post_object ) {
		global $wpdb;

		$data = $this->get_order_data_from_table( $refund );

		if ( ! empty( $data ) ) {
			$refund->set_props( $data );

		} else {
			// Automatically backfill refund data from meta, but allow for disabling.
			if ( apply_filters( 'wc_custom_order_table_automatic_migration', true ) ) {
				$this->populate_from_meta( $refund );
			}
		}
	}

	/**
	 * Retrieve a single refund from the database.
	 *
	 * @global $wpdb
	 *
	 * @param WC_Order_Refund $refund The refund object.
	 *
	 * @return object The refund row, as an associative array.
	 */
	public function get_order_data_from_table( $refund ) {
		global $wpdb;

		$data  = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . esc_sql( wc_custom_order_table()->get_table_name() ) . ' WHERE order_id = %d LIMIT 1',
			$refund->get_id()
		), ARRAY_A ); // WPCS: DB call OK.

		// If no matches were found, this record needs to be created.
		if ( null === $data ) {
			$this->creating = true;

			return array();
		}

		// Expand anything that might need assistance.
		$data['prices_include_tax'] = wc_string_to_bool( $data['prices_include_tax'] );

		return $data;
	}

	/**
	 * Helper method that updates all the post meta for a refund based on it's settings in the
	 * WC_Order_Refund class.
	 *
	 * @global $wpdb
	 *
	 * @param WC_Order $refund The refund to be updated.
	 */
	protected function update_post_meta( &$refund ) {
		global $wpdb;

		$table       = wc_custom_order_table()->get_table_name();
		$refund_data = array(
			'order_id'           => $refund->get_id( 'edit' ),
			'discount_total'     => $refund->get_discount_total( 'edit' ),
			'discount_tax'       => $refund->get_discount_tax( 'edit' ),
			'shipping_total'     => $refund->get_shipping_total( 'edit' ),
			'shipping_tax'       => $refund->get_shipping_tax( 'edit' ),
			'cart_tax'           => $refund->get_cart_tax( 'edit' ),
			'total'              => $refund->get_total( 'edit' ),
			'version'            => $refund->get_version( 'edit' ),
			'currency'           => $refund->get_currency( 'edit' ),
			'prices_include_tax' => wc_bool_to_string( $refund->get_prices_include_tax( 'edit' ) ),
			'amount'             => $refund->get_amount( 'edit' ),
			'reason'             => $refund->get_reason( 'edit' ),
			'refunded_by'        => $refund->get_refunded_by( 'edit' ),
		);

		// Insert or update the database record.
		if ( $this->creating ) {
			$inserted = $wpdb->insert( $table, $refund_data ); // WPCS: DB call OK.

			if ( 1 !== $inserted ) {
				return;
			}

			$this->creating = false;

		} else {
			$refund_data = array_intersect_key( $refund_data, $refund->get_changes() );

			// There's nothing to update.
			if ( empty( $refund_data ) ) {
				return;
			}

			$wpdb->update(
				wc_custom_order_table()->get_table_name(),
				$refund_data,
				array( 'order_id' => (int) $refund->get_id() )
			);
		}

		do_action( 'woocommerce_order_refund_object_updated_props', $refund, $refund_data );
	}
}
