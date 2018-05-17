<?php
/**
 * This file handles core functionality of WP Tide API.
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API;

use WP_Tide_API\API\API_Bootstrap;
use WP_Tide_API\API\Endpoint\Audit;
use WP_Tide_API\API\Endpoint\Report;
use WP_Tide_API\Authentication\JWT_Auth;
use WP_Tide_API\Authentication\Keypair_Auth;
use WP_Tide_API\Integration\GCS;
use WP_Tide_API\Integration\Local;
use WP_Tide_API\Integration\S3;
use WP_Tide_API\Integration\SQS;
use WP_Tide_API\Restriction\Rate_Limit;
use WP_Tide_API\User\User;

/**
 * Class Plugin
 */
class Plugin extends Base {

	const SETTINGS_KEY      = 'tide-api';
	const HOOK_PREFIX       = 'tide_api';
	const MENU_PARENT       = 'tide-api';
	const MENU_PARENT_TITLE = 'Tide API';
	const PAGE_HOOK_BASE    = 'tide-api';
	const PRIMARY_JS_SLUG   = 'tide-api';
	const PRIMARY_JS_OBJECT = 'TideAPI';
	const PRIMARY_CSS_SLUG  = 'tide-api';

	/**
	 * Array containing all the important plugin information.
	 *
	 * @var array|bool
	 */
	public $info = array();

	/**
	 * Plugin components.
	 *
	 * @var array
	 */
	public $components = array();

	/**
	 * Describes CPTs to be registered.
	 *
	 * @var array
	 */
	public $custom_post_types = array();

	/**
	 * Get an instance of the plugin.
	 *
	 * @return \WP_Tide_API\Plugin
	 */
	public static function instance() {
		global $wp_tide_api_plugin;

		return $wp_tide_api_plugin;
	}

	/**
	 * Plugin constructor.
	 *
	 * @param bool $info Plugin information.
	 */
	public function __construct( $info ) {
		/**
		 * Call parent constructor for reflection of code.
		 */
		parent::__construct();

		/**
		 * Plugin information array.
		 */
		$this->info = $info;

		/**
		 * API
		 */
		$this->components['api_bootstrap']       = new API_Bootstrap( $this ); // Prepares the API.
		$this->components['api_endpoint_audit']  = new Audit( $this ); // Prepares the Audit endpoints.
		$this->components['api_endpoint_report'] = new Report( $this ); // Prepares the Report endpoints.

		/**
		 * Authentication
		 */
		$this->components['jwt_auth']     = new JWT_Auth( $this ); // Enables JWT Authentication tokens.
		$this->components['keypair_auth'] = new Keypair_Auth( $this ); // Enabled Key-Pair authentication.

		// @codingStandardsIgnoreStart
		//$this->components['user_refresh_token'] = new User_Refresh_Token( $this ); // Enabled user-based refresh tokens.
		// @codingStandardsIgnoreEnd

		/**
		 * Integrations
		 */
		$this->components['storage_gcs']   = new GCS( $this );
		$this->components['storage_local'] = new Local( $this );
		$this->components['storage_s3']    = new S3( $this );
		$this->components['queue_sqs']     = new SQS( $this );

		/**
		 * User setting
		 */
		$this->components['user'] = new User( $this );
	}

	/**
	 * Load class that requires init hook.
	 *
	 * @action init
	 */
	public function on_init() {
		/**
		 * API Restriction
		 *
		 * Using `init` because Rate_Limit uses is_user_logged_in in constructor
		 * which is only available after this hook.
		 */
		$this->components['restrict_rate_limit'] = new Rate_Limit( $this );
	}

	/**
	 * Convenience method to get the plugin settings.
	 *
	 * Will get settings from site options if on a network, or regular options for single site install.
	 *
	 * @param mixed $key     Key value.
	 * @param mixed $default Value if not found.
	 *
	 * @return mixed
	 */
	public function get_setting( $key = false, $default = null ) {

		if ( is_multisite() ) {
			$settings = get_site_option( static::SETTINGS_KEY, array() );
		} else {
			$settings = get_option( static::SETTINGS_KEY, array() );
		}

		if ( empty( $key ) ) {

			return apply_filters( static::HOOK_PREFIX . '_options_all', $settings );
		} else {

			$option = isset( $settings[ $key ] ) ? $settings[ $key ] : $default;

			// Deal with empty arrays.
			$option = is_array( $option ) && empty( $option ) && ! empty( $default ) ? $default : $option;

			return apply_filters( static::HOOK_PREFIX . '_options_' . $key, $option );
		}
	}

	/**
	 * Update a plugin setting.
	 *
	 * Uses site options if on a network or regular options for single site install.
	 *
	 * @param mixed $key   Key value.
	 * @param mixed $value New value for the setting.
	 */
	public function update_settings( $key = false, $value ) {

		$settings = $value;
		if ( is_multisite() ) {
			if ( false !== $key ) {
				$settings         = get_site_option( static::SETTINGS_KEY, array() );
				$settings[ $key ] = $value;
			}
			update_site_option( static::SETTINGS_KEY, $settings );
		} else {
			if ( false !== $key ) {
				$settings         = get_option( static::SETTINGS_KEY, array() );
				$settings[ $key ] = $value;
			}
			update_option( static::SETTINGS_KEY, $settings );
		}
	}

	/**
	 * Convenience method to get site settings.
	 *
	 * @param int   $blog_id ID for specific blog.
	 * @param mixed $key     Key value for setting.
	 * @param mixed $default Default value if not found.
	 *
	 * @return mixed
	 */
	public function get_site_setting( $blog_id, $key, $default = null ) {

		$settings = maybe_unserialize( get_blog_option( $blog_id, static::SETTINGS_KEY, array() ) );

		if ( empty( $key ) ) {

			return apply_filters( static::HOOK_PREFIX . '_site_settings_all', $settings, $blog_id );
		} else {

			$option = isset( $settings[ $key ] ) ? $settings[ $key ] : $default;

			return apply_filters( static::HOOK_PREFIX . '_site_settings_' . $key, $option, $blog_id );
		}
	}

	/**
	 * Update a site's setting.
	 *
	 * @param int   $blog_id ID for specific blog.
	 * @param mixed $key     Key value for setting.
	 * @param mixed $value   New value.
	 * @param mixed $reason  Optional reason for updating the settings.
	 */
	public function update_site_settings( $blog_id, $key, $value, $reason = false ) {

		$settings = $value;

		if ( false !== $key ) {
			$settings         = maybe_unserialize( get_blog_option( $blog_id, static::SETTINGS_KEY, array() ) );
			$old_value        = isset( $settings[ $key ] ) ? $settings[ $key ] : null;
			$settings[ $key ] = $value;
		} else {
			$old_value = maybe_unserialize( get_blog_option( $blog_id, static::SETTINGS_KEY, array() ) );
		}

		update_blog_option( $blog_id, static::SETTINGS_KEY, maybe_serialize( $settings ) );

		do_action( static::HOOK_PREFIX . '_site_settings_updated_' . $key, $value, $old_value, $blog_id, $reason );
		do_action( static::HOOK_PREFIX . '_site_settings_updated', $key, $value, $old_value, $blog_id, $reason );
	}
}
