<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * MySQL database connection.
 *
 * PHP 7.0 removed ext/mysql, so this driver runs on MySQLi. It keeps the
 * "MySQL" type name for existing configurations. The "persistent" option
 * maps to the MySQLi "p:" host prefix.
 *
 * @package    Kohana/Database
 * @category   Drivers
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Kohana_Database_MySQL extends Database_MySQLi {

	public function connect()
	{
		if ($this->_connection)
			return;

		if ( ! empty($this->_config['connection']['persistent']))
		{
			$hostname = (string) Arr::get($this->_config['connection'], 'hostname', '');

			if (strncmp($hostname, 'p:', 2) !== 0)
			{
				$this->_config['connection']['hostname'] = 'p:'.$hostname;
			}
		}

		parent::connect();
	}

}
