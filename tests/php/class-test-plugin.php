<?php
/**
 * Test_Plugin
 *
 * @package WP_Tide_API
 */

/**
 * Class Test_Plugin
 *
 * @coversDefaultClass WP_Tide_API\Plugin
 *
 */
class Test_Plugin extends WP_UnitTestCase {

	/**
	 * Test __construct().
	 *
	 * @see ::__construct()
	 */
	public function test___construct() {
		$components = array(
			'api_bootstrap',
			'api_endpoint_audit',
			'api_endpoint_report',
			'jwt_auth',
			'keypair_auth',
			'user_refresh_token',
			'storage_gcs',
			'storage_local',
			'storage_s3',
			'queue_firestore',
			'queue_local',
			'queue_sqs',
			'user',
		);

		$plugin = new WP_Tide_API\Plugin( array(
			'api_namespace' => 'test',
			'api_version'   => 'test'
		) );

		foreach( $plugin->components as $component => $value ) {
			$this->assertContains( $component, $components );
		}

		do_action( 'init' );
		$this->assertInstanceOf( 'WP_Tide_API\Restriction\Rate_Limit', $plugin->components['restrict_rate_limit'] );
	}
}