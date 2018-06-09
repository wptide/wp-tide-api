<?php
/**
 * Test_Queue_Local
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\Integration\Queue_Local;

/**
 * Class Test_Queue_Local
 *
 * @coversDefaultClass WP_Tide_API\Integration\Queue_Local
 */
class Test_Queue_Local extends WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var WP_Tide_API\Plugin
	 */
	public $plugin;

	/**
	 * MongoDB.
	 *
	 * @var Queue_Local
	 */
	public $queue_local;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->plugin      = WP_Tide_API\Plugin::instance();
		$this->queue_local = $this->plugin->components['queue_local'];
	}

	/**
	 * Test add_task().
	 *
	 * @covers ::add_task()
	 */
	public function test_add_task_phpcs_is_enabled_false() {
		$mock = $this->getMockBuilder( get_class( $this->queue_local ) )
			->setMethods( array(
				'is_enabled',
			) )
			->getMock();

		$queue_local = new ReflectionClass( get_class( $this->queue_local ) );

		$mock->expects( $this->any() )
			->method('is_enabled')
			->willReturn( false );

		// Fail for provider disabled
		$add_task = $queue_local->getMethod( 'add_task' )->invoke( $mock, array() );

		$this->assertFalse( $add_task );
	}

	/**
	 * Test add_task().
	 *
	 * @covers ::add_task()
	 */
	public function test_add_task_phpcs_failure() {
		$mock = $this->getMockBuilder( get_class( $this->queue_local ) )
			->setMethods( array(
				'is_enabled',
			) )
			->getMock();

		$queue_local = new ReflectionClass( get_class( $this->queue_local ) );

		$mock->expects( $this->any() )
			->method('is_enabled')
			->willReturn( true );

		// Fail for empty audits array.
		$add_task = $queue_local->getMethod( 'add_task' )->invoke( $mock, array() );

		$this->assertTrue( is_wp_error( $add_task ) );
		$this->assertEquals( $add_task->get_error_code(), 'local_add_tasks_fail' );
		$this->assertEquals( $add_task->get_error_data()->getMessage(), 'The audits array is empty.' );

		// Valid task.
		$task = array(
			'project_type' => 'plugin',
			'audits'       => array(
				array(
					'type'    => 'phpcs',
					'options' => array(),
				),
				array(
					'type'    => 'phpcs',
					'options' => array(),
				),
			),
		);
	}

	/**
	 * Test add_task().
	 *
	 * @covers ::add_task()
	 */
	public function test_add_task_phpcs_success() {
		define( 'MONGO_QUEUE_PHPCS', 'queue-phpcs' );

		// Valid task.
		$task = array(
			'project_type'   => 'plugin',
			'request_client' => 'wporg',
			'audits'         => array(
				array(
					'type'    => 'phpcs',
					'options' => array(),
				),
				array(
					'type'    => 'phpcs',
					'options' => array(),
				),
			),
		);

		$queue_local  = new ReflectionClass( get_class( $this->queue_local ) );
		$local_client = $this->_create_dummy_local_client_instance();

		$mock = $this->getMockBuilder( get_class( $this->queue_local ) )
			->setMethods( array(
				'is_enabled',
				'get_client_instance',
			) )
			->getMock();

		$mock->expects( $this->any() )
			->method('is_enabled')
			->willReturn( true );

		$mock->expects( $this->any() )
			->method('get_client_instance')
			->willReturn( $local_client );

		$add_task = $queue_local->getMethod( 'add_task' )->invoke( $mock, array(
			'project_type' => 'plugin',
			'audits'       => array(
				array(
					'type'    => 'bad-type',
					'options' => array(),
 				),
			),
		) );

		// Nothing gets added when the audit type is not supported.
		$this->assertFalse( is_wp_error( $add_task ) );
		$this->assertEmpty( $add_task );
		$this->assertFalse( $local_client->added );

		$add_task = $queue_local->getMethod( 'add_task' )->invoke( $mock, $task );

		$this->assertFalse( is_wp_error( $add_task ) );
		$this->assertEmpty( $add_task );
		$this->assertTrue( $local_client->added );
	}

	/**
	 * Test add_task().
	 *
	 * @covers ::add_task()
	 */
	public function test_add_task_lighthouse() {
		define( 'MONGO_QUEUE_LH', 'queue-lighthouse' );

		$mock         = $this->getMockBuilder( get_class( $this->queue_local ) )->getMock();
		$local_client = $this->_create_dummy_local_client_instance();
		$queue_local  = new ReflectionClass( get_class( $this->queue_local ) );

		$mock->method( 'get_client_instance' )->willReturn( $local_client );
		$mock->method( 'is_enabled' )->willReturn( true );

		$add_task = $queue_local->getMethod( 'add_task' )->invoke( $mock, array(
			'project_type' => 'plugin',
			'audits'       => array(
				array(
					'type'    => 'lighthouse',
					'options' => array(),
 				),
			),
		) );

		// Nothing gets added when the audit type is lighthouse and the project_type is a plugin.
		$this->assertFalse( is_wp_error( $add_task ) );
		$this->assertEmpty( $add_task );
		$this->assertFalse( $local_client->added );

		$add_task = $queue_local->getMethod( 'add_task' )->invoke( $mock, array(
			'project_type' => 'theme',
			'audits'       => array(
				array(
					'type'    => 'lighthouse',
					'options' => array(),
 				),
			),
		) );

		$this->assertFalse( is_wp_error( $add_task ) );
		$this->assertEmpty( $add_task );
		$this->assertTrue( $local_client->added );
	}

	/**
	 * Test get_client_instance().
	 *
	 * @covers ::get_client_instance()
	 */
	public function test_get_client_instance() {
		try{
			$local_client = $this->queue_local->get_client_instance();
		} catch ( \Exception $e ) {
			$this->assertEquals( $e->getMessage(), "Failed to parse MongoDB URI: 'mongodb://:@'. Invalid host string in URI." );
		}
	}

	/**
	 * Test is_enabled().
	 *
	 * @covers ::is_enabled()
	 */
	public function test_is_enabled() {
		/*
		 * We can't define `API_MESSAGE_PROVIDER` multiple times so we have to mock
		 * a true value in the tests above.
		 */
		$this->assertFalse( $this->queue_local->is_enabled() );
	}

	/**
	 * Creates dummy FirestoreClient object.
	 *
	 * @return object.
	 */
	public function _create_dummy_local_client_instance() {
		// @codingStandardsIgnoreStarts
		return new class {
			public $added = false;
			function selectDatabase() {
				return $this;
			}
			function selectCollection() {
				return $this;
			}
			function insertOne() {
				$this->added = true;
			}
		};
		// @codingStandardsIgnoreEnds
	}
}
