<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Security helper class.
 *
 * @package    Kohana
 * @category   Security
 * @author     Kohana Team
 * @copyright  (c) 2007-2012 Kohana Team
 * @license    http://kohanaframework.org/license
 */
class Kohana_Security {

	/**
	 * @var  string  key name used for token storage
	 */
	public static $token_name = 'security_token';

	/**
	 * Generate and store a unique token which can be used to help prevent
	 * [CSRF](http://wikipedia.org/wiki/Cross_Site_Request_Forgery) attacks.
	 *
	 *     $token = Security::token();
	 *
	 * You can insert this token into your forms as a hidden field:
	 *
	 *     echo Form::hidden('csrf', Security::token());
	 *
	 * And then check it when using [Validation]:
	 *
	 *     $array->rules('csrf', array(
	 *         array('not_empty'),
	 *         array('Security::check'),
	 *     ));
	 *
	 * This provides a basic, but effective, method of preventing CSRF attacks.
	 *
	 * @param   boolean $new    force a new token to be generated?
	 * @return  string
	 * @uses    Session::instance
	 */
	public static function token($new = FALSE)
	{
		$session = Session::instance();

		// Get the current token
		$token = $session->get(Security::$token_name);

		if ($new === TRUE OR ! $token)
		{
			// Generate a new token from the CSPRNG
			$token = base64_encode(random_bytes(32));

			// Store the new token
			$session->set(Security::$token_name, $token);
		}

		return $token;
	}

	/**
	 * Check that the given token matches the currently stored security token.
	 *
	 *     if (Security::check($token))
	 *     {
	 *         // Pass
	 *     }
	 *
	 * @param   string  $token  token to check
	 * @return  boolean FALSE also for non-string input, such as an array from `token[]=`
	 * @uses    Security::token
	 */
	public static function check($token)
	{
		return Security::slow_equals(Security::token(), $token);
	}

	/**
	 * Compare two hashes in a time-invariant manner.
	 * Prevents cryptographic side-channel attacks (timing attacks, specifically)
	 *
	 * @param   string  $a  cryptographic hash
	 * @param   string  $b  cryptographic hash
	 * @return  boolean FALSE when one of the values is not a string
	 */
	public static function slow_equals($a, $b)
	{
		if ( ! is_string($a) OR ! is_string($b))
			return FALSE;

		return hash_equals($a, $b);
	}


	/**
	 * Replace each `<img>` and `<image>` tag with its escaped `src` value.
	 * This is not an HTML sanitizer: other tags stay. Use HTML Purifier for
	 * untrusted HTML. See https://github.com/kohana/kohana/issues/107
	 *
	 *     $str = Security::strip_image_tags($str);
	 *
	 * @deprecated since version 3.3.6
	 * @param   string  $str    string to sanitize
	 * @return  string
	 */
	public static function strip_image_tags($str)
	{
		$str = (string) $str;

		// Repeat until stable, because a removal can join the parts of a new tag
		do
		{
			$result = preg_replace_callback('#<(?:img|image)\b([^>]*)>?#i', function ($tag)
			{
				if (preg_match('#(?:^|[\s/])src\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))#i', $tag[1], $src))
				{
					// The source never contains "<" after escaping
					return HTML::chars(implode('', array_slice($src, 1)), FALSE);
				}

				return '';
			}, $str, -1, $count);

			if ($result === NULL)
			{
				// Fail closed on a PCRE error
				return '';
			}

			$str = $result;
		}
		while ($count > 0);

		return $str;
	}

	/**
	 * Encodes PHP tags in a string.
	 *
	 *     $str = Security::encode_php_tags($str);
	 *
	 * @param   string  $str    string to sanitize
	 * @return  string
	 */
	public static function encode_php_tags($str)
	{
		return str_replace(array('<?', '?>'), array('&lt;?', '?&gt;'), $str);
	}

}
