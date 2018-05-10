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
	 * @return bool|false|\WP_User
	 */
	public static function authenticated() {
		$is_user_logged_in = is_user_logged_in();
		if ( false === $is_user_logged_in ) {
			$is_user_logged_in = get_user_by( 'id', Plugin::instance()->components['jwt_auth']->get_user_id() );
		}
		return $is_user_logged_in;
	}
}
