<?php
/**
 * Tests for robots template functions and filters.
 *
 * @package WordPress
 *
 * @group robots
 */
class Tests_Robots extends WP_UnitTestCase {

	/**
	 * The `wp_robots` callbacks registered by default, captured before they are removed.
	 *
	 * @var WP_Hook|null
	 */
	private $default_wp_robots_hook;

	public function set_up() {
		parent::set_up();

		$this->default_wp_robots_hook = isset( $GLOBALS['wp_filter']['wp_robots'] ) ? clone $GLOBALS['wp_filter']['wp_robots'] : null;

		$this->set_permalink_structure( '/%postname%/' );
		remove_all_filters( 'wp_robots' );
	}

	/**
	 * @ticket 51511
	 */
	public function test_wp_robots_renders_when_relevant() {
		// Do not render robots meta tag when there are no directives.
		$output = get_echo( 'wp_robots' );
		$this->assertEmpty( $output );

		// Render robots meta tag with noindex.
		add_filter( 'wp_robots', array( $this, 'add_noindex_directive' ) );
		$output = get_echo( 'wp_robots' );
		$this->assertSame( "<meta name='robots' content='noindex' />\n", $output );

		// Do not render robots meta tag when there are only false-y directives.
		add_filter( 'wp_robots', array( $this, 'remove_noindex_directive' ), 11 );
		$output = get_echo( 'wp_robots' );
		$this->assertEmpty( $output );
	}

	/**
	 * @ticket 51511
	 */
	public function test_wp_robots_parses_directives_correctly() {
		add_filter(
			'wp_robots',
			static function ( array $robots ) {
				// Directives that should have values must use strings.
				$robots['directive-with-value']         = 'yes';
				$robots['directive-with-numeric-value'] = '1';
				// Any non-string value will be evaluated as boolean.
				// False-y directives will not be included.
				$robots['directive-active-boolean']   = true;
				$robots['directive-inactive-boolean'] = false;
				$robots['directive-active-integer']   = 1;
				$robots['directive-inactive-integer'] = 0;
				return $robots;
			}
		);

		$expected_directives_string = implode(
			', ',
			array(
				'directive-with-value:yes',
				'directive-with-numeric-value:1',
				'directive-active-boolean',
				'directive-active-integer',
			)
		);

		$output = get_echo( 'wp_robots' );
		$this->assertStringContainsString( "'{$expected_directives_string}'", $output );
	}

	/**
	 * @ticket 51511
	 */
	public function test_wp_robots_noindex() {
		add_filter( 'wp_robots', 'wp_robots_noindex' );

		update_option( 'blog_public', '1' );
		$output = get_echo( 'wp_robots' );
		$this->assertEmpty( $output );

		update_option( 'blog_public', '0' );
		$output = get_echo( 'wp_robots' );
		$this->assertStringContainsString( "'noindex, nofollow'", $output );
	}

	/**
	 * @ticket 51511
	 */
	public function test_wp_robots_no_robots() {
		add_filter( 'wp_robots', 'wp_robots_no_robots' );

		update_option( 'blog_public', '1' );
		$output = get_echo( 'wp_robots' );
		$this->assertStringContainsString( "'noindex, follow'", $output );

		update_option( 'blog_public', '0' );
		$output = get_echo( 'wp_robots' );
		$this->assertStringContainsString( "'noindex, nofollow'", $output );
	}

	/**
	 * @ticket 51511
	 */
	public function test_wp_robots_sensitive_page() {
		add_filter( 'wp_robots', 'wp_robots_sensitive_page' );

		$output = get_echo( 'wp_robots' );
		$this->assertStringContainsString( "'noindex, noarchive'", $output );
	}

	/**
	 * @ticket 51511
	 */
	public function test_wp_robots_max_image_preview_large() {
		add_filter( 'wp_robots', 'wp_robots_max_image_preview_large' );

		update_option( 'blog_public', '1' );
		$output = get_echo( 'wp_robots' );
		$this->assertStringContainsString( "'max-image-preview:large'", $output );

		update_option( 'blog_public', '0' );
		$output = get_echo( 'wp_robots' );
		$this->assertEmpty( $output );
	}

	/**
	 * @ticket 52457
	 */
	public function test_wp_robots_search_page() {
		add_filter( 'wp_robots', 'wp_robots_noindex_search' );
		$this->go_to( home_url( '?s=ticket+52457+core.trac.wordpress.org' ) );

		$output = get_echo( 'wp_robots' );
		$this->assertStringContainsString( 'noindex', $output );
	}

	/**
	 * @ticket 52457
	 */
	public function test_wp_robots_non_search_page() {
		add_filter( 'wp_robots', 'wp_robots_noindex_search' );
		$this->go_to( home_url() );

		$output = get_echo( 'wp_robots' );
		$this->assertStringNotContainsString( 'noindex', $output );
	}

