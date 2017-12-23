<?php

/**
 * Unit test factory for products.
 *
 * Note: The below @method notations are defined solely for the benefit of IDEs,
 * as a way to indicate expected return values from the given factory methods.
 *
 * @method int create( $args = array(), $generation_definitions = null )
 * @method WP_Post create_and_get( $args = array(), $generation_definitions = null )
 * @method int[] create_many( $count, $args = array(), $generation_definitions = null )
 */
class WP_UnitTest_Factory_For_Product extends WP_UnitTest_Factory_For_Post {

	function __construct( $factory = null ) {
		parent::__construct( $factory );
		$this->default_generation_definitions = array(
			'post_status'  => 'publish',
			'post_title'   => new WP_UnitTest_Generator_Sequence( 'Product name %s' ),
			'post_content' => new WP_UnitTest_Generator_Sequence( 'Product description %s' ),
			'post_excerpt' => new WP_UnitTest_Generator_Sequence( 'Product short description %s' ),
			'post_type'    => 'post',
		);
	}
}
