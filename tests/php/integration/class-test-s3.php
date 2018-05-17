<?php
/**
 * Test_S3
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\Integration\S3;

/**
 * Class Test_S3
 *
 * @coversDefaultClass WP_Tide_API\Integration\S3
 */
class Test_S3 extends WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var WP_Tide_API\Plugin
	 */
	public $plugin;

	/**
	 * AWS S3.
	 *
	 * @var S3
	 */
	public $storage_s3;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->plugin     = WP_Tide_API\Plugin::instance();
		$this->storage_s3 = $this->plugin->components['storage_s3'];
	}

	/**
	 * Test get_url().
	 *
	 * @covers ::get_url()
	 */
	public function test_get_url() {
		$meta = array();

		$get_url = $this->storage_s3->get_url( $meta );
		$this->assertTrue( is_wp_error( $get_url ) );
		$this->assertEquals( $get_url->get_error_code(), 's3_get_url_fail' );

		$mock = $this->getMockBuilder( get_class( $this->storage_s3 ) )->getMock();

		$s3_client = $this->_create_dummy_s3_client_instance();

		$mock->method( 'create_s3_client_instance' )->willReturn( $s3_client );

		$storage_s3 = new ReflectionClass( get_class( $this->storage_s3 ) );

		$get_url = $storage_s3->getMethod( 'get_url' )->invoke( $mock, array(
   			'path'     => 'bucket',
   			'filename' => '12345',
   		) );

		$this->assertEquals( $get_url, 'http://sample.com/report.json' );
	}

	/**
	 * Test create_s3_client_instance().
	 *
	 * @covers ::create_s3_client_instance()
	 */
	public function test_create_s3_client_instance() {
		try{
			$s3_client_instance = $this->storage_s3->create_s3_client_instance();
		} catch ( \Exception $e ) {
			$this->assertEquals( $e->getMessage(), 'The s3 service does not have version: .' );
		}
	}

	/**
	 * Creates dummy S3Client object.
	 *
	 * @return object.
	 */
	public function _create_dummy_s3_client_instance() {
		// @codingStandardsIgnoreStarts
		return new class {
			function getCommand() {
				return $this;
			}

			function createPresignedRequest() {
				return $this;
			}

			function getUri() {
				return 'http://sample.com/report.json';
			}
		};
		// @codingStandardsIgnoreEnds
	}
}
