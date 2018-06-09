<?php
/**
 * This file is responsible for managing messages in MongoDB.
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\Integration;

use MongoDB\Client;
use WP_Tide_API\Base;

/**
 * Class Queue_Local
 */
class Queue_Local extends Base {

	/**
	 * Add a new task to the Mongo collection.
	 *
	 * @action tide_api_audit_tasks_add_task
	 *
	 * @param mixed $task The task to add to the queue.
	 *
	 * @return mixed|\WP_Error
	 *
	 * @throws \Exception When the audits array is empty.
	 */
	public function add_task( $task ) {
		try {
			if ( ! $this->is_enabled() ) {
				return false;
			}

			if ( empty( $task['audits'] ) ) {
				throw new \Exception( __( 'The audits array is empty.', 'tide-api' ) );
			}

			$mongo_client      = $this->get_client_instance();
			$mongo_collections = array();

			foreach ( $task['audits'] as $audit ) {
				$audit_type   = ! empty( $audit['type'] ) ? $audit['type'] : '';
				$project_type = ! empty( $task['project_type'] ) ? $task['project_type'] : '';

				if ( empty( $mongo_collections[ $audit_type ] ) ) {

					// Set the PHPCS queue name.
					if ( 'phpcs' === $audit_type && defined( 'MONGO_QUEUE_PHPCS' ) && MONGO_QUEUE_PHPCS ) {
						$mongo_collections[ $audit_type ] = MONGO_QUEUE_PHPCS;
					}

					// Set the Lighthouse queue name.
					if ( 'lighthouse' === $audit_type && 'theme' === $project_type && defined( 'MONGO_QUEUE_LH' ) && MONGO_QUEUE_LH ) {
						$mongo_collections[ $audit_type ] = MONGO_QUEUE_LH;
					}

					/**
					 * Filters the local MongoDB collections.
					 *
					 * @param array  $mongo_collections The MongoDB collections to send the messages to.
					 * @param string $audit_type        The audit type. A value like `phpcs` or `lighthouse`.
					 * @param string $project_type      The project type. Value of `theme` or `plugin`.
					 */
					$mongo_collections = apply_filters( 'tide_api_local_collections', $mongo_collections, $audit_type, $project_type );

					if ( empty( $mongo_collections[ $audit_type ] ) ) {
						continue;
					}
				} else {
					continue;
				}

				$data = [
					'created'         => time(),
					'lock'            => 0,
					'retries'         => 3,
					'message'         => $task,
					'status'          => 'pending',
					'retry_available' => true,
				];

				$mongo_client
					->selectDatabase( defined( 'MONGO_DATABASE_NAME' ) ? MONGO_DATABASE_NAME : '' )
					->selectCollection( $mongo_collections[ $audit_type ] )
					->insertOne( $data );
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( 'local_add_tasks_fail', __( 'Failed to add MongoDB tasks:', 'tide-api' ), $e );
		}
	}

	/**
	 * Create new client instance for MongoDB.
	 *
	 * @return Client
	 *
	 * @throws \Exception When the connection fails.
	 */
	public function get_client_instance() {
		$host = sprintf(
			'mongodb://%s:%s@%s',
			defined( 'MONGO_DATABASE_USERNAME' ) ? MONGO_DATABASE_USERNAME : '',
			defined( 'MONGO_DATABASE_PASSWORD' ) ? MONGO_DATABASE_PASSWORD : '',
			defined( 'MONGO_HOST' ) ? MONGO_HOST : ''
		);

		return new Client( $host );
	}

	/**
	 * Check if the message provider is enabled.
	 *
	 * @return bool Returns true if the provider is enabled, else false.
	 */
	public function is_enabled() {
		return defined( 'API_MESSAGE_PROVIDER' ) && 'local' === API_MESSAGE_PROVIDER;
	}
}
