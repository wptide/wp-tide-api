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

		$results = array(
			'phpcs_wordpress' => 'test_value',
		);

		$audit->rest_reports_update( $results, $audit_post, 'results', null, null );

		$this->assertEquals( 'test_value', get_post_meta( $audit_id, '_audit_phpcs_wordpress', true ) );
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
		$results = $audit->rest_reports_get( $response, 'results', $request );
		$this->assertTrue( isset( $results['phpcs_wordpress'] ) );
		$this->assertTrue( isset( $results['phpcs_phpcompatibility'] ) );
		$this->assertTrue( isset( $results['lighthouse'] ) );
		$this->assertFalse( isset( $results['phpcs_invalid-standard'] ) );

		update_post_meta( $audit_id, 'standards', array(
			'phpcs_wordpress',
			'phpcs_phpcompatibility',
			'lighthouse',
		) );

		// Test request without `standards` param but the "standards" meta is set.
		$results = $audit->rest_reports_get( $response, 'results', $request );
		$this->assertTrue( isset( $results['phpcs_wordpress'] ) );
		$this->assertTrue( isset( $results['phpcs_phpcompatibility'] ) );
		$this->assertTrue( isset( $results['lighthouse'] ) );
		$this->assertFalse( isset( $results['phpcs_invalid-standard'] ) );

		// Test request with `standards` param.
		$request->set_param( 'standards', 'phpcs_wordpress,phpcs_invalid-standard,lighthouse' );
		$results = $audit->rest_reports_get( $response, 'results', $request );
		$this->assertTrue( isset( $results['phpcs_wordpress'] ) );
		$this->assertFalse( isset( $results['phpcs_phpcompatibility'] ) );
		$this->assertTrue( isset( $results['lighthouse'] ) );
		$this->assertFalse( isset( $results['phpcs_invalid-standard'] ) );
	}
}
