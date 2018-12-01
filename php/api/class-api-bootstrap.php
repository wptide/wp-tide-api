<?php
/**
 * This file is responsible for bootstrapping the REST API extensions.
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\API;

use WP_Tide_API\Base;

/**
 * Class API_Bootstrap
 */
class API_Bootstrap extends Base {

	/**
	 * Register CPTs based on data found in $custom_post_types property.
	 *
	 * @action init
	 */
	public function register_post_types() {
		$default_args = array(
			'public'       => true,
			'show_in_rest' => true,
			'supports'     => array(
				'title',
				'editor',
				'custom-fields',
				'author',
				'excerpt',
			),
		);

		$this->plugin->post_types = apply_filters( 'tide_api_post_types', array() );

		foreach ( $this->plugin->post_types as $post_type => $values ) {
			$values['args'] = array_merge( $default_args, $values['args'] );

			register_post_type( $post_type, $values['args'] );

			if ( isset( $values['taxonomies'] ) ) {
				$this->register_taxonomies( $post_type, $values['taxonomies'] );
			}

			/*
			 * Note that register_meta() in Core does not currently (4.7.2) support object_subtypes,
			 * which is why we use 'post' here. That means that any meta field registered for a CPT
			 * will also apply to any other object_type of 'post' that supports custom-fields (native Post and other CPT's)
			 *
			 * See: https://make.wordpress.org/core/2016/07/08/enhancing-register_meta-in-4-6/
			 * and https://make.wordpress.org/core/2016/07/20/additional-register_meta-changes-in-4-6/
			 */
			if ( isset( $values['meta_fields'] ) ) {
				$this->register_meta_fields( 'post', $values['meta_fields'] );
			}

			if ( isset( $values['rest_fields'] ) ) {
				add_action( 'rest_api_init', function () use ( $post_type, $values ) {
					$this->register_rest_fields( $post_type, $values['rest_fields'] );
				} );
			}

			// Enable filtering.
			add_action( 'rest_api_init', function () use ( $post_type ) {
				add_filter( 'rest_' . $post_type . '_query', function ( $args, $request ) {
					if ( empty( $request['filter'] ) || ! is_array( $request['filter'] ) ) {
						return $args;
					}
					$filter = $request['filter'];
					if ( isset( $filter['posts_per_page'] ) && ( (int) $filter['posts_per_page'] >= 1 && (int) $filter['posts_per_page'] <= 100 ) ) {
						$args['posts_per_page'] = $filter['posts_per_page'];
					}
					global $wp;
					$vars = apply_filters( 'query_vars', $wp->public_query_vars );
					foreach ( $vars as $var ) {
						if ( isset( $filter[ $var ] ) ) {
							$args[ $var ] = $filter[ $var ];
						}
					}

					return $args;
				}, 10, 2 );
			} );

		}

		// Get the file time for this file as the version number.
		$version = filemtime( __FILE__ );
		$option  = __CLASS__ . '_flush';

		// Flush the rewrite rules once after the api initializes, and if this file changes in the future.
		if ( get_option( $option, 0 ) !== $version ) {
			flush_rewrite_rules();
			if ( ! update_option( $option, $version ) ) {
				add_option( $option, $version );
			}
		}
	}

	/**
	 * Register new roles for API Client.
	 *
	 * @action init
	 */
	public function register_roles() {

		// Caps for API Client role.
		$capabilities = array(
			'delete_posts'           => true,
			'delete_published_posts' => true,
			'edit_posts'             => true,
			'edit_published_posts'   => true,
			'publish_posts'          => true,
			'read'                   => true,
			'upload_files'           => true,
		);

		// Add API Client role.
		add_role( 'api_client', __( 'API Client', 'tide-api' ), $capabilities );

		// Caps for Audit Client role.
		$capabilities = array(
			'delete_others_posts'    => true,
			'delete_posts'           => true,
			'delete_private_posts'   => true,
			'delete_published_posts' => true,
			'edit_others_posts'      => true,
			'edit_posts'             => true,
			'edit_private_posts'     => true,
			'edit_published_posts'   => true,
			'publish_posts'          => true,
			'read_private_posts'     => true,
			'read'                   => true,
			'upload_files'           => true,
		);

		// Add API Client role.
		add_role( 'audit_client', __( 'Audit Client', 'tide-api' ), $capabilities );
	}

	/**
	 * Register meta_fields for CPTs based on data in $custom_post_types property.
	 *
	 * @param string $object_type Post Type.
	 * @param array  $meta_fields Array of fields.
	 */
	public function register_meta_fields( $object_type, $meta_fields ) {
		/*
		 * We are not prefixing all our meta keys, since its unlikely that we'll use other plugins with conflicting keys,
		 * but it is important to note the recommended best practice:
		 *
		 * from https://make.wordpress.org/core/2016/07/20/additional-register_meta-changes-in-4-6/ :
		 * "There will no longer be a check for unique object types and subtypes for meta keys.
		 * There is no CURIE like syntax involved.
		 * Instead, be sure to uniquely prefix meta keys so that they do not conflict with others that may be registered with different arguments."
		 */

		// @todo Also supply 'sanitize_callback' function to sanitize each meta value.
		// args that should be different from default values used in register_meta() Core function.
		$default_args = array(
			'single'       => true,
			'show_in_rest' => true,
		);

		foreach ( $meta_fields as $meta_key => $args ) {
			$args = array_merge( $default_args, $args );
			register_meta( $object_type, $meta_key, $args );
		}
	}

