<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Tests that Database_PDO sets the MySQL charset in the DSN and not with
 * SET NAMES. With SET NAMES, PDO::quote() does not know the charset.
 *
 * @group kohana
 * @group kohana.database
 *
 * @package    Kohana/Database
 * @category   Tests
 */
class Kohana_Database_PDOCharsetTest extends Unittest_TestCase {

	public function provider_dsn_with_charset()
	{
		return array(
			array('mysql:host=db;dbname=x', 'utf8mb4', 'mysql:host=db;dbname=x;charset=utf8mb4'),
			array('mysql:host=db;dbname=x;', 'utf8mb4', 'mysql:host=db;dbname=x;charset=utf8mb4'),
			array('MySQL:host=db', 'utf8mb4', 'MySQL:host=db;charset=utf8mb4'),
			array('mysql:host=db;charset=latin1', 'utf8mb4', 'mysql:host=db;charset=latin1'),
			array('mysql:host=db; charset=latin1', 'utf8mb4', 'mysql:host=db; charset=latin1'),
			array('mysql:host=db;CHARSET=latin1', 'utf8mb4', 'mysql:host=db;CHARSET=latin1;charset=utf8mb4'),
			array('mysql:host=db', NULL, 'mysql:host=db'),
			array('pgsql:host=db', 'utf8', 'pgsql:host=db'),
			array('sqlite::memory:', 'utf8', 'sqlite::memory:'),
		);
	}

	/**
	 * @dataProvider provider_dsn_with_charset
	 */
	public function test_dsn_with_charset($dsn, $charset, $expected)
	{
		$db = new Kohana_Database_PDOCharsetTest_DB('kt_charset', array(
			'connection' => array('dsn' => $dsn),
			'charset'    => $charset,
		));

		$this->assertSame($expected, $db->dsn_with_charset($dsn));
	}

	public function test_invalid_charset_throws()
	{
		$db = new Kohana_Database_PDOCharsetTest_DB('kt_charset', array(
			'connection' => array('dsn' => 'mysql:host=db'),
			'charset'    => 'utf8;host=evil',
		));

		$this->expectException(Database_Exception::class);

		$db->dsn_with_charset('mysql:host=db');
	}

	public function test_other_drivers_use_set_names()
	{
		$db = new Kohana_Database_PDOCharsetTest_DB('kt_charset', array(
			'connection' => array('dsn' => 'sqlite::memory:'),
			'charset'    => 'utf8',
		));

		$db->connect();

		$this->assertSame(array('utf8'), $db->set_charset_calls);
		$db->disconnect();
	}

	public function test_mysql_charset_comes_from_dsn()
	{
		if ( ! getenv('KOHANA_TEST_MYSQL_HOST') OR ! in_array('mysql', PDO::getAvailableDrivers()))
		{
			$this->markTestSkipped('No MySQL connection configured');
		}

		$db = new Kohana_Database_PDOCharsetTest_DB('kt_charset', array(
			'connection' => array(
				'dsn'      => 'mysql:host='.getenv('KOHANA_TEST_MYSQL_HOST').';port='.(getenv('KOHANA_TEST_MYSQL_PORT') ?: 3306).';dbname='.getenv('KOHANA_TEST_MYSQL_DATABASE'),
				'username' => getenv('KOHANA_TEST_MYSQL_USER'),
				'password' => getenv('KOHANA_TEST_MYSQL_PASSWORD'),
			),
			'charset'    => 'latin1',
		));

		$db->connect();

		$this->assertSame(array(), $db->set_charset_calls);
		$this->assertSame('latin1', $db->query(Database::SELECT, 'SELECT @@character_set_client AS cs')->get('cs'));
		$db->disconnect();
	}
}

/**
 * Records set_charset() calls.
 */
class Kohana_Database_PDOCharsetTest_DB extends Database_PDO {

	public $set_charset_calls = array();

	public function set_charset($charset)
	{
		$this->set_charset_calls[] = $charset;

		if (strpos($this->_connection->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite') === FALSE)
		{
			parent::set_charset($charset);
		}
	}

	public function dsn_with_charset($dsn)
	{
		return $this->_dsn_with_charset($dsn);
	}
}
