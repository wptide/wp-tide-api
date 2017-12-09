<?php
/**
 * Test_Rate_Limit
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\Restriction;

/**
 * Class Test_Rate_Limit
 *
 * @package WP_Tide_API
 */
class Test_Rate_Limit extends \WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var \WP_Tide_API\Plugin
	 */
	public $plugin;

	/**
	 * Rate Limit
	 *
	 * @var Rate_Limit
	 */
	public $restrict_rate_limit;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->plugin              = \WP_Tide_API\Plugin::instance();
		$this->restrict_rate_limit = new Rate_Limit( $this->plugin );
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
	 * Test identify_client().
	 *
	 * @see Rate_Limit::identify_client()
	 */
	public function test_identify_client_logged_out() {
		$_SERVER['HTTP_CLIENT_IP'] = '127.0.0.1';
		$this->assertSame( 'logged_out_127.0.0.1', $this->restrict_rate_limit->identify_client() );
	}

	/**
	 * Test identify_client().
	 *
	 * @see Rate_Limit::identify_client()
	 */
	public function test_identify_client_filtered() {
		add_filter( 'tide_api_request_client', function() {
			return 'testclient';
		} );
		$this->assertSame( 'testclient', $this->restrict_rate_limit->identify_client() );
	}

	/**
	 * Test identify_client().
	 *
	 * @see Rate_Limit::identify_client()
	 */
	public function test_identify_client_logged_in() {
		$user_id = $this->factory()->user->create( array(
			'user_login' => 'foo',
		) );
		wp_set_current_user( $user_id );
		$this->assertSame( $user_id, $this->restrict_rate_limit->identify_client() );
	}
}
