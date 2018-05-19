<?php
/**
 * Test_Local
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\Integration\Local;

/**
 * Class Test_Local
 *
 * @coversDefaultClass WP_Tide_API\Integration\Local
 */
class Test_Local extends WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var WP_Tide_API\Plugin
	 */
	public $plugin;

	/**
	 * Local.
	 *
	 * @var Local
	 */
	public $storage_local;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->plugin        = WP_Tide_API\Plugin::instance();
		$this->storage_local = $this->plugin->components['storage_local'];
	}

	/**
	 * Test get_url().
	 *
	 * @covers ::get_url()
	 */
	public function test_get_url_empty_path() {
		$meta = array(
			'path'     => '',
			'filename' => 'report.json',
			'type'     => 'local',
		);

		$get_file = $this->storage_local->get_url( $meta );
		$this->assertTrue( is_wp_error( $get_file ) );
		$this->assertEquals( $get_file->get_error_code(), 'local_get_url_fail' );
		$this->assertEquals( $get_file->get_error_message(), 'The path parameter cannot be empty.' );
	}

	/**
	 * Test get_url().
	 *
	 * @covers ::get_url()
	 */
	public function test_get_url_empty_filename() {
		$meta = array(
			'path'     => 'phpcs',
			'filename' => '',
			'type'     => 'local',
		);

		$get_file = $this->storage_local->get_url( $meta );
		$this->assertTrue( is_wp_error( $get_file ) );
		$this->assertEquals( $get_file->get_error_code(), 'local_get_url_fail' );
		$this->assertEquals( $get_file->get_error_message(), 'The filename parameter cannot be empty.' );
	}

	/**
	 * Test get_url().
	 *
	 * @covers ::get_url()
	 */
	public function test_get_url_invalid_type() {
		$meta = array(
			'path'     => 'phpcs',
			'filename' => 'report.json',
			'type'     => 's3',
		);

		$get_file = $this->storage_local->get_url( $meta );
		$this->assertTrue( is_wp_error( $get_file ) );
		$this->assertEquals( $get_file->get_error_code(), 'local_get_url_fail' );
		$this->assertEquals( $get_file->get_error_message(), 'The type parameter must be local.' );
	}

	/**
	 * Test get_url().
	 *
	 * @covers ::get_url()
	 */
	public function test_get_url_missing_file() {
		$meta = array(
			'path'     => 'phpcs',
			'filename' => 'report.json',
			'type'     => 'local',
		);

		$get_file = $this->storage_local->get_url( $meta );
		$this->assertTrue( is_wp_error( $get_file ) );
		$this->assertEquals( $get_file->get_error_code(), 'local_get_url_fail' );
		$this->assertEquals( $get_file->get_error_message(), 'The file does not exist on the host.' );
	}

	/**
	 * Test get_url().
	 *
	 * @covers ::get_url()
	 */
	public function test_get_url_empty_basedir() {
		$meta = array(
			'path'     => 'phpcs',
			'filename' => 'report.json',
			'type'     => 'local',
		);

		$filter_upload_dir = function( $uploads ) {
			return array(
				'path'    => '/app/wp-content/uploads/2018/05',
				'url'     => 'http://tide.local/wp-content/uploads/2018/05',
				'subdir'  => '/2018/05',
				'basedir' => '',
				'baseurl' => 'http://tide.local/wp-content/uploads',
				'error'   => false,
			);
		};

		add_filter( 'upload_dir', $filter_upload_dir );
		$get_file = $this->storage_local->get_url( $meta );
		$this->assertTrue( is_wp_error( $get_file ) );
		$this->assertEquals( $get_file->get_error_code(), 'local_get_url_fail' );
		$this->assertEquals( $get_file->get_error_message(), 'The uploads basedir cannot be empty.' );
		remove_filter( 'upload_dir', $filter_upload_dir );
	}

	/**
	 * Test get_url().
	 *
	 * @covers ::get_url()
	 */
	public function test_get_url_empty_baseurl() {
		$meta = array(
			'path'     => 'phpcs',
			'filename' => 'report.json',
			'type'     => 'local',
		);

		$filter_upload_dir = function( $uploads ) {
			return array(
				'path'    => '/app/wp-content/uploads/2018/05',
				'url'     => 'http://tide.local/wp-content/uploads/2018/05',
				'subdir'  => '/2018/05',
				'basedir' => '/app/wp-content/uploads',
				'baseurl' => '',
				'error'   => false,
			);
		};

		add_filter( 'upload_dir', $filter_upload_dir );
		$get_file = $this->storage_local->get_url( $meta );
		$this->assertTrue( is_wp_error( $get_file ) );
		$this->assertEquals( $get_file->get_error_code(), 'local_get_url_fail' );
		$this->assertEquals( $get_file->get_error_message(), 'The uploads baseurl cannot be empty.' );
		remove_filter( 'upload_dir', $filter_upload_dir );
	}

	/**
	 * Test get_url().
	 *
	 * @covers ::get_url()
	 */
	public function test_get_url() {
		$meta = array(
			'path'     => 'phpcs',
			'filename' => 'report.json',
			'type'     => 'local',
		);

		$filter_upload_dir = function( $uploads ) {
			return array(
				'path'    => '',
				'url'     => '',
				'subdir'  => '',
				'basedir' =>  __DIR__,
				'baseurl' => 'http://tide.local/wp-content/uploads',
				'error'   => false,
			);
		};

		add_filter( 'upload_dir', $filter_upload_dir );
		$get_file = $this->storage_local->get_url( $meta );
		$this->assertFalse( is_wp_error( $get_file ) );
		$this->assertEquals( $get_file, 'http://tide.local/wp-content/uploads/phpcs/report.json' );
		remove_filter( 'upload_dir', $filter_upload_dir );
	}
}
