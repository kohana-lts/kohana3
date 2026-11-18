<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Default auth user toke
 *
 * @package    Kohana/Auth
 * @author     Kohana Team
 * @copyright  (c) 2007-2012 Kohana Team
 * @license    http://kohanaframework.org/license
 */
class Model_Auth_User_Token extends ORM {

	// Relationships
	protected $_belongs_to = array(
		'user' => array('model' => 'User'),
	);
	
	protected $_created_column = array(
		'column' => 'created',
		'format' => TRUE,
	);

	/**
	 * @var string token for the cookie, set by create() and rotate() only.
	 *             The "token" column keeps [Model_Auth_User_Token::hash_token].
	 */
	protected $_plain_token;

	/**
	 * Returns the value that the "token" column keeps for a cookie token,
	 * so a database leak does not give valid cookies. 40 characters fit
	 * the column of the auth schema.
	 *
	 * @param   string  $token  token from the cookie
	 * @return  string
	 */
	public static function hash_token($token)
	{
		return substr(hash('sha256', (string) $token), 0, 40);
	}

	/**
	 * Handles garbage collection and deleting of expired objects.
	 *
	 * @return  void
	 */
	public function __construct($id = NULL)
	{
		parent::__construct($id);

		if (mt_rand(1, 100) === 1)
		{
			// Do garbage collection
			$this->delete_expired();
		}

		if ($this->expires < time() AND $this->_loaded)
		{
			// This object has expired
			$this->delete();
		}
	}

	/**
	 * Deletes all expired tokens.
	 *
	 * @return  ORM
	 */
	public function delete_expired()
	{
		// Delete all expired tokens
		DB::delete($this->_table_name)
			->where('expires', '<', time())
			->execute($this->_db);

		return $this;
	}

	public function create(?Validation $validation = NULL)
	{
		$this->_set_new_token();

		return parent::create($validation);
	}

	/**
	 * Replaces the token with a new one and saves it. The old cookie value
	 * stops working.
	 *
	 * @return  $this
	 */
	public function rotate()
	{
		$this->_set_new_token();

		return $this->update();
	}

	/**
	 * @return  string  token for the cookie, or NULL when the model did not create it
	 */
	public function plain_token()
	{
		return $this->_plain_token;
	}

	protected function _set_new_token()
	{
		$this->_plain_token = $this->create_token();
		$this->token = static::hash_token($this->_plain_token);
	}

	/**
	 * @return  string  new random token for the cookie
	 */
	protected function create_token()
	{
		do
		{
			$token = bin2hex(random_bytes(20));
		}
		while (ORM::factory('User_Token', array('token' => static::hash_token($token)))->loaded());

		return $token;
	}

} // End Auth User Token Model
