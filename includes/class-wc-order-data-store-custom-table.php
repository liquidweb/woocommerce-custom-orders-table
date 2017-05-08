<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC Order Data Store. Order is still a post, but data stored in new table.
 *
 * @version  1.0.0
 * @category Class
 */
class WC_Order_Data_Store_Custom_Table extends Abstract_WC_Order_Data_Store_CPT implements WC_Object_Data_Store_Interface, WC_Order_Data_Store_Interface {
	/**
	 * Set to true when creating so we know to insert meta data.
	 * @var boolean
	 */
	protected $creating = false;

    /**
     * Map table columns to related postmeta keys
     * @var array
     */
	protected $postmeta_mapping = array(
	    'order_key'            => '_order_key',
        'customer_id'          => '_customer_user',
        'payment_method'       => '_payment_method',
        'payment_method_title' => '_payment_method_title',
        'transaction_id'       => '_transaction_id',
        'customer_ip_address'  => '_customer_ip_address',
        'customer_user_agent'  => '_customer_user_agent',
        'created_via'          => '_created_via',
        'date_completed'       => '_date_completed',
        'date_paid'            => '_date_paid',
        'cart_hash'            => '_cart_hash',

        'billing_first_name'   => '_billing_first_name',
        'billing_last_name'    => '_billing_last_name',
        'billing_company'      => '_billing_company',
        'billing_address_1'    => '_billing_address_1',
        'billing_address_2'    => '_billing_address_2',
        'billing_city'         => '_billing_city',
        'billing_state'        => '_billing_state',
        'billing_postcode'     => '_billing_postcode',
        'billing_country'      => '_billing_country',

        'billing_email'        => '_billing_email',
        'billing_phone'        => '_billing_phone',

        'shipping_first_name'  => '_shipping_first_name',
        'shipping_last_name'   => '_shipping_last_name',
        'shipping_company'     => '_shipping_company',
        'shipping_address_1'   => '_shipping_address_1',
        'shipping_address_2'   => '_shipping_address_2',
        'shipping_city'        => '_shipping_city',
        'shipping_state'       => '_shipping_state',
        'shipping_postcode'    => '_shipping_postcode',
        'shipping_country'     => '_shipping_country',

        'discount_total'       => '_cart_discount',
        'discount_tax'         => '_cart_discount_tax',
        'shipping_total'       => '_order_shipping',
        'shipping_tax'         => '_order_shipping_tax',
        'cart_tax'             => '_order_tax',
        'total'                => '_order_total',

        'version'              => '_order_version',
        'currency'             => '_order_currency',
        'prices_include_tax'   => '_prices_include_tax'
    );

	public function get_postmeta_mapping() {
	    return $this->postmeta_mapping;
    }

    /**
     * Method to create a new order in the database.
     * @param WC_Order $order
     */
    public function create( &$order ) {
        $order->set_order_key( 'wc_' . apply_filters( 'woocommerce_generate_order_key', uniqid( 'order_' ) ) );
        $this->creating = true;
        parent::create( $order );
    }

    /**
     * Method to delete an order from the database.
     * @param WC_Order
     * @param array $args Array of args to pass to the delete method.
     */
    public function delete( &$order, $args = array() ) {
        global $wpdb;

        $order_id = $order->get_id();

        parent::delete( $order, $args );

        if( $args['force_delete'] || 0 == $order->get_id() ) {
            $wpdb->delete(
                "{$wpdb->prefix}woocommerce_orders",
                array(
                    'order_id' => $order_id
                )
            );
        }
    }

	/**
	 * Read order data. Can be overridden by child classes to load other props.
	 *
	 * @param WC_Order
	 * @param object $post_object
	 * @since 1.0.0
	 */
	protected function read_order_data( &$order, $post_object ) {
		global $wpdb;

		parent::read_order_data( $order, $post_object );

		$data = $this->get_order_data_from_table( $order );

		if ( ! empty( $data ) ) {
			$order->set_props( $data );
		} else {
		    // Automatically backfill order data from meta, but allow for disabling.
            if( apply_filters( 'wc_custom_order_table_automatic_migration', true ) ) {
                $this->populate_from_meta( $order );
            }
        }
	}

