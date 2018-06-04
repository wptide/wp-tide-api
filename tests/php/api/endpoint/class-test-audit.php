<?php
/**
 * Test_Audit
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\API\Endpoint\Audit;

/**
 * Class Test_Audit
 *
 * @coversDefaultClass WP_Tide_API\API\Endpoint\Audit
 */
class Test_Audit extends WP_UnitTestCase {

	/**
	 * Test Audit::post_type_structure().
	 *
	 * @covers ::post_type_structure()
	 */
	public function test_post_type_structure() {
		$audit           = new Audit();
		$tide_post_types = $audit->post_type_structure( array() );

		$this->assertTrue( isset( $tide_post_types['audit'] ) );
	}

	/**
	 * Test Audit::allowed_standards.
	 *
	 * @covers ::allowed_standards()
	 */
	public function test_allowed_standards() {
		$audit = new Audit();

		add_filter( 'tide_api_client_allowed_audits', array( $this, 'add_custom_audits' ), 10, 1 );
		$allowed_fields = $audit->allowed_standards();

		$this->assertTrue( isset( $allowed_fields['phpcs_wordpress-core'] ) );
		$this->assertTrue( isset( $allowed_fields['test_audit'] ) );
	}

	/**
	 * Test Audit::filter_standards.
	 *
	 * @covers ::filter_standards()
	 */
	public function test_filter_standards() {
		$standards = array_keys( Audit::allowed_standards() );
		$standards = array_merge(
			$standards,
			array( 'disallowed_standard' ) // Add disallowed standard.
		);

		$filtered_standards = Audit::filter_standards( $standards );

		$this->assertTrue( in_array( 'phpcs_wordpress', $filtered_standards, true ) );
		$this->assertFalse( in_array( 'disallowed_standard', $filtered_standards, true ) );
	}

	/**
	 * Add custom test audit.
	 *
	 * @param array $audits Array of audits.
	 * @return array Filtered audits.
	 */
	public function add_custom_audits( $audits ) {
		$audits['test_audit'] = array();
		return $audits;
	}

	/**
	 * Test Audit::executable_audit_fields.
	 *
	 * @covers ::executable_audit_fields()
	 */
	public function test_executable_audit_fields() {
		add_filter( 'tide_api_executable_audits', array( $this, 'add_custom_audits' ), 10, 1 );

		$audit       = new Audit();
		$exec_fields = $audit->executable_audit_fields();

		$this->assertTrue( isset( $exec_fields['test_audit'] ) );
		$this->assertTrue( isset( $exec_fields['phpcs_wordpress'] ) );
	}

	/**
	 * Test Audit::rest_field_url_update_callback.
	 *
	 * @covers ::rest_field_url_update_callback()
	 */
	public function test_rest_field_url_update_callback() {
		$audit_id = $this->factory->post->create( array(
			'post_type' => 'audit',
		) );

		$audit_post = get_post( $audit_id );
		$audit      = new Audit();

		$audit->rest_field_url_update_callback( 'example.com', $audit_post, 'test_url', null, null );

		$this->assertEquals( 'http://example.com', get_post_meta( $audit_id, 'test_url', true ) );
	}

	/**
	 * Test Audit::remove_response_fields.
	 *
	 * @covers ::remove_response_fields()
	 */
	public function test_remove_response_fields() {
		$removed_fields = apply_filters( 'tide_api_response_removed_fields', array(
			'id',
		) );

		$this->assertContains( 'audit_meta', $removed_fields );
	}

	/**
	 * Test Audit::rest_reports_update.
	 *
	 * @covers ::rest_reports_update()
	 */
	public function test_rest_reports_update() {
		$audit_id = $this->factory->post->create( array(
			'post_type' => 'audit',
		) );

		$audit_post = get_post( $audit_id );
		$audit      = new Audit();

		$reports = array(
			'phpcs_wordpress'      => 'test_value',
			'phpcs_wordpress-fake' => 'test_value',
		);

		$audit->rest_reports_update( $reports, $audit_post, 'not_reports', null, null );
		$this->assertEmpty( get_post_meta( $audit_id, '_audit_phpcs_wordpress', true ) );
		$this->assertEmpty( get_post_meta( $audit_id, '_audit_phpcs_wordpress-fake', true ) );

		$audit->rest_reports_update( $reports, $audit_post, 'reports', null, null );
		$this->assertEquals( 'test_value', get_post_meta( $audit_id, '_audit_phpcs_wordpress', true ) );
		$this->assertEmpty( get_post_meta( $audit_id, '_audit_phpcs_wordpress-fake', true ) );
	}

