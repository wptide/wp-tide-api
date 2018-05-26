<?php
/**
 * This class is responsible for adding refresh tokens to users.
 *
 * @package WP_Tide_API\Authentication
 */

namespace WP_Tide_API\Authentication;

use Firebase\JWT\JWT;
use WP_Tide_API\Base;
use WP_Tide_API\Plugin;

/**
 * Class User_Refresh_Token
 */
class User_Refresh_Token extends Base {

	const JWT_REFRESH_EXPIRATION = 31536000; // 1 Year

	/**
	 * Set this to true if authentication was POST'ed with a refresh token to avoid
	 * refresh token being generated.
	 *
	 * @var bool
	 */
	private static $refresh_authentication = false;

	/**
	 * Secure auth key.
	 *
	 * @var string
	 */
	private $secure_auth_key = '';

	/**
	 * User_Refresh_Token constructor.
	 *
	 * @param Plugin $plugin The TIDE API plugin.
	 * @param string $secure_auth_key The secure auth key.
	 */
	public function __construct( Plugin $plugin, $secure_auth_key = '' ) {
		parent::__construct( $plugin );

		$this->secure_auth_key = $secure_auth_key;

		// Use SECURE_AUTH_KEY defined in wp-config.php as secret.
		if ( '' === $this->secure_auth_key && defined( 'SECURE_AUTH_KEY' ) ) {
			$this->secure_auth_key = SECURE_AUTH_KEY;
		}
	}

	/**
	 * Add a refresh token to the JWT token.
	 *
	 * @filter tide_api_jwt_token_response 10, 4
	 *
	 * @param mixed|\WP_REST_Response $response The original response.
	 * @param \WP_REST_Request        $request  The REST request.
	 * @param mixed                   $client   The authentication client.
	 * @param mixed                   $token    The token.
	 *
	 * @return mixed
	 */
	public function append_refresh_token( $response, $request, $client, $token ) {

		/**
		 * Don't regenerate the refresh token if it was used for authentication.
		 */
		if ( true === static::$refresh_authentication ) {
			return $response;
		}

		/**
		 * If we don't have a secret, just return the original response (which should be an error).
		 */
		$secret = $this->get_secret();
		if ( is_wp_error( $secret ) ) {
			return $response;
		}

		/**
		 * Build the JWT refresh token.
		 */
		$iat = time(); // Token issued at.
		$iss = get_bloginfo( 'url' ); // Token issued by.
		$exp = $iat + (int) apply_filters( 'tide_api_jwt_token_expiration', static::JWT_REFRESH_EXPIRATION ); // Token expiry.

		/**
		 * Get client type.
		 */
		$type = isset( $client->type ) ? $client->type : 'wp_user';

		/**
		 * JWT refresh token payload.
		 */
		$payload = array(
			'iat'  => $iat,
			'iss'  => $iss,
			'exp'  => $exp,
			'data' => array(
				'token_type' => 'refresh',
				'client'     => array(
					'id'   => $client->ID,
					'type' => $type,
				),
			),
		);

		/**
		 * Generate JWT token.
		 */
		$refresh_token = JWT::encode( $payload, $secret );

		/**
		 * If a client is a WP user then try to get an existing token or replace if expired (or missing).
		 */
		if ( 'wp_user' === $type ) {

			$user_refresh_token        = get_user_meta( $client->ID, 'tide_api_refresh_token', true );
			$user_refresh_token_decode = empty( $user_refresh_token ) ? false : JWT::decode( $user_refresh_token, $secret, array( 'HS256' ) );

			if ( time() > $user_refresh_token_decode->exp ) {
				update_user_meta( $client->ID, 'tide_api_refresh_token', $refresh_token );
			} else {
				$refresh_token = $user_refresh_token;
			}
		}

		do_action( 'tide_api_jwt_refresh_token_generated', $refresh_token, $client );

		$response['refresh_token'] = $refresh_token;

		return $response;
	}

	/**
	 * Add Refresh token as authentication method.
	 *
	 * @filter tide_api_authenticate_client 10, 2
	 *
	 * @param mixed            $client  The requesting client.
	 * @param \WP_REST_Request $request The HTTP request.
	 *
	 * @return mixed
	 */
	public function authenticate_with_refresh_token( $client, \WP_REST_Request $request ) {

		if ( isset( $this->plugin->components ) && ! empty( $this->plugin->components['jwt_auth'] ) ) {
			$jwt_auth = $this->plugin->components['jwt_auth'];
		} else {
			return $client;
		}

		/**
		 * Check to see if an Authorization header was posted.
		 */
		if ( JWT_Auth::class === get_class( $jwt_auth ) && ! is_wp_error( $jwt_auth->get_auth_header() ) ) {

			$token = $jwt_auth->get_token( $jwt_auth->get_auth_header() );

			/**
			 * Is it a valid token?
			 */
			if ( $jwt_auth->validate_token( $token ) ) {
				$encoded_token = $token;
				$token         = JWT::decode( $token, $jwt_auth->get_secret(), array( 'HS256' ) );

				/**
				 * Is it a refresh token and do we have client data?
				 */
				if ( isset( $token->data->token_type ) && 'refresh' === $token->data->token_type && isset( $token->data->client ) && 'wp_user' === $token->data->client->type ) {

					// Create ID alias for id.
					$token->data->client->ID = $token->data->client->id;
					$client_refresh_token    = get_user_meta( $token->data->client->ID, 'tide_api_refresh_token', true );

					/**
					 * Tokens must match to proceed.
					 */
					if ( $client_refresh_token === $encoded_token ) {
						static::$refresh_authentication = true;
						return $token->data->client;
					}
				}
			}
		}

		return $client;
	}

	/**
	 * Get SECURE_AUTH_KEY to use as JWT token secret.
	 *
	 * @return string|\WP_Error
	 */
	public function get_secret() {

		if ( $this->secure_auth_key ) {
			$secret = $this->secure_auth_key;
		} else {
			$secret = new \WP_Error(
				'rest_auth_key',
				__( 'Secret key was not defined.', 'tide-api' ),
				array( 'status' => 403 )
			);
		}

		return $secret;
	}
}