	public function get_order_data_from_table( $order ) {
	    global $wpdb;

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}woocommerce_orders WHERE order_id = %d;", $order->get_id() ) );
    }

	/**
	 * Helper method that updates all the post meta for an order based on it's settings in the WC_Order class.
	 *
	 * @param WC_Order
	 * @since 1.0.0
	 */
	protected function update_post_meta( &$order ) {
		global $wpdb;

		$edit_data = array(
			'order_key'            => $order->get_order_key( 'edit' ),
			'customer_id'          => $order->get_customer_id( 'edit' ),
            'payment_method'       => $order->get_payment_method( 'edit' ),
			'payment_method_title' => $order->get_payment_method_title( 'edit' ),
			'transaction_id'       => $order->get_transaction_id( 'edit' ),
			'customer_ip_address'  => $order->get_customer_ip_address( 'edit' ),
			'customer_user_agent'  => $order->get_customer_user_agent( 'edit' ),
			'created_via'          => $order->get_created_via( 'edit' ),
			'date_completed'       => $order->get_date_completed( 'edit' ),
			'date_paid'            => $order->get_date_paid( 'edit' ),
			'cart_hash'            => $order->get_cart_hash( 'edit' ),

            'billing_first_name'   => $order->get_billing_first_name( 'edit' ),
            'billing_last_name'    => $order->get_billing_last_name( 'edit' ),
            'billing_company'      => $order->get_billing_company( 'edit' ),
            'billing_address_1'    => $order->get_billing_address_1( 'edit' ),
            'billing_address_2'    => $order->get_billing_address_2( 'edit' ),
            'billing_city'         => $order->get_billing_city( 'edit' ),
            'billing_state'        => $order->get_billing_state( 'edit' ),
            'billing_postcode'     => $order->get_billing_postcode( 'edit' ),
            'billing_country'      => $order->get_billing_country( 'edit' ),

            'billing_email'        => $order->get_billing_email( 'edit' ),
            'billing_phone'        => $order->get_billing_phone( 'edit' ),

            'shipping_first_name'  => $order->get_shipping_first_name( 'edit' ),
            'shipping_last_name'   => $order->get_shipping_last_name( 'edit' ),
            'shipping_company'     => $order->get_shipping_company( 'edit' ),
            'shipping_address_1'   => $order->get_shipping_address_1( 'edit' ),
            'shipping_address_2'   => $order->get_shipping_address_2( 'edit' ),
            'shipping_city'        => $order->get_shipping_city( 'edit' ),
            'shipping_state'       => $order->get_shipping_state( 'edit' ),
            'shipping_postcode'    => $order->get_shipping_postcode( 'edit' ),
            'shipping_country'     => $order->get_shipping_country( 'edit' ),

            'discount_total'       => $order->get_discount_total( 'edit' ),
            'discount_tax'         => $order->get_discount_tax( 'edit' ),
            'shipping_total'       => $order->get_shipping_total( 'edit' ),
            'shipping_tax'         => $order->get_shipping_tax( 'edit'),
            'cart_tax'             => $order->get_total_tax( 'edit' ),
            'total'                => $order->get_total( 'edit' ),

            'version'              => $order->get_version( 'edit' ),
            'currency'             => $order->get_currency( 'edit' ),
            'prices_include_tax'   => $order->get_prices_include_tax( 'edit' )
		);

		if ( $this->creating ) {
			$wpdb->insert(
				"{$wpdb->prefix}woocommerce_orders",
				array_merge( array(
				    'order_id' => $order->get_id()
                ), $edit_data )
			);
		} else {
		    $changes = array_intersect_key( $edit_data, $order->get_changes() );

		    if( ! empty( $changes ) ) {
                $wpdb->update(
                    "{$wpdb->prefix}woocommerce_orders",
                    $changes,
                    array(
                        'order_id' => $order->get_id(),
                    )
                );
            }
		}

		$updated_props = array_keys( (array) $changes );

		// If customer changed, update any downloadable permissions.
		if ( in_array( 'customer_user', $updated_props ) || in_array( 'billing_email', $updated_props ) ) {
			$data_store = WC_Data_Store::load( 'customer-download' );
			$data_store->update_user_by_order_id( $order->get_id(), $order->get_customer_id(), $order->get_billing_email() );
		}

		do_action( 'woocommerce_order_object_updated_props', $order, $updated_props );
	}

	/**
	 * Excerpt for post.
	 *
	 * @param  WC_Order $order
	 * @return string
	 */
	protected function get_post_excerpt( $order ) {
		return $order->get_customer_note();
	}

	/**
	 * Get amount already refunded.
	 *
	 * @param  WC_Order
	 * @return string
	 */
	public function get_total_refunded( $order ) {
		global $wpdb;

		$total = $wpdb->get_var( $wpdb->prepare( "
			SELECT SUM( postmeta.meta_value )
			FROM $wpdb->postmeta AS postmeta
			INNER JOIN $wpdb->posts AS posts ON ( posts.post_type = 'shop_order_refund' AND posts.post_parent = %d )
			WHERE postmeta.meta_key = '_refund_amount'
			AND postmeta.post_id = posts.ID
		", $order->get_id() ) );

		return $total;
	}

	/**
	 * Get the total tax refunded.
	 *
	 * @param  WC_Order
	 * @return float
	 */
	public function get_total_tax_refunded( $order ) {
		global $wpdb;

		$total = $wpdb->get_var( $wpdb->prepare( "
			SELECT SUM( order_itemmeta.meta_value )
			FROM {$wpdb->prefix}woocommerce_order_itemmeta AS order_itemmeta
			INNER JOIN $wpdb->posts AS posts ON ( posts.post_type = 'shop_order_refund' AND posts.post_parent = %d )
			INNER JOIN {$wpdb->prefix}woocommerce_order_items AS order_items ON ( order_items.order_id = posts.ID AND order_items.order_item_type = 'tax' )
			WHERE order_itemmeta.order_item_id = order_items.order_item_id
			AND order_itemmeta.meta_key IN ('tax_amount', 'shipping_tax_amount')
		", $order->get_id() ) );

		return abs( $total );
	}

	/**
	 * Get the total shipping refunded.
	 *
	 * @param  WC_Order
	 * @return float
	 */
	public function get_total_shipping_refunded( $order ) {
		global $wpdb;

		$total = $wpdb->get_var( $wpdb->prepare( "
			SELECT SUM( order_itemmeta.meta_value )
			FROM {$wpdb->prefix}woocommerce_order_itemmeta AS order_itemmeta
			INNER JOIN $wpdb->posts AS posts ON ( posts.post_type = 'shop_order_refund' AND posts.post_parent = %d )
			INNER JOIN {$wpdb->prefix}woocommerce_order_items AS order_items ON ( order_items.order_id = posts.ID AND order_items.order_item_type = 'shipping' )
			WHERE order_itemmeta.order_item_id = order_items.order_item_id
			AND order_itemmeta.meta_key IN ('cost')
		", $order->get_id() ) );

		return abs( $total );
	}

	/**
	 * Finds an Order ID based on an order key.
	 *
	 * @param string $order_key An order key has generated by
	 * @return int The ID of an order, or 0 if the order could not be found
	 */
	public function get_order_id_by_order_key( $order_key ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}woocommerce_orders WHERE order_key = %s", $order_key ) );
	}

	/**
	 * Return count of orders with a specific status.
	 *
	 * @param  string $status
	 * @return int
	 */
	public function get_order_count( $status ) {
		global $wpdb;
		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( * ) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status = %s", $status ) ) );
	}

	/**
	 * Get all orders matching the passed in args.
	 *
	 * @see    wc_get_orders()
	 * @param  array $args
	 * @return object|array array of orders
	 */
	public function get_orders( $args = array() ) {
		/**
		 * Generate WP_Query args. This logic will change if orders are moved to
		 * custom tables in the future.
		 */
		$wp_query_args = array(
			'post_type'      => $args['type'] ? $args['type'] : 'shop_order',
			'post_status'    => $args['status'],
			'posts_per_page' => $args['limit'],
			'meta_query'     => array(),
			'fields'         => 'ids',
			'orderby'        => $args['orderby'],
			'order'          => $args['order'],
		);

        /**
         * wc_customer_query
         */
		$wc_customer_query = array();

        if ( ! empty( $args['customer'] ) ) {
            $values = is_array( $args['customer'] ) ? $args['customer'] : array( $args['customer'] );
            $wc_customer_query = array_merge( $wc_customer_query, $values );
        }

        if ( ! empty( $args['email'] ) ) {
            $values = is_array( $args['email'] ) ? $args['email'] : array( $args['email'] );
            $wc_customer_query = array_merge( $wc_customer_query, $values );
        }

        if( ! empty( $wc_customer_query ) ) {
            $wp_query_args['wc_customer_query'] = $wc_customer_query;
        }

        /**
         * Standard Args
         */
		if ( ! is_null( $args['parent'] ) ) {
			$wp_query_args['post_parent'] = absint( $args['parent'] );
		}

		if ( ! is_null( $args['offset'] ) ) {
			$wp_query_args['offset'] = absint( $args['offset'] );
		} else {
			$wp_query_args['paged'] = absint( $args['page'] );
		}

		if ( ! empty( $args['exclude'] ) ) {
			$wp_query_args['post__not_in'] = array_map( 'absint', $args['exclude'] );
		}

		if ( ! $args['paginate'] ) {
			$wp_query_args['no_found_rows'] = true;
		}

		if ( ! empty( $args['date_before'] ) ) {
			$wp_query_args['date_query']['before'] = $args['date_before'];
		}

		if ( ! empty( $args['date_after'] ) ) {
			$wp_query_args['date_query']['after'] = $args['date_after'];
		}

		// Get results.
		$orders = new WP_Query( apply_filters( 'woocommerce_order_data_store_cpt_get_orders_query', $wp_query_args, $args, $this ) );

		if ( 'objects' === $args['return'] ) {
			$return = array_map( 'wc_get_order', $orders->posts );
		} else {
			$return = $orders->posts;
		}

		if ( $args['paginate'] ) {
			return (object) array(
				'orders'        => $return,
				'total'         => $orders->found_posts,
				'max_num_pages' => $orders->max_num_pages,
			);
		} else {
			return $return;
		}
	}

	/**
	 * Generate meta query for wc_get_orders.
	 *
	 * @param  array $values
	 * @param  string $relation
	 * @return array
	 */
	private function get_orders_generate_customer_meta_query( $values, $relation = 'or' ) {
		$meta_query = array(
			'relation' => strtoupper( $relation ),
			'customer_emails' => array(
				'key'     => '_billing_email',
				'value'   => array(),
				'compare' => 'IN',
			),
			'customer_ids' => array(
				'key'     => '_customer_user',
				'value'   => array(),
				'compare' => 'IN',
			),
		);

		foreach ( $values as $value ) {
			if ( is_array( $value ) ) {
				$meta_query[] = $this->get_orders_generate_customer_meta_query( $value, 'and' );
			} elseif ( is_email( $value ) ) {
				$meta_query['customer_emails']['value'][] = sanitize_email( $value );
			} else {
				$meta_query['customer_ids']['value'][] = strval( absint( $value ) );
			}
		}

		if ( empty( $meta_query['customer_emails']['value'] ) ) {
			unset( $meta_query['customer_emails'] );
			unset( $meta_query['relation'] );
		}

		if ( empty( $meta_query['customer_ids']['value'] ) ) {
			unset( $meta_query['customer_ids'] );
			unset( $meta_query['relation'] );
		}

		return $meta_query;
	}

	/**
	 * Get unpaid orders after a certain date,
	 *
	 * @param  int timestamp $date
	 * @return array
	 */
	public function get_unpaid_orders( $date ) {
		global $wpdb;

		$unpaid_orders = $wpdb->get_col( $wpdb->prepare( "
			SELECT posts.ID
			FROM {$wpdb->posts} AS posts
			WHERE   posts.post_type   IN ('" . implode( "','", wc_get_order_types() ) . "')
			AND     posts.post_status = 'wc-pending'
			AND     posts.post_modified < %s
		", date( "Y-m-d H:i:s", absint( $date ) ) ) );

		return $unpaid_orders;
	}

	/**
	 * Search order data for a term and return ids.
	 *
	 * @param  string $term
	 * @return array of ids
	 */
	public function search_orders( $term ) {
		global $wpdb;

		$order_ids = array();

		if ( is_numeric( $term ) ) {
			$order_ids[] = absint( $term );
		}

		// @todo add order search capability

        /**
         * Searches on meta data can be slow - this lets you choose what fields to search.
         * 2.7.0 added _billing_address and _shipping_address meta which contains all address data to make this faster.
         * This however won't work on older orders unless updated, so search a few others (expand this using the filter if needed).
         * @var array
         */
        $meta_search_fields = array_map( 'wc_clean', apply_filters( 'woocommerce_shop_order_search_fields', array(
            // While we are searching the custom table, we will also search meta when filtered for backwards compatibility.
        ) ) );

        $postmeta_search = ! empty( $meta_search_fields ) ? $wpdb->get_col(
            $wpdb->prepare( "SELECT DISTINCT p1.post_id FROM {$wpdb->postmeta} p1 WHERE p1.meta_key IN ('" . implode( "','", array_map( 'esc_sql', $search_fields ) ) . "') AND p1.meta_value LIKE '%%%s%%';", wc_clean( $term ) )
        ) : array();

        return array_unique( array_merge(
            $order_ids,
            $postmeta_search,
            $wpdb->get_col(
                $wpdb->prepare( "
						SELECT order_id
						FROM {$wpdb->prefix}woocommerce_order_items as order_items
						WHERE order_item_name LIKE '%%%s%%'
						",
                    $term
                )
            )
        ) );
	}

	/**
	 * Gets information about whether permissions were generated yet.
	 *
	 * @param WC_Order|int $order
	 * @return bool
	 */
	public function get_download_permissions_granted( $order ) {
		$order_id = WC_Order_Factory::get_order_id( $order );
		return wc_string_to_bool( get_post_meta( $order_id, '_download_permissions_granted', true ) );
	}

	/**
	 * Stores information about whether permissions were generated yet.
	 *
	 * @param WC_Order|int $order
	 * @param bool $set
	 */
	public function set_download_permissions_granted( $order, $set ) {
		$order_id = WC_Order_Factory::get_order_id( $order );
		update_post_meta( $order_id, '_download_permissions_granted', wc_bool_to_string( $set ) );
	}

	/**
	 * Gets information about whether sales were recorded.
	 *
	 * @param WC_Order|int $order
	 * @return bool
	 */
	public function get_recorded_sales( $order ) {
		$order_id = WC_Order_Factory::get_order_id( $order );
		return wc_string_to_bool( get_post_meta( $order_id, '_recorded_sales', true ) );
	}

	/**
	 * Stores information about whether sales were recorded.
	 *
	 * @param WC_Order|int $order
	 * @param bool $set
	 */
	public function set_recorded_sales( $order, $set ) {
		$order_id = WC_Order_Factory::get_order_id( $order );
		update_post_meta( $order_id, '_recorded_sales', wc_bool_to_string( $set ) );
	}

	/**
	 * Gets information about whether coupon counts were updated.
	 *
	 * @param WC_Order|int $order
	 * @return bool
	 */
	public function get_recorded_coupon_usage_counts( $order ) {
		$order_id = WC_Order_Factory::get_order_id( $order );
		return wc_string_to_bool( get_post_meta( $order_id, '_recorded_coupon_usage_counts', true ) );
	}

	/**
	 * Stores information about whether coupon counts were updated.
	 *
	 * @param WC_Order|int $order
	 * @param bool $set
	 */
	public function set_recorded_coupon_usage_counts( $order, $set ) {
		$order_id = WC_Order_Factory::get_order_id( $order );
		update_post_meta( $order_id, '_recorded_coupon_usage_counts', wc_bool_to_string( $set ) );
	}

	/**
	 * Gets information about whether stock was reduced.
	 *
	 * @param WC_Order|int $order
	 * @return bool
	 */
	public function get_stock_reduced( $order ) {
		$order_id = WC_Order_Factory::get_order_id( $order );
		return wc_string_to_bool( get_post_meta( $order_id, '_order_stock_reduced', true ) );
	}

	/**
	 * Stores information about whether stock was reduced.
	 *
	 * @param WC_Order|int $order
	 * @param bool $set
	 */
	public function set_stock_reduced( $order, $set ) {
		$order_id = WC_Order_Factory::get_order_id( $order );
		update_post_meta( $order_id, '_order_stock_reduced', wc_bool_to_string( $set ) );
	}

	/**
	 * Get the order type based on Order ID.
	 *
	 * @since 1.0.0
	 * @param int $order_id
	 * @return string
	 */
	public function get_order_type( $order_id ) {
		return get_post_type( $order_id );
	}

    /**
     * Populate custom table with data from postmeta, for migrations.
     *
     * @since 1.0.0
     * @param bool $save
     * @param bool $delete
     * @return bool
     */
	public function populate_from_meta( &$order, $save = true, $delete = false ) {
	    $table_data = $this->get_order_data_from_table( $order );

	    if( is_null( $table_data ) ) {
	        $original_creating = $this->creating;
	        $this->creating = true;
        }

	    foreach( $this->get_postmeta_mapping() as $column => $meta_key ) {
	        $meta = get_post_meta( $order->get_id(), $meta_key, true );

	        if( empty( $table_data->$column ) && ! empty( $meta ) ) {
	            switch( $column ) {
                    case 'prices_include_tax':
                        $order->set_prices_include_tax( 'yes' === $meta );
                        break;
                    default:
                        $order->{"set_{$column}"}( $meta );
                }
            }
        }

        if( true === $save ) {
	        $this->update_post_meta( $order );
        }

        if( true === $delete ) {
            foreach( $this->get_postmeta_mapping() as $column => $meta_key ) {
                delete_post_meta( $order->get_id(), $meta_key );
            }
        }

        $this->creating = $original_creating;

	    return $order;
    }

    /**
     * Populate order postmeta from a custom table, for rolling back.
     *
     * @since 1.0.0
     * @return void
     */
    public function backfill_postmeta( &$order ) {
        $data = $this->get_order_data_from_table( $order );

        if( is_null( $data ) ) {
            return;
        }

        foreach( $this->get_postmeta_mapping() as $column => $meta_key ) {
            if( isset( $data->$column ) ) {
                update_post_meta( $order->get_id(), $meta_key, $data->$column );
            }
        }
    }
}
