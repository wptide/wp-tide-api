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
 * Class Storage_S3
 */
class Storage_S3 extends Base {

	/**
	 * Get temporary URL to object.
	 *
	 * @param array $meta Object data to retrieve.
	 *
	 * @return mixed Valid URL or \WP_Error.
	 */
	public function get_url( $meta ) {
		try {
			$s3_client = $this->get_client_instance();

			$args = array(
				'Bucket'              => $meta['path'],
				'Key'                 => $meta['filename'],
				'ResponseContentType' => 'application/json',
			);

			$cmd = $s3_client->getCommand( 'GetObject', $args );

			$request = $s3_client->createPresignedRequest( $cmd, '+5 minutes' );

			// A temporary pre-signed url.
			return (string) $request->getUri();

		} catch ( \Exception $e ) {

			return new \WP_Error( 's3_get_url_fail', $e->getMessage(), $e );
		}
	}

	/**
	 * Get new instance for S3client.
	 *
	 * @return S3Client
	 */
	public function get_client_instance() {
		$args = array(
			'version'     => defined( 'AWS_S3_VERSION' ) ? AWS_S3_VERSION : '',
			'region'      => defined( 'AWS_S3_REGION' ) ? AWS_S3_REGION : '',
			'credentials' => [
				'key'    => defined( 'AWS_API_KEY' ) ? AWS_API_KEY : '',
				'secret' => defined( 'AWS_API_SECRET' ) ? AWS_API_SECRET : '',
			],
		);
		return new S3Client( $args );
	}
}
