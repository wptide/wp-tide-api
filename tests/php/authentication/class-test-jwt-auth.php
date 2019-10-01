<?php
/**
 * Test_JWT_Auth
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\Authentication\JWT_Auth;
use \Firebase\JWT\JWT;

/**
 * Class Test_JWT_Auth
 *
 * @coversDefaultClass WP_Tide_API\Authentication\JWT_Auth
 */
class Test_JWT_Auth extends WP_UnitTestCase {

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
	 * The base route for JWT authentication.
	 *
	 * @var string
	 */
	public $base = 'auth';

	/**
	 * REST Server.
	 *
	 * @var \WP_REST_Server
	 */
	public $server;

	/**
	 * JWT Auth.
	 *
	 * @var JWT_Auth
	 */
	public $jwt_auth;

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

		$this->plugin   = WP_Tide_API\Plugin::instance();
		$this->jwt_auth = new JWT_Auth( $this->plugin, self::$SECURE_AUTH_KEY );
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
	 * Test register_jwt_routes().
	 *
	 * @covers ::register_jwt_routes()
	 */
	public function test_register_jwt_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( $this->namespace, $routes );
		$this->assertArrayHasKey( sprintf( '%s/%s', $this->namespace, $this->base ), $routes );
	}

	/**
	 * Test generate_token().
	 *
	 * @covers ::generate_token()
	 */
	public function test_generate_token() {
		$user_data = array(
			'role'       => 'administrator',
			'user_login' => 'testuser',
			'user_pass'  => 'testpassword',
		);

		$rest_request = new WP_REST_Request( 'POST', sprintf( '%s/%s', $this->namespace, $this->base ) );

		/**
		 * Remove filter so the method can be tested in isolation.
		 */
		remove_filter( 'tide_api_authenticate_client', array( $this->plugin->components['keypair_auth'], 'authenticate_key_pair' ) );
		remove_filter( 'tide_api_jwt_token_response', array( $this->plugin->components['user_refresh_token'], 'append_refresh_token' ) );

		/**
		 * Test if error is thrown when `$SECURE_AUTH_KEY` is false, which mimics no defined value.
		 */
		$jwt_auth = new JWT_Auth( $this->plugin, false );
		$token = $jwt_auth->generate_token( $rest_request );
		$this->assertTrue( is_wp_error( $token ) );
		$this->assertEquals( 'rest_auth_key', $token->get_error_code() );

		$user_id = $this->factory->user->create( $user_data );

		/**
		 * Set incorrect credentials.
		 */
		$rest_request->set_param( 'username', $user_data['user_login'] );
		$rest_request->set_param( 'password', 'incorrect-password' );
		$token = $this->jwt_auth->generate_token( $rest_request );

		/**
		 * Test with incorrect credentials.
		 */
		$this->assertTrue( is_wp_error( $token ) );
		$this->assertEquals( 'rest_auth_invalid_credentials', $token->get_error_code() );

		/**
		 * Test with correct credentials.
		 */
		$rest_request->set_param( 'password', $user_data['user_pass'] );
		$token = $this->jwt_auth->generate_token( $rest_request );

		/**
		 * Test if access_token was generated.
		 */
		$this->assertArrayHasKey( 'access_token', $token );
		$this->assertTrue( ! empty( $token['access_token'] ) );
		$this->assertArrayHasKey( 'client', $token );

		// Client data.
		$client = $token['client'];

		/**
		 * Test if access_token was generated for the intended user.
		 */
		$this->assertEquals( $client->ID, $user_id );
		$this->assertArrayHasKey( 'administrator', $client->caps );
		$this->assertEquals( $user_data['user_login'], $client->data->user_login );

		add_filter( 'tide_api_authenticate_client', array( $this->plugin->components['keypair_auth'], 'authenticate_key_pair' ) );
		add_filter( 'tide_api_jwt_token_response', array( $this->plugin->components['user_refresh_token'], 'append_refresh_token' ) );
	}

	/**
	 * Test validate_token().
	 *
	 * @covers ::validate_token()
	 */
	public function test_validate_token() {

		$jwt_auth = new ReflectionClass( get_class( $this->jwt_auth ) );

		$mock = $this->getMockBuilder( get_class( $this->jwt_auth ) )->setConstructorArgs( array( $this->plugin ) )->getMock();
		$mock->method( 'get_secret' )->willReturn( new WP_Error(
			'rest_auth_key',
			__( 'Secret key was not defined.', 'tide-api' ),
			array( 'status' => 403 )
		) );

		$validate_token = $jwt_auth->getMethod( 'validate_token' )->invoke( $mock, false );

		$this->assertTrue( is_wp_error( $validate_token ) );
		$this->assertEquals( $validate_token->get_error_code(), 'rest_auth_key' );

		$this->assertTrue( is_wp_error( $this->jwt_auth->validate_token() ) );
		$this->assertEquals( $this->jwt_auth->validate_token()->get_error_code(), 'rest_auth_no_header' );

		// @codingStandardsIgnoreStart
		$_SERVER['HTTP_AUTHORIZATION'] = 'test_http_authorization';
		// @codingStandardsIgnoreStart

		$this->assertTrue( is_wp_error( $this->jwt_auth->validate_token() ) );
		$this->assertEquals( $this->jwt_auth->validate_token()->get_error_code(), 'rest_auth_error' );

		$mock = $this->getMockBuilder( get_class( $this->jwt_auth ) )->setConstructorArgs( array( $this->plugin ) )->getMock();
		$mock->method( 'get_secret' )->willReturn( false );
		$mock->method( 'get_auth_header' )->willReturn( false );
		$mock->method( 'get_token' )->willReturn( new WP_Error(
			'rest_auth_malformed_token',
			__( 'Authentication token is malformed.', 'tide-api' ),
			array( 'status' => 403 )
		) );

		$validate_token = $jwt_auth->getMethod( 'validate_token' )->invoke( $mock, false );

		$this->assertTrue( is_wp_error( $validate_token ) );
		$this->assertEquals( $validate_token->get_error_code(), 'rest_auth_malformed_token' );

		$mock = $this->getMockBuilder( get_class( $this->jwt_auth ) )->setConstructorArgs( array( $this->plugin ) )->getMock();
		$mock->method( 'get_secret' )->willReturn( '98765' );
		$mock->method( 'get_auth_header' )->willReturn( 'Bearer 12345' );

		$validate_token = $jwt_auth->getMethod( 'validate_token' )->invoke( $mock, false );

		$this->assertTrue( is_wp_error( $validate_token ) );
		$this->assertEquals( $validate_token->get_error_code(), 'rest_auth_error' );
		$this->assertEquals( $validate_token->get_error_message(), 'Invalid bearer token.' );

		// @todo Test more of validate_token().
	}

	/**
	 * Test get_token().
	 *
	 * @covers ::get_token()
	 */
	public function test_get_token() {
		$token = 'dEsdfjdsflds43kfjdslkjflkdsjflkdsjf';

		$this->assertEquals( $this->jwt_auth->get_token( 'Bearer' . $token ), $token );
		$this->assertNotEquals( $this->jwt_auth->get_token( $token ), $token );
		$this->assertEmpty( $this->jwt_auth->get_token( $token ) );
		$this->assertTrue( is_wp_error( $this->jwt_auth->get_token( 'Bearer ' ) ) );
		$this->assertEquals( $this->jwt_auth->get_token( 'Bearer ' )->get_error_code(), 'rest_auth_malformed_token' );
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
		$jwt_auth = new JWT_Auth( $this->plugin );
		$this->assertEquals( self::$SECURE_AUTH_KEY, $jwt_auth->get_secret() );

		$jwt_auth = new JWT_Auth( $this->plugin, false );
		$this->assertTrue( is_wp_error( $jwt_auth->get_secret() ) );
		$this->assertEquals( $jwt_auth->get_secret()->get_error_code(), 'rest_auth_key' );

		$jwt_auth = new JWT_Auth( $this->plugin, self::$SECURE_AUTH_KEY );
		$this->assertEquals( self::$SECURE_AUTH_KEY, $jwt_auth->get_secret() );
	}

	/**
	 * Test get_user_id().
	 *
	 * @covers ::get_user_id()
	 */
	public function test_get_user_id() {
		$object = json_decode( json_encode( array(
			'data' => array(
				'client' => array(
					'id' => 10,
				),
			),
		) ) );
		$jwt_auth = new ReflectionClass( get_class( $this->jwt_auth ) );

		$mock = $this->getMockBuilder( get_class( $this->jwt_auth ) )->setConstructorArgs( array( $this->plugin ) )->getMock();
		$mock->method( 'validate_token' )->willReturn( new WP_Error(
			'rest_auth_error',
			__( 'Invalid bearer token.', 'tide-api' ),
			array( 'status' => 403 )
		) );

		$get_user_id = $jwt_auth->getMethod( 'get_user_id' )->invoke( $mock );
		$this->assertFalse( $get_user_id );

		$mock = $this->getMockBuilder( get_class( $this->jwt_auth ) )->setConstructorArgs( array( $this->plugin ) )->getMock();
		$mock->method( 'validate_token' )->willReturn( $object );

		$get_user_id = $jwt_auth->getMethod( 'get_user_id' )->invoke( $mock );
		$this->assertEquals( $get_user_id, 10 );
	}

	/**
	 * Test validate_issuer().
	 *
	 * @covers ::validate_issuer()
	 */
	public function test_validate_issuer() {
		$issuer = 'http://example.com';

		// Test with invalid $issuers.
		$this->assertTrue( is_wp_error( $this->jwt_auth->validate_issuer( $issuer ) ) );
		$this->assertEquals( $this->jwt_auth->validate_issuer( $issuer )->get_error_code(), 'rest_auth_invalid_issuer' );

		// Test with valid $issuer.
		update_option( 'home', $issuer );
		$this->assertTrue( $this->jwt_auth->validate_issuer( $issuer ) );
	}

	/**
	 * Test validate_token_client().
	 *
	 * @covers ::validate_token_client()
	 */
	public function test_validate_token_client() {
		$token = new stdClass();
		$token->data = array();

		// Test with invalid token.
		$this->assertTrue( is_wp_error( $this->jwt_auth->validate_token_client( $token ) ) );
		$this->assertEquals( $this->jwt_auth->validate_token_client( $token )->get_error_code(), 'rest_auth_invalid_client' );

		// Test with valid token.
		$token = $this->_create_dummy_token_client();
		$this->assertTrue( $this->jwt_auth->validate_token_client( $token ) );
	}

	/**
	 * Test validate_token_date().
	 *
	 * @covers ::validate_token_date()
	 */
	public function test_validate_token_date() {
		$token = new stdClass();
		$token->exp = time() - 1;

		$this->assertTrue( is_wp_error( $this->jwt_auth->validate_token_date( $token ) ) );
		$this->assertEquals( $this->jwt_auth->validate_token_date( $token )->get_error_code(), 'rest_auth_token_expired' );

		$token->exp = time() + 10000;
		$this->assertTrue( $this->jwt_auth->validate_token_date( $token ) );
	}

	/**
	 * Test get_auth_header().
	 *
	 * @covers ::get_auth_header()
	 */
	public function test_get_auth_header() {
		$http_authorization = 'test_http_authorization';

		// @codingStandardsIgnoreStart
		$_SERVER['HTTP_AUTHORIZATION'] = $http_authorization;
		// @codingStandardsIgnoreStart

		$this->assertEquals( $this->jwt_auth->get_auth_header(), $http_authorization );

		// @codingStandardsIgnoreStart
		unset( $_SERVER['HTTP_AUTHORIZATION'] );
		// @codingStandardsIgnoreStart

		$this->assertTrue( is_wp_error( $this->jwt_auth->get_auth_header() ) );
		$this->assertEquals( 'rest_auth_no_header', $this->jwt_auth->get_auth_header()->get_error_code() );
	}

	/**
	 * Test authentication_errors().
	 *
	 * @covers ::authentication_errors()
	 */
	public function test_authentication_errors() {
		$namespace  = sprintf( '%s/%s', $this->plugin->info['api_namespace'], $this->plugin->info['api_version'] );
		$auth_uri   = sprintf( '/%s/%s/%s', rest_get_url_prefix(), $namespace, 'auth' );
		$report_uri = sprintf( '/%s/%s/%s', rest_get_url_prefix(), $namespace, 'report' );

		$this->assertEquals( null, $this->jwt_auth->authentication_errors( null ) );
		$this->assertEquals( 0, did_action( 'tide_api_jwt_token_error_response' ) );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $auth_uri;

		$this->assertEquals( null, $this->jwt_auth->authentication_errors( null ) );

		$_SERVER['REQUEST_URI'] = $report_uri;
		$result                 = $this->jwt_auth->authentication_errors( null );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertEquals( 2, did_action( 'tide_api_jwt_token_error_' . $result->get_error_code() ) );

		$_SERVER['REQUEST_METHOD'] = 'GET';

		$jwt_auth_mock = $this->getMockBuilder( get_class( $this->jwt_auth ) )
					 ->disableOriginalConstructor()
					 ->getMock();

		$token = $this->_create_dummy_token_client();

		$jwt_auth_mock->method( 'validate_token' )
			 ->willReturn( $token );

		$reflected_class = new ReflectionClass( get_class( $this->jwt_auth ) );

		// Test Success.
		$this->assertEquals( true, $reflected_class->getMethod( 'authentication_errors' )->invoke( $jwt_auth_mock, null ) );

		$user_id = $this->factory->user->create( array(
			'role' => 'administrator',
			'user_login' => 'testuser',
			'user_pass' => 'testpassword',
		) );

		wp_set_current_user( $user_id );

		$this->assertEquals( null, $this->jwt_auth->authentication_errors( null ) );
	}

	/**
	 * Create dummy token with valid client.
	 *
	 * @return \stdClass $token.
	 */
	public function _create_dummy_token_client() {
		$token = new stdClass();
		$token->data = new stdClass();
		$token->data->client = new stdClass();
		$token->data->client->type = 'wp_user';
		$token->data->client->id = 1;
		return $token;
	}
}
