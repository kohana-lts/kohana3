<?php defined('SYSPATH') OR die('Kohana bootstrap needs to be included before tests run');

/**
 * Tests the cookie class
 *
 * @group kohana
 * @group kohana.core
 * @group kohana.core.cookie
 *
 * @package    Kohana
 * @category   Tests
 * @author     Kohana Team
 * @author     Jeremy Bush <contractfrombelow@gmail.com>
 * @author     Andrew Coulton <andrew@ingenerator.com>
 * @copyright  (c) 2008-2014 Kohana Team
 * @license    http://kohanaframework.org/license
 */
class Kohana_CookieTest extends Unittest_TestCase
{
	const UNIX_TIMESTAMP      = 1411040141;
	const COOKIE_EXPIRATION   = 60;

	/**
	 * Sets up the environment
	 */
	// @codingStandardsIgnoreStart
	public function setUp(): void
	// @codingStandardsIgnoreEnd
	{
		parent::setUp();
		Kohana_CookieTest_TestableCookie::$_mock_cookies_set = array();

		$this->setEnvironment(array(
			'Cookie::$salt'   => 'some-random-salt',
			'HTTP_USER_AGENT' => 'cli'
		));
	}

	/**
	 * Tests that cookies are set with the global path, domain, etc options.
	 *
	 * @covers Cookie::set
	 */
	public function test_set_creates_cookie_with_configured_cookie_options()
	{
		$this->setEnvironment(array(
			'Cookie::$path'     => '/path',
			'Cookie::$domain'   => 'my.domain',
			'Cookie::$secure'   => TRUE,
			'Cookie::$httponly' => FALSE,
		));

		Kohana_CookieTest_TestableCookie::set('cookie', 'value');

		$this->assertSetCookieWith(array(
			'path'       => '/path',
			'domain'     => 'my.domain',
			'secure'     => TRUE,
			'httponly'   => FALSE
		));
	}

	/**
	 * @covers Cookie::set
	 */
	public function test_set_sends_httponly_and_samesite_lax_by_default()
	{
		$this->assertTrue(Cookie::$httponly);
		$this->assertSame('Lax', Cookie::$samesite);

		Kohana_CookieTest_TestableCookie::set('cookie', 'value');

		$this->assertSetCookieWith(array(
			'httponly' => TRUE,
			'samesite' => 'Lax',
		));
	}

	/**
	 * @covers Cookie::set
	 */
	public function test_set_sends_configured_samesite()
	{
		$this->setEnvironment(array('Cookie::$samesite' => 'Strict'));

		Kohana_CookieTest_TestableCookie::set('cookie', 'value');

		$this->assertSetCookieWith(array('samesite' => 'Strict'));
	}

	/**
	 * @covers Cookie::set
	 */
	public function test_set_omits_samesite_when_null()
	{
		$this->setEnvironment(array('Cookie::$samesite' => NULL));

		Kohana_CookieTest_TestableCookie::set('cookie', 'value');

		$this->assertArrayNotHasKey('samesite', Kohana_CookieTest_TestableCookie::$_mock_cookies_set[0]);
	}

	/**
	 * setcookie() with an options array needs string values.
	 *
	 * @covers Cookie::delete
	 */
	public function test_delete_sends_string_value_and_domain()
	{
		$this->setEnvironment(array('Cookie::$domain' => NULL));

		Kohana_CookieTest_TestableCookie::delete('cookie');

		$this->assertSetCookieWith(array(
			'value'  => '',
			'domain' => '',
		));
		$this->assertSame('', Kohana_CookieTest_TestableCookie::$_mock_cookies_set[0]['value']);
		$this->assertSame('', Kohana_CookieTest_TestableCookie::$_mock_cookies_set[0]['domain']);
	}

	/**
	 * Session_Native must send the session cookie with the Cookie flags.
	 *
	 * @covers Session_Native::_cookie_params
	 */
	public function test_native_session_cookie_uses_cookie_flags()
	{
		$this->setEnvironment(array(
			'Cookie::$path'     => '/path',
			'Cookie::$domain'   => 'my.domain',
			'Cookie::$secure'   => TRUE,
			'Cookie::$httponly' => TRUE,
			'Cookie::$samesite' => 'Strict',
		));

		$session = new Kohana_CookieTest_TestableSessionNative;

		$this->assertSame(array(
			'lifetime' => 120,
			'path'     => '/path',
			'domain'   => 'my.domain',
			'secure'   => TRUE,
			'httponly' => TRUE,
			'samesite' => 'Strict',
		), $session->cookie_params());

		Cookie::$samesite = NULL;
		$this->assertArrayNotHasKey('samesite', $session->cookie_params());
	}

