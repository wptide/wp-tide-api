<?php
/**
 * This file is responsible for the managing of JSON Web Tokens (JWT).
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\Integration;

use Aws\Sqs\SqsClient;
use WP_Tide_API\Base;

/**
 * Class Rate_Limit
 */
class AWS_SQS extends Base {

	/**
	 * Get Audit Tasks
	 *
	 * @filter tide_api_audit_tasks_get_tasks 10
	 *
	 * @param mixed $task The tasks to perform.
	 * @return mixed|\WP_Error
	 */
	public function get_next( $task ) {
		try {
			$sqs_client = $this->create_sqs_client_instance();

			// Get the queue URL from the queue name.
			$sqs_queue = defined( 'AWS_SQS_QUEUE_PHPCS' ) ? AWS_SQS_QUEUE_PHPCS : '';
			$result    = $sqs_client->getQueueUrl( array(
				'QueueName' => $sqs_queue,
			) );
			$queue_url = $result->get( 'QueueUrl' );

			// Receive a message from the queue.
			$data = array(
				'QueueUrl' => $queue_url,
			);

			$result = $sqs_client->receiveMessage( $data );

			// Nothing to return.
			if ( null === $result['Messages'] ) {
				return $task;
			}

			// Get the message information.
			$result_message = array_pop( $result['Messages'] );
			$queue_handle   = $result_message['ReceiptHandle'];
			$message_json   = $result_message['Body'];

			$sqs_client->deleteMessage( array(
				'QueueUrl'      => $queue_url,
				'ReceiptHandle' => $queue_handle,
			) );

			return array(
				'result_message' => $result_message,
				'queue_handle'   => $queue_handle,
				'message_json'   => $message_json,
			);
		} catch ( \Exception $e ) {

			return new \WP_Error( 'sqs_get_tasks_fail', __( 'Failed to get SQS tasks:', 'tide-api' ), $e );
		} // End try().
	}

	/**
	 * Add a new task to the SQS queue.
	 *
	 * @action tide_api_audit_tasks_add_task
	 *
	 * @param mixed $task The task to add to the queue.
	 * @return void|\WP_Error
	 */
	public function add_task( $task ) {
		try {
			$sqs_client = $this->create_sqs_client_instance();

			// Get the queue URL from the queue name.
			$sqs_queue = defined( 'AWS_SQS_QUEUE_PHPCS' ) ? AWS_SQS_QUEUE_PHPCS : '';
			$result    = $sqs_client->getQueueUrl( array(
				'QueueName' => $sqs_queue,
			) );
			$queue_url = $result->get( 'QueueUrl' );

			// Send the message.
			$data = array(
				'QueueUrl'    => $queue_url,
				'MessageBody' => wp_json_encode( $task ),
			);

			if ( preg_match( '/.fifo$/', $sqs_queue ) ) {
				$data['MessageGroupId'] = $this->get_message_group_id( $task );
			}
			$sqs_client->sendMessage( $data );

		} catch ( \Exception $e ) {

			return new \WP_Error( 'sqs_add_tasks_fail', __( 'Failed to add SQS tasks:', 'tide-api' ), $e );
		} // End try().
	}

	/**
	 * Create new instance for SqsClient.
	 *
	 * @return SqsClient
	 */
	public function create_sqs_client_instance() {
		return new SqsClient( array(
			'idempotency_auto_fill' => true,
			'version'               => defined( 'AWS_SQS_VERSION' ) ? AWS_SQS_VERSION : '',
			'region'                => defined( 'AWS_SQS_REGION' ) ? AWS_SQS_REGION : '',
			'credentials'           => array(
				'key'    => defined( 'AWS_API_KEY' ) ? AWS_API_KEY : '',
				'secret' => defined( 'AWS_API_SECRET' ) ? AWS_API_SECRET : '',
			),
		) );
	}

	/**
	 * Get the MessageGroupId for SQS.
	 *
	 * @param array $task The audit task.
	 *
	 * @return string The MessageGroupId.
	 */
	private function get_message_group_id( $task ) {
		// Generate MessageGroupId for FIFO queues.
		$request_client = $task['request_client'];
		$slug           = $task['slug'] ?? '';

		if ( empty( $slug ) ) {
			$slug = str_replace( ' ', '', strtolower( $task['title'] ) );
		}

		return sprintf( '%s-%s', $request_client, $slug );
	}
}
