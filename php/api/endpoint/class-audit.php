<?php
/**
 * This file is responsible for bootstrapping the REST API extensions.
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\API\Endpoint;

use WP_Tide_API\Base;
use WP_Tide_API\Integration\AWS_S3;

/**
 * Class Audit
 */
class Audit extends Base {

	/**
	 * Define posts types to register to make the Audits post type available.
	 *
	 * @param array $post_types Array defining post types.
	 *
	 * @filter tide_api_post_types
	 *
	 * @return array
	 */
	public function post_type_structure( $post_types ) {

		/**
		 * 'meta_fields' will create meta_fields that is exposed to the API directly.
		 * To add a meta field add a new entry to the array. The key will be the field name and the value will be an
		 * array with normal meta args.
		 *
		 * 'rest_fields' will create a custom REST fields. It will create corresponding meta_fields (for data storage only).
		 * To add a rest field add a new entry to the array. The key will be the field name and the value will be an
		 * array with normal rest field args.
		 *
		 * @todo We still need a full set of labels for each CPT
		 */
		$structure = array(
			'audit' => array(
				'args'        => array(
					'labels'                => array(
						'name'          => 'Audits',
						'singular_name' => 'Audit',
					),
					'rest_base'             => 'audit',
					'rest_controller_class' => 'WP_Tide_API\API\Controller\Audit_Posts_Controller',
				),
				'taxonomies'  => array(
					/**
					 * Note:
					 * ?audit_project=<term_id> can be used to filter projects on term id. (default behavior)
					 * ?project=<project name> can be used to filter projects on name.
					 * { audit_meta: { audit_project: <project name> } } should be used for setting the taxonomy.
					 */
					'audit_project' => array(
						'labels'                => array(
							'name'          => 'Audit Projects',
							'singular_name' => 'Audit Project',
						),
						'description'           => 'Custom taxonomy that represents Audit Projects',
						'rest_controller_class' => 'WP_Tide_API\API\Controller\Audit_Terms_Controller',
					),
				),
				'rest_fields' => array(
					// @todo we need to specify the schema of each field
					'visibility'       => array(),
					'version'          => array(),
					'checksum'         => array(),
					'project_type'     => array(),
					'source_url'       => array(
						'update_callback' => array( $this, 'rest_field_url_update_callback' ),
					),
					'source_type'      => array(), // e.g. 'zip', 'repo'.
					'original_request' => array(),
					'code_info'        => array(),
					'reports'          => array(
						'get_callback'    => array( $this, 'rest_reports_get' ),
						'update_callback' => array( $this, 'rest_reports_update' ),
					),
					// This is the plugin/theme author. Not the user attributed to the audit.
					'project_author'   => array(
						// This should not be possible.
						'update_callback' => function () {
							return;
						},
						'get_callback'    => array( $this, 'rest_field_project_author_get_callback' ),
					),
					// Updates taxonomy terms without IDs.
					'project'          => array(
						'update_callback' => array( $this, 'rest_field_project_update_callback' ),
					),
				),
			),
		);

		return array_merge( $post_types, $structure );
	}

	/**
	 * The allowed audit standards for the API.
	 *
	 * Note: This is required as new entries are added against the API.
	 * Note: This can be overridden for specific clients.
	 *
	 * @return array
	 */
	public static function allowed_standards() {
		return array_merge(
			apply_filters( 'tide_api_client_allowed_audits', array(
				'phpcs_wordpress-core'  => array(),
				'phpcs_wordpress-docs'  => array(),
				'phpcs_wordpress-extra' => array(),
				'phpcs_wordpress-vip'   => array(),
				'lighthouse'            => array(),
			) ),
			array(
				'phpcs_wordpress'        => array(), // Always include the WordPress standard.
				'phpcs_phpcompatibility' => array(), // Always include the PHP Compatibility standard.
			)
		);
	}

	/**
	 * Filter out standards that are not allowed.
	 *
	 * @param array $standards Array of standards as strings.
	 *
	 * @return array Only return allowed standards.
	 */
	public static function filter_standards( $standards ) {
		$allowed = array_keys( self::allowed_standards() );

		return array_filter( (array) $standards, function ( $standard ) use ( $allowed ) {
			return in_array( $standard, $allowed, true );
		} );
	}

	/**
	 * Get all the available fields for an audit.
	 *
	 * Note: This is used when creating an audit task and specifies the default audits to execute.
	 * Note: An array of standards can be provided using the `standards` field in an API request which
	 *       to add additional audits to execute.
	 *
	 * @return array
	 */
	public static function executable_audit_fields() {

		return array_merge( apply_filters( 'tide_api_executable_audits', array() ), array(
			'phpcs_wordpress'        => array(), // Always include the WordPress standard.
			'phpcs_phpcompatibility' => array(), // Always include the PHP Compatibility standard.
		) );
	}

	/**
	 * A custom update_callback that can be used by rest_fields instead of rest_field_default_update_callback().
	 *
	 * Called by \WP_REST_Controller::update_additional_fields_for_object
	 *
	 * @param string           $field_value The field value.
	 * @param mixed            $object      The object data.
	 * @param string           $field_name  The name of the field.
	 * @param \WP_REST_Request $request     The WP REST Request.
	 * @param string           $object_type The type of object.
	 *
	 * @return bool|int
	 */
	public function rest_field_url_update_callback( $field_value, $object, $field_name, $request, $object_type ) {
		// @todo $field_value needs to be made safe before used.
		// value for this field needs to be a valid URL.
		$field_value = esc_url_raw( $field_value );

		return update_post_meta( $object->ID, $field_name, $field_value );
	}

