<?php
/**
 * This file describes the base class for all WP Tide API objects.
 *
 * Hooks by Reflection:
 * Methods can be used to hook into WordPress actions and filters by specifying @action or @filter in the method's doc
 * block.
 *
 * Format is as follows:
 *
 * @action  <action_to_hook>[, <priority>] for actions.
 * @filter  <filter_to_hook>[, <priority>] for filters.
 *
 * If <priority> is not defined a default of 10 will be used.
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API;

/**
 * Class Base
 */
abstract class Base {

	/**
	 * Array of classes already hooked via reflection.
	 *
	 * @var array
	 */
	protected $_classes_hooked = array();

	/**
	 * The Plugin.
	 *
	 * @var Plugin The plugin.
	 */
	public $plugin;

	/**
	 * Base constructor.
	 *
	 * @param bool|Plugin $plugin The plugin.
	 */
	public function __construct( $plugin = false ) {
		if ( ! empty( $plugin ) ) {
			$this->plugin = $plugin;
		}
		$this->hook_by_reflection();
	}

	/**
	 * Base destructor.
	 */
	function __destruct() {
		$this->remove_object_hooks();
	}

	/**
	 * Use PHP DocBlocks to hook actions and filters.
	 */
	public function hook_by_reflection() {
		$current_class = get_class( $this );
		if ( ! in_array( $current_class, $this->_classes_hooked, true ) ) {
			$this->_classes_hooked[] = $current_class;

			$reflector = new \ReflectionObject( $this );
			foreach ( $reflector->getMethods() as $method ) {
				$doc       = $method->getDocComment();
				$arg_count = $method->getNumberOfParameters();
				if ( preg_match_all( '#\* @(?P<type>filter|action)\s+(?P<name>[a-z0-9\-\._]+)(?:,\s+(?P<priority>\d+))?#', $doc, $matches, PREG_SET_ORDER ) ) {
					foreach ( $matches as $match ) {
						$type     = $match['type'];
						$name     = $match['name'];
						$priority = empty( $match['priority'] ) ? 10 : intval( $match['priority'] );
						$callback = array( $this, $method->getName() );
						call_user_func( 'add_' . $type, $name, $callback, $priority, $arg_count );
					}
				}
			}
		}
	}

	/**
	 * Removes the added DocBlock hooks.
	 *
	 * @param object $object The class object.
	 */
	public function remove_object_hooks( $object = null ) {
		if ( is_null( $object ) ) {
			$object = $this;
		}
		$class_name = get_class( $object );
		$reflector  = new \ReflectionObject( $object );
		foreach ( $reflector->getMethods() as $method ) {
			$doc = $method->getDocComment();
			if ( preg_match_all( '#\* @(?P<type>filter|action)\s+(?P<name>[a-z0-9\-\._]+)(?:,\s+(?P<priority>\d+))?#', $doc, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					$type     = $match['type'];
					$name     = $match['name'];
					$priority = empty( $match['priority'] ) ? 10 : intval( $match['priority'] );
					$callback = array( $object, $method->getName() );
					call_user_func( "remove_{$type}", $name, $callback, $priority );
				}
			}
		}
		unset( $this->_classes_hooked[ $class_name ] );
	}
}
