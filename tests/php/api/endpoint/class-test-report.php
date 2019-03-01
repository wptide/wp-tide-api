<?php
/**
 * Test_Audit
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\API\Endpoint\Audit;

/**
 * Class Test_Report
 *
 * @coversDefaultClass WP_Tide_API\API\Endpoint\Report
 */
class Test_Report extends WP_UnitTestCase {

	/**
	 * Test REST Server
	 *
	 * @var \WP_REST_Server
	 */
	protected $server;

	protected $mock_project;
	protected $mock_fail_project;
	protected $mock_invalid_project;

	const REPORT_ROUTE = '/tide/v1/report';

	public function setUp() {
		parent::setUp();

		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		$this->server = $wp_rest_server = new \WP_REST_Server;
		do_action( 'rest_api_init' );

		// Create API user with limited caps.
		$user_id = $this->factory->user->create( array(
			'user_login' => 'user_with_caps',
		) );
		$user    = new WP_User( $user_id );
		$user->add_cap( 'audit_client' );

		// Create API user with tide_audit caps.
		$user_id = $this->factory->user->create( array(
			'user_login' => 'user_without_caps',
		) );
		$user    = new WP_User( $user_id );
		$user->add_cap( 'api_client' );

		// Create mock project.
		$this->mock_project = $this->factory->post->create( array(
			'post_title' => 'Mock Project',
			'post_type'  => 'audit',
		) );
		update_post_meta( $this->mock_project, '_audit_mock_standard', [
			'raw' => [
				'type'     => 's3',
				'path'     => 'mock_bucket',
				'filename' => 'mock_report.json',
			],
		] );
		update_post_meta( $this->mock_project, 'checksum', '39c7d71a68565ddd7b6a0fd68d94924d0db449a99541439b3ab8a477c5f1fc4e' );

		// Create failed mock project.
		$this->mock_fail_project = $this->factory->post->create( array(
			'post_title' => 'Failed Mock Project',
			'post_type'  => 'audit',
		) );
		update_post_meta( $this->mock_fail_project, '_audit_mock_standard', [
			'raw' => [
				'type'     => 's3',
				'path'     => 'error',
				'filename' => 'error',
			],
		] );

		// Create mock project.
		$this->mock_invalid_project = $this->factory->post->create( array(
			'post_title' => 'Invalid Audit Project',
		) );
		update_post_meta( $this->mock_invalid_project, 'checksum', '333333333333333333330fd68d94924d0db449a99541439b3ab8a477c5f1fc4e' );
	}