	/**
	 * Provider for test_set_calculates_expiry_from_lifetime
	 *
	 * @return array of $lifetime, $expect_expiry
	 */
	public function provider_set_calculates_expiry_from_lifetime()
	{
		return array(
			array(NULL, self::COOKIE_EXPIRATION + self::UNIX_TIMESTAMP),
			array(0,    0),
			array(10,   10 + self::UNIX_TIMESTAMP),
		);
	}

	/**
	 * @param int $expiration
	 * @param int $expect_expiry
	 *
	 * @dataProvider provider_set_calculates_expiry_from_lifetime
	 * @covers Cookie::set
	 */
	public function test_set_calculates_expiry_from_lifetime($expiration, $expect_expiry)
	{
		$this->setEnvironment(array('Cookie::$expiration' => self::COOKIE_EXPIRATION));
		Kohana_CookieTest_TestableCookie::set('foo', 'bar', $expiration);
		$this->assertSetCookieWith(array('expire' => $expect_expiry));
	}

	/**
	 * @covers Cookie::get
	 */
	public function test_get_returns_default_if_cookie_missing()
	{
		unset($_COOKIE['missing_cookie']);
		$this->assertEquals('default', Cookie::get('missing_cookie', 'default'));
	}

	/**
	 * @covers Cookie::get
	 */
	public function test_get_returns_value_if_cookie_present_and_signed()
	{
		Kohana_CookieTest_TestableCookie::set('cookie', 'value');
		$cookie = Kohana_CookieTest_TestableCookie::$_mock_cookies_set[0];
		$_COOKIE[$cookie['name']] = $cookie['value'];
		$this->assertEquals('value', Cookie::get('cookie', 'default'));
	}

	/**
	 * Provider for test_get_returns_default_without_deleting_if_cookie_unsigned
	 *
	 * @return array
	 */
	public function provider_get_returns_default_without_deleting_if_cookie_unsigned()
	{
		return array(
			array('unsalted'),
			array('un~salted'),
		);
	}

	/**
	 * Verifies that unsigned cookies are not available to the kohana application, but are not affected for other
	 * consumers.
	 *
	 * @param string $unsigned_value
	 *
	 * @dataProvider provider_get_returns_default_without_deleting_if_cookie_unsigned
	 * @covers Cookie::get
	 */
	public function test_get_returns_default_without_deleting_if_cookie_unsigned($unsigned_value)
	{
		$_COOKIE['cookie'] = $unsigned_value;
		$this->assertEquals('default', Kohana_CookieTest_TestableCookie::get('cookie', 'default'));
		$this->assertEquals($unsigned_value, $_COOKIE['cookie'], '$_COOKIE not affected');
		$this->assertEmpty(Kohana_CookieTest_TestableCookie::$_mock_cookies_set, 'No cookies set or changed');
	}

	/**
	 * A request such as "Cookie: cookie[40]=~" makes $_COOKIE['cookie'] an array.
	 *
	 * @covers Cookie::get
	 */
	public function test_get_returns_default_if_cookie_is_array()
	{
		$split = strlen(Cookie::salt('cookie', NULL));
		$_COOKIE['cookie'] = array($split => '~');
		$this->assertEquals('default', Kohana_CookieTest_TestableCookie::get('cookie', 'default'));
	}

	/**
	 * If a cookie looks like a signed cookie but the signature no longer matches, it should be deleted.
	 *
	 * @covers Cookie::get
	 */
	public function test_get_returns_default_and_deletes_tampered_signed_cookie()
	{
		$_COOKIE['cookie'] = Cookie::salt('cookie', 'value').'~tampered';
		$this->assertEquals('default', Kohana_CookieTest_TestableCookie::get('cookie', 'default'));
		$this->assertDeletedCookie('cookie');
	}

	/**
	 * @covers Cookie::delete
	 */
	public function test_delete_removes_cookie_from_globals_and_expires_cookie()
	{
		$_COOKIE['cookie'] = Cookie::salt('cookie', 'value').'~tampered';
		$this->assertTrue(Kohana_CookieTest_TestableCookie::delete('cookie'));
		$this->assertDeletedCookie('cookie');
	}

	/**
	 * @covers Cookie::delete
	 * @link    http://dev.kohanaframework.org/issues/3501
	 * @link    http://dev.kohanaframework.org/issues/3020
	 */
	public function test_delete_does_not_require_configured_salt()
	{
		Cookie::$salt = NULL;
		$this->assertTrue(Kohana_CookieTest_TestableCookie::delete('cookie'));
		$this->assertDeletedCookie('cookie');
	}

	/**
	 * @covers Cookie::salt
	 */
	public function test_salt_throws_with_no_configured_salt()
	{
		$this->expectException(Kohana_Exception::class);

		Cookie::$salt = NULL;
		Cookie::salt('key', 'value');
	}

	/**
	 * @covers Cookie::salt
	 */
	public function test_salt_creates_same_hash_for_same_values_and_state()
	{
		$name  = 'cookie';
		$value = 'value';
		$this->assertEquals(Cookie::salt($name, $value), Cookie::salt($name, $value));
	}

