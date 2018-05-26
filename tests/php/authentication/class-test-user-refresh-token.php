<?php
/**
 * Test_User_Refresh_Token
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\Authentication\JWT_Auth;
use WP_Tide_API\Authentication\User_Refresh_Token;
use Firebase\JWT\JWT;

/**
 * Class Test_User_Refresh_Token
 *
 * @coversDefaultClass WP_Tide_API\Authentication\User_Refresh_Token
 */
class Test_User_Refresh_Token extends WP_UnitTestCase {

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
	 * @var User_Refresh_Token
	 */
	public $keypair_auth;

	/**
	 * User Refresh Token.
	 *
	 * @var User_Refresh_Token
	 */
	public $user_refresh_token;

	/**
	 * JWT Refresh expiration.
	 */
	const JWT_REFRESH_EXPIRATION = 31536000; // 1 Year

	/**
	 * Random auth key.
	 */
	static $SECURE_AUTH_KEY = '54fda65we2aeb65abaq354150966b198e3444198';

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

		$this->plugin                         = WP_Tide_API\Plugin::instance();
		$this->user_refresh_token             = new User_Refresh_Token( $this->plugin, self::$SECURE_AUTH_KEY );
		$this->plugin->components['jwt_auth'] = new JWT_Auth( $this->plugin, self::$SECURE_AUTH_KEY );
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
	 * Test append_refresh_token().
	 *
	 * @covers ::append_refresh_token()
	 */
	public function test_append_refresh_token() {
		$rest_request = new WP_REST_Request( 'POST', $this->namespace );
		$secret       = $this->user_refresh_token->get_secret();
		$user_id      = $this->factory()->user->create( array(
			'role' => 'administrator',
		) );
		$client       = new stdClass();
		$client->type = 'other';
		$client->ID   = $user_id;

		$response = $this->user_refresh_token->append_refresh_token( array(), $rest_request, $client, false );

		$this->assertNotEmpty( $response );
		$this->assertArrayHasKey( 'refresh_token', $response );
		$this->assertNotEmpty( $response['refresh_token'] );

		$client->type = 'wp_user';

		$payload = array(
			'iat'  => time(),
			'iss'  => 'http://example.org',
			'exp'  => time() + self::JWT_REFRESH_EXPIRATION,
			'data' => array(
				'token_type' => 'refresh',
				'client'     => array(
					'id'   => $client->ID,
					'type' => $client->type,
				),
			),
		);

		$token = JWT::encode( $payload, $secret );

		// @codingStandardsIgnoreStart - Skipping VIP sniffs.
		update_user_meta( $user_id, 'tide_api_refresh_token', $token );
		// @codingStandardsIgnoreEnd

		$response = $this->user_refresh_token->append_refresh_token( array(), $rest_request, $client, false );

		$this->assertNotEmpty( $response );
		$this->assertArrayHasKey( 'refresh_token', $response );
		$this->assertEquals( $response['refresh_token'], $token );

		$payload['exp'] = time() - 1000;
		$token          = JWT::encode( $payload, $secret );

		// @codingStandardsIgnoreStart - Skipping VIP sniffs.
		update_user_meta( $user_id, 'tide_api_refresh_token', $token );
		// @codingStandardsIgnoreEnd

		$this->expectException( '\Firebase\JWT\ExpiredException' );
		$this->expectExceptionMessage( 'Expired token' );
		$this->user_refresh_token->append_refresh_token( array(), $rest_request, $client, false );

		// Test with static property set to true.
		$reflector              = new ReflectionClass( get_class( $this->user_refresh_token ) );
		$mock                   = $this->getMockBuilder( get_class( $this->user_refresh_token ) )->setConstructorArgs( array( $this->plugin, false ) )->getMock();
		$refresh_authentication = $reflector->getProperty( 'refresh_authentication' );

		$refresh_authentication->setAccessible( true );
		$this->assertEquals( $refresh_authentication->getValue( $mock ), false );
		$refresh_authentication->setValue( true );

		$result = $reflector->getMethod( 'append_refresh_token' )->invoke( $mock, array( 'response' ), $rest_request, false, false );
		$this->assertEquals( $result, array( 'response' ) );

		$result = $reflector->getMethod( 'append_refresh_token' )->invoke( $mock, array( 'response' ), $rest_request, false, false );
		$this->assertTrue( is_wp_error( $result ) );
		$this->assertEquals( $result->get_error_code(), 'rest_auth_key' );
	}

	/**
	 * Test authenticate_with_refresh_token().
	 *
	 * @covers ::authenticate_with_refresh_token()
	 */
	public function test_authenticate_with_refresh_token() {
		$rest_request = new WP_REST_Request( 'POST', $this->namespace );
		$this->assertFalse( $this->user_refresh_token->authenticate_with_refresh_token( false, $rest_request ) );

		$secret  = $this->user_refresh_token->get_secret();
		$user_id = $this->factory()->user->create( array(
			'role' => 'administrator',
		) );

		$payload = array(
			'iat'  => time(),
			'iss'  => 'http://example.org',
			'exp'  => time() + self::JWT_REFRESH_EXPIRATION,
			'data' => array(
				'client' => array(
					'id'   => $user_id,
					'type' => 'wp_user',
				),
			),
		);

		$token = JWT::encode( $payload, $secret );

		// @codingStandardsIgnoreStart
		$_SERVER['HTTP_AUTHORIZATION'] = sprintf( 'Bearer %s', $token );
		// @codingStandardsIgnoreEnd


		$tmp = $this->plugin->components['jwt_auth'];
		$this->plugin->components['jwt_auth'] = null;
		$this->assertEquals( 'fake-client', $this->user_refresh_token->authenticate_with_refresh_token( 'fake-client', $rest_request ) );
		$this->plugin->components['jwt_auth'] = $tmp;

		$this->assertFalse( $this->user_refresh_token->authenticate_with_refresh_token( false, $rest_request ) );

		$payload['data']['token_type'] = 'refresh';
		$token                         = JWT::encode( $payload, $secret );

		// @codingStandardsIgnoreStart
		$_SERVER['HTTP_AUTHORIZATION'] = sprintf( 'Bearer %s', $token );
		// @codingStandardsIgnoreEnd

		// @codingStandardsIgnoreStart - Skipping VIP sniffs.
		update_user_meta( $user_id, 'tide_api_refresh_token', $token );
		// @codingStandardsIgnoreEnd

		$client = $this->user_refresh_token->authenticate_with_refresh_token( false, $rest_request );

		$this->assertEquals( $client->ID, $user_id );
	}

	/**
	 * Test get_secret().
	 *
	 * @covers ::get_secret()
	 */
	public function test_get_secret() {
		if ( ! defined( 'SECURE_AUTH_KEY' ) ) {
			define( 'SECURE_AUTH_KEY', self::$SECURE_AUTH_KEY );
		}
		self::$SECURE_AUTH_KEY = SECURE_AUTH_KEY;
		$user_refresh_token = new User_Refresh_Token( $this->plugin );
		$this->assertEquals( self::$SECURE_AUTH_KEY, $user_refresh_token->get_secret() );

		$user_refresh_token = new User_Refresh_Token( $this->plugin, false );
		$this->assertTrue( is_wp_error( $user_refresh_token->get_secret() ) );
		$this->assertEquals( $user_refresh_token->get_secret()->get_error_code(), 'rest_auth_key' );

		$user_refresh_token = new User_Refresh_Token( $this->plugin, self::$SECURE_AUTH_KEY );
		$this->assertEquals( $user_refresh_token->get_secret(), self::$SECURE_AUTH_KEY );
	}
}
