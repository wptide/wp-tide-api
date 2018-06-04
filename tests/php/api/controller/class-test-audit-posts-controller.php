<?php
/**
 * Test Audit_Posts_Controller controller.
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\API\Controller\Audit_Posts_Controller;

/**
 * Class Test_Audit_Posts_Controller.
 *
 * @coversDefaultClass WP_Tide_API\API\Controller\Audit_Posts_Controller
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
	 * API client user ID.
	 *
	 * @var int
	 */
	protected static $api_client_id;

	/**
	 * Audit client user ID.
	 *
	 * @var int
	 */
	protected static $audit_client_id;

	const CHECKSUM_PATTERN = '[a-fA-F\d]{64}';

	/**
	 * Set up before class.
	 *
	 * @param object $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$api_client_id = $factory->user->create( array(
			'user_login' => 'wporg',
			'role'       => 'api_client',
		) );

		self::$audit_client_id = $factory->user->create( array(
			'user_login' => 'audit-server',
			'role'       => 'audit_client',
		) );
	}

	/**
	 * Tear down after class.
	 */
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$api_client_id );
		self::delete_user( self::$audit_client_id );
	}

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		wp_set_current_user( self::$audit_client_id );
	}

	/**
	 * Teardown.
	 *
	 * @inheritdoc
	 */
	function tearDown() {
		$GLOBALS['current_user'] = null;
		parent::tearDown();
	}

	/**
	 * Test registering route.
	 *
	 * @covers ::register_routes()
	 */
	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/tide/v1/audit/(?P<checksum>' . static::CHECKSUM_PATTERN . ')', $routes );
	}


	/**
	 * Test getting items.
	 *
	 * @covers ::get_items()
	 */
	public function test_get_items() {
		$controller = new Audit_Posts_Controller( 'audit' );

		$audit_id = $this->factory()->post->create( array(
			'post_type'   => 'audit',
			'post_author' => self::$api_client_id,
			'post_title'  => 'Some Plugin',
		) );
		$term     = wp_insert_term( 'test-project', 'audit_project' );

		update_post_meta( $audit_id, 'visibility', 'private' );
		update_post_meta( $audit_id, 'version', '1.0.0' );
		update_post_meta( $audit_id, 'project_type', 'plugin' );
		wp_set_object_terms( $audit_id, array( $term['term_id'] ), 'audit_project' );

		$this->assertTrue( has_term( 'test-project', 'audit_project', $audit_id ) );

		$request = new WP_REST_Request( 'GET', '/tide/v1/audit' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'search', 'Some Plugin' );

		$items = $controller->get_items( $request );

		$this->assertEquals( $audit_id, $items[0]['id'] );

		$request = new WP_REST_Request( 'GET', sprintf( '/tide/v1/audit/%s/%s/%s', 'wporg', 'plugin', 'test-project' ) );
		$request->set_param( 'project_client', 'invalid' );
		$request->set_param( 'project_type', 'plugin' );
		$request->set_param( 'project_slug', 'test-project' );
		$request->set_param( 'version', '1.0.1' );
		$request->set_param( 'search', null );

		$items = $controller->get_items( $request );
		$this->assertInstanceOf( 'WP_Error', $items );
		$this->assertEquals( 'tide_audit_invalid_project_client', $items->get_error_code() );

		$request->set_param( 'project_client', 'wporg' );
		$request->set_param( 'version', '1.0.0' );

		$items = $controller->get_items( $request );
		$this->assertNotInstanceOf( 'WP_Error', $items );
		$this->assertEquals( $audit_id, $items['id'] );

		$GLOBALS['current_user'] = null;

		$items = $controller->get_items( $request );

		$this->assertInstanceOf( 'WP_Error', $items );
		$this->assertEquals( 'tide_audit_invalid_item', $items->get_error_code() );
	}

	/**
	 * Test getting an item.
	 *
	 * @covers ::get_item_altid()
	 */
	public function test_get_item() {
		$audit_id = $this->factory()->post->create( array(
			'post_type'   => 'audit',
			'post_author' => self::$api_client_id,
		) );

		$checksum = '7d9e35c703a7f8c6def92d5dbcf4a85a9271ce390474339cef7e404abb600000';
		update_post_meta( $audit_id, 'checksum', $checksum );
		update_post_meta( $audit_id, 'visibility', 'public' );

		$request  = new WP_REST_Request( 'GET', sprintf( '/tide/v1/audit/%s', $checksum ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'public', $data['visibility'] );
	}

	/**
	 * Test getting an item.
	 *
	 * @covers ::get_item_altid()
	 */
	public function test_get_item_by_project() {
		$audit_id = $this->factory()->post->create( array(
			'post_type'   => 'audit',
			'post_author' => self::$api_client_id,
		) );
		$term     = wp_insert_term( 'test-project', 'audit_project' );

		update_post_meta( $audit_id, 'project_type', 'plugin' );
		wp_set_object_terms( $audit_id, array( $term['term_id'] ), 'audit_project' );

		$this->assertTrue( has_term( 'test-project', 'audit_project', $audit_id ) );

		$request  = new WP_REST_Request( 'GET', sprintf( '/tide/v1/audit/%s/%s/%s', 'wporg', 'plugin', 'test-project' ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
	}

	/**
	 * Test getting an item.
	 *
	 * @covers ::get_item_altid()
	 */
	public function test_get_item_private_visibility() {
		$audit_id = $this->factory()->post->create( array(
			'post_type'   => 'audit',
			'post_author' => self::$api_client_id,
		) );

		$checksum = '7d9e35c703a7f8c6def92d5dbcf4a85a9271ce390474339cef7e404abb600000';
		update_post_meta( $audit_id, 'checksum', $checksum );
		update_post_meta( $audit_id, 'visibility', 'private' );

		$request  = new WP_REST_Request( 'GET', sprintf( '/tide/v1/audit/%s', $checksum ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'private', $data['visibility'] );
	}

	/**
	 * Test getting an item with wrong checksum.
	 */
	public function test_get_item_wrong_checksum() {
		$this->factory()->post->create( array(
			'post_type'   => 'audit',
			'post_author' => self::$api_client_id,
		) );

		$checksum = '7d9e35c703a7f8c6def92d5dbcf4a85a9271ce390474339cef7e404a00000000';

		$request  = new WP_REST_Request( 'GET', sprintf( '/tide/v1/audit/%s', $checksum ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_altid_lookup', $response, 404 );
	}

	/**
	 * Test getting an item without permissions.
	 *
	 * @covers ::get_item_permissions_check_altid()
	 */
	public function test_get_item_permissions() {
		$audit_id = $this->factory()->post->create( array(
			'post_type'   => 'audit',
			'post_author' => self::$api_client_id,
		) );

		wp_set_current_user( $this->factory()->post->create( array(
			'role' => 'subscriber',
		) ) );

		$checksum = '7d9e35c703a7f8c6def92d5dbcf4a85a9271ce390474339cef7e404abb600000';
		update_post_meta( $audit_id, 'checksum', $checksum );

		$request  = new WP_REST_Request( 'GET', sprintf( '/tide/v1/audit/%s', $checksum ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test getting an item without permissions.
	 *
	 * @covers ::get_item_permissions_check_altid()
	 */
	public function test_get_item_permissions_private_visibility_fails() {
		$audit_id = $this->factory()->post->create( array(
			'post_type'   => 'audit',
			'post_author' => self::$api_client_id,
		) );

		wp_set_current_user( $this->factory()->post->create( array(
			'role' => 'subscriber',
		) ) );

		$checksum = '7d9e35c703a7f8c6def92d5dbcf4a85a9271ce390474339cef7e404abb600000';
		update_post_meta( $audit_id, 'checksum', $checksum );
		update_post_meta( $audit_id, 'visibility', 'private' );

		$request  = new WP_REST_Request( 'GET', sprintf( '/tide/v1/audit/%s', $checksum ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );
	}

	/**
	 * Test getting an item with permissions.
	 *
	 * @covers ::check_read_permission()
	 * @covers ::get_item_permissions_check_altid()
	 */
	public function test_get_item_permissions_private_visibility_passes() {
		$audit_id = $this->factory()->post->create( array(
			'post_type'   => 'audit',
			'post_author' => self::$api_client_id,
		) );

		wp_set_current_user( self::$api_client_id );

		$checksum = '7d9e35c703a7f8c6def92d5dbcf4a85a9271ce390474339cef7e404abb600000';
		update_post_meta( $audit_id, 'checksum', $checksum );
		update_post_meta( $audit_id, 'visibility', 'private' );

		$request  = new WP_REST_Request( 'GET', sprintf( '/tide/v1/audit/%s', $checksum ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test updating item.
	 *
	 * @covers ::update_item()
	 * @covers ::check_update_permission()
	 * @covers ::update_item_altid()
	 * @covers ::update_item_permissions_check_altid()
	 */
	public function test_update_item() {
		$audit_id = $this->factory()->post->create( array(
			'post_type'   => 'audit',
			'post_author' => self::$api_client_id,
		) );

		$checksum = '7d9e35c703a7f8c6def92d5dbcf4a85a9271ce390474339cef7e404abb600000';
		update_post_meta( $audit_id, 'checksum', $checksum );
		update_post_meta( $audit_id, 'source_type', 'zip' );

		// Update supports POST, PUT, and PATCH.
		$request = new WP_REST_Request( 'POST', sprintf( '/tide/v1/audit/%s', $checksum ) );
		$request->set_param( 'id', $audit_id );
		$request->set_param( 'source_type', 'git' );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );
		$source_type = get_post_meta( $audit_id, 'source_type', true );
		$this->assertSame( 'git', $source_type );
	}

	/**
	 * Test updating item.
	 *
	 * @covers ::update_item()
	 * @covers ::update_item_altid()
	 * @covers ::update_audit_meta()
	 */
	public function test_update_item_altid() {
		$controller = new Audit_Posts_Controller( 'audit' );

		$audit_id = $this->factory()->post->create( array(
			'post_type'   => 'audit',
			'post_author' => self::$api_client_id,
		) );

		$checksum = '7d9e35c703a7f8c6def92d5dbcf4a85a9271ce390474339cef7e404abb600000';
		update_post_meta( $audit_id, 'checksum', $checksum );
		update_post_meta( $audit_id, 'source_type', 'zip' );
		update_post_meta( $audit_id, 'project_type', 'plugin' );

		// Update supports POST, PUT, and PATCH.
		$request = new WP_REST_Request( 'POST', sprintf( '/tide/v1/audit/%s', $checksum ) );
		$request->set_param( 'id', $audit_id );
		$request->set_param( 'source_type', 'git' );
		$request->set_param( 'project_type', 'plugin' );
		$request->set_param( 'checksum', $checksum );

		$response = $controller->update_item( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( 'git', get_post_meta( $audit_id, 'source_type', true ) );

		$request->set_param( 'checksum', null );
		$response = $controller->update_item_altid( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 'invalid_key', $response->get_error_code() );
	}

	/**
	 * Test updating item.
	 *
	 * @covers ::update_item()
	 * @covers ::update_item_altid()
	 * @covers ::update_audit_meta()
	 */
	public function test_update_item_new_standards() {
		$controller = new Audit_Posts_Controller( 'audit' );

		$audit_id = $this->factory()->post->create( array(
			'post_type'   => 'audit',
			'post_author' => self::$api_client_id,
		) );

		$checksum = '7d9e35c703a7f8c6def92d5dbcf4a85a9271ce390474339cef7e404abb600000';
		update_post_meta( $audit_id, 'checksum', $checksum );
		update_post_meta( $audit_id, 'project_type', 'plugin' );
		update_post_meta( $audit_id, 'standards', array(
			'phpcs_wordpress',
		) );

		// Update supports POST, PUT, and PATCH.
		$request = new WP_REST_Request( 'POST', sprintf( '/tide/v1/audit/%s', $checksum ) );
		$request->set_param( 'id', $audit_id );
		$request->set_param( 'project_type', 'plugin' );
		$request->set_param( 'checksum', $checksum );
		$request->set_param( 'standards', array(
			'phpcs_wordpress',
			'phpcs_phpcompatibility',
			'lighthouse',
		) );

		$response = $controller->update_item( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( array(
			'phpcs_wordpress',
			'phpcs_phpcompatibility',
		), get_post_meta( $audit_id, 'standards', true ) );
	}

	/**
	 * Test deleting item.
	 *
	 * @covers ::check_delete_permission()
	 * @covers ::delete_item_altid()
	 * @covers ::delete_item_permissions_check_altid()
	 */
	public function test_delete_item() {
		$audit_id = $this->factory()->post->create( array(
			'post_type'   => 'audit',
			'post_author' => self::$api_client_id,
		) );

		$checksum = '7d9e35c703a7f8c6def92d5dbcf4a85a9271ce390474339cef7e404abb600000';
		update_post_meta( $audit_id, 'checksum', $checksum );
		update_post_meta( $audit_id, 'visibility', 'public' );

		$request  = new WP_REST_Request( 'DELETE', sprintf( '/tide/v1/audit/%s', $checksum ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );

		$post = get_post( $audit_id );
		$this->assertEquals( 'trash', $post->post_status );
	}

	/**
	 * Test preparing item.
	 *
	 * @covers ::prepare_item_for_response()
	 */
	public function test_prepare_item_for_response() {
		$audit_post = $this->factory()->post->create_and_get( array(
			'post_type'   => 'audit',
			'post_author' => self::$api_client_id,
		) );

		$checksum = '7d9e35c703a7f8c6def92d5dbcf4a85a9271ce390474339cef7e404abb600000';
		update_post_meta( $audit_post->ID, 'checksum', $checksum );
		update_post_meta( $audit_post->ID, '_audit_phpcs_wordpress', array(
			'raw'     => array(
				'type'     => 'local',
				'filename' => 'file.json',
				'path'     => 'some-local-path',
			),
			'parsed'  => array(),
			'summary' => array(
				'files'       => array(
					'index.php' => array(
						'errors'   => 0,
						'warnings' => 0,
					),
				),
				'files_count' => 1,
				'errors'      => 0,
				'warnings'    => 0,
			),
		) );
		update_post_meta( $audit_post->ID, 'standards', array(
			'phpcs_wordpress',
		) );

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
	 * @covers ::create_item()
	 * @covers ::create_audit_request
	 * @covers ::check_create_permission()
	 */
	public function test_create_item() {
		$params = array(
			'source_url'  => 'http://example.com/example.zip',
			'source_type' => 'zip',
			'title'       => 'Post title',
			'content'     => 'Plugin Test',
		);

		$request = new WP_REST_Request( 'POST', '/tide/v1/audit' );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'zip', $data['source_type'] );
		$this->assertEquals( 'http://example.com/example.zip', $data['source_url'] );
	}

	/**
	 * Test updating item instead of creating new item when audit post with matching checksum exists.
	 *
	 * @covers ::create_item()
	 * @covers ::check_create_permission()
	 */
	public function test_create_item_matching_checksum_post_exists() {
		$audit_id = $this->factory()->post->create( array(
			'post_type'   => 'audit',
			'post_author' => self::$api_client_id,
		) );

		$checksum = '7d9e35c703a7f8c6def92d5dbcf4a85a9271ce390474339cef7e404abb600000';
		update_post_meta( $audit_id, 'checksum', $checksum );
		update_post_meta( $audit_id, 'visibility', 'public' );

		$params = array(
			'source_url'  => 'http://example.com/example.zip',
			'source_type' => 'zip',
			'title'       => 'Post title',
			'content'     => 'Plugin Test',
			'checksum'    => $checksum,
		);

		$request = new WP_REST_Request( 'POST', '/tide/v1/audit' );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();

		$this->assertEquals( $data['id'], $audit_id );
	}

	/**
	 * Test handle_custom_args().
	 *
	 * @covers ::handle_custom_args()
	 */
	public function test_handle_custom_args() {
		$_GET['project'] = 'test-project,test-project-2,test-project-3';
		$ar_controller   = new Audit_Posts_Controller( 'audit' );
		$args            = $ar_controller->handle_custom_args( array(), null );
		$this->assertTrue( isset( $args['tax_query'] ) );

		// @codingStandardsIgnoreStart
		$expected_args = array(
			'tax_query' => array(
				array(
					'taxonomy' => 'audit_project',
					'field'    => 'name',
					'terms'    => array(
						'test-project',
						'test-project-2',
						'test-project-3',
					),
				),
			),
		);
		// @codingStandardsIgnoreEnd
		$this->assertEquals( $args, $expected_args );
	}

	/**
	 * Test add_tax_query().
	 *
	 * @covers ::add_tax_query()
	 */
	public function test_add_tax_query() {
		$ar_controller   = new Audit_Posts_Controller( 'audit' );
		$args            = $ar_controller->add_tax_query( array(), array(
			array(
				'taxonomy' => 'audit_project',
				'field'    => 'name',
				'terms'    => array(
					'test-project',
					'test-project-2',
					'test-project-3',
				),
			),
		) );

		// @codingStandardsIgnoreStart
		$expected_args = array(
			'tax_query' => array(
				array(
					'taxonomy' => 'audit_project',
					'field'    => 'name',
					'terms'    => array(
						'test-project',
						'test-project-2',
						'test-project-3',
					),
				),
			),
		);
		// @codingStandardsIgnoreEnd
		$this->assertEquals( $args, $expected_args );

		$args = $ar_controller->add_tax_query( array(
			'tax_query' => array(
				array(
					'taxonomy' => 'test_tax',
					'field'    => 'name',
					'terms'    => array(
						'test-tax',
					),
				),
			),
		), array(
			array(
				'taxonomy' => 'audit_project',
				'field'    => 'name',
				'terms'    => array(
					'test-project',
					'test-project-2',
					'test-project-3',
				),
			),
		) );

		// @codingStandardsIgnoreStart
		$expected_args = array(
			'tax_query' => array(
				'relation' => 'AND',
				array(
					'taxonomy' => 'test_tax',
					'field'    => 'name',
					'terms'    => array(
						'test-tax',
					),
				),
				array(
					'taxonomy' => 'audit_project',
					'field'    => 'name',
					'terms'    => array(
						'test-project',
						'test-project-2',
						'test-project-3',
					),
				),
			),
		);
		// @codingStandardsIgnoreEnd
		$this->assertEquals( $args, $expected_args );
	}

	/**
	 * Test getting post by alt ID.
	 *
	 * @covers ::get_altid_post()
	 */
	public function test_get_altid_post() {
		$controller = new Audit_Posts_Controller( 'audit' );

		$audit_id = $this->factory()->post->create( array(
			'post_type'   => 'audit',
			'post_author' => self::$api_client_id,
		) );
		$term     = wp_insert_term( 'test-project', 'audit_project' );

		update_post_meta( $audit_id, 'version', '1.1.0' );
		update_post_meta( $audit_id, 'project_type', 'plugin' );
		wp_set_object_terms( $audit_id, array( $term['term_id'] ), 'audit_project' );

		$this->assertTrue( has_term( 'test-project', 'audit_project', $audit_id ) );

		$request = new WP_REST_Request( 'GET', sprintf( '/tide/v1/audit/%s/%s/%s', 'wporg', 'plugin', 'test-project' ) );
		$post    = $controller->get_altid_post( $request );

		$this->assertInstanceOf( 'WP_Error', $post );
		$this->assertEquals( 'invalid_key', $post->get_error_code() );

		$request->set_param( 'project_client', 'invalid' );
		$request->set_param( 'project_type', 'plugin' );
		$request->set_param( 'project_slug', 'test-project' );
		$request->set_param( 'version', '1.1.0' );

		$post = $controller->get_altid_post( $request );
		$this->assertInstanceOf( 'WP_Error', $post );
		$this->assertEquals( 'tide_audit_invalid_project_client', $post->get_error_code() );

		$request->set_param( 'project_client', 'wporg' );

		$post = $controller->get_altid_post( $request );
		$this->assertNotInstanceOf( 'WP_Error', $post );
		$this->assertInstanceOf( 'WP_Post', $post );
	}

	/**
	 * Test getting post by alt ID.
	 *
	 * @covers ::get_altid_post_id()
	 */
	public function test_get_altid_post_id() {
		$controller = new Audit_Posts_Controller( 'audit' );

		$audit_id = $this->factory()->post->create( array(
			'post_type'   => 'audit',
			'post_author' => self::$api_client_id,
		) );
		$term     = wp_insert_term( 'test-project', 'audit_project' );

		update_post_meta( $audit_id, 'project_type', 'plugin' );
		wp_set_object_terms( $audit_id, array( $term['term_id'] ), 'audit_project' );

		$this->assertTrue( has_term( 'test-project', 'audit_project', $audit_id ) );

		$request = new WP_REST_Request( 'GET', sprintf( '/tide/v1/audit/%s/%s/%s', 'wporg', 'plugin', 'test-project' ) );
		$post    = $controller->get_altid_post_id( $request );

		$this->assertInstanceOf( 'WP_Error', $post );
		$this->assertEquals( 'invalid_key', $post->get_error_code() );

		$request->set_param( 'project_client', 'wporg' );
		$request->set_param( 'project_type', 'plugin' );
		$request->set_param( 'project_slug', 'test-project' );

		$post = $controller->get_altid_post_id( $request );
		$this->assertNotInstanceOf( 'WP_Error', $post );
		$this->assertEquals( $audit_id, $post );
	}

	/**
	 * Test prepare item.
	 */
	public function test_prepare_item() {

		// Not applicable.
	}

	/**
	 * Test get item schema.
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
}
