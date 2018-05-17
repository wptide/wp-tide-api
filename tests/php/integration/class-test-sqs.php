<?php
/**
 * Test_SQS
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\Integration\SQS;

/**
 * Class Test_SQS
 *
 * @coversDefaultClass WP_Tide_API\Integration\SQS
 */
class Test_SQS extends WP_UnitTestCase {

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
	public $queue_sqs;

	/**
	 * User ID.
	 *
	 * @var int
	 */
	protected static $user_id = 0;

	/**
	 * Setup before class.
	 *
	 * @inheritdoc
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create( array( 'user_login' => 'foo' ) );
	}

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->plugin    = WP_Tide_API\Plugin::instance();
		$this->queue_sqs = $this->plugin->components['queue_sqs'];
	}

	/**
	 * Test add_task().
	 *
	 * @covers ::add_task()
	 */
	public function test_add_task() {
		define( 'AWS_SQS_QUEUE_PHPCS', 'queue-name.fifo' );
		$task = array();

		$next_task = $this->queue_sqs->add_task( $task );
		$this->assertTrue( is_wp_error( $next_task ) );
		$this->assertEquals( $next_task->get_error_code(), 'sqs_add_tasks_fail' );

		$mock = $this->getMockBuilder( get_class( $this->queue_sqs ) )->getMock();

		$sqs_client = $this->_create_dummy_sqs_client_instance();

		$mock->method( 'create_sqs_client_instance' )->willReturn( $sqs_client );

		$queue_sqs = new ReflectionClass( get_class( $this->queue_sqs ) );
		$add_task = $queue_sqs->getMethod( 'add_task' )->invoke( $mock, $task );

		$this->assertEmpty( $add_task );

		/**
		 * If $sqs_client->sendMessage was called correctly $sqs_client->queue_url should
		 * change from 'test queue url' to 'QueueUrl'.
		 */
		$this->assertEquals( $sqs_client->queue_url, 'QueueUrl' );
	}

	/**
	 * Test create_sqs_client_instance().
	 *
	 * @covers ::create_sqs_client_instance()
	 */
	public function test_create_sqs_client_instance() {
		try{
			$sqs_client_instance = $this->queue_sqs->create_sqs_client_instance();
		} catch ( \Exception $e ) {
			$this->assertEquals( $e->getMessage(), 'The sqs service does not have version: .' );
		}
	}

	/**
	 * Test get_request_client().
	 *
	 * @covers ::get_request_client()
	 */
	public function test_get_request_client() {
		$user = wp_set_current_user( self::$user_id );
		$request_client = $this->queue_sqs->get_request_client( [
			'request_client' => '',
		] );
		$this->assertEquals( $user->user_login, $request_client );
	}

	/**
	 * Creates dummy SqsClient object.
	 *
	 * @return object.
	 */
	public function _create_dummy_sqs_client_instance() {
		return new DummyClient();
	}
}

// @codingStandardsIgnoreStarts
class DummyClient{
	public $results = array(
		'result_message' => 'result message',
		'queue_handle' => 'queue handle',
		'message_json' => 'message json',
	);

	public $queue_url = 'test queue url';

	function getQueueUrl( $queue_url ) {
		$this->queue_url = $queue_url;
		return $this;
	}

	function receiveMessage() {
		return array(
			'Messages' => array(
				array(
					'ReceiptHandle' => $this->results['queue_handle'],
					'Body' => $this->results['message_json'],
				),
			),
		);
	}

	function sendMessage( $array ) {
		$this->queue_url = $array['QueueUrl'];
		return $this;
	}

	function get( $queue_url ) {
		return $queue_url;
	}

	function deleteMessage( $array ) {
		return $array;
	}
};
// @codingStandardsIgnoreEnds
