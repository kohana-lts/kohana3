<?php defined('SYSPATH') OR die('Kohana bootstrap needs to be included before tests run');

/**
 * Tests Kohana Exception Class
 *
 * @group kohana
 * @group kohana.core
 * @group kohana.core.exception
 *
 * @package    Kohana
 * @category   Tests
 * @author     Kohana Team
 * @copyright  (c) 2008-2012 Kohana Team
 * @license    http://kohanaframework.org/license
 */
class Kohana_ExceptionTest extends Unittest_TestCase
{
	/**
	 * Provides test data for test_constructor()
	 *
	 * @return array
	 */
	public function provider_constructor()
	{
		return array(
			array(array(''), '', 0),
			array(array(':a'), ':a', 0),

			array(array(':a', NULL), ':a', 0),
			array(array(':a', array()), ':a', 0),
			array(array(':a', array(':a' => 'b')), 'b', 0),
			array(array(':a :b', array(':a' => 'c', ':b' => 'd')), 'c d', 0),

			array(array(':a', NULL, 5), ':a', 5),
			// #3358
			array(array(':a', NULL, '3F000'), ':a', '3F000'),
			// #3404
			array(array(':a', NULL, '42S22'), ':a', '42S22'),
			// #3927
			array(array(':a', NULL, 'b'), ':a', 'b'),
			// #4039
			array(array(':a', NULL, '25P01'), ':a', '25P01'),
		);
	}

	/**
	 * Tests Kohana_Kohana_Exception::__construct()
	 *
	 * @test
	 * @dataProvider provider_constructor
	 * @covers Kohana_Kohana_Exception::__construct
	 * @param array             $arguments          Arguments
	 * @param string            $expected_message   Value from getMessage()
	 * @param integer|string    $expected_code      Value from getCode()
	 */
	public function test_constructor($arguments, $expected_message, $expected_code)
	{
		switch (count($arguments))
		{
			case 1:
				$exception = new Kohana_Exception(reset($arguments));
			break;
			case 2:
				$exception = new Kohana_Exception(reset($arguments), next($arguments));
			break;
			default:
				$exception = new Kohana_Exception(reset($arguments), next($arguments), next($arguments));
		}

		$this->assertSame($expected_code, $exception->getCode());
		$this->assertSame($expected_message, $exception->getMessage());
	}

	/**
	 * Provides test data for test_text()
	 *
	 * @return array
	 */
	public function provider_text()
	{
		return array(
			array(new Kohana_Exception('foobar'), $this->dirSeparator('Kohana_Exception [ 0 ]: foobar ~ SYSPATH/tests/kohana/ExceptionTest.php [ '.__LINE__.' ]')),
		);
	}

	/**
	 * Tests Kohana_Exception::text()
	 *
	 * @test
	 * @dataProvider provider_text
	 * @covers Kohana_Exception::text
	 * @param object $exception exception to test
	 * @param string $expected  expected output
	 */
	public function test_text($exception, $expected)
	{
		$this->assertEquals($expected, Kohana_Exception::text($exception));
	}

	/**
	 * PHP 7+ throws Error objects, which are not Exception objects.
	 *
	 * @test
	 * @covers Kohana_Exception::_handler
	 * @covers Kohana_Exception::exception
	 */
	public function test_handler_accepts_errors()
	{
		$log = Kohana::$log;
		Kohana::$log = new Log;

		// The error page dumps trace arguments, and PHPUnit objects are huge
		$ignore_args = ini_set('zend.exception_ignore_args', '1');

		$response = Kohana_Exception::_handler(new TypeError('type error message'));

		ini_set('zend.exception_ignore_args', $ignore_args);
		Kohana::$log = $log;

		$this->assertInstanceOf('Response', $response);
		$this->assertSame(500, $response->status());
	}

	/**
	 * @test
	 * @covers Kohana_Exception::exception
	 */
	public function test_exception_wraps_errors()
	{
		$error = new Error('message');
		$wrapped = Kohana_Exception::exception($error);

		$this->assertInstanceOf('ErrorException', $wrapped);
		$this->assertSame($error, $wrapped->getPrevious());
		$this->assertSame('Error: message', $wrapped->getMessage());

		$exception = new Exception('x');
		$this->assertSame($exception, Kohana_Exception::exception($exception));
	}

	public function provider_production_response()
	{
		return array(
			array(new Kohana_Exception('secret :value', array(':value' => 'database password')), 500, 'Internal Server Error'),
			array(HTTP_Exception::factory(404, 'The secret page :uri', array(':uri' => '/x')), 404, 'Not Found'),
			array(new ErrorException('secret notice', 0, E_NOTICE, '/secret/file.php', 10), 500, 'Internal Server Error'),
		);
	}

	/**
	 * In production the error page shows only the HTTP status.
	 *
	 * @test
	 * @dataProvider provider_production_response
	 * @covers Kohana_Exception::response
	 * @covers Kohana_Exception::response_production
	 */
	public function test_production_response_has_no_details($exception, $status, $message)
	{
		$this->setEnvironment(array(
			'Kohana::$environment' => Kohana::PRODUCTION,
			'Kohana_Exception::$show_details' => NULL,
		));

		$response = Kohana_Exception::response($exception);
		$body = $response->body();

		$this->assertSame($status, $response->status());
		$this->assertStringContainsString($status.' '.$message, $body);
		$this->assertStringNotContainsString('secret', $body);
		$this->assertStringNotContainsString('$_SERVER', $body);
		$this->assertStringNotContainsString(Debug::path(SYSPATH), $body);
		$this->assertStringNotContainsString(SYSPATH, $body);
	}

	/**
	 * @test
	 * @covers Kohana_Exception::show_details
	 */
	public function test_show_details()
	{
		$this->setEnvironment(array(
			'Kohana::$environment' => Kohana::PRODUCTION,
			'Kohana_Exception::$show_details' => NULL,
		));
		$this->assertFalse(Kohana_Exception::show_details());

		$this->setEnvironment(array('Kohana_Exception::$show_details' => TRUE));
		$this->assertTrue(Kohana_Exception::show_details());

		$this->setEnvironment(array(
			'Kohana::$environment' => Kohana::DEVELOPMENT,
			'Kohana_Exception::$show_details' => NULL,
		));
		$this->assertTrue(Kohana_Exception::show_details());

		$this->setEnvironment(array('Kohana_Exception::$show_details' => FALSE));
		$this->assertFalse(Kohana_Exception::show_details());
	}
}