	/**
	 * Test Report::register_routes.
	 *
	 * @covers ::register_routes()
	 */
	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( static::REPORT_ROUTE, $routes );
	}

	/**
	 * Test Report::report_error.
	 *
	 * @covers ::report_error()
	 */
	public function test_report_error() {
		$report = new \WP_Tide_API\API\Endpoint\Report();

		$tests = [
			[
				'fields'     => [
					'code'    => 'error_message',
					'message' => 'Error message.',
					'status'  => 200,
				],
				'want'       => [
					'code'    => 'error_message',
					'message' => 'Error message.',
					'data'    => [
						'status' => 200,
					],
				],
				'want_error' => false,
			],
			[
				'fields'     => [
					'code'    => 'not_matching_code',
					'message' => 'Error message.',
					'status'  => 200,
				],
				'want'       => [
					'code'    => 'error_message',
					'message' => 'Error message.',
					'data'    => [
						'status' => 200,
					],
				],
				'want_error' => true,
			],
		];

		foreach ( $tests as $test ) {
			$code    = $test['fields']['code'];
			$message = $test['fields']['message'];
			$status  = $test['fields']['status'];

			$got = $report->report_error( $code, $message, $status );

			if ( $test['want_error'] ) {
				$this->assertNotEquals( $test['want'], $got );
			} else {
				$this->assertEquals( $test['want'], $got );
			}
		}

	}

	/**
	 * Test Report::report_response.
	 *
	 * @covers ::report_response()
	 */
	public function test_report_response() {
		$report = new \WP_Tide_API\API\Endpoint\Report();

		$tests = [
			[
				'fields' => [
					'request' => new \WP_REST_Request(
						'GET',
						static::REPORT_ROUTE,
						null
					),
				],
				'attr'   => [],
				'want'   => $report->report_error(
					'unauthenticated_call',
					'Unauthenticated report request',
					301
				),
			],
			[
				'fields' => [
					'request' => new \WP_REST_Request(
						'GET',
						static::REPORT_ROUTE,
						null
					),
				],
				'attr'   => [
					'user_login' => 'user_with_caps',
				],
				'want'   => $report->report_error(
					'report_type_not_found',
					'Could not retrieve report for type',
					500
				),
			],
			[
				'fields' => [
					'request' => new \WP_REST_Request(
						'GET',
						static::REPORT_ROUTE,
						null
					),
				],
				'attr'   => [
					'user_login' => 'user_with_caps',
					'type'       => 'raw',
				],
				'want'   => $report->report_error(
					'report_standard_not_found',
					'Could not retrieve report for standard',
					404
				),
			],
			[
				'fields' => [
					'request' => new \WP_REST_Request(
						'GET',
						static::REPORT_ROUTE,
						null
					),
				],
				'attr'   => [
					'user_login'   => 'user_with_caps',
					'post_id'      => $this->mock_project,
					'standard'     => 'mock_standard',
					'type'         => 'raw',
					'source_error' => true,
				],
				'want'   => $report->report_error(
					'report_source_error',
					'Could not retrieve report from source',
					404
				),
			],
			[
				'fields' => [
					'request' => new \WP_REST_Request(
						'GET',
						static::REPORT_ROUTE,
						null
					),
				],
				'attr'   => [
					'user_login'   => 'user_with_caps',
					'post_id'      => $this->mock_project,
					'standard'     => 'mock_standard',
					'type'         => 'raw',
					'unset_expire' => true,
				],
				'want'   => [
					'rel' => 'download',
					'url' => 'http://test.local/',
				],
			],
			[
				'fields' => [
					'request' => new \WP_REST_Request(
						'GET',
						static::REPORT_ROUTE,
						null
					),
				],
				'attr'   => [
					'user_login'   => 'user_with_caps',
					'checksum'     => '39c7d71a68565ddd7b6a0fd68d94924d0db449a99541439b3ab8a477c5f1fc4e',
					'standard'     => 'mock_standard',
					'type'         => 'raw',
					'unset_expire' => true,
				],
				'want'   => [
					'rel' => 'download',
					'url' => 'http://test.local/',
				],
			],
			[
				'fields' => [
					'request' => new \WP_REST_Request(
						'GET',
						static::REPORT_ROUTE,
						null
					),
				],
				'attr'   => [
					'user_login'   => 'user_with_caps',
					'checksum'     => '333333333333333333330fd68d94924d0db449a99541439b3ab8a477c5f1fc4e',
					'standard'     => 'mock_standard',
					'type'         => 'raw',
					'unset_expire' => true,
				],
				'want'   => $report->report_error(
					'report_error',
					'Error occurred in report api',
					500
				),
			],
			[
				'fields' => [
					'request' => new \WP_REST_Request(
						'GET',
						static::REPORT_ROUTE,
						null
					),
				],
				'attr'   => [
					'user_login' => 'user_with_caps',
					'post_id'    => $this->mock_fail_project,
					'standard'   => 'mock_standard',
					'type'         => 'raw',
				],
				'want'   => $report->report_error(
					'report_fetch_error',
					'resource failed',
					500
				),
			],
		];

		foreach ( $tests as $test ) {

			// Use Mock_Storage provider.
			$report->plugin->components['storage_s3'] = new Mock_Storage();

			$request = $test['fields']['request'];

			// Sign in user if provided.
			wp_logout();
			if ( ! empty( $test['attr']['user_login'] ) ) {
				$user = wp_signon( [
					'user_login'    => $test['attr']['user_login'],
					'user_password' => 'password',
				] );
				if ( ! is_wp_error( $user ) ) {
					wp_set_current_user( $user->ID );
				}
			}

			if ( ! empty( $test['attr']['post_id'] ) ) {
				$request->set_param( 'post_id', $test['attr']['post_id'] );
			}

			if ( ! empty( $test['attr']['checksum'] ) ) {
				$request->set_param( 'checksum', $test['attr']['checksum'] );
			}

			if ( ! empty( $test['attr']['standard'] ) ) {
				$request->set_param( 'standard', $test['attr']['standard'] );
			}

			if ( ! empty( $test['attr']['type'] ) ) {
				$request->set_param( 'type', $test['attr']['type'] );
			}

			if ( ! empty( $test['attr']['source_error'] ) ) {
				$report->plugin->components['storage_s3'] = '';
			}

			$got = $report->report_response( $request );

			// Can't mock an exact time, so just unset the expires entry.
			if ( ! empty( $test['attr']['unset_expire'] ) && ! empty( $got->data['expires'] ) ) {
				unset( $got->data['expires'] );
			}

			$this->assertEquals( $test['want'], $got->data );
		}

	}

}

class Mock_Storage {

	public function get_url( $meta ) {

		if ( 'error' === $meta['filename'] ) {
			return new \WP_Error( 'mock_fail_error', 'resource failed', 500 );
		}

		return 'http://test.local/';
	}

}