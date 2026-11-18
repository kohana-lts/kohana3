<?php defined('SYSPATH') OR die('Kohana bootstrap needs to be included before tests run');

/**
 * The userguide and codebench modules must not add routes in production
 * unless the config allows it.
 *
 * @group kohana
 * @group kohana.userguide
 */
class Userguide_ProductionRoutesTest extends Unittest_TestCase
{
	protected $routes;
	protected $environment;

	protected function setUp(): void
	{
		parent::setUp();

		$this->routes = Route::all();
		$this->environment = Kohana::$environment;
	}

	protected function tearDown(): void
	{
		$this->set_routes($this->routes);
		Kohana::$environment = $this->environment;
		Kohana::$config->load('userguide')->set('production', FALSE);
		Kohana::$config->load('codebench')->set('production', FALSE);

		parent::tearDown();
	}

	protected function set_routes(array $routes)
	{
		$property = new ReflectionProperty('Route', '_routes');
		$property->setValue(NULL, $routes);
	}

	public function provider_modules()
	{
		return array(
			array('userguide', 'docs/media'),
			array('userguide', 'docs/guide'),
			array('codebench', 'codebench'),
		);
	}

	/**
	 * @dataProvider provider_modules
	 */
	public function test_no_routes_in_production($module, $route)
	{
		$this->set_routes(array());
		Kohana::$environment = Kohana::PRODUCTION;

		include MODPATH.$module.'/init.php';

		$this->assertArrayNotHasKey($route, Route::all());
	}

	/**
	 * @dataProvider provider_modules
	 */
	public function test_routes_in_production_with_opt_in($module, $route)
	{
		$this->set_routes(array());
		Kohana::$environment = Kohana::PRODUCTION;
		Kohana::$config->load($module)->set('production', TRUE);

		include MODPATH.$module.'/init.php';

		$this->assertArrayHasKey($route, Route::all());
	}

	/**
	 * @dataProvider provider_modules
	 */
	public function test_routes_in_development($module, $route)
	{
		$this->set_routes(array());
		Kohana::$environment = Kohana::DEVELOPMENT;

		include MODPATH.$module.'/init.php';

		$this->assertArrayHasKey($route, Route::all());
	}
}
