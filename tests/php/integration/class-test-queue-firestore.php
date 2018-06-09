<?php
/**
 * Test_Queue_Firestore
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\Integration\Queue_Firestore;

/**
 * Class Test_Queue_Firestore
 *
 * @coversDefaultClass WP_Tide_API\Integration\Queue_Firestore
 */
class Test_Queue_Firestore extends WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var WP_Tide_API\Plugin
	 */
	public $plugin;

	/**
	 * Firestore.
	 *
	 * @var Queue_Firestore
	 */
	public $queue_firestore;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->plugin          = WP_Tide_API\Plugin::instance();
		$this->queue_firestore = $this->plugin->components['queue_firestore'];
	}

	/**
	 * Test add_task().
	 *
	 * @covers ::add_task()
	 */
	public function test_add_task_phpcs_is_enabled_false() {
		$mock = $this->getMockBuilder( get_class( $this->queue_firestore ) )
			->setMethods( array(
				'is_enabled',
			) )
			->getMock();

		$queue_firestore = new ReflectionClass( get_class( $this->queue_firestore ) );

		$mock->expects( $this->any() )
			->method('is_enabled')
			->willReturn( false );

		// Fail for provider disabled
		$add_task = $queue_firestore->getMethod( 'add_task' )->invoke( $mock, array() );

		$this->assertFalse( $add_task );
	}

	/**
	 * Test add_task().
	 *
	 * @covers ::add_task()
	 */
	public function test_add_task_phpcs_failure() {
		$mock = $this->getMockBuilder( get_class( $this->queue_firestore ) )
			->setMethods( array(
				'is_enabled',
			) )
			->getMock();

		$queue_firestore = new ReflectionClass( get_class( $this->queue_firestore ) );

		$mock->expects( $this->any() )
			->method('is_enabled')
			->willReturn( true );

		// Fail for empty audits array.
		$add_task = $queue_firestore->getMethod( 'add_task' )->invoke( $mock, array() );

		$this->assertTrue( is_wp_error( $add_task ) );
		$this->assertEquals( $add_task->get_error_code(), 'firestore_add_tasks_fail' );
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
		define( 'GCF_QUEUE_PHPCS', 'queue-phpcs' );

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

		$queue_firestore  = new ReflectionClass( get_class( $this->queue_firestore ) );
		$firestore_client = $this->_create_dummy_firestore_client_instance();

		$mock = $this->getMockBuilder( get_class( $this->queue_firestore ) )
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
			->willReturn( $firestore_client );

		$add_task = $queue_firestore->getMethod( 'add_task' )->invoke( $mock, array(
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
		$this->assertFalse( $firestore_client->added );

		$add_task = $queue_firestore->getMethod( 'add_task' )->invoke( $mock, $task );

		$this->assertFalse( is_wp_error( $add_task ) );
		$this->assertEmpty( $add_task );
		$this->assertTrue( $firestore_client->added );
	}

	/**
	 * Test add_task().
	 *
	 * @covers ::add_task()
	 */
	public function test_add_task_lighthouse() {
		define( 'GCF_QUEUE_LH', 'queue-lighthouse' );

		$mock             = $this->getMockBuilder( get_class( $this->queue_firestore ) )->getMock();
		$firestore_client = $this->_create_dummy_firestore_client_instance();
		$queue_firestore  = new ReflectionClass( get_class( $this->queue_firestore ) );

		$mock->method( 'get_client_instance' )->willReturn( $firestore_client );
		$mock->method( 'is_enabled' )->willReturn( true );

		$add_task = $queue_firestore->getMethod( 'add_task' )->invoke( $mock, array(
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
		$this->assertFalse( $firestore_client->added );

		$add_task = $queue_firestore->getMethod( 'add_task' )->invoke( $mock, array(
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
		$this->assertTrue( $firestore_client->added );
	}

	/**
	 * Test get_client_instance().
	 *
	 * @covers ::get_client_instance()
	 */
	public function test_get_client_instance() {
		try{
			putenv("GOOGLE_APPLICATION_CREDENTIALS=/bad/path/service-account.json");
			$firestore_client = $this->queue_firestore->get_client_instance();
		} catch ( \Exception $e ) {
			$this->assertEquals( $e->getMessage(), 'Unable to read the credential file specified by  GOOGLE_APPLICATION_CREDENTIALS: file /bad/path/service-account.json does not exist' );
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
		$this->assertFalse( $this->queue_firestore->is_enabled() );
	}

	/**
	 * Creates dummy FirestoreClient object.
	 *
	 * @return object.
	 */
	public function _create_dummy_firestore_client_instance() {
		// @codingStandardsIgnoreStarts
		return new class {
			public $added = false;
			function collection() {
				return $this;
			}
			function add() {
				$this->added = true;
			}
		};
		// @codingStandardsIgnoreEnds
	}
}
