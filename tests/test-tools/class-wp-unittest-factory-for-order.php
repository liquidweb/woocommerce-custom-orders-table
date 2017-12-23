<?php

/**
 * Unit test factory for orders.
 *
 * Note: The below @method notations are defined solely for the benefit of IDEs,
 * as a way to indicate expected return values from the given factory methods.
 *
 * @method int create( $args = array(), $generation_definitions = null )
 * @method WP_Post create_and_get( $args = array(), $generation_definitions = null )
 * @method int[] create_many( $count, $args = array(), $generation_definitions = null )
 */
class WP_UnitTest_Factory_For_Order extends WP_UnitTest_Factory_For_Post {

	function __construct( $factory = null ) {
		parent::__construct( $factory );
		$this->default_generation_definitions = array(
			'post_status'   => 'wc-pending',
			'post_title'    => new WP_UnitTest_Generator_Sequence( 'Order %s' ),
			'post_password' => uniqid( 'wc_' ),
			'post_type'     => 'shop_order',
		);
	}
}
