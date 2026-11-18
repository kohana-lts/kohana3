<?php defined('SYSPATH') OR die('Kohana bootstrap needs to be included before tests run');

/**
 * @group kohana
 * @group kohana.codebench
 */
class Codebench_ControllerTest extends Unittest_TestCase
{
	/**
	 * The application default route comes first, so route to codebench directly
	 */
	protected function request($uri)
	{
		return Request::factory($uri, array(), FALSE, array(Route::get('codebench')))->execute();
	}

	public function test_class_name_is_escaped()
	{
		$response = $this->request('codebench/"><svg onload=alert(1)>');

		$this->assertStringNotContainsString('<svg', $response->body());
		$this->assertStringContainsString('&quot;&gt;&lt;svg', $response->body());
	}

	/**
	 * Log is autoloadable but is not a Codebench, so it must not run
	 */
	public function test_only_codebench_classes_run()
	{
		$response = $this->request('codebench/Log');

		$this->assertSame(200, $response->status());
		$this->assertStringContainsString('Library not found', $response->body());
	}

	public function test_codebench_class_runs()
	{
		$response = $this->request('codebench/Bench_ValidColor');

		$this->assertSame(200, $response->status());
		$this->assertStringNotContainsString('Library not found', $response->body());
	}
}
