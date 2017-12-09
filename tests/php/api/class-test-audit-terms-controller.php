<?php
/**
 * Test Audit_Terms_Controller controller.
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\API\Controller\Audit_Terms_Controller;

/**
 * Class Test_Audit_Terms_Controller
 *
 * @coversDefaultClass WP_Tide_API\API\Controller\Audit_Terms_Controller
 */
class Test_Audit_Terms_Controller extends WP_Test_REST_Controller_TestCase {

	/**
	 * REST Server.
	 *
	 * Note that this variable is already defined on the parent class but it lacks the phpdoc variable type.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Set up before class.
	 *
	 * @param object $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {

		self::$admin_id = $factory->user->create( array(
			'role' => 'administrator',
		) );
	}

	/**
	 * Test registering route.
	 */
	public function test_register_routes() {

		// Not applicable.
	}

	/**
	 * Test getting item.
	 */
	public function test_get_item() {

		// Not applicable.
	}

	/**
	 * Test getting an item permissions.
	 *
	 * @covers ::get_items_permissions_check()
	 */
	public function test_get_items_permissions() {
		$ar_endpoint = new Audit_Terms_Controller( 'category' );

		wp_set_current_user( 0 );
		$request = new WP_REST_Request();
		$request->set_param( 'context', 'test_context' );
		$response = $ar_endpoint->get_items_permissions_check( $request );

		$this->assertFalse( $response );

		wp_set_current_user( self::$admin_id );
		$response = $ar_endpoint->get_items_permissions_check( $request );

		$this->assertTrue( $response );

	}

	/**
	 * Test updating item.
	 */
	public function test_update_item() {

		// Not applicable.
	}

	/**
	 * Test deleting item.
	 */
	public function test_delete_item() {

		// Not applicable.
	}

	/**
	 * Test preparing item.
	 *
	 * @covers ::prepare_item_for_response()
	 */
	public function test_prepare_item() {
		$term_endpoint = new Audit_Terms_Controller( 'category' );
		$request       = new WP_REST_Request();

		wp_insert_term( 'test_term', 'category' );

		// @codingStandardsIgnoreStart Ignore for VIP.
		$term = get_term_by( 'slug', 'test_term', 'category' );
		// @codingStandardsIgnoreEnd

		$response = $term_endpoint->prepare_item_for_response( $term, $request );
		$data     = $response->get_data();

		$this->assertTrue( ! isset( $data['id'] ) );
		$this->assertTrue( ! isset( $data['slug'] ) );
	}

	/**
	 * Test creating item.
	 */
	public function test_create_item() {

		// Not applicable.
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
