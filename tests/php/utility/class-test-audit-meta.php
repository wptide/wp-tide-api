<?php
/**
 * Test_Audit_Meta
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\API\Endpoint\Audit;
use WP_Tide_API\Utility\Audit_Meta;

/**
 * Class Test_Audit_Meta
 *
 * @coversDefaultClass WP_Tide_API\Utility\Audit_Meta
 *
 */
class Test_Audit_Meta extends WP_UnitTestCase {

	protected $mock_project;

	public function setUp() {
		parent::setUp();

		// Create mock project.
		$this->mock_project = $this->factory->post->create( array(
			'post_title' => 'Mock Project',
			'post_type'  => 'audit',
		) );
	}

	/**
	 * Test Audit_Meta::filter_standards.
	 *
	 * @covers ::filter_standards()
	 */
	public function test_filter_standards() {

		$tests = [
			[
				'fields' => [
					'standards' => [
						'unknown_standard',
						'another_unknown',
					],
				],
				'want'   => [],
			],
			[
				'fields' => [
					'standards' => [],
				],
				'want'   => array_keys( Audit::executable_audit_fields() ),
			],
			[
				'fields' => [
					'standards' => [
						'unknown_standard',
						'phpcs_wordpress',
					],
				],
				'want'   => [
					'phpcs_wordpress',
				],
			],

		];

		foreach ( $tests as $t ) {

			$got = Audit_Meta::filter_standards( $t['fields']['standards'] );

			// Use array_values() to reset indexes.
			$this->assertEquals( array_values( $t['want'] ), array_values( $got ) );
		}

	}

	/**
	 * Test Audit_Meta::get_filtered_standards.
	 *
	 * @covers ::get_filtered_standards()
	 */
	public function test_get_filtered_standards() {

		$tests = [
			[
				'fields' => [
					'post_id' => $this->mock_project,
				],
				'args'   => [
					'post_standards' => [],
				],
				'want'   => [],
			],
			[
				'fields' => [
					'post_id' => $this->mock_project,
				],
				'args'   => [
					'post_standards' => array_keys( Audit::executable_audit_fields() ),
				],
				'want'   => array_keys( Audit::executable_audit_fields() ),
			],
			[
				'fields' => [
					'post_id' => $this->mock_project,
				],
				'args'   => [
					'post_standards' => [
						'unknown_standard',
						'phpcs_wordpress',
					],
				],
				'want'   => [
					'phpcs_wordpress',
				],
			],
			[
				'fields' => [
					'post_id' => $this->mock_project,
				],
				'args'   => [
					'post_standards' => [
						'unknown_standard',
						'another_unknown',
					],
				],
				'want'   => [],
			],
		];

		foreach ( $tests as $t ) {

			$post_id = 0;
			if ( isset( $t['fields']['post_id'] ) ) {
				$post_id = $t['fields']['post_id'];
			}

			if ( isset( $t['args']['post_standards'] ) && isset( $t['fields']['post_id'] ) ) {
				update_post_meta( (int) $t['fields']['post_id'], 'standards', $t['args']['post_standards'] );
			}

			$got = Audit_Meta::get_filtered_standards( $post_id );

			// Use array_values() to reset indexes.
			$this->assertEquals( array_values( $t['want'] ), array_values( $got ) );
		}

	}


}