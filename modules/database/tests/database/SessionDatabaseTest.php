<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Tests Session_Database ids on an in-memory SQLite database.
 *
 * @group kohana
 * @group kohana.database
 *
 * @package    Kohana/Database
 * @category   Tests
 */
class Kohana_Database_SessionDatabaseTest extends Unittest_TestCase {

	protected function setUp(): void
	{
		parent::setUp();

		if ( ! in_array('sqlite', PDO::getAvailableDrivers()))
		{
			$this->markTestSkipped('pdo_sqlite is not available');
		}

		if ( ! isset(Database::$instances['kt_session']))
		{
			Database::instance('kt_session', array(
				'type'         => 'PDO',
				'connection'   => array('dsn' => 'sqlite::memory:'),
				'table_prefix' => '',
				'charset'      => NULL,
				'caching'      => FALSE,
			))->query(NULL, 'CREATE TABLE IF NOT EXISTS sessions (session_id VARCHAR(24) NOT NULL PRIMARY KEY, last_active INTEGER NOT NULL, contents TEXT NOT NULL)');
		}
	}

	protected function session()
	{
		return new Session_Database(array('group' => 'kt_session', 'name' => 'kt_session_id'));
	}

	/**
	 * The id must come from a CSPRNG (uniqid() is a timestamp) and must fit
	 * the VARCHAR(24) column of the sample schema.
	 */
	public function test_ids_are_random_hex_that_fit_the_column()
	{
		$ids = array();

		for ($i = 0; $i < 20; $i++)
		{
			$session = $this->session();
			$ids[] = $session->id();
			$ids[] = $session->regenerate();
		}

		foreach ($ids as $id)
		{
			$this->assertMatchesRegularExpression('/^[0-9a-f]{24}$/D', $id);
		}

		$this->assertCount(count($ids), array_unique($ids));

		// uniqid() ids share their first characters (the time)
		$this->assertGreaterThan(1, count(array_unique(array_map(function ($id) { return substr($id, 0, 8); }, $ids))));
	}
}
