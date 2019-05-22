<?php
/**
 * This file is responsible for creating the /report/ API endpoint..
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\API\Endpoint;

use WP_Tide_API\Base;
use WP_Tide_API\Utility\User;

/**
 * Class Report
 */
class Report extends Base {

	/**
	 * API namespace.
	 *
	 * @var string The namespace.
	 */
	private $namespace = 'tide/v1';

	/**
	 * Base path for reports.
	 *
	 * @var string Base path.
	 */
	private $rest_base = 'report';

	const POST_PATTERN     = '(?P<post_id>\d*)';
	const CHECKSUM_PATTERN = '(?P<checksum>[a-fA-F\d]{64})';
	const TYPE_PATTERN     = '(?P<type>(raw|parsed))';
	const STANDARD_PATTERN = '(?P<standard>[\w-]+)';

	/**
	 * Register new routes.
	 *
	 * @action rest_api_init
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'report_response' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/' . static::CHECKSUM_PATTERN . '/' . static::TYPE_PATTERN . '/' . static::STANDARD_PATTERN,
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'report_response' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/' . static::POST_PATTERN . '/' . static::TYPE_PATTERN . '/' . static::STANDARD_PATTERN,
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'report_response' ),
			)
		);
	}

	/**
	 * Prepare the response for a report requests.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return mixed|\WP_REST_Response The response object.
	 */
	public function report_response( \WP_REST_Request $request ) {

		// These reports are not for unauthenticated users.
		if ( ! User::authenticated() ) {
			return rest_ensure_response( $this->report_error( 'unauthenticated_call', __( 'Unauthenticated report request', 'tide-api' ), 301 ) );
		}

		$checksum = $request->get_param( 'checksum' );
		$post_id  = $request->get_param( 'post_id' );
		$standard = $request->get_param( 'standard' );
		$type     = $request->get_param( 'type' );

		// Could not get the meta for the given type.
		if ( empty( $type ) || ! in_array( $type, array( 'raw', 'parsed' ), true ) ) {
			return rest_ensure_response( $this->report_error( 'report_type_not_found', __( 'Could not retrieve report for type', 'tide-api' ), 500 ) );
		}

		// If we don't have a post_id, but a checksum, then hit the checksum endpoint.
		if ( ! empty( $checksum ) ) {
			$request  = new \WP_REST_Request( 'GET', '/' . $this->namespace . '/audit/' . $checksum );
			$response = rest_do_request( $request );

			if ( $response->is_error() ) {
				// Don't be too verbose about the error.
				return rest_ensure_response( $this->report_error( 'report_error', __( 'Error occurred in report api', 'tide-api' ), 500 ) );
			}

			// Set the post_id to get the meta for.
			$post_id = $response->data['id'];
		}

		// Best effort to retrieve meta.
		$meta = get_post_meta( $post_id, '_audit_' . $standard, true );

		// Could not get the meta for the given standard.
		if ( empty( $meta ) ) {
			return rest_ensure_response( $this->report_error( 'report_standard_not_found', __( 'Could not retrieve report for standard', 'tide-api' ), 404 ) );
		}

		$object_source = 'storage_' . $meta[ $type ]['type'];
		if ( empty( $this->plugin->components[ $object_source ] ) ) {
			return rest_ensure_response( $this->report_error( 'report_source_error', __( 'Could not retrieve report from source', 'tide-api' ), 404 ) );
		}

		// Get temporary signed url.
		$meta = maybe_unserialize( $meta );

		$url = $this->plugin->components[ $object_source ]->get_url( $meta[ $type ] );

		// Error fetching from storage provider.
		if ( is_wp_error( $url ) ) {
			return rest_ensure_response( $this->report_error( 'report_fetch_error', $url->get_error_message(), 500 ) );
		}

		$response_object = [
			'rel'     => 'download',
			'url'     => $url,
			'expires' => date( DATE_ISO8601, time() + ( 60 * 5 ) ),
		];

		return rest_ensure_response( $response_object );
	}


	/**
	 * Create an error object to return in response.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code.
	 *
	 * @return array An error array.
	 */
	public function report_error( $code, $message, $status ) {
		return [
			'code'    => $code,
			'message' => $message,
			'data'    => [
				'status' => $status,
			],
		];
	}

}
