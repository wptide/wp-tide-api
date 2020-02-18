<?php
/**
 * A custom controller class can be used to manage WP REST API functionality
 * for CPTs, instead of using the default WP_REST_Post_Types_Controller class.
 * The Tide_REST_Audit_Posts_Controller class is used by the Audit CPT.
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\API\Controller;

use WP_Tide_API\API\Endpoint\Audit;
use WP_Tide_API\Plugin;
use WP_Tide_API\Utility\Audit_Meta;
use WP_Tide_API\Utility\Audit_Tasks;
use WP_Tide_API\Utility\User;

/**
 * Class Tide_REST_Audit_Posts_Controller
 */
class Audit_Posts_Controller extends \WP_REST_Posts_Controller {

	/**
	 * Statically keep the original data of the requests so that it can be
	 * referenced.
	 *
	 * @var mixed
	 */
	private static $original_request_data;

	const CHECKSUM_PATTERN = '(?P<checksum>[a-fA-F\d]{64})';

	const PROJECT_CLIENT_PATTERN = '(?P<project_client>[\w-]{1,32})';

	const PROJECT_TYPE_PATTERN = '(?P<project_type>[\w-]{1,32})';

	const PROJECT_SLUG_PATTERN = '(?P<project_slug>[\w-]+)';

	const PROJECT_VERSION_PATTERN = '(?P<version>[\w.-]+)';

	/**
	 * Allowed source URL extensions.
	 *
	 * @var array
	 */
	private $allowed_extensions = array(
		'zip',
		'git',
	);

