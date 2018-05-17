<?php
/**
 * Test_GCS
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\Integration\GCS;

/**
 * Class Test_SQS
 *
 * @coversDefaultClass WP_Tide_API\Integration\GCS
 */
class Test_GCS extends WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var WP_Tide_API\Plugin
	 */
	public $plugin;

	/**
	 * AWS SQS.
	 *
	 * @var SQS
	 */
	public $storage_gcs;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->plugin      = WP_Tide_API\Plugin::instance();
		$this->storage_gcs = $this->plugin->components['storage_gcs'];
	}

	/**
	 * Test get_url().
	 *
	 * @covers ::get_url()
	 */
	public function test_get_url() {
		$meta = array();

		$get_url = $this->storage_gcs->get_url( $meta );
		$this->assertTrue( is_wp_error( $get_url ) );
		$this->assertEquals( $get_url->get_error_code(), 'gcs_get_url_fail' );

		$mock = $this->getMockBuilder( get_class( $this->storage_gcs ) )->getMock();

		$storage_client = $this->_create_dummy_storage_client();

		$mock->method( 'create_gcs_client_instance' )->willReturn( $storage_client );

		$storage_client = new ReflectionClass( get_class( $this->storage_gcs ) );

		$get_url = $storage_client->getMethod( 'get_url' )->invoke( $mock, array(
   			'path'     => 'bucket',
   			'filename' => '12345',
   		) );

		$this->assertEquals( $get_url, 'http://sample.com/report.json' );
	}

	/**
	 * Test create_gcs_client_instance().
	 *
	 * @covers ::create_gcs_client_instance()
	 */
	public function test_create_gcs_client_instance() {
		try{
			putenv("GOOGLE_APPLICATION_CREDENTIALS=/bad/path/service-account.json");
			$gcs_client = $this->storage_gcs->create_gcs_client_instance();
		} catch ( \Exception $e ) {
			$this->assertEquals( $e->getMessage(), 'Unable to read the credential file specified by  GOOGLE_APPLICATION_CREDENTIALS: file /bad/path/service-account.json does not exist' );
		}
	}

	/**
	 * Creates dummy StorageClient object.
	 *
	 * @return object.
	 */
	public function _create_dummy_storage_client() {
		// @codingStandardsIgnoreStarts
		return new class {
			function bucket() {
				return $this;
			}

			function object() {
				return $this;
			}

			function signedUrl() {
				return 'http://sample.com/report.json';
			}
		};
		// @codingStandardsIgnoreEnds
	}
}
