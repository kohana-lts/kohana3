<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Cookie-based session class.
 *
 * @package    Kohana
 * @category   Session
 * @author     Kohana Team
 * @copyright  (c) 2008-2012 Kohana Team
 * @license    http://kohanaframework.org/license
 */
class Kohana_Session_Cookie extends Session {

	/**
	 * @var  mixed  the client keeps the data, so allow only the user model
	 *              that Auth_ORM stores. See [Session::$_allowed_classes].
	 */
	protected $_allowed_classes = array('Model_User');

	/**
	 * @var  integer  maximum age in seconds of the "last_active" value in the
	 *                signed data. NULL: the lifetime, or one day for a
	 *                lifetime of 0. 0 or FALSE: no limit.
	 */
	protected $_max_age = NULL;

	/**
	 * @param   array   $config  configuration, also "max_age"
	 * @param   string  $id      session id
	 * @return  void
	 */
	public function __construct(?array $config = NULL, $id = NULL)
	{
		if (isset($config['max_age']))
		{
			$this->_max_age = (int) $config['max_age'];
		}

		parent::__construct($config, $id);
	}

	/**
	 * Loads the session data and drops it when "last_active" is older than
	 * the maximum age, so a copied cookie stops working.
	 *
	 * @param   string  $id  session id
	 * @return  void
	 */
	public function read($id = NULL)
	{
		parent::read($id);

		if ($this->_max_age === NULL)
		{
			$max_age = $this->_lifetime ? $this->_lifetime : Date::DAY;
		}
		else
		{
			$max_age = $this->_max_age;
		}

		if ($this->_data AND $max_age > 0)
		{
			$last_active = isset($this->_data['last_active']) ? $this->_data['last_active'] : NULL;

			if ( ! is_int($last_active) OR $last_active < time() - $max_age)
			{
				$this->_data = array();
			}
		}
	}

	/**
	 * @param   string  $id  session id
	 * @return  string
	 */
	protected function _read($id = NULL)
	{
		return Cookie::get($this->_name, NULL);
	}

	/**
	 * @return  null
	 */
	protected function _regenerate()
	{
		// Cookie sessions have no id
		return NULL;
	}

	/**
	 * @return  bool
	 */
	protected function _write()
	{
		return Cookie::set($this->_name, $this->__toString(), $this->_lifetime);
	}

	/**
	 * @return  bool
	 */
	protected function _restart()
	{
		return TRUE;
	}

	/**
	 * @return  bool
	 */
	protected function _destroy()
	{
		return Cookie::delete($this->_name);
	}

}
