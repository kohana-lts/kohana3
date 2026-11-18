<?php defined('SYSPATH') OR die('No direct script access.');

include_once __DIR__.'/MySQLTest.php';

/**
 * @group kohana
 * @group kohana.database
 * @group kohana.database.mysql
 */
class Kohana_Database_PDOMySQLTest extends Kohana_Database_MySQLTest {

	protected $type = 'PDO';
}
