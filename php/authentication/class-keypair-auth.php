<?php
/**
 * This class is responsible for adding API key:secret pairs to a user.
 *
 * This allows a user to be identified by via REST without using login credentials.
 *
 * A POST request is made to {api_prefix}/tide/v1/auth with the following fields:
 *
 *   * `api_key` : User's API key
 *   * `api_secret` : User's API secret
 *
 * @package WP_Tide_API\Authentication
 */

namespace WP_Tide_API\Authentication;

use WP_Tide_API\Base;
use WP_Tide_API\Plugin;
use WP_Tide_API\Utility;

/**
 * Class Keypair_Auth
 */
class Keypair_Auth extends Base {

	/**
	 * API namespace to use for creating user keypairs.
	 *
	 * Format: '{plugin namespace}/{plugin version}'
	 *
	 * @var string
	 */
	private $namespace;

	/**
	 * The base route for creating keypairs.
	 *
	 * @var string
	 */
	private $keypair_base = 'keypair/(?P<id>[\d]+)';

	/**
	 * Keypair_Auth constructor.
	 *
	 * Setup constructor and retrieve plugin API namespace and version from plugin info.
	 * This is defined in the Plugin header as `API Namespace` and `API Version`.
	 *
	 * @param Plugin $plugin The TIDE API plugin.
	 */
	public function __construct( Plugin $plugin ) {
		parent::__construct( $plugin );

		$this->namespace = sprintf( '%s/%s', $plugin->info['api_namespace'], $plugin->info['api_version'] );
	}

	/**
	 * Add new KeyPair Authentication fields to user profile.
	 *
	 * @todo   Add ability to regenerate API credentials.
	 *
	 * @action show_user_profile
	 * @action edit_user_profile
	 *
	 * @param \WP_User $user The user who's profile is getting viewed.
	 */
	public function user_profile_fields( $user ) {

		/**
		 * Get user's API key (generate if not found)
		 */
		// @codingStandardsIgnoreStart - Skipping VIP sniffs.
		$api_key = get_user_meta( $user->ID, 'tide_api_user_key', true );
		if ( empty( $api_key ) ) {
			$api_key = $user->ID . wp_generate_password( 24, false );
			update_user_meta( $user->ID, 'tide_api_user_key', $api_key );
		}
		// @codingStandardsIgnoreEnd

		/**
		 * Get user's API secret (generate if not found)
		 */
		// @codingStandardsIgnoreStart - Skipping VIP sniffs.
		$api_secret = get_user_meta( $user->ID, 'tide_api_user_secret', true );
		if ( empty( $api_secret ) ) {
			$api_secret = str_replace( '#', '$', wp_generate_password( 32 ) );
			update_user_meta( $user->ID, 'tide_api_user_secret', $api_secret );
		}
		// @codingStandardsIgnoreEnd

		?>
		<h2><?php esc_html_e( 'Tide API Credentials', 'tide-api' ); ?></h2>
		<table class="form-table">
			<tbody>
			<tr>
				<th>
					<label for="tide-api-key"><?php esc_html_e( 'API Key', 'tide-api' ); ?></label>
				</th>
				<td>
					<input
						class="regular-text"
						name="tide-api-key"
						id="tide-api-key"
						type="text"
						readonly
						placeholder="<?php esc_attr_e( 'Please generate credentials', 'tide-api' ); ?>"
						value="<?php echo esc_html( $api_key ); ?>"
					/>
				</td>
			</tr>
			<tr>
				<th>
					<label for="tide-api-secret"><?php esc_html_e( 'API Secret', 'tide-api' ); ?></label>
				</th>
				<td>
					<input
						class="regular-text"
						name="tide-api-secret"
						id="tide-api-secret"
						type="text"
						readonly
						value="<?php echo esc_html( $api_secret ); ?>"
					/>
				</td>
			</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Authenticate the key-pair if API key and API secret is provided and return the user.
	 *
	 * If not authenticated, send back the original $client value to allow other authentication methods to attempt
	 * authentication.
	 *
	 * @filter tide_api_authenticate_client
	 *
	 * @param mixed            $client  The client that's being authenticated.
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return array|bool|mixed
	 */
	public function authenticate_key_pair( $client, \WP_REST_Request $request ) {

		/**
		 * Get request parameters.
		 */
		$key    = sanitize_text_field( wp_unslash( $request->get_param( 'api_key' ) ) );
		$secret = sanitize_text_field( wp_unslash( $request->get_param( 'api_secret' ) ) );

		/**
		 * Retrieve a user if a valid key is given; or send back original value.
		 */
		$user = get_users( array(
			'meta_key'   => 'tide_api_user_key', // WPCS: slow query ok.
			'meta_value' => $key, // WPCS: slow query ok.
		) );

		$user = is_array( $user ) && ! empty( $user ) ? array_shift( $user ) : false;
		if ( false === $user ) {
			return $client;
		}

		/**
		 * Determine if provided secret and user secret matches; or send back original value.
		 */
		// @codingStandardsIgnoreStart - Skipping VIP sniffs.
		$user_secret = get_user_meta( $user->ID, 'tide_api_user_secret', true );
		// @codingStandardsIgnoreEnd
		if ( $user_secret !== $secret ) {
			return $client;
		}

		/**
		 * Return the WP_User object minus some security sensitive fields.
		 */
		unset( $user->data->user_pass );
		unset( $user->data->user_nicename );
		unset( $user->data->user_activation_key );
		unset( $user->data->user_status );
		unset( $user->data->user_url );
		unset( $user->cap_key );
		unset( $user->filter );

		wp_set_current_user( $user->ID );

		return $user;
	}

	/**
	 * Register key-pair endpoints.
	 *
	 * @action rest_api_init
	 */
	public function register_keypair_routes() {

		/**
		 * POST endpoint for generating new keypairs.
		 */
		register_rest_route( $this->namespace, sprintf( '/%s', $this->keypair_base ), array(
			array(
				'methods'  => \WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'generate_keypair' ),
			),
			array(
				'methods'  => \WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_keypair' ),
			),
		) );
	}

