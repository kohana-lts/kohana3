<?php defined('SYSPATH') OR die('No direct script access.');

include_once __DIR__.'/DriverTestCase.php';

/**
 * Runs the driver tests on MySQL with the MySQL (MySQLi based) driver.
 * Set KOHANA_TEST_MYSQL_HOST, _USER, _PASSWORD and _DATABASE to run them.
 *
 * @group kohana
 * @group kohana.database
 * @group kohana.database.mysql
 */
class Kohana_Database_MySQLTest extends Kohana_Database_DriverTestCase {

	protected $type = 'MySQL';

	protected function config()
	{
		if ( ! getenv('KOHANA_TEST_MYSQL_HOST'))
			return NULL;

		$connection = array(
			'hostname' => getenv('KOHANA_TEST_MYSQL_HOST'),
			'port'     => (int) (getenv('KOHANA_TEST_MYSQL_PORT') ?: 3306),
			'username' => getenv('KOHANA_TEST_MYSQL_USER'),
			'password' => getenv('KOHANA_TEST_MYSQL_PASSWORD'),
			'database' => getenv('KOHANA_TEST_MYSQL_DATABASE'),
		);

		if ($this->type === 'PDO')
		{
			$connection = array(
				'dsn'      => 'mysql:host='.$connection['hostname'].';port='.$connection['port'].';dbname='.$connection['database'].';charset=utf8mb4',
				'username' => $connection['username'],
				'password' => $connection['password'],
			);
		}

		return array(
			'type'         => $this->type,
			'connection'   => $connection,
			'table_prefix' => '',
			'charset'      => 'utf8mb4',
			'caching'      => FALSE,
			'identifier'   => '`',
		);
	}

	protected function create_table_sql()
	{
		return 'CREATE TABLE IF NOT EXISTS kt_items (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, score INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
	}

	protected function reset_sql()
	{
		return array('DELETE FROM kt_items', 'ALTER TABLE kt_items AUTO_INCREMENT = 1');
	}
}
