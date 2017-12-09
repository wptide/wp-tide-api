<?php
/**
 * Test_AWS_S3
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\Integration\AWS_S3;

/**
 * Class Test_AWS_S3
 *
 * @coversDefaultClass WP_Tide_API\Integration\AWS_S3
 */
class Test_AWS_S3 extends WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var WP_Tide_API\Plugin
	 */
	public $plugin;

	/**
	 * AWS SQS.
	 *
	 * @var AWS_S3
	 */
	public $aws_s3;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->plugin = WP_Tide_API\Plugin::instance();
		$this->aws_s3 = $this->plugin->components['aws_s3'];
	}

	/**
	 * Test add_task().
	 *
	 * @covers ::get_file()
	 */
	public function test_get_file() {
		$meta = array();

		$get_file = $this->aws_s3->get_file( $meta );
		$this->assertTrue( is_wp_error( $get_file ) );
		$this->assertEquals( $get_file->get_error_code(), 's3_get_file_fail' );

		$mock = $this->getMockBuilder( get_class( $this->aws_s3 ) )->getMock();

		$s3_client = $this->_create_dummy_s3_client_instance();

		$mock->method( 'create_s3_client_instance' )->willReturn( $s3_client );

		$aws_s3 = new ReflectionClass( get_class( $this->aws_s3 ) );

		$get_file = $aws_s3->getMethod( 'get_file' )->invoke( $mock, array(
			'bucket_name' => 'test',
			'key'         => '12345',
		) );

		$this->assertObjectHasAttribute( 'totals', $get_file );
	}

	/**
	 * Creates dummy S3Client object.
	 *
	 * @return object.
	 */
	public function _create_dummy_s3_client_instance() {
		// @codingStandardsIgnoreStarts
		return new class {
			function getObject() {
				return new class {
					function get() {
						return $this;
					}

					function getContents() {
						return json_encode( array(
							'totals' => array(
								'errors'   => 100,
								'warnings' => 2774,
							),
						) );
					}
				};
			}
		};
		// @codingStandardsIgnoreEnds
	}
}