	/**
	 * Register rest_fields for CPTs based on data in $custom_post_types property.
	 *
	 * @param string $object_type Post type.
	 * @param array  $rest_fields Array of fields to register.
	 */
	public function register_rest_fields( $object_type, $rest_fields ) {
		$default_meta_args = array(
			'single'       => true,
			'show_in_rest' => false,
			// These meta_fields are only to store rest_field data in, not to be used in REST response directly.
		);

		$default_rest_field_args = array(
			'get_callback'    => array( $this, 'rest_field_default_get_callback' ),
			'update_callback' => array( $this, 'rest_field_default_update_callback' ),
		);

		foreach ( $rest_fields as $attribute => $args ) {
			$args = array_merge( $default_rest_field_args, $args );
			register_rest_field( $object_type, $attribute, $args );

			// Each rest_field needs a meta_field to store it's data in.
			register_meta( 'post', $attribute, $default_meta_args );
		}
	}

	/**
	 * Register taxonomies for CPTs based on data in $custom_post_types property.
	 *
	 * @param string $object_type Post type.
	 * @param array  $taxonomies  Taxonomies.
	 */
	public function register_taxonomies( $object_type, $taxonomies ) {
		$default_args = array(
			'show_in_rest' => true,
		);

		foreach ( $taxonomies as $taxonomy => $args ) {
			// @todo we need to check if a taxonomy already exists before making changes to it.
			$args = array_merge( $default_args, $args );
			register_taxonomy( $taxonomy, $object_type, $args );

			register_taxonomy_for_object_type( $taxonomy, $object_type );
		}
	}

	/**
	 * The default get_callback to be used by rest_fields unless a custom callback is specified.
	 *
	 * Called by \WP_REST_Controller::add_additional_fields_to_object()
	 *
	 * @todo parameters are just as they appear in add_additional_fields_to_object(). Find more descriptive parameter names to use here
	 *
	 * @param mixed            $object      The object data.
	 * @param string           $field_name  The name of the field.
	 * @param \WP_REST_Request $request     The WP REST Request.
	 * @param string           $object_type The type of object.
	 *
	 * @return mixed
	 */
	public function rest_field_default_get_callback( $object, $field_name, $request, $object_type ) {
		/*
		 * When registering rest_field, we also registered a meta_field with key that corresponds to rest_field's name.
		 * That's why we can use $field_name here to look up the meta
		 */
		$meta_value = get_post_meta( $object['id'], $field_name, true );

		return $meta_value;
	}


	/**
	 * The default update_callback to be used by rest_fields unless a custom callback is specified.
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
	public function rest_field_default_update_callback( $field_value, $object, $field_name, $request, $object_type ) {

		// @todo if a 'sanitize_callback' function is specified in register_meta() for corresponding meta_field, will it apply here?
		// Fields need to be filtered and made safe.
		$field_value = apply_filters( 'tide_api_sanitize_rest_field_' . $field_name, $field_value, $request );

		if ( apply_filters( 'tide_api_rest_do_field_update', true, $object, $field_name, $request ) ) {
			return update_post_meta( $object->ID, $field_name, $field_value );
		}

		return false;
	}

	/**
	 * Change the rest_url_prefix.
	 *
	 * @filter rest_url_prefix
	 *
	 * @param string $prefix The REST prefix.
	 *
	 * @return string The new REST prefix.
	 */
	public function rest_url_prefix( $prefix ) {
		return 'api';
	}

	/**
	 * Update the WP_Query args to map REST fields to post_meta.
	 *
	 * @todo   Find a better place for this filter.
	 *
	 * @filter rest_audit_query 10, 2
	 *
	 * @param array            $args    The query args.
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return array The modified args.
	 */
	public function rest_audit_query( $args, $request ) {

		$allowed        = array( 'checksum' );
		$request_params = $request->get_params();

		/**
		 * Prepare a meta query.
		 */
		$queries = array(
			'relation' => 'OR',
		);
		foreach ( $request_params as $key => $value ) {
			if ( ! in_array( $key, $allowed, true ) || is_array( $value ) ) {
				continue;
			}

			$values = explode( ',', $value );

			$trimmed_values = array_map( 'trim', $values );

			$queries[] = array(
				'key'     => $key,
				'value'   => $trimmed_values,
				'compare' => 'IN',
			);
		}

		// If there are less then 3 items (i.e no relation) then remove the relation by shifting the array.
		if ( 3 > count( $queries ) ) {
			array_shift( $queries );
		}

		// If there is a meta query, then add it.
		if ( ! empty( $queries ) ) {
			$args['meta_query'] = $queries; // WPCS: slow query ok.
		}

		return $args;
	}
}
