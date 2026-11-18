<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * ORM Auth driver.
 *
 * @package    Kohana/Auth
 * @author     Kohana Team
 * @copyright  (c) 2007-2012 Kohana Team
 * @license    http://kohanaframework.org/license
 */
class Kohana_Auth_ORM extends Auth {

	/**
	 * Checks if a session is active.
	 *
	 * @param   mixed    $role Role name string, role ORM object, or array with role names
	 * @return  boolean
	 */
	public function logged_in($role = NULL)
	{
		// Get the user from the session
		$user = $this->get_user();

		if ( ! $user)
			return FALSE;

		if ($user instanceof Model_User AND $user->loaded())
		{
			// If we don't have a roll no further checking is needed
			if ( ! $role)
				return TRUE;

			if (is_array($role))
			{
				// Get all the roles
				$roles = ORM::factory('Role')
							->where('name', 'IN', $role)
							->find_all()
							->as_array(NULL, 'id');

				// Make sure all the roles are valid ones
				if (count($roles) !== count($role))
					return FALSE;
			}
			else
			{
				if ( ! is_object($role))
				{
					// Load the role
					$roles = ORM::factory('Role', array('name' => $role));

					if ( ! $roles->loaded())
						return FALSE;
				}
				else
				{
					$roles = $role;
				}
			}

			return $user->has('roles', $roles);
		}
	}

	/**
	 * Logs a user in.
	 *
	 * @param   string   $username
	 * @param   string   $password
	 * @param   boolean  $remember  enable autologin
	 * @return  boolean
	 */
	protected function _login($user, $password, $remember)
	{
		if ( ! is_object($user))
		{
			$username = $user;

			// Load the user
			$user = ORM::factory('User');
			$user->where($user->unique_key($username), '=', $username)->find();
		}

		// If the passwords match, perform a login
		if ($user->loaded() AND $this->verify($password, (string) $user->password)
			AND $user->has('roles', ORM::factory('Role', array('name' => 'login'))))
		{
			if ($this->needs_rehash($user->password))
			{
				// The model filter hashes the password; complete_login() saves it
				$user->password = $password;

				if ( ! $this->verify($password, (string) $user->password))
				{
					// The model has no password filter
					$user->password = $this->hash($password);
				}
			}

			if ($remember === TRUE)
			{
				// Token data
				$data = array(
					'user_id'    => $user->pk(),
					'expires'    => time() + $this->_config['lifetime'],
					'user_agent' => sha1((string) Request::$user_agent),
				);

				// Create a new autologin token
				$token = ORM::factory('User_Token')
							->values($data, array_keys($data))
							->create();

				// Set the autologin cookie
				$this->_set_autologin_cookie($token->plain_token(), $this->_config['lifetime']);
			}

			// Finish the login
			$this->complete_login($user);

			return TRUE;
		}

		// Login failed
		return FALSE;
	}

	/**
	 * Forces a user to be logged in, without specifying a password.
	 *
	 * @param   mixed    $user                    username string, or user ORM object
	 * @param   boolean  $mark_session_as_forced  mark the session as forced
	 * @return  boolean
	 */
	public function force_login($user, $mark_session_as_forced = FALSE)
	{
		if ( ! is_object($user))
		{
			$username = $user;

			// Load the user
			$user = ORM::factory('User');
			$user->where($user->unique_key($username), '=', $username)->find();
		}

		if ($mark_session_as_forced === TRUE)
		{
			// Mark the session as forced, to prevent users from changing account information
			$this->_session->set('auth_forced', TRUE);
		}

		// Run the standard completion
		$this->complete_login($user);
	}

	/**
	 * Logs a user in, based on the authautologin cookie.
	 *
	 * @return  mixed
	 */
	public function auto_login()
	{
		if ($token = $this->_get_autologin_cookie())
		{
			// Load the token and user
			$token = ORM::factory('User_Token', array('token' => Model_User_Token::hash_token($token)));

			if ($token->loaded() AND $token->user->loaded())
			{
				if (hash_equals((string) $token->user_agent, sha1((string) Request::$user_agent)))
				{
					// A token works once, so a copied cookie fails after the next use
					$token->rotate();

					$this->_set_autologin_cookie($token->plain_token(), $token->expires - time());

					// Complete the login with the found data
					$this->complete_login($token->user);

					// Automatic login was successful
					return $token->user;
				}

				// Token is invalid
				$token->delete();
			}
		}

		return FALSE;
	}

	/**
	 * Gets the currently logged in user from the session (with auto_login check).
	 * Returns $default if no user is currently logged in.
	 *
	 * @param   mixed    $default to return in case user isn't logged in
	 * @return  mixed
	 */
	public function get_user($default = NULL)
	{
		$user = parent::get_user($default);

		if ($user === $default)
		{
			// check for "remembered" login
			if (($user = $this->auto_login()) === FALSE)
				return $default;
		}

		return $user;
	}

	/**
	 * Log a user out and remove any autologin cookies.
	 *
	 * @param   boolean  $destroy     completely destroy the session
	 * @param	boolean  $logout_all  remove all tokens for user
	 * @return  boolean
	 */
	public function logout($destroy = FALSE, $logout_all = FALSE)
	{
		// Set by force_login()
		$this->_session->delete('auth_forced');

		if ($token = $this->_get_autologin_cookie())
		{
			// Delete the autologin cookie to prevent re-login
			$this->_delete_autologin_cookie();

			// Clear the autologin token from the database
			$token = ORM::factory('User_Token', array('token' => Model_User_Token::hash_token($token)));

			if ($token->loaded() AND $logout_all)
			{
				// Delete all user tokens. This isn't the most elegant solution but does the job
				$tokens = ORM::factory('User_Token')->where('user_id','=',$token->user_id)->find_all();
				
				foreach ($tokens as $_token)
				{
					$_token->delete();
				}
			}
			elseif ($token->loaded())
			{
				$token->delete();
			}
		}

		return parent::logout($destroy);
	}

	/**
	 * @return  string  autologin token from the cookie, or NULL
	 */
	protected function _get_autologin_cookie()
	{
		return Cookie::get('authautologin');
	}

	/**
	 * @param   string   $token     autologin token
	 * @param   integer  $lifetime  cookie lifetime in seconds
	 * @return  void
	 */
	protected function _set_autologin_cookie($token, $lifetime)
	{
		Cookie::set('authautologin', $token, $lifetime);
	}

	/**
	 * @return  void
	 */
	protected function _delete_autologin_cookie()
	{
		Cookie::delete('authautologin');
	}

	/**
	 * Get the stored password for a username.
	 *
	 * @param   mixed   $user  username string, or user ORM object
	 * @return  string
	 */
	public function password($user)
	{
		if ( ! is_object($user))
		{
			$username = $user;

			// Load the user
			$user = ORM::factory('User');
			$user->where($user->unique_key($username), '=', $username)->find();
		}

		return $user->password;
	}

	/**
	 * Complete the login for a user by incrementing the logins and setting
	 * session data: user_id, username, roles.
	 *
	 * @param   object  $user  user ORM object
	 * @return  void
	 */
	protected function complete_login($user)
	{
		$user->complete_login();

		return parent::complete_login($user);
	}

	/**
	 * Compare a plain text password with the stored hash. Works for current (logged in) user
	 *
	 * @param   string  $password
	 * @return  boolean
	 */
	public function check_password($password)
	{
		$user = $this->get_user();

		if ( ! $user)
			return FALSE;

		return $this->verify($password, (string) $user->password);
	}

} // End Auth ORM
