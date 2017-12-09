<?php
/**
 * This file has utility methods for sending, retrieving and manipulating tasks.
 *
 * It is an abstract wrapper framework that will be using `actions` and `filters` to manage audit tasks.
 * These will then be hooked by integrations that will be performing the real work.
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API\Utility;

/**
 * Class Audit_Tasks
 */
class Audit_Tasks {

	/**
	 * Get next audit tasks.
	 *
	 * @return mixed
	 */
	public static function get_next() {
		return apply_filters( 'tide_api_audit_tasks_get_tasks', false );
	}

	/**
	 * Record a new audit task to perform.
	 *
	 * @param array $task The task array.
	 */
	public static function add_task( $task = array() ) {
		do_action( 'tide_api_audit_tasks_add_task', $task );
	}
}
