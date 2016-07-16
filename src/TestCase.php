<?php
namespace Johnbillion\DocsStandards;

abstract class TestCase extends \PHPUnit_Framework_TestCase {

	public static $docblock_missing                  = 'The docblock for `%s` should not be missing.';
	public static $docblock_desc_empty               = 'The docblock description for `%s` should not be empty.';
	public static $param_count_mismatch              = 'The number of @param docs for `%s` should match its number of parameters.';
	public static $param_desc_empty                  = 'The @param description for the `%s` parameter of `%s` should not be empty.';
	public static $param_name_incorrect              = 'The @param name for the `%s` parameter of `%s` is incorrect.';
	public static $param_type_hint_accept_array      = 'The @param type hint for the `%s` parameter of `%s` should state that it accepts an array.';
	public static $param_type_hint_accept_object     = 'The @param type hint for the `%s` parameter of `%s` should state that it accepts an object of type `%s`.';
	public static $param_type_hint_disallow_callback = '`callback` is not a valid type in the @param type hint for the `%s` parameter of `%s`. `callable` should be used instead.';
	public static $param_type_hint_accept_callable   = 'The @param type hint for the `%s` parameter of `%s` should state that it accepts a callable.';
	public static $param_type_hint_optional          = 'The @param description for the optional `%s` parameter of `%s` should state that it is optional.';
	public static $param_type_hint_not_optional      = 'The @param description for the required `%s` parameter of `%s` should not state that it is optional.';
	public static $param_type_hint_default           = 'The @param description for the `%s` parameter of `%s` should state its default value.';
	public static $param_type_hint_no_default        = 'The @param description for the `%s` parameter of `%s` should not state a default value.';

	abstract protected function getTestFunctions();

	abstract protected function getTestClasses();

	/**
	 * Test a function or method for a given class
	 *
	 * @dataProvider dataReflectionTestFunctions
	 *
	 * @param string|array $function The function name, or array of class name and method name.
	 */
	public function testFunction( $function ) {

		// We can't pass Reflector objects in here because they get printed out as the
		// data set when a test fails

		if ( is_array( $function ) ) {
			$ref  = new \ReflectionMethod( $function[0], $function[1] );
			$name = $function[0] . '::' . $function[1] . '()';
		} else {
			$ref  = new \ReflectionFunction( $function );
			$name = $function . '()';
		}

		$docblock      = new \phpDocumentor\Reflection\DocBlock( $ref );
		$doc_comment   = $ref->getDocComment();
		$method_params = $ref->getParameters();
		$doc_params    = $docblock->getTagsByName( 'param' );

		$this->assertNotFalse( $doc_comment, sprintf(
			self::$docblock_missing,
			$name
		) );

		$this->assertNotEmpty( $docblock->getShortDescription(), sprintf(
			self::$docblock_desc_empty,
			$name
		) );

		$this->assertSame( count( $method_params ), count( $doc_params ), sprintf(
			self::$param_count_mismatch,
			$name
		) );

		// @TODO check description ends in full stop
		// @TODO tests for @link or @see in descriptions, etc

		foreach ( $method_params as $i => $param ) {

			$param_doc   = $doc_params[ $i ];
			$description = $param_doc->getDescription();
			$content     = $param_doc->getContent();

			// @TODO decide how to handle variadic functions
			// ReflectionParameter::isVariadic — Checks if the parameter is variadic

			$is_hash = ( ( 0 === strpos( $description, '{' ) ) && ( ( strlen( $description ) - 1 ) === strrpos( $description, '}' ) ) );

			if ( $is_hash ) {
				$lines = explode( "\n", $description );
				$description = $lines[1];
			}

			$this->assertNotEmpty( $description, sprintf(
				self::$param_desc_empty,
				$param_doc->getVariableName(),
				$name
			) );

			list( $param_doc_type, $param_doc_name ) = preg_split( '#\s+#', $param_doc->getContent() );

			$this->assertSame( '$' . $param->getName(), $param_doc_name, sprintf(
				self::$param_name_incorrect,
				'$' . $param->getName(),
				$name
			) );

			if ( $param->isArray() ) {
				$this->assertNotFalse( strpos( $param_doc_type, 'array' ), sprintf(
					self::$param_type_hint_accept_array,
					$param_doc->getVariableName(),
					$name
				) );
			}

			if ( ( $param_class = $param->getClass() ) && ( 'stdClass' !== $param_class->getName() ) ) {
				$this->assertNotFalse( strpos( $param_doc_type, $param_class->getName() ), sprintf(
					self::$param_type_hint_accept_object,
					$param_doc->getVariableName(),
					$name,
					$param_class->getName()
				) );
			}

			$this->assertFalse( strpos( $param_doc_type, 'callback' ), sprintf(
				self::$param_type_hint_disallow_callback,
				$param_doc->getVariableName(),
				$name
			) );

			if ( $param->isCallable() ) {
				$this->assertNotFalse( strpos( $param_doc_type, 'callable' ), sprintf(
					self::$param_type_hint_accept_callable,
					$param_doc->getVariableName(),
					$name
				) );
			}

			if ( $param->isOptional() ) {
				$this->assertNotFalse( strpos( $description, 'Optional.' ), sprintf(
					self::$param_type_hint_optional,
					$param_doc->getVariableName(),
					$name
				) );
			} else {
				$this->assertFalse( strpos( $description, 'Optional.' ), sprintf(
					self::$param_type_hint_not_optional,
					$param_doc->getVariableName(),
					$name
				) );
			}

			if ( $param->isDefaultValueAvailable() && ( array() !== $param->getDefaultValue() ) ) {
				$this->assertNotFalse( strpos( $description, 'Default ' ), sprintf(
					self::$param_type_hint_default,
					$param_doc->getVariableName(),
					$name
				) );
			} else {
				$this->assertFalse( strpos( $description, 'Default ' ), sprintf(
					self::$param_type_hint_no_default,
					$param_doc->getVariableName(),
					$name
				) );
			}

		}

	}

	public function dataReflectionTestFunctions() {

		$data = array();

		foreach ( $this->getTestFunctions() as $function ) {

			if ( ! function_exists( $function ) ) {
				$this->fail( sprintf( 'Function `%s` does not exist.', $function ) );
			}

			$data[] = array(
				$function,
			);

		}

		foreach ( $this->getTestClasses() as $class ) {

			if ( ! class_exists( $class ) ) {
				$this->fail( sprintf( 'Class `%s` does not exist.', $class ) );
			}

			$class_ref = new \ReflectionClass( $class );

			foreach ( $class_ref->getMethods() as $method_ref ) {

				$data[] = array(
					array(
						$class,
						$method_ref->getName(),
					),
				);

			}

		}

		return $data;

	}

}
