<?php

/**
 * Tests for WP_Query::$resolved_query_vars and WP_Query::get_resolved_query_var().
 *
 * @ticket 23309
 *
 * @group query
 *
 * @covers WP_Query::get_posts
 * @covers WP_Query::get_resolved_query_var
 */
class Tests_Query_ResolvedQueryVars extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	public static $editor_id;

	/**
	 * @var int
	 */
	public static $author_id;

	/**
	 * @var int
	 */
	public static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$editor_id = $factory->user->create( array( 'role' => 'editor' ) );
		self::$author_id = $factory->user->create( array( 'role' => 'author' ) );
		self::$post_id   = $factory->post->create();
	}

	public function set_up() {
		parent::set_up();

		register_post_type( 'wptests_pt', array( 'public' => true ) );
		register_taxonomy( 'wptests_tax', 'wptests_pt' );
	}

	/**
	 * @ticket 23309
	 */
	public function test_resolved_query_vars_should_be_empty_before_query_runs() {
		$query = new WP_Query();

		$this->assertSame( array(), $query->resolved_query_vars );
	}

	/**
	 * @ticket 23309
	 *
	 * @covers WP_Query::init
	 */
	public function test_resolved_query_vars_should_be_reset_by_init() {
		$query = new WP_Query( array( 'post_type' => 'page' ) );
		$this->assertNotEmpty( $query->resolved_query_vars, 'Resolved query vars should be populated after the query.' );

		$query->init();

		$this->assertSame( array(), $query->resolved_query_vars, 'Resolved query vars should be empty after init().' );
	}

	/**
	 * @ticket 23309
	 */
	public function test_get_resolved_query_var_should_return_default_for_unknown_key() {
		$query = new WP_Query( array( 'post_type' => 'post' ) );

		$this->assertSame( '', $query->get_resolved_query_var( 'wptests_unknown' ), 'Default value should be an empty string.' );
		$this->assertSame( 'foo', $query->get_resolved_query_var( 'wptests_unknown', 'foo' ), 'Custom default value should be returned.' );
	}

	/**
	 * @ticket 23309
	 *
	 * @dataProvider data_resolved_post_type_should_match_requested_post_type
	 *
	 * @param string|string[] $post_type Requested post type.
	 * @param string|string[] $expected  Expected resolved post type.
	 */
	public function test_resolved_post_type_should_match_requested_post_type( $post_type, $expected ) {
		$query = new WP_Query( array( 'post_type' => $post_type ) );

		$this->assertSame( $expected, $query->get_resolved_query_var( 'post_type' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_resolved_post_type_should_match_requested_post_type() {
		return array(
			'string'              => array(
				'post_type' => 'page',
				'expected'  => 'page',
			),
			'single-item array'   => array(
				'post_type' => array( 'page' ),
				'expected'  => 'page',
			),
			// Multiple post types are sorted to ensure the same cache key is generated.
			'multiple-item array' => array(
				'post_type' => array( 'post', 'page' ),
				'expected'  => array( 'page', 'post' ),
			),
		);
	}

	/**
	 * When a custom taxonomy is queried without a post type, get_posts() resolves
	 * the post type from the taxonomy's object types. This is the main use case
	 * described in the ticket.
	 *
	 * @ticket 23309
	 */
	public function test_resolved_post_type_should_be_taken_from_queried_taxonomy() {
		$term_id = self::factory()->term->create( array( 'taxonomy' => 'wptests_tax' ) );

		$query = new WP_Query(
			array(
				'tax_query' => array(
					array(
						'taxonomy' => 'wptests_tax',
						'terms'    => $term_id,
					),
				),
			)
		);

		$this->assertSame( '', $query->get( 'post_type' ), 'The original query var should not be changed.' );
		$this->assertSame( 'wptests_pt', $query->get_resolved_query_var( 'post_type' ), 'The resolved post type should come from the taxonomy.' );
	}

	/**
	 * A query without a post type queries posts of the 'post' type.
	 *
	 * @ticket 23309
	 */
	public function test_resolved_post_type_should_default_to_post() {
		$query = new WP_Query( array( 'p' => 0 ) );

		$this->assertSame( 'post', $query->get_resolved_query_var( 'post_type' ) );
	}

	/**
	 * @ticket 23309
	 *
	 * @dataProvider data_resolved_post_status_should_be_array_of_requested_statuses
	 *
	 * @param string|string[] $post_status Requested post status.
	 * @param string[]        $expected    Expected resolved post statuses.
	 */
	public function test_resolved_post_status_should_be_array_of_requested_statuses( $post_status, $expected ) {
		$query = new WP_Query( array( 'post_status' => $post_status ) );

		$this->assertSame( $expected, $query->get_resolved_query_var( 'post_status' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_resolved_post_status_should_be_array_of_requested_statuses() {
		return array(
			'single status'          => array(
				'post_status' => 'draft',
				'expected'    => array( 'draft' ),
			),
			'comma-separated string' => array(
				'post_status' => 'publish,draft',
				'expected'    => array( 'draft', 'publish' ),
			),
			'array'                  => array(
				'post_status' => array( 'publish', 'draft' ),
				'expected'    => array( 'draft', 'publish' ),
			),
			'any'                    => array(
				'post_status' => 'any',
				'expected'    => array( 'any' ),
			),
		);
	}

	/**
	 * @ticket 23309
	 */
	public function test_resolved_post_status_should_be_public_statuses_for_logged_out_user() {
		$query = new WP_Query( array( 'post_type' => 'post' ) );

		$this->assertSameSets(
			get_post_stati( array( 'public' => true ) ),
			$query->get_resolved_query_var( 'post_status' )
		);
	}

	/**
	 * @ticket 23309
	 */
	public function test_resolved_post_status_should_include_private_for_user_who_can_read_private_posts() {
		wp_set_current_user( self::$editor_id );

		$query = new WP_Query( array( 'post_type' => 'post' ) );

		$this->assertContains( 'private', $query->get_resolved_query_var( 'post_status' ) );
	}

	/**
	 * A logged-in user without the `read_private_posts` capability still gets
	 * their own private posts back, so 'private' is part of the queried statuses.
	 *
	 * @ticket 23309
	 */
	public function test_resolved_post_status_should_include_private_for_logged_in_author() {
		wp_set_current_user( self::$author_id );

		$query = new WP_Query( array( 'post_type' => 'post' ) );

		$this->assertContains( 'private', $query->get_resolved_query_var( 'post_status' ) );
	}

	/**
	 * @ticket 23309
	 */
	public function test_resolved_post_status_should_include_admin_all_list_statuses_in_admin() {
		set_current_screen( 'edit.php' );

		$query = new WP_Query( array( 'post_type' => 'post' ) );

		set_current_screen( 'front' );

		$resolved = $query->get_resolved_query_var( 'post_status' );
		$expected = get_post_stati(
			array(
				'protected'              => true,
				'show_in_admin_all_list' => true,
			)
		);

		foreach ( $expected as $status ) {
			$this->assertContains( $status, $resolved, "Status '{$status}' should be resolved in the admin." );
		}
	}

	/**
	 * @ticket 23309
	 */
	public function test_resolved_query_vars_should_be_populated_when_results_come_from_cache() {
		$args = array(
			'post_type'     => 'post',
			'cache_results' => true,
		);

		// Prime the query cache.
		new WP_Query( $args );

		$num_queries = get_num_queries();
		$query       = new WP_Query( $args );

		$this->assertSame( $num_queries, get_num_queries(), 'The second query should come from the cache.' );
		$this->assertSame( 'post', $query->get_resolved_query_var( 'post_type' ), 'Resolved post type should be set.' );
		$this->assertNotEmpty( $query->get_resolved_query_var( 'post_status' ), 'Resolved post status should be set.' );
	}

	/**
	 * @ticket 23309
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $fields Value of the `fields` query var.
	 */
	public function test_resolved_query_vars_should_be_populated_for_all_fields_values( $fields ) {
		$query = new WP_Query(
			array(
				'post_type' => 'page',
				'fields'    => $fields,
			)
		);

		$this->assertSame( 'page', $query->get_resolved_query_var( 'post_type' ) );
	}

	/**
	 * @ticket 23309
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $fields Value of the `fields` query var.
	 */
	public function test_resolved_query_vars_should_be_populated_for_all_fields_values_from_cache( $fields ) {
		$args = array(
			'post_type'     => 'page',
			'fields'        => $fields,
			'cache_results' => true,
		);

		// Prime the query cache.
		new WP_Query( $args );

		$query = new WP_Query( $args );

		$this->assertSame( 'page', $query->get_resolved_query_var( 'post_type' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_fields() {
		return array(
			'all'        => array( 'all' ),
			'ids'        => array( 'ids' ),
			'id=>parent' => array( 'id=>parent' ),
		);
	}
}