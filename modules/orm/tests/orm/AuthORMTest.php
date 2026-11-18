<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Tests Auth_ORM and the auth models on a real database. The tables use
 * the "kt_" prefix. Set the KOHANA_TEST_MYSQL_* variables to run on MySQL,
 * else the tests run on an in-memory SQLite database.
 *
 * @group kohana
 * @group kohana.orm
 * @group kohana.auth
 *
 * @package    Kohana/ORM
 * @category   Tests
 */
class Kohana_ORM_AuthORMTest extends Unittest_TestCase {

	/**
	 * @var Database the "default" instance, one for the process because ORM caches it
	 */
	protected static $db;

	/**
	 * @var Auth_Ktorm
	 */
	protected $auth;

	protected $user_agent;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		if (static::$db)
			return;

		if (getenv('KOHANA_TEST_MYSQL_HOST'))
		{
			$config = array(
				'type'       => 'MySQLi',
				'connection' => array(
					'hostname' => getenv('KOHANA_TEST_MYSQL_HOST'),
					'port'     => (int) (getenv('KOHANA_TEST_MYSQL_PORT') ?: 3306),
					'username' => getenv('KOHANA_TEST_MYSQL_USER'),
					'password' => getenv('KOHANA_TEST_MYSQL_PASSWORD'),
					'database' => getenv('KOHANA_TEST_MYSQL_DATABASE'),
				),
				'charset'    => 'utf8mb4',
			);
		}
		elseif (in_array('sqlite', PDO::getAvailableDrivers()))
		{
			$config = array(
				'type'       => 'Ktsqlite',
				'connection' => array('dsn' => 'sqlite::memory:'),
				'charset'    => NULL,
			);
		}
		else
		{
			return;
		}

		$config += array('table_prefix' => 'kt_', 'caching' => FALSE);

		unset(Database::$instances['default']);
		static::$db = Database::instance('default', $config);

