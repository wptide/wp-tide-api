<?php
/**
 * This file is responsible for getting files from Google Cloud Storage.
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\Integration;

use Google\Cloud\Storage\StorageClient;
use WP_Tide_API\Base;

/**
 * Class GCP_Storage
 */
class GCP_Storage extends Base {

	/**
	 * Get temporary URL to object.
	 *
	 * @param array $meta Object data to retrieve.
	 *
	 * @return mixed Valid URL or \WP_Error.
	 */
	public function get_url( $meta ) {
		// Catch all failures.
		try {
			// Don't need to specify 'projectID'. Its already picked up in the service account file.
			$storage = new StorageClient();

			$bucket = $storage->bucket( $meta['bucket_name'] );
			$object = $bucket->object( $meta['key'] );
			$url    = $object->signedUrl( time() + ( 60 * 5 ) );

			// A temporary pre-signed url.
			return (string) $url;
		} catch ( \Exception $e ) {

			return new \WP_Error( 'gcp_storage_get_url_fail', $e->getMessage(), $e );
		}
	}
}
