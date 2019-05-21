<?php
/**
 * This file is responsible for managing messages in Firestore.
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\Integration;

use Google\Cloud\Firestore\FirestoreClient;
use WP_Tide_API\Base;

/**
 * Class Queue_Firestore
 */
class Queue_Firestore extends Base {

	/**
	 * Add a new task to the Firestore collection.
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

			$firestore_client      = $this->get_client_instance();
			$firestore_collections = array();

			foreach ( $task['audits'] as $audit ) {
				$audit_type   = ! empty( $audit['type'] ) ? $audit['type'] : '';
				$project_type = ! empty( $task['project_type'] ) ? $task['project_type'] : '';

				if ( empty( $firestore_collections[ $audit_type ] ) ) {

					// Set the PHPCS queue name.
					if ( 'phpcs' === $audit_type && defined( 'GCF_QUEUE_PHPCS' ) && GCF_QUEUE_PHPCS ) {
						$firestore_collections[ $audit_type ] = GCF_QUEUE_PHPCS;
					}

					// Set the Lighthouse queue name.
					if ( 'lighthouse' === $audit_type && 'theme' === $project_type && defined( 'GCF_QUEUE_LH' ) && GCF_QUEUE_LH ) {
						$firestore_collections[ $audit_type ] = GCF_QUEUE_LH;
					}

					/**
					 * Filters the Firestore collections.
					 *
					 * @param array  $firestore_collections The Firestore collections to send the messages to.
					 * @param string $audit_type            The audit type. A value like `phpcs` or `lighthouse`.
					 * @param string $project_type          The project type. Value of `theme` or `plugin`.
					 */
					$firestore_collections = apply_filters( 'tide_api_firestore_collections', $firestore_collections, $audit_type, $project_type );

					if ( empty( $firestore_collections[ $audit_type ] ) ) {
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

				$firestore_client->collection( $firestore_collections[ $audit_type ] )->add( $data );
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( 'firestore_add_tasks_fail', __( 'Failed to add Firestore tasks:', 'tide-api' ), $e );
		}
	}

	/**
	 * Create new client instance for Firestore.
	 *
	 * @return FirestoreClient
	 *
	 * @throws \Exception When the connection fails.
	 */
	public function get_client_instance() {
		$project_id = defined( 'GCP_PROJECT' ) ? GCP_PROJECT : '';

		$args = array(
			'projectId' => $project_id,
		);

		return new FirestoreClient( $args );
	}

	/**
	 * Check if the message provider is enabled.
	 *
	 * @return bool Returns true if the provider is enabled, else false.
	 */
	public function is_enabled() {
		return defined( 'API_MESSAGE_PROVIDER' ) && 'firestore' === API_MESSAGE_PROVIDER;
	}
}
