<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Tests password hashing in Auth and Auth_File.
 *
 * @group kohana
 * @group kohana.auth
 *
 * @package    Kohana/Auth
 * @category   Tests
 */
class Kohana_Auth_FileTest extends Unittest_TestCase {

	const HASH_KEY = 'kt-hash-key';

	protected function auth(array $config = array())
	{
		$session = Session::instance('ktauth');
		$data =& $session->as_array();
		$data = array();

		return new Auth_File($config + array(
			'driver'       => 'File',
			'hash_method'  => 'sha256',
			'hash_key'     => Kohana_Auth_FileTest::HASH_KEY,
			'lifetime'     => 1209600,
			'session_type' => 'ktauth',
			'session_key'  => 'auth_user',
			'users'        => array(
				'modern' => password_hash('modern-pw', PASSWORD_DEFAULT),
				'legacy' => hash_hmac('sha256', 'legacy-pw', Kohana_Auth_FileTest::HASH_KEY),
			),
		));
	}

	public function test_hash_uses_password_hash()
	{
		$hash = $this->auth()->hash('secret');

		$this->assertNotSame('unknown', password_get_info($hash)['algoName']);
		$this->assertTrue(password_verify('secret', $hash));
		$this->assertNotSame($hash, $this->auth()->hash('secret'));
		$this->assertTrue(password_verify('secret', $this->auth()->hash_password('secret')));
	}

	public function test_hash_works_without_hash_key()
	{
		$hash = $this->auth(array('hash_key' => NULL))->hash('secret');

		$this->assertTrue(password_verify('secret', $hash));
	}

	public function test_hash_algo_and_options_from_config()
	{
		$hash = $this->auth(array('password_algo' => PASSWORD_BCRYPT, 'password_options' => array('cost' => 5)))->hash('secret');

		$this->assertStringStartsWith('$2y$05$', $hash);
	}

	public function test_legacy_hash_opt_out()
	{
		$auth = $this->auth(array('password_algo' => FALSE));

		$this->assertSame(hash_hmac('sha256', 'secret', Kohana_Auth_FileTest::HASH_KEY), $auth->hash('secret'));
		$this->assertTrue($auth->login('legacy', 'legacy-pw'));
		$this->assertFalse($auth->needs_rehash(hash_hmac('sha256', 'x', Kohana_Auth_FileTest::HASH_KEY)));
	}

	public function provider_verify()
	{
		$legacy = hash_hmac('sha256', 'pw', Kohana_Auth_FileTest::HASH_KEY);

		return array(
			array('pw', password_hash('pw', PASSWORD_DEFAULT), TRUE),
			array('px', password_hash('pw', PASSWORD_DEFAULT), FALSE),
			array('pw', $legacy, TRUE),
			array('px', $legacy, FALSE),
			array('pw', strtoupper($legacy), FALSE),
			array('pw', '', FALSE),
			array('pw', NULL, FALSE),
			array(array('pw'), $legacy, FALSE),
		);
	}

	/**
	 * @dataProvider provider_verify
	 */
	public function test_verify($password, $hash, $expected)
	{
		$this->assertSame($expected, $this->auth()->verify($password, $hash));
	}

	public function test_verify_legacy_hash_needs_hash_key()
	{
		$legacy = hash_hmac('sha256', 'pw', Kohana_Auth_FileTest::HASH_KEY);

		$this->assertFalse($this->auth(array('hash_key' => NULL))->verify('pw', $legacy));
	}

	public function test_needs_rehash()
	{
		$auth = $this->auth();

		$this->assertTrue($auth->needs_rehash(hash_hmac('sha256', 'pw', Kohana_Auth_FileTest::HASH_KEY)));
		$this->assertFalse($auth->needs_rehash(password_hash('pw', PASSWORD_DEFAULT)));
	}

	public function test_login_with_password_hash()
	{
		$auth = $this->auth();

		$this->assertFalse($auth->login('modern', 'wrong'));
		$this->assertFalse($auth->logged_in());
		$this->assertTrue($auth->login('modern', 'modern-pw'));
		$this->assertSame('modern', $auth->get_user());
		$this->assertTrue($auth->check_password('modern-pw'));
		$this->assertFalse($auth->check_password('wrong'));
	}

	public function test_login_with_legacy_hash()
	{
		$auth = $this->auth();

		$this->assertFalse($auth->login('legacy', 'wrong'));
		$this->assertTrue($auth->login('legacy', 'legacy-pw'));
		$this->assertTrue($auth->check_password('legacy-pw'));
	}

	public function test_login_unknown_user()
	{
		$this->assertFalse($this->auth()->login('nobody', 'modern-pw'));
	}
}

/**
 * Session that keeps the data in memory only.
 */
class Session_Ktauth extends Session {

	protected function _read($id = NULL)
	{
		return NULL;
	}

	protected function _regenerate()
	{
		return NULL;
	}

	protected function _write()
	{
		return TRUE;
	}

	protected function _destroy()
	{
		return TRUE;
	}

	protected function _restart()
	{
		return TRUE;
	}
}