	/**
	 * Generate new API key-pair for user.
	 *
	 * @param \WP_REST_Request $request The requests.
	 *
	 * @return array|\WP_Error The key-pair or error.
	 */
	public function generate_keypair( \WP_REST_Request $request ) {

		$user_id = (int) $request->get_param( 'id' );
		$user    = get_user_by( 'id', $user_id );

		if ( ! current_user_can( 'edit_users' ) || is_wp_error( $user ) ) {
			return new \WP_Error( 'rest_user_error',
				__( 'Invalid user request.', 'tide-api' ),
				array(
					'status' => 403,
				)
			);
		}

		$api_key    = $user->ID . wp_generate_password( 24, false );
		$api_secret = wp_generate_password( 32 );
		// @codingStandardsIgnoreStart - Skipping VIP sniffs.
		update_user_meta( $user->ID, 'tide_api_user_key', $api_key );
		update_user_meta( $user->ID, 'tide_api_user_secret', $api_secret );
		// @codingStandardsIgnoreEnd

		/**
		 * Return response containing the API key-pair.
		 */
		return array(
			'api_key'   => $api_key,
			'ap_secret' => $api_secret,
		);
	}

	/**
	 * Get API key-pair for user.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return array|\WP_Error An API keypair or error.
	 */
	public function get_keypair( \WP_REST_Request $request ) {

		$user_id = (int) $request->get_param( 'id' );
		$user    = get_user_by( 'id', $user_id );

		if ( ! current_user_can( 'edit_users' ) || is_wp_error( $user ) ) {
			return new \WP_Error( 'rest_user_error',
				__( 'Invalid user request.', 'tide-api' ),
				array(
					'status' => 403,
				)
			);
		}

		// @codingStandardsIgnoreStart - Skipping VIP sniffs.
		$api_key    = get_user_meta( $user->ID, 'tide_api_user_key', true );
		$api_secret = get_user_meta( $user->ID, 'tide_api_user_secret', true );
		// @codingStandardsIgnoreEnd

		/**
		 * Return response containing the API key-pair.
		 */
		return array(
			'api_key'   => $api_key,
			'ap_secret' => $api_secret,
		);
	}
}
