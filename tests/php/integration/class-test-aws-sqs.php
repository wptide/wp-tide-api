<?php
/**
 * Test_AWS_SQS
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\Integration\AWS_SQS;

/**
 * Class Test_AWS_SQS
 *
 * @coversDefaultClass WP_Tide_API\Integration\AWS_SQS
 */
class Test_AWS_SQS extends WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var WP_Tide_API\Plugin
	 */
	public $plugin;

	/**
	 * AWS SQS.
	 *
	 * @var AWS_SQS
	 */
	public $aws_sqs;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->plugin  = WP_Tide_API\Plugin::instance();
		$this->aws_sqs = $this->plugin->components['aws_sqs'];
	}

	/**
	 * Test get_next().
	 *
	 * @covers ::get_next()
	 */
	public function test_get_next() {
		$task = array();

		$next_task = $this->aws_sqs->get_next( $task );
		$this->assertTrue( is_wp_error( $next_task ) );
		$this->assertEquals( $next_task->get_error_code(), 'sqs_get_tasks_fail' );

		$mock = $this->getMockBuilder( get_class( $this->aws_sqs ) )->getMock();

		$sqs_client = $this->_create_dummy_sqs_client_instance();

		$mock->method( 'create_sqs_client_instance' )->willReturn( $sqs_client );

		$aws_sqs = new ReflectionClass( get_class( $this->aws_sqs ) );

		$next_task = $aws_sqs->getMethod( 'get_next' )->invoke( $mock, $task );

		$this->assertArrayHasKey( 'result_message', $next_task );
		$this->assertArrayHasKey( 'queue_handle', $next_task );
		$this->assertArrayHasKey( 'message_json', $next_task );
		$this->assertArrayHasKey( 'ReceiptHandle', $next_task['result_message'] );
		$this->assertArrayHasKey( 'Body', $next_task['result_message'] );
		$this->assertEquals( $sqs_client->results['queue_handle'], $next_task['queue_handle'] );
		$this->assertEquals( $sqs_client->results['message_json'], $next_task['message_json'] );
	}

	/**
	 * Test add_task().
	 *
	 * @covers ::add_task()
	 */
	public function test_add_task() {
		$task = array();

		$next_task = $this->aws_sqs->add_task( $task );
		$this->assertTrue( is_wp_error( $next_task ) );
		$this->assertEquals( $next_task->get_error_code(), 'sqs_add_tasks_fail' );

		$mock = $this->getMockBuilder( get_class( $this->aws_sqs ) )->getMock();

		$sqs_client = $this->_create_dummy_sqs_client_instance();

		$mock->method( 'create_sqs_client_instance' )->willReturn( $sqs_client );

		$aws_sqs = new ReflectionClass( get_class( $this->aws_sqs ) );

		$this->assertEmpty( $aws_sqs->getMethod( 'add_task' )->invoke( $mock, $task ) );

		/**
		 * If $sqs_client->sendMessage was called correctly $sqs_client->queue_url should
		 * change from 'test queue url' to 'QueueUrl'.
		 */
		$this->assertEquals( $sqs_client->queue_url, 'QueueUrl' );
	}

	/**
	 * Creates dummy SqsClient object.
	 *
	 * @return object.
	 */
	public function _create_dummy_sqs_client_instance() {
		// @codingStandardsIgnoreStarts
		return new class {
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
	}
}
