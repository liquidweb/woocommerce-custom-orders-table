<?php
/**
 * Core plugin functionality.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

/**
 * Core functionality for WooCommerce Custom Orders Table.
 */
class WooCommerce_Custom_Orders_Table_Meta {

	/**
	 * Implements add_meta_boxes_{$post_type}.
	 *
	 * @param WC_Post $order The post object of the order.
	 */
	public static function replace_order_metabox( $order ) {
		// Removes default post metabox.
		// See: register_and_do_post_meta_boxes() in wp-admin/includes/meta-boxes.php.
		remove_meta_box( 'postcustom', null, 'normal' );

		// Adds meta box for order meta fields.
		add_meta_box(
			'ordercustom',
			__( 'Custom Fields', 'woocommerce-custom-orders-table' ),
			__CLASS__ . '::order_meta_box',
			null,
			'normal',
			'core',
			array(
				'__back_compat_meta_box'             => ! (bool) get_user_meta( get_current_user_id(), 'enable_custom_fields', true ),
				'__block_editor_compatible_meta_box' => true,
			)
		);

	}

	/**
	 * This method fakes post_custom_meta_box() for orders.
	 *
	 * @param WC_Post $post The post object being edited.
	 */
	public static function order_meta_box( $post ) {
		echo '<div id="postcustomstuff"> <div id="ajax-response"></div>';
		$metadata = WC_Order_Data_Store_Custom_Table::order_has_meta( $post->ID );
		foreach ( $metadata as $key => $value ) {
			if ( is_protected_meta( $metadata[ $key ]['meta_key'], 'post' ) || ! current_user_can( 'edit_post_meta', $post->ID, $metadata[ $key ]['meta_key'] ) || is_serialized( $metadata[ $key ]['meta_value'] ) ) {
				unset( $metadata[ $key ] );
			}
		}
		list_meta( $metadata );

		// Only look for keys if no ones had been defined.
		if ( ! has_filter( 'postmeta_form_keys' ) ) {
			// Ensure available keys in order form are only order meta keys.
			add_filter( 'postmeta_form_keys', __CLASS__ . '::get_all_order_meta_keys' );
		}

		meta_form( $post );
		echo ' </div>';
	}

	/**
	 * Helper method to retrieve from DB all available order meta keys.
	 *
	 * Note: is recommended to prevent this execution by defining a fixed list of keys with the filter postmeta_form_keys.
	 */
	public static function get_all_order_meta_keys() {
		$limit = apply_filters( 'postmeta_form_limit', 30 );
		$query = "SELECT DISTINCT meta_key
			FROM $wpdb->ordermeta
			WHERE meta_key NOT BETWEEN '_' AND '_z'
			HAVING meta_key NOT LIKE %s
			ORDER BY meta_key
			LIMIT %d";
		// In core code there is no cache of it, so no caching for now.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_col( $wpdb->prepare( $query, $wpdb->esc_like( '_' ) . '%', $limit ) ); // WPCS: unprepared SQL OK.
	}

