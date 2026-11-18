<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Tests that the query builder does not put raw arguments into SQL.
 * The queries are only compiled, on a PDO SQLite connection.
 *
 * @group kohana
 * @group kohana.database
 *
 * @package    Kohana/Database
 * @category   Tests
 */
class Kohana_Database_QueryBuilderTest extends Unittest_TestCase {

	/**
	 * @var Database
	 */
	protected $db;

	protected function setUp(): void
	{
		parent::setUp();

		if ( ! in_array('sqlite', PDO::getAvailableDrivers()))
		{
			$this->markTestSkipped('pdo_sqlite is not available');
		}

		$this->db = new Database_PDO('kt_builder', array(
			'connection'   => array('dsn' => 'sqlite::memory:'),
			'table_prefix' => '',
			'charset'      => NULL,
			'caching'      => FALSE,
			'identifier'   => '"',
		));
	}

	protected function tearDown(): void
	{
		$this->db->disconnect();

		parent::tearDown();
	}

	public function provider_order_by_direction()
	{
		return array(
			array(NULL, 'ORDER BY "id"'),
			array('', 'ORDER BY "id"'),
			array('asc', 'ORDER BY "id" ASC'),
			array('DESC', 'ORDER BY "id" DESC'),
			array('Desc', 'ORDER BY "id" DESC'),
			array(' desc ', 'ORDER BY "id" DESC'),
			array(DB::expr('DESC NULLS LAST'), 'ORDER BY "id" DESC NULLS LAST'),
		);
	}

	/**
	 * @dataProvider provider_order_by_direction
	 */
	public function test_order_by_accepts_valid_direction($direction, $expected)
	{
		$sql = DB::select()->from('t')->order_by('id', $direction)->compile($this->db);

		$this->assertStringEndsWith($expected, $sql);
	}

	public function provider_order_by_invalid_direction()
	{
		return array(
			array('desc,(select 1)'),
			array('ASC; DROP TABLE t'),
			array('DESC LIMIT 1'),
			array('ASCENDING'),
			array(array('DESC')),
		);
	}

	/**
	 * CVE-2019-8979: the direction went into the SQL unchanged.
	 *
	 * @dataProvider provider_order_by_invalid_direction
	 */
	public function test_order_by_rejects_invalid_direction($direction)
	{
		$this->expectException(Database_Exception::class);

		DB::select()->from('t')->order_by('id', $direction)->compile($this->db);
	}

	public function test_order_by_direction_is_checked_in_update_and_delete()
	{
		try
		{
			DB::update('t')->set(array('a' => 1))->order_by('id', 'desc,(select 1)')->compile($this->db);
			$this->fail('UPDATE accepted an invalid direction');
		}
		catch (Database_Exception $e) {}

		$this->expectException(Database_Exception::class);

		DB::delete('t')->order_by('id', 'desc,(select 1)')->compile($this->db);
	}
}
