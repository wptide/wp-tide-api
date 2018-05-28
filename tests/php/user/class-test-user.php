<?php
/**
 * Test_User
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\User;

use WP_Tide_API\Plugin;
use WP_Tide_API\Restriction\Rate_Limit;

/**
 * Class Test_User
 *
 * @coversDefaultClass WP_Tide_API\User\User
 */
class Test_User extends \WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * User
	 *
	 * @var User
	 */
	public $user;

	/**
	 * API Client User ID
	 *
	 * @var int
	 */
	public $api_client_user_id;

	/**
	 * Admin User ID
	 *
	 * @var int
	 */
	public $administrator_user_id;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->plugin = Plugin::instance();
		$this->user   = new User( $this->plugin );

		$this->api_client_user_id = $this->factory->user->create( array(
			'role' => 'api_client',
			'user_login' => 'test_api_client',
			'user_pass' => 'testpassword',
		) );

		$this->administrator_user_id = $this->factory->user->create( array(
			'role' => 'administrator',
			'user_login' => 'test_administrator',
			'user_pass' => 'testpassword',
		) );
	}

	/**
	 * Teardown.
	 *
	 * @inheritdoc
	 */
	function tearDown() {
		unset( $_POST['_tide_user_nonce'] );
		parent::tearDown();
	}

	/**
	 * Test add_custom_field().
	 *
	 * @covers ::add_custom_field()
	 */
	public function test_add_custom_field() {
		wp_set_current_user( $this->api_client_user_id );
		$this->assertEmpty( $this->get_custom_field() );

		wp_set_current_user( $this->administrator_user_id );
		$this->assertRegexp( '/API Rate limit. Used: 0, Default: 1000/', $this->get_custom_field() );
	}

	/**
	 * Test save_custom_field().
	 *
	 * @covers ::save_custom_field()
	 */
	public function test_save_custom_field() {
		update_user_meta( $this->api_client_user_id, User::LIMIT_USER_META_KEY, Rate_Limit::DEFAULT_LIMIT );
		update_user_meta( $this->api_client_user_id, User::INTERVAL_USER_META_KEY, Rate_Limit::DEFAULT_INTERVAL / HOUR_IN_SECONDS );

		$_POST[ User::LIMIT_USER_META_KEY ] = 5000;
		$_POST[ User::INTERVAL_USER_META_KEY ] = 12;

		wp_set_current_user( $this->api_client_user_id );
		$_POST['_tide_user_nonce'] = wp_create_nonce( 'tide_user_settings' );
		$this->user->save_custom_field( $this->api_client_user_id );

		$rate_limit = get_user_meta( $this->api_client_user_id, User::LIMIT_USER_META_KEY, true );
		$interval   = get_user_meta( $this->api_client_user_id, User::INTERVAL_USER_META_KEY, true );

		$this->assertEquals( Rate_Limit::DEFAULT_LIMIT, $rate_limit );
		$this->assertEquals( Rate_Limit::DEFAULT_INTERVAL / HOUR_IN_SECONDS, $interval );

		wp_set_current_user( $this->administrator_user_id );
		$_POST['_tide_user_nonce'] = wp_create_nonce( 'tide_user_settings' );
		$this->user->save_custom_field( $this->api_client_user_id );

		$rate_limit = get_user_meta( $this->api_client_user_id, User::LIMIT_USER_META_KEY, true );
		$interval   = get_user_meta( $this->api_client_user_id, User::INTERVAL_USER_META_KEY, true );

		$this->assertEquals( $_POST[ User::LIMIT_USER_META_KEY ], $rate_limit );
		$this->assertEquals( $_POST[ User::INTERVAL_USER_META_KEY ] * HOUR_IN_SECONDS, $interval );
	}

	/**
	 * Get the output buffer of add_custom_field().
	 */
	public function get_custom_field() {
		ob_start();
		$this->user->add_custom_field( get_user_by( 'id', $this->api_client_user_id ) );
		$page = ob_get_contents();
		ob_end_clean();

		return $page;
	}
}
