<?php defined('SYSPATH') OR die('No direct script access.');

return array(

	'cookie' => array(
		/**
		 * The client keeps the data of a cookie session.
		 *
		 * boolean  encrypted        encrypt the data with Encrypt (needs an encryption key)
		 * mixed    allowed_classes  classes that unserialize() may create: TRUE (all),
		 *                           FALSE (none) or an array of class names.
		 *                           Default for cookie sessions: array('Model_User')
		 * integer  max_age          seconds a cookie session can stay idle. NULL uses the
		 *                           lifetime, or one day for a lifetime of 0. 0 disables it.
		 */
		'encrypted' => FALSE,
	),

);
