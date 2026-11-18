<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * UTF8::strlen
 *
 * @package    Kohana
 * @author     Kohana Team
 * @copyright  (c) 2007-2012 Kohana Team
 * @copyright  (c) 2005 Harry Fuecks
 * @license    http://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt
 */
function _strlen($str)
{
	if (UTF8::is_ascii($str))
		return strlen($str);

	// Count all bytes except UTF-8 continuation bytes (utf8_decode() is deprecated)
	return strlen(preg_replace('/[\x80-\xBF]/', '', $str));
}
