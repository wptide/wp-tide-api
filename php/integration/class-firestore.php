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
 * Class Firestore
 */
class Firestore extends Base {

	/**
	 * Message provider is enabled.
	 *
	 * @var bool
	 */
	public $enabled = false;

	/**
	 * A reference to the client.
	 *
	 * @var FirestoreClient
	 */
	private $client = false;

	/**
	 * Firebase constructor.
	 *
	 * Initializes the Firebase client.
	 *
	 * @param bool $plugin Plugin instance.
	 */
	public function __construct( $plugin = false ) {
		parent::__construct( $plugin );

		$this->init_consts();

		if ( ! defined( 'GCP_PROJECT' ) ) {
			return false;
		}

		$this->enabled = defined( 'API_MESSAGE_PROVIDER' ) && 'firestore' !== API_MESSAGE_PROVIDER;

		try {
			$this->client = new FirestoreClient( [
				'projectId' => GCP_PROJECT,
			] );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'firestore_client_fail', __( 'Failed to initiate Firestore client:', 'tide-api' ), $e );
		}
	}

	/**
	 * Sets defaults in case they were missed in wp-config.php.
	 */
	private function init_consts() {

		if ( ! defined( 'GCF_QUEUE_LH' ) ) {
			define( 'GCF_QUEUE_LH', 'queue-lighthouse' );
		}

		if ( ! defined( 'GCF_QUEUE_PHPCS' ) ) {
			define( 'GCF_QUEUE_PHPCS', 'queue-phpcs' );
		}
	}

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

		if ( ! $this->enabled || ! $this->client ) {
			return false;
		}

		try {
			if ( empty( $task['audits'] ) ) {
				throw new \Exception( __( 'The audits array is empty.', 'tide-api' ) );
			}

			$firestore_collections = array();

			foreach ( $task['audits'] as $audit ) {
				$audit_type   = ! empty( $audit['type'] ) ? $audit['type'] : '';
				$project_type = ! empty( $task['project_type'] ) ? $task['project_type'] : '';

				if ( empty( $firestore_collections[ $audit_type ] ) ) {

					// Set the PHPCS queue name.
					if ( 'phpcs' === $audit_type ) {
						$firestore_collections[ $audit_type ] = GCF_QUEUE_PHPCS;
					}

					// Set the Lighthouse queue name.
					if ( 'lighthouse' === $audit_type ) {
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

				$this->client->collection( $firestore_collections[ $audit_type ] )->add( $data );
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( 'firestore_add_tasks_fail', __( 'Failed to add Firestore tasks:', 'tide-api' ), $e );
		}
	}
}
