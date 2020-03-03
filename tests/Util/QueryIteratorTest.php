<?php
/**
 * Tests for the Migration utility.
 *
 * @package WooCommerce_Custom_Orders_Table
 * @author  Liquid Web
 */

namespace Tests\Util;

use LiquidWeb\WooCommerceCustomOrdersTable\Util\QueryIterator;
use LiquidWeb\WooCommerceCustomOrdersTable\Exceptions\QueryException;
use TestCase;

/**
 * @covers LiquidWeb\WooCommerceCustomOrdersTable\Util\QueryIterator
 * @group Util
 */
class QueryIteratorTest extends TestCase {

	/**
	 * @test
	 */
	public function it_should_iterate_through_results() {
		global $wpdb;

		$post_ids = $this->factory->post->create_many( 3 );
		$results  = [];

		$query = new QueryIterator( "SELECT * FROM {$wpdb->posts}" );

		while ( $query->valid() ) {
			$results[] = (int) $query->current()->ID;

			$query->next();
		}

		$this->assertSame( $post_ids, $results );
	}

	/**
	 * @test
	 */
	public function it_should_be_able_to_batch_results() {
		global $wpdb;

		$post_ids = $this->factory->post->create_many( 3 );
		$results  = [];
		$keys     = [];

		$query = new QueryIterator( "SELECT * FROM {$wpdb->posts}", 2 );

		while ( $query->valid() ) {
			$results[] = (int) $query->current()->ID;
			$keys[]    = $query->key();

			$query->next();
		}

		$this->assertSame( $post_ids, $results );
		$this->assertContains(
			'LIMIT 2 OFFSET 4',
			$wpdb->last_query,
			'The last query should have been empty, but included offsets for the previous two queries.'
		);
	}

	/**
	 * @test
	 */
	public function it_should_implement_the_Countable_interface() {
		global $wpdb;

		$this->factory->post->create_many( 3 );

		$query = new QueryIterator( "SELECT * FROM {$wpdb->posts}" );

		$this->assertSame( 3, $query->count() );
	}

	/**
	 * @test
	 * @depends it_should_implement_the_Countable_interface
	 */
	public function the_count_should_be_unaffected_by_batching() {
		global $wpdb;

		$this->factory->post->create_many( 3 );

		$query = new QueryIterator( "SELECT * FROM {$wpdb->posts}", 1 );

		$this->assertSame( 3, $query->count() );
	}
}
