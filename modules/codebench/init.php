<?php defined('SYSPATH') or die('No direct script access.');

// Catch-all route for Codebench classes to run
// Codebench runs code on request, so production needs an explicit opt-in
if (Kohana::$environment !== Kohana::PRODUCTION OR Kohana::$config->load('codebench.production') === TRUE)
{
	Route::set('codebench', 'codebench(/<class>)')
		->defaults(array(
			'controller' => 'Codebench',
			'action' => 'index',
			'class' => NULL));
}