	/**
	 * @ticket 58751
	 * @covers ::wp_robots_noindex_404
	 */
	public function test_wp_robots_404_page() {
		add_filter( 'wp_robots', 'wp_robots_noindex_404' );
		$this->go_to( '/this-page-does-not-exist' );

		$output = get_echo( 'wp_robots' );
		$this->assertStringContainsString( 'noindex', $output );
	}

	/**
	 * @ticket 58751
	 * @covers ::wp_robots_noindex_404
	 */
	public function test_wp_robots_non_404_page() {
		add_filter( 'wp_robots', 'wp_robots_noindex_404' );
		$this->go_to( home_url() );

		$output = get_echo( 'wp_robots' );
		$this->assertStringNotContainsString( 'noindex', $output );
	}

	/**
	 * Restores the `wp_robots` callbacks that WordPress registers by default.
	 */
	private function restore_default_wp_robots_filters() {
		if ( $this->default_wp_robots_hook ) {
			$GLOBALS['wp_filter']['wp_robots'] = $this->default_wp_robots_hook;
		}
	}

	/**
	 * @ticket 58751
	 * @covers ::wp_robots_noindex_404
	 */
	public function test_wp_robots_noindex_404_is_hooked_by_default() {
		$this->restore_default_wp_robots_filters();
		$this->go_to( '/this-page-does-not-exist' );

		$this->assertTrue( is_404() );
		$this->assertSame( "<meta name='robots' content='noindex, follow, max-image-preview:large' />\n", get_echo( 'wp_robots' ) );
	}

	/**
	 * @ticket 58751
	 * @covers ::wp_robots_noindex_404
	 */
	public function test_wp_robots_404_page_with_public_site_and_search_callback_outputs_one_noindex() {
		update_option( 'blog_public', '1' );
		add_filter( 'wp_robots', 'wp_robots_noindex' );
		add_filter( 'wp_robots', 'wp_robots_noindex_search' );
		add_filter( 'wp_robots', 'wp_robots_noindex_404' );
		$this->go_to( '/this-page-does-not-exist' );

		$output = get_echo( 'wp_robots' );

		$this->assertSame( "<meta name='robots' content='noindex, follow' />\n", $output );
	}

	/**
	 * @ticket 58751
	 * @covers ::wp_robots_noindex_404
	 */
	public function test_wp_robots_noindex_404_can_be_overridden_by_later_filter() {
		$this->restore_default_wp_robots_filters();
		add_filter(
			'wp_robots',
			static function ( array $robots ) {
				$robots['noindex'] = false;
				return $robots;
			},
			20
		);
		$this->go_to( '/this-page-does-not-exist' );

		$this->assertTrue( is_404() );
		$this->assertSame( "<meta name='robots' content='follow, max-image-preview:large' />\n", get_echo( 'wp_robots' ) );
		$this->assertTrue( wp_robots_noindex_404( array() )['noindex'], 'The callback itself should still add noindex.' );
	}

	/**
	 * @ticket 58751
	 * @covers ::wp_robots_noindex_404
	 */
	public function test_wp_robots_noindex_404_preserves_existing_directives() {
		$this->go_to( '/this-page-does-not-exist' );

		$robots = wp_robots_noindex_404( array( 'max-image-preview' => 'large' ) );

		$this->assertSame( 'large', $robots['max-image-preview'] );
		$this->assertTrue( $robots['noindex'] );
	}

	/**
	 * The wp_die() screen can render before a query exists, so is_404() must not run.
	 *
	 * @ticket 58751
	 * @covers ::_default_wp_die_handler
	 */
	public function test_default_wp_die_handler_does_not_call_is_404() {
		$this->restore_default_wp_robots_filters();
		$this->go_to( '/this-page-does-not-exist' );

		$wp_query = $GLOBALS['wp_query'];
		unset( $GLOBALS['wp_query'] );

		ob_start();
		_default_wp_die_handler( 'Synthetic message', 'Synthetic title', array( 'exit' => false ) );
		$output = ob_get_clean();

		$GLOBALS['wp_query'] = $wp_query;

		$this->assertSame( 1, substr_count( $output, '<meta name=\'robots\'' ), 'Only one robots meta tag should be output.' );
		$this->assertSame( 1, substr_count( $output, 'noindex' ), 'The noindex directive should not be duplicated.' );
		$this->assertFalse( has_filter( 'wp_robots', 'wp_robots_noindex_404' ) );
	}

	public function add_noindex_directive( array $robots ) {
		$robots['noindex'] = true;
		return $robots;
	}

	public function remove_noindex_directive( array $robots ) {
		$robots['noindex'] = false;
		return $robots;
	}
}
