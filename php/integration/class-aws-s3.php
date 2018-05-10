<?php
/**
 * This file is responsible for getting files from S3.
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\Integration;

use Aws\S3\S3Client;
use WP_Tide_API\Base;

/**
 * Class S3
 */
class AWS_S3 extends Base {

	/**
	 * Get a file from S3.
	 *
	 * @param array $meta Object data to retrieve.
	 *
	 * @return mixed
	 */
	public function get_file( $meta ) {
		try {
			$s3_client = $this->create_s3_client_instance();
			$object    = $s3_client->getObject( array(
				'Bucket' => $meta['bucket_name'],
				'Key'    => $meta['key'],
			) );
			$data      = $object->get( 'Body' )->getContents();

			return json_decode( $data );
		} catch ( \Exception $e ) {

			return new \WP_Error( 's3_get_file_fail', $e->getMessage(), $e );
		}
	}

	/**
	 * Get temporary URL to object.
	 *
	 * @param array $meta Object data to retrieve.
	 *
	 * @return mixed Valid URL or \WP_Error.
	 */
	public function get_url( $meta ) {
		try {
			$s3_client = $this->create_s3_client_instance();

			$cmd = $s3_client->getCommand( 'GetObject', [
				'Bucket'              => $meta['bucket_name'],
				'Key'                 => $meta['key'],
				'ResponseContentType' => 'application/json',
			] );

			$request = $s3_client->createPresignedRequest( $cmd, '+5 minutes' );

			// A temporary pre-signed url.
			return (string) $request->getUri();

		} catch ( \Exception $e ) {

			return new \WP_Error( 's3_get_file_fail', $e->getMessage(), $e );
		}
	}

	/**
	 * Create new instance for S3client.
	 *
	 * @return S3Client
	 */
	public function create_s3_client_instance() {

		return new S3Client( [
			'version'     => defined( 'AWS_S3_VERSION' ) ? AWS_S3_VERSION : '',
			'region'      => defined( 'AWS_S3_REGION' ) ? AWS_S3_REGION : '',
			'credentials' => [
				'key'    => defined( 'AWS_API_KEY' ) ? AWS_API_KEY : '',
				'secret' => defined( 'AWS_API_SECRET' ) ? AWS_API_SECRET : '',
			],
		] );
	}
}
