<?php
/**
 * Test_API_Bootstrap
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\API\API_Bootstrap;

/**
 * Class Test_API_Bootstrap
 *
 * @coversDefaultClass WP_Tide_API\API\API_Bootstrap
 */
class Test_API_Bootstrap extends WP_UnitTestCase {

	/**
	 * API_Bootstrap instance.
	 *
	 * @var API_Bootstrap
	 */
	public $api_bootstrap;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->api_bootstrap         = new API_Bootstrap();
		$this->api_bootstrap->plugin = WP_Tide_API\Plugin::instance();
	}

	/**
	 * Test API_Bootstrap::register_post_types().
	 *
	 * @covers ::register_post_types()
	 */
	public function test_register_post_types() {

		$this->api_bootstrap->register_post_types();
		$this->assertTrue( post_type_exists( 'audit' ) );
	}

	/**
	 * Test API_Bootstrap::register_roles().
	 *
	 * @covers ::register_roles()
	 */
	public function test_register_roles() {

		$this->api_bootstrap->register_roles();
		$role = get_role( 'api_client' );
		$this->assertInstanceOf( WP_Role::class, $role );
	}

	/**
	 * Test API_Bootstrap::register_meta_fields().
	 *
	 * @covers ::register_meta_fields()
	 */
	public function test_register_meta_fields() {
		$meta_fields = array(
			'test_field' => array(),
		);
		$this->api_bootstrap->register_meta_fields( 'audit', $meta_fields );
		$this->assertTrue( registered_meta_key_exists( 'audit', 'test_field' ) );
	}

	/**
	 * Test API_Bootstrap::register_rest_fields().
	 *
	 * @covers ::register_rest_fields()
	 */
	public function test_register_rest_fields() {
		$rest_fields = array(
			'test_field' => array(),
		);

		$this->api_bootstrap->register_rest_fields( 'audit', $rest_fields );
		global $wp_rest_additional_fields;

		$this->assertTrue( isset( $wp_rest_additional_fields['audit']['test_field'] ) );
	}

	/**
	 * Test API_Bootstrap::register_taxonomies().
	 *
	 * @covers ::register_taxonomies()
	 */
	public function test_register_taxonomies() {
		$taxonomies = array(
			'test_taxonomy' => array(),
		);

		$this->api_bootstrap->register_taxonomies( 'audit', $taxonomies );
		$this->assertTrue( taxonomy_exists( 'test_taxonomy' ) );

		global $wp_taxonomies;

		$this->assertTrue( in_array( 'audit', $wp_taxonomies['test_taxonomy']->object_type, true ) );
	}

	/**
	 * Test API_Bootstrap::rest_field_default_get_callback().
	 *
	 * @covers ::rest_field_default_get_callback()
	 */
	public function test_rest_field_default_get_callback() {
		$audit_id = $this->factory->post->create( array(
			'post_type' => 'audit',
		) );

		update_post_meta( $audit_id, 'test_meta', 'test_value' );
		$audit_post = array(
			'id' => $audit_id,
		);

		$this->assertEquals( 'test_value', $this->api_bootstrap->rest_field_default_get_callback( $audit_post, 'test_meta', null, null ) );
	}

	/**
	 * Test API_Bootstrap::rest_field_default_update_callback().
	 *
	 * @covers ::rest_field_default_update_callback()
	 */
	public function test_rest_field_default_update_callback() {
		$audit_id = $this->factory->post->create( array(
			'post_type' => 'audit',
		) );

		$audit_post = get_post( $audit_id );

		$this->api_bootstrap->rest_field_default_update_callback( 'test_value', $audit_post, 'test_meta', null, null );

		$this->assertEquals( 'test_value', get_post_meta( $audit_id, 'test_meta', true ) );
	}

	/**
	 * Test API_Bootstrap::rest_audit_query().
	 *
	 * @covers ::rest_audit_query()
	 */
	public function test_rest_audit_query() {
		$request = new WP_REST_Request();
		$request->set_param( 'forbidden', 'test' );

		$args = $this->api_bootstrap->rest_audit_query( array(), $request );
		$this->assertEquals( array(), $args );

		$request = new WP_REST_Request();
		$request->set_param( 'checksum', '39c7d71a68565ddd7b6a0fd68d94924d0db449a99541439b3ab8a477c5f1fc4e' );

		$args = $this->api_bootstrap->rest_audit_query( array(), $request );
		$this->assertTrue( isset( $args['meta_query'] ) );
	}
}
