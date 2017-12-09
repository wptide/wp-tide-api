<?php
/**
 * Test_WP_Tide_API
 *
 * @package WP_Tide_API
 */

/**
 * Class Test_WP_Tide_API
 *
 * @package WP_Tide_API
 */
class Test_WP_Tide_API extends \WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var WP_Tide_API
	 */
	public $plugin;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$this->plugin = new WP_Tide_API();
		$this->plugin->launch_plugin();
	}

	/**
	 * Test version_fail().
	 *
	 * @see WP_Tide_API::version_fail()
	 */
	public function test_version_fail() {
		ob_start();
		$this->plugin->version_fail();
		$buffer = ob_get_clean();
		$this->assertContains( '<div class="error">', $buffer );
	}

	/**
	 * Test installation_fail().
	 *
	 * @see WP_Tide_API::installation_fail()
	 */
	public function test_installation_fail() {
		ob_start();
		$this->plugin->installation_fail();
		$buffer = ob_get_clean();
		$this->assertContains( '<div class="error">', $buffer );
	}

	/**
	 * Test launch_plugin()
	 *
	 * This is called during setUp() so we'll check against the global variable:  $wp_tide_api_plugin;
	 *
	 * @see WP_Tide_API::launch_plugin()
	 */
	public function test_launch_plugin() {
		global $wp_tide_api_plugin;

		$this->assertInstanceOf( '\WP_Tide_API\Plugin', $wp_tide_api_plugin );
	}

	/**
	 * Test parse_header_information()
	 *
	 * This is a private method so we'll check against array in $wp_tide_api_plugin->info
	 *
	 * @see WP_Tide_API::parse_header_information()
	 */
	public function test_parse_header_information() {
		global $wp_tide_api_plugin;

		$this->assertArrayHasKey( 'name', $wp_tide_api_plugin->info );
		$this->assertArrayHasKey( 'plugin_uri', $wp_tide_api_plugin->info );
		$this->assertArrayHasKey( 'version', $wp_tide_api_plugin->info );
		$this->assertArrayHasKey( 'description', $wp_tide_api_plugin->info );
		$this->assertArrayHasKey( 'author', $wp_tide_api_plugin->info );
		$this->assertArrayHasKey( 'author_uri', $wp_tide_api_plugin->info );
		$this->assertArrayHasKey( 'text_domain', $wp_tide_api_plugin->info );
		$this->assertArrayHasKey( 'domain_path', $wp_tide_api_plugin->info );
		$this->assertArrayHasKey( 'network', $wp_tide_api_plugin->info );
	}

	/**
	 * Test setup_paths()
	 *
	 * This is a private method so we'll check against array in $wp_tide_api_plugin->info
	 *
	 * @see WP_Tide_API::setup_paths()
	 */
	public function test_setup_paths() {
		global $wp_tide_api_plugin;

		$this->assertArrayHasKey( 'location', $wp_tide_api_plugin->info );
		$this->assertArrayHasKey( 'plugin_dir', $wp_tide_api_plugin->info );
		$this->assertArrayHasKey( 'plugin_url', $wp_tide_api_plugin->info );
		$this->assertArrayHasKey( 'base_name', $wp_tide_api_plugin->info );
		$this->assertArrayHasKey( 'include_dir', $wp_tide_api_plugin->info );
		$this->assertArrayHasKey( 'include_url', $wp_tide_api_plugin->info );
		$this->assertArrayHasKey( 'assets_dir', $wp_tide_api_plugin->info );
		$this->assertArrayHasKey( 'assets_url', $wp_tide_api_plugin->info );
		$this->assertArrayHasKey( 'languages_dir', $wp_tide_api_plugin->info );
		$this->assertArrayHasKey( 'languages_url', $wp_tide_api_plugin->info );
	}
}
