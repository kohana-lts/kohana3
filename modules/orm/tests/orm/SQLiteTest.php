<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * ORM tests on PDO with an in-memory SQLite database.
 *
 * @group kohana
 * @group kohana.orm
 *
 * @package    Kohana/ORM
 * @category   Tests
 */
class Kohana_ORM_SQLiteTest extends Unittest_TestCase {

	/**
	 * @var Database one connection for the class, because ORM caches it
	 */
	protected $db;

	protected function setUp(): void
	{
		parent::setUp();

		if ( ! in_array('sqlite', PDO::getAvailableDrivers()))
		{
			$this->markTestSkipped('pdo_sqlite is not available');
		}

		if ( ! isset(Database::$instances['kt_orm']))
		{
			Database::instance('kt_orm', array(
				'type'         => 'PDO',
				'connection'   => array('dsn' => 'sqlite::memory:'),
				'table_prefix' => '',
				'charset'      => NULL,
				'caching'      => FALSE,
			));
		}

		$this->db = Database::instance('kt_orm');

		$this->db->query(NULL, 'DROP TABLE IF EXISTS kt_items');
		$this->db->query(NULL, 'DROP TABLE IF EXISTS kt_notes');
		$this->db->query(NULL, 'CREATE TABLE kt_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255) NOT NULL, score INTEGER NOT NULL DEFAULT 0)');
		$this->db->query(NULL, 'CREATE TABLE kt_notes (id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER NOT NULL, body TEXT NOT NULL)');
	}

	public function test_create_find_update_delete()
	{
		$item = ORM::factory('Kt_Item');
		$item->name = 'first';
		$item->score = 3;
		$item->save();

		$this->assertTrue($item->loaded());
		$this->assertEquals(1, $item->pk());

		$found = ORM::factory('Kt_Item', $item->pk());
		$this->assertTrue($found->loaded());
		$this->assertSame('first', $found->name);

		$found->score = 7;
		$this->assertSame(array('score' => 'score'), $found->changed());
		$found->save();

		$this->assertEquals(7, ORM::factory('Kt_Item', 1)->score);

		$found->delete();
		$this->assertFalse(ORM::factory('Kt_Item', 1)->loaded());
	}

	public function test_find_all_and_count_all()
	{
		foreach (array('a', 'b', 'c') as $i => $name)
		{
			ORM::factory('Kt_Item')->values(array('name' => $name, 'score' => $i))->save();
		}

		$this->assertSame(3, ORM::factory('Kt_Item')->count_all());

		$names = array();
		foreach (ORM::factory('Kt_Item')->where('score', '>', 0)->order_by('name', 'DESC')->find_all() as $item)
		{
			$names[] = $item->name;
		}

		$this->assertSame(array('c', 'b'), $names);
		$this->assertSame(array('a', 'b', 'c'), array_values(ORM::factory('Kt_Item')->find_all()->as_array('id', 'name')));
	}

	public function test_order_by_rejects_invalid_direction()
	{
		$this->expectException(Database_Exception::class);

		ORM::factory('Kt_Item')->order_by('name', 'desc,(SELECT 1)')->find_all();
	}

	public function test_values_only_sets_expected_columns()
	{
		$item = ORM::factory('Kt_Item')->values(array('name' => 'x', 'score' => 5, 'id' => 99), array('name'));

		$this->assertSame('x', $item->name);
		$this->assertEquals(0, $item->score);
		$this->assertNull($item->id);
	}

	public function test_validation_failure_throws()
	{
		try
		{
			ORM::factory('Kt_Item')->values(array('name' => ''))->save();
			$this->fail('ORM_Validation_Exception was not thrown');
		}
		catch (ORM_Validation_Exception $e)
		{
			$this->assertArrayHasKey('name', $e->errors());
		}
	}

	public function test_has_many_and_belongs_to()
	{
		$item = ORM::factory('Kt_Item')->values(array('name' => 'parent', 'score' => 1))->save();

		foreach (array('one', 'two') as $body)
		{
			ORM::factory('Kt_Note')->values(array('item_id' => $item->pk(), 'body' => $body))->save();
		}

		$this->assertSame(2, $item->notes->count_all());
		$this->assertSame('parent', ORM::factory('Kt_Note', 1)->item->name);
	}

	public function test_serialize_round_trip()
	{
		$item = ORM::factory('Kt_Item')->values(array('name' => 'cached', 'score' => 2))->save();

		$copy = unserialize(serialize($item));

		$this->assertInstanceOf('Model_Kt_Item', $copy);
		$this->assertTrue($copy->loaded());
		$this->assertSame('cached', $copy->name);
		$this->assertEquals($item->pk(), $copy->pk());
	}
}

class Model_Kt_Item extends ORM {

	protected $_db_group = 'kt_orm';
	protected $_table_name = 'kt_items';
	protected $_table_columns = array(
		'id'    => array('type' => 'int'),
		'name'  => array('type' => 'string'),
		'score' => array('type' => 'int'),
	);
	protected $_has_many = array(
		'notes' => array('model' => 'Kt_Note', 'foreign_key' => 'item_id'),
	);

	public function rules()
	{
		return array(
			'name' => array(array('not_empty')),
		);
	}
}

class Model_Kt_Note extends ORM {

	protected $_db_group = 'kt_orm';
	protected $_table_name = 'kt_notes';
	protected $_table_columns = array(
		'id'      => array('type' => 'int'),
		'item_id' => array('type' => 'int'),
		'body'    => array('type' => 'string'),
	);
	protected $_belongs_to = array(
		'item' => array('model' => 'Kt_Item', 'foreign_key' => 'item_id'),
	);
}
