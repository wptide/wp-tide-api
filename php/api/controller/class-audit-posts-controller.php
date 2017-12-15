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
use WP_Tide_API\Utility\Audit_Tasks;

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

	const PROJECT_PATTERN = '(?P<project>[\w-]{1,32})';

	const SLUG_PATTERN = '(?P<slug>[\w-]+)';

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
		add_filter( 'tide_api_rest_do_field_update', array(
			$this,
			'filter_audit_fields',
		), 10, 4 );
		add_filter( 'rest_audit_query', array(
			$this,
			'handle_custom_args',
		), 10, 2 );
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @see    register_rest_route()
	 */
	public function register_routes() {
		parent::register_routes();

		$get_item_args = array(
			'context' => $this->get_context_param( array(
				'default' => 'view',
			) ),
		);

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/' . static::CHECKSUM_PATTERN, array(
			'args'   => array(
				'altid' => array(
					'description' => __( 'An alternate unique id to query on (e.g. checksum)' ),
					'type'        => 'string',
				),
			),
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item_altid' ),
				'permission_callback' => array(
					$this,
					'get_item_permissions_check_altid',
				),
				'args'                => $get_item_args,
			),
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item_altid' ),
				'permission_callback' => array(
					$this,
					'update_item_permissions_check_altid',
				),
				'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::EDITABLE ),
			),
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item_altid' ),
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
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/' . static::PROJECT_PATTERN, array(
			'args'   => array(
				'project' => array(
					'description' => __( 'The users slug.' ),
					'type'        => 'string',
				),
			),
			array(
				'methods'  => \WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_audits' ),
				'args'     => $get_item_args,
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/' . static::PROJECT_PATTERN . '/' . static::SLUG_PATTERN, array(
			'args'   => array(
				'project' => array(
					'description' => __( 'The users slug.' ),
					'type'        => 'string',
				),
				'slug'    => array(
					'description' => __( 'The plugin slug as per taxonomy.' ),
					'type'        => 'string',
				),
			),
			array(
				'methods'  => \WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_audits' ),
				'args'     => $get_item_args,
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

	}

	/**
	 * Retrieve the audits for the user.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return mixed
	 */
	public function get_audits( $request ) {

		$is_single = false;
		$args      = array(
			'post_type' => $this->post_type,
			'order'     => 'DESC',
			// @todo: Make this ensure it's the latest version.
			'orderby'   => 'post_date',
		);

		if ( null !== $request->get_param( 'project' ) ) {
			$user = get_user_by( 'login', $request->get_param( 'project' ) );
			if ( false === $user ) {
				return new \WP_Error( 'tide_audit_invalid_project', __( 'Invalid project.' ), array(
					'status' => 404,
				) );
			}
			$args['author'] = $user->ID;
		}
		if ( null !== $request->get_param( 'slug' ) ) {

			$args['tax_query'] = array(
				array(
					'taxonomy' => 'audit_project',
					'field'    => 'slug',
					'terms'    => $request->get_param( 'slug' ),
				),
			);
			// Only a single one.
			$is_single = true;
		}

		if ( null !== $request->get_param( 'page' ) ) {
			$args['paged'] = $request->get_param( 'page' );
		}

		if ( null !== $request->get_param( 'search' ) ) {
			$args['s'] = $request->get_param( 'search' );
		}

		// version.
		if ( null !== $request->get_param( 'version' ) ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'version',
					'value'   => $request->get_param( 'version' ),
					'compare' => '=',
				),
			);
			$is_single          = true;
		}
		$args       = apply_filters( "rest_{$this->post_type}_query", $args, $request );
		$query_args = $this->prepare_items_query( $args, $request );

		$posts_query  = new \WP_Query();
		$query_result = $posts_query->query( $query_args );
		$posts        = array();

		foreach ( $query_result as $post ) {
			if ( ! $this->check_read_permission( $post ) ) {
				continue;
			}

			$data    = $this->prepare_item_for_response( $post, $request );
			$posts[] = $this->prepare_response_for_collection( $data );

		}

		if ( true === $is_single ) {
			if ( empty( $posts ) ) {
				return new \WP_Error( 'tide_audit_invalid_item', __( 'Invalid item.' ), array(
					'status' => 404,
				) );
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

		$removed_fields = apply_filters( 'tide_api_response_removed_fields', array(
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
			'project', // Hide the rest field in the response.
		) );

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

		$response      = false;
		$is_fill_error = $this->fill_data_from_wp_org_api( $request );
		if ( $is_fill_error instanceof \WP_Error ) {
			$response = $is_fill_error;
		}

		$source_url     = $request->get_param( 'source_url' );
		$source_type    = $request->get_param( 'source_type' );
		$standards      = $request->get_param( 'standards' );
		$checksum       = $request->get_param( 'checksum' );
		$request_client = $request->get_param( 'request_client' );

		if ( ! is_array( $standards ) ) {
			$standards = explode( ',', $standards );
			$standards = array_filter( $standards );
		}

		// Remove any standards that don't belong.
		$standards = Audit::filter_standards( $standards );

		// Resetting array keys.
		$standards = array_values( $standards );

		if ( ( empty( $source_type ) || empty( $source_url ) ) && ! ( $response instanceof \WP_Error ) ) {
			$response = new \WP_Error( 'insufficient_parameters', __( 'Please check your request and ensure you have supplied all required parameters.', 'tide-api' ), array(
				'status' => 400,
			) );
		}

		// Check that the source URL is valid.
		$ext = substr( $source_url, strrpos( $source_url, '.' ) + 1 );
		if ( ! in_array( $ext, $this->allowed_extensions, true ) ) {
			$response = new \WP_Error( 'rest_invalid_source_url', __( 'Please check that the source URL is .zip or .git.', 'tide-api' ), array(
				'status' => 400,
			) );
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
		// if this current request does not have any results.
		if ( empty( $checksum ) ) {
			$this->filter_available_standards( $standards );
			$this->create_audit_request( $request, $post_id, $standards );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Send an audit task to the job queue.
	 *
	 * NOTE:
	 * Standards are passed here just to populate the audit task arguments.
	 * Audit::executable_audit_fields() will pick up the standards via a filter from $request->get_param('standards').
	 * This filter is usually run before calling create_audit_request.
	 *
	 * @see ::filter_available_standards( $standards )
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @param mixed            $post The post or post->ID of an audit that needs to be audited.
	 * @param array            $standards The audit standards.
	 */
	public function create_audit_request( $request, $post = false, $standards = array() ) {

		if ( is_object( $post ) && isset( $post->ID ) ) {
			$post = $post->ID;
		}

		// Fallback request client.
		$current_user    = wp_get_current_user();
		$fallback_client = '';
		if ( ( $current_user instanceof \WP_User ) ) {
			$fallback_client = $current_user->user_login;
		}

		$title          = $request->get_param( 'title' ) ?? '';
		$content        = $request->get_param( 'content' ) ?? '';
		$source_url     = $request->get_param( 'source_url' ) ?? '';
		$source_type    = $request->get_param( 'source_type' ) ?? '';
		$request_client = $request->get_param( 'request_client' ) ?? $fallback_client;
		$force          = $request->get_param( 'force' ) ?? false;
		$visibility     = $request->get_param( 'visibility' ) ?? 'private';
		$slug           = $request->get_param( 'slug' ) ?? '';

		// Cant go much further without these.
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

		$task_args = array(
			'response_api_endpoint' => $response_api_endpoint,
			'title'                 => $title,
			'content'               => $content,
			'source_url'            => $source_url,
			'source_type'           => $source_type,
			'request_client'        => $request_client,
			'force'                 => $force,
			'visibility'            => $visibility,
			'standards'             => $standards,
			'audits'                => array(),
		);

		if ( true === $force ) {
			$task_args['force'] = true;
		}

		if ( ! empty( $slug ) ) {
			$task_args['slug'] = $slug;
		}

		$audits = Audit::executable_audit_fields();
		foreach ( array_keys( $audits ) as $audit ) {
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

			list( $x, $y, $type, $standard ) = explode( '_', $audit );

			$options = array(
				'standard' => $standard,
				'report'   => 'json',
			);

			if ( 'phpcompatibility' === $standard ) {

				// Lowest version set to WordPress minimum version.
				$options['runtime-set'] = 'testVersion 5.2-';
			} else {

				// Ignore 3rd-party directories.
				$options['ignore'] = '*/vendor/*,*/node_modules/*';
			}

			$args = array(
				'type'    => $type,
				'options' => $options,
			);

			$task_args['audits'][] = $args;
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
			$this->filter_available_standards( $new_standards );
			$this->create_audit_request( $request, $post_id, $new_standards );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Update the meta for the audit.
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
					// @codingStandardsIgnoreEnd
					$the_term   = empty( $the_term ) ? wp_insert_term( $term, $key, array(
						'slug' => $slug,
					) ) : $the_term;
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

		return apply_filters( 'tide_api_audit_can_get_items', $allowed, $this );
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

		/**
		 * Determine an item's visibility.
		 * Still allowed users with the right capabilities to read this post.
		 */
		$visibility   = get_post_meta( $post->ID, 'visibility', true );
		$current_user = wp_get_current_user();
		$is_author    = $post->post_author === $current_user->ID && in_array( 'api_client', (array) $current_user->roles, true );

		if ( 'public' !== $visibility && ( ! $is_author || ! current_user_can( 'read_private_posts' ) ) ) {
			$allowed = false;
		}

		return apply_filters( 'tide_api_audit_can_read', $allowed, $post );
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

		return apply_filters( 'tide_api_audit_can_update', $allowed, $post );
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

		return apply_filters( 'tide_api_audit_can_create', $allowed, $post );
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

		return apply_filters( 'tide_api_audit_can_delete', $allowed, $post );
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

		return apply_filters( 'tide_api_audit_can_assign_terms', $allowed, $request );
	}

	/**
	 * Only update Audit fields if client has permission to do so.
	 * We don't want owners to wipe out their audit results.
	 *
	 * @param bool             $allowed    Is this action allowed to take place.
	 * @param object           $object     The object to update a field of.
	 * @param string           $field_name Name of the field to update.
	 * @param \WP_REST_Request $request    The request.
	 *
	 * @return bool Proceed with the update?
	 */
	public function filter_audit_fields( $allowed, $object, $field_name, $request ) {

		if ( 'results' === $field_name ) {
			$results   = $request->get_param( 'results' );
			$standards = is_array( $results ) ? array_keys( $results ) : array();
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
		if ( isset( $_GET['tags'] ) ) { // WPCS: input var okay. CSRF ok.
			$tags      = explode( ',', sanitize_text_field( wp_unslash( $_GET['tags'] ) ) ); // WPCS: input var okay. CSRF ok.
			$tax_query = array(
				array(
					'taxonomy' => 'audit_tag',
					'field'    => 'name',
					'terms'    => $tags,
				),
			);
		}
		$args = $this->add_tax_query( $args, $tax_query );

		$tax_query = false;
		if ( isset( $_GET['project'] ) ) { // WPCS: input var okay. CSRF ok.
			$tags      = explode( ',', sanitize_text_field( wp_unslash( $_GET['project'] ) ) ); // WPCS: input var okay. CSRF ok.
			$tax_query = array(
				array(
					'taxonomy' => 'audit_project',
					'field'    => 'name',
					'terms'    => $tags,
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
	private function add_tax_query( $args, $tax_query ) {

		if ( ! empty( $tax_query ) ) {
			if ( isset( $args['tax_query'] ) && ! empty( $args['tax_query'] ) ) { // WPCS: slow query ok.
				$args['tax_query'] = array_merge( array( // WPCS: slow query ok.
					'relation' => 'AND',
				), $args['tax_query'], $tax_query ); // WPCS: slow query ok.
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
			$response->link_header( 'alternate', get_permalink( $post->ID ), array(
				'type' => 'text/html',
			) );
		}

		return $response;
	}

	/**
	 * Get a post by its altid (checksum).
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return mixed|\WP_REST_Response
	 */
	private function get_altid_post( $request ) {

		$error_data = array(
			'status' => 404,
		);
		$error      = new \WP_Error( 'rest_post_invalid_altid_lookup', __( 'Invalid post lookup.', 'tide-api' ), $error_data );

		$meta_key = ! empty( $request['checksum'] ) ? 'checksum' : false;

		if ( false === $meta_key ) {
			return new \WP_Error( 'invalid_key', 'Invalid key for lookup.' );
		}

		$args = array(
			'meta_key'   => $meta_key, // WPCS: slow query ok.
			'meta_value' => $request[ $meta_key ], // WPCS: slow query ok.
			'post_type'  => 'audit',
		);

		$post = new \WP_Query( $args );

		if ( is_wp_error( $post ) || empty( $post ) || 0 === $post->post_count ) {
			return $error;
		}

		return array_shift( $post->posts );
	}

	/**
	 * Get the post ID based on altid provided.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return int|mixed|\WP_Post|\WP_REST_Response
	 */
	private function get_altid_post_id( $request ) {
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

	/**
	 * Only perform audits on the given standards.
	 *
	 * @param array $standards Provided standards.
	 */
	public function filter_available_standards( $standards = array() ) {
		// Filter the available standards to only include the requested standards (if given).
		if ( ! empty( $standards ) ) {
			$standards = is_array( $standards ) ? $standards : explode( ',', $standards );
			$standards = array_filter( $standards ); // Get rid of empty values.
			add_filter( 'tide_api_executable_audits', function ( $audits ) use ( $standards ) {
				$audits = array(); // Clear original audit list.

				foreach ( $standards as $standard ) {
					$standard            = str_replace( array( 'audit_', '_audit_' ), '', strtolower( $standard ) ); // Get rid of legacy "audit_" and "_audit_" just in case.
					$audits[ $standard ] = array();
				}

				return $audits;
			} );
		}
	}

	/**
	 * Fills data from wp.org plugin/theme api.
	 *
	 * @param \WP_REST_Request $request rest api request.
	 *
	 * @return \WP_Error|bool
	 */
	public function fill_data_from_wp_org_api( \WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );
		$type = $request->get_param( 'type' );
		if ( empty( $slug ) || empty( $type ) ) {
			return false;
		}
		$params_mapping = array(
			'name'          => 'title',
			'download_link' => 'source_url',
			'sections'      => array(
				'description' => 'content',
			),
		);
		$http_args      = array(
			'body' => array(
				'request' => serialize( (object) array( // @codingStandardsIgnoreLine
					'slug'   => $slug,
					'fields' => array(
						'versions' => true,
						// For theme this is default to false.
					),
				) ),
			),
		);
		if ( 'plugin' === $type ) {
			$url                         = 'http://api.wordpress.org/plugins/info/1.0/';
			$http_args['body']['action'] = 'plugin_information';
		} elseif ( 'theme' === $type ) {
			$url                         = 'https://api.wordpress.org/themes/info/1.0/';
			$http_args['body']['action'] = 'theme_information';
		} else {
			return new \WP_Error( 'invalid_type', __( "Type should be either 'plugin' or 'theme'", 'tide-api' ), array(
				'status' => 400,
			) );
		}
		// Send request to get information from WP.org.
		$res = $this->get_data_from_wp_api( $url, $http_args );
		if ( $res instanceof \WP_Error ) {
			return $res;
		}

		// Check if version request.
		if ( ! empty( $request->get_param( 'version' ) ) ) {
			$version = $request->get_param( 'version' );
			if ( empty( $request->get_param( 'source_url' ) ) ) {
				if ( isset( $res->versions[ $version ] ) ) {
					$download_url           = wp_slash( $res->versions[ $version ] );
					$_POST['source_url']    = $download_url;
					$_REQUEST['source_url'] = $download_url; // WPCS: input var okay. // WPCS: CSRF ok.
					unset( $params_mapping['download_link'] ); // As source_url is already set we don't need this mapping.
				} else {
					return new \WP_Error( 'invalid_version', __( 'Specified version is not found', 'tide-api' ), array(
						'status' => 400,
					) );
				}
			}
		}

		// WP.org always gives zip file.
		if ( empty( $request->get_param( 'source_type' ) ) ) {
			$_POST['source_type']    = 'zip';
			$_REQUEST['source_type'] = 'zip';
		}

		// Get data according to TIDE request.
		foreach ( $params_mapping as $org_api_key => $request_key ) {
			if ( is_array( $request_key ) ) {
				foreach ( $request_key as $api_key_temp => $request_key_temp ) {
					if ( empty( $request->get_param( $request_key_temp ) ) && ! empty( $res->{$org_api_key[ $api_key_temp ]} ) ) {
						$_POST[ $request_key_temp ]    = $res->{$org_api_key[ $api_key_temp ]};
						$_REQUEST[ $request_key_temp ] = $res->{$org_api_key[ $api_key_temp ]};
					}
				}
			} elseif ( empty( $request->get_param( $request_key ) ) && ! empty( $res->{$org_api_key} ) ) {
				$_POST[ $request_key ]    = $res->{$org_api_key};
				$_REQUEST[ $request_key ] = $res->{$org_api_key};
			}
		}
		$request->set_body_params( wp_unslash( $_POST ) ); // WPCS: input var okay. // WPCS: CSRF ok.

		return true;
	}

	/**
	 * Get data from wp_api and set it according to param mappings.
	 *
	 * @param string $url       wp.org api url to hit.
	 * @param array  $http_args Http arguments.
	 *
	 * @return bool|\WP_Error
	 * @internal param \WP_REST_Request $request Rest api request.
	 * @internal param array $params_mapping mapping of params.
	 */
	public function get_data_from_wp_api( string $url, array $http_args ) {
		if ( wp_http_supports( array( 'ssl' ) ) ) {
			$url = set_url_scheme( $url, 'https' );
		}
		$http_args = array_merge( array(
			'timeout' => 15,
		), $http_args );

		$http_request = wp_remote_post( $url, $http_args ); // @codingStandardsIgnoreLine
		if ( $http_request instanceof \WP_Error ) {
			return new \WP_Error( 'wp_api_issue', __( 'WordPress API is having issue while pulling data from slug, try with full request.', 'tide-api' ), array(
				'status' => 400,
			) );
		}
		$res = maybe_unserialize( wp_remote_retrieve_body( $http_request ) );
		if ( ! is_object( $res ) && ! is_array( $res ) ) {
			return new \WP_Error( 'invalid_slug', __( 'Slug you specified is invalid.', 'tide-api' ), array(
				'status' => 400,
			) );
		}

		return $res;
	}
}
