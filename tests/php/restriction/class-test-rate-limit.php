<?php
/**
 * Test_Rate_Limit
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\Restriction\Rate_Limit;
use WP_Tide_API\User\User;

/**
 * Class Test_Rate_Limit
 *
 * @coversDefaultClass WP_Tide_API\Restriction\Rate_Limit
 */
class Test_Rate_Limit extends WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var WP_Tide_API\Plugin
	 */
	public $plugin;

	/**
	 * Rate Limit
	 *
	 * @var Rate_Limit
	 */
	public $rate_limit;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->plugin     = WP_Tide_API\Plugin::instance();
		$this->rate_limit = new Rate_Limit( $this->plugin );
	}

	/**
	 * Teardown.
	 *
	 * @inheritdoc
	 */
	function tearDown() {
		unset( $_SERVER['HTTP_CLIENT_IP'] );
		parent::tearDown();
	}

	/**
	 * Test __construct().
	 *
	 * @covers ::__construct()
	 */
	public function test___construct() {
		$settings = array(
			'rate_limit' => 101,
			'interval'   => 102,
		);
		$mock = $this->getMockBuilder( get_class( $this->rate_limit ) )
			->disableOriginalConstructor()
			->setConstructorArgs( array( $this->plugin ) )
			->setMethods( array( 'get_values' ) )
			->getMock();

		$mock->expects( $this->any() )
			->method( 'get_values' )
			->willReturn( $settings );

		$this->assertSame( $settings, $mock->get_values() );

		$mock->__construct( $this->plugin );

		$this->assertSame( 101, $mock->get_rate_limit() );
		$this->assertSame( 102, $mock->get_interval() );
	}

	/**
	 * Test is_free().
	 *
	 * @covers ::is_free()
	 */
	public function test_is_free() {
		$this->assertTrue( $this->rate_limit->is_free( new WP_REST_Request( 'GET', '/tide/v1/audit' ) ) );
		$this->assertTrue( $this->rate_limit->is_free( new WP_REST_Request( 'POST', '/tide/v1/auth' ) ) );
		$this->assertFalse( $this->rate_limit->is_free( new WP_REST_Request( 'GET', '/tide/v1/report' ) ) );
	}

	/**
	 * Test check_rate_limit().
	 *
	 * @covers ::check_rate_limit()
	 */
	public function test_check_rate_limit() {
		$server = $this->getMockBuilder( get_class( new WP_REST_Server ) )
			->getMock();

		$server->expects( $this->any() )
			->method( 'send_headers' )
			->willReturn( false );

		$request = new WP_REST_Request( 'POST', '/tide/v1/audit' );
		$user_id = $this->factory()->user->create( array(
			'user_login' => 'foo',
		) );

		wp_set_current_user( $user_id );

		$this->assertSame( 'response', $this->rate_limit->check_rate_limit( 'response', $server, $request ) );

		$rate_counter = get_transient( Rate_Limit::CACHE_KEY_PREFIX . $user_id );
		$this->assertEquals( 1, $rate_counter['used'] );

		$rate_counter = array(
			'id'       => $user_id,
			'interval' => Rate_Limit::DEFAULT_INTERVAL,
			'limit'    => Rate_Limit::DEFAULT_LIMIT,
			'used'     => 1000,
			'start'    => time() - Rate_Limit::DEFAULT_INTERVAL,
		);
		$expire       = $rate_counter['start'] + $rate_counter['interval'] - time();

		set_transient( Rate_Limit::CACHE_KEY_PREFIX . $user_id, $rate_counter, $expire );

		// Doesn't count in the same request.
		$this->assertSame( 'response', $this->rate_limit->check_rate_limit( 'response', $server, $request ) );

		$rate_counter = get_transient( Rate_Limit::CACHE_KEY_PREFIX . $user_id );
		$this->assertEquals( 1000, $rate_counter['used'] );

		$reflection = new ReflectionClass( $this->rate_limit );
		$property = $reflection->getProperty('point_deducted');
		$property->setAccessible( true );
		$property->setValue( $this->rate_limit, false );

		// Mock a second request.
		$response = $this->rate_limit->check_rate_limit( 'response', $server, $request );
		$this->assertEquals( 'rate_limit_exceeded', $response->data['code'] );

		$property->setValue( $this->rate_limit, false );

		// Mock a third request which is now expired.
		$this->assertEquals( 'response', $this->rate_limit->check_rate_limit( 'response', $server, $request ) );

		$rate_counter = get_transient( Rate_Limit::CACHE_KEY_PREFIX . $user_id );
		$this->assertEquals( 1, $rate_counter['used'] );
	}

	/**
	 * Test identify_client().
	 *
	 * @covers ::identify_client()
	 */
	public function test_identify_client_logged_out() {
		$_SERVER['HTTP_CLIENT_IP'] = '127.0.0.1';
		$this->assertSame( 'logged_out_127.0.0.1', $this->rate_limit->identify_client() );
	}

	/**
	 * Test identify_client().
	 *
	 * @covers ::identify_client()
	 */
	public function test_identify_client_filtered() {
		add_filter( 'tide_api_request_client', function() {
			return 'testclient';
		} );
		$this->assertSame( 'testclient', $this->rate_limit->identify_client() );
	}

	/**
	 * Test identify_client().
	 *
	 * @covers ::identify_client()
	 */
	public function test_identify_client_logged_in() {
		$user_id = $this->factory()->user->create( array(
			'user_login' => 'foo',
		) );
		wp_set_current_user( $user_id );
		$this->assertSame( $user_id, $this->rate_limit->identify_client() );
	}

	/**
	 * Test get_rate_limit().
	 *
	 * @covers ::get_rate_limit()
	 */
	public function test_get_rate_limit() {
		$this->assertSame( Rate_Limit::DEFAULT_LIMIT, $this->rate_limit->get_rate_limit() );
	}

	/**
	 * Test set_rate_limit().
	 *
	 * @covers ::set_rate_limit()
	 */
	public function test_set_rate_limit() {
		$this->rate_limit->set_rate_limit( 10 );
		$this->assertSame( 10, $this->rate_limit->get_rate_limit() );
	}

	/**
	 * Test get_interval().
	 *
	 * @covers ::get_interval()
	 */
	public function test_get_interval() {
		$this->assertSame( Rate_Limit::DEFAULT_INTERVAL, $this->rate_limit->get_interval() );
	}

	/**
	 * Test set_interval().
	 *
	 * @covers ::set_interval()
	 */
	public function test_set_interval() {
		$this->rate_limit->set_interval( 10 );
		$this->assertSame( 10, $this->rate_limit->get_interval() );
	}

	/**
	 * Test get_rate_limit_values().
	 *
	 * @covers ::get_rate_limit_values()
	 */
	public function test_get_rate_limit_values() {
		$user_id = $this->factory()->user->create( array(
			'user_login' => 'foo',
		) );
		wp_set_current_user( $user_id );
		update_user_meta( $user_id, User::LIMIT_USER_META_KEY, 15 );
		update_user_meta( $user_id, User::INTERVAL_USER_META_KEY, 15 );

		$settings = Rate_Limit::get_rate_limit_values( $user_id );
		$this->assertEquals( 15, $settings['rate_limit'] );
		$this->assertEquals( 15, $settings['interval'] );
	}

	/**
	 * Test get_values().
	 *
	 * @covers ::get_values()
	 */
	public function test_get_values() {
		$user_id = $this->factory()->user->create( array(
			'user_login' => 'foo',
		) );
		wp_set_current_user( $user_id );
		update_user_meta( $user_id, User::LIMIT_USER_META_KEY, 20 );
		update_user_meta( $user_id, User::INTERVAL_USER_META_KEY, 20 );
		delete_option( Rate_Limit::SETTINGS_KEY );

		$settings = $this->rate_limit->get_values();
		$this->assertEquals( 20, $settings['rate_limit'] );
		$this->assertEquals( 20, $settings['interval'] );
	}
}
