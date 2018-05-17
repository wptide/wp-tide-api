<?php
/**
 * This file has user related utilities.
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\Utility;

use WP_Tide_API\Plugin;

/**
 * Class User
 */
class User {

	/**
	 * Check whether we have an authenticated user.
	 *
	 * @return bool|\WP_User
	 */
	public static function authenticated() {
		$is_user_logged_in = wp_get_current_user();
		if ( 0 === $is_user_logged_in->ID ) {
			$is_user_logged_in = get_user_by( 'id', Plugin::instance()->components['jwt_auth']->get_user_id() );
		}
		return $is_user_logged_in;
	}

	/**
	 * Determine if a user is authenticated and has the given capability.
	 *
	 * @param string $cap Capability to check.
	 *
	 * @return bool Success or not.
	 */
	public static function has_cap( $cap ) {

		$user = static::authenticated();

		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		return $user->has_cap( $cap );
	}
}
