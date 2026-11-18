<?php defined('SYSPATH') OR die('No direct script access.');

return array(

	'default' => array(
		/**
		 * The following options must be set:
		 *
		 * string   key     secret key: use at least 32 random bytes, for example
		 *                  base64_encode(random_bytes(32))
		 *
		 * The following options are only for values that the old mcrypt
		 * version of Encrypt wrote. These values have no MAC. Disable
		 * "legacy" when the migration is complete.
		 *
		 * boolean  legacy  decode values from the mcrypt version
		 * string   cipher  mcrypt cipher of these values (only "rijndael-128")
		 * string   mode    mcrypt mode of these values: nofb, cbc, ncfb, cfb or ecb
		 */
		'legacy' => FALSE,
		'cipher' => 'rijndael-128',
		'mode'   => 'nofb',
	),

);