		foreach (static::schema(static::$db instanceof Database_PDO) as $sql)
		{
			static::$db->query(NULL, $sql);
		}
	}

	/**
	 * @param   boolean  $sqlite
	 * @return  array    CREATE TABLE statements
	 */
	protected static function schema($sqlite)
	{
		$id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
		$engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

		return array(
			'CREATE TABLE IF NOT EXISTS kt_roles (id '.$id.', name VARCHAR(32) NOT NULL, description VARCHAR(255) NOT NULL)'.$engine,
			'CREATE TABLE IF NOT EXISTS kt_roles_users (user_id INT NOT NULL, role_id INT NOT NULL, PRIMARY KEY (user_id, role_id))'.$engine,
			'CREATE TABLE IF NOT EXISTS kt_users (id '.$id.', email VARCHAR(254) NOT NULL, username VARCHAR(32) NOT NULL DEFAULT \'\', password VARCHAR(255) NOT NULL, logins INT NOT NULL DEFAULT 0, last_login INT)'.$engine,
			'CREATE TABLE IF NOT EXISTS kt_user_tokens (id '.$id.', user_id INT NOT NULL, user_agent VARCHAR(40) NOT NULL, token VARCHAR(40) NOT NULL, created INT NOT NULL, expires INT NOT NULL)'.$engine,
		);
	}

	protected function setUp(): void
	{
		parent::setUp();

		if ( ! static::$db)
		{
			$this->markTestSkipped('No database for the auth tests');
		}

		Database::$instances['default'] = static::$db;

		foreach (array('kt_roles_users', 'kt_user_tokens', 'kt_users', 'kt_roles') as $table)
		{
			static::$db->query(NULL, 'DELETE FROM '.$table);
		}

		DB::insert('roles', array('id', 'name', 'description'))
			->values(array(1, 'login', 'Login'))
			->values(array(2, 'admin', 'Admin'))
			->execute(static::$db);

		$this->user_agent = Request::$user_agent;
		Request::$user_agent = 'kt-agent';

		$session = Session::instance('ktmemory');
		$data =& $session->as_array();
		$data = array();

		$this->auth = Auth_Ktorm::install($this->auth_config());
	}

	protected function tearDown(): void
	{
		Request::$user_agent = $this->user_agent;
		Auth_Ktorm::uninstall();

		parent::tearDown();
	}

	/**
	 * @return array Auth config for the tests
	 */
	protected function auth_config()
	{
		return array(
			'driver'       => 'ORM',
			'hash_method'  => 'sha256',
			'hash_key'     => 'kt-hash-key',
			'lifetime'     => 1209600,
			'session_type' => 'ktmemory',
			'session_key'  => 'auth_user',
		);
	}

	/**
	 * Inserts a user with the login role, without ORM filters.
	 *
	 * @return Model_User
	 */
	protected function insert_user($username, $stored_password)
	{
		list($id) = DB::insert('users', array('email', 'username', 'password', 'logins'))
			->values(array($username.'@example.com', $username, $stored_password, 0))
			->execute(static::$db);

		DB::insert('roles_users', array('user_id', 'role_id'))
			->values(array($id, 1))
			->execute(static::$db);

		return ORM::factory('User', $id);
	}

	/**
	 * update_user() without a list of columns must not set logins or the id.
	 */
	public function test_update_user_without_expected_sets_only_safe_columns()
	{
		$user = $this->insert_user('alice', 'x');

		$user->update_user(array(
			'id'       => 99,
			'username' => 'alice2',
			'email'    => 'alice2@example.com',
			'logins'   => 1000,
		));

		$row = DB::select()->from('users')->where('username', '=', 'alice2')->execute(static::$db)->current();

		$this->assertSame('alice2@example.com', $row['email']);
		$this->assertEquals(0, $row['logins']);
		$this->assertEquals($user->pk(), $row['id']);
	}

	public function test_create_user_without_expected_sets_only_safe_columns()
	{
		$user = ORM::factory('User')->create_user(array(
			'username'         => 'bob',
			'email'            => 'bob@example.com',
			'password'         => 'secret-password',
			'password_confirm' => 'secret-password',
			'logins'           => 1000,
		), NULL);

		$this->assertTrue($user->loaded());
		$this->assertEquals(0, ORM::factory('User', $user->pk())->logins);
	}
}

/**
 * Auth_ORM with an in-memory session, installed as the Auth singleton.
 */
class Auth_Ktorm extends Auth_ORM {

	/**
	 * @var array cookies that the driver set, name => value (NULL when deleted)
	 */
	public $cookies = array();

	public static function install(array $config)
	{
		return Auth::$_instance = new Auth_Ktorm($config);
	}

	public static function uninstall()
	{
		Auth::$_instance = NULL;
	}
}

/**
 * Session that keeps the data in memory only.
 */
class Session_Ktmemory extends Session {

	protected function _read($id = NULL)
	{
		return NULL;
	}

	protected function _regenerate()
	{
		return NULL;
	}

	protected function _write()
	{
		return TRUE;
	}

	protected function _destroy()
	{
		return TRUE;
	}

	protected function _restart()
	{
		return TRUE;
	}
}

/**
 * PDO SQLite driver with list_columns(), which ORM needs for models
 * without $_table_columns.
 */
class Database_Ktsqlite extends Database_PDO {

	public function list_columns($table, $like = NULL, $add_prefix = TRUE)
	{
		if ($add_prefix === TRUE)
		{
			$table = $this->table_prefix().$table;
		}

		$columns = array();

		foreach ($this->query(Database::SELECT, 'PRAGMA table_info('.$this->quote_identifier($table).')') as $row)
		{
			$columns[$row['name']] = array(
				'column_name' => $row['name'],
				'data_type'   => strtolower($row['type']),
				'is_nullable' => ! $row['notnull'],
				'type'        => (stripos($row['type'], 'INT') === FALSE) ? 'string' : 'int',
			);
		}

		return $columns;
	}
}
