<?php
/**
 * Test_Audit_Tasks
 *
 * @package WP_Tide_API
 */

use WP_Tide_API\Utility\Audit_Tasks;

/**
 * Class Test_Audit_Tasks
 *
 * @coversDefaultClass WP_Tide_API\Utility\Audit_Tasks
 *
 */
class Test_Audit_Tasks extends WP_UnitTestCase {

	/**
	 * Test Audit_Tasks::add_task.
	 *
	 * @covers ::add_task()
	 */
	public function test_add_task() {
		Audit_Tasks::add_task();
		$this->assertEquals( 1, did_action( 'tide_api_audit_tasks_add_task' ) );
	}
}