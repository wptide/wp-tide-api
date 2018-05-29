<?php
/**
 * Test_Base
 *
 * @package WP_Tide_API
 */

/**
 * Class Test_Base
 *
 * @coversDefaultClass WP_Tide_API\Base
 *
 */
class Test_Base extends WP_UnitTestCase {

	/**
	 * Base instance.
	 *
	 * @var Base
	 */
	public $base;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();

		$this->base = new Base();
	}

	/**
	 * Test hook_by_reflection().
	 *
	 * @see ::hook_by_reflection()
	 */
	public function test_hook_by_reflection() {
		$this->assertEquals( 10, has_action( 'init', array( $this->base, 'init_action' ) ) );
		$this->assertEquals( 10, has_action( 'the_content', array( $this->base, 'the_content_filter' ) ) );
	}


	/**
	 * Test remove_object_hooks().
	 *
	 * @see ::remove_object_hooks()
	 */
	public function test_remove_object_hooks() {
		$this->base->remove_object_hooks();
		$this->assertFalse( has_action( 'init', array( $this->base, 'init_action' ) ) );
		$this->assertFalse( has_action( 'the_content', array( $this->base, 'the_content_filter' ) ) );
	}

	/**
	 * Test __destruct().
	 *
	 * @see ::__destruct()
	 */
	public function test___destruct() {
		$this->base->hook_by_reflection();
		$this->assertEquals( 10, has_action( 'init', array( $this->base, 'init_action' ) ) );
		$this->assertEquals( 10, has_action( 'the_content', array( $this->base, 'the_content_filter' ) ) );

		$this->base->__destruct();
		$this->assertFalse( has_action( 'init', array( $this->base, 'init_action' ) ) );
		$this->assertFalse( has_action( 'the_content', array( $this->base, 'the_content_filter' ) ) );
	}
}

/**
 * Base class.
 */
class Base extends WP_Tide_API\Base {

	/**
	 * Load this on the init action hook.
	 *
	 * @action init
	 */
	public function init_action() {}

	/**
	 * Load this on the the_content filter hook.
	 *
	 * @filter the_content
	 *
	 * @param string $content The content.
	 * @return string
	 */
	public function the_content_filter( $content ) {
		return $content;
	}
}