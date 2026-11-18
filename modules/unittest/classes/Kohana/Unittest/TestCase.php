<?php defined('SYSPATH') or die('No direct script access.');

/**
 * A version of the stock PHPUnit testcase that includes some extra helpers
 * and default settings.
 *
 * The legacy assertion methods at the end of this class keep the upstream
 * tests (written for PHPUnit 3.7/4) running on PHPUnit 9.
 */
abstract class Kohana_Unittest_TestCase extends \PHPUnit\Framework\TestCase {

	/**
	 * Make sure PHPUnit backs up globals
	 * @var boolean
	 */
	protected $backupGlobals = FALSE;

	/**
	 * A set of unittest helpers that are shared between normal / database
	 * testcases
	 * @var Kohana_Unittest_Helpers
	 */
	protected $_helpers = NULL;

	/**
	 * A default set of environment to be applied before each test
	 * @var array
	 */
	protected $environmentDefault = array();

	/**
	 * Creates a predefined environment using the default environment
	 *
	 * Extending classes that have their own setUp() should call
	 * parent::setUp()
	 */
	protected function setUp(): void
	{
		$this->_helpers = new Unittest_Helpers;

		$this->setEnvironment($this->environmentDefault);
	}

	/**
	 * Restores the original environment overriden with setEnvironment()
	 *
	 * Extending classes that have their own tearDown()
	 * should call parent::tearDown()
	 */
	protected function tearDown(): void
	{
		if ($this->_helpers)
		{
			$this->_helpers->restore_environment();
		}
	}

	/**
	 * Removes all kohana related cache files in the cache directory
	 */
	public function cleanCacheDir()
	{
		return Unittest_Helpers::clean_cache_dir();
	}

	/**
	 * Helper function that replaces all occurences of '/' with
	 * the OS-specific directory separator
	 *
	 * @param string $path The path to act on
	 * @return string
	 */
	public function dirSeparator($path)
	{
		return Unittest_Helpers::dir_separator($path);
	}

	/**
	 * Allows easy setting & backing up of enviroment config
	 *
	 * Option types are checked in the following order:
	 *
	 * * Server Var
	 * * Static Variable
	 * * Config option
	 *
	 * @param array $environment List of environment to set
	 */
	public function setEnvironment(array $environment)
	{
		return $this->_helpers->set_environment($environment);
	}

	/**
	 * Check for internet connectivity
	 *
	 * @return boolean Whether an internet connection is available
	 */
	public function hasInternet()
	{
		return Unittest_Helpers::has_internet();
	}

	/**
	 * PHPUnit 3.7 style mock factory.
	 *
	 * @param string     $class
	 * @param array|null $methods         methods to replace; NULL replaces none, array() replaces all
	 * @param array      $arguments       constructor arguments
	 * @param string     $mock_class_name
	 * @param bool       $call_constructor
	 * @return \PHPUnit\Framework\MockObject\MockObject
	 */
	public function getMock($class, $methods = array(), array $arguments = array(), $mock_class_name = '', $call_constructor = TRUE)
	{
		$builder = $this->getMockBuilder($class);

		if ($methods !== array())
		{
			$methods  = (array) $methods;
			$existing = array_filter($methods, function ($method) use ($class) {
				return method_exists($class, $method);
			});

			$builder->onlyMethods(array_values($existing));

			if ($missing = array_diff($methods, $existing))
			{
				// Methods that only exist through __call()
				$builder->addMethods(array_values($missing));
			}
		}

		if ($arguments)
		{
			$builder->setConstructorArgs($arguments);
		}

		if ($mock_class_name !== '')
		{
			$builder->setMockClassName($mock_class_name);
		}

		if ( ! $call_constructor)
		{
			$builder->disableOriginalConstructor();
		}

		return $builder->getMock();
	}

	/**
	 * PHPUnit 3.7 style expected exception.
	 */
	public function setExpectedException($exception, $message = NULL, $code = NULL)
	{
		$this->expectException($exception);

		if ($message !== NULL AND $message !== '')
		{
			$this->expectExceptionMessage($message);
		}

		if ($code !== NULL)
		{
			$this->expectExceptionCode($code);
		}
	}

	/**
	 * Reads a property of an object or a static property of a class,
	 * whatever its visibility.
	 *
	 * @param object|string $object
	 * @param string        $name
	 * @return mixed
	 */
	public static function readAttribute($object, $name)
	{
		$property = new ReflectionProperty($object, $name);

		return $property->isStatic() ? $property->getValue() : $property->getValue($object);
	}

	public static function assertAttributeSame($expected, $name, $object, $message = '')
	{
		static::assertSame($expected, static::readAttribute($object, $name), $message);
	}

	public static function assertAttributeNotSame($expected, $name, $object, $message = '')
	{
		static::assertNotSame($expected, static::readAttribute($object, $name), $message);
	}

	public static function assertAttributeEquals($expected, $name, $object, $message = '')
	{
		static::assertEquals($expected, static::readAttribute($object, $name), $message);
	}

	public static function assertAttributeContains($needle, $name, $object, $message = '')
	{
		static::assertContains($needle, static::readAttribute($object, $name), $message);
	}

