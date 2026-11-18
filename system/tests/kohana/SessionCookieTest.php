<?php defined('SYSPATH') OR die('Kohana bootstrap needs to be included before tests run');

/**
 * Tests the limits on session data: the classes that unserialize() may
 * create, and the age of Session_Cookie data.
 *
 * @group kohana
 * @group kohana.core
 * @group kohana.core.session
 *
 * @package    Kohana
 * @category   Tests
 */
class Kohana_SessionCookieTest extends Unittest_TestCase {

	/**
	 * @param   array   $data    session data to put in the cookie
	 * @param   array   $config  session config
	 * @return  Session_Cookie   that reads $data in place of the cookie
	 */
	protected function cookie_session(array $data, array $config = array())
	{
		$raw = base64_encode(serialize($data));

		return new class($config, $raw) extends Session_Cookie {

			private $raw;

			public function __construct($config, $raw)
			{
				$this->raw = $raw;

				parent::__construct($config);
			}

			protected function _read($id = NULL)
			{
				return $this->raw;
			}
		};
	}

	public function test_cookie_data_does_not_create_objects()
	{
		$session = $this->cookie_session(array('last_active' => time(), 'x' => new ArrayObject(array(1))));

		$this->assertInstanceOf('__PHP_Incomplete_Class', $session->get('x'));
	}

	public function test_cookie_allowed_classes_from_config()
	{
		$session = $this->cookie_session(
			array('last_active' => time(), 'x' => new ArrayObject(array(1))),
			array('allowed_classes' => array('ArrayObject'))
		);

		$this->assertInstanceOf('ArrayObject', $session->get('x'));
	}

	/**
	 * Server-side drivers keep the old default, which allows all classes.
	 */
	public function test_base_session_allows_classes_by_default()
	{
		$session = $this->getMockForAbstractClass('Session', array(), '', FALSE);
		$session->expects($this->any())->method('_read')->willReturn(base64_encode(serialize(array('x' => new ArrayObject()))));
		$session->__construct(array());

		$this->assertInstanceOf('ArrayObject', $session->get('x'));
	}

	public function test_base_session_allowed_classes_from_config()
	{
		$session = $this->getMockForAbstractClass('Session', array(), '', FALSE);
		$session->expects($this->any())->method('_read')->willReturn(base64_encode(serialize(array('x' => new ArrayObject()))));
		$session->__construct(array('allowed_classes' => FALSE));

		$this->assertInstanceOf('__PHP_Incomplete_Class', $session->get('x'));
	}

	public function provider_cookie_age()
	{
		return array(
			// array(config, age of last_active or NULL for none, data kept)
			array(array('lifetime' => 3600), 60, TRUE),
			array(array('lifetime' => 3600), 7200, FALSE),
			array(array('lifetime' => 3600), NULL, FALSE),
			array(array(), 3600, TRUE),
			array(array(), Date::DAY + 60, FALSE),
			array(array('max_age' => 60), 120, FALSE),
			array(array('lifetime' => 3600, 'max_age' => 60), 120, FALSE),
			array(array('max_age' => 0), Date::YEAR, TRUE),
			array(array('max_age' => FALSE), Date::YEAR, TRUE),
		);
	}

	/**
	 * Old cookie data gives an empty session, so a copied cookie does not
	 * work for ever.
	 *
	 * @dataProvider provider_cookie_age
	 */
	public function test_cookie_age($config, $age, $kept)
	{
		$data = array('user' => 'u1');

		if ($age !== NULL)
		{
			$data['last_active'] = time() - $age;
		}

		$session = $this->cookie_session($data, $config);

		$this->assertSame($kept ? 'u1' : NULL, $session->get('user'));
	}

	public function test_empty_cookie_gives_empty_session()
	{
		$session = $this->cookie_session(array());

		$this->assertSame(array(), $session->as_array());
	}
}
