<?php
/**
 * This file is responsible for managing messages in SQS.
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\Integration;

use Aws\Sqs\SqsClient;
use WP_Tide_API\Base;
use WP_Tide_API\Utility\User;

/**
 * Class Rate_Limit
 */
class SQS extends Base {

	/**
	 * Add a new task to the SQS queue.
	 *
	 * @action tide_api_audit_tasks_add_task
	 *
	 * @param mixed $task The task to add to the queue.
	 * @return mixed|\WP_Error
	 *
	 * @throws \Exception When the audits array is empty.
	 */
	public function add_task( $task ) {
		try {
			if ( empty( $task['audits'] ) ) {
				throw new \Exception( __( 'The audits array is empty.', 'tide-api' ) );
			}

			$sqs_client = $this->create_sqs_client_instance();
			$sqs_added  = array();

			foreach ( $task['audits'] as $audit ) {
				$add_phpcs = (
					'phpcs' === $audit['type']
					&&
					defined( 'AWS_SQS_QUEUE_PHPCS' )
					&&
					AWS_SQS_QUEUE_PHPCS
					&&
					! in_array( $audit['type'], $sqs_added, true )
				);

				// Set the PHPCS queue name.
				if ( $add_phpcs ) {
					$sqs_queue   = AWS_SQS_QUEUE_PHPCS;
					$sqs_added[] = $audit['type'];
				}

				$add_lighthouse = (
					'lighthouse' === $audit['type']
					&&
					'theme' === $task['project_type']
					&&
					defined( 'AWS_SQS_QUEUE_LH' )
					&&
					AWS_SQS_QUEUE_LH
					&&
					! in_array( $audit['type'], $sqs_added, true )
				);

				// Set the Lighthouse queue name.
				if ( $add_lighthouse ) {
					$sqs_queue   = AWS_SQS_QUEUE_LH;
					$sqs_added[] = $audit['type'];
				}

				if ( empty( $sqs_queue ) ) {
					continue;
				}

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
					$data['MessageGroupId'] = $this->get_request_client( $task );
				}
				$sqs_client->sendMessage( $data );
			}
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
	 * Get the request client from the task.
	 *
	 * @param array $task The audit task.
	 *
	 * @return string The request client login name.
	 */
	public function get_request_client( $task ) {
		$request_client = $task['request_client'];

		if ( empty( $request_client ) && User::authenticated() instanceof \WP_User ) {
			$request_client = User::authenticated()->user_login;
		}

		return $request_client;
	}
}