	/**
	 * Provider for test_salt_creates_different_hash_for_different_data
	 *
	 * @return array
	 */
	public function provider_salt_creates_different_hash_for_different_data()
	{
		return array(
			array(array('name' => 'foo', 'value' => 'bar', 'salt' => 'our-salt', 'user-agent' => 'Chrome'), array('name' => 'changed')),
			array(array('name' => 'foo', 'value' => 'bar', 'salt' => 'our-salt', 'user-agent' => 'Chrome'), array('value' => 'changed')),
			array(array('name' => 'foo', 'value' => 'bar', 'salt' => 'our-salt', 'user-agent' => 'Chrome'), array('salt' => 'changed-salt')),
			array(array('name' => 'foo', 'value' => 'bar', 'salt' => 'our-salt', 'user-agent' => 'Chrome'), array('user-agent' => 'Firefox')),
			array(array('name' => 'foo', 'value' => 'bar', 'salt' => 'our-salt', 'user-agent' => 'Chrome'), array('user-agent' => NULL)),
		);
	}

	/**
	 * @param array $first_args
	 * @param array $changed_args
	 *
	 * @dataProvider provider_salt_creates_different_hash_for_different_data
	 * @covers Cookie::salt
	 */
	public function test_salt_creates_different_hash_for_different_data($first_args, $changed_args)
	{
		$second_args = array_merge($first_args, $changed_args);
		$hashes = array();
		foreach (array($first_args, $second_args) as $args)
		{
			Cookie::$salt = $args['salt'];
			$this->set_or_remove_http_user_agent($args['user-agent']);

			$hashes[] = Cookie::salt($args['name'], $args['value']);
		}

		$this->assertNotEquals($hashes[0], $hashes[1]);
	}

	/**
	 * Verify that a cookie was deleted from the global $_COOKIE array, and that a setcookie call was made to remove it
	 * from the client.
	 *
	 * @param string $name
	 */
	// @codingStandardsIgnoreStart
	protected function assertDeletedCookie($name)
	// @codingStandardsIgnoreEnd
	{
		$this->assertArrayNotHasKey($name, $_COOKIE);
		// To delete the client-side cookie, Cookie::delete should send a new cookie with value NULL and expiry in the past
		$this->assertSetCookieWith(array(
			'name'     => $name,
			'value'    => '',
			'expire'   => -86400,
			'path'     => Cookie::$path,
			'domain'   => Cookie::$domain,
			'secure'   => Cookie::$secure,
			'httponly' => Cookie::$httponly
		));
	}

	/**
	 * Verify that there was a single call to setcookie including the provided named arguments
	 *
	 * @param array $expected
	 */
	// @codingStandardsIgnoreStart
	protected function assertSetCookieWith($expected)
	// @codingStandardsIgnoreEnd
	{
		$this->assertCount(1, Kohana_CookieTest_TestableCookie::$_mock_cookies_set);
		$relevant_values = array_intersect_key(Kohana_CookieTest_TestableCookie::$_mock_cookies_set[0], $expected);
		$this->assertEquals($expected, $relevant_values);
	}

	/**
	 * Configure the $_SERVER[HTTP_USER_AGENT] environment variable for the test
	 *
	 * @param string $user_agent
	 */
	protected function set_or_remove_http_user_agent($user_agent)
	{
		if ($user_agent === NULL)
		{
			unset($_SERVER['HTTP_USER_AGENT']);
		}
		else
		{
			$_SERVER['HTTP_USER_AGENT'] = $user_agent;
		}
	}
}

/**
 * Class Kohana_CookieTest_TestableCookie wraps the cookie class to mock out the actual setcookie and time calls for
 * unit testing.
 */
class Kohana_CookieTest_TestableCookie extends Cookie {

	/**
	 * @var array setcookie calls that were made
	 */
	public static $_mock_cookies_set = array();

	/**
	 * {@inheritdoc}
	 */
	protected static function _send_cookie($name, $value, array $options)
	{
		$cookie = array(
			'name'     => $name,
			'value'    => $value,
			'expire'   => $options['expires'],
			'path'     => $options['path'],
			'domain'   => $options['domain'],
			'secure'   => $options['secure'],
			'httponly' => $options['httponly'],
		);

		if (array_key_exists('samesite', $options))
		{
			$cookie['samesite'] = $options['samesite'];
		}

		self::$_mock_cookies_set[] = $cookie;

		return TRUE;
	}

	/**
	 * @return int
	 */
	protected static function _time()
	{
		return Kohana_CookieTest::UNIX_TIMESTAMP;
	}

}

/**
 * Exposes the session cookie parameters without a session start.
 */
class Kohana_CookieTest_TestableSessionNative extends Session_Native {

	public function __construct()
	{
		$this->_lifetime = 120;
	}

	public function cookie_params()
	{
		return $this->_cookie_params();
	}

}