	public static function assertAttributeNotContains($needle, $name, $object, $message = '')
	{
		static::assertNotContains($needle, static::readAttribute($object, $name), $message);
	}

	/**
	 * PHPUnit 3.7 style type assertion.
	 */
	public static function assertInternalType($type, $actual, $message = '')
	{
		$map = array(
			'array'    => 'is_array',
			'bool'     => 'is_bool',
			'boolean'  => 'is_bool',
			'callable' => 'is_callable',
			'float'    => 'is_float',
			'double'   => 'is_float',
			'int'      => 'is_int',
			'integer'  => 'is_int',
			'iterable' => 'is_iterable',
			'null'     => 'is_null',
			'numeric'  => 'is_numeric',
			'object'   => 'is_object',
			'resource' => 'is_resource',
			'scalar'   => 'is_scalar',
			'string'   => 'is_string',
		);

		static::assertArrayHasKey($type, $map, 'Unknown type '.$type);
		static::assertTrue($map[$type]($actual), $message ?: 'Failed asserting that '.var_export($actual, TRUE).' is of type "'.$type.'".');
	}

	/**
	 * Also accepts a string haystack, as PHPUnit 3.7 did.
	 */
	public static function assertContains($needle, $haystack, string $message = ''): void
	{
		if (is_string($haystack))
		{
			static::assertStringContainsString((string) $needle, $haystack, $message);
		}
		else
		{
			parent::assertContains($needle, $haystack, $message);
		}
	}

	/**
	 * Also accepts a string haystack, as PHPUnit 3.7 did.
	 */
	public static function assertNotContains($needle, $haystack, string $message = ''): void
	{
		if (is_string($haystack))
		{
			static::assertStringNotContainsString((string) $needle, $haystack, $message);
		}
		else
		{
			parent::assertNotContains($needle, $haystack, $message);
		}
	}

	/**
	 * Evaluate an HTML or XML string and assert its structure and/or contents.
	 *
	 * @param array  $matcher keys: tag, attributes, content, parent, child, ancestor, descendant
	 * @param string $actual
	 * @param string $message
	 * @param bool   $isHtml
	 */
	public static function assertTag($matcher, $actual, $message = '', $isHtml = TRUE)
	{
		static::assertTrue(static::tag_match($matcher, $actual, $isHtml), $message);
	}

	/**
	 * This assertion is the exact opposite of assertTag
	 */
	public static function assertNotTag($matcher, $actual, $message = '', $isHtml = TRUE)
	{
		static::assertFalse(static::tag_match($matcher, $actual, $isHtml), $message);
	}

	/**
	 * Helper function to match HTML string tags against certain criteria
	 *
	 * @return bool TRUE if there is a match FALSE otherwise
	 */
	protected static function tag_match($matcher, $actual, $isHtml = TRUE)
	{
		$dom = new DOMDocument;
		$internal = libxml_use_internal_errors(TRUE);

		if ($isHtml)
		{
			$dom->loadHTML('<?xml encoding="utf-8"?>'.$actual);
		}
		else
		{
			$dom->loadXML($actual);
		}

		libxml_clear_errors();
		libxml_use_internal_errors($internal);

		foreach ($dom->getElementsByTagName('*') as $node)
		{
			if (static::node_matches($node, $matcher))
				return TRUE;
		}

		return FALSE;
	}

	/**
	 * @param DOMElement $node
	 * @param array      $matcher
	 * @return bool
	 */
	protected static function node_matches(DOMElement $node, array $matcher)
	{
		if (isset($matcher['tag']) AND strtolower($node->tagName) !== strtolower($matcher['tag']))
			return FALSE;

		if (isset($matcher['attributes']))
		{
			foreach ($matcher['attributes'] as $name => $value)
			{
				if ( ! $node->hasAttribute($name) OR $node->getAttribute($name) !== (string) $value)
					return FALSE;
			}
		}

		if (isset($matcher['content']) AND $node->textContent !== (string) $matcher['content'])
			return FALSE;

		if (isset($matcher['parent']))
		{
			if ( ! $node->parentNode instanceof DOMElement OR ! static::node_matches($node->parentNode, $matcher['parent']))
				return FALSE;
		}

		if (isset($matcher['ancestor']))
		{
			$found = FALSE;

			for ($parent = $node->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode)
			{
				if (static::node_matches($parent, $matcher['ancestor']))
				{
					$found = TRUE;
					break;
				}
			}

			if ( ! $found)
				return FALSE;
		}

		if (isset($matcher['child']))
		{
			$found = FALSE;

			foreach ($node->childNodes as $child)
			{
				if ($child instanceof DOMElement AND static::node_matches($child, $matcher['child']))
				{
					$found = TRUE;
					break;
				}
			}

			if ( ! $found)
				return FALSE;
		}

		if (isset($matcher['descendant']))
		{
			$found = FALSE;

			foreach ($node->getElementsByTagName('*') as $child)
			{
				if (static::node_matches($child, $matcher['descendant']))
				{
					$found = TRUE;
					break;
				}
			}

			if ( ! $found)
				return FALSE;
		}

		return TRUE;
	}
}
