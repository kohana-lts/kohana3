<?php defined('SYSPATH') OR die('No direct script access.');

include_once __DIR__.'/DriverTestCase.php';

/**
 * Runs the driver tests on PDO with an in-memory SQLite database.
 *
 * @group kohana
 * @group kohana.database
 */
class Kohana_Database_PDOSQLiteTest extends Kohana_Database_DriverTestCase {

	protected function config()
	{
		if ( ! in_array('sqlite', PDO::getAvailableDrivers()))
			return NULL;

		return array(
			'type'         => 'PDO',
			'connection'   => array('dsn' => 'sqlite::memory:'),
			'table_prefix' => '',
			'charset'      => NULL,
			'caching'      => FALSE,
		);
	}

	protected function create_table_sql()
	{
		return 'CREATE TABLE IF NOT EXISTS kt_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255) NOT NULL, score INTEGER NOT NULL DEFAULT 0)';
	}
}
