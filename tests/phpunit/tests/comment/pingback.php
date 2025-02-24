<?php

/**
 * @group comment
 * @covers ::pingback
 */
class Tests_Comment_Pingback extends WP_UnitTestCase {

	protected static $post_id;
	protected $response = array();

	public function set_up() {
		parent::set_up();

		add_filter( 'pre_http_request', array( $this, 'request_response' ) );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'request_response' ) );
		parent::tear_down();
	}

	public function test_pingback() {
		$content = <<<HTML
<a href="http://example.org">test</a>
<a href="http://example1.org/test">test</a>
<a href="http://example3.org/">test</a>
HTML;

		$body = <<<BODY
			<a rel="pingback" href="https://example1.org/test/pingback">test</a>
BODY;

		$this->response = array(
			'body'     => $body,
			'response' => array( 'code' => 200 ),
		);

		self::$post_id = self::factory()->post->create(
			array( 'post_content' => $content )
		);

		$post = get_post( self::$post_id );
		$this->assertEquals( array( 'http://example1.org/test' => false ), pingback( $post->post_content, self::$post_id ) );
	}

	public function test_pingback_no_ping_back() {
		$content = <<<HTML
<a href="http://example.org">test</a>
<a href="http://example1.org/test">test</a>
<a href="http://example3.org/">test</a>
HTML;

		$body = <<<BODY
			<a href="https://example1.org/test">test</a>
BODY;

		$this->response = array(
			'body'     => $body,
			'response' => array( 'code' => 200 ),
		);

		self::$post_id = self::factory()->post->create(
			array( 'post_content' => $content )
		);

		$post = get_post( self::$post_id );
		$this->assertEquals( array(), pingback( $post->post_content, self::$post_id ) );
	}

	public function test_pingback_error_response() {
		$content = <<<HTML
<a href="http://example.org">test</a>
<a href="http://example1.org/test">test</a>
<a href="http://example3.org/">test</a>
HTML;

		$this->response = new WP_Error();

		self::$post_id = self::factory()->post->create(
			array( 'post_content' => $content )
		);

		$post = get_post( self::$post_id );
		$this->assertEquals( array(), pingback( $post->post_content, self::$post_id ) );
	}

	public function request_response() {
		return $this->response;
	}
}