	/**
	 * Some fields should not show in requests.
	 *
	 * @filter tide_api_response_removed_fields
	 *
	 * @param array $fields Response fields for the audit.
	 *
	 * @return array
	 */
	public function remove_response_fields( $fields ) {
		$fields[] = 'audit_meta';

		return $fields;
	}

	/**
	 * A custom update callback for the `reports` rest field.
	 *
	 * Breaks each report into separate post_meta.
	 *
	 * @param mixed            $field_value The field value.
	 * @param mixed            $post        The object data.
	 * @param string           $field_name  The name of the field.
	 * @param \WP_REST_Request $request     The WP REST Request.
	 * @param string           $object_type The type of object.
	 *
	 * @return bool|int
	 */
	public function rest_reports_update( $field_value, $post, $field_name, $request, $object_type ) {

		/**
		 * Check of an audit standards is allowed and then write the result to
		 * individual post_meta for standard.
		 */
		$allowed_standards = array_keys( self::allowed_standards() );
		foreach ( (array) $field_value as $field => $results ) {
			if ( ! in_array( $field, $allowed_standards, true ) ) {
				continue;
			}
			update_post_meta( $post->ID, sprintf( '_audit_%s', $field ), $results );
		}

		return true;
	}

	/**
	 * Get the `reports` rest field to send via request.
	 *
	 * @param array            $rest_post  The post.
	 * @param string           $field_name "reports".
	 * @param \WP_REST_Request $request    The REST request.
	 *
	 * @return array Return the requested standards.
	 */
	public function rest_reports_get( $rest_post, $field_name, $request ) {

		// If "standards" has been passed with the request then use those standards.
		$standards = $request->get_param( 'standards' );

		// Get "standards" from the post meta.
		if ( empty( $standards ) ) {
			$standards = get_post_meta( $rest_post['id'], 'standards', true );
		}

		// Convert a csv string to an array.
		if ( ! is_array( $standards ) ) {
			$standards = array_filter( explode( ',', $standards ) );
		}

		// Filter using the allowed standards.
		if ( ! empty( $standards ) ) {
			$allowed_standards = array_keys( self::allowed_standards() );

			$standards = array_filter( $standards, function ( $standard ) use ( $allowed_standards ) {
				return in_array( $standard, $allowed_standards, true );
			} );
		} else {
			$standards = array_keys( self::executable_audit_fields() );
		}

		$results = array();

		foreach ( $standards as $standard ) {
			$meta = get_post_meta( $rest_post['id'], sprintf( '_audit_%s', $standard ), true );

			// If we don't have any data for the standard then there's no point in adding an empty element to the results.
			if ( empty( $meta ) ) {
				continue;
			}

			if ( ! empty( $meta['full'] ) ) {
				unset( $meta['full'] );
			}

			if ( ! empty( $meta['details'] ) ) {
				unset( $meta['details'] );
			}

			$results[ $standard ] = $meta;
		}

		return $results;
	}

	/**
	 * This is the theme/plugin author details.
	 *
	 * @param array            $rest_post  The post.
	 * @param string           $field_name "project_author".
	 * @param \WP_REST_Request $request    The REST request.
	 *
	 * @return array
	 */
	public function rest_field_project_author_get_callback( $rest_post, $field_name, $request ) {

		$code_info    = get_post_meta( $rest_post['id'], 'code_info', true );
		$project_info = array();

		if ( empty( $code_info['details'] ) ) {
			return $project_info;
		}

		foreach ( $code_info['details'] as $item ) {
			$key                  = strtolower( $item['key'] );
			$project_info[ $key ] = $item['value'];
		}

		return array(
			'name' => $project_info['author'],
			'uri'  => $project_info['authoruri'],
		);
	}

	/**
	 * Turn the supplied values into taxonomy terms.
	 *
	 * @param string           $field_value The field value.
	 * @param \WP_Post         $object      The object data.
	 * @param string           $field_name  The name of the field.
	 * @param \WP_REST_Request $request     The WP REST Request.
	 * @param string           $object_type The type of object.
	 *
	 * @return bool
	 */
	public function rest_field_project_update_callback( $field_value, $object, $field_name, $request, $object_type ) {

		$field_value = (array) $field_value;

		// Sanitize values.
		$field_value = array_map( '\sanitize_text_field', $field_value );

		$terms = array();
		foreach ( $field_value as $field ) {

			// Get or create the term to assign to the audit.
			$term = get_term_by( 'name', $field, 'audit_project', ARRAY_A );
			if ( ! $term ) {
				$term = wp_insert_term( $field, 'audit_project' );
			}
			$terms[] = $term['term_id'];
		}

		if ( ! empty( $terms ) ) {
			$current = wp_get_object_terms( $object->ID, 'audit_project' );
			wp_remove_object_terms( $object->ID, $current, 'audit_project' );
			wp_set_object_terms( $object->ID, $terms, 'audit_project' );
		}

		return true;
	}
}
