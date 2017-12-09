<?php
/**
 * Unit tests covering WP_Test_REST_Customize_Changesets_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 */

use WP_Tide_API\API\Controller\Audit_Posts_Controller;

/**
 * Class Test_Audit_Posts_Controller.
 *
 * @group restapi
 */
class Test_Audit_Posts_Controller extends WP_Test_REST_Controller_TestCase {

	/**
	 * REST Server.
	 *
	 * Note that this variable is already defined on the parent class but it lacks the phpdoc variable type.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	const CHECKSUM_PATTERN = '[a-fA-F\d]{64}';

	/**
	 * Set up before class.
	 *
	 * @param object $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {

		self::$subscriber_id = $factory->user->create( array(
			'role' => 'subscriber',
		) );

		self::$admin_id = $factory->user->create( array(
			'role' => 'administrator',
		) );
	}

	/**
	 * Test registering route.
	 *
	 * @covers Audit_Posts_Controller::register_routes()
	 */
	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/tide/v2/audit/(?P<checksum>' . static::CHECKSUM_PATTERN . ')', $routes );
	}

	/**
	 * Test getting item.
	 *
	 * @covers Audit_Posts_Controller::get_item_altid()
	 */
	public function test_get_item() {
		wp_set_current_user( self::$admin_id );

		$audit_id = $this->factory()->post->create( array(
			'post_type' => 'audit',
		) );

		$checksum = '7d9e35c703a7f8c6def92d5dbcf4a85a9271ce390474339cef7e404abb600000';
		update_post_meta( $audit_id, 'checksum', $checksum );
		update_post_meta( $audit_id, 'source_type', 'zip' );

		$request  = new WP_REST_Request( 'GET', sprintf( '/tide/v2/audit/%s', $checksum ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'zip', $data['source_type'] );
	}

	/**
	 * Test getting an item with wrong checksum.
	 */
	public function test_get_item_wrong_checksum() {
		wp_set_current_user( self::$admin_id );

		$this->factory()->post->create( array(
			'post_type' => 'audit',
		) );

		$checksum = '7d9e35c703a7f8c6def92d5dbcf4a85a9271ce390474339cef7e404a00000000';

		$request  = new WP_REST_Request( 'GET', sprintf( '/tide/v2/audit/%s', $checksum ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_altid_lookup', $response, 404 );
	}

	/**
	 * Test getting an item without permissions.
	 *
	 * @covers Audit_Posts_Controller::get_item_permissions_check_altid()
	 */
	public function test_get_item_permissions() {
		wp_set_current_user( self::$subscriber_id );

		$audit_id = $this->factory()->post->create( array(
			'post_type' => 'audit',
		) );

		$checksum = '7d9e35c703a7f8c6def92d5dbcf4a85a9271ce390474339cef7e404abb600000';
		update_post_meta( $audit_id, 'checksum', $checksum );

		$request  = new WP_REST_Request( 'GET', sprintf( '/tide/v2/audit/%s', $checksum ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	/**
	 * Test updating item.
	 *
	 * @covers Audit_Posts_Controller::update_item_altid()
	 */
	public function test_update_item() {
		wp_set_current_user( self::$admin_id );

		$audit_id = $this->factory()->post->create( array(
			'post_type' => 'audit',
		) );

		$checksum = '7d9e35c703a7f8c6def92d5dbcf4a85a9271ce390474339cef7e404abb600000';
		update_post_meta( $audit_id, 'checksum', $checksum );
		update_post_meta( $audit_id, 'source_type', 'zip' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/tide/v2/audit/%s', $checksum ) );
		$request->set_param( 'source_type', 'repo' );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );
		$source_type = get_post_meta( $audit_id, 'source_type', true );
		$this->assertSame( 'repo', $source_type );
	}

	/**
	 * Test deleting item.
	 *
	 * @covers Audit_Posts_Controller::delete_item_altid()
	 */
	public function test_delete_item() {
		wp_set_current_user( self::$admin_id );

		$audit_id = $this->factory()->post->create( array(
			'post_type' => 'audit',
		) );

		$checksum = '7d9e35c703a7f8c6def92d5dbcf4a85a9271ce390474339cef7e404abb600000';
		update_post_meta( $audit_id, 'checksum', $checksum );

		$request  = new WP_REST_Request( 'DELETE', sprintf( '/tide/v2/audit/%s', $checksum ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );

		$post = get_post( $audit_id );
		$this->assertEquals( 'trash', $post->post_status );

	}

	/**
	 * Test preparing item.
	 *
	 * @covers Audit_Posts_Controller::delete_item_altid()
	 */
	public function test_prepare_item() {
		wp_set_current_user( self::$admin_id );

		$audit_post = $this->factory()->post->create_and_get( array(
			'post_type' => 'audit',
		) );

		$checksum = '7d9e35c703a7f8c6def92d5dbcf4a85a9271ce390474339cef7e404abb600000';
		update_post_meta( $audit_post->ID, 'checksum', $checksum );

		$audit_posts_endpoint = new Audit_Posts_Controller( 'audit' );
		$request              = new WP_REST_Request();

		$response = $audit_posts_endpoint->prepare_item_for_response( $audit_post, $request );
		$data     = $response->get_data();

		$this->assertTrue( ! isset( $data['post_name'] ) );
		$this->assertEquals( $checksum, $data['checksum'] );
		$this->assertEquals( 'private', $data['visibility'] );
	}

	/**
	 * Test creating item.
	 *
	 * @covers Audit_Posts_Controller::create_item()
	 */
	public function test_create_item() {
		wp_set_current_user( self::$admin_id );

		$params = array(
			'source_url'  => 'http://example.com/example.zip',
			'source_type' => 'zip',
			'title'       => 'Post title',
			'content'     => 'Plugin Test',
		);

		$request = new WP_REST_Request( 'POST', '/tide/v2/audit' );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'zip', $data['source_type'] );
		$this->assertEquals( 'http://example.com/example.zip', $data['source_url'] );
	}

	/**
	 * Test handle_custom_args().
	 *
	 * @covers Audit_Posts_Controller::handle_custom_args()
	 */
	public function test_handle_custom_args() {
		$_GET['tags'] = 'test,test-2,test-3';

		$ar_controller = new Audit_Posts_Controller( 'audit' );
		$args          = $ar_controller->handle_custom_args( array(), null );
		$this->assertTrue( isset( $args['tax_query'] ) );

		// @codingStandardsIgnoreStart
		$expected_args = array(
			'tax_query' => array(
				array(
					'taxonomy' => 'audit_tag',
					'field'    => 'name',
					'terms'    => array(
						'test',
						'test-2',
						'test-3',
					),
				),
			),
		);
		// @codingStandardsIgnoreEnd
		$this->assertEquals( $args, $expected_args );
	}

	/**
	 * Test getting item schema.
	 */
	public function test_get_item_schema() {

		// Not applicable.
	}

	/**
	 * Test context param.
	 */
	public function test_context_param() {

		// Not applicable.
	}

	/**
	 * Test getting items.
	 */
	public function test_get_items() {

		// Not applicable.
	}
}
