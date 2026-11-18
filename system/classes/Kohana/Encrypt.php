<?php defined('SYSPATH') OR die('No direct script access.');

// Configurations written for the mcrypt version of this class use these.
defined('MCRYPT_RIJNDAEL_128') OR define('MCRYPT_RIJNDAEL_128', 'rijndael-128');
defined('MCRYPT_MODE_CBC') OR define('MCRYPT_MODE_CBC', 'cbc');
defined('MCRYPT_MODE_CFB') OR define('MCRYPT_MODE_CFB', 'cfb');
defined('MCRYPT_MODE_ECB') OR define('MCRYPT_MODE_ECB', 'ecb');
defined('MCRYPT_MODE_NOFB') OR define('MCRYPT_MODE_NOFB', 'nofb');
defined('MCRYPT_MODE_NCFB') OR define('MCRYPT_MODE_NCFB', 'ncfb');

/**
 * The Encrypt library provides authenticated two-way encryption of text and
 * binary strings with OpenSSL.
 *
 * [Encrypt::encode] uses AES-256-CBC with a random IV, then signs the IV and
 * ciphertext with HMAC-SHA256 (encrypt-then-MAC). [Encrypt::decode] returns
 * FALSE for any value that was changed. Both sub-keys come from the configured
 * key through HKDF, so the key must be a long random secret.
 *
 * The mcrypt version of this class had no MAC. To read values that it wrote,
 * set "legacy" => TRUE in the configuration group, together with the old
 * "cipher" and "mode". Only the rijndael-128 cipher is supported. Disable it
 * again after the migration: legacy values are not authenticated.
 *
 * @package    Kohana
 * @category   Security
 * @author     Kohana Team
 * @copyright  (c) 2007-2012 Kohana Team
 * @license    http://kohanaframework.org/license
 */
class Kohana_Encrypt {

	/**
	 * Prefix of values from [Encrypt::encode]
	 */
	const PREFIX = 'v2:';

	const CIPHER = 'aes-256-cbc';

	const IV_SIZE = 16;

	const MAC_SIZE = 32;

	/**
	 * @var  string  default instance name
	 */
	public static $default = 'default';

	/**
	 * @var  array  Encrypt class instances
	 */
	public static $instances = array();

	/**
	 * @var  string  encryption sub-key
	 */
	protected $_encryption_key;

	/**
	 * @var  string  authentication sub-key
	 */
	protected $_mac_key;

	/**
	 * @var  string  configured key, used only for legacy values
	 */
	protected $_key;

	/**
	 * @var  string  mcrypt mode, used only for legacy values
	 */
	protected $_mode;

	/**
	 * @var  string  mcrypt cipher, used only for legacy values
	 */
	protected $_cipher;

	/**
	 * @var  boolean  decode values from the mcrypt version of this class
	 */
	protected $_legacy = FALSE;

	/**
	 * Returns a singleton instance of Encrypt. An encryption key must be
	 * provided in your "encrypt" configuration file.
	 *
	 *     $encrypt = Encrypt::instance();
	 *
	 * @param   string  $name   configuration group name
	 * @return  Encrypt
	 */
	public static function instance($name = NULL)
	{
		if ($name === NULL)
		{
			// Use the default instance name
			$name = Encrypt::$default;
		}

		if ( ! isset(Encrypt::$instances[$name]))
		{
			// Load the configuration data
			$config = Kohana::$config->load('encrypt')->$name;

			if ( ! isset($config['key']) OR (string) $config['key'] === '')
			{
				// No default encryption key is provided!
				throw new Kohana_Exception('No encryption key is defined in the encryption configuration group: :group',
					array(':group' => $name));
			}

			$config += array(
				'mode'   => MCRYPT_MODE_NOFB,
				'cipher' => MCRYPT_RIJNDAEL_128,
				'legacy' => FALSE,
			);

			// Create a new instance
			Encrypt::$instances[$name] = new Encrypt($config['key'], $config['mode'], $config['cipher'], $config['legacy']);
		}

		return Encrypt::$instances[$name];
	}

	/**
	 * Creates a new encrypt object.
	 *
	 * @param   string   $key     encryption key
	 * @param   string   $mode    mcrypt mode of legacy values
	 * @param   string   $cipher  mcrypt cipher of legacy values
	 * @param   boolean  $legacy  decode values from the mcrypt version of this class
	 * @throws  Kohana_Exception
	 */
	public function __construct($key, $mode = MCRYPT_MODE_NOFB, $cipher = MCRYPT_RIJNDAEL_128, $legacy = FALSE)
	{
		$key = (string) $key;

		if ($key === '')
			throw new Kohana_Exception('The encryption key must not be empty');

		$this->_encryption_key = hash_hkdf('sha256', $key, 32, 'kohana-encrypt-aes-256-cbc');
		$this->_mac_key        = hash_hkdf('sha256', $key, 32, 'kohana-encrypt-hmac-sha256');

		$this->_key    = $key;
		$this->_mode   = $mode;
		$this->_cipher = $cipher;
		$this->_legacy = (bool) $legacy;
	}

