<?php
/**
 * Test_JWT_Auth
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\Authentication;

/**
 * Class Test_JWT_Auth
 *
 * @package WP_Tide_API
 */
class Test_JWT_Auth extends \WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var \WP_Tide_API\Plugin
	 */
	public $plugin;

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	public $namespace = '/tide/v2';

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
	const SECURE_AUTH_KEY = '54fda65we2aeb65abaq354150966b198e3444198';

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		// @codingStandardsIgnoreStart
		$GLOBALS['wp_rest_server'] = new \WP_REST_Server();
		// @codingStandardsIgnoreEnd
		$this->server = $GLOBALS['wp_rest_server'];

		do_action( 'rest_api_init' );

		$this->plugin   = \WP_Tide_API\Plugin::instance();
		$this->jwt_auth = $this->plugin->components['jwt_auth'];
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
	 * @see JWT_Auth::register_jwt_routes()
	 */
	public function test_register_jwt_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( $this->namespace, $routes );
		$this->assertArrayHasKey( sprintf( '%s/%s', $this->namespace, $this->base ), $routes );
	}

	/**
	 * Test generate_token().
	 *
	 * @see JWT_Auth::generate_token()
	 */
	public function test_generate_token() {
		$user_data = array(
			'role'       => 'administrator',
			'user_login' => 'testuser',
			'user_pass'  => 'testpassword',
		);

		$rest_request = new \WP_REST_Request( 'POST', sprintf( '%s/%s', $this->namespace, $this->base ) );

		/**
		 * Remove filter so the method can be tested in isolation.
		 */
		remove_filter( 'tide_api_authenticate_client', array( $this->plugin->components['keypair_auth'], 'authenticate_key_pair' ) );

		/**
		 * Test if error is thrown when SECURE_AUTH_KEY is not defined.
		 */
		$token = $this->jwt_auth->generate_token( $rest_request );
		$this->assertTrue( is_wp_error( $token ) );
		$this->assertEquals( 'rest_auth_key', $token->get_error_code() );

		$user_id = $this->factory->user->create( $user_data );

		define( 'SECURE_AUTH_KEY', self::SECURE_AUTH_KEY );

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
	}

	/**
	 * Test validate_token().
	 *
	 * @see JWT_Auth::validate_token()
	 */
	public function test_validate_token() {
		$this->assertTrue( is_wp_error( $this->jwt_auth->validate_token() ) );
		$this->assertEquals( $this->jwt_auth->validate_token()->get_error_code(), 'rest_auth_no_header' );

		// @codingStandardsIgnoreStart
		$_SERVER['HTTP_AUTHORIZATION'] = 'test_http_authorization';
		// @codingStandardsIgnoreStart

		$this->assertTrue( is_wp_error( $this->jwt_auth->validate_token() ) );
		$this->assertEquals( $this->jwt_auth->validate_token()->get_error_code(), 'rest_auth_error' );

		// @todo Test more of validate_token().
	}

	/**
	 * Test get_token().
	 *
	 * @see JWT_Auth::get_token()
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
	 * @see JWT_Auth::get_secret()
	 */
	public function test_get_secret() {
		$this->assertEquals( self::SECURE_AUTH_KEY, $this->jwt_auth->get_secret() );
	}

	/**
	 * Test validate_issuer().
	 *
	 * @see JWT_Auth::validate_issuer()
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
	 * @see JWT_Auth::validate_token_client()
	 */
	public function test_validate_token_client() {
		$token = new \stdClass();
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
	 * @see JWT_Auth::validate_token_date()
	 */
	public function test_validate_token_date() {
		$token = new \stdClass();
		$token->exp = time() - 1;

		$this->assertTrue( is_wp_error( $this->jwt_auth->validate_token_date( $token ) ) );
		$this->assertEquals( $this->jwt_auth->validate_token_date( $token )->get_error_code(), 'rest_auth_token_expired' );

		$token->exp = time() + 10000;
		$this->assertTrue( $this->jwt_auth->validate_token_date( $token ) );
	}

	/**
	 * Test get_auth_header().
	 *
	 * @see JWT_Auth::get_auth_header()
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
	 * @see JWT_Auth::authentication_errors()
	 */
	public function test_authentication_errors() {
		$this->assertEquals( null, $this->jwt_auth->authentication_errors( null ) );

		$class_name = 'WP_Tide_API\Authentication\JWT_Auth';
		$jwt_auth_mock = $this->getMockBuilder( $class_name )
					 ->disableOriginalConstructor()
					 ->getMock();

		$token = $this->_create_dummy_token_client();

		$jwt_auth_mock->method( 'validate_token' )
			 ->willReturn( $token );

		$reflected_class = new \ReflectionClass( $class_name );

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
		$token = new \stdClass();
		$token->data = new \stdClass();
		$token->data->client = new \stdClass();
		$token->data->client->type = 'wp_user';
		$token->data->client->id = 1;
		return $token;
	}
}
