<?php

/**
 * Unit test factory for customers.
 *
 * Note: The below @method notations are defined solely for the benefit of IDEs,
 * as a way to indicate expected return values from the given factory methods.
 *
 * @method int create( $args = array(), $generation_definitions = null )
 * @method WP_User create_and_get( $args = array(), $generation_definitions = null )
 * @method int[] create_many( $count, $args = array(), $generation_definitions = null )
 */
class WP_UnitTest_Factory_For_Customer extends WP_UnitTest_Factory_For_User {

	function __construct( $factory = null ) {
		parent::__construct( $factory );
		$this->default_generation_definitions = array(
			'user_login' => new WP_UnitTest_Generator_Sequence( 'Customer %s' ),
			'user_pass'  => 'password',
			'user_email' => new WP_UnitTest_Generator_Sequence( 'customer_%s@example.org' ),
		);
	}

	function get_object_by_id( $user_id ) {
		return new WC_Customer( $user_id );
	}
}
