<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Shared tests for each database driver. Subclasses give the connection
 * config and the CREATE TABLE statement for their SQL dialect.
 *
 * @group kohana
 * @group kohana.database
 *
 * @package    Kohana/Database
 * @category   Tests
 */
abstract class Kohana_Database_DriverTestCase extends Unittest_TestCase {

	/**
	 * @var Database
	 */
	protected $db;

	/**
	 * @return array|NULL connection config, or NULL to skip the tests
	 */
	abstract protected function config();

	/**
	 * @return string CREATE TABLE statement for the kt_items table
	 */
	abstract protected function create_table_sql();

	protected function setUp(): void
	{
		parent::setUp();

		if ( ! $config = $this->config())
		{
			$this->markTestSkipped('No connection configured for '.get_class($this));
		}

		$name = 'test_'.get_class($this);
		unset(Database::$instances[$name]);

		$this->db = Database::instance($name, $config);
		$this->db->query(NULL, 'DROP TABLE IF EXISTS kt_items');
		$this->db->query(NULL, $this->create_table_sql());
	}

	protected function tearDown(): void
	{
		if ($this->db)
		{
			$this->db->query(NULL, 'DROP TABLE IF EXISTS kt_items');
			$this->db->disconnect();
			unset(Database::$instances[(string) $this->db]);
		}

		parent::tearDown();
	}

	protected function insert_items(array $names)
	{
		foreach ($names as $name)
		{
			DB::insert('kt_items', array('name', 'score'))
				->values(array($name, strlen($name)))
				->execute($this->db);
		}
	}

	public function test_insert_returns_id_and_rows()
	{
		list($id, $rows) = DB::insert('kt_items', array('name', 'score'))
			->values(array('one', 1))
			->execute($this->db);

		$this->assertEquals(1, $id);
		$this->assertSame(1, $rows);
	}

	public function test_select_as_array_and_object()
	{
		$this->insert_items(array('alpha', 'be', 'gam'));

		$rows = DB::select('name', 'score')
			->from('kt_items')
			->order_by('id')
			->execute($this->db)
			->as_array();

		$this->assertSame(array('alpha', 'be', 'gam'), array_column($rows, 'name'));

		$row = DB::select()
			->from('kt_items')
			->where('name', '=', 'be')
			->as_object()
			->execute($this->db)
			->current();

		$this->assertInstanceOf('stdClass', $row);
		$this->assertEquals(2, $row->score);
	}

	public function test_result_iteration_and_array_access()
	{
		$this->insert_items(array('a', 'b', 'c', 'd'));

		$result = DB::select('name')->from('kt_items')->order_by('id')->execute($this->db);

		$this->assertCount(4, $result);
		$this->assertSame('a', $result->get('name'));
		$this->assertTrue(isset($result[3]));
		$this->assertFalse(isset($result[4]));
		$this->assertSame('c', $result[2]['name']);
		$result->rewind();

		$names = array();
		foreach ($result as $key => $row)
		{
			$names[$key] = $row['name'];
		}

		$this->assertSame(array('a', 'b', 'c', 'd'), $names);

		$cached = $result->cached();
		$this->assertInstanceOf('Database_Result_Cached', $cached);
		$this->assertSame(array('a', 'b', 'c', 'd'), $cached->as_array(NULL, 'name'));
	}

	public function test_update_and_delete_return_affected_rows()
	{
		$this->insert_items(array('a', 'bb', 'ccc'));

		$updated = DB::update('kt_items')
			->set(array('score' => DB::expr('score + 10')))
			->where('score', '>', 1)
			->execute($this->db);

		$this->assertSame(2, (int) $updated);

		$deleted = DB::delete('kt_items')->where('name', 'IN', array('a', 'bb'))->execute($this->db);

		$this->assertSame(2, (int) $deleted);
		$this->assertEquals(13, DB::select('score')->from('kt_items')->execute($this->db)->get('score'));
	}

	/**
	 * Values must reach the database unchanged, whatever quote characters
	 * or backslashes they contain.
	 */
	public function test_quoted_values_round_trip()
	{
		$values = array(
			"O'Reilly",
			'double "quote"',
			'back\\slash',
			"\\'; DROP TABLE kt_items; --",
			"multi\nline\r\ntext",
			'percent % and _ underscore',
			'ünïcödé ✓',
		);

		$this->insert_items($values);

		foreach ($values as $value)
		{
			$found = DB::select('name')
				->from('kt_items')
				->where('name', '=', $value)
				->execute($this->db)
				->get('name');

			$this->assertSame($value, $found);
		}

		$this->assertCount(count($values), DB::select()->from('kt_items')->execute($this->db));
	}

	public function test_transaction_rollback()
	{
		$this->insert_items(array('keep'));

		$this->db->begin();
		$this->insert_items(array('discard'));
		$this->db->rollback();

		$this->assertCount(1, DB::select()->from('kt_items')->execute($this->db));
	}

	public function test_query_error_throws_database_exception()
	{
		$this->expectException(Database_Exception::class);

		DB::select()->from('kt_missing_table')->execute($this->db);
	}
}
