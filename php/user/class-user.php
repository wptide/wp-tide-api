<?php
/**
 * A custom user setting class for Tide API.
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\User;

use WP_Tide_API\Base, WP_Tide_API\Plugin;
use WP_Tide_API\Restriction\Rate_Limit;

/**
 * Class User
 */
class User extends Base {

	/**
	 * Tide Rate limit user meta key.
	 */
	const LIMIT_USER_META_KEY = 'tide_rate_limit';

	/**
	 * Tide api interval meta key.
	 */
	const INTERVAL_USER_META_KEY = 'tide_api_interval';

	/**
	 * Add custom field to user profile
	 *
	 * @param \WP_User $user WP user object.
	 *
	 * @action show_user_profile
	 * @action edit_user_profile
	 * @action user_new_form
	 */
	public function add_custom_field( $user ) {
		if ( ! is_super_admin() ) {
			return;
		}

		$settings = Rate_Limit::get_rate_limit_values( $user->ID ?? 0 );
		$interval = $settings['interval'] / HOUR_IN_SECONDS; // Seconds to hours.

		if ( isset( $user->ID ) ) {
			$current_usage = get_transient( Rate_Limit::CACHE_KEY_PREFIX . $user->ID );
		}
		?>
		<h3><?php esc_html_e( 'Tide Api Limits', 'tide-api' ); ?></h3>
		<table class="form-table bod-custom-field">
			<tr>
				<th><label for="bod-profile-id"><?php esc_html_e( 'Rate Limit', 'tide-api' ); ?></label>
				</th>
				<td>
					<input id="bod-profile-id" type="text" name="<?php echo esc_attr( static::LIMIT_USER_META_KEY ); ?>" value="<?php echo esc_attr( $settings['rate_limit'] ); ?>" class="regular-text"/>
					<p class="description">
						<?php
						$used = $current_usage['used'] ?? 0;
						printf( '%s %s, %s %s', esc_html__( 'API Rate limit. Used:', 'tide-api' ), esc_html( $used ), esc_html__( 'Default:', 'tide-api' ), esc_html( Rate_Limit::DEFAULT_LIMIT ) );
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="bod-profile-id"><?php esc_html_e( 'Interval', 'tide-api' ); ?></label>
				</th>
				<td>
					<input id="bod-profile-id" type="text" name="<?php echo esc_attr( static::INTERVAL_USER_META_KEY ); ?>" value="<?php echo esc_attr( $interval ); ?>" class="regular-text"/>
					<p class="description">
						<?php
						printf( '%s %s', esc_html__( ' Renews api rate limit, In Hours. Default:', 'tide-api' ), esc_html( ( Rate_Limit::DEFAULT_INTERVAL / HOUR_IN_SECONDS ) ) );
						?>
					</p>
				</td>
			</tr>
		</table>
		<?php
		wp_nonce_field( 'tide_user_settings', '_tide_user_nonce', false );
	}

	/**
	 * Saves post id
	 *
	 * @param int $user_id User id.
	 *
	 * @action user_register
	 * @action edit_user_profile_update
	 * @action personal_options_update
	 *
	 * @return bool
	 */
	public function save_custom_field( $user_id ) {
		if ( ! is_super_admin() ) {
			return false;
		}

		$settings = Rate_Limit::get_rate_limit_values( $user_id );

		// @codingStandardsIgnoreStart
		if ( isset( $_POST['_tide_user_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['_tide_user_nonce'] ), 'tide_user_settings' ) ) {

		    $limit = isset( $_POST[ static::LIMIT_USER_META_KEY ] ) ? absint( wp_unslash( $_POST[ static::LIMIT_USER_META_KEY ] ) ) : 0;
			$interval = isset( $_POST[ static::INTERVAL_USER_META_KEY ] ) ? absint( wp_unslash( $_POST[ static::INTERVAL_USER_META_KEY ] ) ) : 0;
			$rate_counter = get_transient( Rate_Limit::CACHE_KEY_PREFIX . $user_id );

			/**
			 * If there has been no requests, then create a fresh object.
			 */
			if ( false === $rate_counter ) {
				$rate_counter = array(
					'id'       => $user_id,
					'interval' => $settings['interval'],
					'limit'    => $settings['rate_limit'],
					'used'     => 0,
					'start'    => time(),
				);
			}

			if ( ! empty( $limit ) && $settings['rate_limit'] !== $limit ) {
				update_user_meta( $user_id, self::LIMIT_USER_META_KEY, $limit );
				$rate_counter['limit'] = $limit;
			}

			if ( ! empty( $interval ) && ( $settings['interval'] / HOUR_IN_SECONDS ) !== $interval ) {
				update_user_meta( $user_id, self::INTERVAL_USER_META_KEY, $interval * HOUR_IN_SECONDS );
				$rate_counter['interval'] = $interval * HOUR_IN_SECONDS;
			}

			$rate_counter['used'] = 0;

			// Set the transient and its expiry.
			set_transient(  Rate_Limit::CACHE_KEY_PREFIX . $user_id, $rate_counter, $rate_counter['interval'] );
		}
		// @codingStandardsIgnoreEnd
	}

}
