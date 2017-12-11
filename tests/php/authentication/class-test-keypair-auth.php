<?php
/**
 * Test_Keypair_Auth
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\Authentication\Keypair_Auth;

/**
 * Class Test_Keypair_Auth
 *
 * @coversDefaultClass WP_Tide_API\Authentication\Keypair_Auth
 */
class Test_Keypair_Auth extends WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var WP_Tide_API\Plugin
	 */
	public $plugin;

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	public $namespace = '/tide/v1';

	/**
	 * REST Server.
	 *
	 * @var \WP_REST_Server
	 */
	public $server;

	/**
	 * Keypair Auth.
	 *
	 * @var Keypair_Auth
	 */
	public $keypair_auth;

	/**
	 * The base route for creating keypairs.
	 *
	 * @var string
	 */
	public $keypair_base = 'keypair/(?P<id>[\d]+)';

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		// @codingStandardsIgnoreStart
		$GLOBALS['wp_rest_server'] = new WP_REST_Server();
		// @codingStandardsIgnoreEnd
		$this->server = $GLOBALS['wp_rest_server'];

		do_action( 'rest_api_init' );

		$this->plugin       = WP_Tide_API\Plugin::instance();
		$this->keypair_auth = $this->plugin->components['keypair_auth'];
	}

	/**
	 * Teardown.
	 *
	 * @inheritdoc
	 */
	function tearDown() {
		$this->server = null;
		unset( $GLOBALS['wp_rest_server'] );
		parent::tearDown();
	}

	/**
	 * Test user_profile_fields().
	 *
	 * @covers ::user_profile_fields()
	 */
	public function test_user_profile_fields() {
		$user_id = $this->factory()->user->create( array(
			'role' => 'administrator',
		) );

		$user       = new WP_User( $user_id );
		$api_key    = 'dummy-api-key';
		$api_secret = 'dummy-secret';

		// @codingStandardsIgnoreStart - Skipping VIP sniffs.
		update_user_meta( $user->ID, 'tide_api_user_key', $api_key );
		update_user_meta( $user->ID, 'tide_api_user_secret', $api_secret );
		// @codingStandardsIgnoreEnd

		ob_start();
		$this->keypair_auth->user_profile_fields( $user );
		$output = ob_get_clean();

		$this->assertContains( '<table class="form-table">', $output );
		$this->assertContains( 'name="tide-api-key"', $output );
		$this->assertContains( 'name="tide-api-secret"', $output );
		$this->assertContains( $api_key, $output );
		$this->assertContains( $api_secret, $output );
	}

	/**
	 * Test authenticate_key_pair().
	 *
	 * @covers ::authenticate_key_pair()
	 */
	public function test_authenticate_key_pair() {
		$rest_request = new WP_REST_Request( 'POST', sprintf( '%s/%s', $this->namespace, $this->keypair_base ) );

		$user_id = $this->factory()->user->create( array(
			'role' => 'administrator',
		) );

		$api_key    = 'dummy-api-key';
		$api_secret = 'dummy-api-secret';

		// @codingStandardsIgnoreStart - Skipping VIP sniffs.
		update_user_meta( $user_id, 'tide_api_user_key', $api_key );
		update_user_meta( $user_id, 'tide_api_user_secret', $api_secret );
		// @codingStandardsIgnoreEnd

		$this->assertFalse( $this->keypair_auth->authenticate_key_pair( false, $rest_request ) );

		$rest_request->set_param( 'api_secret', $api_secret );
		$rest_request->set_param( 'api_key', $api_key );

		$user = $this->keypair_auth->authenticate_key_pair( false, $rest_request );

		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertEquals( $user_id, $user->ID );
		$this->assertFalse( isset( $user->data->user_pass ) );
		$this->assertFalse( isset( $user->data->user_nicename ) );
		$this->assertFalse( isset( $user->data->user_activation_key ) );
		$this->assertFalse( isset( $user->data->user_status ) );
		$this->assertFalse( isset( $user->data->user_url ) );
		$this->assertFalse( isset( $user->cap_key ) );
		$this->assertFalse( isset( $user->filter ) );
	}

	/**
	 * Test register_keypair_routes().
	 *
	 * @covers ::register_keypair_routes()
	 */
	public function test_register_keypair_routes() {
		$routes       = $this->server->get_routes();
		$keypair_base = sprintf( '%s/%s', $this->namespace, $this->keypair_base );
		$this->assertArrayHasKey( $this->namespace, $routes );
		$this->assertArrayHasKey( $keypair_base, $routes );
		$this->assertEquals( implode( array_keys( $routes[ $keypair_base ][0]['methods'] ), ', ' ), WP_REST_Server::EDITABLE );
		$this->assertEquals( implode( array_keys( $routes[ $keypair_base ][1]['methods'] ), ', ' ), WP_REST_Server::READABLE );
	}

	/**
	 * Test generate_keypair().
	 *
	 * @covers ::generate_keypair()
	 */
	public function test_generate_keypair() {
		$rest_request = new WP_REST_Request( 'POST', sprintf( '%s/%s', $this->namespace, $this->keypair_base ) );
		$key_pair     = $this->keypair_auth->generate_keypair( $rest_request );

		$this->assertTrue( is_wp_error( $key_pair ) );
		$this->assertEquals( $key_pair->get_error_code(), 'rest_user_error' );

		$user_id = $this->factory()->user->create( array(
			'role' => 'administrator',
		) );

		$rest_request->set_param( 'id', $user_id );

		$key_pair = $this->keypair_auth->generate_keypair( $rest_request );
		$this->assertTrue( is_wp_error( $key_pair ) );
		$this->assertEquals( $key_pair->get_error_code(), 'rest_user_error' );

		wp_set_current_user( $user_id );
		$key_pair = $this->keypair_auth->generate_keypair( $rest_request );

		$this->assertArrayHasKey( 'api_key', $key_pair );
		$this->assertArrayHasKey( 'ap_secret', $key_pair );
		$this->assertNotEmpty( $key_pair['api_key'] );
		$this->assertNotEmpty( $key_pair['ap_secret'] );
	}

	/**
	 * Test get_keypair().
	 *
	 * @covers ::get_keypair()
	 */
	public function test_get_keypair() {
		$api_key      = 'dummy-api-key';
		$api_secret   = 'dummy-api-secret';
		$rest_request = new WP_REST_Request( 'POST', sprintf( '%s/%s', $this->namespace, $this->keypair_base ) );
		$key_pair     = $this->keypair_auth->get_keypair( $rest_request );

		$this->assertTrue( is_wp_error( $key_pair ) );
		$this->assertEquals( $key_pair->get_error_code(), 'rest_user_error' );

		$user_id = $this->factory()->user->create( array(
			'role' => 'administrator',
		) );

		$rest_request->set_param( 'id', $user_id );
		$key_pair = $this->keypair_auth->get_keypair( $rest_request );
		$this->assertTrue( is_wp_error( $key_pair ) );
		$this->assertEquals( $key_pair->get_error_code(), 'rest_user_error' );

		wp_set_current_user( $user_id );
		$key_pair = $this->keypair_auth->get_keypair( $rest_request );
		$this->assertArrayHasKey( 'api_key', $key_pair );
		$this->assertArrayHasKey( 'ap_secret', $key_pair );
		$this->assertEmpty( $key_pair['api_key'] );
		$this->assertEmpty( $key_pair['ap_secret'] );

		// @codingStandardsIgnoreStart - Skipping VIP sniffs.
		update_user_meta( $user_id, 'tide_api_user_key', $api_key );
		update_user_meta( $user_id, 'tide_api_user_secret', $api_secret );
		// @codingStandardsIgnoreEnd

		$key_pair = $this->keypair_auth->get_keypair( $rest_request );
		$this->assertEquals( $key_pair['api_key'], $api_key );
		$this->assertEquals( $key_pair['ap_secret'], $api_secret );
	}
}