	/**
	 * Manages both add new metadata and edit existing one to orders.
	 *
	 * Note: update_metadata supports both by default so no need to check value in $update.
	 *
	 * @param int     $post_id The ID of the post of the order.
	 * @param WC_Post $post The post object of the order.
	 * @param bool    $update Flag indicating if is updateing an existing order or creating a new one.
	 */
	public static function save_order_meta_data( $post_id, $post, $update ) {
		// Nonce verification happens in the caller.
		// phpcs:ignore WordPress.Security.NonceVerification
		$post_data = &$_POST;

		// Existing metadata in fields in the meta box.
		$post_id = (int) $post_id;

		// Add new field in the metabox.
		// These repeats add_meta from wp-admin/includes/posts. It should manage different post types.
		$metakeyselect = isset( $post_data['metakeyselect'] ) ? wp_unslash( trim( $post_data['metakeyselect'] ) ) : '';
		$metakeyinput  = isset( $post_data['metakeyinput'] ) ? wp_unslash( trim( $post_data['metakeyinput'] ) ) : '';
		$metavalue     = isset( $post_data['metavalue'] ) ? $post_data['metavalue'] : '';
		if ( is_string( $metavalue ) ) {
			$metavalue = trim( $metavalue );
		}

		if ( ( ( '#NONE#' !== $metakeyselect ) && ! empty( $metakeyselect ) ) || ! empty( $metakeyinput ) ) {
			/*
			 * We have a key/value pair. If both the select and the input
			 * for the key have data, the input takes precedence.
			 */
			if ( '#NONE#' !== $metakeyselect ) {
				$metakey = $metakeyselect;
			}

			if ( $metakeyinput ) {
				$metakey = $metakeyinput;
			}

			if ( current_user_can( 'add_post_meta', $post_id, $metakey ) ) {
				$metakey = wp_slash( $metakey );
				update_metadata( 'order', $post_id, $metakey, $metavalue );
				// WP core stores in also the value in `postmeta` table, we should remove it, at this point is duplicated from `woocommerce_ordermeta` table.
				delete_post_meta( $post_id, $metakey, $metavalue );
			}
		}

		// Add or update metadata fields.
		if ( isset( $post_data['meta'] ) && ! empty( $post_data['meta'] ) ) {
			foreach ( $post_data['meta'] as $mid => $meta ) {
				if ( ! current_user_can( 'edit_post_meta', $post_id, $meta['key'] ) ) {
					continue;
				}
				if ( empty( $meta['value'] ) ) {
					delete_metadata( 'order', $post_id, $meta['key'] );
					continue;
				}
				update_metadata( 'order', $post_id, $meta['key'], $meta['value'] );
			}
		}

		if ( ! empty( $post_data['meta_input'] ) ) {
			foreach ( $post_data['meta_input'] as $field => $value ) {
				update_metadata( 'order', $post_id, $field, $value );
			}
		}

		if ( isset( $post_data['deletemeta'] ) && $post_data['deletemeta'] ) {
			foreach ( $post_data['deletemeta'] as $mid => $value ) {
				$meta_object = get_metadata( 'order', $post_id, $meta['key'] );
				if (
					empty( $meta_object ) ||
					false === $meta_object ||
					$meta->post_id !== $post_id ||
					! current_user_can( 'delete_post_meta', $post_ID, $meta->meta_key )
					) {
						continue;
				}
				delete_metadata_by_mid( 'order', $mid );
			}
		}
	}

	/**
	 * Deletes meta records in order meta table when a order is deleted.
	 *
	 * See post.php:2852
	 *
	 * @param int $post_id The ID of the post of the order.
	 */
	public static function delete_order_meta_data( $post_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$order_meta_ids = $wpdb->get_col( $wpdb->prepare( "SELECT meta_id FROM $wpdb->ordermeta WHERE order_id = %d ", $post_id ) );
		foreach ( $order_meta_ids as $mid ) {
			delete_metadata_by_mid( 'order', $mid );
		}
	}

	/**
	 * This function hooks the pre-CRUD `{add, get, update, delete}_metadata`
	 * operations, trigged by 'update_post_meta' and similars.
	 *
	 * See: wp-includes/meta.php
	 *
	 * Important: This method performs an extra SELECT query to orders table. Should
	 * be deprecated/unused when all order metadata is accessed with the
	 * WC CRUD operations. We log all uses of it to facilitate the detection
	 * and fix up of wrong access methods.
	 */
	public static function map_post_metadata() {
		$args = func_get_args();

		// Only care here of the meta fields not in the core mapping.
		// The $args[2] is the meta_key.
		$postmeta_mapping = WooCommerce_Custom_Orders_Table::get_postmeta_mapping();
		if ( in_array( $args[2], $postmeta_mapping, true ) ) {
			return null;
		}

		// The $args[1] is $object_id.
		$is_order = wc_custom_order_table()->row_exists( $args[1] );
		if ( ! $is_order ) {
			// If the ID is not of an order let the default update continue.
			return null;
		}

		$action = str_replace( '_post', '', current_filter() );
		$args   = func_get_args();
		// Removes the $check argument.
		array_shift( $args );

		$logger = wc_get_logger();
		$logger->log(
			'warning',
			sprintf( 'Access to order meta detected using legacy %s with arguments %s. Please, use WooCommerce CRUD operations to access order metadata, see: https://github.com/woocommerce/woocommerce/wiki/Order-and-Order-Line-Item-Data.', $action, wp_json_encode( $args ) ),
			'order metadata'
		);

		// First argument '$check' is no need to pass it. Set order as meta type
		// for the `*_metadata()` operation.
		array_unshift( $args, 'order' );

		return call_user_func_array( $action, $args );
	}

}
