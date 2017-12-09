<?php
/**
 * This file contains convenient methods for actions required throughout the plugin.
 *
 * @package WP_Tide_API
 */

namespace WP_Tide_API;

/**
 * Class Utility
 */
class Utility {

	/**
	 * Convert a PHP object into an array.
	 *
	 * @param object $object The object to map into an array.
	 *
	 * @return mixed
	 */
	public static function object_to_array( $object ) {
		if ( is_object( $object ) ) {
			$object = get_object_vars( $object );
		}

		if ( is_array( $object ) ) {
			return array_map( array( new static, 'object_to_array' ), $object );
		} else {
			return $object;
		}
	}

	/**
	 * Convert an array into a PHP object.
	 *
	 * @param array $array The array to turn into an object.
	 *
	 * @return mixed
	 */
	public static function array_to_object( $array ) {
		if ( is_array( $array ) ) {
			return (object) array_map( array( new static, 'array_to_object' ), $array );
		} else {
			return $array;
		}
	}

	/**
	 * Echo out escaped and sanitised content.
	 *
	 * @param string $content    The content to output.
	 * @param bool   $form       Include form markup.
	 * @param bool   $script_ok  Allow a <script> snippet.
	 * @param array  $extra_html Extra HTML to include.
	 */
	public static function output( $content, $form = false, $script_ok = false, $extra_html = array() ) {

		$allowed_html = wp_kses_allowed_html( 'post' );

		if ( true === $form ) {
			/**
			 * Allowed HTML attributes for form elements.
			 *
			 * @link: https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes
			 */
			$form_attributes = array(
				'accept'          => true,
				'accept-charset'  => true,
				'accesskey'       => true,
				'action'          => true,
				'alt'             => true,
				'autocomplete'    => true,
				'autofocus'       => true,
				'autosave'        => true,
				'checked'         => true,
				'class'           => true,
				'cols'            => true,
				'contenteditable' => true,
				'dir'             => true,
				'dirname'         => true,
				'disabled'        => true,
				'draggable'       => true,
				'dropzone'        => true,
				'enctype'         => true,
				'for'             => true,
				'form'            => true,
				'formaction'      => true,
				'height'          => true,
				'hidden'          => true,
				'id'              => true,
				'itemprop'        => true,
				'lang'            => true,
				'list'            => true,
				'max'             => true,
				'maxlength'       => true,
				'method'          => true,
				'min'             => true,
				'multiple'        => true,
				'name'            => true,
				'novalidate'      => true,
				'pattern'         => true,
				'placeholder'     => true,
				'readonly'        => true,
				'required'        => true,
				'rows'            => true,
				'selected'        => true,
				'size'            => true,
				'spellcheck'      => true,
				'src'             => true,
				'step'            => true,
				'style'           => true,
				'tabindex'        => true,
				'target'          => true,
				'title'           => true,
				'type'            => true,
				'usemap'          => true,
				'value'           => true,
				'width'           => true,
				'wrap'            => true,
			);

			$form_elements = array(
				'button'   => $form_attributes,
				'datalist' => $form_attributes,
				'fieldset' => $form_attributes,
				'form'     => $form_attributes,
				'input'    => $form_attributes,
				'keygen'   => $form_attributes,
				'label'    => $form_attributes,
				'legend'   => $form_attributes,
				'optgroup' => $form_attributes,
				'option'   => $form_attributes,
				'output'   => $form_attributes,
				'select'   => $form_attributes,
				'textarea' => $form_attributes,
			);

			$allowed_html = array_merge( $allowed_html, $form_elements );
		}

		if ( true === $script_ok ) {
			$allowed_html = array_merge( array(
				'script' => array(
					'type' => true,
				),
			), $allowed_html );
		}

		if ( ! empty( $extra_html ) && is_array( $extra_html ) ) {
			$allowed_html = array_merge( $allowed_html, $extra_html );
		}

		$allowed_html = apply_filters( 'wp_tide_api_allowed_html', $allowed_html );

		echo wp_kses( $content, $allowed_html );
	}

