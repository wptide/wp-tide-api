<?php
/**
 * This file is responsible for managing messages in MongoDB.
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\Integration;

use MongoDB\Client;
use MongoDB\Collection;
use WP_Tide_API\Base;

/**
 * Class Mongo
 */
class Mongo extends Base {

	/**
	 * A reference to the client.
	 *
	 * @var Client
	 */
	private $client = false;

	/**
	 * Mongo constructor.
	 *
	 * Initializes the MongoDB client.
	 *
	 * @param bool $plugin Plugin instance.
	 */
	public function __construct( $plugin = false ) {
		parent::__construct( $plugin );

		$this->init_consts();

		if ( '' !== MONGO_DATABASE_USERNAME && '' !== MONGO_DATABASE_PASSWORD ) {
			$host = sprintf( 'mongodb://%s:%s@%s', MONGO_DATABASE_USERNAME, MONGO_DATABASE_PASSWORD, MONGO_HOST );
		} else {
			$host = sprintf( 'mongodb://%s', MONGO_HOST );
		}

		$this->client = new Client( $host );
	}

	/**
	 * Sets defaults in case they were missed in wp-config.php.
	 */
	private function init_consts() {

		if ( ! defined( 'MONGO_DATABASE_NAME' ) ) {
			define( 'MONGO_DATABASE_NAME', 'queue' );
		}

		if ( ! defined( 'MONGO_DATABASE_PASSWORD' ) ) {
			define( 'MONGO_DATABASE_PASSWORD', 'root' );
		}

		if ( ! defined( 'MONGO_DATABASE_USERNAME' ) ) {
			define( 'MONGO_DATABASE_USERNAME', 'root' );
		}

		if ( ! defined( 'MONGO_HOST' ) ) {
			define( 'MONGO_HOST', '127.0.0.1:27017' );
		}

		if ( ! defined( 'MONGO_QUEUE_LH' ) ) {
			define( 'MONGO_QUEUE_LH', 'lighthouse' );
		}

		if ( ! defined( 'MONGO_QUEUE_PHPCS' ) ) {
			define( 'MONGO_QUEUE_PHPCS', 'phpcs' );
		}
	}

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

		// If mongo is not the API_MESSAGE_PROVIDER then do nothing.
		if ( ! defined( 'API_MESSAGE_PROVIDER' ) || ( 'local' !== API_MESSAGE_PROVIDER && 'mongo' !== API_MESSAGE_PROVIDER ) ) {
			return false;
		}

		try {
			if ( empty( $task['audits'] ) ) {
				throw new \Exception( __( 'The audits array is empty.', 'tide-api' ) );
			}

			$mongo_collections = array();

			foreach ( $task['audits'] as $audit ) {
				$audit_type   = ! empty( $audit['type'] ) ? $audit['type'] : '';
				$project_type = ! empty( $task['project_type'] ) ? $task['project_type'] : '';

				if ( empty( $mongo_collections[ $audit_type ] ) ) {

					// Set the PHPCS queue name.
					if ( 'phpcs' === $audit_type ) {
						$mongo_collections[ $audit_type ] = MONGO_QUEUE_PHPCS;
					}

					// Set the Lighthouse queue name.
					if ( 'lighthouse' === $audit_type ) {
						$mongo_collections[ $audit_type ] = MONGO_QUEUE_LH;
					}

					/**
					 * Filters the MongoDB collections.
					 *
					 * @param array  $mongo_collections The MongoDB collections to send the messages to.
					 * @param string $audit_type        The audit type. A value like `phpcs` or `lighthouse`.
					 * @param string $project_type      The project type. Value of `theme` or `plugin`.
					 */
					$mongo_collections = apply_filters( 'tide_api_mongo_collections', $mongo_collections, $audit_type, $project_type );

					if ( empty( $mongo_collections[ $audit_type ] ) ) {
						continue;
					}
				} else {
					continue;
				}

				$collection = $this->mongo_collection( MONGO_DATABASE_NAME, $mongo_collections[ $audit_type ] );

				$data = [
					'created'         => time(),
					'lock'            => 0,
					'retries'         => 3,
					'message'         => $task,
					'status'          => 'pending',
					'retry_available' => true,
				];

				$collection->insertOne( $data );
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( 'mongo_add_tasks_fail', __( 'Failed to add MongoDB tasks:', 'tide-api' ), $e );
		}
	}


	/**
	 * Get the MongoDB Collection.
	 *
	 * @param string $db         Name of the database.
	 * @param string $collection Name of the collection.
	 *
	 * @return Collection|\WP_Error
	 */
	private function mongo_collection( $db, $collection ) {
		if ( $this->client ) {
			return $this->client->selectDatabase( $db )->selectCollection( $collection );
		} else {
			return new \WP_Error( 'mongo_client_error', __( 'Could not connect to MongoDB', 'tide-api' ) );
		}
	}
}