	/**
	 * Encrypts and signs a string. The result is a printable string, safe
	 * for cookies and URLs once URL encoded.
	 *
	 *     $data = $encrypt->encode($data);
	 *
	 * @param   string  $data   data to be encrypted
	 * @return  string
	 * @throws  Kohana_Exception
	 */
	public function encode($data)
	{
		$iv = $this->_create_iv();

		$encrypted = openssl_encrypt((string) $data, Encrypt::CIPHER, $this->_encryption_key, OPENSSL_RAW_DATA, $iv);

		if ($encrypted === FALSE)
			throw new Kohana_Exception('Could not encrypt the data');

		$mac = hash_hmac('sha256', Encrypt::PREFIX.$iv.$encrypted, $this->_mac_key, TRUE);

		return Encrypt::PREFIX.base64_encode($iv.$encrypted.$mac);
	}

	/**
	 * Verifies and decrypts a string from [Encrypt::encode].
	 *
	 *     $data = $encrypt->decode($data);
	 *
	 * @param   string  $data   encoded string to be decrypted
	 * @return  string|FALSE    FALSE if the value is not valid or was changed
	 */
	public function decode($data)
	{
		if ( ! is_string($data))
			return FALSE;

		if (strncmp($data, Encrypt::PREFIX, strlen(Encrypt::PREFIX)) !== 0)
			return $this->_legacy ? $this->_decode_legacy($data) : FALSE;

		$raw = base64_decode(substr($data, strlen(Encrypt::PREFIX)), TRUE);

		// The ciphertext has at least one block
		if ($raw === FALSE OR strlen($raw) < Encrypt::IV_SIZE + 16 + Encrypt::MAC_SIZE)
			return FALSE;

		$iv        = substr($raw, 0, Encrypt::IV_SIZE);
		$encrypted = substr($raw, Encrypt::IV_SIZE, -Encrypt::MAC_SIZE);
		$mac       = substr($raw, -Encrypt::MAC_SIZE);

		$expected = hash_hmac('sha256', Encrypt::PREFIX.$iv.$encrypted, $this->_mac_key, TRUE);

		if ( ! hash_equals($expected, $mac))
			return FALSE;

		return openssl_decrypt($encrypted, Encrypt::CIPHER, $this->_encryption_key, OPENSSL_RAW_DATA, $iv);
	}

	/**
	 * Decrypts a value from the mcrypt version of this class with OpenSSL.
	 * These values have no MAC, so an attacker can change them.
	 *
	 * @param   string  $data   base64 of IV and ciphertext
	 * @return  string|FALSE
	 */
	protected function _decode_legacy($data)
	{
		if ($this->_cipher !== MCRYPT_RIJNDAEL_128)
			return FALSE;

		$modes = array(
			MCRYPT_MODE_NOFB => array('ofb', TRUE),
			MCRYPT_MODE_CBC  => array('cbc', TRUE),
			MCRYPT_MODE_NCFB => array('cfb', TRUE),
			MCRYPT_MODE_CFB  => array('cfb8', TRUE),
			MCRYPT_MODE_ECB  => array('ecb', FALSE),
		);

		if ( ! isset($modes[$this->_mode]))
			return FALSE;

		list($mode, $has_iv) = $modes[$this->_mode];

		$data = base64_decode($data, TRUE);

		if ( ! $data)
			return FALSE;

		$iv = '';

		if ($has_iv)
		{
			if (strlen($data) < 16)
				return FALSE;

			$iv   = substr($data, 0, 16);
			$data = substr($data, 16);
		}
		elseif (strlen($data) >= 16)
		{
			// mcrypt wrote an unused IV in ECB mode too
			$data = substr($data, 16);
		}

		$key = $this->_legacy_key($this->_key);
		$cipher = 'aes-'.(strlen($key) * 8).'-'.$mode;

		$plain = openssl_decrypt($data, $cipher, $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);

		if ($plain === FALSE)
			return FALSE;

		// mcrypt padded with \0 bytes
		return rtrim($plain, "\0");
	}

	/**
	 * Pads or cuts a key as mcrypt did for rijndael-128 (16, 24 or 32 bytes).
	 *
	 * @param   string  $key
	 * @return  string
	 */
	protected function _legacy_key($key)
	{
		foreach (array(16, 24, 32) as $size)
		{
			if (strlen($key) <= $size)
				return str_pad($key, $size, "\0");
		}

		return substr($key, 0, 32);
	}

	/**
	 * Returns a random initialization vector.
	 *
	 * @return  string
	 */
	protected function _create_iv()
	{
		return random_bytes(Encrypt::IV_SIZE);
	}

}
