<?php
/**
 * Test_User
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\Plugin;
use WP_Tide_API\Utility\User;

/**
 * Class Test_User
 *
 * @coversDefaultClass WP_Tide_API\Utility\User
 *
 */
class Test_User extends WP_UnitTestCase {

	protected $mock_project;

	protected $api_user;

	const SECURE_AUTH_KEY = '54fda65we2aeb65abaq354150966b198e3444198';

	public function setUp() {
		parent::setUp();

		// Create mock project.
		$this->mock_project = $this->factory->post->create( array(
			'post_title' => 'Mock Project',
			'post_type'  => 'audit',
		) );

		// Create API user.
		$this->api_user = $this->factory->user->create( array(
			'user_login' => 'test_user',
		) );
		$user           = new WP_User( $this->api_user );
		$user->add_cap( 'api_client' );
		update_user_meta( $this->api_user, 'tide_api_user_key', 'api_key' );
		update_user_meta( $this->api_user, 'tide_api_user_secret', 'api_secret' );
	}

	/**
	 * Test User::authenticated.
	 *
	 * @covers ::authenticated()
	 */
	public function test_authenticated() {


		$tests = [
			[
				'args' => [],
				'want' => false,
			],
			[
				'args' => [
					'username' => 'test_user',
					'password' => 'password',
				],
				'want' => true,
			],
			[
				'args' => [
					'request'        => new \WP_REST_Request(),
					'request_fields' => [
						'api_key' => 'api_key',
						'api_secret' => 'api_secret',
					],
				],
				'want' => true,
			],
		];

		foreach ( $tests as $t ) {

			wp_logout();

			if ( ! empty( $t['args']['username'] ) && ! empty( $t['args']['password'] ) ) {
				$user = wp_signon( [
					'user_login'    => $t['args']['username'],
					'user_password' => $t['args']['password'],
				] );
				if ( ! is_wp_error( $user ) ) {
					wp_set_current_user( $user->ID );
				}
			}

			if ( ! empty( $t['args']['request'] ) ) {
				$req = $t['args']['request'];
				$req->set_param( 'api_key', $t['args']['request_fields']['api_key'] );
				$req->set_param( 'api_secret', $t['args']['request_fields']['api_secret'] );

				if ( ! defined( 'SECURE_AUTH_KEY' ) ) {
					define( 'SECURE_AUTH_KEY', self::SECURE_AUTH_KEY );
				}

				$token = Plugin::instance()->components['jwt_auth']->generate_token( $req );
				$_SERVER['HTTP_AUTHORIZATION'] = sprintf( 'Bearer %s', $token['access_token'] );
			}

			$got = User::authenticated();

			// Use array_values() to reset indexes.
			$this->assertEquals( (bool) $t['want'], (bool) $got );
		}

	}

	/**
	 * Test User::has_cap.
	 *
	 * @covers ::has_cap()
	 */
	public function test_has_cap() {


		$tests = [
			[
				'fields' => [
					'cap' => 'audit_client',
				],
				'args' => [],
				'want' => false,
			],
			[
				'fields' => [
					'cap' => 'audit_client',
				],
				'args' => [
					'username' => 'test_user',
					'password' => 'password',
				],
				'want' => false,
			],
			[
				'fields' => [
					'cap' => 'api_client',
				],
				'args' => [
					'username' => 'test_user',
					'password' => 'password',
				],
				'want' => true,
			],
		];

		foreach ( $tests as $t ) {

			wp_logout();

			if ( ! empty( $t['args']['username'] ) && ! empty( $t['args']['password'] ) ) {
				$user = wp_signon( [
					'user_login'    => $t['args']['username'],
					'user_password' => $t['args']['password'],
				] );
				if ( ! is_wp_error( $user ) ) {
					wp_set_current_user( $user->ID );
				}
			}

			$got = User::has_cap( $t['fields']['cap'] );

			// Use array_values() to reset indexes.
			$this->assertEquals( (bool) $t['want'], (bool) $got );
		}

	}

}