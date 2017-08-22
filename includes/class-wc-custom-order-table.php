<?php

class WC_Custom_Order_Table {

    protected $table_name = null;

    public function setup() {
        global $wpdb;

        $this->table_name = $wpdb->prefix . 'woocommerce_orders';

        add_filter( 'woocommerce_order_data_store', array( $this, 'order_data_store' ) );
        add_filter( 'posts_join', array( $this, 'wp_query_customer_query' ), 10, 2 );
    }

    public function get_table_name() {
        return apply_filters( 'wc_customer_order_table_name', $this->table_name );
    }

    /**
     * Init the order data store.
     *
     * @return string
     */
    public function order_data_store() {
        return 'WC_Order_Data_Store_Custom_Table';
    }

    /**
     * Filter WP_Query for wc_customer_query
     *
     * @return string
     */
    public function wp_query_customer_query( $join, $wp_query ) {
        global $wpdb;

        // If there is no wc_customer_query then no need to process anything
        if( ! isset( $wp_query->query_vars['wc_customer_query'] ) ) {
            return $join;
        }

        $customer_query = $this->generate_wc_customer_query( $wp_query->query_vars['wc_customer_query'] );


        $query_parts = array();

        if( ! empty( $customer_query['emails'] ) ) {
            $emails = '\'' . implode( '\', \'', array_unique( $customer_query['emails'] ) ) . '\'';
            $query_parts[] = "{$this->get_table_name()}.billing_email IN ( {$emails} )";
        }

        if( ! empty( $customer_query['users'] ) ) {
            $users  = implode( ',', array_unique( $customer_query['users'] ) );
            $query_parts[] = "{$this->get_table_name()}.customer_id IN ( {$users} )";
        }

        if( ! empty( $query_parts ) ) {
            $query = '( ' . implode( ') OR (', $query_parts ) . ' )';
            $join .= "
            JOIN {$this->get_table_name()} ON
            ( {$wpdb->posts}.ID = {$this->get_table_name()}.order_id )
            AND ( {$query} )";
        }

        return $join;
    }

    public function generate_wc_customer_query( $values ) {
        $customer_query['emails'] = array();
        $customer_query['users'] = array();

        foreach ( $values as $value ) {
            if ( is_array( $value ) ) {
                $query = $this->generate_wc_customer_query( $value );

                if( is_array( $query['emails'] ) ) {
                    $customer_query['emails'] = array_merge( $customer_query['emails'], $query['emails'] );
                }

                if( is_array( $query['users'] ) ) {
                    $customer_query['users'] = array_merge( $customer_query['users'], $query['users'] );
                }
            } elseif ( is_email( $value ) ) {
                $customer_query['emails'][] = sanitize_email( $value );
            } else {
                $customer_query['users'][] = strval( absint( $value ) );
            }
        }

        return $customer_query;
    }
}