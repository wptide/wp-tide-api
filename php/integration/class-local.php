<?php
/**
 * This file is responsible for getting files local to the host.
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\Integration;

use WP_Tide_API\Base;

/**
 * Class Local
 */
class Local extends Base {

	/**
	 * Get URL to object.
	 *
	 * @param array $meta Object data to retrieve.
	 *
	 * @return mixed Valid URL or \WP_Error.
	 *
	 * @throws \Exception On failure.
	 */
	public function get_url( $meta ) {
		// Catch all failures.
		try {
			if ( empty( $meta['path'] ) ) {
				throw new \Exception( __( 'The path parameter cannot be empty.', 'tide-api' ) );
			}

			if ( empty( $meta['filename'] ) ) {
				throw new \Exception( __( 'The filename parameter cannot be empty.', 'tide-api' ) );
			}

			if ( empty( $meta['type'] ) || 'local' !== $meta['type'] ) {
				throw new \Exception( __( 'The type parameter must be local.', 'tide-api' ) );
			}

			$uploads = wp_upload_dir();
			if ( empty( $uploads['basedir'] ) ) {
				throw new \Exception( __( 'The uploads basedir cannot be empty.', 'tide-api' ) );
			}
			if ( empty( $uploads['baseurl'] ) ) {
				throw new \Exception( __( 'The uploads baseurl cannot be empty.', 'tide-api' ) );
			}

			$file     = trailingslashit( $meta['path'] ) . $meta['filename'];
			$filepath = trailingslashit( $uploads['basedir'] ) . $file;
			if ( ! file_exists( $filepath ) ) {
				throw new \Exception( __( 'The file does not exist on the host.', 'tide-api' ) );
			}

			// Local url.
			return trailingslashit( $uploads['baseurl'] ) . $file;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'local_get_url_fail', $e->getMessage(), $e );
		}
	}
}