	/**
	 * Test Audit::rest_reports_get.
	 *
	 * @covers ::rest_reports_get()
	 */
	public function test_rest_reports_get() {

		$audit = new Audit();

		$audit_id = $this->factory->post->create( array(
			'post_type' => 'audit',
		) );
		update_post_meta( $audit_id, '_audit_phpcs_wordpress', '{"value":"not empty"}' );
		update_post_meta( $audit_id, '_audit_phpcs_phpcompatibility', '{"value":"not empty"}' );
		update_post_meta( $audit_id, '_audit_lighthouse', '{"value":"not empty"}' );
		update_post_meta( $audit_id, '_audit_phpcs_invalid-standard', '{"value":"not empty"}' );

		$request = new WP_REST_Request( 'GET', rest_url( "tide/v1/audit/{$audit_id}" ) );

		$response = array(
			'id' => $audit_id,
		);

		// Test request without `standards` param.
		$reports = $audit->rest_reports_get( $response, 'reports', $request );
		$this->assertTrue( isset( $reports['phpcs_wordpress'] ) );
		$this->assertTrue( isset( $reports['phpcs_phpcompatibility'] ) );
		$this->assertTrue( isset( $reports['lighthouse'] ) );
		$this->assertFalse( isset( $reports['phpcs_invalid-standard'] ) );

		update_post_meta( $audit_id, 'standards', array(
			'phpcs_wordpress-vip', // Test valid standard with empty results.
			'phpcs_wordpress',
			'phpcs_phpcompatibility',
			'lighthouse',
		) );

		// Test request without `standards` param but the "standards" meta is set.
		$reports = $audit->rest_reports_get( $response, 'reports', $request );
		$this->assertFalse( isset( $reports['phpcs_wordpress-vip'] ) );
		$this->assertTrue( isset( $reports['phpcs_wordpress'] ) );
		$this->assertTrue( isset( $reports['phpcs_phpcompatibility'] ) );
		$this->assertTrue( isset( $reports['lighthouse'] ) );
		$this->assertFalse( isset( $reports['phpcs_invalid-standard'] ) );

		// Test request with `standards` param.
		$request->set_param( 'standards', 'phpcs_wordpress-vip,phpcs_wordpress,phpcs_invalid-standard,lighthouse' );
		$reports = $audit->rest_reports_get( $response, 'reports', $request );
		$this->assertFalse( isset( $reports['phpcs_wordpress-vip'] ) );
		$this->assertTrue( isset( $reports['phpcs_wordpress'] ) );
		$this->assertFalse( isset( $reports['phpcs_phpcompatibility'] ) );
		$this->assertTrue( isset( $reports['lighthouse'] ) );
		$this->assertFalse( isset( $reports['phpcs_invalid-standard'] ) );

		// Test invalid `$field_name` returns `WP_REST_Request` instance.
		$reports = $audit->rest_reports_get( $response, 'not_reports', $request );
		$this->assertInstanceOf( 'WP_REST_Request', $reports );
	}

	/**
	 * Test Audit::rest_field_project_author_get_callback.
	 *
	 * @covers ::rest_field_project_author_get_callback()
	 */
	public function test_rest_field_project_author_get_callback() {

		$audit = new Audit();

		$audit_id = $this->factory->post->create( array(
			'post_type' => 'audit',
		) );

		$request = new WP_REST_Request( 'GET', rest_url( "tide/v1/audit/{$audit_id}" ) );

		$rest_post = array(
			'id' => $audit_id,
		);

		$code_info = array(
 			'details' => array(),
  		);

		// Test invalid `$field_name` returns `WP_REST_Request` instance.
		$project_author = $audit->rest_field_project_author_get_callback( $rest_post, 'not_project_author', $request );
		$this->assertInstanceOf( 'WP_REST_Request', $project_author );

		// Test valid `$field_name` returns empty array.
		$project_author = $audit->rest_field_project_author_get_callback( $rest_post, 'project_author', $request );
		$this->assertEquals( array(), $project_author );

		// Test valid `$field_name` returns author info.
		$code_info['details'][] = array(
			'key' => 'author',
		);
		update_post_meta( $audit_id, 'code_info', $code_info );
		$project_author = $audit->rest_field_project_author_get_callback( $rest_post, 'project_author', $request );
		$this->assertEquals( array(
			'name' => '',
			'uri'  => '',
		), $project_author );

		// Test valid `$field_name` returns author info.
		$code_info['details'][] = array(
			'key'   => 'author',
			'value' => 'Test Author',
		);
		update_post_meta( $audit_id, 'code_info', $code_info );
		$project_author = $audit->rest_field_project_author_get_callback( $rest_post, 'project_author', $request );
		$this->assertEquals( array(
			'name' => 'Test Author',
			'uri'  => '',
		), $project_author );

		// Test valid `$field_name` returns author info.
		$code_info['details'][] = array(
			'key'   => 'authoruri',
			'value' => 'http://sample.otg/',
		);
		update_post_meta( $audit_id, 'code_info', $code_info );
		$project_author = $audit->rest_field_project_author_get_callback( $rest_post, 'project_author', $request );
		$this->assertEquals( array(
			'name' => 'Test Author',
			'uri'  => 'http://sample.otg/',
		), $project_author );
	}

	/**
	 * Test Audit::rest_field_project_update_callback.
	 *
	 * @covers ::rest_field_project_update_callback()
	 */
	public function test_rest_field_project_update_callback() {

		$audit = new Audit();

		$audit_id = $this->factory->post->create( array(
			'post_type' => 'audit',
		) );

		$object      = get_post( $audit_id );
		$field_value = array( 'current' );
		$request     = new WP_REST_Request( 'POST', rest_url( "tide/v1/audit/{$audit_id}" ) );
		$term        = wp_insert_term( 'previous', 'audit_project' );

		wp_set_object_terms( $object->ID, array( $term['term_id'] ), 'audit_project' );
		$this->assertTrue( has_term( 'previous', 'audit_project', $object ) );

		// Test invalid `$field_name` returns false.
		$project = $audit->rest_field_project_update_callback( $field_value, $object, 'not_project', $request, null );
		$this->assertFalse( $project );

		// Test valid `$field_name` returns true.
		$project = $audit->rest_field_project_update_callback( $field_value, $object, 'project', $request, null );
		$this->assertTrue( $project );
		$this->assertFalse( has_term( 'previous', 'audit_project', $object ) );
		$this->assertTrue( has_term( 'current', 'audit_project', $object ) );
	}
}
