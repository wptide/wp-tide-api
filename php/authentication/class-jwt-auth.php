<?php
/**
 * This file is responsible for the managing of JSON Web Tokens (JWT).
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\Authentication;

use WP_Tide_API\Base;
use WP_Tide_API\Plugin;
use \Firebase\JWT\JWT;
use \WP_REST_Server;
use \WP_REST_Request;
use \WP_Error;

/**
 * Class JWT_Auth
 */
class JWT_Auth extends Base {

	/**
	 * JWT token expiration in seconds.
	 */
	const JWT_EXPIRATION = 2592000; // 30 days.

	/**
	 * API namespace to use for JWT authentication.
	 *
	 * Format: '{plugin namespace}/{plugin version}'
	 *
	 * @var string
	 */
	private $namespace;

	/**
	 * The base route for JWT authentication.
	 *
	 * @var string
	 */
	private $base = 'auth';

	/**
	 * Secure auth key.
	 *
	 * @var string
	 */
	private $secure_auth_key = '';

	/**
	 * JWT_Auth constructor.
	 *
	 * Setup constructor and retrieve plugin API namespace and version from plugin info.
	 * This is defined in the Plugin header as `API Namespace` and `API Version`.
	 *
	 * @param Plugin $plugin The TIDE API plugin.
	 * @param string $secure_auth_key The secure auth key.
	 */
	public function __construct( Plugin $plugin, $secure_auth_key = '' ) {
		parent::__construct( $plugin );

		$this->namespace       = sprintf( '%s/%s', $plugin->info['api_namespace'], $plugin->info['api_version'] );
		$this->secure_auth_key = $secure_auth_key;

		// Use SECURE_AUTH_KEY defined in wp-config.php as secret.
		if ( '' === $this->secure_auth_key && defined( 'SECURE_AUTH_KEY' ) ) {
			$this->secure_auth_key = SECURE_AUTH_KEY;
		}
	}

