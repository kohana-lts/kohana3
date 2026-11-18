<?php defined('SYSPATH') OR die('No direct access allowed.');

return array(

	'driver'       => 'File',

	// Algorithm and options for password_hash(). FALSE uses the legacy HMAC hash.
	'password_algo'    => PASSWORD_DEFAULT,
	'password_options' => array(),

	// Only to verify legacy HMAC hashes, which login replaces (Auth_ORM)
	'hash_method'  => 'sha256',
	'hash_key'     => NULL,
	'lifetime'     => 1209600,
	'session_type' => Session::$default,
	'session_key'  => 'auth_user',

	// Username/password combinations for the Auth File driver
	'users' => array(
		// 'admin' => Auth::instance()->hash('password') output, for example
		// '$2y$12$...',
	),

);
