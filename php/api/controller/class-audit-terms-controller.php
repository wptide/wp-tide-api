<?php
/**
 * Custom Controller class used by the Audit Projects custom taxonomy.
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\API\Controller;

use WP_Tide_API\Plugin;

/**
 * Class Tide_REST_Audit_Projects_Terms_Controller
 */
class Audit_Terms_Controller extends \WP_REST_Terms_controller {

	/**
	 * Tide_REST_Audit_Projects_Terms_Controller constructor.
	 *
	 * @param string $taxonomy The taxonomy this controller applies to.
	 */
	public function __construct( $taxonomy ) {
		$version   = Plugin::instance()->info['api_version'];
		$namespace = Plugin::instance()->info['api_namespace'];
		parent::__construct( $taxonomy );
		$this->namespace = sprintf( '%s/%s', $namespace, $version );
	}

	/**
	 * Prepares a single term output for response.
	 *
	 * @param object           $item    Term object.
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response $response Response object.
	 */
	public function prepare_item_for_response( $item, $request ) {
		$response = parent::prepare_item_for_response( $item, $request );

		$removed_fields = apply_filters(
			'tide_api_response_removed_fields',
			array(
				'id',
				'link',
				'slug',
				'taxonomy',
				'_links',
				'status',
			)
		);

		$removed_hal_link_fields = array(
			'about',
			'collection',
			'https://api.w.org/post_type',
			'https://api.w.org/attachment',
		);

		$data = $response->get_data();

		foreach ( $removed_fields as $field ) {
			unset( $data[ $field ] );
		}

		$response->set_data( $data );

		foreach ( $removed_hal_link_fields as $field ) {
			$response->remove_link( $field );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Checks if terms can be listed.
	 *
	 * @param \WP_REST_Request $request Taxonomy.
	 *
	 * @return bool Whether the terms can be read.
	 */
	public function get_items_permissions_check( $request ) {
		$allowed = parent::get_items_permissions_check( $request );

		/**
		 * Unauthenticated users should not be able to list terms.
		 *
		 * But, they should be able to see the terms if its embedded in an audit.
		 */
		$context = $request->get_param( 'context' );
		if ( ! is_user_logged_in() && 'embed' !== $context ) {
			$allowed = false;
		}

		return $allowed;
	}
}
