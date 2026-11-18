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

	public function provider_valid_operators()
	{
		return array(
			array('=', 1, '"a" = 1'),
			array('==', 1, '"a" == 1'),
			array('!=', 1, '"a" != 1'),
			array('<>', 1, '"a" <> 1'),
			array('<', 1, '"a" < 1'),
			array('<=', 1, '"a" <= 1'),
			array('>', 1, '"a" > 1'),
			array('>=', 1, '"a" >= 1'),
			array('<=>', 1, '"a" <=> 1'),
			array('like', 'x%', '"a" LIKE \'x%\''),
			array('not  like', 'x%', '"a" NOT LIKE \'x%\''),
			array('ILIKE', 'x%', '"a" ILIKE \'x%\''),
			array('NOT ILIKE', 'x%', '"a" NOT ILIKE \'x%\''),
			array('in', array(1, 2), '"a" IN (1, 2)'),
			array('NOT IN', array(1, 2), '"a" NOT IN (1, 2)'),
			array('between', array(1, 2), '"a" BETWEEN 1 AND 2'),
			array('NOT BETWEEN', array(1, 2), '"a" NOT BETWEEN 1 AND 2'),
			array('=', NULL, '"a" IS NULL'),
			array('!=', NULL, '"a" IS NOT NULL'),
			array('is', NULL, '"a" IS NULL'),
			array('IS NOT', NULL, '"a" IS NOT NULL'),
			array('REGEXP', '^x', '"a" REGEXP \'^x\''),
			array('NOT REGEXP', '^x', '"a" NOT REGEXP \'^x\''),
			array('RLIKE', '^x', '"a" RLIKE \'^x\''),
			array('NOT RLIKE', '^x', '"a" NOT RLIKE \'^x\''),
			array('SOUNDS LIKE', 'x', '"a" SOUNDS LIKE \'x\''),
			array('GLOB', 'x*', '"a" GLOB \'x*\''),
			array('SIMILAR TO', 'x%', '"a" SIMILAR TO \'x%\''),
			array('IS DISTINCT FROM', 1, '"a" IS DISTINCT FROM 1'),
			array('~*', 'x', '"a" ~* \'x\''),
			array('&', 4, '"a" & 4'),
			array('= ANY', DB::expr('(SELECT 1)'), '"a" = ANY (SELECT 1)'),
			array("\tnot\nin ", array(1), '"a" NOT IN (1)'),
		);
	}

	/**
	 * @dataProvider provider_valid_operators
	 */
	public function test_where_accepts_valid_operator($op, $value, $expected)
	{
		$sql = DB::select()->from('t')->where('a', $op, $value)->compile($this->db);

		$this->assertSame('SELECT * FROM "t" WHERE '.$expected, $sql);
	}

	public function test_where_exists_without_column()
	{
		$sql = DB::select()->from('t')->where(NULL, 'EXISTS', DB::select('id')->from('u'))->compile($this->db);

		$this->assertSame('SELECT * FROM "t" WHERE EXISTS (SELECT "id" FROM "u")', $sql);
	}

	public function provider_invalid_operators()
	{
		return array(
			array('= 1 OR 1=1 --'),
			array('= 1) OR (1=1'),
			array('IN (SELECT password FROM users) OR 1 IN'),
			array(';'),
			array('--'),
			array(array('=')),
		);
	}

	/**
	 * @dataProvider provider_invalid_operators
	 */
	public function test_where_rejects_invalid_operator($op)
	{
		$this->expectException(Database_Exception::class);

		DB::select()->from('t')->where('id', $op, 1)->compile($this->db);
	}

	/**
	 * @dataProvider provider_invalid_operators
	 */
	public function test_having_rejects_invalid_operator($op)
	{
		$this->expectException(Database_Exception::class);

		DB::select()->from('t')->group_by('id')->having('id', $op, 1)->compile($this->db);
	}

	public function test_update_and_delete_reject_invalid_operator()
	{
		try
		{
			DB::update('t')->set(array('a' => 1))->where('id', '= 1 OR 1=1 --', 1)->compile($this->db);
			$this->fail('UPDATE accepted an invalid operator');
		}
		catch (Database_Exception $e) {}

		$this->expectException(Database_Exception::class);

		DB::delete('t')->where('id', '= 1 OR 1=1 --', 1)->compile($this->db);
	}

	public function test_expression_operator_is_raw_sql()
	{
		$sql = DB::select()->from('t')->where('a', DB::expr('@>'), DB::expr("'{1}'"))->compile($this->db);

		$this->assertSame('SELECT * FROM "t" WHERE "a" @> \'{1}\'', $sql);
	}

	public function test_applications_can_add_operators()
	{
		$operators = Database_Query_Builder::$operators;

		try
		{
			Database_Query_Builder::$operators[] = '#>>';

			$sql = DB::select()->from('t')->where('a', '#>>', 'x')->compile($this->db);
			$this->assertSame('SELECT * FROM "t" WHERE "a" #>> \'x\'', $sql);
		}
		finally
		{
			Database_Query_Builder::$operators = $operators;
		}

		$this->expectException(Database_Exception::class);

		DB::select()->from('t')->where('a', '#>>', 'x')->compile($this->db);
	}

	public function provider_valid_join_types()
	{
		return array(
			array(NULL, 'JOIN'),
			array('inner', 'INNER JOIN'),
			array('LEFT', 'LEFT JOIN'),
			array('left  outer', 'LEFT OUTER JOIN'),
			array('RIGHT OUTER', 'RIGHT OUTER JOIN'),
			array('FULL', 'FULL JOIN'),
			array('CROSS', 'CROSS JOIN'),
			array('NATURAL', 'NATURAL JOIN'),
			array('NATURAL LEFT OUTER', 'NATURAL LEFT OUTER JOIN'),
		);
	}

	/**
	 * @dataProvider provider_valid_join_types
	 */
	public function test_join_accepts_valid_type($type, $expected)
	{
		$sql = DB::select()->from('t')->join('u', $type)->on('t.id', '=', 'u.t_id')->compile($this->db);

		$this->assertSame('SELECT * FROM "t" '.$expected.' "u" ON ("t"."id" = "u"."t_id")', $sql);
	}

	public function provider_invalid_join_types()
	{
		return array(
			array('LEFT JOIN u2 ON 1=1 LEFT'),
			array('; DROP TABLE t; --'),
			array('OUTER'),
			array('STRAIGHT'),
		);
	}

	/**
	 * @dataProvider provider_invalid_join_types
	 */
	public function test_join_rejects_invalid_type($type)
	{
		$this->expectException(Database_Exception::class);

		DB::select()->from('t')->join('u', $type)->on('t.id', '=', 'u.t_id')->compile($this->db);
	}

	public function test_join_on_rejects_invalid_operator()
	{
		$this->expectException(Database_Exception::class);

		DB::select()->from('t')->join('u')->on('t.id', '= 1 OR 1=1 OR u.id =', 'u.t_id')->compile($this->db);
	}

	public function test_join_on_accepts_valid_operator()
	{
		$sql = DB::select()->from('t')->join('u', 'LEFT')->on('t.id', '<>', 'u.t_id')->compile($this->db);

		$this->assertSame('SELECT * FROM "t" LEFT JOIN "u" ON ("t"."id" <> "u"."t_id")', $sql);
	}

	public function provider_pdo_identifier()
	{
		return array(
			// array(dsn, config identifier, expected quoted column)
			array('mysql:host=localhost;dbname=x', NULL, '`a`.`b``c`'),
			array('pgsql:host=localhost;dbname=x', NULL, '"a"."b`c"'),
			array('sqlite::memory:', NULL, '"a"."b`c"'),
			array('sqlsrv:Server=localhost', NULL, '"a"."b`c"'),
			array('odbc:kt', NULL, 'a.b`c'),
			array('mysql:host=localhost;dbname=x', '', 'a.b`c'),
			array('mysql:host=localhost;dbname=x', '"', '"a"."b`c"'),
		);
	}

	/**
	 * Without the 'identifier' config, the PDO driver name gives the quote.
	 *
	 * @dataProvider provider_pdo_identifier
	 */
	public function test_pdo_identifier_from_driver($dsn, $identifier, $expected)
	{
		$config = array(
			'connection'   => array('dsn' => $dsn),
			'table_prefix' => '',
		);

		if ($identifier !== NULL)
		{
			$config['identifier'] = $identifier;
		}

		$db = new Database_PDO('kt_identifier', $config);

		$this->assertSame($expected, $db->quote_column('a.b`c'));
	}

	public function test_pdo_sqlite_quotes_column_names()
	{
		$db = new Database_PDO('kt_identifier', array(
			'connection'   => array('dsn' => 'sqlite::memory:'),
			'table_prefix' => '',
		));

		$db->query(NULL, 'CREATE TABLE t (id INTEGER)');
		$db->query(NULL, 'INSERT INTO t VALUES (1)');

		$rows = DB::select()->from('t')->where('id"=1 OR 1=1 OR "id', '=', 2)->execute($db);

		$this->assertSame('SELECT * FROM "t" WHERE "id""=1 OR 1=1 OR ""id" = 2', $db->last_query);
		$this->assertCount(0, $rows);
		$this->assertCount(1, DB::select()->from('t')->execute($db));
		$db->disconnect();
	}
}
