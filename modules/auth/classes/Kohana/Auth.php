<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * User authorization library. Handles user login and logout, as well as secure
 * password hashing.
 *
 * @package    Kohana/Auth
 * @author     Kohana Team
 * @copyright  (c) 2007-2012 Kohana Team
 * @license    http://kohanaframework.org/license
 */
abstract class Kohana_Auth {

	// Auth instances
	protected static $_instance;

	/**
	 * Singleton pattern
	 *
	 * @return Auth
	 */
	public static function instance()
	{
		if ( ! isset(Auth::$_instance))
		{
			// Load the configuration for this type
			$config = Kohana::$config->load('auth');

			if ( ! $type = $config->get('driver'))
			{
				$type = 'file';
			}

			// Set the session class name
			$class = 'Auth_'.ucfirst($type);

			// Create a new session instance
			Auth::$_instance = new $class($config);
		}

		return Auth::$_instance;
	}

	protected $_session;

	protected $_config;

	/**
	 * Loads Session and configuration options.
	 *
	 * @param   array  $config  Config Options
	 * @return  void
	 */
	public function __construct($config = array())
	{
		// Save the config in the object
		$this->_config = $config;

		$this->_session = Session::instance($this->_config['session_type']);
	}

	abstract protected function _login($username, $password, $remember);

	abstract public function password($username);

	abstract public function check_password($password);

	/**
	 * Gets the currently logged in user from the session.
	 * Returns NULL if no user is currently logged in.
	 *
	 * @param   mixed  $default  Default value to return if the user is currently not logged in.
	 * @return  mixed
	 */
	public function get_user($default = NULL)
	{
		return $this->_session->get($this->_config['session_key'], $default);
	}

	/**
	 * Attempt to log in a user by using an ORM object and plain-text password.
	 *
	 * @param   string   $username  Username to log in
	 * @param   string   $password  Password to check against
	 * @param   boolean  $remember  Enable autologin
	 * @return  boolean
	 */
	public function login($username, $password, $remember = FALSE)
	{
		if (empty($password))
			return FALSE;

		return $this->_login($username, $password, $remember);
	}

	/**
	 * Log out a user by removing the related session variables.
	 *
	 * @param   boolean  $destroy     Completely destroy the session
	 * @param   boolean  $logout_all  Remove all tokens for user
	 * @return  boolean
	 */
	public function logout($destroy = FALSE, $logout_all = FALSE)
	{
		if ($destroy === TRUE)
		{
			// Destroy the session completely
			$this->_session->destroy();
		}
		else
		{
			// Remove the user from the session
			$this->_session->delete($this->_config['session_key']);

			// Regenerate session_id
			$this->_session->regenerate();
		}

		// Double check
		return ! $this->logged_in();
	}

	/**
	 * Check if there is an active session. Optionally allows checking for a
	 * specific role.
	 *
	 * @param   string  $role  role name
	 * @return  mixed
	 */
	public function logged_in($role = NULL)
	{
		return ($this->get_user() !== NULL);
	}

	/**
	 * Creates a password hash. This method is deprecated, [Auth::hash]
	 * should be used instead.
	 *
	 * @deprecated
	 * @param  string  $password Plaintext password
	 */
	public function hash_password($password)
	{
		return $this->hash($password);
	}

	/**
	 * Hashes a password with [password_hash] and the 'password_algo' and
	 * 'password_options' config values. With 'password_algo' => FALSE,
	 * it gives the legacy HMAC hash of [Auth::legacy_hash].
	 *
	 * [!!] The hash has a random salt. Use [Auth::verify] to compare.
	 *
	 * @param   string  $str  password to hash
	 * @return  string
	 */
	public function hash($str)
	{
		$algo = $this->_password_algo();

		if ($algo === FALSE)
			return $this->legacy_hash($str);

		return password_hash((string) $str, $algo, $this->_password_options());
	}

	/**
	 * Performs an HMAC hash with the 'hash_method' and 'hash_key' config
	 * values. Only for hashes from before [password_hash] was the default.
	 *
	 * @param   string  $str  string to hash
	 * @return  string
	 */
	public function legacy_hash($str)
	{
		if (empty($this->_config['hash_key']))
			throw new Kohana_Exception('A valid hash key must be set in your auth config.');

		return hash_hmac($this->_config['hash_method'], (string) $str, $this->_config['hash_key']);
	}

	/**
	 * Checks a password against a stored hash. Accepts [password_hash]
	 * hashes and, when 'hash_key' is set, legacy HMAC hashes.
	 *
	 * @param   string  $password  plaintext password
	 * @param   string  $hash      stored hash
	 * @return  boolean
	 */
	public function verify($password, $hash)
	{
		if ( ! is_string($password) OR ! is_string($hash) OR $hash === '')
			return FALSE;

		if (password_get_info($hash)['algoName'] !== 'unknown')
			return password_verify($password, $hash);

		if (empty($this->_config['hash_key']))
			return FALSE;

		return hash_equals($hash, $this->legacy_hash($password));
	}

	/**
	 * Checks if a stored hash must be replaced, because it is a legacy hash
	 * or the algorithm or options changed. Always FALSE with
	 * 'password_algo' => FALSE.
	 *
	 * @param   string  $hash  stored hash
	 * @return  boolean
	 */
	public function needs_rehash($hash)
	{
		$algo = $this->_password_algo();

		if ($algo === FALSE)
			return FALSE;

		return password_needs_rehash((string) $hash, $algo, $this->_password_options());
	}

	/**
	 * @return  mixed  algorithm for [password_hash], or FALSE for legacy HMAC
	 */
	protected function _password_algo()
	{
		return isset($this->_config['password_algo']) ? $this->_config['password_algo'] : PASSWORD_DEFAULT;
	}

	/**
	 * @return  array  options for [password_hash]
	 */
	protected function _password_options()
	{
		return isset($this->_config['password_options']) ? (array) $this->_config['password_options'] : array();
	}

	protected function complete_login($user)
	{
		// Regenerate session_id
		$this->_session->regenerate();

		// Store username in session
		$this->_session->set($this->_config['session_key'], $user);

		return TRUE;
	}

} // End Auth
