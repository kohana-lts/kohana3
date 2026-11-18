<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * File Auth driver.
 * [!!] this Auth driver does not support roles nor autologin.
 * [!!] this Auth driver cannot save a new hash for a legacy HMAC hash.
 *      Replace legacy hashes in the 'users' config with [Auth::hash] values.
 *
 * @package    Kohana/Auth
 * @author     Kohana Team
 * @copyright  (c) 2007-2012 Kohana Team
 * @license    http://kohanaframework.org/license
 */
class Kohana_Auth_File extends Auth {

	// User list
	protected $_users;

	/**
	 * Constructor loads the user list into the class.
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		// Load user list
		$this->_users = Arr::get($config, 'users', array());
	}

	/**
	 * Logs a user in.
	 *
	 * @param   string   $username  Username
	 * @param   string   $password  Password
	 * @param   boolean  $remember  Enable autologin (not supported)
	 * @return  boolean
	 */
	protected function _login($username, $password, $remember)
	{
		if (isset($this->_users[$username]) AND $this->verify($password, $this->_users[$username]))
		{
			// Complete the login
			return $this->complete_login($username);
		}

		// Login failed
		return FALSE;
	}

	/**
	 * Forces a user to be logged in, without specifying a password.
	 *
	 * @param   mixed    $username  Username
	 * @return  boolean
	 */
	public function force_login($username)
	{
		// Complete the login
		return $this->complete_login($username);
	}

	/**
	 * Get the stored password for a username.
	 *
	 * @param   mixed   $username  Username
	 * @return  string
	 */
	public function password($username)
	{
		return Arr::get($this->_users, $username, FALSE);
	}

	/**
	 * Compare a plain text password with the stored hash. Works for current (logged in) user
	 *
	 * @param   string   $password  Password
	 * @return  boolean
	 */
	public function check_password($password)
	{
		$username = $this->get_user();

		if ($username === NULL OR $username === FALSE)
		{
			return FALSE;
		}

		return $this->verify($password, $this->password($username));
	}

} // End Auth File
