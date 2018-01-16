<?php
/**
 * This file is responsible for the managing of JSON Web Tokens (JWT).
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\Restriction;

use WP_Tide_API\Base, WP_Tide_API\Plugin, WP_Tide_API\User\User;
use WP_REST_Server, WP_REST_Request;

/**
 * Class Rate_Limit
 */
class Rate_Limit extends Base {

	/**
	 * Default rate limit.
	 */
	const DEFAULT_LIMIT = 1000;

	/**
	 * Default interval in seconds.
	 */
	const DEFAULT_INTERVAL = 86400;

	/**
	 * Option key for default settings.
	 */
	const SETTINGS_KEY = 'tide_api_rate_limit_settings';

	/**
	 * Prefix to use for cache items.
	 */
	const CACHE_KEY_PREFIX = 'tide_api:rate_counter:';

	/**
	 * The number of requests in the interval.
	 *
	 * @var int
	 */
	protected $rate_limit;

	/**
	 * The rate interval in seconds.
	 *
	 * @var int
	 */
	protected $interval;

	/**
	 * Flag to deduct point one time only.
	 * `rest_pre_dispatch` is also getting called for embed links, Check \WP_REST_Server::embed_links().
	 *
	 * @var bool
	 */
	protected $point_deducted = false;

	/**
	 * Rate_Limit constructor.
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function __construct( Plugin $plugin ) {
		parent::__construct( $plugin );

		$settings = $this->get_rate_limit_values();

		/**
		 * These will be the defaults unless a client has special restrictions.
		 */
		$this->set_rate_limit( (int) $settings['rate_limit'] );
		$this->set_interval( (int) $settings['interval'] );
	}

	/**
	 * Checks the the request route is to be rate limited.
	 *
	 * @param WP_REST_Request $request The Request.
	 *
	 * @return bool
	 */
	public function is_free( WP_REST_Request $request ) {

		if ( $request->get_method() === 'GET' ) {
			return true;
		}

		$free_routes = apply_filters( 'tide_api_free_post_routes', [
			'/tide/v1/auth',
		] );

		return in_array( $request->get_route(), $free_routes );
	}

	/**
	 * Checks the rate limit and throttles the request. If the request exceeds the limit an error will be returned.
	 *
	 * @filter rest_pre_dispatch 10, 3
	 *
	 * @param mixed           $response Return alternate response if limit is exceeded, or return original.
	 * @param WP_REST_Server  $server The WP REST Server.
	 * @param WP_REST_Request $request The Request.
	 *
	 * @return null | \WP_REST_Response
	 */
	public function check_rate_limit( $response, WP_REST_Server $server, WP_REST_Request $request ) {

		// Check if the request should be deducted.
		if ( true === $this->is_free( $request ) ) {
			return $response;
		}

		// This should only deduct api rate limit point once per request.
		if ( $this->point_deducted ) {
			return $response;
		}

		$client_id = $this->identify_client();

		$client = array(
			'id'       => $client_id,
			'limit'    => apply_filters( 'tide_api_client_rate_limit', $this->get_rate_limit(), $client_id, $response, $server, $request ),
			'interval' => apply_filters( 'tide_api_client_rate_interval', $this->get_interval(), $client_id, $response, $server, $request ),
		);

		/**
		 * Get it from the the database.
		 */
		$rate_counter = get_transient( self::CACHE_KEY_PREFIX . $client['id'] );

		/**
		 * If there has been no requests or it's too old, then create a fresh object.
		 */
		if ( false === $rate_counter ) {
			$rate_counter = array(
				'id'       => $client['id'],
				'interval' => (int) $client['interval'],
				'limit'    => (int) $client['limit'],
				'used'     => 0,
				'start'    => time(),
			);
		}

		// Get counter parameters.
		$remaining   = $rate_counter['limit'] - $rate_counter['used'];
		$is_exceeded = 0 >= $remaining;

		// TIDE-37: Deduct point for all requests.
		$rate_counter['used'] += 1;
		$this->point_deducted  = true;

		/**
		 * Add headers to requests that clients can use to identify rate restrictions.
		 * Based on GitHub API.
		 */
		$headers = array(
			'X-Rate-Limit'           => $rate_counter['limit'],
			'X-Rate-Limit-Remaining' => $is_exceeded ? 0 : $remaining,
			'X-Rate-Limit-Reset'     => $rate_counter['interval'],
		);

		/**
		 * If the rate limit is exceeded...
		 */
		if ( $is_exceeded ) {

			// Add retry header.
			$headers['Retry-After'] = $rate_counter['interval'];

			/**
			 * Create new error response.
			 */
			$response_code = 429; // Too many requests.
			$response      = new \WP_REST_Response(
				array(
					'code'    => 'rate_limit_exceeded',
					'message' => __( 'Too many requests to the API', 'tide-api' ),
					'data'    => array(
						'limit'     => $rate_counter['limit'],
						'remaining' => 0,
						'reset'     => $rate_counter['start'] + $rate_counter['interval'] - time(),
						'status'    => $response_code,
					),
				),
				$response_code
			);
			// Error should not increase used request count.
			$rate_counter['used'] -= 1;
		}

		$server->send_headers( $headers );

		$expire = $rate_counter['start'] + $rate_counter['interval'] - time();
		if ( 0 >= $expire ) {
			$expire               = $client['interval'];
			$rate_counter['used'] = 1;
		}

		// Set the transient data and update the expiry.
		set_transient( self::CACHE_KEY_PREFIX . $client['id'], $rate_counter, $expire );

		return $response;
	}

	/**
	 * Get the client that is requesting the resource.
	 *
	 * @return mixed
	 */
	public function identify_client() {

		$other_client = apply_filters( 'tide_api_request_client', false );
		$user_id      = false;

		// If user profile has data about rate limit, use it.
		if ( false !== is_user_logged_in() ) {
			$user_id = get_current_user_id();
		} else {
			$jwt_user_id = Plugin::instance()->components['jwt_auth']->get_user_id();
			if ( get_user_by( 'id', $jwt_user_id ) ) {
				$user_id = $jwt_user_id;
			}
		}

		if ( false !== $user_id ) {
			return $user_id;
		} elseif ( false !== $other_client ) {
			return $other_client;
		} else {
			if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				$identifier = $_SERVER['HTTP_CLIENT_IP'];
			} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$identifier = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else {
				$identifier = $_SERVER['REMOTE_ADDR'];
			}
			return 'logged_out_' . $identifier;
		}
	}

	/**
	 * Get the rate limit.
	 *
	 * @return mixed The rate limit.
	 */
	public function get_rate_limit() {
		return $this->rate_limit;
	}

	/**
	 * Set new limit.
	 *
	 * @param mixed $rate_limit New limit.
	 */
	public function set_rate_limit( $rate_limit ) {
		$this->rate_limit = $rate_limit;
	}

	/**
	 * Get interval.
	 *
	 * @return mixed The interval.
	 */
	public function get_interval() {
		return $this->interval;
	}

	/**
	 * Set interval.
	 *
	 * @param mixed $interval New interval.
	 */
	public function set_interval( $interval ) {
		$this->interval = $interval;
	}

	/**
	 * Get rate limit values.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return array rate limits.
	 */
	public static function get_rate_limit_values( $user_id = false ) {
		$settings = get_option( self::SETTINGS_KEY );

		if ( false === $settings || self::DEFAULT_LIMIT !== $settings['rate_limit'] || self::DEFAULT_INTERVAL !== $settings['interval'] ) {
			$settings = array(
				'rate_limit' => self::DEFAULT_LIMIT,
				'interval'   => self::DEFAULT_INTERVAL,
			);
			update_option( self::SETTINGS_KEY, $settings );
		}

		// If user profile has data about rate limit, use it.
		if ( false === $user_id ) {
			if ( false !== is_user_logged_in() ) {
				$user_id = get_current_user_id();
			} else {
				$jwt_user_id = Plugin::instance()->components['jwt_auth']->get_user_id();
				if ( get_user_by( 'id', $jwt_user_id ) ) {
					$user_id = $jwt_user_id;
				}
			}
		}

		if ( false !== $user_id ) {

			$limit    = get_user_meta( $user_id, User::LIMIT_USER_META_KEY, true );
			$interval = get_user_meta( $user_id, User::INTERVAL_USER_META_KEY, true );

			if ( ! empty( $limit ) ) {
				$settings['rate_limit'] = absint( $limit );
			}

			if ( ! empty( $interval ) ) {
				$settings['interval'] = absint( $interval );
			}
		}

		return $settings;
	}
}
