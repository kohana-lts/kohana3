<?php defined('SYSPATH') OR die('Kohana bootstrap needs to be included before tests run');

/**
 * Tests Kohana_Security
 *
 * @group kohana
 * @group kohana.core
 * @group kohana.core.security
 *
 * @package    Kohana
 * @category   Tests
 */
class Kohana_SecurityTest extends Unittest_TestCase
{
	/**
	 * Provides test data for test_envode_php_tags()
	 *
	 * @return array Test data sets
	 */
	public function provider_encode_php_tags()
	{
		return array(
			array("&lt;?php echo 'helloo'; ?&gt;", "<?php echo 'helloo'; ?>"),
		);
	}

	/**
	 * Tests Security::encode_php_tags()
	 *
	 * @test
	 * @dataProvider provider_encode_php_tags
	 * @covers Security::encode_php_tags
	 */
	public function test_encode_php_tags($expected, $input)
	{
		$this->assertSame($expected, Security::encode_php_tags($input));
	}

	/**
	 * Provides test data for test_strip_image_tags()
	 *
	 * @return array Test data sets
	 */
	public function provider_strip_image_tags()
	{
		return array(
			array('foo', '<img src="foo" />'),
			array('foo', "<IMG SRC='foo'>"),
			array('foo', '<img alt="x" src=foo>'),
			array('text  more', 'text <img data-src="x"> more'),
			array('a&amp;b', '<img src="a&amp;b">'),
			array('a&quot;b', '<img src=\'a"b\'>'),
		);
	}

	/**
	 * Tests Security::strip_image_tags()
	 *
	 * @test
	 * @dataProvider provider_strip_image_tags
	 * @covers Security::strip_image_tags
	 */
	public function test_strip_image_tags($expected, $input)
	{
		$this->assertSame($expected, Security::strip_image_tags($input));
	}

	/**
	 * Payloads from https://github.com/kohana/kohana/issues/107 and parser tricks.
	 *
	 * @return array
	 */
	public function provider_strip_image_tags_xss()
	{
		return array(
			array('<img/src=x onerror=prompt(document.domain)>'),
			array('<img/onerror=alert(1) src=x>'),
			array("<IMG\tSRC=x\tonerror=alert(1)>"),
			array("<img\nsrc=x\nonerror=alert(1)>"),
			array('<img src=x onerror=alert(1)//'),
			array('<img'),
			array('<image src=x onerror=alert(1)>'),
			array('<im<img>g src=x onerror=alert(1)>'),
			array('<<img src="img src=x onerror=alert(1)//">>'),
			array('<img alt=">" src=x onerror=alert(1)>'),
			array('<img src="x" onerror="alert(1)">'),
			array("<img src='x'onerror=alert(1)>"),
		);
	}

	/**
	 * @test
	 * @dataProvider provider_strip_image_tags_xss
	 * @covers Security::strip_image_tags
	 */
	public function test_strip_image_tags_removes_every_image_tag($input)
	{
		$output = Security::strip_image_tags($input);

		$this->assertIsString($output);
		$this->assertDoesNotMatchRegularExpression('/<\s*im(?:g|age)\b/i', $output);
		$this->assertDoesNotMatchRegularExpression('/<[a-z][^>]*\bonerror/i', $output);
		$this->assertSame($output, Security::strip_image_tags($output), 'The output is a fixpoint');
	}

	/**
	 * Provides non-string input for test_check_rejects_non_string_input()
	 *
	 * @return array
	 */
	public function provider_non_string_input()
	{
		return array(
			array(array('a')),
			array(array()),
			array(NULL),
			array(123),
			array(new stdClass),
		);
	}

	/**
	 * Array input from a form (token[]=x) must fail the check, not raise a TypeError.
	 *
	 * @test
	 * @dataProvider provider_non_string_input
	 * @covers Security::check
	 */
	public function test_check_rejects_non_string_input($input)
	{
		Security::token();
		$this->assertFalse(Security::check($input));
	}

	/**
	 * @test
	 * @dataProvider provider_non_string_input
	 * @covers Security::slow_equals
	 */
	public function test_slow_equals_rejects_non_string_input($input)
	{
		$this->assertFalse(Security::slow_equals('abc', $input));
		$this->assertFalse(Security::slow_equals($input, 'abc'));
	}

	/**
	 * @test
	 * @covers Security::slow_equals
	 */
	public function test_slow_equals_compares_strings()
	{
		$this->assertTrue(Security::slow_equals('abc', 'abc'));
		$this->assertTrue(Security::slow_equals('', ''));
		$this->assertFalse(Security::slow_equals('abc', 'abd'));
		$this->assertFalse(Security::slow_equals('abc', 'abcd'));
	}

	/**
	 * @test
	 * @covers Security::token
	 */
	public function test_token_is_base64_of_32_random_bytes()
	{
		$this->setEnvironment(array('Security::$token_name' => 'token_random'));
		$token = Security::token(TRUE);

		$this->assertSame(32, strlen(base64_decode($token, TRUE)));
		$this->assertNotSame($token, Security::token(TRUE));
		Session::instance()->delete(Security::$token_name);
	}

	/**
	 * Provides test data for Security::token()
	 *
	 * @return array Test data sets
	 */
	public function provider_csrf_token()
	{
		$array = array();
		for ($i = 0; $i <= 4; $i++)
		{
			Security::$token_name = 'token_'.$i;
			$array[] = array(Security::token(TRUE), Security::check(Security::token(FALSE)), $i);
		}
		return $array;
	}

	/**
	 * Tests Security::token()
	 *
	 * @test
	 * @dataProvider provider_csrf_token
	 * @covers Security::token
	 */
	public function test_csrf_token($expected, $input, $iteration)
	{
		//@todo: the Security::token tests need to be reviewed to check how much of the logic they're actually covering
		Security::$token_name = 'token_'.$iteration;
		$this->assertSame(TRUE, $input);
		$this->assertSame($expected, Security::token(FALSE));
		Session::instance()->delete(Security::$token_name);
	}
}