	/**
	 * Tide_REST_Audit_Posts_Controller constructor.
	 *
	 * @param string $post_type The post type this controller applies to.
	 */
	public function __construct( $post_type ) {

		$version   = Plugin::instance()->info['api_version'];
		$namespace = Plugin::instance()->info['api_namespace'];
		parent::__construct( $post_type );
		$this->namespace = sprintf( '%s/%s', $namespace, $version );

		/**
		 * Only update audit fields if the client has permission to do so.
		 */
		add_filter(
			'tide_api_rest_do_field_update',
			array(
				$this,
				'filter_audit_fields',
			),
			10,
			4
		);

		add_filter(
			'rest_audit_query',
			array(
				$this,
				'handle_custom_args',
			),
			10,
			2
		);
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @see    register_rest_route()
	 */
	public function register_routes() {
		parent::register_routes();

		$get_item_args = array(
			'context' => $this->get_context_param(
				array(
					'default' => 'view',
				)
			),
		);

		$args = array(
			'args'   => array(
				'altid' => array(
					'description' => __( 'An alternate unique id to query on (e.g. checksum)' ),
					'type'        => 'string',
				),
			),
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array(
					$this,
					'get_item_altid',
				),
				'permission_callback' => array(
					$this,
					'get_item_permissions_check_altid',
				),
				'args'                => $get_item_args,
			),
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array(
					$this,
					'update_item_altid',
				),
				'permission_callback' => array(
					$this,
					'update_item_permissions_check_altid',
				),
				'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::EDITABLE ),
			),
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array(
					$this,
					'delete_item_altid',
				),
				'permission_callback' => array(
					$this,
					'delete_item_permissions_check_altid',
				),
				'args'                => array(
					'force' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Whether to bypass trash and force deletion.' ),
					),
				),
			),
			'schema' => array(
				$this,
				'get_public_item_schema',
			),
		);
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/' . static::CHECKSUM_PATTERN, $args );

		$args = array(
			'args'   => array(
				'project_client' => array(
					'description' => __( 'User login name representing a project client.' ),
					'type'        => 'string',
				),
			),
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array(
					$this,
					'get_items',
				),
				'permission_callback' => array(
					$this,
					'get_items_permissions_check',
				),
				'args'                => $get_item_args,
			),
			'schema' => array(
				$this,
				'get_public_item_schema',
			),
		);
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/' . static::PROJECT_CLIENT_PATTERN, $args );

		$args = array(
			'args'   => array(
				'project_client' => array(
					'description' => __( 'User login name representing a project client.' ),
					'type'        => 'string',
				),
				'project_type'   => array(
					'description' => __( 'The project type: theme or plugin.' ),
					'type'        => 'string',
				),
			),
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array(
					$this,
					'get_items',
				),
				'permission_callback' => array(
					$this,
					'get_items_permissions_check',
				),
				'args'                => $get_item_args,
			),
			'schema' => array(
				$this,
				'get_public_item_schema',
			),
		);
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/' . static::PROJECT_CLIENT_PATTERN . '/' . static::PROJECT_TYPE_PATTERN, $args );

		$args = array(
			'args'   => array(
				'project_client' => array(
					'description' => __( 'User login name representing a project client.' ),
					'type'        => 'string',
				),
				'project_type'   => array(
					'description' => __( 'The project type: theme or plugin.' ),
					'type'        => 'string',
				),
				'project_slug'   => array(
					'description' => __( 'The taxonomy term representing the project.' ),
					'type'        => 'string',
				),
			),
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array(
					$this,
					'get_items',
				),
				'permission_callback' => array(
					$this,
					'get_items_permissions_check',
				),
				'args'                => $get_item_args,
			),
			'schema' => array(
				$this,
				'get_public_item_schema',
			),
		);
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/' . static::PROJECT_CLIENT_PATTERN . '/' . static::PROJECT_TYPE_PATTERN . '/' . static::PROJECT_SLUG_PATTERN, $args );

		$args = array(
			'args'   => array(
				'project_client' => array(
					'description' => __( 'User login name representing a project client.' ),
					'type'        => 'string',
				),
				'project_type'   => array(
					'description' => __( 'The project type: theme or plugin.' ),
					'type'        => 'string',
				),
				'project_slug'   => array(
					'description' => __( 'The taxonomy term representing the project.' ),
					'type'        => 'string',
				),
				'version'        => array(
					'description' => __( 'The version representing the project.' ),
					'type'        => 'string',
				),
			),
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array(
					$this,
					'get_item_altid',
				),
				'permission_callback' => array(
					$this,
					'get_item_permissions_check_altid',
				),
				'args'                => $get_item_args,
			),
			'schema' => array(
				$this,
				'get_public_item_schema',
			),
		);
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/' . static::PROJECT_CLIENT_PATTERN . '/' . static::PROJECT_TYPE_PATTERN . '/' . static::PROJECT_SLUG_PATTERN . '/' . static::PROJECT_VERSION_PATTERN, $args );
	}

	/**
	 * Retrieve the audits for the user.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return mixed
	 */
	public function get_items( $request ) {

		$is_single = false;
		$args      = array(
			'post_type'  => $this->post_type,
			'orderby'    => 'post_date',
			'order'      => 'DESC',
			'meta_query' => array(),
		);

		if ( null !== $request->get_param( 'project_client' ) ) {
			$user = get_user_by( 'login', $request->get_param( 'project_client' ) );
			if ( false === $user ) {
				return new \WP_Error(
					'tide_audit_invalid_project_client',
					__( 'Invalid project client.' ),
					array(
						'status' => 404,
					)
				);
			}
			$args['author'] = $user->ID;
		}

		if ( null !== $request->get_param( 'project_type' ) ) {
			$args['meta_query'][] = array(
				'key'     => 'project_type',
				'value'   => $request->get_param( 'project_type' ),
				'compare' => '=',
			);
		}

		if ( null !== $request->get_param( 'project_slug' ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'audit_project',
					'field'    => 'slug', // search by taxonomy slug.
					'terms'    => $request->get_param( 'project_slug' ),
				),
			);

			$args['meta_key'] = 'version';
			$args['orderby']  = 'meta_value';
			$args['order']    = 'DESC';
		}

		if ( null !== $request->get_param( 'page' ) ) {
			$args['paged'] = $request->get_param( 'page' );
		}

		if ( null !== $request->get_param( 'search' ) ) {
			$args['s'] = $request->get_param( 'search' );
		}

		if ( null !== $request->get_param( 'per_page' ) ) {
			$args['posts_per_page'] = $request->get_param( 'per_page' );
		}

		// Version.
		if ( null !== $request->get_param( 'version' ) ) {
			$args['meta_query'][] = array(
				'key'     => 'version',
				'value'   => $request->get_param( 'version' ),
				'compare' => '=',
			);

			$is_single = true;
		}

		if ( empty( $args['meta_query'] ) ) {
			unset( $args['meta_query'] );
		}

		$args       = apply_filters( "rest_{$this->post_type}_query", $args, $request );
		$query_args = $this->prepare_items_query( $args, $request );

		$posts_query  = new \WP_Query();
		$query_result = $posts_query->query( $query_args );
		$posts        = array();

		foreach ( $query_result as $post ) {
			if ( ! isset( $post->ID ) || ! $this->check_read_permission( $post ) ) {
				continue;
			}

			$data    = $this->prepare_item_for_response( $post, $request );
			$posts[] = $this->prepare_response_for_collection( $data );
		}

		if ( true === $is_single ) {
			if ( empty( $posts ) ) {
				return new \WP_Error(
					'tide_audit_invalid_item',
					__( 'Invalid item.' ),
					array(
						'status' => 404,
					)
				);
			}
			$posts = array_shift( $posts );
		}

		return $posts;
	}

	/**
	 * Prepares a single post output for response.
	 *
	 * @param \WP_Post         $post    Post object.
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $post, $request ) {

		$response = parent::prepare_item_for_response( $post, $request );

		/**
		 * Hack to get embeds added to the response without passing _embed with the query.
		 */
		$_GET['_embed'] = 1;

		$removed_fields = apply_filters(
			'tide_api_response_removed_fields',
			array(
				'guid',
				'slug',
				'type',
				'date_gmt',
				'link',
				'excerpt',
				'author',
				'template',
				'modified_gmt',
				'password',
				'status',
				'post_name',
				'original_request',
				'project', // Hide the rest field in the response.
				'meta',
			)
		);

		$removed_hal_link_fields = array(
			'about',
			'author',
			'https://api.w.org/attachment',
		);

		$data                          = $response->get_data();
		static::$original_request_data = $data;

		foreach ( $removed_fields as $field ) {
			unset( $data[ $field ] );
		}

		$data['title']   = $data['title']['rendered'];
		$data['content'] = $data['content']['rendered'];

		$data['visibility'] = empty( $data['visibility'] ) ? 'private' : $data['visibility'];

		$response->set_data( $data );

		foreach ( $removed_hal_link_fields as $field ) {
			$response->remove_link( $field );
		}

		if ( ! empty( $data['standards'] ) && is_array( $data['standards'] ) ) {
			foreach ( $data['standards'] as $standard ) {

				// If we don't have any data skip.
				if ( empty( $data['reports'][ $standard ] ) ) {
					continue;
				}

				foreach ( $data['reports'][ $standard ] as $type => $value ) {

					// Not in here, skip.
					if ( ! in_array( $type, array( 'raw', 'parsed' ), true ) ) {
						continue;
					}

					if ( ! empty( $data['reports'][ $standard ][ $type ] ) ) {

						// Pretty path or query path?
						if ( get_option( 'permalink_structure' ) ) {
							$path = sprintf( '/%s/%s/%s/%s/%s', $this->namespace, 'report', $post->ID, $type, $standard );
						} else {
							$path = sprintf( '/%s/%s?post_id=%s&type=%s&standard=%s', $this->namespace, 'report', $post->ID, $type, $standard );
						}

						// Add the report link.
						$response->add_link(
							'report',
							rest_url( $path ),
							[
								'standard' => $standard,
								'type'     => $type,
								'rel'      => 'download',
							]
						);
					}

					// Remove raw & parsed from the response.
					unset( $data['reports'][ $standard ][ $type ] );
					$response->set_data( $data );
				}
			}
		}

		$response = apply_filters( 'tide_api_modify_response', $response, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Creates a single post.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or
	 *                                     WP_Error object on failure.
	 */
	public function create_item( $request ) {
		$checksum = $request->get_param( 'checksum' );

		// if $checksum exists, mimic update_item_altid() to an extend.
		if ( $checksum ) {
			// check if an audit post with that checksum exists.
			$post_id = $this->get_altid_post_id( $request );

			if ( ! is_wp_error( $post_id ) ) {
				// An audit post does exist, short-circuit create_item() execution.
				$request->set_param( 'id', $post_id );

				return $this->update_item( $request );
			}
			// If no audit post exists, just continue what we were doing in create_item().
		}

		$response       = false;
		$source_url     = $request->get_param( 'source_url' );
		$source_type    = $request->get_param( 'source_type' );
		$standards      = $request->get_param( 'standards' );
		$project_type   = $request->get_param( 'project_type' );
		$request_client = $request->get_param( 'request_client' );

		if ( ! is_array( $standards ) ) {
			$standards = explode( ',', $standards );
			$standards = array_filter( $standards );
		}

		if ( empty( $standards ) ) {
			$standards = array_keys( Audit::executable_audit_fields() );
		}

		// Remove any standards that don't belong.
		$standards = Audit::filter_standards( $standards );

		// Remove lighthouse if this is not a theme.
		$lh_key = array_search( 'lighthouse', $standards, true );
		if ( 'theme' !== $project_type && false !== $lh_key ) {
			unset( $standards[ $lh_key ] );
		}

		// Resetting array keys.
		$standards = array_values( $standards );

		if ( ( empty( $source_type ) || empty( $source_url ) ) && ! ( $response instanceof \WP_Error ) ) {
			$response = new \WP_Error(
				'insufficient_parameters',
				__( 'Please check your request and ensure you have supplied all required parameters.', 'tide-api' ),
				array(
					'status' => 400,
				)
			);
		}

		// Check that the source URL is valid.
		$ext = substr( $source_url, strrpos( $source_url, '.' ) + 1 );
		if ( ! in_array( $ext, $this->allowed_extensions, true ) ) {
			$response = new \WP_Error(
				'rest_invalid_source_url',
				__( 'Please check that the source URL is .zip or .git.', 'tide-api' ),
				array(
					'status' => 400,
				)
			);
		}

		$request->set_param( 'status', 'publish' );

		/**
		 * Author should be set to the request client. Use current user as the fallback (i.e. do nothing).
		 *
		 * @var  \WP_User|bool $author A WordPress user.
		 */
		$author = get_user_by( 'login', $request_client );
		if ( ! empty( $author ) ) {
			$request->set_param( 'author', $author->ID );
		}

		if ( ! is_wp_error( $response ) ) {
			$response = parent::create_item( $request );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$original_data = static::$original_request_data; // Keep the original data because...
		$data          = $response->get_data(); // $response->get_data() will have the filtered data.
		$post_id       = (int) $original_data['id'];

		$this->update_audit_meta( $post_id, $request );
		update_post_meta( $post_id, 'standards', $standards );

		/*
		 * We are using ::get_data( $data ) above which runs the data through a filter.
		 * This is different to the current state of the data.
		 * Therefore, we are now using ::set_data( $data ) to update with the filtered data.
		 */
		$response->set_data( $data );

		// Filter the available standards and then create the audit request
		// if this current request does not have any reports.
		if ( empty( $checksum ) ) {
			$this->create_audit_request( $request, $post_id, Audit::filter_standards( $standards ) );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Send an audit task to the job queue.
	 *
	 * @param \WP_REST_Request $request   Full details about the request.
	 * @param mixed            $post      The post or post->ID of an audit that needs to be audited.
	 * @param array            $standards The audit standards.
	 */
	public function create_audit_request( $request, $post = false, $standards = array() ) {

		if ( is_object( $post ) && isset( $post->ID ) ) {
			$post = $post->ID;
		}

		$fallback_client = '';

		$user = User::authenticated();
		if ( $user instanceof \WP_User ) {
			$fallback_client = $user->user_login;
		}

		$request_client = $fallback_client;
		if ( ! empty( $request->get_param( 'request_client' ) ) && User::has_cap( 'audit_client' ) ) {
			$request_client = $request->get_param( 'request_client' );
		}

		$title        = $request->get_param( 'title' ) ? $request->get_param( 'title' ) : '';
		$content      = $request->get_param( 'content' ) ? $request->get_param( 'content' ) : '';
		$source_url   = $request->get_param( 'source_url' ) ? $request->get_param( 'source_url' ) : '';
		$source_type  = $request->get_param( 'source_type' ) ? $request->get_param( 'source_type' ) : '';
		$project_type = $request->get_param( 'project_type' ) ? $request->get_param( 'project_type' ) : '';
		$force        = $request->get_param( 'force' ) ? filter_var( $request->get_param( 'force' ), FILTER_VALIDATE_BOOLEAN ) : false;
		$visibility   = $request->get_param( 'visibility' ) ? $request->get_param( 'visibility' ) : 'private';
		$slug         = $request->get_param( 'slug' ) ? $request->get_param( 'slug' ) : '';

		// Can't go much further without these.
		if ( empty( $source_type ) || empty( $source_url ) ) {
			return;
		}

		/*
		 * If a post does not exist yet, use the collection route as response endpoint
		 * If we do have a post, use it's route as response endpoint
		 */
		if ( ! $post ) {
			$response_api_endpoint = rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) );
		} else {
			$response_api_endpoint = rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $post ) );
		}

		$map = function ( $standard ) {
			return sanitize_text_field( $standard );
		};

		$standards = array_map( $map, $standards );

		$task_args = array(
			'response_api_endpoint' => esc_url_raw( $response_api_endpoint ),
			'title'                 => sanitize_text_field( $title ),
			'content'               => wp_kses_post( $content ),
			'project_type'          => sanitize_text_field( $project_type ),
			'source_url'            => esc_url_raw( $source_url ),
			'source_type'           => sanitize_text_field( $source_type ),
			'request_client'        => sanitize_text_field( $request_client ),
			'force'                 => $force,
			'visibility'            => sanitize_text_field( $visibility ),
			'standards'             => $standards,
			'audits'                => array(),
		);

		if ( ! empty( $slug ) ) {
			$task_args['slug'] = sanitize_text_field( $slug );
		}

		foreach ( $standards as $audit ) {
			// Make sure the edit key is exactly what we want. So replace legacy audit_,
			// but also replace _audit_ to prevent a possible __audit_ prefix.
			$audit = '_audit_' . str_replace( array( 'audit_', '_audit_' ), '', strtolower( $audit ) );

			$audit_result = '';
			if ( false !== $post ) {
				$audit_result = get_post_meta( $post, $audit, true );
			}

			// If a result already exists, then move on to the next audit.
			if ( ! empty( $audit_result ) && false === $force ) {
				continue;
			}

			$audit_keys   = explode( '_', $audit );
			$standard_key = 3;

			if ( count( $audit_keys ) === $standard_key ) {
				list( $x, $y, $type ) = $audit_keys;

				$standard = '';
			} else {

				// Missing type and standard.
				if ( $standard_key > count( $audit_keys ) ) {
					continue;
				}

				// Support standards that use underscores.
				for ( $i = 0; $i < count( $audit_keys ); $i++ ) {
					if ( $standard_key < $i ) {
						$audit_keys[ $standard_key ] .= '_' . $audit_keys[ $i ];
					}
				}
				list( $x, $y, $type, $standard ) = $audit_keys;
			}

			$options = array();

			if ( ! empty( $standard ) && 'phpcs' === $type ) {
				$options = array(
					'standard' => $standard,
					'report'   => 'json',
					'encoding' => 'utf-8',
				);

				if ( 'phpcompatibility' === $standard ) {

					// Lowest version set to WordPress minimum version.
					$options['runtime-set'] = 'testVersion 5.2-';
				} else {

					// Ignore 3rd-party directories.
					$options['ignore'] = '*/vendor/*,*/node_modules/*';
				}
			}

			$args = array(
				'type'    => $type,
				'options' => $options,
			);

			/**
			 * Filters the task arguments before they are sent to the queue provider.
			 *
			 * The dynamic portion of the hook name, `$type`, refers to the audit type.
			 * Unless a custom audit type has been implemented this will be one of `phpcs`
			 * or `lighthouse`. The lighthouse type will have an empty value for `$standard`.
			 *
			 * @param array  $args     The task arguments for the message queue.
			 * @param string $standard The audit standard.
			 */
			$args = apply_filters( "tide_api_{$type}_task_args", $args, $standard );

			if ( ! empty( $args ) ) {
				$task_args['audits'][] = $args;
			}
		}

		// It is possible that all required audits have already been run, so don't do anything.
		if ( ! empty( $task_args['audits'] ) ) {
			Audit_Tasks::add_task( $task_args );
		}
	}

	/**
	 * Updates a single audit.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or
	 *                                     WP_Error object on failure.
	 */
	public function update_item( $request ) {

		$post_id            = $request->get_param( 'id' );
		$original_standards = get_post_meta( $post_id, 'standards', true );

		if ( ! is_array( $original_standards ) ) {
			$original_standards = (array) $original_standards;
		}

		$response = parent::update_item( $request );

		$response_data = $response->get_data();

		$this->update_audit_meta( $post_id, $request );

		$response->set_data( $response_data );

		$standards = $request->get_param( 'standards' );

		if ( ! is_array( $standards ) ) {
			$standards = (array) $standards;
		}

		// Remove lighthouse if this is not a theme.
		$lh_key = array_search( 'lighthouse', $standards, true );
		if ( 'theme' !== $request->get_param( 'project_type' ) && false !== $lh_key ) {
			unset( $standards[ $lh_key ] );
		}

		// Merge existing and new `standards`.
		$merged_standards = array_unique( array_merge( $original_standards, $standards ) );

		// Remove any standards that don't belong.
		$merged_standards = Audit::filter_standards( $merged_standards );

		// Resetting array keys.
		$merged_standards = array_values( $merged_standards );

		// Update the standards properly.
		update_post_meta( $post_id, 'standards', $merged_standards );

		// Check to see if there are any new standards that require an audit request.
		$new_standards = array_diff( $standards, $original_standards );
		$new_standards = array_filter( $new_standards );
		if ( ! empty( $new_standards ) ) {
			$this->create_audit_request( $request, $post_id, Audit::filter_standards( $new_standards ) );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Update the meta for the audit.
	 *
	 * @todo Do we even need this, should it be removed?
	 *
	 * @param int              $post_id ID of an audit to update.
	 * @param \WP_REST_Request $request The update Request.
	 */
	private function update_audit_meta( $post_id, $request ) {

		$audit_meta = json_decode( $request->get_param( 'audit_meta' ), true );

		if ( ! empty( $audit_meta ) && is_array( $audit_meta ) ) {
			foreach ( $audit_meta as $key => $item ) {
				$terms = explode( ',', $item );

				$term_ids = array();
				foreach ( $terms as $term ) {
					$term = trim( $term );
					$slug = strtolower( str_replace( ' ', '-', $term ) );
					// @codingStandardsIgnoreStart - Ignore VIP discouraged usage if running against VIP standards.
					$the_term = term_exists( $term, $key );
					$the_term   = empty( $the_term ) ? wp_insert_term( $term, $key, array(
						'slug' => $slug,
					) ) : $the_term;
					// @codingStandardsIgnoreEnd
					$term_ids[] = $the_term['term_id'];
				}

				$term_ids = array_unique( array_map( 'intval', $term_ids ) );
				wp_set_object_terms( $post_id, $term_ids, $key );
			}
		}
	}

	/**
	 * Modify the REST HAL links.
	 *
	 * @param \WP_Post $post The Audit.
	 *
	 * @return array
	 */
	protected function prepare_links( $post ) {
		$links = parent::prepare_links( $post );

		/**
		 * For some strange reason `wp/v2` is hardcoded so we need to replace the namespace here to make terms work.
		 * We might as well cleanup the links more promoting the audit_{terms} and dropping wp:term.
		 */
		if ( isset( $links['https://api.w.org/term'] ) && is_array( $links['https://api.w.org/term'] ) ) {
			$links['tide:term'] = array();
			foreach ( $links['https://api.w.org/term'] as $key => $link ) {
				$links['https://api.w.org/term'][ $key ]['href'] = str_replace( 'wp/v2', $this->namespace, $link['href'] );
				$links[ $link['taxonomy'] ]                      = $links['https://api.w.org/term'][ $key ];
				unset( $links['https://api.w.org/term'][ $key ] );
			}
		}

		return $links;
	}

	/**
	 * Checks if a given request has access to read posts.
	 *
	 * @param  \WP_REST_Request $request Full details about the request.
	 *
	 * @return true|\WP_Error True if the request has read access, WP_Error
	 *                        object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		$allowed = parent::get_items_permissions_check( $request );

		return apply_filters( 'tide_api_audit_can_get_items', self::elevate_audit_client_permission( $allowed ), $this );
	}

	/**
	 * Checks if a post can be read.
	 * Correctly handles posts with the inherit status.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return bool Whether the post can be read.
	 */
	public function check_read_permission( $post ) {
		$allowed = parent::check_read_permission( $post );

		// Determine an item's visibility and allow users with the right capabilities to read this post.
		$visibility   = get_post_meta( $post->ID, 'visibility', true );
		$current_user = wp_get_current_user();
		$is_author    = (
			! is_wp_error( $current_user )
			&&
			absint( $post->post_author ) === absint( $current_user->ID )
			&&
			$current_user->has_cap( 'api_client' )
		);

		if ( 'private' === $visibility && ! ( $is_author || current_user_can( 'read_private_posts' ) ) ) {
			$allowed = false;
		}

		return apply_filters( 'tide_api_audit_can_read', self::elevate_audit_client_permission( $allowed ), $post );
	}

	/**
	 * Checks if a post can be edited.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return bool Whether the post can be edited.
	 */
	protected function check_update_permission( $post ) {
		$allowed = parent::check_update_permission( $post );

		return apply_filters( 'tide_api_audit_can_update', self::elevate_audit_client_permission( $allowed ), $post );
	}

	/**
	 * Checks if a post can be created.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return bool Whether the post can be created.
	 */
	protected function check_create_permission( $post ) {
		$allowed = parent::check_create_permission( $post );

		return apply_filters( 'tide_api_audit_can_create', self::elevate_audit_client_permission( $allowed ), $post );
	}

	/**
	 * Checks if a post can be deleted.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return bool Whether the post can be deleted.
	 */
	protected function check_delete_permission( $post ) {
		$allowed = parent::check_delete_permission( $post );

		return apply_filters( 'tide_api_audit_can_delete', self::elevate_audit_client_permission( $allowed ), $post );
	}

	/**
	 * Checks whether current user can assign all terms sent with the current
	 * request.
	 *
	 * @param \WP_REST_Request $request The request object with post and terms
	 *                                  data.
	 *
	 * @return bool Whether the current user can assign the provided terms.
	 */
	protected function check_assign_terms_permission( $request ) {
		$allowed = parent::check_assign_terms_permission( $request );

		return apply_filters( 'tide_api_audit_can_assign_terms', self::elevate_audit_client_permission( $allowed ), $request );
	}

	/**
	 * Checks if the current user is an audit_client and elevate permissions.
	 *
	 * @param bool $allowed Whether the current user is allowed to modify a post.
	 *
	 * @return bool Whether the current user is an audit_client.
	 */
	protected function elevate_audit_client_permission( $allowed ) {
		$current_user = wp_get_current_user();

		if ( ! is_wp_error( $current_user ) && $current_user->has_cap( 'audit_client' ) ) {
			$allowed = true;
		}

		return $allowed;
	}

	/**
	 * Only update Audit fields if client has permission to do so.
	 * We don't want owners to wipe out their audit reports.
	 *
	 * @param bool             $allowed    Is this action allowed to take place.
	 * @param object           $object     The object to update a field of.
	 * @param string           $field_name Name of the field to update.
	 * @param \WP_REST_Request $request    The request.
	 *
	 * @return bool Proceed with the update?
	 */
	public function filter_audit_fields( $allowed, $object, $field_name, $request ) {

		if ( 'reports' === $field_name ) {
			$reports   = $request->get_param( 'reports' );
			$standards = is_array( $reports ) ? array_keys( $reports ) : array();
			$standards = Audit::filter_standards( $standards );

			return ! empty( $standards ) && current_user_can( 'edit_others_posts' );
		}

		return $allowed;
	}

	/**
	 * Handle the `?tags` and `?project` query params.
	 *
	 * @param array            $args    WP_Query args.
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return mixed
	 */
	public function handle_custom_args( $args, $request ) {

		$tax_query = false;
		if ( isset( $_GET['project'] ) ) { // WPCS: input var okay. CSRF ok.
			$project   = explode( ',', sanitize_text_field( wp_unslash( $_GET['project'] ) ) ); // WPCS: input var okay. CSRF ok.
			$tax_query = array(
				array(
					'taxonomy' => 'audit_project',
					'field'    => 'name',
					'terms'    => $project,
				),
			);
		}
		$args = $this->add_tax_query( $args, $tax_query );

		return $args;
	}

	/**
	 * Add a tax query to the original tax queries (relation, AND)
	 *
	 * @param array $args      WP_Query args.
	 * @param array $tax_query The tax query to add.
	 *
	 * @return mixed
	 */
	public function add_tax_query( $args, $tax_query ) {

		if ( ! empty( $tax_query ) ) {
			if ( isset( $args['tax_query'] ) && ! empty( $args['tax_query'] ) ) { // WPCS: slow query ok.
				$args['tax_query'] = array_merge( // WPCS: slow query ok.
					array(
						'relation' => 'AND',
					),
					$args['tax_query'], // WPCS: slow query ok.
					$tax_query
				);
			} else {
				$args['tax_query'] = $tax_query; // WPCS: slow query ok.
			}
		}

		return $args;
	}

	/**
	 * Retrieves a single post.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or
	 *                                     WP_Error object on failure.
	 */
	public function get_item_altid( $request ) {

		$post = $this->get_altid_post( $request );

		$data     = $this->prepare_item_for_response( $post, $request );
		$response = rest_ensure_response( $data );

		if ( is_post_type_viewable( get_post_type_object( $post->post_type ) ) ) {
			$response->link_header(
				'alternate',
				get_permalink( $post->ID ),
				array(
					'type' => 'text/html',
				)
			);
		}

		return $response;
	}

	/**
	 * Get a post by its altid (checksum).
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_Error|\WP_Post
	 */
	public function get_altid_post( $request ) {

		$args = array(
			'post_type'  => $this->post_type,
			'meta_query' => array(),
		);

		if ( null === $request->get_param( 'checksum' ) && null === $request->get_param( 'project_slug' ) ) {
			return new \WP_Error(
				'invalid_key',
				'Invalid key for lookup.',
				array(
					'status' => 400,
				)
			);
		}

		if ( null !== $request->get_param( 'checksum' ) ) {
			$args['meta_key']   = 'checksum'; // WPCS: slow query ok.
			$args['meta_value'] = $request->get_param( 'checksum' ); // WPCS: slow query ok.
		}

		if ( null !== $request->get_param( 'project_client' ) ) {
			$user = get_user_by( 'login', $request->get_param( 'project_client' ) );
			if ( false === $user ) {
				return new \WP_Error(
					'tide_audit_invalid_project_client',
					__( 'Invalid project client.' ),
					array(
						'status' => 404,
					)
				);
			}
			$args['author'] = $user->ID;
		}

		if ( null !== $request->get_param( 'project_type' ) ) {
			$args['meta_query'][] = array(
				'key'     => 'project_type',
				'value'   => $request->get_param( 'project_type' ),
				'compare' => '=',
			);
		}

		if ( null !== $request->get_param( 'project_slug' ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'audit_project',
					'field'    => 'slug', // search by taxonomy slug.
					'terms'    => $request->get_param( 'project_slug' ),
				),
			);
		}

		if ( null !== $request->get_param( 'version' ) ) {
			$args['meta_query'][] = array(
				'key'     => 'version',
				'value'   => $request->get_param( 'version' ),
				'compare' => '=',
			);
		}

		if ( empty( $args['meta_query'] ) ) {
			unset( $args['meta_query'] );
		}

		$post = new \WP_Query( $args );

		if ( is_wp_error( $post ) || empty( $post ) || 0 === $post->post_count ) {
			$post = new \WP_Error(
				'rest_post_invalid_altid_lookup',
				__( 'Invalid post lookup.', 'tide-api' ),
				array(
					'status' => 404,
				)
			);
		} else {
			$post = array_shift( $post->posts );
		}

		return apply_filters( 'tide_api_get_altid_post', $post, $request );
	}

	/**
	 * Get the post ID based on altid provided.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return int|mixed|\WP_Post|\WP_REST_Response
	 */
	public function get_altid_post_id( $request ) {
		$post = $this->get_altid_post( $request );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return $post->ID;
	}

	/**
	 * Checks if a given request has access to read a post.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return bool|\WP_Error True if the request has read access for the item,
	 *                        WP_Error object otherwise.
	 */
	public function get_item_permissions_check_altid( $request ) {
		$request['id'] = $this->get_altid_post_id( $request );
		if ( is_wp_error( $request['id'] ) ) {
			return $request['id'];
		}

		return parent::get_item_permissions_check( $request );
	}

	/**
	 * Updates a single post.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or
	 *                                     WP_Error object on failure.
	 */
	public function update_item_altid( $request ) {
		$request['id'] = $this->get_altid_post_id( $request );
		if ( is_wp_error( $request['id'] ) ) {
			return $request['id'];
		}

		return parent::update_item( $request );
	}

	/**
	 * Checks if a given request has access to update a post.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return bool|\WP_Error True if the request has read access for the item,
	 *                        WP_Error object otherwise.
	 */
	public function update_item_permissions_check_altid( $request ) {
		$request['id'] = $this->get_altid_post_id( $request );
		if ( is_wp_error( $request['id'] ) ) {
			return $request['id'];
		}

		return parent::update_item_permissions_check( $request );
	}

	/**
	 * Deletes a single post.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or
	 *                                     WP_Error object on failure.
	 */
	public function delete_item_altid( $request ) {
		$request['id'] = $this->get_altid_post_id( $request );
		if ( is_wp_error( $request['id'] ) ) {
			return $request['id'];
		}

		return parent::delete_item( $request );
	}

	/**
	 * Checks if a given request has access to delete a post.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return bool|\WP_Error True if the request has read access for the item,
	 *                        WP_Error object otherwise.
	 */
	public function delete_item_permissions_check_altid( $request ) {
		$request['id'] = $this->get_altid_post_id( $request );
		if ( is_wp_error( $request['id'] ) ) {
			return $request['id'];
		}

		return parent::delete_item_permissions_check( $request );
	}
}
