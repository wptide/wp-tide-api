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
use WP_Tide_API\Authentication\User_Refresh_Token;
use WP_Tide_API\Integration\Firestore;
use WP_Tide_API\Integration\GCS;
use WP_Tide_API\Integration\Local;
use WP_Tide_API\Integration\Mongo;
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
		$this->components['jwt_auth']           = new JWT_Auth( $this ); // Enables JWT Authentication tokens.
		$this->components['keypair_auth']       = new Keypair_Auth( $this ); // Enabled Key-Pair authentication.
		$this->components['user_refresh_token'] = new User_Refresh_Token( $this ); // Enabled user-based refresh tokens.

		/**
		 * Integrations
		 */
		$this->components['storage_gcs']     = new GCS( $this );
		$this->components['storage_local']   = new Local( $this );
		$this->components['storage_s3']      = new S3( $this );
		$this->components['queue_sqs']       = new SQS( $this );
		$this->components['queue_mongo']     = new Mongo( $this );
		$this->components['queue_firestore'] = new Firestore( $this );

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
}