	/**
	 * Convenience method for $_GET variables.
	 *
	 * Pass in false to return a sanitised version of the $_GET array.
	 *
	 * @uses Utility::input_query
	 *
	 * @param mixed $name Array key of item in the $_GET global array.
	 *
	 * @return mixed
	 */
	public static function get_query( $name = false ) {

		return static::input_query( 'get', false, false, $name );
	}

	/**
	 * Convenience method for $_POST variables.
	 *
	 * Accessing $_POST using this method expects a valid nonce.
	 *
	 * Pass in false to return a sanitised version of the $_POST array.
	 *
	 * @uses Control_Utility::input_query
	 *
	 * @param mixed  $name        Array key of item in the $_POST global array.
	 * @param int    $action      Nonce action.
	 * @param String $nonce_field Nonce field to check.
	 *
	 * @return mixed
	 */
	public static function post_query( $name = false, $action = -1, $nonce_field = '_wpnonce' ) {

		return static::input_query( 'post', $action, $nonce_field, $name );
	}

	/**
	 * Convenience method for $_REQUEST variables.
	 *
	 * Pass in false to return a sanitised version of the $_REQUEST array.
	 *
	 * @uses Utility::input_query
	 *
	 * @param mixed $name Array key of item in the $_REQUEST global array.
	 *
	 * @return mixed
	 */
	public static function request_query( $name = false ) {

		return static::input_query( 'request', false, false, $name );
	}

	/**
	 * This method retrieves $_GET, $_POST, and $_REQUEST items.
	 *
	 * @param mixed $type        Type of request.
	 * @param int   $action      Nonce action if dealing with POST.
	 * @param mixed $nonce_field Nonce field if dealing with POST.
	 * @param mixed $name        Array key of the item in the array.
	 *
	 * @return null
	 */
	public static function input_query( $type = 'get', $action = -1, $nonce_field = '_wpnonce', $name = false ) {

		$query = false;

		if ( 'get' === strtolower( $type ) || 'request' === strtolower( $type ) || ( 'post' === strtolower( $type ) && ! empty( $_POST[ $nonce_field ] ) && wp_verify_nonce( sanitize_key( $_POST[ $nonce_field ] ), $action ) ) ) { // WPCS: input var okay.

			if ( false === $name ) {
				// @codingStandardsIgnoreStart
				switch ( strtolower( $type ) ) {
					case 'get':
						$query = $_GET;
						break;
					case 'post':
						$query = $_POST;
						break;
					case 'request':
						$query = $_REQUEST;
						break;
				}
				// @codingStandardsIgnoreEnd
			} else {
				if ( isset( $_GET[ $name ] ) || isset( $_POST[ $name ] ) || isset( $_REQUEST[ $name ] ) ) { // WPCS: input var okay.
					// @codingStandardsIgnoreStart
					switch ( strtolower( $type ) ) {
						case 'get':
							$query = $_GET[ $name ];
							break;
						case 'post':
							$query = $_POST[ $name ];
							break;
						case 'request':
							$query = $_REQUEST[ $name ];
							break;
					}
					// @codingStandardsIgnoreEnd
				}
			}
		}

		if ( is_array( $query ) ) {
			array_walk_recursive( $query, array( new static, 'sanitize_array_item' ) );
		} else {
			$query = sanitize_text_field( wp_unslash( $query ) );
		}

		if ( ! empty( $query ) || '' === $query ) {
			return $query;
		}

		return null;
	}

	/**
	 * Used by array_walk_recursive to sanitize array items.
	 *
	 * @param mixed $item Array item.
	 *
	 * @return string
	 */
	public static function sanitize_array_item( &$item ) {
		return sanitize_text_field( wp_unslash( $item ) );
	}
}