	/**
	 * Register JWT authentication endpoints.
	 *
	 * @action rest_api_init
	 */
	public function register_jwt_routes() {

		/**
		 * POST endpoint for generating new tokens.
		 */
		register_rest_route( $this->namespace, sprintf( '/%s', $this->base ), array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'generate_token' ),
		) );
	}

	/**
	 * Authenticate the user and generate a JWT token.
	 *
	 * @param WP_REST_Request $request The authentication request.
	 *
	 * @return array|string|WP_Error
	 */
	public function generate_token( WP_REST_Request $request ) {

		/**
		 * Get secret to encode the JWT tokens with.
		 */
		$secret = $this->get_secret();
		if ( is_wp_error( $secret ) ) {
			return $secret;
		}

		/**
		 * Authenticate the client.
		 *
		 * Regardless the authentication method, a $client must be an object and must have
		 * an ID property to identify the client and a `type` property to identify the type of client
		 * (or wp_user will be used).
		 */
		$client = apply_filters( 'tide_api_authenticate_client', false, $request );

		/**
		 * If alternate method for authentication is not provided then expect a username and password.
		 */
		if ( false === $client ) {
			$username = sanitize_text_field( wp_unslash( $request->get_param( 'username' ) ) );
			$password = sanitize_text_field( wp_unslash( $request->get_param( 'password' ) ) );

			// Authenticate the WordPress user.
			$client = wp_authenticate( $username, $password );
		}

		if ( false === $client || is_wp_error( $client ) ) {
			return new WP_Error(
				'rest_auth_invalid_credentials',
				__( 'Invalid credentials provided.', 'tide-api' ),
				array( 'status' => 403 )
			);
		}

		/**
		 * Build the JWT token.
		 */
		$iat = time(); // Token issued at.
		$iss = get_bloginfo( 'url' ); // Token issued by.
		$exp = $iat + (int) apply_filters( 'tide_api_jwt_token_expiration', static::JWT_EXPIRATION ); // Token expiry.

		/**
		 * JWT Reserved claims.
		 */
		$payload = array(
			'iat' => $iat,
			'iss' => $iss,
			'exp' => $exp,
		);

		/**
		 * JWT Private claims
		 *
		 * The `data` private claim will always be added, but additional claims can be added via the
		 * `tide_api_jwt_token_payload_private_claims` filter.
		 */
		$payload = array_merge( $payload, array(
			'data' => array(
				'client' => array(
					'id'   => $client->ID,
					'type' => isset( $client->type ) ? $client->type : 'wp_user',
				),
			),
		), apply_filters( 'tide_api_jwt_token_payload_private_claims', array(), $request, $client ) );

		/**
		 * Generate JWT token.
		 */
		$token = JWT::encode( $payload, $secret );

		/**
		 * Return response containing the JWT token and $client.
		 */
		return apply_filters( 'tide_api_jwt_token_response', array(
			'access_token' => $token,
			'client'       => $client,
		), $request, $client, $token );
	}

	/**
	 * Determine if a valid Bearer token has been provided.
	 *
	 * @param bool $jwt Optional token.
	 *
	 * @return mixed
	 */
	public function validate_token( $jwt = false ) {

		/**
		 * Get secret to decode the JWT tokens with.
		 */
		$secret = $this->get_secret();
		if ( is_wp_error( $secret ) ) {
			return $secret;
		}

		/**
		 * Get HTTP Authorization header.
		 */
		$auth = $this->get_auth_header();
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		/**
		 * Get the bearer token from the Authorization header.
		 */
		$jwt = false !== $jwt ? $jwt : $this->get_token( $auth );
		if ( is_wp_error( $jwt ) ) {
			return $jwt;
		}

		/**
		 * Decode the token and validate.
		 */
		try {

			/**
			 * Decode.
			 */
			$token = JWT::decode( $jwt, $secret, array( 'HS256' ) );

			/**
			 * Determine if the token is from a valid issuer. Should be the API only at this stage.
			 */
			$issuer_valid = $this->validate_issuer( $token->iss );
			if ( is_wp_error( $issuer_valid ) ) {
				return $issuer_valid;
			}

			/**
			 * Determine the client in the token is a valid client.
			 */
			$user_valid = $this->validate_token_client( $token );
			if ( is_wp_error( $user_valid ) ) {
				return $user_valid;
			}

			/**
			 * Determine if the token has expired.
			 *
			 * Allow this requirement to be overridden.
			 */
			$date_valid = $this->validate_token_date( $token );
			if ( is_wp_error( $date_valid ) ) {
				$override_date_validation = apply_filters( 'tide_api_jwt_token_override_date_validation', false, $token );

				if ( ! $override_date_validation ) {
					return $date_valid;
				}
			}

			return $token;
		} catch ( \Exception $e ) {

			/**
			 * Return exceptions as WP_Errors.
			 */
			return new WP_Error(
				'rest_auth_error',
				__( 'Invalid bearer token.', 'tide-api' ),
				array( 'status' => 403 )
			);
		}
	}

	/**
	 * Get SECURE_AUTH_KEY to use as JWT secret.
	 *
	 * @return string|WP_Error
	 */
	public function get_secret() {

		if ( $this->secure_auth_key ) {
			$secret = $this->secure_auth_key;
		} else {
			$secret = new WP_Error(
				'rest_auth_key',
				__( 'Secret key was not defined.', 'tide-api' ),
				array( 'status' => 403 )
			);
		}

		return $secret;
	}

	/**
	 * Get the HTTP Authorization Header.
	 *
	 * @return mixed
	 */
	public function get_auth_header() {
		// Get HTTP Authorization Header.
		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$header = $_SERVER['HTTP_AUTHORIZATION'];
		} else {
			$header = new WP_Error(
				'rest_auth_no_header',
				__( 'Authorization header not found.', 'tide-api' ),
				array( 'status' => 403 )
			);
		}

		return $header;
	}

	/**
	 * Get the Bearer token from the header.
	 *
	 * @param string $auth The Authorization header.
	 *
	 * @return array|mixed|WP_Error
	 */
	public function get_token( $auth ) {
		$token = sscanf( $auth, 'Bearer %s' );

		if ( is_array( $token ) ) {
			$token = array_shift( $token );
		} else {
			$token = new WP_Error(
				'rest_auth_malformed_token',
				__( 'Authentication token is malformed.', 'tide-api' ),
				array( 'status' => 403 )
			);
		}

		return $token;
	}

	/**
	 * Get the user id of the current token.
	 *
	 * @return bool|int
	 */
	public function get_user_id() {
		$obj = $this->validate_token();

		if ( is_wp_error( $obj ) || ! isset( $obj->data->client->id ) ) {
			return false;
		}

		return $obj->data->client->id;
	}

	/**
	 * Make sure that the issuer is valid.
	 *
	 * @param string $issuer Issuer of the token.
	 *
	 * @return bool|WP_Error
	 */
	public function validate_issuer( $issuer ) {
		$valid = true;

		if ( get_bloginfo( 'url' ) !== $issuer ) {
			$valid = new WP_Error(
				'rest_auth_invalid_issuer',
				__( 'Invalid token issuer.', 'tide-api' ),
				array( 'status' => 403 )
			);
		}

		return $valid;
	}

	/**
	 * Determine if the token contains a valid client.
	 *
	 * @param object $token The token.
	 *
	 * @return bool|WP_Error
	 */
	public function validate_token_client( $token ) {
		$valid = true;

		if ( ! isset( $token->data->client->id ) ) {
			$valid = new WP_Error(
				'rest_auth_invalid_client',
				__( 'Invalid token client.', 'tide-api' ),
				array( 'status' => 403 )
			);
		}

		return $valid;
	}

	/**
	 * Determine if the token has expired.
	 *
	 * @param object $token The token.
	 *
	 * @return bool|WP_Error
	 */
	public function validate_token_date( $token ) {
		$valid = true;

		if ( time() > $token->exp ) {
			$valid = new WP_Error(
				'rest_auth_token_expired',
				__( 'Token has expired.', 'tide-api' ),
				array( 'status' => 403 )
			);
		}

		return $valid;
	}

	/**
	 * Filter REST authentication and
	 *
	 * @filter rest_authentication_errors
	 *
	 * @param mixed $result Result of any other authentication errors.
	 *
	 * @return bool|null|object|WP_Error
	 */
	public function authentication_errors( $result ) {

		/**
		 * User is already authenticated, so skipp authentication.
		 */
		$user = wp_get_current_user();
		if ( isset( $user->ID ) && 0 !== $user->ID ) {
			return $result;
		}

		/**
		 * Don't filter out the authentication endpoint on POST request or we'll never get a token.
		 */
		$is_auth_endpoint = 'POST' === $_SERVER['REQUEST_METHOD'] && sprintf( '/%s/%s/%s', rest_get_url_prefix(), $this->namespace, $this->base ) === $_SERVER['REQUEST_URI'];
		if ( $is_auth_endpoint ) {
			return $result;
		}

		/**
		 * A valid token was passed, so update the user and return true for success.
		 */
		$token = $this->validate_token();
		if ( ! is_wp_error( $token ) ) {

			/**
			 * If its a user based token then set the current user to the JWT authenticated user.
			 */
			if ( 'wp_user' === $token->data->client->type ) {
				wp_set_current_user( $token->data->client->id );
			}

			/**
			 * This hook is useful if you want to retrieve information about the token and perform extra actions.
			 */
			do_action( 'tide_api_jwt_token_validated', $token );

			return true; // Not null to indicate success.
		} else {

			/**
			 * Perform actions when an error occurs.
			 */
			$error_code = $token->get_error_code();
			do_action( 'tide_api_jwt_token_error_' . $error_code, $this );

			// Anything other than GET must be authenticated.
			// GET requests will be filtered by the relevant API controller.
			if ( 'GET' === $_SERVER['REQUEST_METHOD'] ) {
				return $result;
			} else {
				return apply_filters( 'tide_api_jwt_token_error_response', $token, $this );
			}
		}
	}

}
